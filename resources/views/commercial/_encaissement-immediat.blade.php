{{-- Encaissement au moment de la vente (facultatif) : nécessite le droit d’encaisser. --}}
@can('CPT.encaisser')
    <fieldset class="rounded-lg border border-stone-200 p-4">
        <legend class="px-1 text-sm font-semibold text-stone-900">Paiement reçu maintenant</legend>
        <div class="mb-3 inline-flex flex-wrap rounded-lg border border-stone-300 p-0.5" role="radiogroup">
            @foreach (['aucun' => 'Rien pour l’instant', 'total' => 'Totalité', 'partiel' => 'Une partie'] as $k => $v)
                <label class="cursor-pointer">
                    <input type="radio" name="encaissement" value="{{ $k }}" class="peer sr-only" @checked(old('encaissement', 'aucun') === $k)
                           onchange="document.querySelectorAll('[data-paiement]').forEach(e => e.hidden = this.value === 'aucun'); document.querySelector('[data-montant-partiel]').hidden = this.value !== 'partiel'">
                    <span class="block rounded-md px-3 py-2 text-sm font-medium text-stone-600 peer-checked:bg-ferme-700 peer-checked:text-white">{{ $v }}</span>
                </label>
            @endforeach
        </div>
        <div class="grid gap-3 sm:grid-cols-2">
            <div data-paiement @if(old('encaissement', 'aucun') === 'aucun') hidden @endif>
                <x-choix name="moyen_paiement" label="Moyen de paiement" :options="array_combine(config('somadi.moyens_paiement'), config('somadi.moyens_paiement'))" vide="Choisir…"/>
            </div>
            <div data-montant-partiel @if(old('encaissement') !== 'partiel') hidden @endif>
                <x-champ name="montant_encaisse" label="Montant reçu" suffixe="FCFA" inputmode="decimal"/>
            </div>
        </div>
        <p class="mt-2 text-xs text-stone-500">Sans paiement, la vente reste « à encaisser » ; les règlements se saisissent ensuite depuis sa fiche.</p>
    </fieldset>
@endcan
