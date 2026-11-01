@php $st = ['brouillon' => ['Brouillon', 'neutre'], 'en_cours' => ['En cours', 'attente'], 'terminee' => ['Terminée', 'ok']]; @endphp
<x-layouts.app titre="Marketing WhatsApp" sous-titre="Messages personnalisés aux clients, envoyés par vous depuis WhatsApp">
    @include('organisation.marketing._onglets')
    <div class="grid gap-6 lg:grid-cols-[1fr_22rem]">
        <div class="carte divide-y divide-stone-100 text-sm">
            @forelse ($campagnes as $c)
                @php($s = collect($stats[$c->id] ?? [])->pluck('n', 'action'))
                <a href="{{ route('marketing.campagne', $c->id) }}" class="flex flex-wrap items-center gap-3 px-4 py-3 hover:bg-stone-50">
                    <span class="flex-1"><span class="font-medium">{{ $c->titre }}</span><span class="block text-xs text-stone-500">Modèle « {{ $c->modele }} » · créée le {{ \App\Support\Format::date(substr($c->created_at, 0, 10)) }}</span></span>
                    <span class="text-xs tabular-nums text-stone-600">{{ (int) ($s['MARQUE_ENVOYE'] ?? 0) }} envoyé(s) · {{ (int) ($s['IGNORE'] ?? 0) }} ignoré(s)</span>
                    <x-statut :ton="$st[$c->statut][1]">{{ $st[$c->statut][0] }}</x-statut>
                </a>
            @empty <p class="px-4 py-3 text-stone-500">Aucune campagne.</p> @endforelse
        </div>
        @can('MKT.gerer')
            <form method="POST" action="{{ route('marketing.campagnes.store') }}" class="carte h-fit space-y-3 p-4">@csrf
                <h2 class="font-semibold text-stone-900">Nouvelle campagne</h2>
                <x-champ name="titre" label="Titre" required maxlength="150" placeholder="Tabaski 2026"/>
                <x-choix name="id_template" label="Modèle de message" :options="$modeles->mapWithKeys(fn ($m) => [$m->id => $m->titre])->all()" vide="Choisir…" required/>
                <button class="btn-primaire w-full">Créer</button>
                @if ($modeles->isEmpty())<p class="text-xs text-stone-500">Créez d’abord un modèle de message.</p>@endif
            </form>
        @endcan
    </div>
</x-layouts.app>
