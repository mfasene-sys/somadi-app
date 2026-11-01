@php use App\Support\Format; $e = $edition; $verrou = $e && \App\Domaine\Ovins\Reproduction::naissancesLiees($e->id_repro) > 0; @endphp
<x-layouts.app :titre="'Reproduction de '.($a->code_animal ?: '#'.$a->id_animal)" :sous-titre="($a->nom_animal ? $a->nom_animal.' · ' : '').\App\Domaine\Ovins\Animaux::age($a->date_naissance).($a->nom_lot ? ' · '.$a->nom_lot : '')">
    <x-slot:actions><a href="{{ route('ovins.animaux.show', $a->id_animal) }}" class="btn-secondaire">Fiche</a></x-slot:actions>

    <div class="grid gap-6 lg:grid-cols-[1fr_24rem]">
        <section class="min-w-0">
            <h2 class="mb-3 font-semibold text-stone-900">Historique</h2>
            @if ($historique->isEmpty())
                <x-vide icone="heart">Aucune saillie enregistrée.</x-vide>
            @else
                <div class="space-y-3">
                    @foreach ($historique as $r)
                        <div class="carte p-4 text-sm {{ $e && $e->id_repro === $r->id_repro ? 'ring-2 ring-ferme-500' : '' }}">
                            <div class="flex flex-wrap items-center gap-2">
                                <strong>Saillie {{ Format::date($r->date_saillie) }}</strong>{{ $r->type_saillie ? ' · '.$r->type_saillie : '' }}
                                <span class="text-stone-500">· père {{ $r->code_pere ?: 'inconnu' }}</span>
                                @can('OVR.gerer')<a href="?repro={{ $r->id_repro }}#formulaire" class="ml-auto text-xs font-semibold text-ferme-700">Compléter</a>@endcan
                            </div>
                            @if ($r->date_agnelage_reel)
                                <p class="mt-1">Agnelage le {{ Format::date($r->date_agnelage_reel) }} : <strong>{{ $r->nb_agneaux_vivants }}</strong> vivant(s) ({{ (int) $r->nb_agneaux_males }} M, {{ (int) $r->nb_agneaux_femelles }} F), {{ (int) $r->nb_agneaux_morts }} mort(s){{ $r->difficulte_agnelage ? ' · agnelage difficile' : '' }}
                                    @if ($r->naissances)<span class="text-ferme-700"> · {{ $r->naissances }} fiche(s) créée(s)</span>@endif</p>
                            @else
                                <p class="mt-1 {{ $r->date_agnelage_prevu && $r->date_agnelage_prevu < today()->toDateString() ? 'text-red-700' : 'text-stone-600' }}">Agnelage prévu le {{ Format::date($r->date_agnelage_prevu) }}</p>
                            @endif
                            @if ($r->commentaire)<p class="mt-1 text-stone-500">{{ $r->commentaire }}</p>@endif
                        </div>
                    @endforeach
                </div>
            @endif
        </section>

        @can('OVR.gerer')
            @if ($e || $a->statut === 'en_ferme')
                <form id="formulaire" method="POST" action="{{ route('ovins.reproduction.enregistrer', $e?->id_repro) }}" class="carte h-fit space-y-3 p-4">
                    @csrf <x-cle/> <input type="hidden" name="id_mere" value="{{ $a->id_animal }}">
                    <h2 class="font-semibold text-stone-900">{{ $e ? 'Compléter la reproduction' : 'Nouvelle saillie' }}</h2>
                    @if ($verrou)<p class="rounded bg-stone-100 px-3 py-2 text-xs text-stone-600">Des agneaux ont été créés : le père, la date réelle et les effectifs sont figés.</p>@endif
                    <x-choix name="id_pere" label="Père" :options="$peres->mapWithKeys(fn ($p) => [$p->id_animal => ($p->code_animal ?: '#'.$p->id_animal).($p->nom_animal ? ' · '.$p->nom_animal : '').($p->statut !== 'en_ferme' ? ' (sorti)' : '')])->all()" :value="$e?->id_pere" vide="Inconnu"/>
                    <div class="grid grid-cols-2 gap-3">
                        <x-champ name="date_saillie" label="Saillie" type="date" :value="$e?->date_saillie" :max="today()->toDateString()"/>
                        <x-champ name="type_saillie" label="Type" :value="$e?->type_saillie" list="types-saillie" maxlength="50"/>
                    </div>
                    <datalist id="types-saillie"><option value="Monte naturelle"><option value="Lutte en lot"><option value="Insémination"></datalist>
                    <x-champ name="date_agnelage_prevu" label="Agnelage prévu" type="date" :value="$e?->date_agnelage_prevu" aide="Vide : saillie + 150 jours."/>
                    <fieldset class="space-y-3 rounded-lg border border-stone-200 p-3">
                        <legend class="px-1 text-sm font-semibold">Agnelage réel</legend>
                        <x-champ name="date_agnelage_reel" label="Date" type="date" :value="$e?->date_agnelage_reel" :max="today()->toDateString()"/>
                        <div class="grid grid-cols-2 gap-3">
                            <x-champ name="nb_agneaux_males" label="Mâles vivants" type="number" min="0" max="100" :value="$e?->nb_agneaux_males" oninput="majVivants()"/>
                            <x-champ name="nb_agneaux_femelles" label="Femelles vivantes" type="number" min="0" max="100" :value="$e?->nb_agneaux_femelles" oninput="majVivants()"/>
                            <x-champ name="nb_agneaux_vivants" label="Total vivants" type="number" min="0" max="100" :value="$e?->nb_agneaux_vivants"/>
                            <x-champ name="nb_agneaux_morts" label="Morts" type="number" min="0" max="100" :value="$e?->nb_agneaux_morts"/>
                        </div>
                        <x-choix name="id_lot_agneaux" label="Lot d’accueil des agneaux" :options="$lots->mapWithKeys(fn ($l) => [$l->id_lot => $l->nom_lot])->all()" :value="$a->id_lot" vide="Sans lot"/>
                        <label class="flex items-center gap-2 text-sm"><input type="checkbox" name="difficulte_agnelage" value="1" class="size-5" @checked(old('difficulte_agnelage', $e?->difficulte_agnelage))> Agnelage difficile</label>
                        <p class="text-xs text-stone-500">Une fiche est créée pour chaque agneau vivant, avec sa mère et son père, une seule fois.</p>
                    </fieldset>
                    <x-champ name="commentaire" label="Commentaire" type="textarea" :value="$e?->commentaire"/>
                    <button class="btn-primaire w-full">Enregistrer</button>
                    @if ($e)<a href="{{ route('ovins.reproduction.femelle', $a->id_animal) }}" class="btn-secondaire w-full">Nouvelle saillie plutôt</a>@endif
                </form>
                <script>function majVivants(){var m=+document.getElementById('nb_agneaux_males').value||0,f=+document.getElementById('nb_agneaux_femelles').value||0;document.getElementById('nb_agneaux_vivants').value=m+f;}</script>
            @endif
        @endcan
    </div>
</x-layouts.app>
