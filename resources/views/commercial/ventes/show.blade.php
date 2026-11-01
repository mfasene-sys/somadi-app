@php use App\Support\Montant; use App\Support\Format; use App\Domaine\Ventes; $total = Montant::base($v->montant_total); $reste = Montant::base($v->montant_reste_encaisser); $cloturee = $borne && $v->date_vente <= $borne; @endphp
<x-layouts.app :titre="'Vente n° '.$v->id.' · '.$v->client_nom" :sous-titre="Format::dateLongue($v->date_vente).' · '.$v->client_tel">
    <x-slot:actions>
        <x-statut :ton="$v->statut === 'Encaissé' ? 'ok' : 'attente'" class="px-3 py-1 text-sm">{{ $v->statut === 'Encaissé' ? 'Encaissé' : 'À encaisser' }}</x-statut>
    </x-slot:actions>

    @if ($cloturee)
        <p class="mb-4 rounded-lg bg-stone-100 px-4 py-3 text-sm text-stone-600">Vente d’un exercice clôturé : ses informations sont figées. Un règlement reçu aujourd’hui reste possible et sera daté de l’exercice ouvert.</p>
    @endif
    @unless ($audit['ok'])
        <p class="mb-4 rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-800">{{ $audit['message'] }}
            @can('RAP.voir')<a href="{{ route('rapprochements.show', $v->id) }}" class="font-semibold underline">Ouvrir le rapprochement</a>@endcan
        </p>
    @endunless

    <div class="mb-6 grid grid-cols-2 gap-3 lg:grid-cols-4">
        <x-indicateur label="Produit" :valeur="Format::nombre($v->quantite)" :detail="Ventes::libelle($v->espece).' à '.Montant::fcfa(Montant::base($v->prix_unitaire))"/>
        <x-indicateur label="Total" :valeur="Montant::fcfa($total)"/>
        <x-indicateur label="Encaissé" :valeur="Montant::fcfa($audit['encaisse'])"/>
        <x-indicateur label="Reste à encaisser" :valeur="Montant::fcfa($reste)" :ton="$reste > 0 ? 'alerte' : null"/>
    </div>

    <div class="grid gap-6 lg:grid-cols-[1fr_22rem]">
        <div class="space-y-6">
            <section>
                <h2 class="mb-3 font-semibold text-stone-900">Règlements</h2>
                @if ($operations->isEmpty())
                    <x-vide icone="wallet">Aucun règlement.</x-vide>
                @else
                    <div class="carte divide-y divide-stone-100">
                        @foreach ($operations as $o)
                            <div class="flex flex-wrap items-center gap-3 px-4 py-3 text-sm {{ $o->est_valide ? '' : 'text-stone-400' }}">
                                <span class="w-20 tabular-nums">{{ Format::date($o->date_op) }}</span>
                                <span class="flex-1">{{ $o->moyen_paiement }} · {{ $o->categorie }}
                                    @unless ($o->est_valide)<span class="block text-xs">Invalidé : {{ $o->invalidated_comment }} ({{ $o->invalidated_by }})</span>@endunless
                                </span>
                                <span class="font-semibold tabular-nums {{ $o->est_valide ? '' : 'line-through' }}">{{ Montant::fcfa(Montant::base($o->montant)) }}</span>
                                @if ($o->est_valide)
                                    @can('CPT.gerer')
                                        <details class="w-full sm:w-auto">
                                            <summary class="cursor-pointer text-xs font-medium text-red-700">Invalider</summary>
                                            <form method="POST" action="{{ route('tresorerie.invalider', $o->id) }}" class="mt-2 flex flex-wrap items-end gap-2">
                                                @csrf
                                                <input name="motif" required minlength="3" maxlength="1000" placeholder="Motif" class="champ min-w-40 flex-1">
                                                <label class="flex items-center gap-2 text-xs"><input type="checkbox" name="confirmation" value="1" required class="size-4"> Je confirme</label>
                                                <button class="btn-secondaire min-h-9 text-xs">Invalider</button>
                                            </form>
                                        </details>
                                    @endcan
                                @endif
                            </div>
                        @endforeach
                    </div>
                @endif
            </section>

            @if ($affectations->isNotEmpty())
                <section>
                    <h2 class="mb-3 font-semibold text-stone-900">Œufs prélevés</h2>
                    <div class="carte divide-y divide-stone-100 text-sm">
                        @foreach ($affectations as $a)
                            <div class="flex justify-between px-4 py-2.5"><span>Lot n° {{ $a->id_lot_oeufs }} · ramassé le {{ Format::date($a->date_ramassage) }}</span><strong class="tabular-nums">{{ $a->quantite }} / {{ $a->quantite_lot }}</strong></div>
                        @endforeach
                    </div>
                </section>
            @endif

            <section class="carte space-y-1 p-4 text-sm">
                @if ($lot)<p><span class="text-stone-500">Lot :</span> <a href="{{ route('lots.show', $lot->id) }}" class="text-ferme-700 hover:underline">{{ $lot->nom_lot }}</a></p>@endif
                <p><span class="text-stone-500">Saisie par :</span> {{ $v->utilisateur }}</p>
                @if ($v->commentaire)<p class="whitespace-pre-line text-stone-600">{{ $v->commentaire }}</p>@endif
            </section>
        </div>

        @can('CPT.encaisser')
            @if ($reste > 0 && $audit['ok'])
                <form method="POST" action="{{ route('ventes.encaisser', $v->id) }}" class="carte h-fit space-y-3 p-4">
                    @csrf <x-cle/>
                    <h2 class="font-semibold text-stone-900">Encaisser</h2>
                    <div class="inline-flex rounded-lg border border-stone-300 p-0.5">
                        @foreach (['total' => 'Totalité ('.Montant::fcfa($reste).')', 'partiel' => 'Une partie'] as $k => $lib)
                            <label class="cursor-pointer"><input type="radio" name="mode" value="{{ $k }}" class="peer sr-only" @checked(old('mode', 'total') === $k) onchange="document.getElementById('bloc-montant').hidden = this.value !== 'partiel'">
                                <span class="block rounded-md px-3 py-2 text-sm font-medium text-stone-600 peer-checked:bg-ferme-700 peer-checked:text-white">{{ $lib }}</span></label>
                        @endforeach
                    </div>
                    <div id="bloc-montant" @if(old('mode') !== 'partiel') hidden @endif><x-champ name="montant" label="Montant" suffixe="FCFA" inputmode="decimal"/></div>
                    <x-champ name="date_op" label="Date du paiement" type="date" :value="today()->toDateString()" :min="$v->date_vente" :max="today()->toDateString()" required/>
                    <x-choix name="moyen_paiement" label="Moyen de paiement" :options="array_combine(config('somadi.moyens_paiement'), config('somadi.moyens_paiement'))" vide="Choisir…" required/>
                    <x-choix name="categorie" label="Catégorie de recette" :options="array_combine(config('somadi.categories.recette'), config('somadi.categories.recette'))" :value="config('somadi.produits.'.$v->espece.'.categorie', 'Autres recettes')"/>
                    <x-champ name="observations" label="Observations" maxlength="1000"/>
                    <button class="btn-primaire w-full">Enregistrer le paiement</button>
                </form>
            @endif
        @endcan
    </div>
</x-layouts.app>
