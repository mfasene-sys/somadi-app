@php use App\Support\Format; use App\Domaine\Ponte; use App\Support\Empreinte; @endphp
<x-layouts.app titre="Ponte" sous-titre="Saisie des ramassages, validation et historique">
    <x-onglets :liens="['Saisie et validation' => route('ponte.index'), 'Courbe de ponte' => route('ponte.courbe', $bande ? ['bande' => $bande->id] : [])]"/>
    <div class="grid gap-6 lg:grid-cols-[24rem_1fr]">
        {{-- Saisie --}}
        <section>
            <h2 class="mb-3 font-semibold text-stone-900">Saisir un ramassage</h2>
            <form method="GET" action="{{ route('ponte.index') }}" class="carte mb-3 grid grid-cols-2 gap-3 p-4">
                <x-choix name="bande" label="Bande" :options="$bandes->pluck('nom_bande', 'id')->all()" :value="$bande->id ?? null" vide="Choisir…" class="col-span-2" onchange="this.form.submit()"/>
                <x-champ name="date" label="Date" type="date" :value="$date" :max="today()->toDateString()" class="col-span-2" onchange="this.form.submit()"/>
                <noscript><button class="btn-secondaire col-span-2">Afficher</button></noscript>
            </form>

            @if ($bande)
                @can('PON.gerer')
                    @php $modifiable = ! $doublon && (! $existante || Ponte::etat($existante) === 'attente'); @endphp
                    @if ($doublon)
                        <div class="carte border-red-200 bg-red-50 p-4 text-sm text-red-800">Plusieurs ramassages existent pour cette bande et cette date. Aucune ligne n’a été supprimée : faites vérifier l’historique.</div>
                    @elseif (! $modifiable)
                        <div class="carte p-4 text-sm text-stone-600">Le ramassage du {{ Format::date($date) }} est déjà validé : il ne peut plus être modifié ici.</div>
                    @else
                        <form method="POST" action="{{ route('ponte.store') }}" class="carte space-y-4 p-4">
                            @csrf
                            <input type="hidden" name="id_bande" value="{{ $bande->id }}">
                            <input type="hidden" name="date" value="{{ $date }}">
                            @if ($existante)<input type="hidden" name="empreinte" value="{{ Empreinte::de($existante) }}">@endif
                            <p class="text-sm text-stone-600"><strong>{{ Format::nombre($bande->nb_femelles) }}</strong> femelles dans la bande{{ $existante ? ' · saisie existante, modifiable jusqu’à sa validation' : '' }}.</p>
                            <div class="grid grid-cols-2 gap-3">
                                <x-champ name="ramassage_matin" label="Matin" type="number" min="0" :value="$existante->ramassage_matin ?? null" required inputmode="numeric"/>
                                <x-champ name="ramassage_apres_midi" label="Après-midi" type="number" min="0" :value="$existante->ramassage_apres_midi ?? null" required inputmode="numeric"/>
                            </div>
                            <label class="flex items-center gap-3 text-sm text-stone-700">
                                <input type="hidden" name="temoin" value="0">
                                <input type="checkbox" name="temoin" value="1" @checked(old('temoin', $existante->temoin ?? false)) class="size-5"> Ramassage témoin
                            </label>
                            <button type="submit" class="btn-primaire w-full">{{ $existante ? 'Mettre à jour' : 'Enregistrer' }}</button>
                        </form>
                    @endif
                @endcan
            @else
                <p class="text-sm text-stone-500">Choisissez une bande active pour saisir le ramassage.</p>
            @endif
        </section>

        <div class="space-y-6">
            {{-- À valider --}}
            <section>
                <h2 class="mb-3 font-semibold text-stone-900">À valider <span class="font-normal text-stone-500">({{ $aValider->count() }})</span></h2>
                @if ($aValider->isEmpty())
                    <x-vide icone="check">Aucun ramassage en attente de validation.</x-vide>
                @else
                    <div class="space-y-2">
                        @foreach ($aValider as $l)
                            @php $total = (int) $l->ramassage_matin + (int) $l->ramassage_apres_midi; @endphp
                            <div class="carte flex flex-wrap items-center gap-3 p-3">
                                <div class="min-w-0 flex-1">
                                    <div class="font-medium text-stone-900">{{ $l->nom_bande }} · {{ Format::date($l->date) }}</div>
                                    <div class="text-xs text-stone-500">{{ $l->ramassage_matin }} matin + {{ $l->ramassage_apres_midi }} après-midi = <strong>{{ $total }}</strong> · taux {{ Format::pourcent($l->taux_ponte, 2) }}</div>
                                </div>
                                @can('PON.valider')
                                    <form method="POST" action="{{ route('ponte.valider', $l->id) }}" class="flex items-end gap-2">
                                        @csrf
                                        <input type="hidden" name="empreinte" value="{{ Empreinte::de(\Illuminate\Support\Arr::except((array) $l, ['nom_bande'])) }}">
                                        <label class="text-xs text-stone-500">Défectueux
                                            <input type="number" name="nb_oeufs_defectueux" min="0" max="{{ $total }}" value="0" required inputmode="numeric" class="champ mt-1 w-24">
                                        </label>
                                        <button type="submit" class="btn-primaire">Valider</button>
                                    </form>
                                @endcan
                            </div>
                        @endforeach
                    </div>
                @endif
            </section>

            {{-- Historique --}}
            <section>
                <div class="mb-3 flex flex-wrap items-end justify-between gap-2">
                    <h2 class="font-semibold text-stone-900">Historique</h2>
                    <form method="GET" class="grid w-full grid-cols-[1fr_1fr_auto] items-end gap-2 sm:w-auto">
                        @if ($bande)<input type="hidden" name="bande" value="{{ $bande->id }}">@endif
                        <input type="date" name="du" value="{{ $du }}" class="champ" aria-label="Du">
                        <input type="date" name="au" value="{{ $au }}" class="champ" aria-label="Au">
                        <button class="btn-secondaire">Filtrer</button>
                    </form>
                </div>
                <div class="carte overflow-x-auto">
                    <table class="tableau">
                        <thead class="bg-stone-50"><tr><th>Date</th><th>Bande</th><th class="text-right">Matin</th><th class="text-right">A-midi</th><th class="text-right">Taux</th><th class="text-right">Valides</th><th class="text-right">Défect.</th><th>État</th></tr></thead>
                        <tbody class="divide-y divide-stone-100">
                            @forelse ($historique as $l)
                                @php $etat = Ponte::etat($l); @endphp
                                <tr>
                                    <td class="whitespace-nowrap tabular-nums">{{ Format::date($l->date) }}</td>
                                    <td>{{ $l->nom_bande }}@if($l->temoin) <x-statut ton="info">témoin</x-statut>@endif</td>
                                    <td class="text-right tabular-nums">{{ $l->ramassage_matin }}</td>
                                    <td class="text-right tabular-nums">{{ $l->ramassage_apres_midi }}</td>
                                    <td class="text-right tabular-nums">{{ Format::pourcent($l->taux_ponte, 2) }}</td>
                                    <td class="text-right tabular-nums">{{ $l->total_oeufs_valides ?? '—' }}</td>
                                    <td class="text-right tabular-nums">{{ $l->nb_oeufs_defectueux ?? '—' }}</td>
                                    <td>
                                        @if ($etat === 'valide')<x-statut ton="ok">Validé</x-statut>
                                        @elseif ($etat === 'attente')<x-statut ton="attente">En attente</x-statut>
                                        @else<x-statut ton="alerte">À vérifier</x-statut>@endif
                                    </td>
                                </tr>
                            @empty
                                <tr><td colspan="8" class="py-6 text-center text-stone-500">Aucun ramassage sur la période.</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
                <div class="mt-3">{{ $historique->links() }}</div>
            </section>
        </div>
    </div>
</x-layouts.app>
