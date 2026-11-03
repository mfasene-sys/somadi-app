<x-layouts.app titre="Utilisateurs" sous-titre="Comptes, rôles et accès à l'application">
    <x-slot:actions>
        @can('ACC.gerer')
            <form method="POST" action="{{ route('utilisateurs.email_essai') }}">
                @csrf
                <button type="submit" class="btn-secondaire">Tester l'envoi d'e-mail</button>
            </form>
        @endcan
        @can('ACC.droits')
            <a href="{{ route('droits.edit') }}" class="btn-secondaire"><x-icone nom="shield" class="size-4"/>Droits par rôle</a>
        @endcan
        @can('ACC.journal')
            <a href="{{ route('journal.index') }}" class="btn-secondaire"><x-icone nom="clock" class="size-4"/>Journal</a>
        @endcan
        @can('ACC.gerer')
            <a href="{{ route('utilisateurs.create') }}" class="btn-primaire"><x-icone nom="plus" class="size-4"/>Nouvel utilisateur</a>
        @endcan
    </x-slot:actions>

    <livewire:liste-utilisateurs />
</x-layouts.app>
