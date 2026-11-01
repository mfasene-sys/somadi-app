<?php

namespace App\Domaine;

use App\Exceptions\RegleMetier;
use App\Support\Empreinte;
use App\Support\Format;
use App\Support\Journal;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/** Incubation et éclosion (INC). */
class Incubation
{
    public const DUREES = ['poulet' => 21, 'caille' => 17];

    public const JOUR_MIRAGE = 10;

    // ---------- Lectures ----------

    /** Œufs disponibles d'une incubation : fertiles après mirage, sinon œufs retenus (RG-INC-003). */
    public static function disponibles(object $l): int
    {
        return self::aMire($l) ? (int) $l->nombre_fertiles : (int) $l->nombre_oeufs;
    }

    public static function aMire(object $l): bool
    {
        return $l->date_mirage !== null || (int) $l->nombre_fertiles > 0;
    }

    public static function estEclose(object $l): bool
    {
        return $l->date_eclosion !== null || (int) $l->nombre_eclos > 0;
    }

    /** Stade lisible d'une incubation. */
    public static function stade(object $l): array
    {
        if (self::estEclose($l)) {
            return ['libelle' => 'Éclos', 'ton' => 'ok'];
        }
        if ($l->transfere) {
            return ['libelle' => 'En éclosoir', 'ton' => 'info'];
        }
        $jour = $l->date_entree ? Carbon::parse($l->date_entree)->diffInDays(today(), false) : null;

        return ['libelle' => $jour !== null ? 'En incubation · J+'.max(0, (int) $jour) : 'En incubation', 'ton' => 'attente'];
    }

    /** Places occupées dans un équipement (incubations non écloses qui le désignent). */
    public static function occupe(string $nomEquipement, ?int $sauf = null): int
    {
        return (int) DB::table('lots_incubation')
            ->where('incubateur', $nomEquipement)
            ->whereNull('date_eclosion')
            ->whereRaw('COALESCE(nombre_eclos,0) = 0')
            ->when($sauf, fn ($q) => $q->where('id', '!=', $sauf))
            ->sum(DB::raw('CASE WHEN date_mirage IS NOT NULL OR nombre_fertiles > 0 THEN nombre_fertiles ELSE nombre_oeufs END'));
    }

    /** @return Collection<int, object> équipements avec places libres */
    public static function equipements(?string $type = null): Collection
    {
        return DB::table('incubateurs')->when($type, fn ($q) => $q->where('type', $type))->orderBy('nom')->get()
            ->map(function ($e) {
                $e->occupe = $e->nom ? self::occupe($e->nom) : 0;
                $e->libre = max(0, (int) $e->capacite - $e->occupe);

                return $e;
            });
    }

    /** RG-INC-004 : date de chargement proposée (jeudi suivant la semaine jeudi–mercredi commune). */
    public static function dateProposee(array $datesRamassage): ?string
    {
        $semaines = collect($datesRamassage)->filter()->map(function ($d) {
            $c = Carbon::parse($d);

            return $c->copy()->subDays(($c->dayOfWeek - Carbon::THURSDAY + 7) % 7)->toDateString();
        })->unique();

        return $semaines->count() === 1 ? Carbon::parse($semaines->first())->addDays(7)->toDateString() : null;
    }

    public static function prevision(string $espece, string $dateEntree): string
    {
        return Carbon::parse($dateEntree)->addDays(self::DUREES[$espece] ?? 21)->toDateString();
    }

    // ---------- Écritures ----------

    /** RG-INC-001 : un nom d'équipement utilisable dans un lot (≤ 50 caractères, unique). */
    private static function equipementVerrouille(int $id, string $typeAttendu): object
    {
        $tous = DB::table('incubateurs')->orderBy('id')->lockForUpdate()->get();
        $e = $tous->firstWhere('id', $id) ?? throw new RegleMetier($typeAttendu === 'eclosoir' ? 'Choisissez un éclosoir existant.' : 'Choisissez un incubateur existant.');
        if ($e->type !== $typeAttendu) {
            throw new RegleMetier($typeAttendu === 'eclosoir' ? 'Choisissez un éclosoir.' : 'Choisissez un incubateur.');
        }
        $nom = trim((string) $e->nom);
        if ($nom === '' || mb_strlen($nom) > 50) {
            throw new RegleMetier('Le nom d’un équipement doit contenir de 1 à 50 caractères pour être enregistré dans un lot.');
        }
        if ($tous->filter(fn ($x) => trim((string) $x->nom) === $nom)->count() > 1) {
            throw new RegleMetier('Deux équipements portent le même nom. Corrigez leur référentiel.');
        }
        $e->nom = $nom;

        return $e;
    }

    private static function trace(?string $obs, string $texte): string
    {
        $ligne = '['.now()->format('d/m/Y H:i').'] '.Format::auteur().' : '.$texte;
        $nouveau = trim(($obs ? $obs."\n" : '').$ligne);
        if (strlen($nouveau) > 65000) {
            throw new RegleMetier('Les observations sont trop longues pour ajouter une trace.');
        }

        return $nouveau;
    }

    private static function verrouiller(int $id, ?string $empreinte): object
    {
        $l = DB::table('lots_incubation')->where('id', $id)->lockForUpdate()->first() ?? throw new RegleMetier('Lot introuvable.');
        if ($empreinte !== null) {
            Empreinte::verifier($l, $empreinte, 'Ce lot a changé depuis l’ouverture de la page. Rechargez-le avant de recommencer.');
        }

        return $l;
    }

    /** RG-INC-002 à 005 : chargement d'un incubateur. */
    public static function charger(array $idsLots, int $idIncubateur, string $dateEntree, ?string $observations): int
    {
        $ids = array_values(array_unique(array_map('intval', $idsLots)));
        if (count($ids) < 1 || count($ids) > 500) {
            throw new RegleMetier('Sélectionnez de 1 à 500 lots d’œufs.');
        }

        return DB::transaction(function () use ($ids, $idIncubateur, $dateEntree, $observations) {
            $inc = self::equipementVerrouille($idIncubateur, 'incubateur');
            $lots = DB::table('lots_oeufs_ramasses as l')
                ->leftJoin('stock_oeufs_ramasses as s', 's.id', '=', 'l.id_stock_oeufs')
                ->leftJoin('bandes_parentales as b', 'b.id', '=', 'l.id_bande')
                ->whereIn('l.id', $ids)->orderBy('l.id')->lockForUpdate()
                ->get(['l.*', 's.date_ramassage', 's.id_bande as stock_bande', 'b.espece', 'b.nom_bande']);
            if ($lots->count() !== count($ids) || $lots->contains(fn ($l) => $l->type_lot !== 'incuber' || $l->statut !== 'nouveau' || (int) $l->quantite <= 0)) {
                throw new RegleMetier('Un lot sélectionné a déjà été chargé ou n’est plus disponible.');
            }
            if ($lots->contains(fn ($l) => ! $l->id_bande || (int) $l->stock_bande !== (int) $l->id_bande || ! isset(self::DUREES[$l->espece]))) {
                throw new RegleMetier('La bande ou l’espèce d’un lot est incohérente.');
            }
            if ($lots->pluck('id_bande')->unique()->count() !== 1) {
                throw new RegleMetier('Tous les lots sélectionnés doivent appartenir à la même bande.');
            }
            $date = Carbon::parse($dateEntree)->toDateString();
            if ($lots->contains(fn ($l) => ! $l->date_ramassage || $l->date_ramassage > $date)) {
                throw new RegleMetier('Le chargement ne peut pas précéder le ramassage.');
            }
            $total = (int) $lots->sum('quantite');
            $libre = (int) $inc->capacite - self::occupe($inc->nom);
            if ($total > $libre) {
                throw new RegleMetier("La capacité disponible de l’incubateur est insuffisante ($libre places pour $total œufs).");
            }
            $premier = $lots->first();
            $prevision = self::prevision($premier->espece, $date);
            $nom = mb_substr($premier->nom_bande.' · '.Carbon::parse($date)->format('d/m/Y'), 0, 50);
            $obs = self::trace($observations, "chargement de $total œufs dans {$inc->nom} (lots ".$lots->pluck('id')->implode(', ').')');
            $id = DB::table('lots_incubation')->insertGetId([
                'nom_lot' => $nom, 'date_entree' => $date, 'date_eclosion_previsionnelle' => $prevision,
                'espece' => $premier->espece, 'type_oeuf' => 'produit', 'nombre_oeufs' => $total, 'nombre_oeufs_initial' => $total,
                'incubateur' => $inc->nom, 'observations' => $obs, 'id_bande' => $premier->id_bande,
            ]);
            DB::table('resa_lots_previsionnels')->insert(['id_lot' => $id, 'espece' => $premier->espece, 'date_eclosion_prevue' => $prevision, 'nombre_prevu' => $total]);
            DB::table('lots_oeufs_ramasses')->whereIn('id', $ids)->update(['statut' => 'charge']);
            Journal::enregistrer('incubation.chargement', 'INC', 'incubation', $id, apres: ['oeufs' => $total, 'incubateur' => $inc->nom, 'lots' => $ids, 'prevision' => $prevision]);

            return $id;
        });
    }

    /** RG-INC-006 */
    public static function ajuster(int $id, int $nombre, string $empreinte): void
    {
        DB::transaction(function () use ($id, $nombre, $empreinte) {
            DB::table('incubateurs')->orderBy('id')->lockForUpdate()->get();
            $l = self::verrouiller($id, $empreinte);
            if (self::estEclose($l)) {
                throw new RegleMetier('L’éclosion de ce lot est déjà enregistrée.');
            }
            if (! $l->date_entree) {
                throw new RegleMetier('Date d’entrée du lot invalide.');
            }
            if (self::aMire($l) || $l->transfere || today()->gte(Carbon::parse($l->date_entree)->addDays(self::JOUR_MIRAGE))) {
                throw new RegleMetier('L’ajustement est réservé à la période avant J+10 et avant mirage.');
            }
            if ($nombre <= 0) {
                throw new RegleMetier('Le nombre d’œufs doit être strictement positif.');
            }
            if ($nombre > (int) $l->nombre_oeufs_initial) {
                throw new RegleMetier('Le nombre ne peut pas dépasser le nombre initial.');
            }
            if ($nombre > (int) $l->nombre_oeufs) {
                $cap = (int) DB::table('incubateurs')->where('nom', $l->incubateur)->value('capacite');
                if ($nombre > $cap - self::occupe((string) $l->incubateur, $id)) {
                    throw new RegleMetier('La capacité disponible ne permet pas cet ajustement.');
                }
            }
            $obs = self::trace($l->observations, "ajustement de {$l->nombre_oeufs} à $nombre œufs");
            DB::table('lots_incubation')->where('id', $id)->update(['nombre_oeufs' => $nombre, 'observations' => $obs]);
            Journal::enregistrer('incubation.ajustement', 'INC', 'incubation', $id, ['nombre_oeufs' => (int) $l->nombre_oeufs], ['nombre_oeufs' => $nombre]);
        });
    }

    /** RG-INC-007 */
    public static function mirer(int $id, int $fertiles, string $date, string $empreinte): void
    {
        DB::transaction(function () use ($id, $fertiles, $date, $empreinte) {
            $l = self::verrouiller($id, $empreinte);
            if (self::estEclose($l)) {
                throw new RegleMetier('L’éclosion de ce lot est déjà enregistrée.');
            }
            if (self::aMire($l) || $l->transfere) {
                throw new RegleMetier('Le mirage est déjà enregistré ou le lot est transféré.');
            }
            $j10 = Carbon::parse($l->date_entree)->addDays(self::JOUR_MIRAGE);
            $d = Carbon::parse($date);
            if (today()->lt($j10) || $d->lt($j10) || $d->isAfter(today())) {
                throw new RegleMetier('Mirage possible seulement à partir de J+10 ('.$j10->format('d/m/Y').') et au plus tard aujourd’hui.');
            }
            if ($fertiles < 0 || $fertiles > (int) $l->nombre_oeufs) {
                throw new RegleMetier('Le nombre fertile dépasse les œufs retenus.');
            }
            $taux = (int) $l->nombre_oeufs > 0 ? (int) round(100 * $fertiles / (int) $l->nombre_oeufs) : 0;
            $obs = self::trace($l->observations, "mirage : $fertiles fertiles sur {$l->nombre_oeufs} ($taux %)");
            DB::table('lots_incubation')->where('id', $id)->update([
                'nombre_fertiles' => $fertiles, 'taux_fertilite' => $taux, 'date_mirage' => $d->toDateString(),
                'utilisateur_mirage' => Format::auteur(), 'observations' => $obs,
            ]);
            Journal::enregistrer('incubation.mirage', 'INC', 'incubation', $id, apres: ['fertiles' => $fertiles, 'taux_fertilite' => $taux]);
        });
    }

    /** RG-INC-008 à 010 : transfert vers un ou deux éclosoirs, scission éventuelle en A et B. */
    public static function transferer(int $id, int $eclosoir1, ?int $eclosoir2, string $date, string $empreinte): ?int
    {
        return DB::transaction(function () use ($id, $eclosoir1, $eclosoir2, $date, $empreinte) {
            if ($eclosoir2 !== null && $eclosoir2 === $eclosoir1) {
                throw new RegleMetier('Choisissez un ou deux éclosoirs distincts.');
            }
            $e1 = self::equipementVerrouille($eclosoir1, 'eclosoir');
            $e2 = $eclosoir2 ? self::equipementVerrouille($eclosoir2, 'eclosoir') : null;
            $l = self::verrouiller($id, $empreinte);
            $dispo = self::disponibles($l);
            if (self::estEclose($l) || $l->transfere || $dispo <= 0) {
                throw new RegleMetier('Ce lot ne peut pas être transféré : aucun œuf disponible ou transfert déjà enregistré.');
            }
            $d = Carbon::parse($date);
            if ($d->lt(Carbon::parse($l->date_entree)) || $d->isAfter(today())) {
                throw new RegleMetier('Le transfert ne peut pas précéder le chargement ni être daté dans le futur.');
            }
            if ($l->date_mirage && (Carbon::parse($l->date_mirage)->isAfter(today()) || $d->lt(Carbon::parse($l->date_mirage)))) {
                throw new RegleMetier('Date de mirage future incohérente ou postérieure au transfert.');
            }
            $libre1 = max(0, (int) $e1->capacite - self::occupe($e1->nom));
            $jour = $d->toDateString();
            $auteur = Format::auteur();

            if ($libre1 >= $dispo) {
                $obs = self::trace($l->observations, "transfert complet de $dispo œufs vers {$e1->nom}");
                DB::table('lots_incubation')->where('id', $id)->update([
                    'transfere' => 1, 'incubateur' => $e1->nom, 'date_transfert' => $jour, 'utilisateur_transfert' => $auteur, 'observations' => $obs,
                ]);
                Journal::enregistrer('incubation.transfert', 'INC', 'incubation', $id, ['incubateur' => $l->incubateur], ['incubateur' => $e1->nom]);

                return null;
            }

            $libre2 = $e2 ? max(0, (int) $e2->capacite - self::occupe($e2->nom)) : 0;
            if (! $e2 || $libre1 <= 0 || $libre1 + $libre2 < $dispo) {
                throw new RegleMetier('Capacité insuffisante. Choisissez un principal disposant de places et, si nécessaire, un second éclosoir.');
            }
            if (DB::table('resa_reservations')->where('id_lot_prevu', $id)->lockForUpdate()->exists()) {
                throw new RegleMetier('Ce lot possède des réservations : utilisez un éclosoir assez grand pour un transfert complet. La scission nécessite une réaffectation préalable des réservations.');
            }
            $prev = DB::table('resa_lots_previsionnels')->where('id_lot', $id)->lockForUpdate()->first();
            if ((int) $l->nombre_oeufs_initial < (int) $l->nombre_oeufs || ($prev && (int) $prev->nombre_prevu < 0)) {
                throw new RegleMetier('Les compteurs historiques sont incohérents : scission bloquée.');
            }

            $ratio = $libre1 / $dispo;
            $partage = fn (int $n) => [(int) floor($n * $ratio), $n - (int) floor($n * $ratio)];
            [$iniA, $iniB] = $partage((int) $l->nombre_oeufs_initial);
            [$oeA, $oeB] = $partage((int) $l->nombre_oeufs);
            [$feA, $feB] = $partage((int) $l->nombre_fertiles);
            [$prA, $prB] = $partage((int) ($prev->nombre_prevu ?? $l->nombre_oeufs));
            $taux = fn ($f, $o) => self::aMire($l) ? ($o > 0 ? (int) round(100 * $f / $o) : 0) : $l->taux_fertilite;
            $base = mb_substr((string) $l->nom_lot, 0, 46);

            DB::table('lots_incubation')->where('id', $id)->update([
                'nom_lot' => $base.' A', 'nombre_oeufs_initial' => $iniA, 'nombre_oeufs' => $oeA, 'nombre_fertiles' => $feA,
                'taux_fertilite' => $taux($feA, $oeA), 'transfere' => 1, 'incubateur' => $e1->nom, 'date_transfert' => $jour,
                'utilisateur_transfert' => $auteur, 'observations' => self::trace($l->observations, "scission : partie A vers {$e1->nom}, partie B vers {$e2->nom}"),
            ]);
            $idB = DB::table('lots_incubation')->insertGetId([
                'nom_lot' => $base.' B', 'date_entree' => $l->date_entree, 'date_eclosion_previsionnelle' => $l->date_eclosion_previsionnelle,
                'espece' => $l->espece, 'type_oeuf' => $l->type_oeuf, 'nombre_oeufs' => $oeB, 'nombre_oeufs_initial' => $iniB,
                'incubateur' => $e2->nom, 'transfere' => 1, 'date_transfert' => $jour, 'utilisateur_transfert' => $auteur,
                'observations' => self::trace(null, "partie B issue de la scission du lot n° $id"), 'id_bande' => $l->id_bande,
                'date_mirage' => $l->date_mirage, 'nombre_fertiles' => $feB, 'utilisateur_mirage' => $l->utilisateur_mirage, 'taux_fertilite' => $taux($feB, $oeB),
            ]);
            if ($prev) {
                DB::table('resa_lots_previsionnels')->where('id_lot', $id)->update(['nombre_prevu' => $prA]);
            }
            DB::table('resa_lots_previsionnels')->insert(['id_lot' => $idB, 'espece' => $l->espece, 'date_eclosion_prevue' => $l->date_eclosion_previsionnelle, 'nombre_prevu' => $prB]);
            Journal::enregistrer('incubation.scission', 'INC', 'incubation', $id, (array) $l, ['A' => [$iniA, $oeA, $feA, $e1->nom], 'B' => [$idB, $iniB, $oeB, $feB, $e2->nom]]);

            return $idB;
        });
    }

    /** RG-INC-011 à 014 : éclosion, création du stock de poussins et du lot volaille. */
    public static function eclore(int $id, array $d, string $empreinte): ?int
    {
        return DB::transaction(function () use ($id, $d, $empreinte) {
            $l = self::verrouiller($id, $empreinte);
            if (self::estEclose($l)) {
                throw new RegleMetier('L’éclosion de ce lot est déjà enregistrée.');
            }
            $mirageNul = self::aMire($l) && $l->date_mirage && (int) $l->nombre_fertiles === 0;
            if (! $l->transfere && ! $mirageNul) {
                throw new RegleMetier('Enregistrez le transfert avant l’éclosion.');
            }
            if ($l->date_eclosion_previsionnelle && today()->lt(Carbon::parse($l->date_eclosion_previsionnelle))) {
                throw new RegleMetier('La date prévisionnelle d’éclosion n’est pas encore atteinte.');
            }
            $date = Carbon::parse($d['date_eclosion']);
            $minimum = collect([$l->date_entree, $l->date_transfert, $l->date_mirage])->filter()->max();
            if ($date->isAfter(today()) || ($minimum && $date->lt(Carbon::parse($minimum)))) {
                throw new RegleMetier('La date réelle doit être entre le transfert et aujourd’hui.');
            }
            $eclos = (int) $d['nombre_eclos'];
            $dispo = self::disponibles($l);
            if ($eclos < 0 || $eclos > $dispo) {
                throw new RegleMetier("Le nombre éclos dépasse le nombre d’œufs disponibles ($dispo).");
            }
            $methode = $d['methode'] ?? 'estimation';
            if ($methode === 'mesure') {
                if (! $l->date_mirage) {
                    throw new RegleMetier('Un mirage réellement réalisé est nécessaire pour calculer les taux séparément.');
                }
                $fertiles = (int) $l->nombre_fertiles;
                $tauxEclosion = $fertiles > 0 ? (int) round(100 * $eclos / $fertiles) : 0;
                $tauxFertilite = (int) $l->nombre_oeufs > 0 ? (int) round(100 * $fertiles / (int) $l->nombre_oeufs) : 0;
            } elseif ($methode === 'estimation') {
                $tauxEclosion = $tauxFertilite = $dispo > 0 ? (int) round(100 * sqrt($eclos / $dispo)) : 0;
            } else {
                throw new RegleMetier('Méthode de calcul invalide.');
            }
            if (DB::table('stock_poussins_jour')->where('id_lot', $id)->exists()
                || DB::table('modLotVol_lots_volailles')->where('id_lot_incubation_source', $id)
                    ->when($l->nom_lot !== null && $l->nom_lot !== '', fn ($q) => $q->orWhere('origine', $l->nom_lot))->exists()) {
                throw new RegleMetier('Un stock ou un lot de poussins existe déjà pour cette incubation. Vérifiez les données avant toute nouvelle saisie.');
            }
            $jour = $date->toDateString();
            $auteur = Format::auteur();
            $idLotVolaille = null;
            if ($eclos > 0) {
                $race = trim((string) ($d['race'] ?? ''));
                if ($race === '' || mb_strlen($race) > 50) {
                    throw new RegleMetier('Renseignez la race des poussins (50 caractères maximum).');
                }
                DB::table('stock_poussins_jour')->insert(['date_eclosion' => $jour, 'espece' => $l->espece, 'nombre' => $eclos, 'id_lot' => $id, 'utilisateur' => $auteur]);
                $idLotVolaille = LotsVolailles::creer([
                    'nom_lot' => mb_substr((string) $l->nom_lot, 0, 100), 'espece' => $l->espece, 'race' => $race,
                    'nombre_initial' => $eclos, 'date_entree' => $jour, 'origine' => mb_substr((string) $l->nom_lot, 0, 100),
                    'remarques' => "Issu de l’incubation n° $id", 'id_lot_incubation_source' => $id,
                ], ! empty($d['programme_soins']));
            }
            $methodeTexte = $methode === 'mesure' ? 'mesure (mirage réel)' : 'estimation SOMADI';
            DB::table('lots_incubation')->where('id', $id)->update([
                'nombre_eclos' => $eclos, 'taux_eclosion' => $tauxEclosion, 'taux_fertilite' => $tauxFertilite, 'date_eclosion' => $jour,
                'utilisateur_eclosion' => $auteur,
                'observations' => self::trace($l->observations, "éclosion : $eclos poussins, méthode $methodeTexte, éclosion $tauxEclosion %, fertilité $tauxFertilite %".($idLotVolaille ? ", lot volaille n° $idLotVolaille" : ', aucun lot créé')),
            ]);
            Journal::enregistrer('incubation.eclosion', 'INC', 'incubation', $id, apres: ['eclos' => $eclos, 'methode' => $methode, 'taux_eclosion' => $tauxEclosion, 'taux_fertilite' => $tauxFertilite, 'lot_volaille' => $idLotVolaille]);

            return $idLotVolaille;
        });
    }

    // ---------- Référentiel des équipements (EC22) ----------

    public static function enregistrerEquipement(?int $id, array $d): int
    {
        return DB::transaction(function () use ($id, $d) {
            $tous = DB::table('incubateurs')->orderBy('id')->lockForUpdate()->get();
            $nom = trim($d['nom']);
            if ($tous->contains(fn ($e) => trim((string) $e->nom) === $nom && (int) $e->id !== (int) $id)) {
                throw new RegleMetier('Un équipement porte déjà ce nom.');
            }
            $valeurs = ['nom' => $nom, 'capacite' => (int) $d['capacite'], 'type' => $d['type'], 'date_mise_service' => $d['date_mise_service'] ?? null];
            if ($id) {
                $e = $tous->firstWhere('id', $id) ?? throw new RegleMetier('Équipement introuvable.');
                // EC22 : les lots référencent l'équipement par son nom ; le renommer casserait l'historique.
                if (trim((string) $e->nom) !== $nom && DB::table('lots_incubation')->where('incubateur', $e->nom)->exists()) {
                    throw new RegleMetier('Des lots d’incubation portent le nom de cet équipement : il ne peut pas être renommé.');
                }
                if ($e->type !== $d['type'] && self::occupe((string) $e->nom) > 0) {
                    throw new RegleMetier('Cet équipement contient des œufs : son type ne peut pas changer.');
                }
                DB::table('incubateurs')->where('id', $id)->update($valeurs);
                Journal::enregistrer('equipement.modification', 'INC', 'incubateur', $id, (array) $e, $valeurs);

                return $id;
            }
            $id = DB::table('incubateurs')->insertGetId($valeurs);
            Journal::enregistrer('equipement.creation', 'INC', 'incubateur', $id, apres: $valeurs);

            return $id;
        });
    }
}
