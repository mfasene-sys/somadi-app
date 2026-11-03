<?php

use App\Http\Controllers\AccueilController;
use App\Http\Controllers\Commercial\CommandeController;
use App\Http\Controllers\Commercial\ExerciceController;
use App\Http\Controllers\Commercial\RapprochementController;
use App\Http\Controllers\Commercial\ReservationController;
use App\Http\Controllers\Commercial\TresorerieController;
use App\Http\Controllers\Commercial\VenteController;
use App\Http\Controllers\Auth\CodeConnexionController;
use App\Http\Controllers\Auth\ConnexionController;
use App\Http\Controllers\Auth\EmpreinteController;
use App\Http\Controllers\DroitsController;
use App\Http\Controllers\JournalController;
use App\Http\Controllers\ModuleAVenirController;
use App\Http\Controllers\ProfilController;
use App\Http\Controllers\UtilisateurController;
use App\Http\Controllers\Organisation\ActiviteController;
use App\Http\Controllers\Organisation\LieuController;
use App\Http\Controllers\Organisation\MarketingController;
use App\Http\Controllers\Organisation\RhController;
use App\Http\Controllers\Ovins\AnimalController;
use App\Http\Controllers\Ovins\InventaireController;
use App\Http\Controllers\Ovins\MouvementController;
use App\Http\Controllers\Ovins\ReferentielController;
use App\Http\Controllers\Ovins\ReproductionController;
use App\Http\Controllers\Ovins\SoinController as OvinSoinController;
use App\Http\Controllers\Volailles\AlimentationController;
use App\Http\Controllers\Volailles\EquipementController;
use App\Http\Controllers\Volailles\IncubationController;
use App\Http\Controllers\Volailles\LotController;
use App\Http\Controllers\Volailles\OeufsController;
use App\Http\Controllers\Volailles\PonteController;
use App\Http\Controllers\Volailles\RentabiliteController;
use App\Http\Controllers\Volailles\ReproducteurController;
use App\Http\Controllers\Volailles\SoinController;
use Illuminate\Support\Facades\Route;

// Page affichée par l'application installée quand le réseau manque et que la page demandée n'est pas en cache.
Route::view('/hors-ligne', 'hors-ligne')->name('hors_ligne');

Route::middleware('guest')->group(function () {
    Route::get('/connexion', [ConnexionController::class, 'create'])->name('login');
    Route::post('/connexion', [ConnexionController::class, 'store'])->middleware('throttle:connexion')->name('login.store');
    // Seconde étape après le mot de passe : code PIN reçu par e-mail.
    Route::get('/connexion/code', [CodeConnexionController::class, 'create'])->name('login.code');
    Route::post('/connexion/code', [CodeConnexionController::class, 'store'])->middleware('throttle:code_connexion')->name('login.code.store');
    Route::post('/connexion/code/renvoyer', [CodeConnexionController::class, 'renvoyer'])->middleware('throttle:code_connexion')->name('login.code.renvoyer');
    Route::post('/connexion/code/annuler', [CodeConnexionController::class, 'annuler'])->name('login.code.annuler');
    // Connexion par empreinte (téléphone).
    Route::post('/connexion/empreinte/options', [EmpreinteController::class, 'optionsConnexion'])->middleware('throttle:empreinte')->name('login.empreinte.options');
    Route::post('/connexion/empreinte', [EmpreinteController::class, 'connexion'])->middleware('throttle:empreinte')->name('login.empreinte');
});

Route::middleware(['auth', 'compte.actif'])->group(function () {
    Route::post('/deconnexion', [ConnexionController::class, 'destroy'])->name('logout');

    Route::get('/', AccueilController::class)->middleware('can:PIL.voir')->name('accueil');
    Route::get('/modules/{code}', ModuleAVenirController::class)->where('code', '[a-zA-Z]{2,4}')->name('module.a_venir');

    Route::get('/mon-profil', [ProfilController::class, 'edit'])->name('profil.edit');
    Route::put('/mon-profil', [ProfilController::class, 'update'])->name('profil.update');
    Route::put('/mon-profil/mot-de-passe', [ProfilController::class, 'motDePasse'])->name('profil.mot_de_passe');
    Route::post('/mon-profil/empreintes/options', [EmpreinteController::class, 'optionsActivation'])->middleware('throttle:empreinte')->name('profil.empreintes.options');
    Route::post('/mon-profil/empreintes', [EmpreinteController::class, 'activer'])->middleware('throttle:empreinte')->name('profil.empreintes.activer');
    Route::delete('/mon-profil/empreintes/{id}', [EmpreinteController::class, 'retirer'])->whereNumber('id')->name('profil.empreintes.retirer');

    // ---------- Lot 2 : volailles ----------
    Route::prefix('volailles')->group(function () {
        Route::get('/reproducteurs', [ReproducteurController::class, 'index'])->middleware('can:REP.voir')->name('reproducteurs.index');
        Route::get('/reproducteurs/nouvelle', [ReproducteurController::class, 'create'])->middleware('can:REP.gerer')->name('reproducteurs.create');
        Route::post('/reproducteurs', [ReproducteurController::class, 'store'])->middleware('can:REP.gerer')->name('reproducteurs.store');
        Route::get('/reproducteurs/{id}', [ReproducteurController::class, 'show'])->whereNumber('id')->middleware('can:REP.voir')->name('reproducteurs.show');
        Route::get('/reproducteurs/{id}/modifier', [ReproducteurController::class, 'edit'])->whereNumber('id')->middleware('can:REP.gerer')->name('reproducteurs.edit');
        Route::put('/reproducteurs/{id}', [ReproducteurController::class, 'update'])->whereNumber('id')->middleware('can:REP.gerer')->name('reproducteurs.update');
        Route::post('/reproducteurs/{id}/mouvements', [ReproducteurController::class, 'mouvement'])->whereNumber('id')->middleware('can:REP.gerer')->name('reproducteurs.mouvement');

        Route::get('/ponte', [PonteController::class, 'index'])->middleware('can:PON.voir')->name('ponte.index');
        Route::get('/ponte/courbe', [PonteController::class, 'courbe'])->middleware('can:PON.voir')->name('ponte.courbe');
        Route::post('/ponte', [PonteController::class, 'store'])->middleware('can:PON.gerer')->name('ponte.store');
        Route::post('/ponte/{id}/validation', [PonteController::class, 'valider'])->whereNumber('id')->middleware('can:PON.valider')->name('ponte.valider');

        Route::get('/oeufs', [OeufsController::class, 'index'])->middleware('can:OEU.voir')->name('oeufs.index');
        Route::post('/oeufs/{stock}/affectation', [OeufsController::class, 'affecter'])->whereNumber('stock')->middleware('can:OEU.gerer')->name('oeufs.affecter');

        Route::get('/incubation', [IncubationController::class, 'index'])->middleware('can:INC.voir')->name('incubation.index');
        Route::get('/incubation/chargement', [IncubationController::class, 'create'])->middleware('can:INC.gerer')->name('incubation.create');
        Route::post('/incubation', [IncubationController::class, 'store'])->middleware('can:INC.gerer')->name('incubation.store');
        Route::get('/incubation/{id}', [IncubationController::class, 'show'])->whereNumber('id')->middleware('can:INC.voir')->name('incubation.show');
        Route::middleware('can:INC.gerer')->whereNumber('id')->group(function () {
            Route::post('/incubation/{id}/ajustement', [IncubationController::class, 'ajuster'])->name('incubation.ajuster');
            Route::post('/incubation/{id}/mirage', [IncubationController::class, 'mirer'])->name('incubation.mirer');
            Route::post('/incubation/{id}/transfert', [IncubationController::class, 'transferer'])->name('incubation.transferer');
            Route::post('/incubation/{id}/eclosion', [IncubationController::class, 'eclore'])->name('incubation.eclore');
        });
        Route::get('/equipements', [EquipementController::class, 'index'])->middleware('can:INC.voir')->name('equipements.index');
        Route::post('/equipements', [EquipementController::class, 'store'])->middleware('can:INC.gerer')->name('equipements.store');
        Route::put('/equipements/{id}', [EquipementController::class, 'update'])->whereNumber('id')->middleware('can:INC.gerer')->name('equipements.update');

        Route::get('/lots', [LotController::class, 'index'])->middleware('can:VOL.voir')->name('lots.index');
        Route::get('/lots/nouveau', [LotController::class, 'create'])->middleware('can:VOL.gerer')->name('lots.create');
        Route::post('/lots', [LotController::class, 'store'])->middleware('can:VOL.gerer')->name('lots.store');
        Route::whereNumber('id')->group(function () {
            Route::get('/lots/{id}', [LotController::class, 'show'])->middleware('can:VOL.voir')->name('lots.show');
            Route::get('/lots/{id}/modifier', [LotController::class, 'edit'])->middleware('can:VOL.gerer')->name('lots.edit');
            Route::put('/lots/{id}', [LotController::class, 'update'])->middleware('can:VOL.gerer')->name('lots.update');
            Route::post('/lots/{id}/mouvements', [LotController::class, 'mouvement'])->middleware('can:VOL.gerer')->name('lots.mouvement');
            Route::post('/lots/{id}/statut', [LotController::class, 'statut'])->middleware('can:VOL.gerer')->name('lots.statut');
            Route::post('/lots/{id}/pesees', [LotController::class, 'pesee'])->middleware('can:POI.gerer')->name('lots.pesee');
            Route::post('/lots/{id}/programme-soins', [LotController::class, 'programme'])->middleware('can:SAV.gerer')->name('lots.programme');
            Route::get('/rentabilite/{id}', [RentabiliteController::class, 'show'])->middleware('can:POI.voir')->name('rentabilite.show');
            Route::post('/rentabilite/{id}/exclusion', [RentabiliteController::class, 'exclusion'])->middleware('can:POI.gerer')->name('rentabilite.exclusion');
        });
        Route::get('/rentabilite', [RentabiliteController::class, 'index'])->middleware('can:POI.voir')->name('rentabilite.index');
        Route::get('/references-couts', [RentabiliteController::class, 'references'])->middleware('can:POI.voir')->name('references.index');
        Route::post('/references-couts', [RentabiliteController::class, 'enregistrerReference'])->middleware('can:POI.gerer')->name('references.store');

        Route::get('/alimentation', [AlimentationController::class, 'index'])->middleware('can:ALI.voir')->name('alimentation.index');
        Route::post('/alimentation/generation', [AlimentationController::class, 'generer'])->middleware('can:ALI.distribuer')->name('alimentation.generer');
        Route::post('/alimentation/distributions', [AlimentationController::class, 'distribuer'])->middleware('can:ALI.distribuer')->name('alimentation.distribuer');
        Route::post('/alimentation/distributions/{id}/correction', [AlimentationController::class, 'corriger'])->whereNumber('id')->middleware('can:ALI.gerer')->name('alimentation.corriger');
        Route::get('/alimentation/stocks', [AlimentationController::class, 'stocks'])->middleware('can:ALI.voir')->name('alimentation.stocks');
        Route::post('/alimentation/stocks', [AlimentationController::class, 'entreeStock'])->middleware('can:ALI.gerer')->name('alimentation.entree');
        Route::post('/alimentation/aliments', [AlimentationController::class, 'aliment'])->middleware('can:ALI.gerer')->name('alimentation.aliment');
        Route::get('/alimentation/plans', [AlimentationController::class, 'plans'])->middleware('can:ALI.voir')->name('alimentation.plans');
        Route::post('/alimentation/plans', [AlimentationController::class, 'creerPlan'])->middleware('can:ALI.gerer')->name('alimentation.plans.store');
        Route::get('/alimentation/plans/{id}', [AlimentationController::class, 'plan'])->whereNumber('id')->middleware('can:ALI.voir')->name('alimentation.plan');
        Route::post('/alimentation/plans/{id}/lignes', [AlimentationController::class, 'ajouterLigne'])->whereNumber('id')->middleware('can:ALI.gerer')->name('alimentation.ligne');
        Route::delete('/alimentation/plans/{id}/lignes/{ligne}', [AlimentationController::class, 'supprimerLigne'])->whereNumber(['id', 'ligne'])->middleware('can:ALI.gerer')->name('alimentation.ligne.supprimer');
        Route::get('/alimentation/consommation', [AlimentationController::class, 'consommation'])->middleware('can:ALI.voir')->name('alimentation.consommation');

        Route::get('/soins', [SoinController::class, 'index'])->middleware('can:SAV.voir')->name('soins.index');
        Route::post('/soins', [SoinController::class, 'store'])->middleware('can:SAV.gerer')->name('soins.store');
        Route::post('/soins/{id}/etat', [SoinController::class, 'etat'])->whereNumber('id')->middleware('can:SAV.voir')->name('soins.etat');
        Route::get('/soins/catalogue', [SoinController::class, 'catalogue'])->middleware('can:SAV.voir')->name('soins.catalogue');
        Route::post('/soins/catalogue', [SoinController::class, 'enregistrerCatalogue'])->middleware('can:SAV.gerer')->name('soins.catalogue.store');
        Route::put('/soins/catalogue/{id}', [SoinController::class, 'enregistrerCatalogue'])->whereNumber('id')->middleware('can:SAV.gerer')->name('soins.catalogue.update');
        Route::get('/soins/programme', [SoinController::class, 'programme'])->middleware('can:SAV.voir')->name('soins.programme');
        Route::post('/soins/programme', [SoinController::class, 'ajouterProgramme'])->middleware('can:SAV.gerer')->name('soins.programme.store');
        Route::delete('/soins/programme/{id}', [SoinController::class, 'supprimerProgramme'])->whereNumber('id')->middleware('can:SAV.gerer')->name('soins.programme.supprimer');
    });

    // ---------- Lot 3 : commercial et finance ----------
    Route::prefix('commercial')->group(function () {
        Route::get('/ventes', [VenteController::class, 'index'])->middleware('can:VEN.voir')->name('ventes.index');
        Route::middleware('can:VEN.gerer')->group(function () {
            Route::get('/ventes/nouvelle', [VenteController::class, 'create'])->name('ventes.create');
            Route::post('/ventes', [VenteController::class, 'store'])->name('ventes.store');
            Route::get('/ventes/oeufs', [VenteController::class, 'oeufs'])->name('ventes.oeufs');
            Route::post('/ventes/oeufs', [VenteController::class, 'vendreOeufs'])->name('ventes.oeufs.store');
            Route::get('/ventes/a-completer', [VenteController::class, 'aCompleter'])->name('ventes.a_completer');
            Route::post('/ventes/a-completer/{id}', [VenteController::class, 'finaliser'])->whereNumber('id')->name('ventes.finaliser');
        });
        Route::get('/ventes/{id}', [VenteController::class, 'show'])->whereNumber('id')->middleware('can:VEN.voir')->name('ventes.show');
        Route::post('/ventes/{id}/encaissements', [VenteController::class, 'encaisser'])->whereNumber('id')->middleware('can:CPT.encaisser')->name('ventes.encaisser');

        Route::get('/tresorerie', [TresorerieController::class, 'index'])->middleware('can:CPT.voir')->name('tresorerie.index');
        Route::post('/tresorerie/operations', [TresorerieController::class, 'store'])->middleware('can:CPT.gerer')->name('tresorerie.store');
        Route::post('/tresorerie/operations/{id}/invalidation', [TresorerieController::class, 'invalider'])->whereNumber('id')->middleware('can:CPT.gerer')->name('tresorerie.invalider');
        Route::get('/tresorerie/recapitulatif', [TresorerieController::class, 'recapitulatif'])->middleware('can:CPT.voir')->name('tresorerie.recapitulatif');
        Route::get('/tresorerie/a-encaisser', [TresorerieController::class, 'aEncaisser'])->middleware('can:CPT.voir')->name('tresorerie.a_encaisser');

        Route::get('/exercices', [ExerciceController::class, 'index'])->middleware('can:EXE.voir')->name('exercices.index');
        Route::whereNumber('annee')->group(function () {
            Route::post('/exercices/{annee}/apercu', [ExerciceController::class, 'apercu'])->middleware('can:EXE.cloturer')->name('exercices.apercu');
            Route::get('/exercices/{annee}/cloture', [ExerciceController::class, 'cloture'])->middleware('can:EXE.cloturer')->name('exercices.cloture');
            Route::post('/exercices/{annee}/cloture', [ExerciceController::class, 'cloturer'])->middleware('can:EXE.cloturer')->name('exercices.cloturer');
            Route::get('/exercices/{annee}/archive', [ExerciceController::class, 'archive'])->middleware('can:EXE.voir')->name('exercices.archive');
            Route::get('/exercices/{annee}/archive.pdf', [ExerciceController::class, 'pdf'])->middleware('can:EXE.voir')->name('exercices.pdf');
        });

        Route::get('/rapprochements', [RapprochementController::class, 'index'])->middleware('can:RAP.voir')->name('rapprochements.index');
        Route::get('/rapprochements/ventes/{id}', [RapprochementController::class, 'show'])->whereNumber('id')->middleware('can:RAP.voir')->name('rapprochements.show');
        Route::post('/rapprochements/ventes/{id}/apercu', [RapprochementController::class, 'apercu'])->whereNumber('id')->middleware('can:RAP.gerer')->name('rapprochements.preparer');
        Route::get('/rapprochements/apercu/{jeton}', [RapprochementController::class, 'voirApercu'])->where('jeton', '[a-f0-9]{64}')->middleware('can:RAP.gerer')->name('rapprochements.apercu');
        Route::post('/rapprochements/apercu/{jeton}', [RapprochementController::class, 'confirmer'])->where('jeton', '[a-f0-9]{64}')->middleware('can:RAP.gerer')->name('rapprochements.confirmer');

        Route::get('/reservations', [ReservationController::class, 'index'])->middleware('can:RES.voir')->name('reservations.index');
        Route::get('/reservations/nouvelle', [ReservationController::class, 'create'])->middleware('can:RES.gerer')->name('reservations.create');
        Route::post('/reservations', [ReservationController::class, 'store'])->middleware('can:RES.gerer')->name('reservations.store');
        Route::get('/reservations/{id}', [ReservationController::class, 'show'])->whereNumber('id')->middleware('can:RES.voir')->name('reservations.show');
        Route::post('/reservations/{id}/annulation', [ReservationController::class, 'annuler'])->whereNumber('id')->middleware('can:RES.gerer')->name('reservations.annuler');
        Route::post('/reservations/{id}/livraison', [ReservationController::class, 'livrer'])->whereNumber('id')->middleware('can:RES.gerer')->name('reservations.livrer');
        Route::post('/reservations/traitement/{incubation}', [ReservationController::class, 'traiter'])->whereNumber('incubation')->middleware('can:RES.gerer')->name('reservations.traiter');

        Route::get('/commandes', [CommandeController::class, 'index'])->middleware('can:CMD.voir')->name('commandes.index');
    });

    // Lot 4 — ovins
    Route::prefix('ovins')->name('ovins.')->group(function () {
        Route::whereNumber(['id', 'media', 'plan', 'sortie', 'ligne'])->group(function () {
            Route::get('/animaux', [AnimalController::class, 'index'])->middleware('can:OVA.voir')->name('animaux.index');
            Route::get('/animaux/nouveau', [AnimalController::class, 'create'])->middleware('can:OVA.gerer')->name('animaux.create');
            Route::post('/animaux', [AnimalController::class, 'store'])->middleware('can:OVA.gerer')->name('animaux.store');
            Route::get('/animaux/{id}', [AnimalController::class, 'show'])->middleware('can:OVA.voir')->name('animaux.show');
            Route::get('/animaux/{id}/modifier', [AnimalController::class, 'edit'])->middleware('can:OVA.gerer')->name('animaux.edit');
            Route::put('/animaux/{id}', [AnimalController::class, 'update'])->middleware('can:OVA.gerer')->name('animaux.update');
            Route::get('/animaux/{id}/arbre', [AnimalController::class, 'arbre'])->middleware('can:OVA.voir')->name('animaux.arbre');
            Route::post('/animaux/{id}/medias', [AnimalController::class, 'ajouterMedias'])->middleware('can:OVA.gerer')->name('animaux.medias');
            Route::post('/animaux/{id}/medias/{media}/suppression', [AnimalController::class, 'supprimerMedia'])->middleware('can:OVA.gerer')->name('animaux.medias.supprimer');
            Route::get('/medias/{media}', [AnimalController::class, 'media'])->middleware('can:OVA.voir')->name('medias.show');
            Route::post('/animaux/{id}/transfert', [MouvementController::class, 'transferer'])->middleware('can:OVM.gerer')->name('animaux.transfert');
            Route::post('/animaux/{id}/sortie', [MouvementController::class, 'sortir'])->middleware('can:OVM.gerer')->name('animaux.sortie');

            Route::get('/lots', [ReferentielController::class, 'lots'])->middleware('can:OVA.voir')->name('lots.index');
            Route::get('/lots/nouveau', [ReferentielController::class, 'creerLot'])->middleware('can:OVA.gerer')->name('lots.create');
            Route::post('/lots', [ReferentielController::class, 'storeLot'])->middleware('can:OVA.gerer')->name('lots.store');
            Route::get('/lots/{id}', [ReferentielController::class, 'lot'])->middleware('can:OVA.voir')->name('lots.show');
            Route::get('/lots/{id}/modifier', [ReferentielController::class, 'editLot'])->middleware('can:OVA.gerer')->name('lots.edit');
            Route::put('/lots/{id}', [ReferentielController::class, 'updateLot'])->middleware('can:OVA.gerer')->name('lots.update');
            Route::post('/lots/{id}/ajustement', [ReferentielController::class, 'ajuster'])->middleware('can:OVA.gerer')->name('lots.ajuster');
            Route::get('/lots/{id}/calendrier', [ReferentielController::class, 'calendrier'])->middleware('can:OVR.voir')->name('lots.calendrier');
            Route::post('/lots/{id}/calendrier', [ReferentielController::class, 'genererCalendrier'])->middleware('can:OVR.gerer')->name('lots.calendrier.generer');
            Route::post('/fenetres/{plan}/actions', [ReferentielController::class, 'ajouterAction'])->middleware('can:OVR.gerer')->name('fenetres.actions');
            Route::get('/sites', [ReferentielController::class, 'sites'])->middleware('can:OVA.voir')->name('sites');
            Route::post('/sites/{id?}', [ReferentielController::class, 'enregistrerSite'])->middleware('can:OVA.gerer')->name('sites.enregistrer');
            Route::get('/proprietaires', [ReferentielController::class, 'proprietaires'])->middleware('can:OVA.voir')->name('proprietaires');
            Route::post('/proprietaires/{id?}', [ReferentielController::class, 'enregistrerProprietaire'])->middleware('can:OVA.gerer')->name('proprietaires.enregistrer');

            Route::get('/reproduction', [ReproductionController::class, 'index'])->middleware('can:OVR.voir')->name('reproduction.index');
            Route::get('/reproduction/femelles/{id}', [ReproductionController::class, 'femelle'])->middleware('can:OVR.voir')->name('reproduction.femelle');
            Route::post('/reproduction/{id?}', [ReproductionController::class, 'enregistrer'])->middleware('can:OVR.gerer')->name('reproduction.enregistrer');

            Route::get('/soins', [OvinSoinController::class, 'index'])->middleware('can:OVS.voir')->name('soins.index');
            Route::post('/soins', [OvinSoinController::class, 'store'])->middleware('can:OVS.gerer')->name('soins.store');
            Route::post('/soins/application', [OvinSoinController::class, 'appliquer'])->middleware('can:OVS.gerer')->name('soins.appliquer');
            Route::post('/soins/{id}/realise', [OvinSoinController::class, 'realise'])->middleware('can:OVS.gerer')->name('soins.realise');
            Route::post('/soins/{id}/annulation', [OvinSoinController::class, 'annuler'])->middleware('can:OVS.gerer')->name('soins.annuler');
            Route::post('/soins/{id}/retour', [OvinSoinController::class, 'retour'])->middleware('can:OVS.gerer')->name('soins.retour');
            Route::get('/soins/catalogue', [OvinSoinController::class, 'catalogue'])->middleware('can:OVS.voir')->name('soins.catalogue');
            Route::post('/soins/types/{id?}', [OvinSoinController::class, 'enregistrerType'])->middleware('can:OVS.gerer')->name('soins.types');
            Route::get('/soins/modeles/nouveau', [OvinSoinController::class, 'modele'])->middleware('can:OVS.gerer')->name('soins.modele.create');
            Route::get('/soins/modeles/{id}', [OvinSoinController::class, 'modele'])->middleware('can:OVS.voir')->name('soins.modele');
            Route::post('/soins/modeles/{id?}', [OvinSoinController::class, 'enregistrerModele'])->middleware('can:OVS.gerer')->name('soins.modele.enregistrer');

            Route::get('/mouvements', [MouvementController::class, 'index'])->middleware('can:OVM.voir')->name('mouvements.index');
            Route::middleware('can:ACC.droits')->group(function () {
                // RG-OVM-010 : rapprochement historique réservé à l'administrateur.
                Route::get('/rapprochements', [MouvementController::class, 'rapprochements'])->name('rapprochements.index');
                Route::get('/rapprochements/{sortie}', [MouvementController::class, 'rapprochement'])->name('rapprochements.show');
                Route::post('/rapprochements/{sortie}/apercu', [MouvementController::class, 'apercu'])->name('rapprochements.apercu');
                Route::post('/rapprochements/{sortie}/confirmation', [MouvementController::class, 'confirmer'])->name('rapprochements.confirmer');
            });

            Route::get('/inventaires', [InventaireController::class, 'index'])->middleware('can:OVI.voir')->name('inventaires.index');
            Route::get('/inventaires/nouveau', [InventaireController::class, 'create'])->middleware('can:OVI.gerer')->name('inventaires.create');
            Route::post('/inventaires', [InventaireController::class, 'store'])->middleware('can:OVI.gerer')->name('inventaires.store');
            Route::get('/inventaires/{id}', [InventaireController::class, 'show'])->middleware('can:OVI.voir')->name('inventaires.show');
            Route::get('/inventaires/{id}/feuille.pdf', [InventaireController::class, 'feuille'])->middleware('can:OVI.voir')->name('inventaires.feuille');
            Route::post('/inventaires/{id}/lignes/{ligne}', [InventaireController::class, 'pointer'])->middleware('can:OVI.gerer')->name('inventaires.pointer');
            Route::post('/inventaires/{id}/groupe', [InventaireController::class, 'groupe'])->middleware('can:OVI.gerer')->name('inventaires.groupe');
            Route::post('/inventaires/{id}/ajout', [InventaireController::class, 'ajouter'])->middleware('can:OVI.gerer')->name('inventaires.ajouter');
            Route::post('/inventaires/{id}/cloture', [InventaireController::class, 'cloturer'])->middleware('can:OVI.cloturer')->name('inventaires.cloturer');
        });
    });

    // Lot 5 — organisation, RH et marketing
    Route::whereNumber(['id', 'tache'])->group(function () {
        Route::prefix('organisation')->name('organisation.')->group(function () {
            Route::get('/lieux', [LieuController::class, 'index'])->middleware('can:INF.voir')->name('lieux.index');
            Route::post('/lieux', [LieuController::class, 'store'])->middleware('can:INF.gerer')->name('lieux.store');
            Route::get('/lieux/{id}', [LieuController::class, 'show'])->middleware('can:INF.voir')->name('lieux.show');
            Route::put('/lieux/{id}', [LieuController::class, 'update'])->middleware('can:INF.gerer')->name('lieux.update');
            Route::get('/repartition', [LieuController::class, 'repartition'])->middleware('can:INF.voir')->name('repartition');
            Route::post('/repartition', [LieuController::class, 'deplacer'])->middleware('can:INF.gerer')->name('repartition.deplacer');
            Route::get('/lieux/journal', [LieuController::class, 'journal'])->middleware('can:INF.voir')->name('lieux.journal');
            Route::get('/planning', [LieuController::class, 'planning'])->middleware('can:IFP.voir')->name('planning.index');
            Route::post('/planning', [LieuController::class, 'planifier'])->middleware('can:IFP.gerer')->name('planning.store');
            Route::put('/planning/{id}', [LieuController::class, 'modifierPeriode'])->middleware('can:IFP.gerer')->name('planning.update');
            Route::post('/planning/{id}/{action}', [LieuController::class, 'transition'])->where('action', 'annuler|cloturer|demarrer|terminer|administrative')->middleware('can:IFP.gerer')->name('planning.transition');
        });

        Route::prefix('activites')->name('activites.')->group(function () {
            Route::get('/', [ActiviteController::class, 'index'])->middleware('can:ACT.voir')->name('index');
            Route::get('/taches', [ActiviteController::class, 'liste'])->middleware('can:ACT.voir')->name('taches.index');
            Route::get('/taches/nouvelle', [ActiviteController::class, 'create'])->middleware('can:ACT.gerer')->name('taches.create');
            Route::post('/taches', [ActiviteController::class, 'store'])->middleware('can:ACT.gerer')->name('taches.store');
            Route::get('/taches/{id}', [ActiviteController::class, 'show'])->middleware('can:ACT.voir')->name('taches.show');
            Route::get('/taches/{id}/modifier', [ActiviteController::class, 'edit'])->middleware('can:ACT.gerer')->name('taches.edit');
            Route::put('/taches/{id}', [ActiviteController::class, 'update'])->middleware('can:ACT.gerer')->name('taches.update');
            Route::post('/taches/{id}/{action}', [ActiviteController::class, 'agir'])->where('action', 'demarrer|terminer|annuler|reporter|commenter')->middleware('can:ACT.voir')->name('taches.agir');
            Route::get('/planning', [ActiviteController::class, 'planning'])->middleware('can:ACT.voir')->name('planning');
            Route::post('/occupations', [ActiviteController::class, 'ajouterOccupation'])->middleware('can:ACT.gerer')->name('occupations.store');
            Route::get('/routines', [ActiviteController::class, 'routines'])->middleware('can:ACT.voir')->name('routines.index');
            Route::post('/routines/{id?}', [ActiviteController::class, 'enregistrerRoutine'])->middleware('can:ACT.gerer')->name('routines.enregistrer');
            Route::get('/routines/{id}', [ActiviteController::class, 'routine'])->middleware('can:ACT.voir')->name('routines.show');
            Route::post('/routines/{id}/taches/{tache?}', [ActiviteController::class, 'enregistrerTacheRoutine'])->middleware('can:ACT.gerer')->name('routines.taches');
            Route::post('/routines/{id}/generation', [ActiviteController::class, 'generer'])->middleware('can:ACT.gerer')->name('routines.generer');
            Route::get('/equipes', [ActiviteController::class, 'equipes'])->middleware('can:ACT.voir')->name('equipes');
            Route::post('/equipes/{id?}', [ActiviteController::class, 'enregistrerEquipe'])->middleware('can:ACT.gerer')->name('equipes.enregistrer');
            Route::post('/categories/{id?}', [ActiviteController::class, 'enregistrerCategorie'])->middleware('can:ACT.gerer')->name('categories.enregistrer');
            Route::get('/projets', [ActiviteController::class, 'projets'])->middleware('can:ACT.voir')->name('projets');
            Route::post('/projets/{id?}', [ActiviteController::class, 'enregistrerProjet'])->middleware('can:ACT.gerer')->name('projets.enregistrer');
        });

        Route::prefix('rh')->name('rh.')->group(function () {
            Route::get('/employes', [RhController::class, 'employes'])->middleware('can:RH.voir')->name('employes.index');
            Route::get('/employes/nouveau', [RhController::class, 'create'])->middleware('can:RH.gerer')->name('employes.create');
            Route::post('/employes', [RhController::class, 'store'])->middleware('can:RH.gerer')->name('employes.store');
            Route::get('/employes/{id}', [RhController::class, 'show'])->middleware('can:RH.voir')->name('employes.show');
            Route::get('/employes/{id}/modifier', [RhController::class, 'edit'])->middleware('can:RH.gerer')->name('employes.edit');
            Route::put('/employes/{id}', [RhController::class, 'update'])->middleware('can:RH.gerer')->name('employes.update');
            Route::post('/employes/{id}/droits', [RhController::class, 'droits'])->middleware('can:RH.gerer')->name('employes.droits');
            Route::post('/employes/{id}/correction', [RhController::class, 'corriger'])->middleware('can:RH.cloturer')->name('employes.corriger');
            Route::get('/absences', [RhController::class, 'absences'])->middleware('can:RH.voir')->name('absences.index');
            Route::post('/absences', [RhController::class, 'demander'])->middleware('can:RH.gerer')->name('absences.store');
            Route::post('/absences/{id}/decision', [RhController::class, 'traiter'])->middleware('can:RH.gerer')->name('absences.traiter');
            Route::get('/jours-imposes', [RhController::class, 'jours'])->middleware('can:RH.voir')->name('jours');
            Route::post('/jours-imposes', [RhController::class, 'ajouterJour'])->middleware('can:RH.gerer')->name('jours.store');
            Route::post('/jours-imposes/{id}', [RhController::class, 'basculerJour'])->middleware('can:RH.gerer')->name('jours.basculer');
            Route::get('/retenues', [RhController::class, 'retenues'])->middleware('can:RH.voir')->name('retenues');
            Route::post('/retenues', [RhController::class, 'appliquerRetenues'])->middleware('can:RH.gerer')->name('retenues.appliquer');
            Route::get('/cloture', [RhController::class, 'cloture'])->middleware('can:RH.voir')->name('cloture');
            Route::post('/cloture', [RhController::class, 'cloturer'])->middleware('can:RH.cloturer')->name('cloture.appliquer');
        });

        Route::prefix('marketing')->name('marketing.')->group(function () {
            Route::get('/', [MarketingController::class, 'index'])->middleware('can:MKT.voir')->name('index');
            Route::post('/campagnes', [MarketingController::class, 'creerCampagne'])->middleware('can:MKT.gerer')->name('campagnes.store');
            Route::get('/campagnes/{id}', [MarketingController::class, 'campagne'])->middleware('can:MKT.voir')->name('campagne');
            Route::post('/campagnes/{id}/statut', [MarketingController::class, 'statut'])->middleware('can:MKT.gerer')->name('campagne.statut');
            Route::post('/campagnes/{id}/action', [MarketingController::class, 'action'])->middleware('can:MKT.gerer')->name('campagne.action');
            Route::get('/modeles', [MarketingController::class, 'modeles'])->middleware('can:MKT.voir')->name('modeles');
            Route::post('/modeles/{id?}', [MarketingController::class, 'enregistrerModele'])->middleware('can:MKT.gerer')->name('modeles.enregistrer');
            Route::get('/modeles/{id}/image', [MarketingController::class, 'image'])->middleware('can:MKT.voir')->name('modeles.image');
            Route::get('/exclusions', [MarketingController::class, 'exclusions'])->middleware('can:MKT.voir')->name('exclusions');
            Route::post('/exclusions', [MarketingController::class, 'exclure'])->middleware('can:MKT.gerer')->name('exclusions.store');
            Route::post('/exclusions/{id}/retrait', [MarketingController::class, 'retirer'])->middleware('can:MKT.gerer')->name('exclusions.retirer');
            Route::get('/journal', [MarketingController::class, 'journal'])->middleware('can:MKT.voir')->name('journal');
        });
    });

    Route::prefix('administration')->group(function () {
        Route::get('/utilisateurs', [UtilisateurController::class, 'index'])->middleware('can:ACC.voir')->name('utilisateurs.index');
        Route::middleware('can:ACC.gerer')->group(function () {
            Route::get('/utilisateurs/nouveau', [UtilisateurController::class, 'create'])->name('utilisateurs.create');
            Route::post('/utilisateurs', [UtilisateurController::class, 'store'])->name('utilisateurs.store');
            Route::get('/utilisateurs/{utilisateur}', [UtilisateurController::class, 'edit'])->whereNumber('utilisateur')->name('utilisateurs.edit');
            Route::put('/utilisateurs/{utilisateur}', [UtilisateurController::class, 'update'])->whereNumber('utilisateur')->name('utilisateurs.update');
            Route::post('/email-essai', [UtilisateurController::class, 'emailEssai'])->middleware('throttle:empreinte')->name('utilisateurs.email_essai');
            Route::delete('/utilisateurs/{utilisateur}/empreintes', [EmpreinteController::class, 'retirerTout'])->whereNumber('utilisateur')->name('utilisateurs.empreintes.retirer');
        });
        Route::get('/droits', [DroitsController::class, 'edit'])->middleware('can:ACC.droits')->name('droits.edit');
        Route::put('/droits', [DroitsController::class, 'update'])->middleware('can:ACC.droits')->name('droits.update');
        Route::get('/journal', [JournalController::class, 'index'])->middleware('can:ACC.journal')->name('journal.index');
    });
});
