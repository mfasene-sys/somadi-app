<?php

namespace App\Domaine\Organisation;

use App\Exceptions\RegleMetier;
use App\Support\Format;
use App\Support\Journal;
use App\Support\Montant;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/** Personnel, absences, congés, retenues et clôture annuelle (RH, RG-RH-001 à 014 ; EC20). */
class Rh
{
    public const TYPES = ['conge' => 'Congé', 'absence' => 'Absence', 'maladie' => 'Maladie', 'sans_solde' => 'Sans solde', 'autre' => 'Autre'];

    public const STATUTS = ['brouillon' => ['Brouillon', 'neutre'], 'soumise' => ['À valider', 'attente'], 'validee' => ['Validée', 'ok'], 'refusee' => ['Refusée', 'alerte'], 'annulee' => ['Annulée', 'neutre']];

    public const MOUVEMENTS = ['PRISE' => 'Jours pris', 'BONUS' => 'Bonus', 'PAIEMENT' => 'Paiement de jours non pris', 'RETENUE' => 'Retenue'];

    // ----- Employés (RG-RH-001)

    public static function enregistrerEmploye(?int $id, array $d): int
    {
        $v = [
            'matricule' => trim((string) ($d['matricule'] ?? '')) ?: null, 'nom' => trim((string) $d['nom']), 'prenom' => ($d['prenom'] ?? null) ?: null,
            'telephone' => ($d['telephone'] ?? null) ?: null, 'poste' => ($d['poste'] ?? null) ?: null, 'site_id' => ($d['site_id'] ?? null) ?: null,
            'date_entree' => ($d['date_entree'] ?? null) ?: null, 'salaire_mensuel' => Montant::versBase(Montant::saisie($d['salaire_mensuel'] ?? '0', 'Le salaire')),
            'actif' => empty($d['actif']) ? 0 : 1,
        ];
        if ($v['nom'] === '') {
            throw new RegleMetier('Le nom est obligatoire.');
        }

        return DB::transaction(function () use ($id, $v) {
            if ($v['matricule'] && DB::table('rh_employes')->where('matricule', $v['matricule'])->when($id, fn ($q) => $q->where('id', '<>', $id))->exists()) {
                throw new RegleMetier("Le matricule « {$v['matricule']} » est déjà utilisé.");
            }
            if ($id) {
                $avant = DB::table('rh_employes')->where('id', $id)->lockForUpdate()->first() ?? throw new RegleMetier('Employé introuvable.');
                DB::table('rh_employes')->where('id', $id)->update($v);
                Journal::enregistrer('employe.modification', 'RH', 'employe', $id, (array) $avant, $v);

                return $id;
            }
            $id = DB::table('rh_employes')->insertGetId($v);
            self::droits($id, (int) today()->year);
            Journal::enregistrer('employe.creation', 'RH', 'employe', $id, apres: $v);

            return $id;
        });
    }

    /** RG-RH-002 : droits par défaut créés à la demande (30 jours libres, 0 bonus, 2 imposés). */
    public static function droits(int $employe, int $annee, bool $creer = true): object
    {
        $d = DB::table('rh_conges_droits')->where('employe_id', $employe)->where('annee', $annee)->first();
        if (! $d && ! $creer) {
            return (object) ['id' => null, 'employe_id' => $employe, 'annee' => $annee, 'quota_libre' => 30, 'bonus' => 0, 'quota_impose' => 2, 'note_bonus' => null];
        }
        if (! $d) {
            DB::table('rh_conges_droits')->insertOrIgnore(['employe_id' => $employe, 'annee' => $annee, 'quota_libre' => 30, 'bonus' => 0, 'quota_impose' => 2]);
            $d = DB::table('rh_conges_droits')->where('employe_id', $employe)->where('annee', $annee)->first();
        }

        return $d;
    }

    public static function modifierDroits(int $employe, int $annee, array $d): void
    {
        DB::transaction(function () use ($employe, $annee, $d) {
            $avant = self::droits($employe, $annee);
            $v = ['quota_libre' => max(0, (int) $d['quota_libre']), 'bonus' => (int) $d['bonus'], 'quota_impose' => max(0, (int) $d['quota_impose']), 'note_bonus' => ($d['note_bonus'] ?? null) ?: null];
            DB::table('rh_conges_droits')->where('id', $avant->id)->update($v);
            if ($v['bonus'] !== (int) $avant->bonus) {
                DB::table('rh_conges_mouvements')->insert(['employe_id' => $employe, 'annee' => $annee, 'date_mvt' => today()->toDateString(), 'type_mvt' => 'BONUS',
                    'jours' => $v['bonus'] - (int) $avant->bonus, 'created_by' => mb_substr(Format::auteur(), 0, 80)]);
            }
            Journal::enregistrer('droits_conges.modification', 'RH', 'employe', $employe, (array) $avant, $v + ['annee' => $annee]);
        });
    }

    // ----- Jours imposés (RG-RH-006)

    public static function joursImposes(int $annee)
    {
        return DB::table('rh_jours_imposes')->where('annee', $annee)->orderBy('date_jour')->get();
    }

    public static function ajouterJourImpose(string $date, string $libelle): void
    {
        if (! in_array($libelle, ['Korité', 'Tabaski', 'Autre'], true)) {
            throw new RegleMetier('Libellé invalide.');
        }
        $annee = (int) substr($date, 0, 4);
        if (DB::table('rh_jours_imposes')->where('annee', $annee)->where('date_jour', $date)->exists()) {
            throw new RegleMetier('Ce jour est déjà enregistré.');
        }
        DB::table('rh_jours_imposes')->insert(['annee' => $annee, 'date_jour' => $date, 'libelle' => $libelle, 'actif' => 1]);
        Journal::enregistrer('jour_impose.ajout', 'RH', 'jour_impose', null, apres: compact('date', 'libelle'));
    }

    public static function basculerJourImpose(int $id): void
    {
        DB::table('rh_jours_imposes')->where('id', $id)->update(['actif' => DB::raw('1 - actif')]);
    }

    /** RG-RH-005 : jours imputés d'un congé = dates incluses, hors dimanches et jours imposés actifs. */
    public static function joursImputes(string $debut, string $fin): int
    {
        $imposes = DB::table('rh_jours_imposes')->where('actif', 1)->whereBetween('date_jour', [$debut, $fin])->pluck('date_jour')->all();
        $n = 0;
        for ($d = Carbon::parse($debut); $d->toDateString() <= $fin; $d->addDay()) {
            if (! $d->isSunday() && ! in_array($d->toDateString(), $imposes, true)) {
                $n++;
            }
        }

        return $n;
    }

    // ----- Absences (RG-RH-003, 004, 007, 010)

    public static function demander(array $d): int
    {
        if (! isset(self::TYPES[$d['type'] ?? ''])) {
            throw new RegleMetier('Type d’absence invalide.');
        }
        $deb = (string) $d['date_debut'];
        $fin = (string) $d['date_fin'];
        if ($fin < $deb) {
            throw new RegleMetier('La fin ne peut pas précéder le début.');
        }
        if (substr($deb, 0, 4) !== substr($fin, 0, 4)) {
            throw new RegleMetier('Une absence sur deux années se saisit en deux demandes (une par année).');
        }

        return DB::transaction(function () use ($d, $deb, $fin) {
            $e = DB::table('rh_employes')->where('id', (int) $d['employe_id'])->lockForUpdate()->first();
            if (! $e || ! $e->actif) {
                throw new RegleMetier('Employé introuvable ou inactif.');
            }
            $conflit = DB::table('rh_absences')->where('employe_id', $e->id)->whereIn('statut', ['soumise', 'validee'])
                ->where('date_debut', '<=', $fin)->where('date_fin', '>=', $deb)->first();
            if ($conflit) {
                throw new RegleMetier('Cette période chevauche une autre demande ('.Format::date($conflit->date_debut).' – '.Format::date($conflit->date_fin).').');
            }
            $v = ['employe_id' => $e->id, 'type' => $d['type'], 'date_debut' => $deb, 'date_fin' => $fin, 'motif' => ($d['motif'] ?? null) ?: null,
                'statut' => 'soumise', 'jours_imputes' => 0, 'cree_par' => mb_substr(Format::auteur(), 0, 80)];
            $id = DB::table('rh_absences')->insertGetId($v);
            Journal::enregistrer('absence.demande', 'RH', 'absence', $id, apres: $v);

            return $id;
        });
    }

    /** RG-RH-007, 010 : validation (avec mouvement PRISE pour un congé), refus ou annulation d'une demande soumise. */
    public static function traiter(int $id, string $decision, ?string $commentaire = null): void
    {
        if (! in_array($decision, ['validee', 'refusee', 'annulee'], true)) {
            throw new RegleMetier('Décision invalide.');
        }
        DB::transaction(function () use ($id, $decision, $commentaire) {
            $a = DB::table('rh_absences')->where('id', $id)->lockForUpdate()->first() ?? throw new RegleMetier('Demande introuvable.');
            if ($a->statut !== 'soumise') {
                throw new RegleMetier('Cette demande a déjà été traitée.');
            }
            $jours = 0;
            if ($decision === 'validee' && $a->type === 'conge') {
                $jours = self::joursImputes($a->date_debut, $a->date_fin);
                self::droits((int) $a->employe_id, (int) substr($a->date_debut, 0, 4));
                if ($jours > 0) {
                    DB::table('rh_conges_mouvements')->insert(['employe_id' => $a->employe_id, 'annee' => (int) substr($a->date_debut, 0, 4), 'date_mvt' => $a->date_debut,
                        'type_mvt' => 'PRISE', 'jours' => $jours, 'reference_table' => 'rh_absences', 'reference_id' => $a->id, 'created_by' => mb_substr(Format::auteur(), 0, 80)]);
                }
            }
            $v = ['statut' => $decision, 'jours_imputes' => $jours, 'valide_par' => mb_substr(Format::auteur(), 0, 80), 'validated_at' => now()]
                + ($commentaire ? ['motif' => mb_substr(trim(($a->motif ? $a->motif.' — ' : '').$commentaire), 0, 255)] : []);
            DB::table('rh_absences')->where('id', $id)->update($v);
            Journal::enregistrer('absence.'.$decision, 'RH', 'absence', $id, ['statut' => 'soumise'], $v, $commentaire);
        });
    }

    // ----- Soldes, retenues, clôture

    public static function solde(int $employe, int $annee): array
    {
        $d = self::droits($employe, $annee, false);
        $m = DB::table('rh_conges_mouvements')->where('employe_id', $employe)->where('annee', $annee)->groupBy('type_mvt')
            ->pluck(DB::raw('SUM(jours)'), 'type_mvt');
        $quota = (int) $d->quota_libre + (int) $d->bonus;
        $pris = (int) ($m['PRISE'] ?? 0);
        $retenus = (int) ($m['RETENUE'] ?? 0);

        return ['droits' => $d, 'quota' => $quota, 'pris' => $pris, 'retenus' => $retenus, 'payes' => (int) ($m['PAIEMENT'] ?? 0),
            'reste' => $quota - $pris, 'a_retenir' => max(0, $pris - $quota - $retenus)];
    }

    public static function taux(object $e): int
    {
        return intdiv(Montant::base($e->salaire_mensuel), 30);
    }

    /**
     * RG-RH-008, 009 : retenues des dépassements non encore traités ; mois d'application = mois suivant le traitement.
     *
     * @return list<array{employe:object, jours:int, montant:int}>
     */
    public static function retenues(int $annee, bool $appliquer): array
    {
        return DB::transaction(function () use ($annee, $appliquer) {
            $out = [];
            foreach (DB::table('rh_employes')->where('actif', 1)->orderBy('nom')->lockForUpdate()->get() as $e) {
                $s = self::solde((int) $e->id, $annee);
                if ($s['a_retenir'] <= 0) {
                    continue;
                }
                $taux = self::taux($e);
                $montant = $s['a_retenir'] * $taux;
                $out[] = ['employe' => $e, 'jours' => $s['a_retenir'], 'montant' => $montant];
                if ($appliquer) {
                    DB::table('rh_conges_mouvements')->insert(['employe_id' => $e->id, 'annee' => $annee, 'date_mvt' => today()->toDateString(), 'type_mvt' => 'RETENUE',
                        'jours' => $s['a_retenir'], 'taux_journalier' => Montant::versBase($taux), 'montant' => Montant::versBase($montant),
                        'mois_application' => today()->addMonthNoOverflow()->startOfMonth()->toDateString(), 'created_by' => mb_substr(Format::auteur(), 0, 80)]);
                    Journal::enregistrer('rh.retenue', 'RH', 'employe', $e->id, null, ['annee' => $annee, 'jours' => $s['a_retenir'], 'montant' => $montant]);
                }
            }

            return $out;
        });
    }

    /**
     * RG-RH-011, 012 : paiement unique par employé et par année des jours non pris (EC20).
     *
     * @return list<array{employe:object, jours:int, montant:int, deja:bool}>
     */
    public static function cloture(int $annee, bool $appliquer): array
    {
        if ($annee > (int) today()->year) {
            throw new RegleMetier('Année future.');
        }

        return DB::transaction(function () use ($annee, $appliquer) {
            $out = [];
            foreach (DB::table('rh_employes')->where('actif', 1)->orderBy('nom')->lockForUpdate()->get() as $e) {
                $s = self::solde((int) $e->id, $annee);
                $deja = DB::table('rh_conges_mouvements')->where('employe_id', $e->id)->where('annee', $annee)->where('type_mvt', 'PAIEMENT')->where(fn ($q) => $q->whereNull('reference_table')->orWhere('reference_table', '<>', 'correction'))->exists();
                if ($s['reste'] <= 0) {
                    continue;
                }
                $taux = self::taux($e);
                $out[] = ['employe' => $e, 'jours' => $s['reste'], 'montant' => $s['reste'] * $taux, 'deja' => $deja];
                if ($appliquer && ! $deja) {
                    DB::table('rh_conges_mouvements')->insert(['employe_id' => $e->id, 'annee' => $annee, 'date_mvt' => "$annee-12-31", 'type_mvt' => 'PAIEMENT',
                        'jours' => $s['reste'], 'taux_journalier' => Montant::versBase($taux), 'montant' => Montant::versBase($s['reste'] * $taux),
                        'mois_application' => "$annee-12-01", 'created_by' => mb_substr(Format::auteur(), 0, 80)]);
                    Journal::enregistrer('rh.cloture_paiement', 'RH', 'employe', $e->id, null, ['annee' => $annee, 'jours' => $s['reste']]);
                }
            }

            return $out;
        });
    }

    /** EC20 : écriture de correction (positive ou négative) avec motif obligatoire ; rien n'est effacé. */
    public static function corriger(int $employe, int $annee, string $type, int $jours, string $motif): void
    {
        if (! in_array($type, ['PRISE', 'PAIEMENT', 'RETENUE'], true) || $jours === 0) {
            throw new RegleMetier('Indiquez le type et un nombre de jours non nul (positif ou négatif).');
        }
        if (mb_strlen(trim($motif)) < 10) {
            throw new RegleMetier('Le motif de la correction est obligatoire (10 caractères au moins).');
        }
        DB::transaction(function () use ($employe, $annee, $type, $jours, $motif) {
            $e = DB::table('rh_employes')->where('id', $employe)->lockForUpdate()->first() ?? throw new RegleMetier('Employé introuvable.');
            $taux = self::taux($e);
            DB::table('rh_conges_mouvements')->insert(['employe_id' => $employe, 'annee' => $annee, 'date_mvt' => today()->toDateString(), 'type_mvt' => $type, 'jours' => $jours,
                'taux_journalier' => $type === 'PRISE' ? null : Montant::versBase($taux), 'montant' => $type === 'PRISE' ? null : Montant::versBase($jours * $taux),
                'reference_table' => 'correction', 'mois_application' => $type === 'PRISE' ? null : today()->addMonthNoOverflow()->startOfMonth()->toDateString(),
                'created_by' => mb_substr(Format::auteur(), 0, 80)]);
            Journal::enregistrer('rh.correction', 'RH', 'employe', $employe, null, compact('annee', 'type', 'jours'), trim($motif));
        });
    }
}
