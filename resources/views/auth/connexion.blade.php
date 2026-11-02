<x-layouts.invite titre="Connexion">
    @if (session('vider_cache_hors_ligne'))
        <meta name="somadi-vider-cache" content="1">
    @endif
    <div class="carte p-6 sm:p-7">
        @if (session('statut'))
            <div class="mb-4 rounded-lg bg-ferme-50 px-3 py-2 text-sm text-ferme-800" role="status">{{ session('statut') }}</div>
        @endif
        @if (session('erreur'))
            <div class="mb-4 rounded-lg border border-red-200 bg-red-50 px-3 py-2 text-sm text-red-800" role="alert">{{ session('erreur') }}</div>
        @endif
        @if ($errors->any())
            <div class="mb-4 rounded-lg border border-red-200 bg-red-50 px-3 py-2 text-sm text-red-800" role="alert">{{ $errors->first() }}</div>
        @endif

        @if (\App\Support\Empreintes::active())
            {{-- Affiché par empreinte.js uniquement sur un téléphone équipé d'un capteur (empreinte ou visage). --}}
            <div id="bloc-empreinte" class="hidden" data-empreinte-connexion
                 data-options="{{ route('login.empreinte.options') }}" data-verifier="{{ route('login.empreinte') }}">
                <div id="erreur-empreinte" class="mb-4 hidden rounded-lg border border-red-200 bg-red-50 px-3 py-2 text-sm text-red-800" role="alert"></div>
                <button type="button" class="btn-primaire w-full" data-empreinte-bouton>
                    <x-icone nom="empreinte" class="size-5"/>Se connecter avec mon empreinte
                </button>
                <div class="my-5 flex items-center gap-3 text-xs text-stone-400" aria-hidden="true" style="margin-block: 1.25rem">
                    <span class="flex-1 border-t border-stone-200"></span>ou avec le mot de passe<span class="flex-1 border-t border-stone-200"></span>
                </div>
            </div>
        @endif

        <form method="POST" action="{{ route('login.store') }}" class="space-y-5" novalidate>
            @csrf
            <div>
                <label for="email" class="etiquette">Adresse e-mail</label>
                <input id="email" name="email" type="email" value="{{ old('email') }}" autocomplete="username" inputmode="email" required autofocus class="champ">
            </div>
            <div>
                <label for="mot_de_passe" class="etiquette">Mot de passe</label>
                <input id="mot_de_passe" name="mot_de_passe" type="password" autocomplete="current-password" required class="champ">
            </div>
            <button type="submit" class="btn-primaire w-full" data-bouton-mot-de-passe>Se connecter</button>
            @if (config('somadi.authentification.pin_email'))
                <p class="text-center text-xs text-stone-500">Un code de confirmation vous sera envoyé par e-mail.</p>
            @endif
        </form>
    </div>
    <p class="mt-6 text-center text-xs text-stone-500">Mot de passe oublié ? Demandez à un administrateur de le réinitialiser.</p>
    @if (\App\Support\Empreintes::active())
        <script src="{{ asset('js/empreinte.js') }}?v={{ @filemtime(public_path('js/empreinte.js')) }}" defer></script>
    @endif
</x-layouts.invite>
