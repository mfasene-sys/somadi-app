@props(['titre' => 'Connexion'])
<!DOCTYPE html>
<html lang="fr" class="h-full">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <meta name="theme-color" content="#2f6b45">
    <title>{{ $titre }} · SOMADI</title>
    <link rel="manifest" href="{{ asset('manifest.webmanifest') }}">
    <link rel="icon" href="{{ asset('icons/icon-192.png') }}" type="image/png">
    <link rel="apple-touch-icon" href="{{ asset('icons/apple-touch-icon.png') }}">
    <link rel="stylesheet" href="{{ asset('css/app.css') }}?v={{ @filemtime(public_path('css/app.css')) }}">
</head>
<body class="h-full">
    <div class="flex min-h-full flex-col justify-center bg-gradient-to-b from-ferme-50 to-stone-50 px-5 py-10">
        <div class="mx-auto w-full max-w-sm">
            <div class="mb-8 flex flex-col items-center text-center">
                <img src="{{ asset('icons/icon-192.png') }}" alt="" class="size-16 rounded-2xl shadow-sm">
                <h1 class="mt-4 text-2xl font-bold tracking-tight text-ferme-900">SOMADI Farming</h1>
                <p class="mt-1 text-sm text-stone-500">Gestion de la ferme</p>
            </div>
            {{ $slot }}
        </div>
    </div>
    <script src="{{ asset('js/app.js') }}?v={{ @filemtime(public_path('js/app.js')) }}" defer></script>
</body>
</html>
