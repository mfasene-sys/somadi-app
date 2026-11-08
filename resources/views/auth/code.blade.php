<x-layouts.invite titre="Code de connexion">
    <div class="carte p-6 sm:p-7">
        @if (session('statut'))
            <div class="mb-4 rounded-lg bg-ferme-50 px-3 py-2 text-sm text-ferme-800" role="status">{{ session('statut') }}</div>
        @endif
        @if ($errors->any())
            <div class="mb-4 rounded-lg border border-red-200 bg-red-50 px-3 py-2 text-sm text-red-800" role="alert">{{ $errors->first() }}</div>
        @endif

        @if ($mode === 'appli')
            <h2 class="font-semibold text-stone-900">Code de votre application</h2>
            <p class="mt-1 text-sm text-stone-500">
                Ouvrez votre application d'authentification (Google Authenticator, Microsoft Authenticator…) et saisissez le code à 6 chiffres affiché pour <strong class="font-medium text-stone-700">SOMADI Farming</strong>.
            </p>
        @else
            <h2 class="font-semibold text-stone-900">Vérifiez vos e-mails</h2>
            <p class="mt-1 text-sm text-stone-500">
                Un code à 6 chiffres vient d'être envoyé à <strong class="font-medium text-stone-700">{{ $email }}</strong>.
                Il est valable {{ $minutes }} minutes.
            </p>
        @endif

        <form method="POST" action="{{ route('login.code.store') }}" class="mt-5 space-y-5" novalidate>
            @csrf
            <div>
                <label for="code" class="etiquette">{{ $mode === 'appli' ? 'Code affiché' : 'Code reçu' }}</label>
                <input id="code" name="code" type="text" inputmode="numeric" pattern="[0-9]*" maxlength="6" autocomplete="one-time-code"
                       required autofocus class="champ text-center text-2xl font-semibold tabular-nums" style="letter-spacing: .4em" placeholder="••••••">
            </div>
            <button type="submit" class="btn-primaire w-full">Valider</button>
        </form>

        @if ($secours)
            <details class="mt-4 text-sm">
                <summary class="cursor-pointer font-medium text-ferme-700">Utiliser un code de secours</summary>
                <form method="POST" action="{{ route('login.code.store') }}" class="mt-3 space-y-3" novalidate>
                    @csrf
                    <label for="code_secours" class="etiquette">Code de secours (sur la liste imprimée lors de l'activation)</label>
                    <input id="code_secours" name="code" type="text" maxlength="12" autocomplete="off" autocapitalize="characters" spellcheck="false"
                           class="champ text-center font-mono text-lg uppercase tracking-wider" placeholder="XXXXX-XXXXX">
                    <p class="text-xs text-stone-500">Chaque code ne sert qu'une fois.</p>
                    <button type="submit" class="btn-secondaire w-full">Valider le code de secours</button>
                </form>
            </details>
        @endif

        <div class="mt-5 flex flex-wrap items-center justify-between gap-3 border-t border-stone-100 pt-4 text-sm">
            <form method="POST" action="{{ route('login.code.annuler') }}">
                @csrf
                <button type="submit" class="font-medium text-stone-500">← Retour</button>
            </form>
            @if ($mode === 'appli')
                @if ($emailPossible)
                    <form method="POST" action="{{ route('login.code.renvoyer') }}">
                        @csrf
                        <button type="submit" class="font-medium text-ferme-700">Recevoir plutôt un code par e-mail</button>
                    </form>
                @endif
            @else
                <div class="flex flex-wrap gap-x-4 gap-y-2">
                    @if ($appli)
                        <form method="POST" action="{{ route('login.code.application') }}">
                            @csrf
                            <button type="submit" class="font-medium text-ferme-700">Utiliser l'application</button>
                        </form>
                    @endif
                    <form method="POST" action="{{ route('login.code.renvoyer') }}">
                        @csrf
                        <button type="submit" class="font-medium text-ferme-700">Renvoyer un code</button>
                    </form>
                </div>
            @endif
        </div>
    </div>
    <p class="mt-6 text-center text-xs text-stone-500">
        @if ($mode === 'appli')
            Téléphone perdu ? Utilisez un code de secours, ou prévenez un administrateur.
        @else
            Rien reçu ? Regardez dans les courriers indésirables avant de demander un nouveau code.
        @endif
    </p>
</x-layouts.invite>
