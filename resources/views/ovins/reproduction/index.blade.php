@php use App\Support\Format; use App\Domaine\Ovins\Animaux; @endphp
<x-layouts.app titre="Reproduction ovine" sous-titre="Agnelages attendus, agnelles prêtes et dernières saillies">
    <div class="grid gap-6 lg:grid-cols-2">
        <section>
            <h2 class="mb-3 font-semibold text-stone-900">Agnelages attendus <span class="font-normal text-stone-500">· 30 jours et retards</span></h2>
            @if ($alertes->isEmpty())
                <p class="carte px-4 py-3 text-sm text-stone-500">Aucun agnelage attendu dans les 30 prochains jours.</p>
            @else
                <div class="carte divide-y divide-stone-100 text-sm">
                    @foreach ($alertes as $r)
                        @php($retard = $r->date_agnelage_prevu < today()->toDateString())
                        <a href="{{ route('ovins.reproduction.femelle', $r->id_mere) }}" class="flex items-center gap-3 px-4 py-2.5 hover:bg-stone-50">
                            <span class="w-20 shrink-0 tabular-nums {{ $retard ? 'font-semibold text-red-700' : '' }}">{{ Format::date($r->date_agnelage_prevu) }}</span>
                            <span class="flex-1">{{ $r->code_animal }} <span class="text-stone-500">{{ $r->nom_animal }} · {{ $r->nom_site }}</span></span>
                            @if ($retard)<x-statut ton="alerte">en retard</x-statut>@endif
                        </a>
                    @endforeach
                </div>
            @endif
        </section>

        <section>
            <h2 class="mb-3 font-semibold text-stone-900">Agnelles prêtes <span class="font-normal text-stone-500">· {{ config('somadi.ovins.age_agnelle_jours') }} jours et plus</span></h2>
            @if ($agnelles->isEmpty())
                <p class="carte px-4 py-3 text-sm text-stone-500">Aucune agnelle prête dans les lots « Agnelles ».</p>
            @else
                <div class="carte divide-y divide-stone-100 text-sm">
                    @foreach ($agnelles as $a)
                        <div class="flex flex-wrap items-center gap-3 px-4 py-2.5">
                            <a href="{{ route('ovins.animaux.show', $a->id_animal) }}" class="flex-1 text-ferme-700 hover:underline">{{ $a->code_animal ?: '#'.$a->id_animal }} <span class="text-stone-500">{{ Animaux::age($a->date_naissance) }} · {{ $a->nom_lot }}</span></a>
                            @can('OVM.gerer')
                                <details class="w-full sm:w-auto">
                                    <summary class="cursor-pointer text-xs font-semibold text-ferme-700">Intégrer</summary>
                                    <form method="POST" action="{{ route('ovins.animaux.transfert', $a->id_animal) }}" class="mt-2 grid gap-2 sm:w-72">
                                        @csrf <input type="hidden" name="integration" value="1"><input type="hidden" name="date" value="{{ today()->toDateString() }}">
                                        <select name="id_lot" required class="champ"><option value="">Lot de reproductrices…</option>@foreach ($lotsRepro->where('id_site', $a->id_site) as $l)<option value="{{ $l->id_lot }}">{{ $l->nom_lot }}</option>@endforeach</select>
                                        <select name="id_modele" class="champ"><option value="">Sans modèle de soins</option>@foreach ($modeles as $m)<option value="{{ $m->id_modele }}">{{ $m->nom_modele }}</option>@endforeach</select>
                                        <button class="btn-secondaire min-h-9 text-xs">Intégrer en reproduction</button>
                                    </form>
                                </details>
                            @endcan
                        </div>
                    @endforeach
                </div>
            @endif
        </section>
    </div>

    <section class="mt-8">
        <h2 class="mb-3 font-semibold text-stone-900">Dernières saillies et agnelages</h2>
        @if ($recentes->isEmpty())
            <x-vide icone="heart">Aucune reproduction enregistrée. Ouvrez la fiche d’une femelle pour saisir une saillie.</x-vide>
        @else
            <div class="carte overflow-x-auto">
                <table class="tableau">
                    <thead class="bg-stone-50"><tr><th>Mère</th><th>Père</th><th>Saillie</th><th>Prévu</th><th>Agnelage</th><th class="text-right">Vivants</th><th class="text-right">Morts</th></tr></thead>
                    <tbody class="divide-y divide-stone-100">
                        @foreach ($recentes as $r)
                            <tr class="cursor-pointer hover:bg-stone-50" onclick="location.href='{{ route('ovins.reproduction.femelle', $r->id_mere) }}'">
                                <td class="font-medium">{{ $r->code_mere }}</td><td>{{ $r->code_pere ?: '—' }}</td>
                                <td class="tabular-nums">{{ Format::date($r->date_saillie) }}</td><td class="tabular-nums">{{ Format::date($r->date_agnelage_prevu) }}</td>
                                <td class="tabular-nums">{{ Format::date($r->date_agnelage_reel) }}</td><td class="text-right tabular-nums">{{ $r->date_agnelage_reel ? $r->nb_agneaux_vivants : '' }}</td><td class="text-right tabular-nums">{{ $r->date_agnelage_reel ? $r->nb_agneaux_morts : '' }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </section>
</x-layouts.app>
