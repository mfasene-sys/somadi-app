@props(['ton' => 'neutre'])
@php
    $classes = [
        'ok' => 'bg-ferme-50 text-ferme-700 ring-ferme-200',
        'attente' => 'bg-paille-50 text-paille-700 ring-paille-500/30',
        'alerte' => 'bg-red-50 text-red-700 ring-red-200',
        'info' => 'bg-sky-50 text-sky-700 ring-sky-200',
        'neutre' => 'bg-stone-100 text-stone-600 ring-stone-200',
    ][$ton] ?? 'bg-stone-100 text-stone-600 ring-stone-200';
@endphp
<span {{ $attributes->merge(['class' => "badge ring-1 ring-inset $classes"]) }}>{{ $slot }}</span>
