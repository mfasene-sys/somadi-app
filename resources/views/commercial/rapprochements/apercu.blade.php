@php use App\Support\Montant; use App\Support\Format; @endphp
<x-layouts.app :titre="'Aperçu du rapprochement · vente n° '.$v->id" sous-titre="Rien n’est encore modifié : vérifiez puis confirmez">
    <div class="mx-auto max-w-2xl space-y-5">
        <div class="carte p-4 text-sm">
            <p><strong>{{ $v->client_nom }}</strong> · vente du {{ Format::date($v->date_vente) }} · total {{ Montant::fcfa($t['total']) }}</p>
            <p class="mt-1 text-stone-600">{{ $t['motif'] }}</p>
        </div>
        <div class="grid grid-cols-2 gap-3">
            <div class="carte p-4"><p class="text-xs text-stone-500">Avant</p><p class="text-xl font-bold tabular-nums">{{ Montant::fcfa($t['avant']['reste']) }}</p><p class="text-xs text-stone-500">{{ $t['avant']['statut'] }}</p></div>
            <div class="carte border-ferme-300 p-4"><p class="text-xs text-stone-500">Après</p><p class="text-xl font-bold tabular-nums text-ferme-700">{{ Montant::fcfa($t['apres']['reste']) }}</p><p class="text-xs text-stone-500">{{ $t['apres']['statut'] }}</p></div>
        </div>
        @if ($operations->isNotEmpty())
            <div class="carte divide-y divide-stone-100 text-sm">
                <p class="px-4 py-2 text-xs font-semibold uppercase tracking-wide text-stone-500">Opérations qui seront liées</p>
                @foreach ($operations as $o)
                    <div class="flex gap-3 px-4 py-2.5"><span class="tabular-nums text-stone-500">{{ Format::date($o->date_op) }}</span><span class="flex-1">n° {{ $o->id }} · {{ $o->libelle }}</span><strong class="tabular-nums">{{ Montant::fcfa(Montant::base($o->montant)) }}</strong></div>
                @endforeach
            </div>
        @endif
        <form method="POST" action="{{ route('rapprochements.confirmer', $jeton) }}" class="carte space-y-3 p-4">
            @csrf
            <label class="flex items-start gap-2 text-sm"><input type="checkbox" name="historique_complet" value="1" required class="mt-0.5 size-5"> J’ai vérifié l’historique complet des paiements de ce client.</label>
            <label class="flex items-start gap-2 text-sm"><input type="checkbox" name="confirmation" value="1" required class="mt-0.5 size-5"> Je confirme ce rapprochement ; il sera journalisé à mon nom.</label>
            <div class="flex flex-col-reverse gap-3 sm:flex-row sm:justify-end">
                <a href="{{ route('rapprochements.show', $v->id) }}" class="btn-secondaire">Annuler</a>
                <button class="btn-primaire">Valider le rapprochement</button>
            </div>
            <p class="text-xs text-stone-500">Aperçu valable 20 minutes. Si la vente ou les opérations changent entre-temps, il faudra le refaire.</p>
        </form>
    </div>
</x-layouts.app>
