<?php

namespace App\Domaine\Organisation;

use App\Exceptions\RegleMetier;
use App\Support\Journal;
use App\Support\Lieux;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

/** Planning des emplacements : réservations et interventions (IFP, RG-IFP-002 à 012 ; EC01). */
class Planning
{
    public const NATURES = ['reservation' => 'Réservation', 'nettoyage' => 'Nettoyage', 'vide_sanitaire' => 'Vide sanitaire', 'maintenance' => 'Maintenance'];

    public const STATUTS = ['prevu' => ['Prévu', 'info'], 'en_cours' => ['En cours', 'attente'], 'termine' => ['Terminé', 'ok'], 'annule' => ['Annulé', 'neutre']];

    private static function valider(array $d): array
    {
        if (! isset(self::NATURES[$d['nature'] ?? ''])) {
            throw new RegleMetier('Nature invalide.');
        }
        try {
            $deb = Carbon::createFromFormat('!Y-m-d', (string) ($d['date_debut'] ?? ''));
            $fin = Carbon::createFromFormat('!Y-m-d', (string) ($d['date_fin'] ?? ''));
        } catch (\Throwable) {
            throw new RegleMetier('Dates invalides.');
        }
        if (! $deb || ! $fin || $deb->year < 2000 || $fin->year > 2099 || $fin->lt($deb)) {
            throw new RegleMetier('Dates invalides : la fin ne précède pas le début, entre 2000 et 2099.');
        }
        if ($deb->diffInDays($fin) + 1 > 731) {
            throw new RegleMetier('Une période dure au plus 731 jours.');
        }
        $reservation = $d['nature'] === 'reservation';
        $q = $reservation ? (int) ($d['quantite'] ?? 0) : 0;
        if ($reservation && $q <= 0) {
            throw new RegleMetier('Une réservation porte une quantité positive.');
        }
        $lib = trim((string) ($d['libelle'] ?? '')) ?: self::NATURES[$d['nature']];

        return ['id_batiment' => (int) $d['id_batiment'], 'nature' => $d['nature'], 'date_debut' => $deb->toDateString(), 'date_fin' => $fin->toDateString(),
            'quantite' => $q, 'libelle' => mb_substr($lib, 0, 150), 'commentaire' => ($d['commentaire'] ?? null) ?: null];
    }

    /** RG-IFP-005 : pic exact des quantités simultanées (balayage des débuts et des lendemains de fin). */
    public static function pic(array $periodes): int
    {
        $evts = [];
        foreach ($periodes as [$deb, $fin, $q]) {
            $evts[] = [$deb, $q];
            $evts[] = [Carbon::parse($fin)->addDay()->toDateString(), -$q];
        }
        usort($evts, fn ($a, $b) => [$a[0], $a[1]] <=> [$b[0], $b[1]]);
        $cur = $max = 0;
        foreach ($evts as [, $delta]) {
            $cur += $delta;
            $max = max($max, $cur);
        }

        return $max;
    }

    private static function controler(array $v, ?int $sauf): void
    {
        $l = Infrastructures::lieu($v['id_batiment']);
        if (! $l->code || $l->famille === 'a_configurer' || ! $l->actif || ! $l->site_actif) {
            throw new RegleMetier('L’emplacement et son site doivent être actifs et configurés.');
        }
        if ($v['nature'] === 'reservation' && (int) $l->capacite <= 0) {
            throw new RegleMetier('Une réservation exige une capacité positive.');
        }
        $actives = DB::table('app_infra_planning')->where('id_batiment', $v['id_batiment'])->whereIn('statut', ['prevu', 'en_cours'])
            ->when($sauf, fn ($q) => $q->where('id', '<>', $sauf))
            ->where('date_debut', '<=', $v['date_fin'])->where('date_fin', '>=', $v['date_debut'])->lockForUpdate()->get();
        // RG-IFP-006 : réservation et indisponibilité, ou deux indisponibilités, ne se recouvrent pas.
        foreach ($actives as $a) {
            if ($v['nature'] !== 'reservation' || $a->nature !== 'reservation') {
                throw new RegleMetier('Conflit avec « '.$a->libelle.' » du '.\App\Support\Format::date($a->date_debut).' au '.\App\Support\Format::date($a->date_fin).'.');
            }
        }
        if ($v['nature'] === 'reservation') {
            $p = $actives->where('nature', 'reservation')->map(fn ($a) => [$a->date_debut, $a->date_fin, (int) $a->quantite])->values()->all();
            $p[] = [$v['date_debut'], $v['date_fin'], $v['quantite']];
            $pic = self::pic($p);
            if ($pic > (int) $l->capacite) {
                throw new RegleMetier("Capacité dépassée : jusqu’à $pic places réservées en même temps pour {$l->capacite}.");
            }
        }
    }

    public static function creer(array $d): int
    {
        $v = self::valider($d);
        if ($v['date_debut'] < today()->toDateString()) {
            throw new RegleMetier('Une nouvelle période ne commence pas avant aujourd’hui.');
        }

        return DB::transaction(function () use ($v) {
            DB::table('batiments_engraissement')->where('id', $v['id_batiment'])->lockForUpdate()->first();
            self::controler($v, null);
            $id = DB::table('app_infra_planning')->insertGetId($v + ['statut' => 'prevu', 'version' => 1, 'cree_par' => Auth::id(), 'cree_le' => now()]);
            Journal::enregistrer('planning.creation', 'IFP', 'planning', $id, apres: $v);

            return $id;
        });
    }

    /** RG-IFP-004 : seule une période prévue, de version inchangée, se modifie. */
    public static function modifier(int $id, array $d, int $version): void
    {
        DB::transaction(function () use ($id, $d, $version) {
            $p = self::ligne($id);
            if ($p->statut !== 'prevu' || (int) $p->version !== $version) {
                throw new RegleMetier('Cette période a changé ou n’est plus prévue : rechargez la page.');
            }
            $v = self::valider($d + ['id_batiment' => $p->id_batiment, 'nature' => $p->nature]);
            if ($v['date_debut'] < today()->toDateString() && $v['date_debut'] !== $p->date_debut) {
                throw new RegleMetier('Le début ne peut pas être déplacé dans le passé.');
            }
            self::controler($v, $id);
            DB::table('app_infra_planning')->where('id', $id)->update($v + ['version' => $p->version + 1]);
            Journal::enregistrer('planning.modification', 'IFP', 'planning', $id, (array) $p, $v);
        });
    }

    private static function ligne(int $id): object
    {
        return DB::table('app_infra_planning')->where('id', $id)->lockForUpdate()->first() ?? throw new RegleMetier('Période introuvable.');
    }

    private static function changer(int $id, object $p, array $v, string $action, ?string $motif = null): void
    {
        DB::table('app_infra_planning')->where('id', $id)->update($v + ['version' => $p->version + 1]);
        Journal::enregistrer('planning.'.$action, 'IFP', 'planning', $id, ['statut' => $p->statut], $v, $motif);
    }

    public static function annuler(int $id, string $motif): void
    {
        if (mb_strlen(trim($motif)) < 3) {
            throw new RegleMetier('Indiquez le motif.');
        }
        DB::transaction(function () use ($id, $motif) {
            $p = self::ligne($id);
            if ($p->statut !== 'prevu') {
                throw new RegleMetier('Seule une période prévue peut être annulée.');
            }
            self::changer($id, $p, ['statut' => 'annule', 'termine_le' => now(), 'motif' => trim($motif)], 'annulation', trim($motif));
        });
    }

    /** RG-IFP-007 : une réservation se clôture à partir de son début, sans déplacer aucun animal. */
    public static function cloturerReservation(int $id): void
    {
        DB::transaction(function () use ($id) {
            $p = self::ligne($id);
            if ($p->nature !== 'reservation' || $p->statut !== 'prevu') {
                throw new RegleMetier('Seule une réservation prévue se clôture.');
            }
            if (today()->toDateString() < $p->date_debut) {
                throw new RegleMetier('Une réservation se clôture à partir de son premier jour.');
            }
            self::changer($id, $p, ['statut' => 'termine', 'termine_le' => now(), 'cloture_mode' => 'reservation'], 'cloture');
        });
    }

    /** RG-IFP-008, 009 : démarrage manuel d'une intervention sur un lieu vide. */
    public static function demarrer(int $id): void
    {
        DB::transaction(function () use ($id) {
            $p = self::ligne($id);
            if ($p->nature === 'reservation' || $p->statut !== 'prevu') {
                throw new RegleMetier('Seule une intervention prévue peut démarrer.');
            }
            $t = today()->toDateString();
            if ($t < $p->date_debut || $t > $p->date_fin) {
                throw new RegleMetier('Une intervention démarre pendant sa période.');
            }
            $l = Infrastructures::lieu((int) $p->id_batiment);
            if (! $l->actif || ! $l->site_actif || $l->etat !== 'disponible' || $l->disponible_le !== null) {
                throw new RegleMetier('L’emplacement doit être actif et disponible pour démarrer l’intervention.');
            }
            if ((int) $l->occupation > 0) {
                throw new RegleMetier("L’emplacement n’est pas vide ({$l->occupation} sujet(s)) : déplacez-les d’abord.");
            }
            if (DB::table('app_infra_planning')->where('id_batiment', $p->id_batiment)->where('statut', 'en_cours')->where('id', '<>', $id)->exists()) {
                throw new RegleMetier('Une autre intervention est déjà en cours sur cet emplacement.');
            }
            $dispo = Carbon::parse($p->date_fin)->addDay()->toDateString();
            Lieux::preparer('lieu', (int) $p->id_batiment, null, 'Début : '.$p->libelle);
            Lieux::executer(fn () => DB::table('modInfra_emplacements')->where('id_batiment', $p->id_batiment)
                ->update(['etat' => $p->nature, 'disponible_le' => $dispo, 'version' => DB::raw('version + 1')]));
            Lieux::reinitialiser();
            self::changer($id, $p, ['statut' => 'en_cours', 'demarre_le' => now()], 'demarrage');
        });
    }

    /** RG-IFP-010 : fin normale à partir du lendemain du dernier jour, si l'état réel concorde. */
    public static function terminer(int $id): void
    {
        DB::transaction(function () use ($id) {
            $p = self::ligne($id);
            if ($p->statut !== 'en_cours') {
                throw new RegleMetier('Cette intervention n’est pas en cours.');
            }
            $dispo = Carbon::parse($p->date_fin)->addDay()->toDateString();
            if (today()->toDateString() < $dispo) {
                throw new RegleMetier('La fin normale est possible à partir du '.\App\Support\Format::date($dispo).'.');
            }
            $l = Infrastructures::lieu((int) $p->id_batiment);
            if ($l->etat !== $p->nature || $l->disponible_le !== $dispo) {
                throw new RegleMetier('L’état réel de l’emplacement a changé hors planning : une clôture administrative par l’administrateur est nécessaire.');
            }
            Lieux::preparer('lieu', (int) $p->id_batiment, null, 'Fin : '.$p->libelle);
            Lieux::executer(fn () => DB::table('modInfra_emplacements')->where('id_batiment', $p->id_batiment)
                ->update(['etat' => 'disponible', 'disponible_le' => null, 'version' => DB::raw('version + 1')]));
            Lieux::reinitialiser();
            self::changer($id, $p, ['statut' => 'termine', 'termine_le' => now(), 'cloture_mode' => 'normale'], 'fin');
        });
    }

    /** RG-IFP-011 : clôture administrative (administrateur), sans remise en service automatique. */
    public static function clotureAdministrative(int $id, string $motif): void
    {
        if (mb_strlen(trim($motif)) < 10) {
            throw new RegleMetier('Expliquez la clôture administrative (10 caractères au moins).');
        }
        DB::transaction(function () use ($id, $motif) {
            $p = self::ligne($id);
            if ($p->statut !== 'en_cours') {
                throw new RegleMetier('Cette intervention n’est pas en cours.');
            }
            self::changer($id, $p, ['statut' => 'termine', 'termine_le' => now(), 'cloture_mode' => 'administrative', 'motif' => trim($motif)], 'cloture_administrative', trim($motif));
        });
    }

    public static function periodes(?int $lieu = null, bool $actives = true)
    {
        return DB::table('app_infra_planning as p')->join('batiments_engraissement as b', 'b.id', '=', 'p.id_batiment')->leftJoin('modInfra_emplacements as e', 'e.id_batiment', '=', 'p.id_batiment')
            ->when($lieu, fn ($q) => $q->where('p.id_batiment', $lieu))
            ->when($actives, fn ($q) => $q->whereIn('p.statut', ['prevu', 'en_cours']), fn ($q) => $q->whereIn('p.statut', ['termine', 'annule']))
            ->orderBy($actives ? 'p.date_debut' : 'p.termine_le', $actives ? 'asc' : 'desc')->limit(300)->get(['p.*', 'b.nom_batiment', 'e.code', 'e.etat', 'e.disponible_le']);
    }

    /** RG-IFP-012 : alertes de préparation. */
    public static function alertes(): array
    {
        $t = today()->toDateString();
        $out = [];
        foreach (self::periodes() as $p) {
            $finPlus = Carbon::parse($p->date_fin)->addDay()->toDateString();
            if ($p->statut === 'prevu' && $p->date_fin < $t) {
                $out[] = ['alerte', $p, 'Période échue sans être démarrée ni clôturée.'];
            } elseif ($p->statut === 'prevu' && $p->nature !== 'reservation' && $p->date_debut <= $t) {
                $out[] = ['attente', $p, 'Intervention à démarrer.'];
            } elseif ($p->statut === 'en_cours' && $t >= $finPlus) {
                $out[] = ['attente', $p, 'Intervention à terminer : remettre l’emplacement en service.'];
            }
            if ($p->statut === 'en_cours' && ($p->etat !== $p->nature || $p->disponible_le !== $finPlus)) {
                $out[] = ['alerte', $p, 'État réel de l’emplacement modifié hors planning.'];
            }
            if ($p->statut === 'prevu' && $p->nature !== 'reservation' && $p->date_debut <= today()->addDays(7)->toDateString()) {
                $occ = (int) DB::table('modInfra_positions')->where('id_batiment', $p->id_batiment)->sum('quantite');
                if ($occ > 0) {
                    $out[] = ['attente', $p, "$occ sujet(s) à évacuer avant l’intervention."];
                }
            }
            if ($p->statut === 'prevu' && $p->nature === 'reservation' && $p->date_debut <= today()->addDays(30)->toDateString()) {
                $l = Infrastructures::lieu((int) $p->id_batiment);
                if ((int) $l->occupation + (int) $p->quantite > (int) $l->capacite) {
                    $out[] = ['attente', $p, "Occupation actuelle ({$l->occupation}) + réservation ({$p->quantite}) au-delà de la capacité ({$l->capacite}) : prévoir des départs."];
                }
            }
        }

        return $out;
    }
}
