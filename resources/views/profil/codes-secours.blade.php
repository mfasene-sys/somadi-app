@php($texte = "SOMADI Farming — codes de secours de ".$user->email."\nCréés le ".now()->format('d/m/Y à H:i')."\nChaque code ne sert qu'une fois.\n\n".implode("\n", $codes)."\n")
<x-layouts.app titre="Codes de secours" sous-titre="À imprimer ou à noter maintenant : ils ne seront plus affichés">
    <div class="max-w-xl space-y-5">
        <div class="carte space-y-4 p-5 sm:p-6">
            <p class="text-sm text-stone-600">
                Si vous n'avez plus votre téléphone, ou si l'e-mail n'arrive pas, saisissez l'un de ces codes à la place du code à 6 chiffres
                (lien « Utiliser un code de secours »). <strong>Chaque code ne sert qu'une fois.</strong>
            </p>
            <ol class="codes-secours">
                @foreach ($codes as $c)
                    <li>{{ $c }}</li>
                @endforeach
            </ol>
            <p class="text-xs text-stone-500">{{ $user->email }} · créés le {{ now()->format('d/m/Y à H:i') }}</p>
            <div class="non-imprime flex flex-wrap gap-2">
                <button type="button" onclick="window.print()" class="btn-primaire">Imprimer</button>
                <a href="data:text/plain;charset=utf-8,{{ rawurlencode($texte) }}" download="somadi-codes-secours.txt" class="btn-secondaire">Télécharger</a>
                <a href="{{ route('profil.edit') }}#authentificateur" class="btn-secondaire">J'ai noté mes codes</a>
            </div>
        </div>
        <p class="non-imprime text-sm text-stone-500">Rangez-les à l'abri, pas dans le téléphone lui-même. Vous pourrez en créer de nouveaux à tout moment dans Mon profil (les anciens cesseront alors de fonctionner).</p>
    </div>
</x-layouts.app>
