<?php

namespace App\Providers;

use App\Terrain\DataSource;
use App\Terrain\DemoData;
use App\Terrain\Messaging\DemoMessenger;
use App\Terrain\Messaging\Messenger;
use App\Terrain\Messaging\SupabaseMessenger;
use App\Terrain\SupabaseData;
use Throwable;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        // Sans SUPABASE_URL / SUPABASE_ANON_KEY dans .env : mode démo avec les données d'exemple.
        $this->app->singleton(DataSource::class, function () {
            if (config('terrain.supabase_url') && config('terrain.supabase_anon_key')) {
                return new SupabaseData;
            }

            // Sans Supabase : vrais comptes enregistrés dans la base du site
            return new \App\Terrain\LocalData;
        });

        // Compteurs réels (j'aime, commentaires, partages, vues, abonnés) et sous-titres : un seul objet par requête
        $this->app->singleton(\App\Terrain\Growth\Engagement::class);
        $this->app->singleton(\App\Terrain\Growth\Subtitles::class);

        // Messagerie et appels : même source que les données
        $this->app->singleton(Messenger::class, function ($app) {
            $data = $app->make(DataSource::class);

            return match (true) {
                $data instanceof SupabaseData => new SupabaseMessenger($data),
                $data instanceof \App\Terrain\LocalData => new \App\Terrain\Messaging\LocalMessenger($data),
                default => new DemoMessenger($data),
            };
        });
    }

    public function boot(): void
    {
        // @td($texte) : affiche un contenu (titre de vidéo, poste, pays…) dans la langue de la page
        \Illuminate\Support\Facades\Blade::directive('td', fn ($e) => "<?php echo e(\\App\\Terrain\\DataLang::t({$e})); ?>");

        // Toutes les vues connaissent l'utilisateur connecté ($me) et le mode ($demo).
        View::composer('*', function ($view) {
            static $shared = null;
            if ($shared === null) {
                $data = app(DataSource::class);
                $shared = ['me' => $data->user(), 'demo' => $data->isDemo(), 'realAccounts' => $data instanceof \App\Terrain\LocalData];
            }
            $view->with($shared);
        });

        // Pastilles (messages non lus, notifications) et mode économie de données
        View::composer('layouts.web', function ($view) {
            $unread = 0;
            $bell = 0;
            $lite = request()->cookie('terrain_lite') === '1';
            try {
                $user = app(DataSource::class)->user();
                if ($user) {
                    $unread = app(Messenger::class)->unreadTotal();
                    $bell = app(\App\Terrain\Growth\Notifier::class)->unread($user['id']);
                    // Badge vérifié accordé depuis l'espace admin
                    if (empty($user['verified']) && app(\App\Terrain\Growth\Verifications::class)->isVerified($user['id'])) {
                        $user['verified'] = true;
                        session(['terrain.user' => $user]);
                    }
                }
            } catch (Throwable) {
            }
            $view->with(['unread' => $unread, 'bell' => $bell, 'lite' => $lite]);
        });
    }
}
