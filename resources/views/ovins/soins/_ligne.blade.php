@php use App\Support\Format; use App\Support\Empreinte; use App\Domaine\Ovins\Soins; $t = today()->toDateString(); @endphp
<div class="flex flex-wrap items-center gap-x-3 gap-y-2 px-4 py-3 text-sm" id="soin-{{ $s->id_soin }}">
    <span class="w-20 tabular-nums {{ $s->statut === 'planifie' && $s->date_soin < $t ? 'font-semibold text-red-700' : 'text-stone-500' }}">{{ Format::date($s->date_soin) }}</span>
    <span class="min-w-0 flex-1">
        <span class="font-medium text-stone-900">{{ $s->nom_soin ?: $s->produit }}</span>{{ $s->dose ? ' · '.$s->dose : '' }}
        <span class="block text-xs text-stone-500">
            @if ($s->cible === 'animal' && $s->id_animal)
                Animal <a href="{{ route('ovins.animaux.show', $s->id_animal) }}" class="text-ferme-700 hover:underline">{{ $s->code_animal ?: '#'.$s->id_animal }}</a>
            @else
                Lot {{ $s->nom_lot ?: '—' }} (collectif)
            @endif
            · {{ $s->nom_site }}{{ $s->num_cycle ? ' · cycle '.$s->num_cycle : '' }}{{ $s->delai_attente_jours ? ' · attente '.$s->delai_attente_jours.' j' : '' }}
        </span>
        @if ($s->commentaire)<span class="block text-xs text-stone-400">{{ $s->commentaire }}</span>@endif
    </span>
    @if ($s->statut === 'planifie')
        @can('OVS.gerer')
            <form method="POST" action="{{ route('ovins.soins.realise', $s->id_soin) }}">@csrf<input type="hidden" name="empreinte" value="{{ $s->empreinte ?? '' }}">
                <button class="btn-primaire min-h-9 px-3 text-xs" @disabled($s->date_soin > $t) title="{{ $s->date_soin > $t ? 'Prévu plus tard' : '' }}"><x-icone nom="check" class="size-4"/> Réalisé</button></form>
            <details class="w-full sm:w-auto"><summary class="cursor-pointer text-xs text-stone-500">Annuler</summary>
                <form method="POST" action="{{ route('ovins.soins.annuler', $s->id_soin) }}" class="mt-2 flex gap-2">@csrf<input name="motif" required minlength="3" maxlength="500" placeholder="Motif" class="champ min-h-9 flex-1 text-xs"><button class="btn-secondaire min-h-9 text-xs">OK</button></form></details>
        @endcan
    @else
        <x-statut :ton="Soins::STATUTS[$s->statut][1] ?? 'neutre'">{{ Soins::STATUTS[$s->statut][0] ?? $s->statut }}{{ $s->statut === 'realise' && $s->utilisateur ? ' · '.$s->utilisateur : '' }}</x-statut>
        @if ($s->statut === 'realise' && ($peutAnnulerRealisation ?? false))
            <details class="w-full sm:w-auto"><summary class="cursor-pointer text-xs text-stone-500">Remettre à faire</summary>
                <form method="POST" action="{{ route('ovins.soins.retour', $s->id_soin) }}" class="mt-2 flex gap-2">@csrf<input name="motif" required minlength="3" maxlength="500" placeholder="Motif" class="champ min-h-9 flex-1 text-xs"><button class="btn-secondaire min-h-9 text-xs">OK</button></form></details>
        @endif
    @endif
</div>
