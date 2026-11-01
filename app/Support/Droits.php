<?php

namespace App\Support;

use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * Matrice des droits rôle × action (EC02).
 * - L'administrateur a tous les droits.
 * - Certaines actions sont réservées à l'administrateur et ne peuvent pas être accordées (ex. clôture comptable, RG EXE).
 */
class Droits
{
    /** @var array<string, array<string,bool>> cache par requête */
    private static array $cache = [];

    /** Actions réservées exclusivement au rôle admin. */
    public const RESERVEES_ADMIN = ['EXE.cloturer', 'ACC.droits'];

    /** @return array<string, array{titre:string, groupe:string, lot:int, actions:array<string,string>}> */
    public static function modules(): array
    {
        return config('somadi.modules');
    }

    /** Liste à plat de toutes les permissions connues : 'CODE.action' => libellé. */
    public static function catalogue(): array
    {
        $out = [];
        foreach (self::modules() as $code => $m) {
            foreach ($m['actions'] as $action => $libelle) {
                $out["$code.$action"] = $libelle;
            }
        }

        return $out;
    }

    public static function existe(string $permission): bool
    {
        return array_key_exists($permission, self::catalogue());
    }

    public static function autorise(?User $user, string $permission): bool
    {
        if (! $user || ! $user->actif) {
            return false;
        }
        if ($user->estAdmin()) {
            return true;
        }
        if (in_array($permission, self::RESERVEES_ADMIN, true)) {
            return false;
        }

        return isset(self::permissionsDuRole($user->roleCanonique())[$permission]);
    }

    /** @return array<string,bool> */
    public static function permissionsDuRole(string $role): array
    {
        if (! isset(self::$cache[$role])) {
            self::$cache[$role] = DB::table('app_permissions')
                ->where('role', $role)
                ->pluck('permission')
                ->mapWithKeys(fn ($p) => [$p => true])
                ->all();
        }

        return self::$cache[$role];
    }

    public static function viderCache(): void
    {
        self::$cache = [];
    }

    /** Module visible dans le menu si l'utilisateur peut au moins le consulter. */
    public static function moduleVisible(?User $user, string $code): bool
    {
        return self::autorise($user, "$code.voir");
    }

    /** Développe la matrice par défaut de la configuration en liste de permissions par rôle. */
    public static function matriceParDefaut(): array
    {
        $out = [];
        foreach (config('somadi.default_matrix') as $role => $entrees) {
            $perms = [];
            foreach ($entrees as $e) {
                [$code, $action] = explode('.', $e, 2);
                $actions = $action === '*' ? array_keys(self::modules()[$code]['actions'] ?? []) : [$action];
                foreach ($actions as $a) {
                    $p = "$code.$a";
                    if (self::existe($p) && ! in_array($p, self::RESERVEES_ADMIN, true)) {
                        $perms[$p] = true;
                    }
                }
            }
            $out[$role] = array_keys($perms);
        }

        return $out;
    }

    /**
     * Remplace les permissions d'un rôle et renvoie [ajoutées, retirées].
     *
     * @param  list<string>  $permissions
     * @return array{0:list<string>,1:list<string>}
     */
    public static function remplacer(string $role, array $permissions): array
    {
        $voulues = array_values(array_unique(array_filter(
            $permissions,
            fn ($p) => self::existe($p) && ! in_array($p, self::RESERVEES_ADMIN, true)
        )));
        sort($voulues);

        return DB::transaction(function () use ($role, $voulues) {
            $actuelles = DB::table('app_permissions')->where('role', $role)->lockForUpdate()->pluck('permission')->all();
            $ajout = array_values(array_diff($voulues, $actuelles));
            $retrait = array_values(array_diff($actuelles, $voulues));
            if ($retrait) {
                DB::table('app_permissions')->where('role', $role)->whereIn('permission', $retrait)->delete();
            }
            $now = now();
            foreach ($ajout as $p) {
                DB::table('app_permissions')->insert(['role' => $role, 'permission' => $p, 'created_at' => $now, 'updated_at' => $now]);
            }
            self::viderCache();

            return [$ajout, $retrait];
        });
    }
}
