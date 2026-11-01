@props(['name', 'label', 'type' => 'text', 'value' => null, 'aide' => null, 'suffixe' => null])
@php
    $id = $attributes->get('id', str_replace(['[', ']'], ['_', ''], $name));
    $cle = str_replace(['[', ']'], ['.', ''], $name);
    $val = old($cle, $value);
@endphp
<div {{ $attributes->only('class') }}>
    <label for="{{ $id }}" class="etiquette">{{ $label }}</label>
    @if ($type === 'textarea')
        <textarea id="{{ $id }}" name="{{ $name }}" rows="3" {{ $attributes->except(['class', 'id']) }} class="champ py-2">{{ $val }}</textarea>
    @else
        <div class="relative">
            <input id="{{ $id }}" name="{{ $name }}" type="{{ $type }}" value="{{ $val }}" {{ $attributes->except(['class', 'id']) }} class="champ {{ $suffixe ? 'pr-14' : '' }}">
            @if ($suffixe)<span class="pointer-events-none absolute inset-y-0 right-3 flex items-center text-sm text-stone-400">{{ $suffixe }}</span>@endif
        </div>
    @endif
    @if ($aide)<p class="mt-1 text-xs text-stone-500">{{ $aide }}</p>@endif
    @error($cle)<p class="erreur">{{ $message }}</p>@enderror
</div>
