@php use App\Support\Format; @endphp
<x-layouts.app :titre="$bande->nom_bande" :sous-titre="ucfirst($bande->espece).' · mise en place le '.Format::date($bande->date_mise_en_place)">
    <x-slot:actions>
        @can('REP.gerer')<a href="{{ route('reproducteurs.edit', $bande->id) }}" class="btn-secondaire">Modifier</a>@endcan
        @can('PON.voir')
            <a href="{{ route('ponte.courbe', ['bande' => $bande->id]) }}" class="btn-secondaire"><x-icone nom="chart" class="size-4"/>Courbe de ponte</a>
            <a href="{{ route('ponte.index', ['bande' => $bande->id]) }}" class="btn-secondaire"><x-icone nom="egg" class="size-4"/>Saisir la ponte</a>
        @endcan
    </x-slot:actions>

    @if ($bande->reformee)
        <div class="mb-5 rounded-lg border border-stone-200 bg-stone-100 px-4 py-3 text-sm text-stone-600">Bande réformée : elle ne reçoit plus de mouvement ni de saisie de ponte.</div>
    @endif

    <div class="grid gap-3 sm:grid-cols-3">
        <x-indicateur label="Femelles" :valeur="Format::nombre($bande->nb_femelles)"/>
        <x-indicateur label="Mâles" :valeur="Format::nombre($bande->nb_males)"/>
        <x-indicateur label="Dernier taux de ponte" :valeur="Format::pourcent(optional($ponte->first())->taux_ponte, 2)" :detail="$ponte->first() ? 'le '.Format::date($ponte->first()->date) : 'aucune saisie'"/>
    </div>

    <div class="mt-6 grid gap-6 lg:grid-cols-[1fr_22rem]">
        <section>
            <h2 class="mb-3 font-semibold text-stone-900">Historique des mouvements</h2>
            @if ($mouvements->isEmpty())
                <x-vide icone="clock">Aucun mouvement enregistré.</x-vide>
            @else
                <div class="carte divide-y divide-stone-100">
                    @foreach ($mouvements as $m)
                        <div class="flex items-center gap-3 px-4 py-3 text-sm">
                            <span class="w-20 shrink-0 tabular-nums text-stone-500">{{ Format::date($m->date_mouvement) }}</span>
                            <x-statut :ton="$m->type_mouvement === 'entrée' ? 'ok' : ($m->type_mouvement === 'mortalité' ? 'alerte' : 'neutre')">{{ ucfirst($m->type_mouvement) }}</x-statut>
                            <span class="font-semibold tabular-nums">{{ $m->type_mouvement === 'entrée' ? '+' : '−' }}{{ $m->quantite }}</span>
                            <span class="text-stone-500">{{ $m->sexe === 'mâle' ? 'mâles' : 'femelles' }}</span>
                            <span class="ml-auto hidden truncate text-xs text-stone-400 sm:block">{{ $m->commentaire }} · {{ $m->utilisateur }}</span>
                        </div>
                    @endforeach
                </div>
                <div class="mt-3">{{ $mouvements->links() }}</div>
            @endif
        </section>

        @can('REP.gerer')
            @unless ($bande->reformee)
                <section>
                    <h2 class="mb-3 font-semibold text-stone-900">Nouveau mouvement</h2>
                    <form method="POST" action="{{ route('reproducteurs.mouvement', $bande->id) }}" class="carte space-y-4 p-4">
                        @csrf
                        <div class="grid grid-cols-2 gap-3">
                            <x-choix name="type_mouvement" label="Type" :options="\App\Domaine\Reproducteurs::TYPES"/>
                            <x-choix name="sexe" label="Sexe" :options="\App\Domaine\Reproducteurs::SEXES" value="femelle" id="sexe-mvt"/>
                        </div>
                        <div class="grid grid-cols-2 gap-3">
                            <x-champ name="quantite" label="Quantité" type="number" min="1" required inputmode="numeric"/>
                            <x-champ name="date_mouvement" label="Date" type="date" :value="today()->toDateString()" :max="today()->toDateString()" required/>
                        </div>
                        @foreach ($origines as $sexe => $liste)
                            @if (count($liste) > 1)
                                <x-choix :name="$sexe === 'mâle' ? 'origine_male' : 'origine_femelle'" :label="'Emplacement de sortie ('.($sexe === 'mâle' ? 'mâles' : 'femelles').')'" :options="collect($liste)->mapWithKeys(fn ($o) => [$o['id'] => $o['nom'].' ('.$o['quantite'].')'])->all()" vide="—" aide="Pour un retrait ou une mortalité de ce sexe : ces sujets sont répartis sur plusieurs emplacements."/>
                            @endif
                        @endforeach
                        <x-champ name="commentaire" label="Motif" required maxlength="1000"/>
                        <button type="submit" class="btn-primaire w-full">Enregistrer le mouvement</button>
                    </form>
                </section>
            @endunless
        @endcan
    </div>
</x-layouts.app>
