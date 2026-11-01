@php use App\Support\Montant; @endphp
<x-layouts.app titre="Clôture annuelle des congés" :sous-titre="'Paiement des jours non pris '.$annee.' (une seule fois par employé)'">
    <x-slot:actions><a href="?annee={{ $annee - 1 }}" class="btn-secondaire">‹ {{ $annee - 1 }}</a>@if ($annee < today()->year)<a href="?annee={{ $annee + 1 }}" class="btn-secondaire">{{ $annee + 1 }} ›</a>@endif</x-slot:actions>
    @include('organisation.rh._onglets')
    @if (! $apercu)
        <x-vide icone="check">Aucun jour non pris à payer pour {{ $annee }}.</x-vide>
    @else
        <div class="carte divide-y divide-stone-100 text-sm">
            @foreach ($apercu as $r)
                <div class="flex flex-wrap items-center gap-3 px-4 py-2.5 {{ $r['deja'] ? 'text-stone-400' : '' }}"><span class="flex-1">{{ trim($r['employe']->prenom.' '.$r['employe']->nom) }}</span><span class="tabular-nums">{{ $r['jours'] }} j · {{ Montant::fcfa($r['montant']) }}</span>
                    @if ($r['deja'])<x-statut ton="neutre">déjà payé</x-statut>@endif</div>
            @endforeach
        </div>
        @can('RH.cloturer')
            <form method="POST" action="{{ route('rh.cloture.appliquer') }}" class="carte mt-4 flex flex-wrap items-center gap-3 p-4">@csrf <input type="hidden" name="annee" value="{{ $annee }}">
                <label class="flex items-center gap-2 text-sm"><input type="checkbox" name="confirmation" value="1" required class="size-5"> Je génère les paiements de {{ $annee }} (datés du 31/12, mois d’application décembre).</label>
                <button class="btn-primaire">Générer</button></form>
        @endcan
        <p class="mt-3 text-xs text-stone-500">Un employé déjà payé pour l’année n’est jamais payé une seconde fois ; une erreur se corrige par une écriture de correction sur sa fiche. Ce calcul n’est pas une paie réglementaire et ne crée aucune opération de trésorerie.</p>
    @endif
</x-layouts.app>
