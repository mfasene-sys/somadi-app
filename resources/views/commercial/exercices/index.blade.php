@php use App\Support\Format; @endphp
<x-layouts.app titre="Exercices comptables" :sous-titre="'Bilan annuel et clôture'.($borne ? ' · clôturé jusqu’au '.Format::date($borne) : '')">
    <div class="mb-5 flex flex-wrap items-center gap-2">
        <a href="?annee={{ $annee - 1 }}" class="btn-secondaire">‹ {{ $annee - 1 }}</a>
        <span class="px-3 py-2 text-lg font-bold tabular-nums">{{ $annee }}</span>
        @if ($annee < today()->year)<a href="?annee={{ $annee + 1 }}" class="btn-secondaire">{{ $annee + 1 }} ›</a>@endif
        @if ($archivee)
            <a href="{{ route('exercices.archive', $annee) }}" class="btn-primaire ml-auto"><x-icone nom="lock" class="size-4"/> Voir l’archive {{ $annee }}</a>
        @elseif ($cloturable)
            @can('EXE.cloturer')
                <form method="POST" action="{{ route('exercices.apercu', $annee) }}" class="ml-auto">@csrf
                    <button class="btn-primaire" @disabled($controles['bloque'])><x-icone nom="lock" class="size-4"/> Préparer la clôture {{ $annee }}</button>
                </form>
            @endcan
        @endif
    </div>

    @if ($archivee)
        <x-vide icone="lock">L’exercice {{ $annee }} est clôturé. Son bilan est celui de l’archive, figé à la date de clôture.</x-vide>
    @else
        @if ($bilan['provisoire'])
            <p class="mb-4 rounded-lg bg-sky-50 px-4 py-3 text-sm text-sky-800">Exercice en cours : bilan provisoire arrêté à aujourd’hui.</p>
        @elseif ($controles['bloque'] && $cloturable)
            <p class="mb-4 rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-800">La clôture est bloquée tant que les anomalies ci-dessous ne sont pas corrigées.</p>
        @endif
        <div class="grid gap-6 xl:grid-cols-[1fr_22rem]">
            <div class="min-w-0">@include('commercial.exercices._bilan', ['b' => $bilan])</div>
            <div>@include('commercial.exercices._controles', ['c' => $controles])</div>
        </div>
    @endif

    <section class="mt-8">
        <h2 class="mb-3 font-semibold text-stone-900">Archives</h2>
        @if ($archives->isEmpty())
            <p class="carte px-4 py-3 text-sm text-stone-500">Aucun exercice clôturé.</p>
        @else
            <div class="carte divide-y divide-stone-100 text-sm">
                @foreach ($archives as $a)
                    <a href="{{ route('exercices.archive', $a->annee) }}" class="flex items-center gap-3 px-4 py-3 hover:bg-stone-50">
                        <strong class="tabular-nums">{{ $a->annee }}</strong>
                        <span class="flex-1 text-stone-500">Clôturé le {{ Format::date(substr($a->closed_at, 0, 10)) }} par {{ $a->author_name }}</span>
                        <x-icone nom="chevron-right" class="size-4 text-stone-400"/>
                    </a>
                @endforeach
            </div>
        @endif
    </section>
</x-layouts.app>
