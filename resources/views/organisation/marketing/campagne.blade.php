@php
    use App\Domaine\Organisation\Marketing as M; use App\Support\Format; use App\Support\Montant; use App\Domaine\Ventes;
    $peut = auth()->user()->can('MKT.gerer') && $c->statut !== 'terminee';
@endphp
<x-layouts.app :titre="$c->titre" :sous-titre="'Modèle « '.$c->modele.' » · '.$total.' client(s) dans la sélection'">
    <x-slot:actions>
        @can('MKT.gerer')
            <form method="POST" action="{{ route('marketing.campagne.statut', $c->id) }}" class="flex gap-2">@csrf
                <select name="statut" class="champ" onchange="this.form.submit()">@foreach (['brouillon' => 'Brouillon', 'en_cours' => 'En cours', 'terminee' => 'Terminée'] as $k => $l)<option value="{{ $k }}" @selected($c->statut === $k)>{{ $l }}</option>@endforeach</select></form>
        @endcan
    </x-slot:actions>
    @include('organisation.marketing._onglets')
    <div class="mb-4 grid gap-4 lg:grid-cols-[1fr_20rem]">
        <form method="GET" class="carte grid grid-cols-2 gap-3 p-4 sm:grid-cols-4">
            <x-champ name="du" label="Ventes du" type="date" :value="$f['du'] ?? null"/>
            <x-champ name="au" label="au" type="date" :value="$f['au'] ?? null"/>
            <x-choix name="produit" label="Produit" :options="$produits" :value="$f['produit'] ?? null" vide="Tous"/>
            <x-champ name="montant_min" label="Montant minimum" suffixe="FCFA" :value="$f['montant_min'] ?? null"/>
            <x-choix name="etat" label="Suivi" :options="['a_faire' => 'À contacter', 'MARQUE_ENVOYE' => 'Envoyés', 'IGNORE' => 'Ignorés']" :value="$f['etat'] ?? null" vide="Tous"/>
            <x-choix name="mode" label="Affichage" :options="['liste' => 'Liste (50 par page)', 'suivant' => 'Un par un']" :value="$mode"/>
            <label class="flex items-end gap-2 pb-3 text-sm"><input type="hidden" name="exclure" value="0"><input type="checkbox" name="exclure" value="1" class="size-5" @checked(($f['exclure'] ?? '1') === '1')> Exclure la liste noire</label>
            <div class="flex items-end"><button class="btn-secondaire w-full">Appliquer</button></div>
        </form>
        <div class="carte p-4 text-sm"><p class="mb-1 text-xs font-semibold uppercase text-stone-500">Message</p><p class="whitespace-pre-line text-stone-700">{{ $c->message }}</p>
            @if ($c->image_path)<a href="{{ route('marketing.modeles.image', $c->id_template) }}" target="_blank" class="mt-2 inline-block text-xs font-semibold text-ferme-700">Voir l’image à joindre (à envoyer à part dans WhatsApp)</a>@endif</div>
    </div>

    @if ($liste->isEmpty())
        <x-vide icone="chat">{{ $mode === 'suivant' ? 'Tous les clients de la sélection ont été traités.' : 'Aucun client ne correspond.' }}</x-vide>
    @else
        <div class="space-y-2">
            @foreach ($liste as $cl)
                @php($texte = M::personnaliser($c->message, $cl))
                <div class="carte p-4 text-sm">
                    <div class="flex flex-wrap items-center gap-2">
                        <span class="flex-1"><strong>{{ $cl->client_nom }}</strong> · {{ $cl->tel_norm }}<span class="block text-xs text-stone-500">Dernier achat : {{ Ventes::libelle($cl->espece) }}, {{ Montant::fcfa(Montant::base($cl->montant_total)) }} le {{ Format::date($cl->date_vente) }} · {{ $cl->nb_achats }} achat(s)</span></span>
                        @if ($cl->etat)<x-statut :ton="M::ACTIONS[$cl->etat][1]">{{ M::ACTIONS[$cl->etat][0] }}</x-statut>@endif
                    </div>
                    @if ($mode === 'suivant')<p class="mt-2 whitespace-pre-line rounded-lg bg-stone-50 p-3 text-stone-700">{{ $texte }}</p>@endif
                    @if ($peut)
                        <div class="mt-2 flex flex-wrap gap-2">
                            <form method="POST" action="{{ route('marketing.campagne.action', $c->id) }}" target="_blank" onsubmit="setTimeout(() => location.reload(), 1500)">@csrf
                                <input type="hidden" name="action" value="OPEN_WA"><input type="hidden" name="tel" value="{{ $cl->client_tel }}"><input type="hidden" name="nom" value="{{ $cl->client_nom }}"><input type="hidden" name="texte" value="{{ $texte }}">
                                <button class="btn-primaire min-h-9 px-3 text-xs"><x-icone nom="chat" class="size-4"/> Ouvrir WhatsApp</button></form>
                            @foreach (['MARQUE_ENVOYE' => 'Marquer envoyé', 'IGNORE' => 'Ignorer'] as $a => $lib)
                                <form method="POST" action="{{ route('marketing.campagne.action', $c->id) }}">@csrf
                                    <input type="hidden" name="action" value="{{ $a }}"><input type="hidden" name="tel" value="{{ $cl->client_tel }}"><input type="hidden" name="nom" value="{{ $cl->client_nom }}">
                                    <button class="btn-secondaire min-h-9 px-3 text-xs">{{ $lib }}</button></form>
                            @endforeach
                        </div>
                    @endif
                </div>
            @endforeach
        </div>
        @if ($mode === 'liste')<div class="mt-3">{{ $liste->links() }}</div>@endif
    @endif
    <p class="mt-4 text-xs text-stone-500">« Ouvrir WhatsApp » prépare le message ; c’est vous qui l’envoyez. « Marquer envoyé » est une déclaration : WhatsApp ne confirme ni la réception ni la lecture.</p>
</x-layouts.app>
