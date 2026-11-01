<?php

namespace App\Domaine\Ovins;

use App\Exceptions\RegleMetier;
use App\Support\Empreinte;
use App\Support\Format;
use App\Support\Journal;
use Illuminate\Support\Facades\DB;

/** Sites, lots et propriétaires ovins (RG-OVA-005 à 007). */
class Referentiels
{
    public static function sites(bool $actifsSeulement = false)
    {
        return DB::table('OVINS_sites')->when($actifsSeulement, fn ($q) => $q->where('actif', 1))->orderBy('nom_site')->get();
    }

    public static function lots(bool $actifsSeulement = false)
    {
        return DB::table('OVINS_lots as l')->join('OVINS_sites as s', 's.id_site', '=', 'l.id_site')
            ->when($actifsSeulement, fn ($q) => $q->where('l.statut', 'actif'))
            ->orderBy('s.nom_site')->orderBy('l.nom_lot')->get(['l.*', 's.nom_site']);
    }

    public static function proprietaires(bool $actifsSeulement = false)
    {
        return DB::table('OVINS_proprietaires')->when($actifsSeulement, fn ($q) => $q->where('actif', 1))->orderBy('nom_proprietaire')->get();
    }

    public static function libelleType(?string $t): string
    {
        return config('somadi.ovins.types_lot')[$t] ?? ucfirst((string) $t);
    }

    // ----- Sites (RG-OVA-007)

    public static function enregistrerSite(?int $id, array $d, ?string $empreinte = null): int
    {
        $v = [
            'code_site' => mb_strtoupper(trim($d['code_site'])), 'nom_site' => trim($d['nom_site']), 'localisation' => $d['localisation'] ?? null,
            'capacite_ovins' => max(0, (int) ($d['capacite_ovins'] ?? 0)), 'type_site' => $d['type_site'] ?? null,
            'actif' => empty($d['actif']) ? 0 : 1, 'observations' => $d['observations'] ?? null,
        ];
        if ($v['code_site'] === '' || $v['nom_site'] === '') {
            throw new RegleMetier('Le code et le nom du site sont obligatoires.');
        }

        return DB::transaction(function () use ($id, $v, $empreinte) {
            if (DB::table('OVINS_sites')->where('code_site', $v['code_site'])->when($id, fn ($q) => $q->where('id_site', '<>', $id))->exists()) {
                throw new RegleMetier("Le code « {$v['code_site']} » est déjà utilisé par un autre site.");
            }
            if ($id) {
                $avant = DB::table('OVINS_sites')->where('id_site', $id)->lockForUpdate()->first() ?? throw new RegleMetier('Site introuvable.');
                Empreinte::verifier($avant, $empreinte);
                if (! $v['actif'] && DB::table('OVINS_animaux')->where('id_site', $id)->where('statut', 'en_ferme')->exists()) {
                    throw new RegleMetier('Des animaux sont encore présents sur ce site : déplacez-les avant de le désactiver.');
                }
                DB::table('OVINS_sites')->where('id_site', $id)->update($v);
                Journal::enregistrer('site.modification', 'OVA', 'site_ovin', $id, (array) $avant, $v);

                return $id;
            }
            $id = DB::table('OVINS_sites')->insertGetId($v);
            Journal::enregistrer('site.creation', 'OVA', 'site_ovin', $id, apres: $v);

            return $id;
        });
    }

    // ----- Propriétaires (RG-OVA-006)

    public static function enregistrerProprietaire(?int $id, array $d, ?string $empreinte = null): int
    {
        $couleur = trim((string) ($d['couleur_hex'] ?? ''));
        if ($couleur !== '' && ! preg_match('/^#[0-9a-fA-F]{6}$/', $couleur)) {
            throw new RegleMetier('Couleur invalide (format #RRGGBB).');
        }
        $v = [
            'nom_proprietaire' => trim($d['nom_proprietaire']), 'telephone' => $d['telephone'] ?? null,
            'couleur_hex' => $couleur ?: null, 'actif' => empty($d['actif']) ? 0 : 1, 'observations' => $d['observations'] ?? null,
        ];
        if ($v['nom_proprietaire'] === '') {
            throw new RegleMetier('Le nom du propriétaire est obligatoire.');
        }

        return DB::transaction(function () use ($id, $v, $empreinte) {
            if ($id) {
                $avant = DB::table('OVINS_proprietaires')->where('id_proprietaire', $id)->lockForUpdate()->first() ?? throw new RegleMetier('Propriétaire introuvable.');
                Empreinte::verifier($avant, $empreinte);
                DB::table('OVINS_proprietaires')->where('id_proprietaire', $id)->update($v);
                Journal::enregistrer('proprietaire.modification', 'OVA', 'proprietaire_ovin', $id, (array) $avant, $v);

                return $id;
            }
            $id = DB::table('OVINS_proprietaires')->insertGetId($v);
            Journal::enregistrer('proprietaire.creation', 'OVA', 'proprietaire_ovin', $id, apres: $v);

            return $id;
        });
    }

    // ----- Lots (RG-OVA-005)

    /** Création : l'effectif initial est celui du lot (achat, constitution) ; il n'est jamais déduit des fiches. */
    public static function creerLot(array $d): int
    {
        $v = self::valeursLot($d) + ['effectif_initial' => max(0, (int) ($d['effectif_initial'] ?? 0)), 'statut' => 'actif'];
        $v['effectif_encours'] = $v['effectif_initial'];

        return DB::transaction(function () use ($v) {
            self::siteActif($v['id_site']);
            $id = DB::table('OVINS_lots')->insertGetId($v);
            if ($v['effectif_initial'] > 0) {
                Mouvements::inscrire(['date_mvt' => $v['date_entree'], 'type_mvt' => 'entree', 'id_lot' => $id, 'quantite' => $v['effectif_initial'],
                    'id_site_destination' => $v['id_site'], 'commentaire' => 'Constitution du lot']);
            }
            Journal::enregistrer('lot_ovin.creation', 'OVA', 'lot_ovin', $id, apres: $v);

            return $id;
        });
    }

    /** Les informations générales ; le site et l'effectif ne changent pas ici (transferts et ajustements tracés). */
    public static function modifierLot(int $id, array $d, string $empreinte): void
    {
        DB::transaction(function () use ($id, $d, $empreinte) {
            $avant = DB::table('OVINS_lots')->where('id_lot', $id)->lockForUpdate()->first() ?? throw new RegleMetier('Lot introuvable.');
            Empreinte::verifier($avant, $empreinte);
            $v = self::valeursLot($d + ['id_site' => $avant->id_site]);
            unset($v['id_site']);
            $v['statut'] = in_array($d['statut'] ?? 'actif', ['actif', 'clos'], true) ? $d['statut'] : 'actif';
            if ($v['statut'] === 'clos' && DB::table('OVINS_animaux')->where('id_lot', $id)->where('statut', 'en_ferme')->exists()) {
                throw new RegleMetier('Des animaux en ferme sont encore dans ce lot : transférez-les avant de le clore.');
            }
            DB::table('OVINS_lots')->where('id_lot', $id)->update($v);
            Journal::enregistrer('lot_ovin.modification', 'OVA', 'lot_ovin', $id, (array) $avant, $v);
        });
    }

    /** RG-OVA-003 (REC-OVA-003) : correction d'effectif tracée, jamais silencieuse. */
    public static function ajusterEffectif(int $id, int $nouveau, string $motif, string $date): void
    {
        if ($nouveau < 0) {
            throw new RegleMetier('L’effectif ne peut pas être négatif.');
        }
        if (mb_strlen(trim($motif)) < 5) {
            throw new RegleMetier('Indiquez le motif de la correction (5 caractères au moins).');
        }
        DB::transaction(function () use ($id, $nouveau, $motif, $date) {
            $lot = DB::table('OVINS_lots')->where('id_lot', $id)->lockForUpdate()->first() ?? throw new RegleMetier('Lot introuvable.');
            $ecart = $nouveau - (int) $lot->effectif_encours;
            if ($ecart === 0) {
                throw new RegleMetier('L’effectif saisi est déjà celui du lot.');
            }
            DB::table('OVINS_lots')->where('id_lot', $id)->update(['effectif_encours' => $nouveau]);
            Mouvements::inscrire(['date_mvt' => $date, 'type_mvt' => 'ajustement', 'id_lot' => $id, 'quantite' => $ecart,
                'id_site_source' => $lot->id_site, 'commentaire' => trim($motif)]);
            Journal::enregistrer('lot_ovin.ajustement', 'OVA', 'lot_ovin', $id, ['effectif_encours' => $lot->effectif_encours], ['effectif_encours' => $nouveau], trim($motif));
        });
    }

    private static function valeursLot(array $d): array
    {
        $nom = trim((string) ($d['nom_lot'] ?? ''));
        if ($nom === '' || mb_strlen($nom) > 150) {
            throw new RegleMetier('Le nom du lot est obligatoire (150 caractères maximum).');
        }
        $type = (string) ($d['type_lot'] ?? '');
        if (! array_key_exists($type, config('somadi.ovins.types_lot'))) {
            throw new RegleMetier('Type de lot inconnu.');
        }
        $pu = isset($d['valeur_unitaire_achat']) && $d['valeur_unitaire_achat'] !== '' ? \App\Support\Montant::saisie($d['valeur_unitaire_achat'], 'La valeur unitaire') : null;
        $pt = isset($d['valeur_totale_achat']) && $d['valeur_totale_achat'] !== '' ? \App\Support\Montant::saisie($d['valeur_totale_achat'], 'La valeur totale') : null;

        return [
            'nom_lot' => $nom, 'type_lot' => $type, 'race' => $d['race'] ?? null, 'id_site' => (int) $d['id_site'],
            'date_entree' => $d['date_entree'], 'provenance' => $d['provenance'] ?? null,
            'valeur_unitaire_achat' => $pu === null ? null : \App\Support\Montant::versBase($pu),
            'valeur_totale_achat' => $pt === null ? null : \App\Support\Montant::versBase($pt), 'observations' => $d['observations'] ?? null,
        ];
    }

    public static function siteActif(int $id): object
    {
        $s = DB::table('OVINS_sites')->where('id_site', $id)->first();
        if (! $s || ! $s->actif) {
            throw new RegleMetier('Site introuvable ou inactif.');
        }

        return $s;
    }

    /** REC-OVA-003 : fiches présentes par lot comparées à l'effectif courant, écart affiché sans être masqué. */
    public static function ecartsLots()
    {
        $fiches = DB::table('OVINS_animaux')->where('statut', 'en_ferme')->whereNull('date_sortie')->whereNotNull('id_lot')
            ->groupBy('id_lot')->select('id_lot', DB::raw('COUNT(*) n'));

        return DB::table('OVINS_lots as l')->join('OVINS_sites as s', 's.id_site', '=', 'l.id_site')
            ->leftJoinSub($fiches, 'f', 'f.id_lot', '=', 'l.id_lot')
            ->orderBy('s.nom_site')->orderBy('l.nom_lot')
            ->get(['l.*', 's.nom_site', DB::raw('COALESCE(f.n,0) fiches')]);
    }

    public static function auteur(): string
    {
        return Format::auteur();
    }
}
