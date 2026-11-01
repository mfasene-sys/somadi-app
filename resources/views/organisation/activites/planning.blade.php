@php use App\Domaine\Organisation\Activites as A; $jours = collect(range(0, 6))->map(fn ($i) => $lundi->copy()->addDays($i)); $occ = $occupations->groupBy('id_user'); @endphp
<x-layouts.app titre="Planning de la semaine" :sous-titre="'Du '.$lundi->format('d/m').' au '.$lundi->copy()->addDays(6)->format('d/m/Y')">
    @include('organisation.activites._onglets')
    <div class="mb-4 flex items-center gap-2">
        <a href="?semaine={{ $lundi->copy()->subWeek()->toDateString() }}" class="btn-secondaire">‹ Semaine précédente</a>
        <a href="?semaine={{ today()->toDateString() }}" class="btn-secondaire">Cette semaine</a>
        <a href="?semaine={{ $lundi->copy()->addWeek()->toDateString() }}" class="btn-secondaire">Suivante ›</a>
    </div>
    @if ($personnes->isEmpty())
        <x-vide icone="calendar">Aucune tâche affectée cette semaine.</x-vide>
    @else
        <div class="carte overflow-x-auto">
            <table class="w-full min-w-[56rem] table-fixed text-xs">
                <thead class="bg-stone-50"><tr><th class="w-32 p-2 text-left">Personne</th>@foreach ($jours as $j)<th class="p-2 text-left {{ $j->isToday() ? 'text-ferme-700' : '' }}">{{ ucfirst($j->locale('fr')->isoFormat('ddd D')) }}</th>@endforeach</tr></thead>
                <tbody class="divide-y divide-stone-100">
                    @foreach ($personnes as $p)
                        <tr class="align-top">
                            <td class="p-2 font-medium">{{ trim($p->prenom.' '.$p->nom) }}</td>
                            @foreach ($jours as $j)
                                <td class="space-y-1 p-1 {{ $j->isToday() ? 'bg-ferme-50/40' : '' }}">
                                    @foreach ($occ[$p->id] ?? [] as $o)
                                        @if (substr($o->debut, 0, 10) <= $j->toDateString() && substr($o->fin, 0, 10) >= $j->toDateString())
                                            <div class="rounded bg-stone-200 px-1.5 py-1 text-stone-700" title="{{ $o->commentaire }}">{{ A::OCCUPATIONS[$o->type] }} · {{ $o->titre }}</div>
                                        @endif
                                    @endforeach
                                    @foreach ($parPersonne[$p->id][$j->toDateString()] ?? [] as $t)
                                        <a href="{{ route('activites.taches.show', $t->id) }}" class="block rounded border-l-4 bg-white px-1.5 py-1 shadow-sm hover:bg-stone-50 {{ $t->statut === 'realise' ? 'opacity-50 line-through' : '' }}" style="border-color: {{ $t->couleur ?: '#a8a29e' }}">
                                            <span class="tabular-nums text-stone-500">{{ substr($t->date_debut_prevue, 11, 5) }}</span> {{ $t->nom }}</a>
                                    @endforeach
                                </td>
                            @endforeach
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @endif
    @if (A::planificateur())
        <details class="carte mt-6 max-w-2xl p-4">
            <summary class="cursor-pointer font-semibold text-stone-900">Ajouter une occupation (réunion, mission, absence)</summary>
            <form method="POST" action="{{ route('activites.occupations.store') }}" class="mt-3 grid gap-3 sm:grid-cols-2">
                @csrf
                <x-choix name="id_user" label="Personne" :options="$utilisateurs->mapWithKeys(fn ($u) => [$u->id => trim($u->prenom.' '.$u->nom)])->all()" vide="Choisir…" required/>
                <x-choix name="type" label="Type" :options="A::OCCUPATIONS"/>
                <x-champ name="debut" label="Début" type="datetime-local" required/>
                <x-champ name="fin" label="Fin" type="datetime-local" required/>
                <x-champ name="titre" label="Titre" maxlength="200" class="sm:col-span-2"/>
                <button class="btn-secondaire sm:col-span-2">Ajouter</button>
                <p class="text-xs text-stone-500 sm:col-span-2">Ces occupations sont indépendantes des congés du module RH.</p>
            </form>
        </details>
    @endif
</x-layouts.app>
