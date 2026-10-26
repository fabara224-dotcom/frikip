<?php

namespace App\Console\Commands;

use App\Terrain\Growth\Payments;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Throwable;

/**
 * Redemande à CinetPay l'état des paiements restés « en attente » (notification perdue, client qui a fermé la page…).
 * Lancé automatiquement toutes les 10 minutes par le planificateur.
 */
class CheckPayments extends Command
{
    protected $signature = 'terrain:paiements';

    protected $description = 'Vérifie auprès de CinetPay les paiements en attente des dernières 48 heures';

    public function handle(Payments $payments): int
    {
        $this->cleanMontages();
        if (! Payments::live()) {
            $this->info('CinetPay non configuré : rien à vérifier.');

            return self::SUCCESS;
        }
        $n = 0;
        $rows = DB::table('payments')->where('provider', 'cinetpay')->where('status', 'pending')
            ->where('created_at', '>', now()->subHours(48))->where('created_at', '<', now()->subMinutes(2))->limit(100)->get();
        foreach ($rows as $p) {
            try {
                $after = $payments->verify($p);
                $n += $after->status === 'accepted' ? 1 : 0;
            } catch (Throwable $e) {
                $this->warn($p->reference.' : '.$e->getMessage());
            }
        }
        // Au-delà de 48 h sans réponse : le paiement est abandonné
        DB::table('payments')->where('status', 'pending')->where('created_at', '<', now()->subHours(48))->update(['status' => 'expired', 'updated_at' => now()]);
        $this->info(count($rows).' paiement(s) vérifié(s), '.$n.' confirmé(s).');

        return self::SUCCESS;
    }

    /** Vidéos brutes des montages : effacées 30 jours après la livraison ; commandes jamais payées annulées après 7 jours. */
    private function cleanMontages(): void
    {
        try {
            DB::table('video_orders')->where('status', 'attente_paiement')->where('created_at', '<', now()->subDays(7))
                ->update(['status' => 'annulee', 'admin_note' => 'Annulée automatiquement : paiement non reçu.', 'updated_at' => now()]);
            $old = DB::table('video_orders')->where(fn ($q) => $q->where(fn ($q) => $q->where('status', 'livree')->where('delivered_at', '<', now()->subDays(30)))
                ->orWhere(fn ($q) => $q->where('status', 'annulee')->where('updated_at', '<', now()->subDays(7))))->whereNotNull('files')->where('files', '!=', '[]')->get();
            foreach ($old as $o) {
                $dir = storage_path("app/montages/{$o->reference}");
                foreach (glob($dir.'/*') ?: [] as $f) {
                    @unlink($f);
                }
                @rmdir($dir);
                DB::table('video_orders')->where('id', $o->id)->update(['files' => '[]']);
            }
        } catch (Throwable) {
        }
    }
}
