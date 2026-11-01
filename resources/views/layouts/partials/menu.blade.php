<div class="flex h-16 shrink-0 items-center gap-2.5 border-b border-stone-100 px-5">
    <img src="{{ asset('icons/icon-192.png') }}" alt="" class="size-8 rounded-lg">
    <div class="leading-tight">
        <div class="font-bold text-ferme-800">SOMADI</div>
        <div class="text-xs text-stone-500">Farming</div>
    </div>
</div>
<nav class="flex-1 overflow-y-auto px-3 py-4" aria-label="Navigation principale">
    @foreach ($menu as $groupe)
        <div class="mb-5">
            <div class="px-3 pb-1.5 text-[11px] font-semibold tracking-wider text-stone-400 uppercase">{{ $groupe['titre'] }}</div>
            <ul class="space-y-0.5">
                @foreach ($groupe['entrees'] as $e)
                    <li>
                        <a href="{{ $e['url'] }}" class="lien-menu {{ $e['actif'] ? 'lien-menu-actif' : '' }}" @if($e['actif']) aria-current="page" @endif>
                            <x-icone :nom="$e['icone']" class="size-5 shrink-0 {{ $e['actif'] ? 'text-ferme-700' : 'text-stone-400' }}"/>
                            <span class="truncate">{{ $e['titre'] }}</span>
                            @unless ($e['disponible'])
                                <span class="ml-auto shrink-0 whitespace-nowrap text-[10px] font-medium text-stone-400">lot {{ $e['lot'] }}</span>
                            @endunless
                        </a>
                    </li>
                @endforeach
            </ul>
        </div>
    @endforeach
</nav>
<div class="border-t border-stone-100 p-3">
    <a href="{{ route('profil.edit') }}" class="lien-menu {{ request()->routeIs('profil.*') ? 'lien-menu-actif' : '' }}">
        <span class="grid size-7 shrink-0 place-items-center rounded-full bg-ferme-100 text-xs font-semibold text-ferme-800">{{ $user->initiales() }}</span>
        <span class="min-w-0">
            <span class="block truncate text-stone-800">{{ $user->nomComplet() }}</span>
            <span class="block truncate text-xs font-normal text-stone-500">{{ \App\Support\Roles::libelle($user->role) }}</span>
        </span>
    </a>
    <form method="POST" action="{{ route('logout') }}" data-deconnexion data-hors-ligne-ok>
        @csrf
        <button type="submit" class="lien-menu w-full">
            <x-icone nom="logout" class="size-5 text-stone-400"/>Se déconnecter
        </button>
    </form>
</div>
