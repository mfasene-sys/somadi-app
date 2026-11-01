<?php

namespace Tests\Feature\Lot4;

use App\Domaine\Ovins\Animaux;
use App\Domaine\Ovins\Referentiels;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

abstract class OvinsTestCase extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->chargerMatriceParDefaut();
        $this->actingAs($this->utilisateur('admin'));
    }

    protected function site(string $nom = 'Site'): int
    {
        return Referentiels::enregistrerSite(null, ['code_site' => Str::upper(Str::random(6)), 'nom_site' => $nom.' '.Str::random(3), 'actif' => 1, 'capacite_ovins' => 100]);
    }

    protected function lot(int $site, string $type = 'reproductrices', int $effectif = 0): int
    {
        return Referentiels::creerLot(['nom_lot' => ucfirst($type).' '.Str::random(4), 'type_lot' => $type, 'id_site' => $site, 'date_entree' => today()->subYear()->toDateString(), 'effectif_initial' => $effectif]);
    }

    protected function animal(int $site, ?int $lot, string $sexe = 'F', array $d = [], bool $compter = true): int
    {
        return Animaux::creer($d + ['sexe' => $sexe, 'id_site' => $site, 'id_lot' => $lot, 'date_naissance' => today()->subYears(2)->toDateString(),
            'date_entree' => today()->subYear()->toDateString(), 'code_animal' => 'T'.Str::upper(Str::random(7))], $compter);
    }

    protected function a(int $id): object
    {
        return DB::table('OVINS_animaux')->where('id_animal', $id)->first();
    }

    protected function effectif(int $lot): int
    {
        return (int) DB::table('OVINS_lots')->where('id_lot', $lot)->value('effectif_encours');
    }
}
