@php
    use App\Support\Format; use App\Domaine\Ovins\Inventaires;
    $ouvert = $inv->statut === 'ouvert';
    $peut = $ouvert && auth()->user()->can('OVI.gerer');
    $ctrl = old('controleur', session('ovins.controleur') ?: auth()->user()->nomComplet());
    $t = today()->toDateString();
    $restant = $synthese['attendu']['non_pointe'];
@endphp
<x-layouts.app :titre="$inv->titre" :sous-titre="$inv->perimetre.' · ouvert le '.Format::date(substr($inv->ouvert_le, 0, 10)).' par '.$inv->ouvert_par">
    <x-slot:actions>
        <x-statut :ton="$ouvert ? 'attente' : 'ok'" class="px-3 py-1 text-sm">{{ $ouvert ? 'Ouvert' : 'Clôturé' }}</x-statut>
        <a href="{{ route('ovins.inventaires.feuille', $inv->id) }}" class="btn-secondaire" target="_blank">Feuille de comptage (PDF)</a>
    </x-slot:actions>

    <div class="mb-4 grid grid-cols-2 gap-3 sm:grid-cols-5">
        <x-indicateur label="Attendus" :valeur="$inv->total_attendu"/>
        <x-indicateur label="Présents" :valeur="$synthese['attendu']['present']"/>
        <x-indicateur label="Anomalies" :valeur="$synthese['attendu']['anomalie'] + $synthese['supplementaire']['anomalie']" :ton="$synthese['attendu']['anomalie'] + $synthese['supplementaire']['anomalie'] ? 'alerte' : null"/>
        <x-indicateur label="Absents" :valeur="$synthese['attendu']['absent']" :ton="$synthese['attendu']['absent'] ? 'alerte' : null"/>
        <x-indicateur label="Restant à pointer" :valeur="$restant"/>
    </div>

    @if ($rapprochement->isNotEmpty())
        <section class="mb-4 rounded-lg border border-sky-200 bg-sky-50 p-4 text-sm text-sky-900">
            <h2 class="mb-2 font-semibold">Changements depuis l’ouverture</h2>
            <ul class="space-y-1">@foreach ($rapprochement as $m)<li>{{ Format::date($m->date_mvt) }} · ligne {{ $m->numero }} ({{ $m->code_animal }}) : {{ $m->type_mvt }} — {{ $m->commentaire }}</li>@endforeach</ul>
            <p class="mt-2 text-xs">La liste attendue reste celle de l’ouverture ; ces mouvements expliquent les écarts.</p>
        </section>
    @endif

    @if (! $ouvert)
        <section class="carte mb-4 p-4 text-sm"><h2 class="font-semibold">Bilan</h2><p class="mt-1 whitespace-pre-line">{{ $inv->bilan }}</p>
            <p class="mt-2 text-xs text-stone-500">Clôturé le {{ Format::date(substr($inv->cloture_le, 0, 10)) }} par {{ $inv->cloture_par }}. Les absents n’ont pas été transformés en sorties : à traiter sur la fiche de chaque animal si l’absence est confirmée.</p></section>
    @endif

    <div class="grid gap-6 lg:grid-cols-[1fr_20rem]">
        <div class="min-w-0">
            <form method="GET" class="mb-3 flex flex-wrap gap-2">
                <input name="q" value="{{ $f['q'] ?? '' }}" placeholder="Boucle ou nom" class="champ max-w-48">
                <select name="etat" class="champ max-w-44" onchange="this.form.submit()"><option value="">Tous les états</option>@foreach (Inventaires::POINTAGES as $k => [$lib]) <option value="{{ $k }}" @selected(($f['etat'] ?? '') === $k)>{{ $lib }}</option>@endforeach</select>
                <button class="btn-secondaire">Chercher</button>
            </form>

            @if ($peut)
                <form id="groupe" method="POST" action="{{ route('ovins.inventaires.groupe', $inv->id) }}">@csrf
                    <input type="hidden" name="controleur" value="{{ $ctrl }}" data-ctrl><input type="hidden" name="date_controle" value="{{ $t }}" data-date></form>
            @endif
            <div class="carte divide-y divide-stone-100">
                @foreach ($lignes as $l)
                    @php([$lib, $ton] = Inventaires::POINTAGES[$l->pointage] ?? [$l->pointage, 'neutre'])
                    <div id="ligne-{{ $l->id }}" class="px-3 py-2.5 text-sm {{ $l->nature === 'supplementaire' ? 'bg-paille-50/50' : '' }}">
                        <div class="flex items-center gap-3">
                            @if ($peut && $l->nature === 'attendu' && $l->pointage === 'non_pointe')
                                <input type="checkbox" form="groupe" name="lignes[{{ $l->id }}]" value="{{ $l->version }}" class="size-6 shrink-0" aria-label="Vu : {{ $l->code_animal }}" onchange="majGroupe()">
                            @else <span class="w-6 shrink-0 text-center text-xs text-stone-400">{{ $l->numero }}</span> @endif
                            <span class="min-w-0 flex-1"><strong>{{ $l->code_animal }}</strong> <span class="text-stone-500">{{ $l->nom_animal }}</span>
                                <span class="block text-xs text-stone-500">{{ $l->nature === 'supplementaire' ? 'Observation supplémentaire' : trim($l->nom_site.' · '.$l->nom_lot, ' ·') }}{{ $l->sexe ? ' · '.$l->sexe : '' }}
                                    @if ($l->pointage !== 'non_pointe') · {{ $l->controleur }} le {{ Format::date($l->date_controle) }}@endif</span>
                                @if ($l->commentaire)<span class="block text-xs text-stone-600">{{ $l->commentaire }}</span>@endif</span>
                            <x-statut :ton="$ton">{{ $lib }}</x-statut>
                        </div>
                        @if ($peut)
                            <details class="ml-9 mt-1">
                                <summary class="cursor-pointer text-xs font-medium text-ferme-700">Pointer</summary>
                                <form method="POST" action="{{ route('ovins.inventaires.pointer', [$inv->id, $l->id]) }}" class="mt-2 grid gap-2 sm:grid-cols-2">@csrf
                                    <input type="hidden" name="version" value="{{ $l->version }}">
                                    <select name="pointage" class="champ" required>
                                        @foreach (Inventaires::POINTAGES as $k => [$lb])
                                            @if (($l->nature === 'attendu' && $k !== 'ecarte') || ($l->nature === 'supplementaire' && in_array($k, ['anomalie', 'ecarte', 'present'])))
                                                <option value="{{ $k }}" @selected($l->pointage === $k || ($l->pointage === 'non_pointe' && $k === 'present'))>{{ $lb }}</option>
                                            @endif
                                        @endforeach
                                    </select>
                                    <input name="controleur" value="{{ $ctrl }}" placeholder="Contrôleur" class="champ" maxlength="100">
                                    <input type="date" name="date_controle" value="{{ $t }}" min="{{ substr($inv->ouvert_le, 0, 10) }}" max="{{ $t }}" class="champ">
                                    <input name="site_constate" value="{{ $l->site_constate }}" placeholder="Site constaté" class="champ" maxlength="150">
                                    <input name="commentaire" value="{{ $l->commentaire }}" placeholder="Commentaire (obligatoire si anomalie ou absent)" class="champ sm:col-span-2" maxlength="2000">
                                    <button class="btn-secondaire min-h-9 sm:col-span-2">Enregistrer</button>
                                </form>
                            </details>
                        @endif
                    </div>
                @endforeach
            </div>
            <div class="mt-3">{{ $lignes->links() }}</div>
        </div>

        <aside class="space-y-4">
            @if ($peut)
                <div class="carte sticky top-4 space-y-3 p-4">
                    <h2 class="font-semibold text-stone-900">Pointage rapide</h2>
                    <x-champ name="ctrl_rapide" label="Contrôleur" :value="$ctrl" oninput="document.querySelector('[data-ctrl]').value = this.value"/>
                    <p class="text-sm"><strong id="nb-coches">0</strong> animal(aux) coché(s) <span class="text-xs text-stone-500">(50 au plus)</span></p>
                    <button form="groupe" class="btn-primaire w-full">Marquer présents</button>
                </div>
                <form method="POST" action="{{ route('ovins.inventaires.ajouter', $inv->id) }}" class="carte space-y-3 p-4">@csrf <x-cle/>
                    <h2 class="font-semibold text-stone-900">Animal non attendu</h2>
                    <x-champ name="code_animal" label="Numéro de boucle vu" required maxlength="100"/>
                    <x-champ name="site_constate" label="Où ?" required maxlength="150" id="site_supp"/>
                    <x-champ name="commentaire" label="Commentaire" required id="com_supp"/>
                    <input type="hidden" name="controleur" value="{{ $ctrl }}"><input type="hidden" name="date_controle" value="{{ $t }}">
                    <button class="btn-secondaire w-full">Ajouter</button>
                    <p class="text-xs text-stone-500">Aucune fiche n’est créée : l’observation reste à identifier.</p>
                </form>
            @endif
            @if ($ouvert)
                @can('OVI.cloturer')
                    <form method="POST" action="{{ route('ovins.inventaires.cloturer', $inv->id) }}" class="carte space-y-3 p-4">@csrf
                        <input type="hidden" name="version" value="{{ $inv->version }}">
                        <h2 class="font-semibold text-stone-900">Clôturer</h2>
                        @if ($restant)<p class="text-sm text-red-700">{{ $restant }} ligne(s) restent à pointer.</p>@endif
                        <x-champ name="bilan" label="Bilan" type="textarea" required minlength="10"/>
                        <label class="flex items-start gap-2 text-sm"><input type="checkbox" name="confirmation" value="1" required class="mt-0.5 size-5"> Je clôture cet inventaire.</label>
                        <button class="btn-primaire w-full" @disabled($restant)>Clôturer</button>
                    </form>
                @endcan
            @endif
            <section>
                <h2 class="mb-2 text-sm font-semibold text-stone-900">Journal</h2>
                <ul class="carte divide-y divide-stone-100 text-xs">
                    @foreach ($journal as $j)<li class="px-3 py-2"><span class="text-stone-500">{{ Format::date(substr($j->cree_le, 0, 10)) }} {{ substr($j->cree_le, 11, 5) }} · {{ $j->auteur }}</span><span class="block">{{ $j->resume }}</span></li>@endforeach
                </ul>
            </section>
        </aside>
    </div>
    @if ($peut)
        {{-- Téléphone : barre d'action au-dessus de la navigation du bas dès qu'un animal est coché. --}}
        <div id="barre-groupe" hidden class="fixed inset-x-0 z-30 border-t border-stone-200 bg-white/95 px-4 py-2 backdrop-blur lg:hidden" style="bottom: calc(3.75rem + env(safe-area-inset-bottom))">
            <button form="groupe" class="btn-primaire w-full">Marquer présents (<span data-nb>0</span>)</button>
        </div>
    @endif
    <script>function majGroupe(){var n=document.querySelectorAll('input[form=groupe]:checked').length;document.getElementById('nb-coches').textContent=n;var b=document.getElementById('barre-groupe');if(b){b.hidden=n===0;b.querySelector('[data-nb]').textContent=n;}}</script>
</x-layouts.app>
