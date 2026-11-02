<x-layouts.invite titre="Code de connexion">
    <div class="carte p-6 sm:p-7">
        @if (session('statut'))
            <div class="mb-4 rounded-lg bg-ferme-50 px-3 py-2 text-sm text-ferme-800" role="status">{{ session('statut') }}</div>
        @endif
        @if ($errors->any())
            <div class="mb-4 rounded-lg border border-red-200 bg-red-50 px-3 py-2 text-sm text-red-800" role="alert">{{ $errors->first() }}</div>
        @endif

        <h2 class="font-semibold text-stone-900">Vérifiez vos e-mails</h2>
        <p class="mt-1 text-sm text-stone-500">
            Un code à 6 chiffres vient d'être envoyé à <strong class="font-medium text-stone-700">{{ $email }}</strong>.
            Il est valable {{ $minutes }} minutes.
        </p>

        <form method="POST" action="{{ route('login.code.store') }}" class="mt-5 space-y-5" novalidate>
            @csrf
            <div>
                <label for="code" class="etiquette">Code reçu</label>
                <input id="code" name="code" type="text" inputmode="numeric" pattern="[0-9]*" maxlength="6" autocomplete="one-time-code"
                       required autofocus class="champ text-center text-2xl font-semibold tabular-nums" style="letter-spacing: .4em" placeholder="••••••">
            </div>
            <button type="submit" class="btn-primaire w-full">Valider</button>
        </form>

        <div class="mt-5 flex items-center justify-between gap-3 border-t border-stone-100 pt-4 text-sm">
            <form method="POST" action="{{ route('login.code.annuler') }}">
                @csrf
                <button type="submit" class="font-medium text-stone-500">← Retour</button>
            </form>
            <form method="POST" action="{{ route('login.code.renvoyer') }}">
                @csrf
                <button type="submit" class="font-medium text-ferme-700">Renvoyer un code</button>
            </form>
        </div>
    </div>
    <p class="mt-6 text-center text-xs text-stone-500">Rien reçu ? Regardez dans les courriers indésirables avant de demander un nouveau code.</p>
</x-layouts.invite>
