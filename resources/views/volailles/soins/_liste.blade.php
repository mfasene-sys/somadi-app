@php use App\Support\Format; use App\Support\Empreinte; use App\Domaine\Soins; @endphp
{{-- Liste de soins avec actions Réalisé / Annulé. Paramètres : $liste, $avecLot, $lotFiltre (optionnel). --}}
@if ($liste->isEmpty())
    <x-vide icone="heart">Aucun soin.</x-vide>
@else
    <div class="carte divide-y divide-stone-100">
        @foreach ($liste as $s)
            @php
                $retard = $s->etat === 'planifie' && $s->date_prevue < today()->toDateString();
                $brut = \Illuminate\Support\Arr::only((array) $s, ['id', 'id_lot', 'date_prevue', 'id_soin', 'type', 'produit', 'dose', 'voie', 'origine_plan_id', 'etat', 'date_generation', 'date_changement_etat', 'observation', 'origine']);
                $verrou = Soins::verrouille($s);
            @endphp
            <div class="flex flex-wrap items-center gap-x-3 gap-y-2 px-4 py-3 text-sm">
                <span class="w-20 shrink-0 tabular-nums {{ $retard ? 'font-semibold text-red-700' : 'text-stone-500' }}">{{ Format::date($s->date_prevue) }}</span>
                <div class="min-w-0 flex-1">
                    <div class="font-medium text-stone-900">{{ $s->nom_soin ?? $s->produit ?? 'Soin' }} <span class="font-normal text-stone-500">· {{ $s->type }}</span></div>
                    <div class="truncate text-xs text-stone-500">
                        @if ($avecLot)<a href="{{ route('lots.show', $s->id_lot) }}" class="text-ferme-700 hover:underline">{{ $s->nom_lot }}</a> · @endif
                        {{ collect([$s->produit, $s->dose, $s->voie])->filter()->implode(' · ') ?: '—' }}
                    </div>
                </div>
                <x-statut :ton="['planifie' => $retard ? 'alerte' : 'attente', 'realise' => 'ok', 'annule' => 'neutre'][$s->etat] ?? 'alerte'">{{ $retard ? 'En retard' : (Soins::ETATS[$s->etat] ?? $s->etat) }}</x-statut>
                @if (! $verrou)
                    <form method="POST" action="{{ route('soins.etat', $s->id) }}" class="flex w-full justify-end gap-1 sm:w-auto">
                        @csrf
                        <input type="hidden" name="empreinte" value="{{ Empreinte::de($brut) }}">
                        @isset($lotFiltre)<input type="hidden" name="lot_filtre" value="{{ $lotFiltre }}">@endisset
                        @if ($s->etat !== 'realise')
                            @can('SAV.realiser')<button name="etat" value="realise" class="btn-primaire min-h-9 px-3 text-xs">Réalisé</button>@endcan
                        @endif
                        @if ($s->etat !== 'annule')
                            @can('SAV.gerer')<button name="etat" value="annule" class="btn-secondaire min-h-9 px-3 text-xs">Annuler</button>@endcan
                        @endif
                    </form>
                @endif
            </div>
        @endforeach
    </div>
@endif
