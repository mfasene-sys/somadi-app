@props(['liens' => []])
{{-- Sous-navigation d'un module : [libellé => url]. Défile horizontalement sur mobile. --}}
<nav class="-mx-4 mb-5 overflow-x-auto px-4 sm:mx-0 sm:px-0" aria-label="Sous-menu">
    <div class="flex min-w-max gap-1 rounded-xl bg-stone-100 p-1">
        @foreach ($liens as $libelle => $url)
            @php($actif = request()->url() === $url)
            <a href="{{ $url }}" class="rounded-lg px-3 py-2 text-sm font-medium {{ $actif ? 'bg-white text-ferme-800 shadow-sm' : 'text-stone-600 hover:text-stone-900' }}" @if($actif) aria-current="page" @endif>{{ $libelle }}</a>
        @endforeach
    </div>
</nav>
