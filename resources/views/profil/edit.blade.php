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

        @if (\App\Support\Empreintes::active())
            <section class="carte space-y-4 p-5 sm:p-6 lg:col-span-2" id="empreinte"
                     data-empreinte-activation data-options="{{ route('profil.empreintes.options') }}" data-activer="{{ route('profil.empreintes.activer') }}">
                <div class="flex items-center gap-2">
                    <x-icone nom="empreinte" class="size-5 text-ferme-700"/>
                    <h2 class="font-semibold text-stone-900">Connexion par empreinte</h2>
                </div>
                <p class="text-sm text-stone-500">
                    Sur votre téléphone, l'empreinte remplace le mot de passe et le code reçu par e-mail.
                    Votre empreinte reste dans le téléphone : SOMADI n'en reçoit jamais de copie.
                </p>

                @if ($empreintes->isNotEmpty())
                    <ul class="divide-y divide-stone-100 rounded-lg border border-stone-200">
                        @foreach ($empreintes as $e)
                            <li class="flex items-center justify-between gap-3 px-3 py-3">
                                <div class="min-w-0">
                                    <p class="truncate text-sm font-medium text-stone-800">{{ $e->appareil ?: 'Appareil' }}</p>
                                    <p class="text-xs text-stone-500">
                                        Activée le {{ \Illuminate\Support\Carbon::parse($e->cree_le)->format('d/m/Y') }}
                                        · {{ $e->utilise_le ? 'dernière connexion le '.\Illuminate\Support\Carbon::parse($e->utilise_le)->format('d/m/Y à H:i') : 'pas encore utilisée' }}
                                    </p>
                                </div>
                                <form method="POST" action="{{ route('profil.empreintes.retirer', $e->id) }}">
                                    @csrf @method('DELETE')
                                    <button type="submit" class="btn-secondaire text-red-700">Retirer</button>
                                </form>
                            </li>
                        @endforeach
                    </ul>
                @else
                    <p class="text-sm text-stone-500">Aucun appareil pour l'instant.</p>
                @endif

                {{-- Affiché par empreinte.js sur un téléphone équipé d'un capteur. --}}
                <div class="hidden space-y-4 border-t border-stone-100 pt-4" data-empreinte-formulaire>
                    <div id="erreur-empreinte" class="hidden rounded-lg border border-red-200 bg-red-50 px-3 py-2 text-sm text-red-800" role="alert"></div>
                    <div>
                        <label for="mdp_empreinte" class="etiquette">Confirmez votre mot de passe pour activer l'empreinte sur ce téléphone</label>
                        <input id="mdp_empreinte" type="password" autocomplete="current-password" class="champ">
                    </div>
                    <button type="button" class="btn-primaire" data-empreinte-bouton>
                        <x-icone nom="empreinte" class="size-5"/>Activer sur ce téléphone
                    </button>
                    <p class="text-xs text-stone-500">N'activez l'empreinte que sur votre téléphone personnel : toute personne dont l'empreinte est enregistrée sur ce téléphone pourrait ouvrir votre session.</p>
                </div>
                <p class="border-t border-stone-100 pt-4 text-sm text-stone-500" data-empreinte-indisponible>
                    Pour activer l'empreinte, ouvrez SOMADI sur votre téléphone, puis Mon profil. Sur ordinateur, la connexion se fait par mot de passe et code reçu par e-mail.
                </p>
            </section>
            <script src="{{ asset('js/empreinte.js') }}?v={{ @filemtime(public_path('js/empreinte.js')) }}" defer></script>
        @endif
    </div>
</x-layouts.app>
