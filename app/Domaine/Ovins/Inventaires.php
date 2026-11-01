<?php

namespace App\Domaine\Ovins;

use App\Exceptions\RegleMetier;
use App\Support\Format;
use Illuminate\Database\QueryException;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/** Inventaires physiques des ovins (OVI) : liste figée, pointage, observations supplémentaires, clôture. */
class Inventaires
{
    public const POINTAGES = ['non_pointe' => ['Non pointé', 'neutre'], 'present' => ['Présent', 'ok'], 'anomalie' => ['Anomalie', 'attente'], 'absent' => ['Absent', 'alerte'], 'ecarte' => ['Écarté', 'neutre']];

    public const MAX = 10000;

    private static function journal(int $inv, ?int $ligne, string $action, string $resume, mixed $avant = null, mixed $apres = null): void
    {
        DB::table('OVINS_inventaire_journal')->insert([
            'inventaire_id' => $inv, 'ligne_id' => $ligne, 'action' => $action, 'resume' => mb_substr($resume, 0, 255),
            'avant_json' => $avant === null ? null : json_encode($avant, JSON_UNESCAPED_UNICODE), 'apres_json' => $apres === null ? null : json_encode($apres, JSON_UNESCAPED_UNICODE),
            'auteur' => mb_substr(Format::auteur(), 0, 100), 'cree_le' => now(),
        ]);
    }

    private static function perimetre(array $d)
    {
        $q = DB::table('OVINS_animaux as a')->leftJoin('OVINS_sites as s', 's.id_site', '=', 'a.id_site')
            ->leftJoin('OVINS_lots as l', 'l.id_lot', '=', 'a.id_lot')->leftJoin('OVINS_proprietaires as p', 'p.id_proprietaire', '=', 'a.id_proprietaire')
            ->where('a.statut', 'en_ferme')->whereNull('a.date_sortie');
        foreach (['site' => 'a.id_site', 'lot' => 'a.id_lot', 'proprietaire' => 'a.id_proprietaire'] as $k => $c) {
            if (! empty($d[$k])) {
                $q->where($c, (int) $d[$k]);
            }
        }

        return $q;
    }

    public static function compterPerimetre(array $d): int
    {
        return self::perimetre($d)->count();
    }

    /** RG-OVI-001 à 003 : ouverture et instantané cohérent. */
    public static function ouvrir(array $d, string $cle): int
    {
        $titre = trim((string) ($d['titre'] ?? ''));
        if ($titre === '' || mb_strlen($titre) > 150) {
            throw new RegleMetier('Donnez un titre à l’inventaire (150 caractères maximum).');
        }
        if (! empty($d['site']) && ! empty($d['lot']) && ! DB::table('OVINS_lots')->where('id_lot', (int) $d['lot'])->where('id_site', (int) $d['site'])->exists()) {
            throw new RegleMetier('Le lot choisi n’appartient pas au site choisi.');
        }
        try {
            return DB::transaction(function () use ($d, $titre, $cle) {
                $animaux = self::perimetre($d)->lockForUpdate()->orderBy('a.code_animal')->orderBy('a.id_animal')
                    ->get(['a.*', 's.nom_site', 'l.nom_lot', 'p.nom_proprietaire']);
                if ($animaux->isEmpty() || $animaux->count() > self::MAX) {
                    throw new RegleMetier('Le périmètre doit contenir de 1 à '.self::MAX.' animaux en ferme (ici : '.$animaux->count().').');
                }
                $noms = array_filter([
                    ! empty($d['site']) ? 'Site '.DB::table('OVINS_sites')->where('id_site', $d['site'])->value('nom_site') : null,
                    ! empty($d['lot']) ? 'Lot '.DB::table('OVINS_lots')->where('id_lot', $d['lot'])->value('nom_lot') : null,
                    ! empty($d['proprietaire']) ? 'Propriétaire '.DB::table('OVINS_proprietaires')->where('id_proprietaire', $d['proprietaire'])->value('nom_proprietaire') : null,
                ]);
                $id = DB::table('OVINS_inventaires')->insertGetId([
                    'titre' => $titre, 'site_id' => ($d['site'] ?? null) ?: null, 'lot_id' => ($d['lot'] ?? null) ?: null, 'proprietaire_id' => ($d['proprietaire'] ?? null) ?: null,
                    'perimetre' => mb_substr($noms ? implode(' · ', $noms) : 'Tout le troupeau en ferme', 0, 500), 'ouvert_le' => now(), 'ouvert_par' => mb_substr(Format::auteur(), 0, 100),
                    'statut' => 'ouvert', 'version' => 1, 'total_attendu' => $animaux->count(), 'dernier_mouvement_id' => (int) DB::table('OVINS_mouvements')->max('id_mvt'), 'request_key' => $cle,
                ]);
                foreach ($animaux->values()->chunk(500) as $paquet) {
                    DB::table('OVINS_inventaire_lignes')->insert($paquet->map(fn ($a, $i) => [
                        'inventaire_id' => $id, 'numero' => $i + 1, 'nature' => 'attendu', 'animal_id' => $a->id_animal,
                        'code_animal' => (string) ($a->code_animal ?: '#'.$a->id_animal), 'nom_animal' => (string) $a->nom_animal,
                        'site_id' => $a->id_site, 'lot_id' => $a->id_lot, 'proprietaire_id' => $a->id_proprietaire,
                        'nom_site' => (string) $a->nom_site, 'nom_lot' => (string) $a->nom_lot, 'nom_proprietaire' => (string) $a->nom_proprietaire,
                        'sexe' => $a->sexe, 'date_naissance' => $a->date_naissance, 'statut_animal' => (string) $a->statut,
                    ])->values()->all());
                }
                self::journal($id, null, 'ouverture', "Ouverture : {$animaux->count()} animaux attendus");

                return $id;
            });
        } catch (QueryException $e) {
            if (($e->errorInfo[1] ?? null) === 1062) {
                throw new RegleMetier('Cet inventaire a déjà été ouvert (double envoi).');
            }
            throw $e;
        }
    }

    private static function ouvert(int $id): object
    {
        $inv = DB::table('OVINS_inventaires')->where('id', $id)->lockForUpdate()->first() ?? throw new RegleMetier('Inventaire introuvable.');
        if ($inv->statut !== 'ouvert') {
            throw new RegleMetier('Cet inventaire est clôturé : il ne peut plus être modifié.');
        }

        return $inv;
    }

    private static function controle(object $inv, string $etat, array $d): array
    {
        if ($etat === 'non_pointe') {
            return ['controleur' => '', 'date_controle' => null, 'site_constate' => '', 'lot_constate' => '', 'commentaire' => null];
        }
        $controleur = trim((string) ($d['controleur'] ?? ''));
        if ($controleur === '') {
            throw new RegleMetier('Indiquez le contrôleur.');
        }
        $date = (string) ($d['date_controle'] ?? '');
        if ($date === '' || $date < substr($inv->ouvert_le, 0, 10) || $date > today()->toDateString()) {
            throw new RegleMetier('La date de contrôle doit être comprise entre l’ouverture de l’inventaire et aujourd’hui.');
        }
        $com = trim((string) ($d['commentaire'] ?? ''));
        if (in_array($etat, ['anomalie', 'absent'], true) && $com === '') {
            throw new RegleMetier('Une anomalie ou une absence exige un commentaire.');
        }

        return ['controleur' => mb_substr($controleur, 0, 100), 'date_controle' => $date, 'site_constate' => mb_substr(trim((string) ($d['site_constate'] ?? '')), 0, 150),
            'lot_constate' => mb_substr(trim((string) ($d['lot_constate'] ?? '')), 0, 150), 'commentaire' => $com ?: null];
    }

    /** RG-OVI-004 à 006 : pointage d'une ligne, avec sa version. */
    public static function pointer(int $idInv, int $idLigne, string $etat, int $version, array $d): void
    {
        if (! isset(self::POINTAGES[$etat])) {
            throw new RegleMetier('État de pointage invalide.');
        }
        DB::transaction(function () use ($idInv, $idLigne, $etat, $version, $d) {
            $inv = self::ouvert($idInv);
            $l = DB::table('OVINS_inventaire_lignes')->where('id', $idLigne)->where('inventaire_id', $idInv)->lockForUpdate()->first() ?? throw new RegleMetier('Ligne introuvable.');
            if ((int) $l->version !== $version) {
                throw new RegleMetier("La ligne n° {$l->numero} a été modifiée par ailleurs : rechargez la page.");
            }
            if ($etat === 'ecarte' && $l->nature !== 'supplementaire') {
                throw new RegleMetier('Seule une observation supplémentaire peut être écartée.');
            }
            if ($l->nature === 'supplementaire' && in_array($etat, ['non_pointe', 'absent'], true)) {
                throw new RegleMetier('Une observation supplémentaire reste une anomalie ou est écartée.');
            }
            $c = self::controle($inv, $etat, $d);
            if ($etat === 'present' && $l->nature === 'attendu') {
                $c['site_constate'] = $c['site_constate'] ?: $l->nom_site;
                $c['lot_constate'] = $c['lot_constate'] ?: $l->nom_lot;
            }
            $v = $c + ['pointage' => $etat, 'version' => $l->version + 1, 'modifie_le' => now(), 'modifie_par' => mb_substr(Format::auteur(), 0, 100)];
            DB::table('OVINS_inventaire_lignes')->where('id', $idLigne)->update($v);
            DB::table('OVINS_inventaires')->where('id', $idInv)->increment('version');
            self::journal($idInv, $idLigne, 'pointage', "Ligne {$l->numero} ({$l->code_animal}) : ".self::POINTAGES[$etat][0],
                array_intersect_key((array) $l, $v), $v);
        });
    }

    /** RG-OVI-007 : pointage collectif « présent » de 1 à 50 lignes non pointées ; tout ou rien. */
    public static function pointerGroupe(int $idInv, array $versions, array $d): int
    {
        if (count($versions) < 1 || count($versions) > 50) {
            throw new RegleMetier('Sélectionnez de 1 à 50 lignes.');
        }

        return DB::transaction(function () use ($idInv, $versions, $d) {
            $inv = self::ouvert($idInv);
            $c = self::controle($inv, 'present', $d);
            $lignes = DB::table('OVINS_inventaire_lignes')->where('inventaire_id', $idInv)->whereIn('id', array_keys($versions))->lockForUpdate()->get()->keyBy('id');
            foreach ($versions as $id => $ver) {
                $l = $lignes->get((int) $id);
                if (! $l || $l->nature !== 'attendu' || $l->pointage !== 'non_pointe' || (int) $l->version !== (int) $ver) {
                    throw new RegleMetier('Une des lignes sélectionnées a changé ou est déjà pointée : rien n’a été enregistré. Rechargez la page.');
                }
            }
            foreach ($lignes as $l) {
                DB::table('OVINS_inventaire_lignes')->where('id', $l->id)->update($c + ['pointage' => 'present', 'site_constate' => $c['site_constate'] ?: $l->nom_site,
                    'lot_constate' => $c['lot_constate'] ?: $l->nom_lot, 'version' => $l->version + 1, 'modifie_le' => now(), 'modifie_par' => mb_substr(Format::auteur(), 0, 100)]);
            }
            DB::table('OVINS_inventaires')->where('id', $idInv)->increment('version');
            self::journal($idInv, null, 'pointage_groupe', count($lignes).' ligne(s) pointée(s) présentes', null, ['lignes' => $lignes->keys()->all()]);

            return count($lignes);
        });
    }

    /**
     * RG-OVI-008, 009 : observation d'un animal non attendu. Aucune fiche n'est créée.
     *
     * @return array{id:int, existante:bool, numero:int}
     */
    public static function ajouter(int $idInv, array $d, string $cle): array
    {
        $code = trim((string) ($d['code_animal'] ?? ''));
        if ($code === '' || mb_strlen($code) > 100) {
            throw new RegleMetier('Indiquez le numéro de boucle observé.');
        }
        if (trim((string) ($d['site_constate'] ?? '')) === '' || trim((string) ($d['commentaire'] ?? '')) === '') {
            throw new RegleMetier('Le site constaté et un commentaire d’anomalie sont obligatoires.');
        }

        return DB::transaction(function () use ($idInv, $d, $code, $cle) {
            $inv = self::ouvert($idInv);
            $deja = DB::table('OVINS_inventaire_lignes')->where('inventaire_id', $idInv)->where('code_animal', $code)->first();
            if ($deja) {
                return ['id' => (int) $deja->id, 'existante' => true, 'numero' => (int) $deja->numero];
            }
            $c = self::controle($inv, 'anomalie', $d);
            $fiches = DB::table('OVINS_animaux')->where('code_animal', $code)->get();
            $a = $fiches->count() === 1 ? $fiches->first() : null;
            if ($a && DB::table('OVINS_inventaire_lignes')->where('inventaire_id', $idInv)->where('animal_id', $a->id_animal)->exists()) {
                $a = null;
            }
            $numero = (int) DB::table('OVINS_inventaire_lignes')->where('inventaire_id', $idInv)->max('numero') + 1;
            try {
                $id = DB::table('OVINS_inventaire_lignes')->insertGetId($c + [
                    'inventaire_id' => $idInv, 'numero' => $numero, 'nature' => 'supplementaire', 'animal_id' => $a?->id_animal, 'code_animal' => $code,
                    'nom_animal' => (string) ($a->nom_animal ?? ($fiches->count() > 1 ? 'Plusieurs fiches : à identifier' : 'À identifier')),
                    'site_id' => $a?->id_site, 'lot_id' => $a?->id_lot, 'proprietaire_id' => $a?->id_proprietaire, 'nom_site' => '', 'nom_lot' => '', 'nom_proprietaire' => '',
                    'sexe' => (string) ($a->sexe ?? ''), 'date_naissance' => $a?->date_naissance, 'statut_animal' => (string) ($a->statut ?? 'inconnu'),
                    'pointage' => 'anomalie', 'version' => 1, 'modifie_le' => now(), 'modifie_par' => mb_substr(Format::auteur(), 0, 100), 'request_key' => $cle,
                ]);
            } catch (QueryException $e) {
                if (($e->errorInfo[1] ?? null) === 1062) {
                    throw new RegleMetier('Cette observation a déjà été enregistrée (double envoi).');
                }
                throw $e;
            }
            DB::table('OVINS_inventaires')->where('id', $idInv)->increment('version');
            self::journal($idInv, $id, 'ajout', "Observation supplémentaire n° $numero : $code", null, ['code' => $code, 'animal' => $a?->id_animal] + $c);

            return ['id' => $id, 'existante' => false, 'numero' => $numero];
        });
    }

    public static function synthese(int $idInv): array
    {
        $rows = DB::table('OVINS_inventaire_lignes')->where('inventaire_id', $idInv)->groupBy('nature', 'pointage')->select('nature', 'pointage', DB::raw('COUNT(*) n'))->get();
        $out = ['attendu' => array_fill_keys(array_keys(self::POINTAGES), 0), 'supplementaire' => array_fill_keys(array_keys(self::POINTAGES), 0)];
        foreach ($rows as $r) {
            $out[$r->nature][$r->pointage] = (int) $r->n;
        }

        return $out;
    }

    /** RG-OVI-003 (REC-OVI-001) : mouvements intervenus depuis l'ouverture pour les animaux attendus. */
    public static function rapprochement(object $inv)
    {
        return DB::table('OVINS_mouvements as m')
            ->join('OVINS_inventaire_lignes as l', fn ($j) => $j->on('l.animal_id', '=', 'm.id_animal')->where('l.inventaire_id', $inv->id))
            ->where('m.id_mvt', '>', $inv->dernier_mouvement_id)->orderBy('m.id_mvt')
            ->get(['m.id_mvt', 'm.date_mvt', 'm.type_mvt', 'm.commentaire', 'l.numero', 'l.code_animal', 'l.pointage']);
    }

    /** RG-OVI-010, 011 : clôture (admin et technicien) ; aucun absent n'est transformé en sortie. */
    public static function cloturer(int $idInv, int $version, string $bilan): void
    {
        if (mb_strlen(trim($bilan)) < 10) {
            throw new RegleMetier('Rédigez le bilan de l’inventaire (10 caractères au moins).');
        }
        DB::transaction(function () use ($idInv, $version, $bilan) {
            $inv = self::ouvert($idInv);
            if ((int) $inv->version !== $version) {
                throw new RegleMetier('L’inventaire a changé depuis l’ouverture de la page : vérifiez-le de nouveau avant de clôturer.');
            }
            $reste = DB::table('OVINS_inventaire_lignes')->where('inventaire_id', $idInv)->where('nature', 'attendu')->where('pointage', 'non_pointe')->count();
            if ($reste > 0) {
                throw new RegleMetier("$reste ligne(s) attendue(s) ne sont pas pointées : la clôture est impossible.");
            }
            $rappro = ['synthese' => self::synthese($idInv), 'mouvements' => self::rapprochement($inv)->all(), 'cloture' => now()->toIso8601String()];
            DB::table('OVINS_inventaires')->where('id', $idInv)->update(['statut' => 'cloture', 'cloture_le' => now(), 'cloture_par' => mb_substr(Format::auteur(), 0, 100),
                'bilan' => trim($bilan), 'rapprochement_json' => json_encode($rappro, JSON_UNESCAPED_UNICODE), 'version' => $inv->version + 1]);
            self::journal($idInv, null, 'cloture', 'Clôture de l’inventaire', ['statut' => 'ouvert'], ['statut' => 'cloture', 'bilan' => trim($bilan)]);
        });
    }

    public static function dateOuverture(object $inv): string
    {
        return Carbon::parse($inv->ouvert_le)->toDateString();
    }
}
