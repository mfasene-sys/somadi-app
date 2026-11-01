<x-layouts.app titre="Mon profil" :sous-titre="$user->email.' · '.\App\Support\Roles::libelle($user->role)">
    <div class="grid max-w-4xl gap-6 lg:grid-cols-2">
        <form method="POST" action="{{ route('profil.update') }}" class="carte space-y-5 p-5 sm:p-6">
            @csrf @method('PUT')
            <h2 class="font-semibold text-stone-900">Identité</h2>
            <div>
                <label for="prenom" class="etiquette">Prénom</label>
                <input id="prenom" name="prenom" value="{{ old('prenom', $user->prenom) }}" maxlength="100" class="champ">
                @error('prenom')<p class="erreur">{{ $message }}</p>@enderror
            </div>
            <div>
                <label for="nom" class="etiquette">Nom</label>
                <input id="nom" name="nom" value="{{ old('nom', $user->nom) }}" maxlength="100" required class="champ">
                @error('nom')<p class="erreur">{{ $message }}</p>@enderror
            </div>
            <p class="text-xs text-stone-500">L'adresse e-mail et le rôle sont modifiables par un administrateur.</p>
            <button type="submit" class="btn-primaire">Enregistrer</button>
        </form>

        <form method="POST" action="{{ route('profil.mot_de_passe') }}" class="carte space-y-5 p-5 sm:p-6">
            @csrf @method('PUT')
            <h2 class="font-semibold text-stone-900">Mot de passe</h2>
            <div>
                <label for="actuel" class="etiquette">Mot de passe actuel</label>
                <input id="actuel" name="actuel" type="password" autocomplete="current-password" required class="champ">
                @error('actuel')<p class="erreur">{{ $message }}</p>@enderror
            </div>
            <div>
                <label for="nouveau" class="etiquette">Nouveau mot de passe</label>
                <input id="nouveau" name="nouveau" type="password" autocomplete="new-password" required class="champ">
                @error('nouveau')<p class="erreur">{{ $message }}</p>@enderror
            </div>
            <div>
                <label for="nouveau_confirmation" class="etiquette">Confirmation</label>
                <input id="nouveau_confirmation" name="nouveau_confirmation" type="password" autocomplete="new-password" required class="champ">
            </div>
            <button type="submit" class="btn-primaire">Changer le mot de passe</button>
        </form>
    </div>
</x-layouts.app>
