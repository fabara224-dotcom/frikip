<?php

namespace App\Console\Commands;

use App\Terrain\Growth\AdminAuth;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

/** Crée un compte admin, ou change son mot de passe s'il existe déjà (mot de passe oublié). */
class MakeAdmin extends Command
{
    protected $signature = 'frikip:admin {email : identifiant (e-mail)} {--nom= : nom affiché} {--moderateur : rôle modérateur au lieu d’administrateur}';

    protected $description = 'Crée un compte de l’espace admin ou réinitialise son mot de passe';

    public function handle(AdminAuth $auth): int
    {
        $email = mb_strtolower(trim((string) $this->argument('email')));
        if (! filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $this->error('E-mail invalide.');

            return self::FAILURE;
        }
        $password = (string) $this->secret('Mot de passe (10 caractères minimum)');
        if (mb_strlen($password) < 10 || $password !== (string) $this->secret('Le même mot de passe')) {
            $this->error('Mot de passe trop court ou différent.');

            return self::FAILURE;
        }
        if ($a = DB::table('admins')->where('email', $email)->first()) {
            DB::table('admins')->where('id', $a->id)->update(['password' => Hash::make($password), 'active' => true, 'updated_at' => now()]);
            $this->info("Mot de passe de {$email} changé.");
        } else {
            $auth->create((string) ($this->option('nom') ?: strstr($email, '@', true)), $email, $password, $this->option('moderateur') ? 'moderateur' : 'admin');
            $this->info("Compte admin {$email} créé. Connexion : /admin");
        }

        return self::SUCCESS;
    }
}
