@props(['titre'])
@php
    $fil = \App\Support\Navigation::fil(request());
    $parent = $fil ? end($fil) : null;
@endphp
@if ($parent)
    {{-- Le bouton vise la page parente ; app.js le redirige vers la page d'où l'on vient, si on a navigué dans l'application. --}}
    <nav class="mb-3 flex min-w-0 items-center gap-2 text-sm" aria-label="Navigation">
        <a href="{{ $parent['url'] }}" data-retour class="nav-retour inline-flex min-w-0 shrink-0 items-center gap-1 rounded-md py-1 font-medium text-stone-600" title="Retour à {{ $parent['titre'] }}">
            <span aria-hidden="true">←</span>
            <span class="truncate lg:hidden" data-retour-libelle>{{ $parent['titre'] }}</span>
            <span class="nav-retour-pc">Retour</span>
        </a>
        {{-- Fil d'Ariane : sur PC seulement, pour remonter plusieurs niveaux d'un coup. --}}
        <ol class="hidden min-w-0 flex-wrap items-center gap-1 text-stone-500 lg:flex" data-fil>
            <li aria-hidden="true" style="color:#d6d3d1">|</li>
            @foreach ($fil as $etape)
                <li class="inline-flex min-w-0 items-center gap-1">
                    <a href="{{ $etape['url'] }}" data-chemin="{{ $etape['chemin'] }}" class="nav-fil truncate max-w-xs">{{ $etape['titre'] }}</a>
                    <span aria-hidden="true" style="color:#a8a29e">›</span>
                </li>
            @endforeach
            <li class="truncate max-w-xs font-medium text-stone-700" aria-current="page">{{ $titre }}</li>
        </ol>
    </nav>
@endif
