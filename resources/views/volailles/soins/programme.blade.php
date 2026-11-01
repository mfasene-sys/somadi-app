@php use App\Support\Format; @endphp
<x-layouts.app titre="Programme poussins" sous-titre="Soins planifiés automatiquement à partir de la date d’entrée ou d’éclosion">
    <x-onglets :liens="['Planning' => route('soins.index'), 'Catalogue' => route('soins.catalogue'), 'Programme poussins' => route('soins.programme')]"/>
    <div class="grid gap-6 lg:grid-cols-[1fr_20rem]">
        <div class="space-y-6">
            @forelse ($lignes->groupBy('espece') as $espece => $etapes)
                <section>
                    <h2 class="mb-2 font-semibold text-stone-900">{{ ucfirst($espece) }} <span class="font-normal text-stone-500">· {{ $etapes->count() }} étape(s)</span></h2>
                    <div class="carte divide-y divide-stone-100">
                        @foreach ($etapes as $e)
                            <div class="flex flex-wrap items-center gap-3 px-4 py-3 text-sm">
                                <span class="w-16 font-semibold tabular-nums">J+{{ $e->jour_post_eclosion }}</span>
                                <span class="min-w-0 flex-1">{{ $e->nom ?? '⚠ soin introuvable' }} <span class="text-stone-500">{{ $e->produit_utilise ? '· '.$e->produit_utilise : '' }}{{ $e->commentaire ? ' · '.$e->commentaire : '' }}</span></span>
                                @can('SAV.gerer')
                                    <form method="POST" action="{{ route('soins.programme.supprimer', $e->id) }}">@csrf @method('DELETE')<button class="font-medium text-red-700 hover:underline">Retirer</button></form>
                                @endcan
                            </div>
                        @endforeach
                    </div>
                </section>
            @empty
                <x-vide icone="heart">Aucun programme défini.</x-vide>
            @endforelse

            @if ($plans->isNotEmpty())
                <section>
                    <h2 class="mb-2 font-semibold text-stone-900">Plans récurrents existants <span class="font-normal text-stone-500">(consultation)</span></h2>
                    <div class="carte overflow-x-auto">
                        <table class="tableau">
                            <thead class="bg-stone-50"><tr><th>Plan</th><th>Espèce</th><th>Récurrence</th><th>Début</th><th>Fin</th></tr></thead>
                            <tbody class="divide-y divide-stone-100">
                                @foreach ($plans as $p)
                                    <tr><td>{{ $p->nom_plan }}</td><td>{{ $p->espece }}</td><td>{{ $p->recurrence_type }}{{ in_array($p->recurrence_type, ['mensuelle', 'annuelle']) ? ' (jours plafonnés au 28)' : '' }}</td><td>{{ Format::date($p->date_debut) }}</td><td>{{ $p->fin_type === 'date' ? Format::date($p->date_fin) : ($p->fin_type === 'occurrences' ? $p->nb_occurrences.' fois' : 'illimitée') }}</td></tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </section>
            @endif
        </div>
        @can('SAV.gerer')
            <form method="POST" action="{{ route('soins.programme.store') }}" class="carte h-fit space-y-3 p-4">
                @csrf
                <h2 class="font-semibold text-stone-900">Ajouter une étape</h2>
                <div class="grid grid-cols-2 gap-3">
                    <x-champ name="espece" label="Espèce" value="poulet" required/>
                    <x-champ name="jour_post_eclosion" label="Jour" type="number" min="0" required/>
                </div>
                <x-choix name="id_soin" label="Soin" :options="$catalogue->mapWithKeys(fn ($c) => [$c->id => $c->nom.' ('.$c->type.')'])->all()" vide="Choisir…" required/>
                <x-champ name="commentaire" label="Commentaire" maxlength="1000"/>
                <button class="btn-primaire w-full">Ajouter</button>
            </form>
        @endcan
    </div>
</x-layouts.app>
