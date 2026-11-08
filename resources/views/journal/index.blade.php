<x-layouts.app titre="Journal" sous-titre="Qui a fait quoi, et quand">
    <form method="GET" class="carte mb-4 grid gap-3 p-4 sm:grid-cols-2 lg:grid-cols-5">
        <div>
            <label for="action" class="etiquette">Action</label>
            <select id="action" name="action" class="champ">
                <option value="">Toutes</option>
                @foreach ($actions as $a)
                    <option value="{{ $a }}" @selected(($filtres['action'] ?? '') === $a)>{{ $a }}</option>
                @endforeach
            </select>
        </div>
        <div>
            <label for="du" class="etiquette">Du</label>
            <input id="du" name="du" type="date" value="{{ $filtres['du'] ?? '' }}" class="champ">
        </div>
        <div>
            <label for="au" class="etiquette">Au</label>
            <input id="au" name="au" type="date" value="{{ $filtres['au'] ?? '' }}" class="champ">
        </div>
        <div class="flex items-end gap-2 lg:col-span-2">
            <button type="submit" class="btn-primaire">Filtrer</button>
            <a href="{{ route('journal.index') }}" class="btn-secondaire">Effacer</a>
        </div>
    </form>

    <div class="carte divide-y divide-stone-100">
        @forelse ($lignes as $l)
            <details class="group px-4 py-3">
                <summary class="flex cursor-pointer list-none flex-wrap items-center gap-x-3 gap-y-1">
                    <span class="w-36 shrink-0 text-xs tabular-nums text-stone-500">{{ \Illuminate\Support\Carbon::parse($l->cree_le)->format('d/m/Y H:i:s') }}</span>
                    <span class="badge bg-stone-100 font-mono text-stone-700">{{ $l->action }}</span>
                    <span class="text-sm text-stone-800">{{ $l->user_nom ?? 'Anonyme' }}</span>
                    @if (! empty($l->agi_par_nom))<span class="journal-agi-par">par {{ $l->agi_par_nom }}, sous cette identité</span>@endif
                    @if ($l->objet_type)<span class="text-xs text-stone-500">{{ $l->objet_type }} #{{ $l->objet_id }}</span>@endif
                    <x-icone nom="chevron-right" class="ml-auto size-4 text-stone-400 transition group-open:rotate-90"/>
                </summary>
                <div class="mt-3 grid gap-3 text-xs sm:grid-cols-2">
                    @foreach (['avant' => 'Avant', 'apres' => 'Après'] as $champ => $libelle)
                        @if ($l->$champ)
                            <div>
                                <div class="mb-1 font-semibold text-stone-500">{{ $libelle }}</div>
                                <pre class="overflow-x-auto rounded-lg bg-stone-50 p-3 text-stone-700">{{ json_encode(json_decode($l->$champ), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) }}</pre>
                            </div>
                        @endif
                    @endforeach
                    @if ($l->motif)<div class="sm:col-span-2"><span class="font-semibold text-stone-500">Motif :</span> {{ $l->motif }}</div>@endif
                    <div class="text-stone-400 sm:col-span-2">IP {{ $l->ip ?? '—' }}</div>
                </div>
            </details>
        @empty
            <p class="p-6 text-center text-sm text-stone-500">Aucune entrée.</p>
        @endforelse
    </div>
    <div class="mt-4">{{ $lignes->links() }}</div>
</x-layouts.app>
