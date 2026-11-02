<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Support\Connexion;
use App\Support\Empreintes;
use App\Support\Journal;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * Empreinte digitale (WebAuthn) : connexion sur téléphone, activation et retrait depuis Mon profil,
 * retrait par un administrateur (téléphone perdu, départ d'un employé).
 */
class EmpreinteController extends Controller
{
    // ---------- Connexion (visiteur) ----------

    public function optionsConnexion(Request $request): JsonResponse
    {
        abort_unless(Empreintes::active(), 404);

        return response()->json(Empreintes::optionsConnexion($request));
    }

    public function connexion(Request $request): JsonResponse
    {
        abort_unless(Empreintes::active(), 404);
        $user = Empreintes::authentifier($request, (array) $request->input('reponse', []) + ['id' => $request->input('id')]);
        Connexion::ouvrir($request, $user, 'empreinte');

        return response()->json(['redirection' => redirect()->intended(route('accueil'))->getTargetUrl()]);
    }

    // ---------- Mon profil ----------

    /** Activation : le mot de passe est redemandé, pour qu'une session laissée ouverte ne suffise pas à ajouter un téléphone. */
    public function optionsActivation(Request $request): JsonResponse
    {
        abort_unless(Empreintes::active(), 404);
        $user = $request->user();
        if (! $user->verifierMotDePasse((string) $request->input('mot_de_passe'))) {
            Journal::enregistrer('empreinte.activation_refusee', 'ACC', 'utilisateur', $user->id, apres: ['motif' => 'mot de passe']);

            return response()->json(['message' => 'Mot de passe incorrect.'], 422);
        }

        return response()->json(Empreintes::optionsActivation($request, $user));
    }

    public function activer(Request $request): JsonResponse
    {
        abort_unless(Empreintes::active(), 404);
        $user = $request->user();
        $id = Empreintes::enregistrer($request, $user, (array) $request->input('reponse', []));
        $appareil = DB::table('app_empreintes')->where('id', $id)->value('appareil');
        Journal::enregistrer('empreinte.activation', 'ACC', 'empreinte', $id, apres: ['appareil' => $appareil]);
        $request->session()->flash('succes', 'Empreinte activée sur cet appareil ('.$appareil.'). À la prochaine connexion, touchez « Se connecter avec mon empreinte ».');

        return response()->json(['redirection' => route('profil.edit')]);
    }

    public function retirer(Request $request, int $id): RedirectResponse
    {
        $user = $request->user();
        $cle = DB::table('app_empreintes')->where('id', $id)->where('user_id', $user->id)->first();
        abort_unless($cle, 404);
        DB::table('app_empreintes')->where('id', $id)->delete();
        Journal::enregistrer('empreinte.retrait', 'ACC', 'empreinte', $id, avant: ['appareil' => $cle->appareil]);

        return back()->with('succes', 'Empreinte retirée pour « '.$cle->appareil.' ». Pensez aussi à supprimer la clé d’accès SOMADI dans les réglages du téléphone.');
    }

    // ---------- Administration ----------

    public function retirerTout(User $utilisateur): RedirectResponse
    {
        $n = DB::table('app_empreintes')->where('user_id', $utilisateur->id)->delete();
        Journal::enregistrer('empreinte.retrait_admin', 'ACC', 'utilisateur', $utilisateur->id, avant: ['nombre' => $n]);

        return back()->with('succes', $n ? "Empreintes retirées ($n appareil".($n > 1 ? 's' : '').") pour {$utilisateur->nomComplet()}." : 'Aucune empreinte à retirer.');
    }
}
