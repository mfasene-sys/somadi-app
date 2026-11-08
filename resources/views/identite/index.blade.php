<x-layouts.app titre="Changer d'identité" sous-titre="Agir sous le compte d'un utilisateur, puis revenir au vôtre">
    <div class="carte mb-5 max-w-3xl space-y-2 p-4 text-sm text-stone-600">
        <p>Vous voyez l'application exactement comme la personne choisie : ses menus, ses droits, ses tâches. <strong>Ce que vous saisissez est réellement enregistré</strong>, à son nom, et le journal indique que c'est vous ({{ $admin->nomComplet() }}) qui l'avez fait.</p>
        <p>Sous une autre identité, vous ne pouvez pas modifier son profil, son mot de passe ou ses empreintes, ni gérer les comptes et les droits. Les autres administrateurs et les comptes inactifs n'apparaissent pas ici.</p>
    </div>

    @if ($actuel)
        <div class="carte mb-5 flex max-w-3xl flex-wrap items-center justify-between gap-3 p-4">
            <p class="text-sm text-stone-700">Identité actuelle : <strong>{{ $actuel->nomComplet() }}</strong> ({{ \App\Support\Roles::libelle($actuel->role) }})</p>
            <form method="POST" action="{{ route('identite.revenir') }}" data-changement-identite>
                @csrf
                <button type="submit" class="btn-primaire">Revenir à mon compte</button>
            </form>
        </div>
    @endif

    @forelse ($cibles as $role => $comptes)
        <section class="mb-5 max-w-3xl">
            <h2 class="mb-2 text-sm font-semibold text-stone-500">{{ \App\Support\Roles::libelle($role) }}</h2>
            <ul class="carte divide-y divide-stone-100">
                @foreach ($comptes as $c)
                    <li class="flex flex-wrap items-center gap-3 px-4 py-3">
                        <span class="grid size-9 shrink-0 place-items-center rounded-full bg-ferme-100 text-sm font-semibold text-ferme-800">{{ $c->initiales() }}</span>
                        <span class="min-w-0 flex-1">
                            <span class="block truncate text-stone-800">{{ $c->nomComplet() }}</span>
                            <span class="block truncate text-xs text-stone-500">{{ $c->email }}</span>
                        </span>
                        @if ($actuel && $actuel->id === $c->id)
                            <span class="badge bg-paille-100 text-paille-700">Identité actuelle</span>
                        @else
                            <form method="POST" action="{{ route('identite.prendre', $c) }}" data-changement-identite>
                                @csrf
                                <button type="submit" class="btn-secondaire">Prendre cette identité</button>
                            </form>
                        @endif
                    </li>
                @endforeach
            </ul>
        </section>
    @empty
        <x-vide icone="users">Aucun compte disponible. Créez d'abord des utilisateurs actifs avec un rôle autre qu'administrateur.</x-vide>
    @endforelse
</x-layouts.app>
