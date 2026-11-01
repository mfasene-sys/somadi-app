@php use App\Support\Montant; use App\Support\Format; $cloturee = $borne && $v->date_vente <= $borne; @endphp
<x-layouts.app :titre="'Rapprochement · vente n° '.$v->id" :sous-titre="$v->client_nom.' · '.Format::date($v->date_vente).' · '.Montant::fcfa(Montant::base($v->montant_total))">
    <x-slot:actions><a href="{{ route('ventes.show', $v->id) }}" class="btn-secondaire">Fiche de la vente</a></x-slot:actions>

    <p class="mb-4 rounded-lg px-4 py-3 text-sm {{ $audit['ok'] ? 'bg-ferme-50 text-ferme-800' : 'border border-red-200 bg-red-50 text-red-800' }}">{{ $audit['ok'] ? 'Cette vente est cohérente avec ses règlements liés.' : $audit['message'] }}</p>

    <div class="mb-6 grid grid-cols-2 gap-3 lg:grid-cols-4">
        <x-indicateur label="Total" :valeur="Montant::fcfa($audit['total'])"/>
        <x-indicateur label="Réglé (lié)" :valeur="Montant::fcfa($audit['encaisse'])"/>
        <x-indicateur label="Reste affiché" :valeur="Montant::fcfa($audit['reste'])"/>
        <x-indicateur label="Reste calculé" :valeur="Montant::fcfa($audit['reste_calcule'])" :ton="$audit['reste'] !== $audit['reste_calcule'] ? 'alerte' : null"/>
    </div>

    <div class="grid gap-6 lg:grid-cols-2">
        <section>
            <h2 class="mb-3 font-semibold text-stone-900">Règlements déjà liés</h2>
            <div class="carte divide-y divide-stone-100 text-sm">
                @forelse ($liees as $o)
                    <div class="flex gap-3 px-4 py-2.5"><span class="tabular-nums text-stone-500">{{ Format::date($o->date_op) }}</span><span class="flex-1">n° {{ $o->id }} · {{ $o->libelle }}</span><strong class="tabular-nums">{{ Montant::fcfa(Montant::base($o->montant)) }}</strong></div>
                @empty <p class="px-4 py-3 text-stone-500">Aucun.</p> @endforelse
            </div>
        </section>

        <section>
            <h2 class="mb-3 font-semibold text-stone-900">Recettes sans lien pouvant correspondre</h2>
            @if ($cloturee)
                <p class="carte px-4 py-3 text-sm text-stone-500">Vente d’un exercice clôturé : consultation seulement.</p>
            @else
                @can('RAP.gerer')
                    <form method="POST" action="{{ route('rapprochements.preparer', $v->id) }}" class="space-y-3">
                        @csrf
                        <div class="carte divide-y divide-stone-100 text-sm">
                            @forelse ($candidates as $o)
                                <label class="flex min-h-12 cursor-pointer items-center gap-3 px-4 py-2 hover:bg-stone-50">
                                    <input type="checkbox" name="operations[]" value="{{ $o->id }}" class="size-5" @checked(in_array($o->id, old('operations', [])))>
                                    <span class="flex-1"><span class="tabular-nums text-stone-500">{{ Format::date($o->date_op) }}</span> · n° {{ $o->id }} · {{ $o->libelle }}<span class="block text-xs text-stone-500">{{ $o->categorie }} · {{ $o->moyen_paiement }}</span></span>
                                    <strong class="tabular-nums">{{ Montant::fcfa(Montant::base($o->montant)) }}</strong>
                                </label>
                            @empty <p class="px-4 py-3 text-stone-500">Aucune recette autonome compatible (même période, même lot).</p> @endforelse
                        </div>
                        <x-champ name="motif" label="Justificatifs et motif" type="textarea" required minlength="10" maxlength="2000" aide="Ex. : reçu n° 124 du client, confirmé par téléphone. Sans case cochée, seul le reste à encaisser est recalculé."/>
                        <button class="btn-primaire w-full sm:w-auto">Voir l’aperçu</button>
                    </form>
                @else
                    <p class="carte px-4 py-3 text-sm text-stone-500">Consultation seulement.</p>
                @endcan
            @endif
        </section>
    </div>

    @if ($historique->isNotEmpty())
        <section class="mt-8">
            <h2 class="mb-3 font-semibold text-stone-900">Historique</h2>
            <div class="carte divide-y divide-stone-100 text-sm">
                @foreach ($historique as $h)
                    @php($av = json_decode($h->avant_json, true)) @php($ap = json_decode($h->apres_json, true))
                    <div class="px-4 py-3"><p><strong>{{ Format::date(substr($h->created_at, 0, 10)) }}</strong> · {{ $h->author_name }}</p>
                        <p class="text-stone-600">{{ $h->motif }}</p>
                        <p class="text-xs text-stone-500">Reste : {{ Montant::fcfa($av['reste'] ?? 0) }} → {{ Montant::fcfa($ap['reste'] ?? 0) }}@if (! empty($ap['ajoutees'])) · opérations liées n° {{ implode(', ', $ap['ajoutees']) }}@endif</p></div>
                @endforeach
            </div>
        </section>
    @endif
</x-layouts.app>
