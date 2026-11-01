<?php

namespace Tests\Feature\Lot2;

use Illuminate\Support\Facades\DB;
use Tests\TestCase;

abstract class VolaillesTestCase extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->chargerMatriceParDefaut();
        $this->actingAs($this->utilisateur('admin'));
    }

    protected function bande(int $femelles = 80, int $males = 10, string $espece = 'poulet', array $attrs = []): int
    {
        return DB::table('bandes_parentales')->insertGetId($attrs + ['nom_bande' => 'Bande '.uniqid(), 'espece' => $espece, 'nb_males' => $males, 'nb_femelles' => $femelles, 'reformee' => 0]);
    }

    protected function equipement(string $nom, int $capacite, string $type = 'incubateur'): int
    {
        return DB::table('incubateurs')->insertGetId(['nom' => $nom, 'capacite' => $capacite, 'type' => $type]);
    }

    protected function lotVolaille(int $effectif = 100, array $attrs = []): int
    {
        return DB::table('modLotVol_lots_volailles')->insertGetId($attrs + [
            'nom_lot' => 'Lot '.uniqid(), 'espece' => 'poulet', 'race' => 'Cobb 500', 'nombre_initial' => $effectif,
            'date_entree' => today()->subDays(20)->toDateString(), 'statut' => 'actif', 'effectif_en_cours' => $effectif,
        ]);
    }

    protected function ligne(string $table, int $id, string $cle = 'id'): object
    {
        return DB::table($table)->where($cle, $id)->first();
    }
}
