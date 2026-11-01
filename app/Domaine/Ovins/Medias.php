<?php

namespace App\Domaine\Ovins;

use App\Exceptions\RegleMetier;
use App\Support\Format;
use App\Support\Journal;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Photos et vidéos des ovins (RG-OVA-009 à 014). Le type est jugé sur le contenu réel du fichier (fileinfo),
 * jamais sur l'extension ni sur le type annoncé par le navigateur.
 */
class Medias
{
    public const STADES = ['naissance' => 'Naissance', 'adolescence' => 'Adolescence', 'adulte' => 'Adulte'];

    public static function ajouter(int $idAnimal, UploadedFile $f, string $stade, ?string $description): int
    {
        if (! isset(self::STADES[$stade])) {
            throw new RegleMetier('Stade d’âge invalide.');
        }
        if (! $f->isValid()) {
            throw new RegleMetier('Le fichier n’a pas pu être reçu (taille maximale du serveur dépassée ?).');
        }
        $cfg = config('somadi.ovins.medias');
        $mime = (new \finfo(FILEINFO_MIME_TYPE))->file($f->getRealPath()) ?: '';
        if (isset($cfg['images'][$mime])) {
            [$type, $ext, $max] = ['image', $cfg['images'][$mime], $cfg['max_image']];
        } elseif (isset($cfg['videos'][$mime])) {
            [$type, $ext, $max] = ['video', $cfg['videos'][$mime], $cfg['max_video']];
        } else {
            throw new RegleMetier("« {$f->getClientOriginalName()} » n’est pas une image (JPEG, PNG, WebP, GIF) ni une vidéo (MP4, WebM, Ogg, QuickTime) : contenu détecté $mime.");
        }
        $taille = (int) $f->getSize();
        if ($taille > $max) {
            throw new RegleMetier("« {$f->getClientOriginalName()} » dépasse la limite de ".intdiv($max, 1024 * 1024).' Mo pour une '.($type === 'image' ? 'image' : 'vidéo').'.');
        }
        if (! DB::table('OVINS_animaux')->where('id_animal', $idAnimal)->exists()) {
            throw new RegleMetier('Animal introuvable.');
        }
        $nom = $idAnimal.'_'.now()->format('Ymd_His').'_'.Str::lower(Str::random(8)).'.'.$ext;
        $dossier = rtrim($cfg['dossier'], '/').'/'.$idAnimal;
        if (! is_dir($dossier) && ! @mkdir($dossier, 0775, true) && ! is_dir($dossier)) {
            throw new RegleMetier('Dossier des médias non accessible en écriture.');
        }
        // RG-OVA-011 : le fichier et la ligne SQL vont ensemble ; si l'insertion échoue, le fichier est retiré.
        $f->move($dossier, $nom);
        try {
            $id = DB::table('OVINS_animaux_medias')->insertGetId([
                'id_animal' => $idAnimal, 'type_media' => $type, 'stade_age' => $stade, 'description' => $description ? mb_substr($description, 0, 255) : null,
                'nom_fichier_original' => mb_substr($f->getClientOriginalName(), 0, 255), 'nom_fichier_stocke' => $nom,
                'chemin_fichier' => 'app:'.$idAnimal.'/'.$nom, 'mime_type' => $mime, 'taille_octets' => $taille, 'utilisateur' => mb_substr(Format::auteur(), 0, 100),
            ]);
        } catch (\Throwable $e) {
            @unlink($dossier.'/'.$nom);
            throw $e;
        }
        Journal::enregistrer('ovin.media_ajout', 'OVA', 'ovin', $idAnimal, apres: ['media' => $id, 'type' => $type, 'stade' => $stade, 'fichier' => $nom]);

        return $id;
    }

    /**
     * Fichier physique d'un média : ceux de la nouvelle application sont préfixés « app: » ;
     * les anciens sont relus depuis la racine de l'ancienne application.
     */
    public static function chemin(object $m): ?string
    {
        $c = (string) $m->chemin_fichier;
        $cfg = config('somadi.ovins.medias');
        if (str_starts_with($c, 'app:')) {
            $p = rtrim($cfg['dossier'], '/').'/'.substr($c, 4);
            $base = realpath($cfg['dossier']);
        } else {
            $base = realpath($cfg['racine_ancienne']);
            if (! $base) {
                return null;
            }
            $rel = ltrim(preg_replace('#^(\.\./)+#', '', parse_url($c, PHP_URL_PATH) ?? ''), '/');
            $p = null;
            foreach ([$rel, 'modules/'.$rel, 'somadi-v2/'.$rel] as $essai) {
                if (is_file($base.'/'.$essai)) {
                    $p = $base.'/'.$essai;
                    break;
                }
            }
            if (! $p) {
                return null;
            }
        }
        $reel = realpath($p);

        // Jamais en dehors du dossier autorisé (chemin forgé, lien symbolique).
        return $reel && $base && str_starts_with($reel, $base.DIRECTORY_SEPARATOR) && is_file($reel) ? $reel : null;
    }

    public static function liste(int $idAnimal)
    {
        return DB::table('OVINS_animaux_medias')->where('id_animal', $idAnimal)
            ->orderByRaw("FIELD(stade_age, 'naissance', 'adolescence', 'adulte')")->orderByRaw("FIELD(type_media, 'image', 'video')")->orderBy('id_media')->get();
    }

    /** Photo de référence : image adulte, sinon adolescence, sinon naissance. */
    public static function photo(int $idAnimal): ?int
    {
        $id = DB::table('OVINS_animaux_medias')->where('id_animal', $idAnimal)->where('type_media', 'image')
            ->orderByRaw("FIELD(stade_age, 'adulte', 'adolescence', 'naissance')")->orderByDesc('id_media')->value('id_media');

        return $id ? (int) $id : null;
    }

    /** RG-OVA-012 : suppression d'un média, distincte de la fiche. Le fichier de la nouvelle application est effacé ; celui de l'ancienne est laissé en place. */
    public static function supprimer(int $idAnimal, int $idMedia): void
    {
        $m = DB::table('OVINS_animaux_medias')->where('id_media', $idMedia)->where('id_animal', $idAnimal)->first() ?? throw new RegleMetier('Média introuvable.');
        DB::table('OVINS_animaux_medias')->where('id_media', $idMedia)->delete();
        if (str_starts_with((string) $m->chemin_fichier, 'app:') && ($p = self::chemin($m))) {
            @unlink($p);
        }
        Journal::enregistrer('ovin.media_suppression', 'OVA', 'ovin', $idAnimal, avant: (array) $m);
    }
}
