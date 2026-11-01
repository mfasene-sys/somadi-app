@props(['label', 'valeur', 'detail' => null, 'ton' => null])
@php
    // Un montant « 4 407 900 FCFA » affiche l'unité en petit, pour tenir dans les cartes étroites du téléphone.
    $unite = null;
    if (is_string($valeur) && str_ends_with($valeur, "\u{202F}FCFA")) {
        $valeur = mb_substr($valeur, 0, -5);
        $unite = 'FCFA';
    }
@endphp
<div {{ $attributes->merge(['class' => 'carte min-w-0 p-4']) }}>
    <div class="text-xs font-medium text-stone-500">{{ $label }}</div>
    <div class="mt-1 text-lg font-bold leading-tight whitespace-nowrap tabular-nums sm:text-2xl {{ $ton === 'alerte' ? 'text-red-700' : 'text-stone-900' }}">{{ $valeur }}@if ($unite)<span class="ml-1 text-xs font-semibold sm:text-sm">{{ $unite }}</span>@endif</div>
    @if ($detail)<div class="mt-0.5 text-xs text-stone-500">{{ $detail }}</div>@endif
</div>
