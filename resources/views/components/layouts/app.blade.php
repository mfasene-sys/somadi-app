@props(['titre' => null, 'sousTitre' => null])
@php
    $user = auth()->user();
    $menu = \App\Support\Menu::pour($user);
    $raccourcis = collect($menu)->flatMap(fn ($g) => $g['entrees'])->keyBy('code');
    $bas = collect(['PIL', 'PON', 'VOL', 'VEN', 'OVA', 'OVS'])->filter(fn ($c) => $raccourcis->has($c))->take(3)->map(fn ($c) => $raccourcis[$c]);
    $titrePage = $titre ? $titre.' · SOMADI' : 'SOMADI Farming';
@endphp
<!DOCTYPE html>
<html lang="fr" class="h-full">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <meta name="theme-color" content="#2f6b45">
    <meta name="somadi-genere-le" content="{{ now()->toIso8601String() }}">
    <title>{{ $titrePage }}</title>
    <link rel="manifest" href="{{ asset('manifest.webmanifest') }}">
    <link rel="icon" href="{{ asset('icons/icon-192.png') }}" type="image/png">
    <link rel="apple-touch-icon" href="{{ asset('icons/apple-touch-icon.png') }}">
    <link rel="stylesheet" href="{{ asset('css/app.css') }}?v={{ @filemtime(public_path('css/app.css')) }}">
    @livewireStyles
</head>
<body class="h-full">
<div id="bandeau-hors-ligne" class="hidden sticky top-0 z-50 bg-paille-100 px-4 py-2 text-sm text-paille-700 border-b border-paille-500/40" role="status">
    <div class="mx-auto flex max-w-6xl items-center gap-2">
        <x-icone nom="wifi-off" class="size-4 shrink-0"/>
        <span><strong>Hors connexion.</strong> Affichage de la page enregistrée <span data-fraicheur></span>. Les saisies sont désactivées jusqu'au retour du réseau.</span>
    </div>
</div>

{{-- Barre latérale : bureau --}}
<aside class="fixed inset-y-0 left-0 z-30 hidden w-64 flex-col border-r border-stone-200 bg-white lg:flex">
    @include('layouts.partials.menu', ['menu' => $menu, 'user' => $user])
</aside>

{{-- Tiroir : mobile --}}
<div id="tiroir" class="fixed inset-0 z-40 hidden lg:hidden" aria-modal="true" role="dialog" aria-label="Menu">
    <div class="absolute inset-0 bg-stone-900/40" data-fermer-tiroir></div>
    <aside class="absolute inset-y-0 left-0 flex w-[85%] max-w-xs flex-col bg-white shadow-xl">
        <button type="button" class="absolute right-2 top-3 rounded-lg p-2 text-stone-500 hover:bg-stone-100" data-fermer-tiroir aria-label="Fermer le menu">
            <x-icone nom="x"/>
        </button>
        @include('layouts.partials.menu', ['menu' => $menu, 'user' => $user])
    </aside>
</div>

<div class="flex min-h-full flex-col lg:pl-64">
    {{-- Barre du haut : mobile --}}
    <header class="sticky top-0 z-20 flex h-14 items-center gap-3 border-b border-stone-200 bg-white/95 px-3 backdrop-blur lg:hidden" style="padding-top: env(safe-area-inset-top)">
        <button type="button" class="rounded-lg p-2 text-stone-600 hover:bg-stone-100" data-ouvrir-tiroir aria-label="Ouvrir le menu">
            <x-icone nom="menu" class="size-6"/>
        </button>
        <a href="{{ route('accueil') }}" class="flex items-center gap-2 font-semibold text-ferme-800">
            <img src="{{ asset('icons/icon-192.png') }}" alt="" class="size-7 rounded-md">SOMADI
        </a>
        <a href="{{ route('profil.edit') }}" class="ml-auto grid size-9 place-items-center rounded-full bg-ferme-100 text-sm font-semibold text-ferme-800" aria-label="Mon profil">{{ $user->initiales() }}</a>
    </header>

    <main class="mx-auto w-full max-w-6xl flex-1 px-4 pb-28 pt-5 sm:px-6 lg:px-8 lg:pb-10 lg:pt-8">
        @if ($titre)
            <div class="mb-6 flex flex-wrap items-end justify-between gap-3">
                <div>
                    <h1 class="text-2xl font-bold tracking-tight text-stone-900">{{ $titre }}</h1>
                    @if ($sousTitre)<p class="mt-1 text-sm text-stone-500">{{ $sousTitre }}</p>@endif
                </div>
                @isset($actions)<div class="flex flex-wrap gap-2">{{ $actions }}</div>@endisset
            </div>
        @endif

        @if (session('succes'))
            <div class="mb-5 rounded-lg border border-ferme-200 bg-ferme-50 px-4 py-3 text-sm text-ferme-800" role="status">{{ session('succes') }}</div>
        @endif

        @if (session('erreur'))
            <div class="mb-5 rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-800" role="alert">{{ session('erreur') }}</div>
        @endif

        @if (session('proposer_empreinte') && \App\Support\Empreintes::active())
            {{-- Affiché par empreinte.js seulement sur un téléphone équipé d'un capteur où l'empreinte n'est pas encore utilisée. --}}
            <div class="mb-5 hidden rounded-lg border border-ferme-200 bg-ferme-50 px-4 py-3 text-sm text-ferme-800" role="status" data-empreinte-proposition>
                <div class="flex items-center gap-3">
                    <x-icone nom="empreinte" class="size-6 shrink-0"/>
                    <p class="flex-1">Connectez-vous plus vite : activez l'empreinte sur ce téléphone, sans mot de passe ni code.</p>
                    <a href="{{ route('profil.edit') }}#empreinte" class="btn-primaire shrink-0">Activer</a>
                </div>
            </div>
            <script src="{{ asset('js/empreinte.js') }}?v={{ @filemtime(public_path('js/empreinte.js')) }}" defer></script>
        @endif

        {{ $slot }}
    </main>
</div>

{{-- Navigation du bas : mobile --}}
<nav class="fixed inset-x-0 bottom-0 z-20 border-t border-stone-200 bg-white lg:hidden" style="padding-bottom: env(safe-area-inset-bottom)" aria-label="Raccourcis">
    <div class="grid grid-cols-4">
        @foreach ($bas as $e)
            <a href="{{ $e['url'] }}" class="flex flex-col items-center gap-0.5 py-2 text-[11px] font-medium {{ $e['actif'] ? 'text-ferme-700' : 'text-stone-500' }}">
                <x-icone :nom="$e['icone']" class="size-6"/>{{ $e['court'] }}
            </a>
        @endforeach
        @for ($i = $bas->count(); $i < 3; $i++)<span></span>@endfor
        <button type="button" class="flex flex-col items-center gap-0.5 py-2 text-[11px] font-medium text-stone-500" data-ouvrir-tiroir>
            <x-icone nom="grid" class="size-6"/>Menu
        </button>
    </div>
</nav>

<script src="{{ asset('js/app.js') }}?v={{ @filemtime(public_path('js/app.js')) }}" defer></script>
@livewireScripts
</body>
</html>
