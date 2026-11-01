<?php

namespace App\Support;

use App\Models\User;
use Illuminate\Support\Facades\Route;

/** Construit le menu à partir des modules et des droits de l'utilisateur. */
class Menu
{
    /**
     * @return list<array{groupe:string, titre:string, entrees:list<array{code:string,titre:string,icone:string,url:string,disponible:bool,actif:bool}>}>
     */
    public static function pour(?User $user): array
    {
        $lotLivre = (int) config('somadi.lot_livre');
        $groupes = [];
        foreach (Droits::modules() as $code => $m) {
            if (! Droits::moduleVisible($user, $code)) {
                continue;
            }
            $disponible = $m['lot'] <= $lotLivre && isset($m['route']) && Route::has($m['route']);
            $url = $disponible ? route($m['route']) : route('module.a_venir', ['code' => strtolower($code)]);
            $groupes[$m['groupe']][] = [
                'code' => $code,
                'titre' => $m['titre'],
                'court' => $m['court'] ?? $m['titre'],
                'icone' => $m['icone'],
                'url' => $url,
                'disponible' => $disponible,
                'actif' => request()->url() === $url || ($disponible && str_starts_with((string) request()->route()?->getName(), substr($m['route'], 0, (int) strrpos($m['route'], '.')).'.')),
                'lot' => $m['lot'],
            ];
        }

        $out = [];
        foreach (config('somadi.groups') as $cle => $titre) {
            if (! empty($groupes[$cle])) {
                $out[] = ['groupe' => $cle, 'titre' => $titre, 'entrees' => $groupes[$cle]];
            }
        }

        return $out;
    }
}
