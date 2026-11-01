<x-layouts.invite titre="Hors connexion">
    <div class="carte p-6 text-center">
        <span class="mx-auto grid size-12 place-items-center rounded-full bg-paille-100 text-paille-700">
            <x-icone nom="wifi-off" class="size-6"/>
        </span>
        <h2 class="mt-4 text-lg font-semibold text-stone-900">Pas de connexion</h2>
        <p class="mt-2 text-sm text-stone-600">Cette page n'a pas encore été consultée sur cet appareil, elle n'est donc pas disponible hors connexion. Les pages déjà ouvertes restent consultables.</p>
        <button type="button" onclick="location.reload()" class="btn-primaire mt-6 w-full">Réessayer</button>
    </div>
</x-layouts.invite>
