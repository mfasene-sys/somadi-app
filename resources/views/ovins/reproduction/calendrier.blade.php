@php use App\Support\Format; use App\Domaine\Ovins\Reproduction; $t = today()->toDateString(); @endphp
<x-layouts.app :titre="'Calendrier · '.$lot->nom_lot" sous-titre="Calendrier théorique de reproduction du lot : il prévoit, il ne crée aucune naissance">
    <x-slot:actions><a href="{{ route('ovins.lots.show', $lot->id_lot) }}" class="btn-secondaire">Fiche du lot</a></x-slot:actions>

    <div class="grid gap-6 lg:grid-cols-[1fr_20rem]">
        <div class="min-w-0 space-y-4">
            @if ($cycles->isEmpty())
                <x-vide icone="calendar">Aucun calendrier. Indiquez la date de la première lutte pour le générer.</x-vide>
            @endif
            @foreach ($cycles as $c)
                @php($encours = $c->date_lutte <= $t && $t < $c->date_prochaine_lutte)
                <section class="carte p-4 {{ $encours ? 'ring-2 ring-ferme-500' : ($c->date_prochaine_lutte <= $t ? 'opacity-70' : '') }}">
                    <h2 class="mb-3 font-semibold text-stone-900">Cycle {{ $c->num_cycle }} @if ($encours)<x-statut ton="ok">en cours</x-statut>@endif</h2>
                    <ol class="grid grid-cols-2 gap-2 text-sm sm:grid-cols-5">
                        @foreach (['date_lutte' => 'Lutte', 'date_agnelage_prevu' => 'Agnelage', 'date_fin_allaitement' => 'Fin allaitement', 'date_prepa' => 'Préparation', 'date_prochaine_lutte' => 'Lutte suivante'] as $k => $lib)
                            <li class="rounded-lg bg-stone-50 px-3 py-2 {{ $c->$k <= $t ? 'text-stone-500' : '' }}"><span class="block text-xs text-stone-500">{{ $lib }}</span><strong class="tabular-nums">{{ Format::date($c->$k) }}</strong></li>
                        @endforeach
                    </ol>
                    <div class="mt-3 grid gap-3 md:grid-cols-2">
                        @foreach ($fenetres[$c->id_cycle] ?? [] as $f)
                            <div class="rounded-lg border border-stone-200 p-3 text-sm">
                                <p class="font-medium">{{ Reproduction::FENETRES[$f->type_fenetre] ?? $f->type_fenetre }}</p>
                                <p class="text-xs text-stone-500">{{ Format::date($f->date_debut) }} → {{ Format::date($f->date_fin) }} · {{ $f->libelle_prophylaxie }}</p>
                                <ul class="mt-2 space-y-1">
                                    @foreach ($actions[$f->id_soin_plan] ?? [] as $ac)
                                        <li class="flex gap-2 text-xs"><span class="tabular-nums text-stone-500">{{ Format::date($ac->date_soin) }}</span><span class="flex-1">{{ $ac->libelle_action }}</span>
                                            <x-statut :ton="\App\Domaine\Ovins\Soins::STATUTS[$ac->statut][1] ?? 'neutre'">{{ \App\Domaine\Ovins\Soins::STATUTS[$ac->statut][0] ?? $ac->statut }}</x-statut></li>
                                    @endforeach
                                </ul>
                                @can('OVR.gerer')
                                    <details class="mt-2">
                                        <summary class="cursor-pointer text-xs font-semibold text-ferme-700">Ajouter une action</summary>
                                        <form method="POST" action="{{ route('ovins.fenetres.actions', $f->id_soin_plan) }}" class="mt-2 grid gap-2">
                                            @csrf
                                            <input type="date" name="date_soin" value="{{ max($f->date_debut, min($f->date_fin, $t)) }}" min="{{ $f->date_debut }}" max="{{ $f->date_fin }}" class="champ" required>
                                            <select name="id_type_soin" class="champ"><option value="">Action libre (sans soin)</option>@foreach ($types as $ty)<option value="{{ $ty->id_type_soin }}">{{ $ty->nom_soin }}</option>@endforeach</select>
                                            <input name="libelle_action" placeholder="Libellé (facultatif avec un soin)" maxlength="255" class="champ">
                                            <button class="btn-secondaire min-h-9 text-xs">Ajouter</button>
                                        </form>
                                    </details>
                                @endcan
                            </div>
                        @endforeach
                    </div>
                </section>
            @endforeach
        </div>
        @can('OVR.gerer')
            <form method="POST" action="{{ route('ovins.lots.calendrier.generer', $lot->id_lot) }}" class="carte h-fit space-y-3 p-4" @if ($cycles->isNotEmpty()) onsubmit="return confirm('Remplacer le calendrier actuel ?')" @endif>
                @csrf
                <h2 class="font-semibold text-stone-900">{{ $cycles->isEmpty() ? 'Générer' : 'Régénérer' }} le calendrier</h2>
                <x-champ name="premiere_lutte" label="Première lutte" type="date" :value="$cycles->first()?->date_lutte ?? $t" required/>
                <div class="grid grid-cols-2 gap-3">
                    <x-champ name="gestation" label="Gestation" type="number" suffixe="j" :value="$cycles->first()?->duree_gestation_jours ?? $p['gestation']" required/>
                    <x-champ name="allaitement" label="Allaitement" type="number" suffixe="j" :value="$cycles->first()?->duree_allaitement_jours ?? $p['allaitement']" required/>
                    <x-champ name="preparation" label="Préparation" type="number" suffixe="j" :value="$cycles->first()?->duree_prepa_jours ?? $p['preparation']" required/>
                    <x-champ name="cycle_mois" label="Cycle" type="number" suffixe="mois" :value="$cycles->first()?->duree_cycle_mois ?? $p['cycle_mois']" required/>
                    <x-champ name="horizon_ans" label="Horizon" type="number" suffixe="ans" :value="$cycles->first()?->nb_ans_horizon ?? $p['horizon_ans']" required/>
                </div>
                <button class="btn-primaire w-full">{{ $cycles->isEmpty() ? 'Générer' : 'Régénérer' }}</button>
                <p class="text-xs text-stone-500">La régénération est refusée dès qu’une action du calendrier a été suivie.</p>
            </form>
        @endcan
    </div>
</x-layouts.app>
