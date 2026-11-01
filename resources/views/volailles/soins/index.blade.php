@php use App\Support\Format; use App\Domaine\Soins; @endphp
<x-layouts.app titre="Soins volailles" sous-titre="Planning des soins et vaccins">
    <x-onglets :liens="['Planning' => route('soins.index'), 'Catalogue' => route('soins.catalogue'), 'Programme poussins' => route('soins.programme')]"/>

    <form method="GET" class="carte mb-6 grid gap-3 p-4 sm:grid-cols-5">
        <x-choix name="lot" label="Lot" :options="$lots->pluck('nom_lot', 'id')->all()" :value="$filtres['lot'] ?? null" vide="Tous les lots" class="sm:col-span-2"/>
        <x-choix name="etat" label="État (période)" :options="Soins::ETATS" :value="$filtres['etat'] ?? null" vide="Tous"/>
        <x-champ name="du" label="Du" type="date" :value="$du"/>
        <x-champ name="au" label="Au" type="date" :value="$au"/>
        <div class="flex gap-2 sm:col-span-5"><button class="btn-secondaire">Filtrer</button><a href="{{ route('soins.index') }}" class="btn-secondaire">Effacer</a></div>
    </form>

    <div class="grid gap-6 lg:grid-cols-[1fr_20rem]">
        <div class="space-y-6">
            @if ($retards->isNotEmpty())
                <section>
                    <h2 class="mb-3 font-semibold text-red-700">En retard ({{ $retards->count() }})</h2>
                    @include('volailles.soins._liste', ['liste' => $retards, 'avecLot' => true])
                </section>
            @endif
            <section>
                <h2 class="mb-3 font-semibold text-stone-900">Aujourd’hui</h2>
                @include('volailles.soins._liste', ['liste' => $dujour, 'avecLot' => true])
            </section>
            <section>
                <h2 class="mb-3 font-semibold text-stone-900">Du {{ Format::date($du) }} au {{ Format::date($au) }}</h2>
                @include('volailles.soins._liste', ['liste' => collect($periode->items()), 'avecLot' => true])
                <div class="mt-3">{{ $periode->links() }}</div>
            </section>
        </div>

        @can('SAV.gerer')
            <form method="POST" action="{{ route('soins.store') }}" class="carte h-fit space-y-3 p-4">
                @csrf
                <h2 class="font-semibold text-stone-900">Soin occasionnel</h2>
                <x-choix name="id_lot" label="Lot" :options="$lots->pluck('nom_lot', 'id')->all()" vide="Choisir…" required/>
                <x-choix name="id_soin" label="Soin" :options="$catalogue->mapWithKeys(fn ($c) => [$c->id => $c->nom.' ('.$c->type.')'])->all()" vide="Choisir…" required/>
                <x-champ name="date_prevue" label="Date prévue" type="date" :value="today()->toDateString()" required/>
                <x-champ name="observation" label="Observation" maxlength="2000"/>
                <p class="text-xs text-stone-500">Produit, dose et voie sont recopiés du catalogue. Un même soin ne peut pas être planifié deux fois pour un lot à la même date.</p>
                <button class="btn-primaire w-full">Planifier</button>
            </form>
        @endcan
    </div>
    <p class="mt-6 text-xs text-stone-500">Un soin réalisé ou annulé reste modifiable 24 heures, puis il est verrouillé. L’application ne constitue pas une prescription vétérinaire.</p>
</x-layouts.app>
