<x-layouts.invite :titre="$titre">
    <div class="carte p-6 text-center">
        <div class="text-4xl font-bold text-ferme-700">{{ $code }}</div>
        <h2 class="mt-3 text-lg font-semibold text-stone-900">{{ $titre }}</h2>
        <p class="mt-2 text-sm text-stone-600">{{ $message }}</p>
        <a href="{{ url('/') }}" class="btn-primaire mt-6 w-full">Retour à l'accueil</a>
    </div>
</x-layouts.invite>
