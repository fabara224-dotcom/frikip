<?php

namespace App\Console\Commands;

use App\Terrain\DataSource;
use App\Terrain\Growth\Notifier;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/** Alertes recruteur : prévient des nouveaux profils correspondant aux recherches enregistrées (1 fois par jour). */
class SendAlerts extends Command
{
    protected $signature = 'terrain:alertes';

    protected $description = 'Envoie aux recruteurs les nouveaux profils correspondant à leurs alertes';

    public function handle(DataSource $data, Notifier $notifier): int
    {
        $sent = 0;
        foreach (DB::table('saved_searches')->get() as $s) {
            $f = json_decode($s->filters, true) ?: [];
            $seen = json_decode($s->seen_ids ?? '[]', true) ?: [];
            $found = $data->athletes($f);
            $new = array_values(array_filter($found, fn ($a) => ! in_array($a['id'], $seen, true)));
            if ($new) {
                $names = implode(', ', array_slice(array_column($new, 'name'), 0, 3)).(count($new) > 3 ? '…' : '');
                $notifier->send($s->user_key, 'alert', count($new).' nouveau(x) profil(s) : '.$s->label, $names,
                    route('search', array_filter($f, fn ($v) => ! is_bool($v)) + (! empty($f['verified']) ? ['verified' => 1] : [])));
                $sent++;
            }
            DB::table('saved_searches')->where('id', $s->id)->update(['seen_ids' => json_encode(array_column($found, 'id')), 'last_sent_at' => now(), 'updated_at' => now()]);
        }
        $this->info("{$sent} alerte(s) envoyée(s).");

        return self::SUCCESS;
    }
}
