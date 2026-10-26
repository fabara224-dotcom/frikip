<?php

namespace App\Http\Controllers;

use App\Terrain\DataSource;
use App\Terrain\Growth\Members;
use App\Terrain\Growth\Notifier;
use App\Terrain\Growth\Payments;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use Throwable;

/**
 * Services vidéo : le sportif commande un montage de sa compilation, envoie ses vidéos brutes (« rushs »)
 * et paie par Mobile Money. L'équipe suit les commandes dans l'espace admin et livre le montage.
 */
class VideoServiceController extends Controller
{
    public const STATUS = [
        'attente_paiement' => 'En attente de paiement', 'payee' => 'Payée – montage à faire', 'en_cours' => 'Montage en cours',
        'livree' => 'Livrée', 'annulee' => 'Annulée',
    ];

    public function __construct(private DataSource $data)
    {
    }

    public static function packages(): array
    {
        return Payments::plans('montage');
    }

    public function index(): View
    {
        return view('web.video-services', ['packages' => self::packages()]);
    }

    public function create(int $package): View
    {
        abort_unless(isset(self::packages()[$package]), 404);
        $m = app(Members::class)->current();

        return view('web.video-order', ['i' => $package, 'pack' => self::packages()[$package], 'member' => $m,
            'chunk' => PublishController::CHUNK, 'maxMb' => PublishController::MAX_MB]);
    }

    public function store(Request $request): JsonResponse
    {
        $count = count(self::packages());
        try {
            $data = $request->validate([
                'package' => ['required', 'integer', 'min:0', 'max:'.($count - 1)],
                'notes' => ['nullable', 'string', 'max:1500'],
                'whatsapp' => ['required', 'string', 'max:30'],
                'rush_link' => ['nullable', 'url', 'max:500'],
                'uploads' => ['nullable', 'array', 'max:5'],
                'uploads.*.id' => ['required', 'string', 'max:40'],
                'uploads.*.name' => ['required', 'string', 'max:200'],
                'method' => ['required', 'in:om,momo,card'],
                'phone' => ['required', 'string', 'max:30', function ($attr, $value, $fail) {
                    if (strlen(preg_replace('/\D/', '', (string) $value)) < 8) {
                        $fail(__('Entre un numéro valide (8 chiffres minimum).'));
                    }
                }],
            ], ['whatsapp.required' => __('Indique ton numéro WhatsApp pour qu’on puisse te joindre.'), 'rush_link.url' => __('Le lien doit commencer par https://')]);
            if (empty($data['uploads']) && empty($data['rush_link'])) {
                throw ValidationException::withMessages(['uploads' => __('Envoie au moins une vidéo, ou colle un lien vers tes vidéos.')]);
            }
            $pay = app(PaymentController::class);
            if ($closed = PaymentController::countryClosedMessage()) {
                throw ValidationException::withMessages(['phone' => $closed]);
            }
            if (! Payments::live() && ! $pay->simulationAllowed()) {
                throw ValidationException::withMessages(['phone' => __('Le paiement en ligne n’est pas encore ouvert sur ce site. Réessaie bientôt.')]);
            }
            $u = $this->data->user();
            $ref = 'MT'.now()->format('ymd').Str::upper(Str::random(6));
            // Rushs rassemblés et rangés dans storage/app/montages/<référence>/
            $files = [];
            $dir = storage_path("app/montages/{$ref}");
            foreach ($data['uploads'] ?? [] as $n => $up) {
                $f = app(PublishController::class)->assembled($up['id'], $up['name']);
                if (! is_dir($dir)) {
                    mkdir($dir, 0755, true);
                }
                $name = 'video-'.($n + 1).'.'.strtolower($f->getClientOriginalExtension() ?: 'mp4');
                rename($f->getPathname(), "{$dir}/{$name}");
                $files[] = ['file' => $name, 'original' => Str::limit($up['name'], 120, ''), 'size' => filesize("{$dir}/{$name}")];
            }
        } catch (ValidationException $e) {
            return response()->json(['error' => collect($e->errors())->flatten()->first()], 422);
        }

        app(Members::class)->touch();
        $p = app(Payments::class)->create($u['id'], 'montage', (int) $data['package'], $data['method'], $data['phone']);
        [$label] = self::packages()[(int) $data['package']];
        DB::table('video_orders')->insert([
            'reference' => $ref, 'user_key' => $u['id'], 'athlete_id' => $u['athlete_id'] ?? null, 'package' => (int) $data['package'],
            'package_label' => $label, 'amount' => $p->amount, 'currency' => $p->currency, 'notes' => $data['notes'] ?? null,
            'whatsapp' => Notifier::e164($data['whatsapp']), 'rush_link' => $data['rush_link'] ?? null, 'files' => json_encode($files),
            'payment_ref' => $p->reference, 'status' => 'attente_paiement', 'created_at' => now(), 'updated_at' => now(),
        ]);
        try {
            return response()->json(['ok' => true, 'url' => $pay->checkoutUrl($p)]);
        } catch (Throwable $e) {
            return response()->json(['error' => $e->getMessage(), 'url' => route('video.orders')], 502);
        }
    }

    /** Reprendre le paiement d'une commande pas encore payée. */
    public function pay(string $ref): \Illuminate\Http\RedirectResponse
    {
        $o = DB::table('video_orders')->where('reference', $ref)->where('user_key', $this->data->user()['id'])->where('status', 'attente_paiement')->first();
        abort_unless($o, 404);
        $old = app(Payments::class)->find((string) $o->payment_ref);
        $p = app(Payments::class)->create($o->user_key, 'montage', (int) $o->package, $old->method ?? 'om', $old->phone ?? (string) $o->whatsapp);
        DB::table('video_orders')->where('id', $o->id)->update(['payment_ref' => $p->reference, 'updated_at' => now()]);
        try {
            return redirect()->away(app(PaymentController::class)->checkoutUrl($p));
        } catch (Throwable $e) {
            return back()->with('status', $e->getMessage());
        }
    }

    public function orders(): View
    {
        $rows = DB::table('video_orders')->where('user_key', $this->data->user()['id'])->orderByDesc('id')->get();

        return view('web.video-orders', ['rows' => $rows]);
    }
}
