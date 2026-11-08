<?php

namespace App\Support;

use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Journal métier unique (EC19). Chaque écriture sensible y laisse une trace : qui, quoi, avant, après, pourquoi.
 */
class Journal
{
    public static function enregistrer(
        string $action,
        ?string $module = null,
        ?string $objetType = null,
        string|int|null $objetId = null,
        mixed $avant = null,
        mixed $apres = null,
        ?string $motif = null,
        ?User $auteur = null,
    ): void {
        $auteur ??= Auth::user();
        $req = request();
        // Changement d'identité : l'administrateur réel est noté à côté du compte utilisé.
        $reel = Identite::infos($req);

        DB::table('app_journal')->insert([
            'cree_le' => now(),
            'user_id' => $auteur?->id,
            'user_nom' => $auteur ? mb_substr($auteur->nomComplet(), 0, 150) : null,
            'module' => $module,
            'action' => $action,
            'objet_type' => $objetType,
            'objet_id' => $objetId !== null ? (string) $objetId : null,
            'avant' => self::encoder($avant),
            'apres' => self::encoder($apres),
            'motif' => $motif,
            'ip' => $req?->ip(),
            'user_agent' => $req ? mb_substr((string) $req->userAgent(), 0, 255) : null,
        ] + ($reel && self::colonnesIdentite() ? [
            'agi_par_id' => (int) $reel['id'],
            'agi_par_nom' => mb_substr((string) $reel['nom'], 0, 150),
        ] : []));
    }

    /** Colonnes ajoutées par la migration du changement d'identité (absentes tant qu'elle n'a pas tourné). */
    private static function colonnesIdentite(): bool
    {
        static $ok = null;

        return $ok ??= Schema::hasColumn('app_journal', 'agi_par_id');
    }

    private static function encoder(mixed $v): ?string
    {
        if ($v === null) {
            return null;
        }

        return json_encode($v, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }
}
