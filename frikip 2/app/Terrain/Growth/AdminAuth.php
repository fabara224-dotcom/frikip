<?php

namespace App\Terrain\Growth;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Throwable;

/**
 * Comptes de l'espace admin.
 *  - « admin » : tout (réglages, paiements, publicités, admins…) ;
 *  - « moderateur » : membres, modération, signalements, vérifications, contenus (défis, directs, coaching, clubs, offres…).
 * Le tout premier compte se crée sur /admin/installation (protégé par TERRAIN_ADMIN_PASSWORD s'il est rempli)
 * ou avec la commande : php artisan frikip:admin ton@email.com
 */
class AdminAuth
{
    public const ROLES = ['admin' => 'Administrateur (tout)', 'moderateur' => 'Modérateur (membres et contenus)'];

    /** Pages réservées aux administrateurs (le modérateur n'y a pas accès). */
    public const ADMIN_ONLY = [
        'admin.algorithm*', 'admin.payments*', 'admin.ads*', 'admin.media.settings', 'admin.broadcasters*', 'admin.admins*', 'admin.montages*',
        'admin.partners*', 'admin.deletions', 'admin.update',
    ];

    private ?object $me = null;

    public static function hasAny(): bool
    {
        try {
            return DB::table('admins')->exists();
        } catch (Throwable) {
            return false;
        }
    }

    public function current(): ?object
    {
        if ($this->me) {
            return $this->me;
        }
        $id = session('admin.id');
        if (! $id) {
            return null;
        }
        try {
            $a = DB::table('admins')->where('id', $id)->where('active', true)->first();
        } catch (Throwable) {
            $a = null;
        }

        return $this->me = $a;
    }

    public function attempt(string $email, string $password, string $ip): ?object
    {
        $a = DB::table('admins')->where('email', mb_strtolower(trim($email)))->first();
        if (! $a || ! $a->active || ! Hash::check($password, $a->password)) {
            return null;
        }
        DB::table('admins')->where('id', $a->id)->update(['last_login_at' => now(), 'last_login_ip' => $ip]);
        session(['admin.id' => $a->id]);

        return $this->me = DB::table('admins')->find($a->id);
    }

    public function create(string $name, string $email, string $password, string $role = 'admin'): int
    {
        return DB::table('admins')->insertGetId([
            'name' => $name, 'email' => mb_strtolower(trim($email)), 'password' => Hash::make($password),
            'role' => array_key_exists($role, self::ROLES) ? $role : 'moderateur', 'active' => true, 'created_at' => now(), 'updated_at' => now(),
        ]);
    }

    /** Ce compte peut-il ouvrir cette page ? */
    public static function allows(?object $admin, ?string $route): bool
    {
        if (! $admin) {
            return false;
        }
        if ($admin->role === 'admin') {
            return true;
        }
        foreach (self::ADMIN_ONLY as $pattern) {
            if ($route && \Illuminate\Support\Str::is($pattern, $route)) {
                return false;
            }
        }

        return true;
    }
}
