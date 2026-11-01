@php use App\Support\Format; use App\Support\Empreinte; use App\Domaine\LotsVolailles; use App\Domaine\Soins; $actif = $lot->statut === 'actif'; @endphp
<x-layouts.app :titre="$lot->nom_lot" :sous-titre="ucfirst((string) $lot->espece).' · '.$lot->race.' · entré le '.Format::date($lot->date_entree).($age !== null && $age >= 0 ? ' · '.$age.' jours' : '')">
    <x-slot:actions>
        @can('POI.voir')<a href="{{ route('rentabilite.show', $lot->id) }}" class="btn-secondaire"><x-icone nom="scale" class="size-4"/>Rentabilité</a>@endcan
        @can('VOL.gerer')<a href="{{ route('lots.edit', $lot->id) }}" class="btn-secondaire">Modifier</a>@endcan
    </x-slot:actions>

    @unless ($actif)
        <div class="mb-5 rounded-lg border border-stone-200 bg-stone-100 px-4 py-3 text-sm text-stone-600">Lot archivé : consultation seule. Les pesées restent possibles.</div>
    @endunless

    <div class="mb-6 grid grid-cols-2 gap-3 lg:grid-cols-4">
        <x-indicateur label="Effectif courant" :valeur="Format::nombre($lot->effectif_en_cours)" :detail="'initial '.Format::nombre($lot->nombre_initial)"/>
        <x-indicateur label="Dernier poids moyen" :valeur="$serie->last() ? Format::nombre($serie->last()->poids_moyen, 0).' g' : '—'" :detail="$gain ? 'gain '.Format::nombre($gain['g_jour'], 1).' g/jour' : null"/>
        <x-indicateur label="Aliment consommé" :valeur="$alimentation ? Format::nombre($alimentation['kg'], 1).' kg' : '—'" :detail="$plan ? 'plan : '.$plan->nom_plan : 'aucun plan'"/>
        <x-indicateur label="Soins à faire" :valeur="$soins->where('etat', 'planifie')->count()" :detail="$soins->where('etat', 'planifie')->where('date_prevue', '<', today()->toDateString())->count().' en retard'"/>
    </div>

    @if ($aCompleter)
        <p class="mb-5 rounded-lg bg-paille-50 px-4 py-3 text-sm text-paille-700">{{ $aCompleter }} sujet(s) sortis en vente attendent d’être complétés dans le module Ventes. L’effectif en tient déjà compte. @can('VEN.gerer')<a href="{{ route('ventes.a_completer') }}" class="font-semibold underline">Compléter</a>@endcan</p>
    @endif

    <div class="grid gap-6 lg:grid-cols-[1fr_22rem]">
        <div class="space-y-6">
            {{-- Mouvements --}}
            <section>
                <h2 class="mb-3 font-semibold text-stone-900">Mouvements</h2>
                @if ($mouvements->isEmpty())
                    <x-vide icone="move">Aucun mouvement.</x-vide>
                @else
                    <div class="carte divide-y divide-stone-100">
                        @foreach ($mouvements as $m)
                            <div class="flex items-center gap-3 px-4 py-2.5 text-sm">
                                <span class="w-20 shrink-0 tabular-nums text-stone-500">{{ Format::date($m->date_mouvement) }}</span>
                                <x-statut :ton="$m->type_mouvement === 'entrée' ? 'ok' : ($m->type_mouvement === 'mortalité' ? 'alerte' : ($m->type_mouvement === 'vente' ? 'info' : 'neutre'))">{{ ucfirst($m->type_mouvement) }}</x-statut>
                                <span class="font-semibold tabular-nums">{{ $m->type_mouvement === 'entrée' ? '+' : '−' }}{{ $m->quantite }}</span>
                                <span class="ml-auto truncate text-xs text-stone-400">{{ $m->commentaire }}</span>
                            </div>
                        @endforeach
                    </div>
                @endif
            </section>

            {{-- Pesées --}}
            <section id="pesees">
                <h2 class="mb-3 font-semibold text-stone-900">Croissance</h2>
                @if ($serie->count() >= 2)
                    @php
                        $t0 = \Illuminate\Support\Carbon::parse($serie->first()->date_suivi); $tN = \Illuminate\Support\Carbon::parse($serie->last()->date_suivi);
                        $duree = max(1, $t0->diffInDays($tN)); $max = max(1, (float) $serie->max('poids_moyen'));
                        $pts = $serie->map(fn ($p) => [round(40 + 540 * $t0->diffInDays(\Illuminate\Support\Carbon::parse($p->date_suivi)) / $duree, 1), round(170 - 150 * (float) $p->poids_moyen / $max, 1), $p]);
                    @endphp
                    <figure class="carte p-3">
                        <svg viewBox="0 0 600 200" class="h-48 w-full" role="img" aria-label="Courbe du poids moyen">
                            <line x1="40" y1="170" x2="580" y2="170" stroke="currentColor" class="text-stone-200"/>
                            <text x="36" y="24" text-anchor="end" class="fill-stone-400 text-[11px]">{{ Format::nombre($max) }} g</text>
                            <polyline fill="none" stroke="currentColor" stroke-width="2.5" class="text-ferme-600" points="{{ $pts->map(fn ($p) => $p[0].','.$p[1])->implode(' ') }}"/>
                            @foreach ($pts as $p)<circle cx="{{ $p[0] }}" cy="{{ $p[1] }}" r="3.5" class="fill-ferme-700"><title>{{ Format::date($p[2]->date_suivi) }} : {{ Format::nombre($p[2]->poids_moyen) }} g</title></circle>@endforeach
                            <text x="40" y="190" class="fill-stone-400 text-[11px]">{{ Format::date($serie->first()->date_suivi) }}</text>
                            <text x="580" y="190" text-anchor="end" class="fill-stone-400 text-[11px]">{{ Format::date($serie->last()->date_suivi) }}</text>
                        </svg>
                        <figcaption class="px-1 text-xs text-stone-500">Points espacés selon le temps écoulé. Survolez un point pour sa valeur.</figcaption>
                    </figure>
                @endif
                <div class="mt-3 grid gap-3 sm:grid-cols-[1fr_auto]">
                    <div class="carte overflow-x-auto">
                        <table class="tableau">
                            <thead class="bg-stone-50"><tr><th>Date</th><th class="text-right">Poids moyen</th><th>Saisi par</th></tr></thead>
                            <tbody class="divide-y divide-stone-100">
                                @forelse ($serie->reverse()->take(20) as $p)
                                    <tr><td class="tabular-nums">{{ Format::date($p->date_suivi) }}</td><td class="text-right tabular-nums">{{ Format::nombre($p->poids_moyen, 2) }} g</td><td class="text-stone-500">{{ $p->utilisateur }}</td></tr>
                                @empty
                                    <tr><td colspan="3" class="py-4 text-center text-stone-500">Aucune pesée.</td></tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                    @can('POI.gerer')
                        <form method="POST" action="{{ route('lots.pesee', $lot->id) }}" class="carte space-y-3 p-4 sm:w-56">
                            @csrf
                            <x-champ name="date_suivi" label="Date" type="date" :value="today()->toDateString()" required/>
                            <x-champ name="poids_moyen" label="Poids moyen" suffixe="g" required inputmode="decimal" placeholder="1280,50"/>
                            <button class="btn-primaire w-full">Ajouter</button>
                        </form>
                    @endcan
                </div>
            </section>

            {{-- Soins --}}
            <section id="soins">
                <div class="mb-3 flex flex-wrap items-center justify-between gap-2">
                    <h2 class="font-semibold text-stone-900">Soins</h2>
                    @can('SAV.gerer')
                        @if ($actif)
                            <form method="POST" action="{{ route('lots.programme', $lot->id) }}">@csrf<button class="btn-secondaire">Générer le programme poussins</button></form>
                        @endif
                    @endcan
                </div>
                @include('volailles.soins._liste', ['liste' => $soins, 'avecLot' => false, 'lotFiltre' => $lot->id])
            </section>
        </div>

        <aside class="order-first space-y-5 lg:order-none">
            @can('VOL.gerer')
                @if ($actif)
                    <form method="POST" action="{{ route('lots.mouvement', $lot->id) }}" class="carte space-y-3 p-4">
                        @csrf
                        <h2 class="font-semibold text-stone-900">Nouveau mouvement</h2>
                        <x-choix name="type_mouvement" label="Type" :options="LotsVolailles::TYPES" value="mortalité"/>
                        <div class="grid grid-cols-2 gap-3">
                            <x-champ name="quantite" label="Quantité" type="number" min="1" required inputmode="numeric"/>
                            <x-champ name="date_mouvement" label="Date" type="date" :value="today()->toDateString()" :max="today()->toDateString()" required/>
                        </div>
                        @if (count($origines) > 1)
                            <x-choix name="origine" label="Emplacement de sortie" :options="collect($origines)->mapWithKeys(fn ($o) => [$o['id'] => $o['nom'].' ('.$o['quantite'].')'])->all()" vide="—" aide="Obligatoire pour une sortie : les sujets sont répartis sur plusieurs emplacements."/>
                        @endif
                        <x-champ name="commentaire" label="Motif" required maxlength="1000"/>
                        <p class="text-xs text-stone-500">Une vente crée une <a href="{{ route('ventes.a_completer') }}" class="text-ferme-700 underline">vente à compléter</a> dans le module Ventes, sans second débit d’effectif.</p>
                        <button class="btn-primaire w-full">Enregistrer</button>
                    </form>
                @endif
            @endcan

            @can('SAV.gerer')
                @if ($actif)
                    <form method="POST" action="{{ route('soins.store') }}" class="carte space-y-3 p-4">
                        @csrf
                        <input type="hidden" name="id_lot" value="{{ $lot->id }}">
                        <h2 class="font-semibold text-stone-900">Soin occasionnel</h2>
                        <x-choix name="id_soin" label="Soin" :options="$catalogue->mapWithKeys(fn ($c) => [$c->id => $c->nom.' ('.$c->type.')'])->all()"/>
                        <x-champ name="date_prevue" label="Date prévue" type="date" :value="today()->toDateString()" required/>
                        <button class="btn-secondaire w-full">Planifier</button>
                    </form>
                @endif
            @endcan

            @if ($thermo->isNotEmpty())
                <div class="carte p-4">
                    <h2 class="mb-2 font-semibold text-stone-900">Température cible</h2>
                    <ul class="space-y-1 text-sm">
                        @foreach ($thermo as $t)
                            <li class="flex justify-between {{ $t->date_prevue === today()->toDateString() ? 'font-semibold text-ferme-800' : 'text-stone-600' }}"><span>{{ Format::date($t->date_prevue) }}</span><span class="tabular-nums">{{ Format::nombre($t->temperature_cible, 1) }} °C ± {{ Format::nombre($t->tolerance, 1) }}</span></li>
                        @endforeach
                    </ul>
                </div>
            @endif

            <div class="carte space-y-2 p-4 text-sm">
                <h2 class="font-semibold text-stone-900">Informations</h2>
                <p><span class="text-stone-500">Origine :</span> {{ $lot->origine ?: '—' }}</p>
                @if ($incubation)<p><span class="text-stone-500">Incubation :</span> <a href="{{ route('incubation.show', $incubation->id) }}" class="text-ferme-700 hover:underline">{{ $incubation->nom_lot }}</a></p>@endif
                @if ($lot->remarques)<p class="whitespace-pre-line text-stone-600">{{ $lot->remarques }}</p>@endif
                @can('VOL.gerer')
                    <form method="POST" action="{{ route('lots.statut', $lot->id) }}" class="pt-2">
                        @csrf
                        <input type="hidden" name="statut" value="{{ $actif ? 'archive' : 'actif' }}">
                        <button class="btn-secondaire w-full">{{ $actif ? 'Archiver le lot' : 'Réactiver le lot' }}</button>
                    </form>
                @endcan
            </div>
        </aside>
    </div>
</x-layouts.app>
