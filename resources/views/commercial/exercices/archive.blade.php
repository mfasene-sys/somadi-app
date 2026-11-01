@php use App\Support\Format; $l = $a['ligne']; @endphp
<x-layouts.app :titre="'Archive de l’exercice '.$annee" :sous-titre="'Clôturé le '.Format::date(substr($l->closed_at, 0, 10)).' par '.$l->author_name">
    <x-slot:actions>
        @if ($a['integre'])
            <a href="{{ route('exercices.pdf', $annee) }}" class="btn-primaire"><x-icone nom="archive" class="size-4"/> Télécharger le PDF</a>
        @endif
    </x-slot:actions>

    @if ($a['integre'])
        <p class="mb-4 flex items-center gap-2 rounded-lg bg-ferme-50 px-4 py-3 text-sm text-ferme-800"><x-icone nom="shield" class="size-5"/> Archive intègre : empreintes SHA-256 vérifiées.</p>
        <div class="carte mb-6 p-4 text-sm"><p class="whitespace-pre-line text-stone-700">{{ $l->commentaire }}</p>
            <p class="mt-2 text-xs text-stone-500">Arrêté au {{ Format::date($l->cutoff) }} · version {{ $l->version_application }} · empreinte {{ substr($l->snapshot_sha256, 0, 16) }}…</p></div>
        @include('commercial.exercices._bilan', ['b' => $a['contenu']['bilan']])
    @else
        <p class="rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-800">Archive altérée : les empreintes ne correspondent plus. Son contenu n’est ni affiché ni téléchargeable ; prévenez l’administrateur.</p>
    @endif
</x-layouts.app>
