@php($nouveau = ! $utilisateur->exists)
<x-layouts.app :titre="$nouveau ? 'Nouvel utilisateur' : $utilisateur->nomComplet()" :sous-titre="$nouveau ? null : $utilisateur->email">
    @if (! $nouveau && auth()->user()->can('ACC.identite') && \App\Support\Identite::refus(auth()->user(), $utilisateur) === null)
        <x-slot:actions>
            <form method="POST" action="{{ route('identite.prendre', $utilisateur) }}" data-changement-identite>
                @csrf
                <button type="submit" class="btn-secondaire"><x-icone nom="users" class="size-4"/>Prendre son identité</button>
            </form>
        </x-slot:actions>
    @endif
    <form method="POST" action="{{ $nouveau ? route('utilisateurs.store') : route('utilisateurs.update', $utilisateur) }}" class="carte max-w-2xl space-y-6 p-5 sm:p-6">
        @csrf
        @unless ($nouveau) @method('PUT') @endunless

        @if ($errors->any())
            <div class="rounded-lg border border-red-200 bg-red-50 px-3 py-2 text-sm text-red-800" role="alert">Corrigez les champs signalés.</div>
        @endif

        <div class="grid gap-5 sm:grid-cols-2">
            <div>
                <label for="prenom" class="etiquette">Prénom</label>
                <input id="prenom" name="prenom" value="{{ old('prenom', $utilisateur->prenom) }}" maxlength="100" class="champ" autocomplete="off">
                @error('prenom')<p class="erreur">{{ $message }}</p>@enderror
            </div>
            <div>
                <label for="nom" class="etiquette">Nom</label>
                <input id="nom" name="nom" value="{{ old('nom', $utilisateur->nom) }}" maxlength="100" required class="champ" autocomplete="off">
                @error('nom')<p class="erreur">{{ $message }}</p>@enderror
            </div>
            <div class="sm:col-span-2">
                <label for="email" class="etiquette">Adresse e-mail (identifiant de connexion)</label>
                <input id="email" name="email" type="email" value="{{ old('email', $utilisateur->email) }}" maxlength="100" required class="champ" autocomplete="off" inputmode="email">
                @error('email')<p class="erreur">{{ $message }}</p>@enderror
            </div>
            <div>
                <label for="role" class="etiquette">Rôle</label>
                <select id="role" name="role" class="champ">
                    @foreach ($roles as $cle => $libelle)
                        <option value="{{ $cle }}" @selected(old('role', $utilisateur->role) === $cle)>{{ $libelle }}</option>
                    @endforeach
                </select>
                @error('role')<p class="erreur">{{ $message }}</p>@enderror
            </div>
            @unless ($nouveau)
                <div class="flex items-end">
                    <label class="flex min-h-11 cursor-pointer items-center gap-3">
                        <input type="hidden" name="actif" value="0">
                        <input type="checkbox" name="actif" value="1" @checked(old('actif', $utilisateur->actif)) class="size-5 rounded border-stone-300 text-ferme-700 focus:ring-ferme-600">
                        <span class="text-sm font-medium text-stone-700">Compte actif</span>
                    </label>
                </div>
            @endunless
        </div>

        <fieldset class="border-t border-stone-100 pt-5">
            <legend class="text-sm font-semibold text-stone-900">{{ $nouveau ? 'Mot de passe' : 'Réinitialiser le mot de passe' }}</legend>
            <p class="mt-1 text-sm text-stone-500">{{ $nouveau ? '8 caractères minimum, avec des lettres et des chiffres.' : 'Laissez vide pour conserver le mot de passe actuel.' }}</p>
            <div class="mt-4 grid gap-5 sm:grid-cols-2">
                <div>
                    <label for="mot_de_passe" class="etiquette">Mot de passe</label>
                    <input id="mot_de_passe" name="mot_de_passe" type="password" autocomplete="new-password" class="champ" @if($nouveau) required @endif>
                    @error('mot_de_passe')<p class="erreur">{{ $message }}</p>@enderror
                </div>
                <div>
                    <label for="mot_de_passe_confirmation" class="etiquette">Confirmation</label>
                    <input id="mot_de_passe_confirmation" name="mot_de_passe_confirmation" type="password" autocomplete="new-password" class="champ">
                </div>
            </div>
        </fieldset>

        <div class="flex flex-col-reverse gap-3 border-t border-stone-100 pt-5 sm:flex-row sm:justify-end">
            <a href="{{ route('utilisateurs.index') }}" class="btn-secondaire">Annuler</a>
            <button type="submit" class="btn-primaire">{{ $nouveau ? 'Créer le compte' : 'Enregistrer' }}</button>
        </div>
    </form>

    @if (! $nouveau && \App\Support\Authentificateur::disponible() && ($appli = \App\Support\Authentificateur::infos($utilisateur)))
        <section class="carte mt-6 max-w-2xl space-y-3 p-5 sm:p-6">
            <h2 class="font-semibold text-stone-900">Application d'authentification</h2>
            <p class="text-sm text-stone-500">
                Activée le {{ \Illuminate\Support\Carbon::parse($appli->active_le)->format('d/m/Y') }}
                · {{ \App\Support\Authentificateur::codesSecoursRestants($utilisateur) }} code(s) de secours restant(s).
                Téléphone perdu et plus de codes de secours ? Retirez-la : la personne recevra de nouveau son code par e-mail.
            </p>
            <form method="POST" action="{{ route('utilisateurs.authentificateur.retirer', $utilisateur) }}">
                @csrf @method('DELETE')
                <button type="submit" class="btn-secondaire text-red-700">Retirer l'application d'authentification</button>
            </form>
        </section>
    @endif

    @if (! $nouveau && \App\Support\Empreintes::active())
        <section class="carte mt-6 max-w-2xl space-y-3 p-5 sm:p-6">
            <h2 class="font-semibold text-stone-900">Connexion par empreinte</h2>
            @if (($empreintes ?? collect())->isEmpty())
                <p class="text-sm text-stone-500">Aucun téléphone activé pour ce compte.</p>
            @else
                <p class="text-sm text-stone-500">
                    {{ $empreintes->count() }} appareil{{ $empreintes->count() > 1 ? 's' : '' }} :
                    {{ $empreintes->pluck('appareil')->map(fn ($a) => $a ?: 'Appareil')->implode(', ') }}.
                    En cas de téléphone perdu ou de départ, retirez-les : la personne devra se reconnecter avec mot de passe et code.
                </p>
                <form method="POST" action="{{ route('utilisateurs.empreintes.retirer', $utilisateur) }}">
                    @csrf @method('DELETE')
                    <button type="submit" class="btn-secondaire text-red-700">Retirer toutes les empreintes</button>
                </form>
            @endif
        </section>
    @endif
</x-layouts.app>
