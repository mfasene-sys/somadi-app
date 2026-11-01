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
            <button type="submit" class="btn-primaire w-full">Se connecter</button>
        </form>
    </div>
    <p class="mt-6 text-center text-xs text-stone-500">Mot de passe oublié ? Demandez à un administrateur de le réinitialiser.</p>
</x-layouts.invite>
