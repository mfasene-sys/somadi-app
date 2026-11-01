<?php

namespace App\Domaine\Organisation;

use App\Exceptions\RegleMetier;
use App\Support\Empreinte;
use App\Support\Journal;
use App\Support\Lieux;
use Illuminate\Support\Facades\DB;

/** Bâtiments, parcs et répartition physique des sujets (INF, RG-INF-001 à 012). */
class Infrastructures
{
    public const TYPES = ['batiment' => 'Bâtiment', 'parc' => 'Parc', 'cage' => 'Cage', 'compartiment' => 'Compartiment'];

    public const FAMILLES = ['volaille' => 'Volailles', 'repro' => 'Reproducteurs', 'ovin' => 'Ovins'];

    public const ETATS = ['disponible' => ['Disponible', 'ok'], 'nettoyage' => ['Nettoyage', 'attente'], 'vide_sanitaire' => ['Vide sanitaire', 'attente'],
        'maintenance' => ['Maintenance', 'attente'], 'inactif' => ['Inactif', 'neutre']];

    /** Familles de position → groupe de compatibilité de l'emplacement (RG-INF-003, 007). */
    public const SOURCES = ['volaille' => ['volaille', 'Lot de volailles'], 'repro_m' => ['repro', 'Reproducteurs mâles'], 'repro_f' => ['repro', 'Reproductrices'], 'ovin' => ['ovin', 'Ovin']];

    public static function lieux()
    {
        $occ = DB::table('modInfra_positions')->groupBy('id_batiment')->select('id_batiment', DB::raw('SUM(quantite) occupation'));

        return DB::table('batiments_engraissement as b')
            ->leftJoin('modInfra_emplacements as e', 'e.id_batiment', '=', 'b.id')
            ->leftJoin('OVINS_sites as s', 's.id_site', '=', 'e.id_site')
            ->leftJoinSub($occ, 'o', 'o.id_batiment', '=', 'b.id')
            ->orderBy('s.nom_site')->orderBy('e.code')->orderBy('b.nom_batiment')
            ->get(['b.id', 'b.nom_batiment', 'b.capacite', 'b.actif', 'e.code', 'e.id_site', 'e.type_lieu', 'e.famille', 'e.espece', 'e.stade',
                'e.surface_m2', 'e.etat', 'e.disponible_le', 'e.observations', 'e.version', 's.nom_site', 's.actif as site_actif', DB::raw('COALESCE(o.occupation,0) occupation')]);
    }

    public static function lieu(int $id): object
    {
        return self::lieux()->firstWhere('id', $id) ?? throw new RegleMetier('Emplacement introuvable.');
    }

    /** RG-INF-001, 002, 010 : création ou configuration d'un lieu ; les déclencheurs protègent un lieu occupé. */
    public static function enregistrer(?int $id, array $d, ?int $version = null): int
    {
        $code = mb_strtoupper(trim((string) ($d['code'] ?? '')));
        $nom = trim((string) ($d['nom_batiment'] ?? ''));
        if ($code === '' || mb_strlen($code) > 40 || $nom === '' || mb_strlen($nom) > 100) {
            throw new RegleMetier('Le code (40 caractères) et le nom (100) sont obligatoires.');
        }
        foreach (['type_lieu' => self::TYPES, 'famille' => self::FAMILLES, 'etat' => self::ETATS] as $k => $liste) {
            if (! isset($liste[$d[$k] ?? ''])) {
                throw new RegleMetier("Valeur invalide : $k.");
            }
        }
        $capacite = filter_var($d['capacite'] ?? null, FILTER_VALIDATE_INT);
        if ($capacite === false || $capacite <= 0) {
            throw new RegleMetier('La capacité doit être un entier strictement positif.');
        }
        $surface = null;
        if (($d['surface_m2'] ?? '') !== '' && $d['surface_m2'] !== null) {
            $txt = str_replace(',', '.', trim((string) $d['surface_m2']));
            if (! preg_match('/^\d{1,10}(\.\d{1,2})?$/', $txt) || (float) $txt <= 0) {
                throw new RegleMetier('Surface invalide : nombre positif, deux décimales au plus.');
            }
            $surface = $txt;
        }
        $site = (int) ($d['id_site'] ?? 0);
        if (! DB::table('OVINS_sites')->where('id_site', $site)->where('actif', 1)->exists()) {
            throw new RegleMetier('Choisissez un site actif.');
        }
        $e = ['code' => $code, 'id_site' => $site, 'type_lieu' => $d['type_lieu'], 'famille' => $d['famille'], 'espece' => mb_substr(trim((string) ($d['espece'] ?? '')), 0, 50),
            'stade' => mb_substr(trim((string) ($d['stade'] ?? '')), 0, 80), 'surface_m2' => $surface, 'etat' => $d['etat'],
            'disponible_le' => $d['etat'] === 'disponible' ? null : (($d['disponible_le'] ?? null) ?: null), 'observations' => ($d['observations'] ?? null) ?: null];
        $b = ['nom_batiment' => $nom, 'capacite' => $capacite, 'actif' => empty($d['actif']) ? 0 : 1];

        return DB::transaction(function () use ($id, $e, $b, $version) {
            if (DB::table('modInfra_emplacements')->where('code', $e['code'])->when($id, fn ($q) => $q->where('id_batiment', '<>', $id))->exists()) {
                throw new RegleMetier("Le code « {$e['code']} » est déjà utilisé.");
            }
            Lieux::preparer('lieu', (int) $id, null, 'Configuration de l’emplacement');
            if (! $id) {
                $id = DB::table('batiments_engraissement')->insertGetId($b);
                DB::table('modInfra_emplacements')->insert($e + ['id_batiment' => $id, 'version' => 1]);
                Journal::enregistrer('lieu.creation', 'INF', 'lieu', $id, apres: $b + $e);

                return $id;
            }
            $avant = DB::table('modInfra_emplacements')->where('id_batiment', $id)->lockForUpdate()->first();
            if ($avant && $version !== null && (int) $avant->version !== $version) {
                throw new RegleMetier('Cet emplacement a été modifié entre-temps : rechargez la page.');
            }
            Lieux::executer(function () use ($id, $b, $e, $avant) {
                DB::table('batiments_engraissement')->where('id', $id)->update($b);
                if ($avant) {
                    DB::table('modInfra_emplacements')->where('id_batiment', $id)->update($e + ['version' => $avant->version + 1]);
                } else {
                    DB::table('modInfra_emplacements')->insert($e + ['id_batiment' => $id, 'version' => 1]);
                }
            });
            Lieux::reinitialiser();
            Journal::enregistrer('lieu.modification', 'INF', 'lieu', $id, $avant ? (array) $avant : null, $b + $e);

            return $id;
        });
    }

    // ----- Sources et répartition

    /** Sources d'une famille avec effectif courant, quantité placée et non affectée (RG-INF-004). */
    public static function sources(string $famille)
    {
        $placees = DB::table('modInfra_positions')->where('famille', $famille)->groupBy('id_source')->pluck(DB::raw('SUM(quantite)'), 'id_source');
        $liste = match ($famille) {
            'volaille' => DB::table('modLotVol_lots_volailles')->where('statut', 'actif')->orderBy('nom_lot')->get(['id', 'nom_lot as nom', 'espece', 'effectif_en_cours as effectif', DB::raw('NULL as id_site')]),
            'repro_m', 'repro_f' => DB::table('bandes_parentales')->where(fn ($q) => $q->where('reformee', 0)->orWhereNull('reformee'))->orderBy('nom_bande')
                ->get(['id', 'nom_bande as nom', 'espece', ($famille === 'repro_m' ? 'nb_males' : 'nb_femelles').' as effectif', DB::raw('NULL as id_site')]),
            'ovin' => DB::table('OVINS_animaux as a')->leftJoin('OVINS_sites as s', 's.id_site', '=', 'a.id_site')->where('a.statut', 'en_ferme')->whereNull('a.date_sortie')
                ->orderBy('a.code_animal')->get(['a.id_animal as id', DB::raw("COALESCE(a.code_animal, CONCAT('#', a.id_animal)) as nom"), DB::raw("'ovin' as espece"), DB::raw('1 as effectif'), 'a.id_site', 's.nom_site']),
            default => throw new RegleMetier('Famille inconnue.'),
        };

        return $liste->each(function ($s) use ($placees) {
            $s->place = (int) ($placees[$s->id] ?? 0);
            $s->non_affecte = max(0, (int) $s->effectif - $s->place);
        });
    }

    public static function source(string $famille, int $id): object
    {
        return self::sources($famille)->firstWhere('id', $id) ?? throw new RegleMetier('Source introuvable ou inactive.');
    }

    public static function positions(string $famille, int $id)
    {
        return DB::table('modInfra_positions as p')->join('batiments_engraissement as b', 'b.id', '=', 'p.id_batiment')
            ->leftJoin('modInfra_emplacements as e', 'e.id_batiment', '=', 'p.id_batiment')
            ->where('p.famille', $famille)->where('p.id_source', $id)->orderBy('e.code')
            ->get(['p.id_batiment', 'p.quantite', 'b.nom_batiment', 'e.code']);
    }

    /** Empreinte d'effectif et de répartition : bloque un formulaire ancien (RG-INF-009). */
    public static function empreinte(string $famille, int $id): string
    {
        $s = self::source($famille, $id);

        return Empreinte::de(['effectif' => (int) $s->effectif, 'positions' => self::positions($famille, $id)->map(fn ($p) => [$p->id_batiment, (int) $p->quantite])->all()]);
    }

    /** Destinations compatibles pour une source (RG-INF-006 à 008). */
    public static function destinations(string $famille, object $source)
    {
        [$groupe] = self::SOURCES[$famille];

        return self::lieux()->filter(fn ($l) => self::compatible($l, $famille, $source) === null)->values();
    }

    public static function compatible(object $l, string $famille, object $source): ?string
    {
        [$groupe] = self::SOURCES[$famille];
        if (! $l->code) {
            return 'Emplacement non configuré.';
        }
        if (! $l->actif || ! $l->site_actif) {
            return 'Bâtiment ou site inactif.';
        }
        if ($l->etat !== 'disponible' || $l->disponible_le !== null) {
            return 'Emplacement indisponible ('.(self::ETATS[$l->etat][0] ?? $l->etat).').';
        }
        if ($l->famille !== $groupe) {
            return 'Emplacement réservé à une autre famille ('.(self::FAMILLES[$l->famille] ?? $l->famille).').';
        }
        if ($l->espece !== '' && mb_strtolower($l->espece) !== mb_strtolower((string) $source->espece)) {
            return "Emplacement réservé à l’espèce « {$l->espece} ».";
        }
        if ($famille === 'ovin' && (int) $l->id_site !== (int) $source->id_site) {
            return 'Le parc doit être sur le site de l’animal : transférez d’abord l’animal dans le module Ovins.';
        }
        if ((int) $l->capacite <= 0) {
            return 'Capacité non renseignée.';
        }

        return null;
    }

    /**
     * RG-INF-004 à 009 : déplace une quantité d'une origine vers une destination (0 = non affecté).
     */
    public static function deplacer(string $famille, int $idSource, int $origine, int $destination, int $quantite, string $motif, string $empreinte): void
    {
        if (! isset(self::SOURCES[$famille])) {
            throw new RegleMetier('Famille inconnue.');
        }
        if ($origine === $destination) {
            throw new RegleMetier('L’origine et la destination doivent être différentes.');
        }
        if ($quantite <= 0) {
            throw new RegleMetier('La quantité doit être strictement positive.');
        }
        if (mb_strlen(trim($motif)) < 3) {
            throw new RegleMetier('Indiquez le motif du déplacement.');
        }
        DB::transaction(function () use ($famille, $idSource, $origine, $destination, $quantite, $motif, $empreinte) {
            DB::table('modInfra_verrou')->where('id', 1)->lockForUpdate()->first();
            if (! hash_equals(self::empreinte($famille, $idSource), $empreinte)) {
                throw new RegleMetier('L’effectif ou la répartition a changé depuis l’ouverture de la page : rechargez-la.');
            }
            $s = self::source($famille, $idSource);
            if ($famille === 'ovin' && $quantite !== 1) {
                throw new RegleMetier('Un ovin se déplace à l’unité.');
            }
            $dispo = $origine === 0 ? $s->non_affecte : (int) DB::table('modInfra_positions')->where(['famille' => $famille, 'id_source' => $idSource, 'id_batiment' => $origine])->value('quantite');
            if ($quantite > $dispo) {
                throw new RegleMetier("Quantité supérieure à celle présente à l’origine ($dispo).");
            }
            if ($destination !== 0) {
                $l = DB::table('batiments_engraissement')->where('id', $destination)->lockForUpdate()->exists() ? self::lieu($destination) : throw new RegleMetier('Destination introuvable.');
                if ($raison = self::compatible($l, $famille, $s)) {
                    throw new RegleMetier($raison);
                }
                if ((int) $l->occupation + $quantite > (int) $l->capacite) {
                    throw new RegleMetier("Capacité dépassée : {$l->occupation} présents sur {$l->capacite}, arrivée de $quantite refusée.");
                }
            }
            Lieux::preparer($famille, $idSource, $origine ?: null, trim($motif));
            Lieux::executer(function () use ($famille, $idSource, $origine, $destination, $quantite) {
                $cle = ['famille' => $famille, 'id_source' => $idSource];
                if ($origine !== 0) {
                    $q = (int) DB::table('modInfra_positions')->where($cle + ['id_batiment' => $origine])->value('quantite');
                    $q === $quantite
                        ? DB::table('modInfra_positions')->where($cle + ['id_batiment' => $origine])->delete()
                        : DB::table('modInfra_positions')->where($cle + ['id_batiment' => $origine])->update(['quantite' => $q - $quantite]);
                }
                if ($destination !== 0) {
                    $q = DB::table('modInfra_positions')->where($cle + ['id_batiment' => $destination])->value('quantite');
                    $q === null
                        ? DB::table('modInfra_positions')->insert($cle + ['id_batiment' => $destination, 'quantite' => $quantite])
                        : DB::table('modInfra_positions')->where($cle + ['id_batiment' => $destination])->update(['quantite' => (int) $q + $quantite]);
                }
            });
            Lieux::reinitialiser();
            Journal::enregistrer('lieu.deplacement', 'INF', $famille, $idSource, ['origine' => $origine], ['destination' => $destination, 'quantite' => $quantite], trim($motif));
        });
    }

    /** Occupation par source pour un lieu. */
    public static function occupants(int $idBatiment)
    {
        return DB::table('modInfra_positions')->where('id_batiment', $idBatiment)->orderBy('famille')->get()->map(function ($p) {
            $p->nom = match ($p->famille) {
                'volaille' => DB::table('modLotVol_lots_volailles')->where('id', $p->id_source)->value('nom_lot'),
                'repro_m', 'repro_f' => DB::table('bandes_parentales')->where('id', $p->id_source)->value('nom_bande'),
                'ovin' => DB::table('OVINS_animaux')->where('id_animal', $p->id_source)->value('code_animal'),
                default => null,
            } ?? '#'.$p->id_source;

            return $p;
        });
    }

    public static function journal(int $limite = 100)
    {
        return DB::table('modInfra_journal')->orderByDesc('id')->limit($limite)->get();
    }
}
