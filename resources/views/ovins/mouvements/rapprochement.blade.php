@php use App\Support\Format; use App\Support\Montant; $pl = $plan['p'] ?? null; $d = $plan['d'] ?? []; @endphp
<x-layouts.app :titre="'Sortie n° '.$s->id_sortie.' · '.($s->code_animal ?: '#'.$s->id_animal)" :sous-titre="'Vente du '.Format::date($s->date_sortie).' à '.($s->client_nom ?: '?').' · '.($s->prix_unitaire ? Montant::fcfa(Montant::base($s->prix_unitaire)) : 'sans prix')">
    @if ($liaison)
        <p class="mb-4 rounded-lg bg-ferme-50 px-4 py-3 text-sm text-ferme-800">Cette sortie est liée à la <a class="font-semibold underline" href="{{ route('ventes.show', $liaison->id_vente) }}">vente n° {{ $liaison->id_vente }}</a>.</p>
    @else
        @if ($pl)
            <section class="carte mb-6 space-y-3 border-ferme-300 p-4">
                <h2 class="font-semibold text-stone-900">Aperçu</h2>
                @if ($pl['mode'] === 'existante')
                    <p class="text-sm">La sortie sera liée à la vente existante n° <strong>{{ $pl['vente'] }}</strong>. Aucun montant ne change.</p>
                @else
                    <p class="text-sm">Une vente ovine de <strong>{{ Montant::fcfa($pl['prix']) }}</strong> sera créée au {{ Format::date($s->date_sortie) }} pour {{ $s->client_nom }}.
                        {{ count($pl['recettes']) }} recette(s) déjà saisie(s) y seront rattachées ({{ Montant::fcfa($pl['cumul']) }}) ; reste à encaisser : <strong>{{ Montant::fcfa($pl['prix'] - $pl['cumul']) }}</strong>.</p>
                @endif
                <p class="text-sm text-stone-600">Motif : {{ $pl['motif'] }}</p>
                <form method="POST" action="{{ route('ovins.rapprochements.confirmer', $s->id_sortie) }}" class="flex flex-wrap items-center gap-3">@csrf
                    <label class="flex items-center gap-2 text-sm"><input type="checkbox" name="confirmation" value="1" required class="size-5"> Je confirme ce rapprochement.</label>
                    <button class="btn-primaire">Valider</button></form>
            </section>
        @endif

        <div class="grid gap-6 lg:grid-cols-2">
            <form method="POST" action="{{ route('ovins.rapprochements.apercu', $s->id_sortie) }}" class="carte space-y-3 p-4">
                @csrf <input type="hidden" name="mode" value="existante">
                <h2 class="font-semibold text-stone-900">Lier à une vente existante</h2>
                @if ($concordantes->isEmpty())
                    <p class="text-sm text-stone-500">Aucune vente ne concorde exactement (ovin, quantité 1, même client, même date, même prix, solde cohérent, sans liaison).</p>
                @else
                    @foreach ($concordantes as $v)
                        <label class="flex items-center gap-3 rounded-lg border border-stone-200 px-3 py-2 text-sm"><input type="radio" name="id_vente" value="{{ $v->id }}" required class="size-5" @checked(($d['id_vente'] ?? null) == $v->id)>
                            <span class="flex-1">Vente n° {{ $v->id }} · {{ $v->client_nom }} · {{ $v->espece }}</span><strong>{{ Montant::fcfa(Montant::base($v->montant_total)) }}</strong></label>
                    @endforeach
                    <x-champ name="motif" label="Motif" required minlength="10" type="textarea" :value="($d['mode'] ?? '') === 'existante' ? $d['motif'] : null" id="motif_e"/>
                    <button class="btn-secondaire w-full">Voir l’aperçu</button>
                @endif
            </form>

            <form method="POST" action="{{ route('ovins.rapprochements.apercu', $s->id_sortie) }}" class="carte space-y-3 p-4">
                @csrf <input type="hidden" name="mode" value="creation">
                <h2 class="font-semibold text-stone-900">Créer la vente manquante</h2>
                <p class="text-xs text-stone-500">Cochez les recettes déjà saisies qui correspondent à cette vente (postérieures à la vente, sans lien, hors période clôturée).</p>
                <div class="max-h-72 divide-y divide-stone-100 overflow-y-auto rounded-lg border border-stone-200 text-sm">
                    @forelse ($recettes as $o)
                        <label class="flex items-center gap-3 px-3 py-2"><input type="checkbox" name="recettes[]" value="{{ $o->id }}" class="size-4" @checked(in_array($o->id, $d['recettes'] ?? []))>
                            <span class="flex-1">{{ Format::date($o->date_op) }} · {{ $o->libelle }}</span><span class="tabular-nums">{{ Montant::fcfa(Montant::base($o->montant)) }}</span></label>
                    @empty <p class="px-3 py-2 text-stone-500">Aucune recette admissible.</p> @endforelse
                </div>
                <label class="flex items-start gap-2 text-sm"><input type="checkbox" name="aucun_paiement" value="1" class="mt-0.5 size-5"> Sans recette cochée : j’atteste qu’aucun paiement n’a été reçu pour cette vente.</label>
                <x-champ name="client_tel" label="Téléphone du client" type="tel" :value="$s->client_contact" maxlength="20"/>
                <x-champ name="motif" label="Motif" required minlength="10" type="textarea" :value="($d['mode'] ?? '') === 'creation' ? $d['motif'] : null" id="motif_c"/>
                <button class="btn-secondaire w-full">Voir l’aperçu</button>
            </form>
        </div>
    @endif
</x-layouts.app>
