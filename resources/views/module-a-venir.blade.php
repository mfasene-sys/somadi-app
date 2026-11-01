<x-layouts.app :titre="$module['titre']">
    <div class="carte mx-auto max-w-xl p-8 text-center">
        <span class="mx-auto grid size-14 place-items-center rounded-2xl bg-stone-100 text-stone-500">
            <x-icone :nom="$module['icone']" class="size-7"/>
        </span>
        <h2 class="mt-4 text-lg font-semibold text-stone-900">Ce module arrive avec le lot {{ $module['lot'] }}</h2>
        <p class="mt-2 text-sm text-stone-600">
            En attendant, utilisez l'ancienne application pour {{ mb_strtolower($module['titre']) }}. Les données sont partagées : rien ne sera perdu au passage à la nouvelle version.
        </p>
        <a href="{{ route('accueil') }}" class="btn-secondaire mt-6">Retour à l'accueil</a>
    </div>
</x-layouts.app>
