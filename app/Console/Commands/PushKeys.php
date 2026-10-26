<?php

namespace App\Console\Commands;

use App\Terrain\Growth\WebPush;
use Illuminate\Console\Command;

/** Crée les clés des notifications sur téléphone (Web Push) et les ajoute à .env si elles manquent. */
class PushKeys extends Command
{
    protected $signature = 'terrain:cles-push {--force : remplace les clés existantes (les téléphones devront se réabonner)}';

    protected $description = 'Crée VAPID_PUBLIC_KEY et VAPID_PRIVATE_KEY pour les notifications sur téléphone';

    public function handle(): int
    {
        $env = base_path('.env');
        if (! is_file($env) || ! is_writable($env)) {
            $this->error('Fichier .env introuvable ou non modifiable.');

            return self::FAILURE;
        }
        $content = (string) file_get_contents($env);
        $has = preg_match('/^VAPID_PUBLIC_KEY=\S+/m', $content) && preg_match('/^VAPID_PRIVATE_KEY=\S+/m', $content);
        if ($has && ! $this->option('force')) {
            $this->info('Clés des notifications déjà présentes.');

            return self::SUCCESS;
        }
        $k = WebPush::generateKeys();
        foreach (['VAPID_PUBLIC_KEY' => $k['public'], 'VAPID_PRIVATE_KEY' => $k['private']] as $name => $value) {
            if (preg_match("/^{$name}=.*$/m", $content)) {
                $content = preg_replace("/^{$name}=.*$/m", "{$name}={$value}", $content);
            } else {
                $content = rtrim($content, "\n")."\n{$name}={$value}\n";
            }
        }
        file_put_contents($env, $content);
        $this->info('Clés des notifications créées dans .env (VAPID_PUBLIC_KEY / VAPID_PRIVATE_KEY).');

        return self::SUCCESS;
    }
}
