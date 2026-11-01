<?php

namespace App\Domaine;

use App\Exceptions\RegleMetier;
use App\Support\Format;
use App\Support\Journal;
use App\Support\Lieux;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/** Bandes de reproducteurs (REP). */
class Reproducteurs
{
    public const TYPES = ['entrée' => 'Entrée', 'retrait' => 'Retrait', 'mortalité' => 'Mortalité'];

    public const SEXES = ['mâle' => 'Mâles', 'femelle' => 'Femelles'];

    /** RG-REP-001 */
    public static function creer(array $d): int
    {
        return DB::transaction(function () use ($d) {
            $id = DB::table('bandes_parentales')->insertGetId([
                'nom_bande' => $d['nom_bande'],
                'espece' => $d['espece'],
                'nb_males' => (int) $d['nb_males'],
                'nb_femelles' => (int) $d['nb_femelles'],
                'date_mise_en_place' => $d['date_mise_en_place'] ?? null,
                'observations' => $d['observations'] ?? null,
                'reformee' => 0,
            ]);
            Journal::enregistrer('bande.creation', 'REP', 'bande', $id, apres: $d);

            return $id;
        });
    }

    /** Informations générales (nom, date, observations, réforme). Les effectifs ne changent que par mouvement. */
    public static function modifier(int $id, array $d, string $empreinte): void
    {
        DB::transaction(function () use ($id, $d, $empreinte) {
            $bande = self::verrouiller($id);
            \App\Support\Empreinte::verifier($bande, $empreinte);
            $maj = [
                'nom_bande' => $d['nom_bande'],
                'date_mise_en_place' => $d['date_mise_en_place'] ?? null,
                'observations' => $d['observations'] ?? null,
                'reformee' => ! empty($d['reformee']) ? 1 : 0,
            ];
            Lieux::preparer(Lieux::REPRO_F, $id, null, 'Modification de la bande');
            Lieux::executer(fn () => DB::table('bandes_parentales')->where('id', $id)->update($maj));
            Lieux::reinitialiser();
            Journal::enregistrer($maj['reformee'] && ! $bande->reformee ? 'bande.reforme' : 'bande.modification', 'REP', 'bande', $id, (array) $bande, $maj);
        });
    }

    /** RG-REP-002 à RG-REP-005 : mouvement par sexe, effectif ajusté dans la même transaction. */
    public static function mouvement(int $id, array $d): void
    {
        $quantite = (int) $d['quantite'];
        if ($quantite <= 0) {
            throw new RegleMetier('La quantité doit être un entier strictement positif.');
        }
        if (Carbon::parse($d['date_mouvement'])->isAfter(today())) {
            throw new RegleMetier('La date du mouvement ne peut pas être future.');
        }

        DB::transaction(function () use ($id, $d, $quantite) {
            $bande = self::verrouiller($id);
            if ($bande->reformee) {
                throw new RegleMetier('Bande réformée : elle ne peut plus recevoir de mouvement.');
            }
            $colonne = $d['sexe'] === 'mâle' ? 'nb_males' : 'nb_femelles';
            $famille = $d['sexe'] === 'mâle' ? Lieux::REPRO_M : Lieux::REPRO_F;
            $avant = (int) $bande->$colonne;
            $apres = $d['type_mouvement'] === 'entrée' ? $avant + $quantite : $avant - $quantite;
            if ($apres < 0) {
                throw new RegleMetier("Quantité supérieure à l’effectif actuel ($avant).");
            }

            DB::table('modRepro_mouvements')->insert([
                'id_bande' => $id,
                'date_mouvement' => $d['date_mouvement'],
                'type_mouvement' => $d['type_mouvement'],
                'sexe' => $d['sexe'],
                'quantite' => $quantite,
                'commentaire' => $d['commentaire'],
                'utilisateur' => Format::auteur(),
            ]);
            $origine = isset($d['origine']) && $d['origine'] !== '' ? (int) $d['origine'] : null;
            Lieux::preparer($famille, $id, $origine, $d['commentaire']);
            Lieux::executer(fn () => DB::table('bandes_parentales')->where('id', $id)->update([$colonne => $apres]));
            Lieux::reinitialiser();

            Journal::enregistrer('bande.mouvement', 'REP', 'bande', $id, [$colonne => $avant], [$colonne => $apres] + $d, $d['commentaire']);
        });
    }

    public static function verrouiller(int $id): object
    {
        return DB::table('bandes_parentales')->where('id', $id)->lockForUpdate()->first()
            ?? throw new RegleMetier('Bande introuvable.');
    }

    /** Bandes utilisables pour une saisie (non réformées). */
    public static function actives(): \Illuminate\Support\Collection
    {
        return DB::table('bandes_parentales')->where(fn ($q) => $q->where('reformee', 0)->orWhereNull('reformee'))->orderBy('nom_bande')->get();
    }
}
