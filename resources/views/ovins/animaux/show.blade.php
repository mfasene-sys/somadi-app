@php
    use App\Domaine\Ovins\Animaux; use App\Domaine\Ovins\Medias; use App\Domaine\Ovins\Soins; use App\Support\Format; use App\Support\Montant; use App\Support\Empreinte;
    $enFerme = $a->statut === 'en_ferme' && ! $a->date_sortie;
    $code = $a->code_animal ?: '#'.$a->id_animal;
    $photo = Medias::photo($a->id_animal);
@endphp
<x-layouts.app :titre="$code.($a->nom_animal ? ' · '.$a->nom_animal : '')" :sous-titre="($a->sexe === 'F' ? 'Femelle' : 'Mâle').($a->race ? ' · '.$a->race : '').' · '.Animaux::age($a->date_naissance)">
    <x-slot:actions>
        @include('ovins._statut', ['statut' => $a->statut])
        @if ($a->est_reproducteur)<x-statut ton="info">Reproducteur</x-statut>@endif
        <a href="{{ route('ovins.animaux.arbre', $a->id_animal) }}" class="btn-secondaire">Généalogie</a>
        @can('OVA.gerer')<a href="{{ route('ovins.animaux.edit', $a->id_animal) }}" class="btn-secondaire">Modifier</a>@endcan
    </x-slot:actions>

    <div class="grid gap-6 lg:grid-cols-[1fr_22rem]">
        <div class="min-w-0 space-y-6">
            <section class="carte flex flex-col gap-4 p-4 sm:flex-row">
                @if ($photo)
                    <img src="{{ route('ovins.medias.show', $photo) }}" alt="Photo de {{ $code }}" class="h-40 w-full rounded-lg object-cover sm:w-40" loading="lazy">
                @endif
                <dl class="grid flex-1 grid-cols-[8rem_1fr] gap-y-1.5 text-sm">
                    <dt class="text-stone-500">Site · lot</dt><dd>{{ $a->nom_site }}{{ $a->nom_lot ? ' · ' : '' }}@if ($a->id_lot)<a href="{{ route('ovins.lots.show', $a->id_lot) }}" class="text-ferme-700 hover:underline">{{ $a->nom_lot }}</a>@endif</dd>
                    <dt class="text-stone-500">Propriétaire</dt><dd class="flex items-center gap-1.5">@if ($a->nom_proprietaire)<span class="size-2.5 rounded-full" style="background: {{ $a->couleur_hex ?: '#a8a29e' }}"></span>{{ $a->nom_proprietaire }} {{ $a->tel_proprietaire }}@else — @endif</dd>
                    <dt class="text-stone-500">Naissance</dt><dd>{{ Format::date($a->date_naissance) }}</dd>
                    <dt class="text-stone-500">Entrée</dt><dd>{{ Format::date($a->date_entree) }}{{ $a->origine ? ' · '.$a->origine : '' }}</dd>
                    <dt class="text-stone-500">Mère · père</dt><dd>
                        @if ($a->id_mere)<a href="{{ route('ovins.animaux.show', $a->id_mere) }}" class="text-ferme-700 hover:underline">{{ $a->code_mere ?: '#'.$a->id_mere }}</a>@else ? @endif ·
                        @if ($a->id_pere)<a href="{{ route('ovins.animaux.show', $a->id_pere) }}" class="text-ferme-700 hover:underline">{{ $a->code_pere ?: '#'.$a->id_pere }}</a>@else ? @endif</dd>
                    <dt class="text-stone-500">Poids</dt><dd>{{ $a->poids_naissance ? Format::nombre($a->poids_naissance, 1).' kg à la naissance' : '' }}{{ $a->poids_naissance && $a->poids_dernier ? ' · ' : '' }}{{ $a->poids_dernier ? Format::nombre($a->poids_dernier, 1).' kg (dernier)' : '' }}{{ ! $a->poids_naissance && ! $a->poids_dernier ? '—' : '' }}</dd>
                    @if ($a->prix_achat)<dt class="text-stone-500">Prix d’achat</dt><dd>{{ Montant::fcfa(Montant::base($a->prix_achat)) }}</dd>@endif
                    @unless ($enFerme)<dt class="text-stone-500">Sortie</dt><dd>{{ Format::date($a->date_sortie) }} · {{ $a->motif_sortie }}{{ $a->prix_vente ? ' · '.Montant::fcfa(Montant::base($a->prix_vente)) : '' }}
                        @if ($sortie?->id_vente)@can('VEN.voir') · <a href="{{ route('ventes.show', $sortie->id_vente) }}" class="text-ferme-700 hover:underline">vente n° {{ $sortie->id_vente }}</a>@endcan @endif</dd>@endunless
                </dl>
            </section>
            @if ($a->observations)<p class="carte whitespace-pre-line p-4 text-sm text-stone-700">{{ $a->observations }}</p>@endif

            @if ($enfants->isNotEmpty())
                <section>
                    <h2 class="mb-3 font-semibold text-stone-900">Descendance <span class="font-normal text-stone-500">· {{ $enfants->count() }}</span></h2>
                    <div class="flex flex-wrap gap-2">
                        @foreach ($enfants as $e)
                            <a href="{{ route('ovins.animaux.show', $e->id_animal) }}" class="carte px-3 py-2 text-sm hover:border-ferme-200">{{ $e->sexe === 'F' ? '♀' : '♂' }} {{ $e->code_animal ?: '#'.$e->id_animal }} <span class="text-xs text-stone-500">{{ Format::date($e->date_naissance) }}</span></a>
                        @endforeach
                    </div>
                </section>
            @endif

            @if ($a->sexe === 'F')
                <section>
                    <div class="mb-3 flex items-center gap-3"><h2 class="flex-1 font-semibold text-stone-900">Reproduction</h2>
                        @can('OVR.voir')<a href="{{ route('ovins.reproduction.femelle', $a->id_animal) }}" class="text-sm font-medium text-ferme-700 hover:underline">{{ auth()->user()->can('OVR.gerer') && $enFerme ? 'Saisir une saillie ou un agnelage' : 'Détail' }}</a>@endcan</div>
                    @if ($repro->isEmpty())
                        <p class="carte px-4 py-3 text-sm text-stone-500">Aucune saillie enregistrée.</p>
                    @else
                        <div class="carte divide-y divide-stone-100 text-sm">
                            @foreach ($repro->take(5) as $r)
                                <div class="flex flex-wrap gap-x-3 px-4 py-2.5"><span class="tabular-nums text-stone-500">{{ Format::date($r->date_saillie) }}</span>
                                    <span class="flex-1">{{ $r->date_agnelage_reel ? 'Agnelage le '.Format::date($r->date_agnelage_reel).' : '.$r->nb_agneaux_vivants.' vivant(s)'.($r->nb_agneaux_morts ? ', '.$r->nb_agneaux_morts.' mort(s)' : '') : 'Agnelage prévu le '.Format::date($r->date_agnelage_prevu) }}</span>
                                    <span class="text-stone-500">{{ $r->code_pere ? 'père '.$r->code_pere : '' }}</span></div>
                            @endforeach
                        </div>
                    @endif
                </section>
            @endif

            <section id="medias">
                <h2 class="mb-3 font-semibold text-stone-900">Photos et vidéos <span class="font-normal text-stone-500">· {{ $medias->count() }}</span></h2>
                @if ($medias->isNotEmpty())
                    <div class="grid grid-cols-2 gap-3 sm:grid-cols-3">
                        @foreach ($medias as $m)
                            <figure class="carte overflow-hidden">
                                @if ($m->type_media === 'image')
                                    <a href="{{ route('ovins.medias.show', $m->id_media) }}" target="_blank"><img src="{{ route('ovins.medias.show', $m->id_media) }}" alt="{{ $m->description ?: $code }}" class="aspect-square w-full object-cover" loading="lazy"></a>
                                @else
                                    <video src="{{ route('ovins.medias.show', $m->id_media) }}" controls preload="none" class="aspect-square w-full bg-stone-900 object-contain"></video>
                                @endif
                                <figcaption class="flex items-start gap-2 p-2 text-xs text-stone-600"><span class="flex-1">{{ Medias::STADES[$m->stade_age] ?? $m->stade_age }}{{ $m->description ? ' · '.$m->description : '' }}</span>
                                    @can('OVA.gerer')
                                        <form method="POST" action="{{ route('ovins.animaux.medias.supprimer', [$a->id_animal, $m->id_media]) }}" onsubmit="return confirm('Supprimer ce média ?')">@csrf<button class="text-red-700" aria-label="Supprimer"><x-icone nom="x" class="size-4"/></button></form>
                                    @endcan</figcaption>
                            </figure>
                        @endforeach
                    </div>
                @endif
                @can('OVA.gerer')
                    <form method="POST" action="{{ route('ovins.animaux.medias', $a->id_animal) }}" enctype="multipart/form-data" class="carte mt-3 grid gap-3 p-4 sm:grid-cols-[1fr_10rem_1fr_auto] sm:items-end">
                        @csrf
                        <div><label class="etiquette" for="fichiers">Fichiers</label><input id="fichiers" type="file" name="fichiers[]" multiple accept="image/jpeg,image/png,image/webp,image/gif,video/mp4,video/webm,video/ogg,video/quicktime" capture="environment" class="block w-full text-sm" required></div>
                        <x-choix name="stade_age" label="Stade" :options="Medias::STADES" :value="\App\Domaine\Ovins\Animaux::categorieAge($a->date_naissance) === 'adulte' ? 'adulte' : (\App\Domaine\Ovins\Animaux::categorieAge($a->date_naissance) === 'jeune' ? 'adolescence' : 'naissance')"/>
                        <x-champ name="description" label="Description" maxlength="255"/>
                        <button class="btn-primaire">Ajouter</button>
                        <p class="text-xs text-stone-500 sm:col-span-4">Images JPEG, PNG, WebP ou GIF jusqu’à 5 Mo ; vidéos MP4, WebM, Ogg ou MOV jusqu’à 50 Mo.</p>
                    </form>
                @endcan
            </section>

            <section>
                <h2 class="mb-3 font-semibold text-stone-900">Soins individuels</h2>
                @if ($soins->isEmpty())
                    <p class="carte px-4 py-3 text-sm text-stone-500">Aucun soin individuel. Les soins du lot figurent sur la fiche du lot.</p>
                @else
                    <div class="carte divide-y divide-stone-100 text-sm">
                        @foreach ($soins as $s)
                            <div class="flex flex-wrap items-center gap-3 px-4 py-2.5"><span class="w-20 tabular-nums text-stone-500">{{ Format::date($s->date_soin) }}</span><span class="flex-1">{{ $s->nom_soin ?: $s->produit }}{{ $s->dose ? ' · '.$s->dose : '' }}</span>
                                <x-statut :ton="Soins::STATUTS[$s->statut][1] ?? 'neutre'">{{ Soins::STATUTS[$s->statut][0] ?? $s->statut }}</x-statut></div>
                        @endforeach
                    </div>
                @endif
            </section>

            <section>
                <h2 class="mb-3 font-semibold text-stone-900">Chronologie</h2>
                <ol class="carte divide-y divide-stone-100 text-sm">
                    @forelse ($chronologie as $e)
                        <li class="flex gap-3 px-4 py-2.5"><span class="w-20 shrink-0 tabular-nums text-stone-500">{{ Format::date($e['date']) }}</span>
                            <span class="flex-1">{{ $e['titre'] }}@if ($e['detail'])<span class="block text-xs text-stone-500">{{ $e['detail'] }}</span>@endif</span>
                            <span class="hidden text-xs text-stone-400 sm:block">{{ $e['auteur'] }}</span></li>
                    @empty <li class="px-4 py-3 text-stone-500">Aucun événement.</li> @endforelse
                </ol>
            </section>
        </div>

        @if ($enFerme)
            <aside class="space-y-4">
                @can('OVM.gerer')
                    <details class="carte p-4" @if ($errors->hasAny(['id_lot', 'date'])) open @endif>
                        <summary class="cursor-pointer font-semibold text-stone-900">Transférer</summary>
                        <form method="POST" action="{{ route('ovins.animaux.transfert', $a->id_animal) }}" class="mt-3 space-y-3">
                            @csrf
                            <x-choix name="id_lot" label="Lot de destination" :options="$lots->where('id_lot', '<>', $a->id_lot)->mapWithKeys(fn ($l) => [$l->id_lot => $l->nom_site.' · '.$l->nom_lot.' ('.\App\Domaine\Ovins\Referentiels::libelleType($l->type_lot).')'])->all()" vide="Choisir…" required/>
                            <x-champ name="date" label="Date" type="date" :value="today()->toDateString()" :max="today()->toDateString()" required/>
                            @if ($a->sexe === 'F' && ! $a->est_reproducteur)
                                <label class="flex items-start gap-2 text-sm"><input type="checkbox" name="integration" value="1" class="mt-0.5 size-5"> Intégration en reproduction (lot de reproductrices)</label>
                                <x-choix name="id_modele" label="Modèle de soins d’entrée" :options="$modeles->mapWithKeys(fn ($m) => [$m->id_modele => $m->nom_modele])->all()" vide="Aucun"/>
                            @endif
                            <x-champ name="commentaire" label="Commentaire" maxlength="500"/>
                            <button class="btn-primaire w-full">Transférer</button>
                        </form>
                    </details>
                    <details class="carte p-4" @if ($errors->hasAny(['type_sortie', 'date_sortie', 'prix', 'client_nom', 'client_contact'])) open @endif>
                        <summary class="cursor-pointer font-semibold text-stone-900">Sortie (vente, mortalité, réforme)</summary>
                        <form method="POST" action="{{ route('ovins.animaux.sortie', $a->id_animal) }}" class="mt-3 space-y-3" onsubmit="return confirm('Enregistrer la sortie de {{ $code }} ? Elle est définitive.')">
                            @csrf <x-cle/>
                            <x-choix name="type_sortie" label="Type" :options="collect(config('somadi.ovins.sorties'))->map(fn ($t) => $t[0])->when(! auth()->user()->can('VEN.gerer'), fn ($c) => $c->except('vente'))->all()" required
                                     onchange="document.getElementById('bloc-vente').hidden = this.value !== 'vente'"/>
                            <x-champ name="date_sortie" label="Date" type="date" :value="today()->toDateString()" :max="today()->toDateString()" required/>
                            <div id="bloc-vente" class="space-y-3" @if (old('type_sortie', 'vente') !== 'vente' || ! auth()->user()->can('VEN.gerer')) hidden @endif>
                                <x-champ name="prix" label="Prix de vente" suffixe="FCFA" inputmode="decimal"/>
                                <x-champ name="client_nom" label="Client" maxlength="100"/>
                                <x-champ name="client_contact" label="Téléphone du client" type="tel" inputmode="tel" maxlength="20"/>
                                <p class="text-xs text-stone-500">La vente est inscrite au registre des ventes, à encaisser.</p>
                            </div>
                            <x-champ name="motif" label="Motif" maxlength="100"/>
                            <x-champ name="commentaire" label="Commentaire" type="textarea"/>
                            <button class="btn-primaire w-full">Enregistrer la sortie</button>
                        </form>
                    </details>
                @endcan
                @can('OVS.gerer')
                    <details class="carte p-4">
                        <summary class="cursor-pointer font-semibold text-stone-900">Appliquer un modèle de soins</summary>
                        <form method="POST" action="{{ route('ovins.soins.appliquer') }}" class="mt-3 space-y-3">
                            @csrf <input type="hidden" name="cible" value="animal"><input type="hidden" name="id_cible" value="{{ $a->id_animal }}">
                            <x-choix name="id_modele" label="Modèle" :options="$modeles->mapWithKeys(fn ($m) => [$m->id_modele => $m->nom_modele])->all()" vide="Choisir…" required id="modele_animal"/>
                            <x-champ name="date_reference" label="Date de référence (J0)" type="date" :value="today()->toDateString()" required id="ref_animal"/>
                            <button class="btn-secondaire w-full">Planifier les soins</button>
                        </form>
                    </details>
                @endcan
            </aside>
        @endif
    </div>
</x-layouts.app>
