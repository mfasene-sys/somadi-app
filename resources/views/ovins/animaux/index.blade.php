@php
    use App\Domaine\Ovins\Animaux; use App\Support\Format;
    $coche = fn ($k, $v) => in_array((string) $v, array_map('strval', (array) ($f[$k] ?? ($k === 'statuts' ? ['en_ferme'] : []))), true);
    $nbFiltres = collect(['sites', 'proprietaires', 'sexes', 'ages', 'repros'])->sum(fn ($k) => count((array) ($f[$k] ?? []))) + (! empty($f['lot']) ? 1 : 0) + (($f['statuts'] ?? ['en_ferme']) !== ['en_ferme'] ? 1 : 0);
@endphp
<x-layouts.app titre="Troupeau ovin" :sous-titre="Format::nombre($total).' animal(aux) · '.($parSexe['F'] ?? 0).' femelle(s), '.($parSexe['M'] ?? 0).' mâle(s)'">
    <x-slot:actions>
        @can('OVA.gerer')<a href="{{ route('ovins.animaux.create') }}" class="btn-primaire"><x-icone nom="plus" class="size-4"/> Nouvel animal</a>@endcan
    </x-slot:actions>
    @include('ovins._onglets')

    <form method="GET" class="carte mb-4 p-4">
        <div class="flex flex-wrap items-end gap-3">
            <x-champ name="q" label="Boucle, nom ou race" :value="$f['q'] ?? ''" class="min-w-48 flex-1"/>
            <x-choix name="lot" label="Lot" :options="$lots->mapWithKeys(fn ($l) => [$l->id_lot => $l->nom_site.' · '.$l->nom_lot])->all()" :value="$f['lot'] ?? null" vide="Tous" class="min-w-48 flex-1"/>
            <button class="btn-primaire">Rechercher</button>
            <a href="?reset=1" class="btn-secondaire">Réinitialiser</a>
        </div>
        <details class="mt-3" @if ($nbFiltres) open @endif>
            <summary class="cursor-pointer text-sm font-medium text-ferme-700">Filtres détaillés{{ $nbFiltres ? ' ('.$nbFiltres.')' : '' }}</summary>
            <div class="mt-3 grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                @foreach ([
                    'statuts' => ['Statut', config('somadi.ovins.statuts') + ['tous' => 'Tous']],
                    'sexes' => ['Sexe', ['F' => 'Femelles', 'M' => 'Mâles']],
                    'ages' => ['Âge', Animaux::AGES],
                    'repros' => ['Reproduction', Animaux::REPROS],
                    'sites' => ['Sites', $sites->mapWithKeys(fn ($s) => [$s->id_site => $s->nom_site])->all()],
                    'proprietaires' => ['Propriétaires', $proprietaires->mapWithKeys(fn ($p) => [$p->id_proprietaire => $p->nom_proprietaire])->all()],
                ] as $cle => [$titre, $options])
                    <fieldset>
                        <legend class="etiquette">{{ $titre }}</legend>
                        <div class="flex flex-wrap gap-1.5">
                            @foreach ($options as $v => $lib)
                                <label class="cursor-pointer"><input type="checkbox" name="{{ $cle }}[]" value="{{ $v }}" class="peer sr-only" @checked($coche($cle, $v))>
                                    <span class="block rounded-full border border-stone-300 px-3 py-1.5 text-xs font-medium text-stone-600 peer-checked:border-ferme-600 peer-checked:bg-ferme-50 peer-checked:text-ferme-800">{{ $lib }}</span></label>
                            @endforeach
                        </div>
                    </fieldset>
                @endforeach
            </div>
        </details>
    </form>

    @if ($animaux->isEmpty())
        <x-vide icone="tag">Aucun animal ne correspond.</x-vide>
    @else
        <div class="carte divide-y divide-stone-100">
            @foreach ($animaux as $a)
                @php($etat = Animaux::etatRepro($a))
                <a href="{{ route('ovins.animaux.show', $a->id_animal) }}" class="flex min-h-16 items-center gap-3 px-4 py-2.5 hover:bg-stone-50">
                    <span class="grid size-10 shrink-0 place-items-center rounded-full text-sm font-bold {{ $a->sexe === 'F' ? 'bg-rose-50 text-rose-700' : 'bg-sky-50 text-sky-700' }}">{{ $a->sexe === 'F' ? '♀' : '♂' }}</span>
                    <span class="min-w-0 flex-1">
                        <span class="block truncate font-medium text-stone-900">{{ $a->code_animal ?: '#'.$a->id_animal }}@if ($a->nom_animal) <span class="font-normal text-stone-500">· {{ $a->nom_animal }}</span>@endif</span>
                        <span class="block truncate text-xs text-stone-500">{{ $a->nom_site }}{{ $a->nom_lot ? ' · '.$a->nom_lot : '' }} · {{ Animaux::age($a->date_naissance) }}{{ $a->race ? ' · '.$a->race : '' }}</span>
                    </span>
                    @if ($a->nom_proprietaire)<span class="hidden items-center gap-1.5 text-xs text-stone-500 sm:flex"><span class="size-2.5 rounded-full" style="background: {{ $a->couleur_hex ?: '#a8a29e' }}"></span>{{ $a->nom_proprietaire }}</span>@endif
                    @if ($etat)<x-statut :ton="$etat === 'retard' ? 'alerte' : ($etat === 'a_agneler' ? 'attente' : 'info')">{{ Animaux::REPROS[$etat] }}</x-statut>@endif
                    @if ($a->est_reproducteur && ! $etat)<x-statut ton="info">Repro.</x-statut>@endif
                    @if ($a->statut !== 'en_ferme')@include('ovins._statut', ['statut' => $a->statut])@endif
                </a>
            @endforeach
        </div>
        <div class="mt-3">{{ $animaux->links() }}</div>
    @endif
</x-layouts.app>
