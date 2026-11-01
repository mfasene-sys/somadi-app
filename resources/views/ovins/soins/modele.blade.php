@php
    $lignesForm = old('lignes', $lignes->map(fn ($l) => ['jour_offset' => $l->jour_offset, 'id_type_soin' => $l->id_type_soin, 'dose' => $l->dose, 'unite' => $l->unite, 'commentaire' => $l->commentaire, 'obligatoire' => $l->obligatoire])->all());
    $lignesForm = array_values($lignesForm) ?: [['jour_offset' => 0]];
    $peut = auth()->user()->can('OVS.gerer');
@endphp
<x-layouts.app :titre="$m ? $m->nom_modele : 'Nouveau modèle de soins'" sous-titre="Chaque ligne est datée à partir de la date de référence choisie à l’application (J0)">
    <form method="POST" action="{{ route('ovins.soins.modele.enregistrer', $m?->id_modele) }}" class="carte max-w-4xl space-y-5 p-5 sm:p-6">
        @csrf
        <fieldset @disabled(! $peut) class="space-y-5">
            <div class="grid gap-4 sm:grid-cols-2">
                <x-champ name="nom_modele" label="Nom" :value="$m?->nom_modele" required maxlength="150"/>
                <x-champ name="categorie" label="Catégorie (usage)" :value="$m?->categorie" required maxlength="50" list="cats" aide="Un seul modèle actif par catégorie."/>
                <x-choix name="cible" label="Cible" :options="['tous' => 'Lots et animaux', 'lot' => 'Lots', 'animal' => 'Animaux']" :value="$m?->cible ?? 'tous'"/>
                <label class="flex items-center gap-2 self-end pb-3 text-sm"><input type="checkbox" name="actif" value="1" class="size-5" @checked(old('actif', $m?->actif ?? 1))> Actif</label>
                <x-champ name="description" label="Description" type="textarea" :value="$m?->description" class="sm:col-span-2"/>
            </div>
            <datalist id="cats"><option value="entree_reproduction"><option value="agneaux"><option value="brebis"><option value="engraissement"><option value="general"></datalist>

            <div>
                <h2 class="mb-2 font-semibold text-stone-900">Soins du modèle</h2>
                <div id="lignes" class="space-y-2">
                    @foreach ($lignesForm as $i => $l)
                        <div class="grid grid-cols-[5rem_1fr] gap-2 rounded-lg border border-stone-200 p-2 sm:grid-cols-[5rem_1fr_6rem_5rem_1fr_auto]" data-ligne>
                            <input name="lignes[{{ $i }}][jour_offset]" type="number" value="{{ $l['jour_offset'] ?? 0 }}" class="champ" aria-label="Jour (J+)" title="Jour par rapport à J0">
                            <select name="lignes[{{ $i }}][id_type_soin]" class="champ" aria-label="Soin"><option value="">— retirer cette ligne —</option>@foreach ($types as $t)<option value="{{ $t->id_type_soin }}" @selected((string) ($l['id_type_soin'] ?? '') === (string) $t->id_type_soin)>{{ $t->nom_soin }}</option>@endforeach</select>
                            <input name="lignes[{{ $i }}][dose]" value="{{ $l['dose'] ?? '' }}" placeholder="Dose" maxlength="80" class="champ">
                            <input name="lignes[{{ $i }}][unite]" value="{{ $l['unite'] ?? '' }}" placeholder="Unité" maxlength="30" class="champ">
                            <input name="lignes[{{ $i }}][commentaire]" value="{{ $l['commentaire'] ?? '' }}" placeholder="Commentaire" maxlength="255" class="champ col-span-2 sm:col-span-1">
                            <label class="flex items-center gap-1 text-xs"><input type="checkbox" name="lignes[{{ $i }}][obligatoire]" value="1" @checked($l['obligatoire'] ?? true) class="size-4"> Oblig.</label>
                        </div>
                    @endforeach
                </div>
                @if ($peut)<button type="button" class="btn-secondaire mt-2 min-h-9" onclick="ajouterLigne()"><x-icone nom="plus" class="size-4"/> Ligne</button>@endif
                <p class="mt-2 text-xs text-stone-500">Exemple : J0 vaccin, J+7 rappel. Un décalage négatif place le soin avant la date de référence.</p>
            </div>
        </fieldset>
        <div class="flex flex-col-reverse gap-3 border-t border-stone-100 pt-5 sm:flex-row sm:justify-end">
            <a href="{{ route('ovins.soins.catalogue') }}" class="btn-secondaire">Retour</a>
            @if ($peut)<button class="btn-primaire">Enregistrer le modèle</button>@endif
        </div>
    </form>
    <script>
        function ajouterLigne() {
            var c = document.getElementById('lignes'), d = c.querySelector('[data-ligne]').cloneNode(true), n = c.children.length;
            d.querySelectorAll('[name]').forEach(function (e) { e.name = e.name.replace(/lignes\[\d+\]/, 'lignes[' + n + ']'); if (e.type === 'checkbox') e.checked = true; else if (e.tagName === 'SELECT') e.value = ''; else e.value = e.type === 'number' ? 0 : ''; });
            c.appendChild(d);
        }
    </script>
</x-layouts.app>
