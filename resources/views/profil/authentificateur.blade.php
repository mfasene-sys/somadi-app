<x-layouts.app titre="Application d'authentification" sous-titre="Un code sur votre téléphone, sans attendre d'e-mail">
    <div class="grid max-w-4xl gap-6 lg:grid-cols-2">
        <section class="carte space-y-4 p-5 sm:p-6">
            <h2 class="font-semibold text-stone-900">1. Installez une application</h2>
            <p class="text-sm text-stone-600">
                Sur votre téléphone, installez <strong>Google Authenticator</strong> ou <strong>Microsoft Authenticator</strong> (gratuites, Play Store ou App Store).
                Elles fonctionnent sans réseau.
            </p>
            <h2 class="font-semibold text-stone-900">2. Scannez ce QR code</h2>
            <p class="text-sm text-stone-600">Dans l'application : « + » puis « Scanner un code QR ».</p>
            <div class="qr-authentificateur">{!! $qr !!}</div>
            <details class="text-sm">
                <summary class="cursor-pointer font-medium text-ferme-700">Impossible de scanner ? Saisir la clé à la main</summary>
                <div class="mt-3 space-y-2 text-stone-600">
                    <p>Choisissez « Saisir une clé de configuration », nom du compte : <strong>SOMADI Farming</strong>, type : <strong>basé sur l'heure</strong>, puis la clé :</p>
                    <p class="cle-authentificateur">{{ $cle }}</p>
                    <p class="text-xs text-stone-500">Ne communiquez cette clé à personne : elle permet de produire vos codes.</p>
                </div>
            </details>
        </section>

        <form method="POST" action="{{ route('profil.authentificateur.activer') }}" class="carte space-y-5 p-5 sm:p-6 self-start" novalidate>
            @csrf
            <h2 class="font-semibold text-stone-900">3. Confirmez</h2>
            <p class="text-sm text-stone-600">L'application affiche maintenant un code à 6 chiffres pour SOMADI Farming, qui change toutes les 30 secondes. Saisissez-le, avec votre mot de passe.</p>
            <div>
                <label for="code" class="etiquette">Code affiché</label>
                <input id="code" name="code" type="text" inputmode="numeric" pattern="[0-9]*" maxlength="6" autocomplete="one-time-code" required
                       class="champ text-center text-2xl font-semibold tabular-nums" style="letter-spacing: .4em" placeholder="••••••">
                @error('code')<p class="erreur">{{ $message }}</p>@enderror
            </div>
            <div>
                <label for="mot_de_passe" class="etiquette">Mot de passe</label>
                <input id="mot_de_passe" name="mot_de_passe" type="password" autocomplete="current-password" required class="champ">
                @error('mot_de_passe')<p class="erreur">{{ $message }}</p>@enderror
            </div>
            <button type="submit" class="btn-primaire w-full">Activer</button>
            <p class="text-xs text-stone-500">Ensuite, vous recevrez 10 codes de secours à imprimer : ils servent si vous perdez votre téléphone.</p>
        </form>
    </div>
</x-layouts.app>
