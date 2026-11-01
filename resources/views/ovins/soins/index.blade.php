@php use App\Support\Format; use App\Support\Empreinte; use App\Domaine\Ovins\Soins; $t = today()->toDateString(); @endphp
<x-layouts.app titre="Soins ovins" sous-titre="Soins à faire par lot ou par animal, et leur suivi">
    <x-onglets :liens="['À faire' => route('ovins.soins.index'), 'Historique' => route('ovins.soins.index', ['vue' => 'historique']), 'Catalogue et modèles' => route('ovins.soins.catalogue')]"/>

    <div class="grid gap-6 lg:grid-cols-[1fr_22rem]">
        <div class="min-w-0">
            <form method="GET" class="carte mb-4 grid grid-cols-2 gap-3 p-4 sm:grid-cols-3">
                <input type="hidden" name="vue" value="{{ $vue }}">
                <x-choix name="site" label="Site" :options="$sites->mapWithKeys(fn ($s) => [$s->id_site => $s->nom_site])->all()" :value="$f['site'] ?? null" vide="Tous"/>
                <x-choix name="lot" label="Lot" :options="$lots->mapWithKeys(fn ($l) => [$l->id_lot => $l->nom_lot])->all()" :value="$f['lot'] ?? null" vide="Tous"/>
                <div class="col-span-2 flex items-end sm:col-span-1"><button class="btn-secondaire w-full">Filtrer</button></div>
            </form>

            @if ($vue === 'a_faire')
                @foreach ($groupes as $titre => $liste)
                    <section class="mb-6">
                        <h2 class="mb-3 font-semibold {{ $titre === 'En retard' && $liste->isNotEmpty() ? 'text-red-700' : 'text-stone-900' }}">{{ $titre }} <span class="font-normal text-stone-500">· {{ $liste->count() }}</span></h2>
                        @if ($liste->isEmpty())
                            <p class="carte px-4 py-3 text-sm text-stone-500">Rien.</p>
                        @else
                            <div class="carte divide-y divide-stone-100">
                                @foreach ($liste as $s)
                                    @include('ovins.soins._ligne', ['s' => $s])
                                @endforeach
                            </div>
                        @endif
                    </section>
                @endforeach
            @else
                @if ($soins->isEmpty())
                    <x-vide icone="heart">Aucun soin réalisé ou annulé.</x-vide>
                @else
                    <div class="carte divide-y divide-stone-100">
                        @foreach ($soins as $s)
                            @include('ovins.soins._ligne', ['s' => $s])
                        @endforeach
                    </div>
                    <div class="mt-3">{{ $soins->links() }}</div>
                @endif
            @endif
        </div>

        @can('OVS.gerer')
            <aside class="space-y-4">
                <form method="POST" action="{{ route('ovins.soins.store') }}" class="carte space-y-3 p-4">
                    @csrf <x-cle/>
                    <h2 class="font-semibold text-stone-900">Saisir un soin</h2>
                    <div class="inline-flex rounded-lg border border-stone-300 p-0.5">
                        @foreach (['lot' => 'Un lot', 'animal' => 'Un animal'] as $k => $lib)
                            <label class="cursor-pointer"><input type="radio" name="cible" value="{{ $k }}" class="peer sr-only" @checked(old('cible', 'lot') === $k) onchange="document.querySelectorAll('[data-cible]').forEach(e => { e.hidden = e.dataset.cible !== this.value; e.querySelector('select').disabled = e.hidden; })">
                                <span class="block rounded-md px-3 py-2 text-sm font-medium text-stone-600 peer-checked:bg-ferme-700 peer-checked:text-white">{{ $lib }}</span></label>
                        @endforeach
                    </div>
                    <div data-cible="lot" @if (old('cible', 'lot') !== 'lot') hidden @endif><x-choix name="id_cible" label="Lot" :options="$lots->mapWithKeys(fn ($l) => [$l->id_lot => $l->nom_site.' · '.$l->nom_lot])->all()" vide="Choisir…" id="cible_lot" :disabled="old('cible', 'lot') !== 'lot'"/></div>
                    <div data-cible="animal" @if (old('cible', 'lot') !== 'animal') hidden @endif><x-choix name="id_cible" label="Animal" :options="$animaux->mapWithKeys(fn ($a) => [$a->id_animal => ($a->code_animal ?: '#'.$a->id_animal).($a->nom_animal ? ' · '.$a->nom_animal : '')])->all()" vide="Choisir…" id="cible_animal" :disabled="old('cible', 'lot') !== 'animal'"/></div>
                    <x-choix name="id_type_soin" label="Soin" :options="$types->mapWithKeys(fn ($t) => [$t->id_type_soin => $t->nom_soin])->all()" vide="Choisir…" required/>
                    <div class="grid grid-cols-2 gap-3">
                        <x-champ name="date_soin" label="Date" type="date" :value="$t" required/>
                        <x-champ name="dose" label="Dose" maxlength="30"/>
                    </div>
                    <x-champ name="commentaire" label="Commentaire"/>
                    <label class="flex items-center gap-2 text-sm"><input type="checkbox" name="realise" value="1" class="size-5" checked> Déjà réalisé</label>
                    <button class="btn-primaire w-full">Enregistrer</button>
                </form>
                <form method="POST" action="{{ route('ovins.soins.appliquer') }}" class="carte space-y-3 p-4">
                    @csrf
                    <h2 class="font-semibold text-stone-900">Appliquer un modèle à un lot</h2>
                    <input type="hidden" name="cible" value="lot">
                    <x-choix name="id_cible" label="Lot" :options="$lots->mapWithKeys(fn ($l) => [$l->id_lot => $l->nom_site.' · '.$l->nom_lot])->all()" vide="Choisir…" required id="app_lot"/>
                    <x-choix name="id_modele" label="Modèle" :options="$modeles->whereIn('cible', ['tous', 'lot'])->mapWithKeys(fn ($m) => [$m->id_modele => $m->nom_modele])->all()" vide="Choisir…" required id="app_modele"/>
                    <x-champ name="date_reference" label="Date de référence (J0)" type="date" :value="$t" required id="app_ref"/>
                    <button class="btn-secondaire w-full">Planifier</button>
                </form>
            </aside>
        @endcan
    </div>
</x-layouts.app>
