@php
    use App\Support\Format;
    $indispo = 'Indisponible';
    $val = fn ($k) => array_key_exists($k, $i) ? ($i[$k] === null ? $indispo : $i[$k]) : null;
    $actions = collect([
        ['soins_retard', 'Soins en retard', 'soins.index', 'alerte'],
        ['soins_jour', 'Soins du jour', 'soins.index', null],
        ['ramassages_a_valider', 'Ramassages à valider', 'ponte.index', null],
        ['ramassages_jour', 'Saisies de ponte du jour', 'ponte.index', null],
        ['oeufs_en_stock', 'Œufs à répartir', 'oeufs.index', null],
        ['file_chargement', 'Œufs à charger', 'incubation.create', null],
        ['incubations_en_cours', 'Incubations en cours', 'incubation.index', null],
        ['ventes_a_completer', 'Ventes à compléter', 'ventes.a_completer', 'alerte'],
        ['soins_ovins_retard', 'Soins ovins en retard', 'ovins.soins.index', 'alerte'],
        ['soins_ovins_jour', 'Soins ovins du jour', 'ovins.soins.index', null],
        ['agnelages_proches', 'Agnelages sous 30 jours', 'ovins.reproduction.index', null],
        ['ovins_en_ferme', 'Ovins en ferme', 'ovins.animaux.index', null],
        ['mes_taches_jour', 'Mes tâches du jour', 'activites.index', null],
        ['absences_a_valider', 'Absences à valider', 'rh.absences.index', 'alerte'],
        ['alertes_planning', 'Alertes de planning', 'organisation.planning.index', 'alerte'],
    ])->filter(fn ($a) => array_key_exists($a[0], $i));
@endphp
<x-layouts.app titre="Bonjour {{ auth()->user()->prenom ?: auth()->user()->nom }}" :sous-titre="ucfirst(now()->locale('fr')->isoFormat('dddd D MMMM YYYY'))">
    @if ($actions->isNotEmpty())
        <section class="mb-8">
            <h2 class="mb-3 text-sm font-semibold tracking-wide text-stone-500 uppercase">Aujourd’hui</h2>
            <div class="grid grid-cols-2 gap-3 sm:grid-cols-3 lg:grid-cols-4">
                @foreach ($actions as [$cle, $libelle, $route, $ton])
                    @php $v = $val($cle); @endphp
                    <a href="{{ route($route) }}" class="carte block p-4 transition hover:border-ferme-200">
                        <div class="text-xs font-medium text-stone-500">{{ $libelle }}</div>
                        <div class="mt-1 text-2xl font-bold tabular-nums {{ $ton === 'alerte' && is_int($v) && $v > 0 ? 'text-red-700' : 'text-stone-900' }}">{{ is_int($v) ? Format::nombre($v) : $v }}</div>
                    </a>
                @endforeach
            </div>
            @if (($i['ramassages_jour'] ?? null) === 0)
                <p class="mt-2 text-xs text-stone-500">Aucune saisie de ponte aujourd’hui ne signifie pas zéro œuf : la saisie n’a peut-être pas encore été faite.</p>
            @endif
        </section>
    @endif

    @php
        $finances = collect([
            ['ventes_mois', 'Ventes du mois', 'ventes.index', null],
            ['recettes_mois', 'Recettes du mois', 'tresorerie.index', null],
            ['reste_a_encaisser', 'Reste à encaisser', 'tresorerie.a_encaisser', 'alerte'],
        ])->filter(fn ($a) => array_key_exists($a[0], $i));
    @endphp
    @if ($finances->isNotEmpty())
        <section class="mb-8">
            <h2 class="mb-3 text-sm font-semibold tracking-wide text-stone-500 uppercase">Ventes et caisse</h2>
            <div class="grid grid-cols-1 gap-3 sm:grid-cols-3">
                @foreach ($finances as [$cle, $libelle, $route, $ton])
                    @php $v = $val($cle); @endphp
                    <a href="{{ route($route) }}" class="carte block p-4 transition hover:border-ferme-200">
                        <div class="text-xs font-medium text-stone-500">{{ $libelle }}</div>
                        <div class="mt-1 text-2xl font-bold tabular-nums {{ $ton === 'alerte' && is_int($v) && $v > 0 ? 'text-red-700' : 'text-stone-900' }}">{{ is_int($v) ? \App\Support\Montant::fcfa($v) : $v }}</div>
                    </a>
                @endforeach
            </div>
        </section>
    @endif

    @if (array_key_exists('eclosions_proches', $i))
        <section class="mb-8">
            <h2 class="mb-3 text-sm font-semibold tracking-wide text-stone-500 uppercase">Éclosions des 3 prochains jours</h2>
            @if ($i['eclosions_proches'] === null)
                <p class="text-sm text-stone-500">{{ $indispo }}</p>
            @elseif ($i['eclosions_proches']->isEmpty())
                <p class="text-sm text-stone-500">Aucune éclosion prévue.</p>
            @else
                <div class="grid gap-2 sm:grid-cols-2">
                    @foreach ($i['eclosions_proches'] as $e)
                        <a href="{{ route('incubation.show', $e->id) }}" class="carte flex items-center gap-3 p-3 hover:border-ferme-200">
                            <span class="grid size-10 shrink-0 place-items-center rounded-lg bg-paille-100 text-paille-700"><x-icone nom="sun" class="size-5"/></span>
                            <span class="min-w-0 flex-1"><span class="block truncate font-medium">{{ $e->nom_lot }}</span><span class="text-xs text-stone-500">{{ Format::dateLongue($e->date_eclosion_previsionnelle) }} · {{ $e->incubateur }}</span></span>
                            <span class="tabular-nums text-sm font-semibold">{{ $e->date_mirage ? $e->nombre_fertiles : $e->nombre_oeufs }} œufs</span>
                        </a>
                    @endforeach
                </div>
            @endif
            @if (($i['incubations_sans_prevision'] ?? 0) > 0)
                <p class="mt-2 text-xs text-paille-700">{{ $i['incubations_sans_prevision'] }} incubation(s) en cours sans date d’éclosion prévisionnelle.</p>
            @endif
        </section>
    @endif

    @if (array_key_exists('effectifs_volailles', $i) || array_key_exists('reproducteurs', $i))
        <section class="mb-8 grid gap-6 lg:grid-cols-2">
            @if (array_key_exists('effectifs_volailles', $i))
                <div>
                    <h2 class="mb-3 text-sm font-semibold tracking-wide text-stone-500 uppercase">Lots de volailles actifs</h2>
                    <div class="carte divide-y divide-stone-100">
                        @forelse ($i['effectifs_volailles'] ?? [] as $e)
                            <div class="flex justify-between px-4 py-3 text-sm"><span>{{ ucfirst((string) $e->espece) }} <span class="text-stone-500">· {{ $e->lots }} lot(s)</span></span><strong class="tabular-nums">{{ Format::nombre($e->total) }}</strong></div>
                        @empty
                            <p class="px-4 py-3 text-sm text-stone-500">{{ $i['effectifs_volailles'] === null ? $indispo : 'Aucun lot actif.' }}</p>
                        @endforelse
                    </div>
                </div>
            @endif
            @if (array_key_exists('reproducteurs', $i))
                <div>
                    <h2 class="mb-3 text-sm font-semibold tracking-wide text-stone-500 uppercase">Reproducteurs</h2>
                    <div class="carte divide-y divide-stone-100">
                        @forelse ($i['reproducteurs'] ?? [] as $r)
                            <div class="flex justify-between px-4 py-3 text-sm"><span>{{ ucfirst($r->espece) }}</span><span class="tabular-nums"><strong>{{ Format::nombre($r->femelles) }}</strong> femelles · <strong>{{ Format::nombre($r->males) }}</strong> mâles</span></div>
                        @empty
                            <p class="px-4 py-3 text-sm text-stone-500">{{ $i['reproducteurs'] === null ? $indispo : 'Aucune bande active.' }}</p>
                        @endforelse
                    </div>
                </div>
            @endif
        </section>
    @endif

    @if (! empty($i['soins_a_venir']) && $i['soins_a_venir']->isNotEmpty())
        <section class="mb-8">
            <h2 class="mb-3 text-sm font-semibold tracking-wide text-stone-500 uppercase">Soins des 7 prochains jours</h2>
            <div class="carte divide-y divide-stone-100">
                @foreach ($i['soins_a_venir'] as $s)
                    <a href="{{ route('lots.show', $s->id_lot) }}#soins" class="flex gap-3 px-4 py-2.5 text-sm hover:bg-stone-50"><span class="w-24 shrink-0 tabular-nums text-stone-500">{{ Format::date($s->date_prevue) }}</span><span class="flex-1 truncate">{{ $s->produit ?: $s->type }}</span><span class="truncate text-stone-500">{{ $s->nom_lot }}</span></a>
                @endforeach
            </div>
        </section>
    @endif

    <details class="group" @if ($actions->isEmpty()) open @endif>
        <summary class="mb-3 flex cursor-pointer list-none items-center gap-2 text-sm font-semibold tracking-wide text-stone-500 uppercase">
            Tous les modules <x-icone nom="chevron-right" class="size-4 transition group-open:rotate-90"/>
        </summary>
        @if ($lotLivre < 5)
        <div class="mb-6 rounded-xl border border-paille-500/30 bg-paille-50 px-4 py-3 text-sm text-paille-700">
            <strong>Lot {{ $lotLivre }} sur 5 livré.</strong> Les modules marqués « bientôt » restent à utiliser dans l’ancienne application : les deux partagent la même base de données.
        </div>
        @endif
        @foreach ($groupes as $groupe)
            <section class="mb-6">
                <h3 class="mb-2 text-xs font-semibold text-stone-400 uppercase">{{ $groupe['titre'] }}</h3>
                <div class="grid grid-cols-2 gap-3 sm:grid-cols-3 lg:grid-cols-4">
                    @foreach ($groupe['entrees'] as $e)
                        <a href="{{ $e['url'] }}" class="carte flex min-h-20 items-center gap-3 p-3 transition hover:border-ferme-200">
                            <span class="grid size-9 shrink-0 place-items-center rounded-lg {{ $e['disponible'] ? 'bg-ferme-50 text-ferme-700' : 'bg-stone-100 text-stone-400' }}"><x-icone :nom="$e['icone']" class="size-5"/></span>
                            <span class="min-w-0 text-sm font-medium leading-snug {{ $e['disponible'] ? 'text-stone-800' : 'text-stone-500' }}">{{ $e['titre'] }}@unless ($e['disponible'])<span class="block text-xs font-normal">bientôt</span>@endunless</span>
                        </a>
                    @endforeach
                </div>
            </section>
        @endforeach
    </details>
</x-layouts.app>
