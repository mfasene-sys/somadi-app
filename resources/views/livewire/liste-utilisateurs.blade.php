<div>
    <div class="mb-4 grid gap-3 sm:grid-cols-[1fr_auto_auto]">
        <label class="relative block">
            <span class="sr-only">Rechercher</span>
            <x-icone nom="search" class="pointer-events-none absolute left-3 top-1/2 size-4 -translate-y-1/2 text-stone-400"/>
            <input type="search" wire:model.live.debounce.300ms="recherche" placeholder="Nom, prénom ou e-mail" class="champ pl-9">
        </label>
        <select wire:model.live="role" class="champ sm:w-48" aria-label="Filtrer par rôle">
            <option value="">Tous les rôles</option>
            @foreach ($roles as $cle => $libelle)
                <option value="{{ $cle }}">{{ $libelle }}</option>
            @endforeach
        </select>
        <select wire:model.live="statut" class="champ sm:w-40" aria-label="Filtrer par statut">
            <option value="actifs">Actifs</option>
            <option value="inactifs">Inactifs</option>
            <option value="tous">Tous</option>
        </select>
    </div>

    {{-- Mobile : cartes --}}
    <ul class="space-y-2 md:hidden">
        @forelse ($utilisateurs as $u)
            <li>
                <a @can('ACC.gerer') href="{{ route('utilisateurs.edit', $u) }}" @endcan class="carte flex items-center gap-3 p-3">
                    <span class="grid size-10 shrink-0 place-items-center rounded-full {{ $u->actif ? 'bg-ferme-100 text-ferme-800' : 'bg-stone-100 text-stone-400' }} text-sm font-semibold">{{ $u->initiales() }}</span>
                    <span class="min-w-0 flex-1">
                        <span class="block truncate font-medium text-stone-900">{{ $u->nomComplet() }}</span>
                        <span class="block truncate text-xs text-stone-500">{{ $u->email }}</span>
                    </span>
                    <span class="text-right">
                        <span class="badge bg-stone-100 text-stone-700">{{ \App\Support\Roles::libelle($u->role) }}</span>
                        @unless ($u->actif)<span class="mt-1 block text-xs text-stone-400">inactif</span>@endunless
                    </span>
                </a>
            </li>
        @empty
            <li class="carte p-6 text-center text-sm text-stone-500">Aucun utilisateur ne correspond.</li>
        @endforelse
    </ul>

    {{-- Bureau : tableau --}}
    <div class="carte hidden overflow-hidden md:block">
        <table class="tableau">
            <thead class="bg-stone-50">
                <tr><th>Nom</th><th>E-mail</th><th>Rôle</th><th>Statut</th><th class="w-px"></th></tr>
            </thead>
            <tbody class="divide-y divide-stone-100">
                @forelse ($utilisateurs as $u)
                    <tr class="{{ $u->actif ? '' : 'text-stone-400' }}">
                        <td class="font-medium {{ $u->actif ? 'text-stone-900' : '' }}">{{ $u->nomComplet() }}</td>
                        <td>{{ $u->email }}</td>
                        <td><span class="badge bg-stone-100 text-stone-700">{{ \App\Support\Roles::libelle($u->role) }}</span></td>
                        <td>
                            @if ($u->actif)
                                <span class="badge bg-ferme-50 text-ferme-700">Actif</span>
                            @else
                                <span class="badge bg-stone-100 text-stone-500">Inactif</span>
                            @endif
                        </td>
                        <td class="text-right">
                            @can('ACC.gerer')
                                <a href="{{ route('utilisateurs.edit', $u) }}" class="font-medium text-ferme-700 hover:underline">Modifier</a>
                            @endcan
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="5" class="py-8 text-center text-stone-500">Aucun utilisateur ne correspond.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div class="mt-4">{{ $utilisateurs->links() }}</div>
</div>
