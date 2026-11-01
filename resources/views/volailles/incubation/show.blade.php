@php
    use App\Support\Format; use App\Domaine\Incubation;
    $stade = Incubation::stade($l);
    $eclose = Incubation::estEclose($l);
    $mire = Incubation::aMire($l);
    $dispo = Incubation::disponibles($l);
    $peutAjuster = ! $eclose && ! $mire && ! $l->transfere && $jour !== null && $jour < Incubation::JOUR_MIRAGE;
    $peutMirer = ! $eclose && ! $mire && ! $l->transfere && $jour !== null && $jour >= Incubation::JOUR_MIRAGE;
    $peutTransferer = ! $eclose && ! $l->transfere && $dispo > 0;
    $mirageNul = $mire && $l->date_mirage && (int) $l->nombre_fertiles === 0;
    $peutEclore = ! $eclose && ($l->transfere || $mirageNul);
    $dateAtteinte = ! $l->date_eclosion_previsionnelle || today()->gte(\Illuminate\Support\Carbon::parse($l->date_eclosion_previsionnelle));
@endphp
<x-layouts.app :titre="$l->nom_lot ?: 'Incubation n° '.$l->id" :sous-titre="ucfirst($l->espece).' · '.($l->nom_bande ?? 'œufs achetés').' · '.$l->incubateur">
    <x-slot:actions><x-statut :ton="$stade['ton']" class="px-3 py-1 text-sm">{{ $stade['libelle'] }}</x-statut></x-slot:actions>

    {{-- Frise des étapes --}}
    <ol class="mb-6 grid grid-cols-4 gap-1 text-center text-xs">
        @foreach ([['Chargement', true, $l->date_entree], ['Mirage', $mire, $l->date_mirage], ['Transfert', (bool) $l->transfere, $l->date_transfert], ['Éclosion', $eclose, $l->date_eclosion]] as [$nom, $fait, $date])
            <li class="rounded-lg px-1 py-2 {{ $fait ? 'bg-ferme-50 text-ferme-800' : 'bg-stone-100 text-stone-500' }}">
                <div class="font-semibold">{{ $nom }}</div><div class="tabular-nums">{{ $fait && $date ? Format::date($date) : '—' }}</div>
            </li>
        @endforeach
    </ol>

    <div class="mb-6 grid gap-3 sm:grid-cols-4">
        <x-indicateur label="Œufs chargés" :valeur="Format::nombre($l->nombre_oeufs_initial)" :detail="(int) $l->nombre_oeufs !== (int) $l->nombre_oeufs_initial ? 'retenus : '.$l->nombre_oeufs : null"/>
        <x-indicateur label="Fertiles (mirage)" :valeur="$mire ? Format::nombre($l->nombre_fertiles) : '—'" :detail="$mire ? 'fertilité '.Format::pourcent($l->taux_fertilite) : 'mirage à partir du '.Format::date($dateMirage?->toDateString())"/>
        <x-indicateur label="Éclosion prévue" :valeur="Format::date($l->date_eclosion_previsionnelle)" :detail="$jour !== null ? 'jour J+'.max(0, $jour) : null"/>
        <x-indicateur label="Éclos" :valeur="$eclose ? Format::nombre($l->nombre_eclos) : '—'" :detail="$eclose ? 'éclosion '.Format::pourcent($l->taux_eclosion).' · fertilité '.Format::pourcent($l->taux_fertilite) : null"/>
    </div>

    @if ($lotVolaille)
        <a href="{{ route('lots.show', $lotVolaille->id) }}" class="carte mb-6 flex items-center gap-3 p-4 hover:border-ferme-200">
            <x-icone nom="layers" class="size-5 text-ferme-700"/>
            <span class="flex-1 text-sm">Lot de poussins créé : <strong>{{ $lotVolaille->nom_lot }}</strong> ({{ $lotVolaille->effectif_en_cours }} sujets)</span>
            <x-icone nom="chevron-right" class="size-4 text-stone-400"/>
        </a>
    @endif

    @can('INC.gerer')
        <div class="grid gap-5 lg:grid-cols-2">
            @if ($peutAjuster)
                <form method="POST" action="{{ route('incubation.ajuster', $l->id) }}" class="carte space-y-3 p-4">
                    @csrf <input type="hidden" name="empreinte" value="{{ $empreinte }}">
                    <h2 class="font-semibold text-stone-900">Ajuster le nombre d’œufs</h2>
                    <p class="text-xs text-stone-500">Possible avant J+10, avant mirage et transfert. Au plus {{ $l->nombre_oeufs_initial }} œufs.</p>
                    <x-champ name="nombre_oeufs" label="Nombre d’œufs retenus" type="number" min="1" :max="$l->nombre_oeufs_initial" :value="$l->nombre_oeufs" required inputmode="numeric"/>
                    <button class="btn-secondaire w-full">Ajuster</button>
                </form>
            @endif

            @if ($peutMirer)
                <form method="POST" action="{{ route('incubation.mirer', $l->id) }}" class="carte space-y-3 p-4">
                    @csrf <input type="hidden" name="empreinte" value="{{ $empreinte }}">
                    <h2 class="font-semibold text-stone-900">Mirage <span class="text-xs font-normal text-stone-500">(facultatif, une seule fois)</span></h2>
                    <div class="grid grid-cols-2 gap-3">
                        <x-champ name="nombre_fertiles" label="Œufs fertiles" type="number" min="0" :max="$l->nombre_oeufs" required inputmode="numeric" :aide="'sur '.$l->nombre_oeufs.' retenus'"/>
                        <x-champ name="date_mirage" label="Date" type="date" :value="today()->toDateString()" :min="$dateMirage?->toDateString()" :max="today()->toDateString()" required/>
                    </div>
                    <button class="btn-primaire w-full">Enregistrer le mirage</button>
                </form>
            @elseif (! $eclose && ! $mire && ! $l->transfere)
                <div class="carte p-4 text-sm text-stone-500">Le mirage sera possible à partir du {{ Format::date($dateMirage?->toDateString()) }} (J+10).</div>
            @endif

            @if ($peutTransferer)
                <form method="POST" action="{{ route('incubation.transferer', $l->id) }}" class="carte space-y-3 p-4">
                    @csrf <input type="hidden" name="empreinte" value="{{ $empreinte }}">
                    <h2 class="font-semibold text-stone-900">Transfert en éclosoir</h2>
                    <p class="text-xs text-stone-500">{{ $dispo }} œufs à transférer. Si le premier éclosoir manque de place, choisissez un second : le lot sera scindé en A et B{{ $reservations ? ' — impossible ici car ce lot a '.$reservations.' réservation(s)' : '' }}.</p>
                    @php $opts = $eclosoirs->mapWithKeys(fn ($e) => [$e->id => $e->nom.' — '.Format::nombre($e->libre).' places'])->all(); @endphp
                    <x-choix name="eclosoir1" label="Éclosoir principal" :options="$opts"/>
                    <x-choix name="eclosoir2" label="Second éclosoir (si nécessaire)" :options="$opts" vide="Aucun"/>
                    <x-champ name="date_transfert" label="Date" type="date" :value="today()->toDateString()" :min="$l->date_entree" :max="today()->toDateString()" required/>
                    <button class="btn-primaire w-full" @disabled($eclosoirs->isEmpty())>Transférer</button>
                </form>
            @endif

            @if ($peutEclore)
                <form method="POST" action="{{ route('incubation.eclore', $l->id) }}" class="carte space-y-3 p-4 lg:col-span-2">
                    @csrf <input type="hidden" name="empreinte" value="{{ $empreinte }}">
                    <h2 class="font-semibold text-stone-900">Éclosion</h2>
                    @unless ($dateAtteinte)
                        <p class="rounded-lg bg-paille-50 px-3 py-2 text-sm text-paille-700">La date prévisionnelle ({{ Format::date($l->date_eclosion_previsionnelle) }}) n’est pas encore atteinte.</p>
                    @endunless
                    <div class="grid gap-3 sm:grid-cols-3">
                        <x-champ name="nombre_eclos" label="Poussins éclos" type="number" min="0" :max="$dispo" required inputmode="numeric" :aide="'sur '.$dispo.' œufs disponibles'"/>
                        <x-champ name="date_eclosion" label="Date réelle" type="date" :value="today()->toDateString()" :max="today()->toDateString()" required/>
                        <x-champ name="race" label="Race des poussins" maxlength="50" aide="Obligatoire s’il y a des poussins."/>
                    </div>
                    <fieldset>
                        <legend class="etiquette">Calcul des taux</legend>
                        <div class="grid gap-2 sm:grid-cols-2">
                            <label class="flex items-start gap-3 rounded-lg border border-stone-200 p-3 text-sm {{ $l->date_mirage ? '' : 'opacity-50' }}">
                                <input type="radio" name="methode" value="mesure" @checked($l->date_mirage) @disabled(! $l->date_mirage) class="mt-0.5 size-4">
                                <span><strong>Mesure</strong> — fertilité et éclosion calculées à partir du mirage réel.</span>
                            </label>
                            <label class="flex items-start gap-3 rounded-lg border border-stone-200 p-3 text-sm">
                                <input type="radio" name="methode" value="estimation" @checked(! $l->date_mirage) class="mt-0.5 size-4">
                                <span><strong>Estimation SOMADI</strong> — sans mirage, les deux taux valent √(éclos / œufs). Convention d’estimation, pas une mesure.</span>
                            </label>
                        </div>
                    </fieldset>
                    <label class="flex items-center gap-3 text-sm text-stone-700">
                        <input type="checkbox" name="programme_soins" value="1" class="size-5"> Générer le programme de soins poussins et les réglages de température
                    </label>
                    <div class="flex justify-end"><button class="btn-primaire" @disabled(! $dateAtteinte)>Enregistrer l’éclosion</button></div>
                </form>
            @endif
        </div>
    @endcan

    <section class="mt-6">
        <h2 class="mb-2 font-semibold text-stone-900">Historique</h2>
        <pre class="carte overflow-x-auto whitespace-pre-wrap p-4 font-sans text-sm text-stone-700">{{ $l->observations ?: 'Aucune observation.' }}</pre>
    </section>
</x-layouts.app>
