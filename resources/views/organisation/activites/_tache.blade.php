@php use App\Domaine\Organisation\Activites as A; use App\Support\Format; $retard = in_array($t->statut, ['planifie', 'en_cours', 'reporte']) && $t->date_echeance && $t->date_echeance < now()->format('Y-m-d H:i:s'); @endphp
<a href="{{ route('activites.taches.show', $t->id) }}" class="flex min-h-14 items-center gap-3 px-4 py-2.5 text-sm hover:bg-stone-50">
    <span class="w-1.5 self-stretch rounded-full" style="background: {{ $t->couleur ?: '#d6d3d1' }}"></span>
    <span class="w-24 shrink-0 tabular-nums {{ $retard ? 'font-semibold text-red-700' : 'text-stone-500' }}">{{ $t->date_debut_prevue ? Format::date(substr($t->date_debut_prevue, 0, 10)).' '.substr($t->date_debut_prevue, 11, 5) : 'Sans date' }}</span>
    <span class="min-w-0 flex-1"><span class="block truncate font-medium text-stone-900">{{ $t->nom }}</span>
        <span class="block truncate text-xs text-stone-500">{{ $t->categorie ?: '' }}{{ $t->projet ? ' · '.$t->projet : '' }}{{ $t->routine ? ' · '.$t->routine : '' }}</span></span>
    @if (in_array($t->priorite, ['haute', 'critique']))<x-statut :ton="A::PRIORITES[$t->priorite][1]">{{ A::PRIORITES[$t->priorite][0] }}</x-statut>@endif
    <x-statut :ton="A::STATUTS[$t->statut][1]">{{ A::STATUTS[$t->statut][0] }}</x-statut>
</a>
