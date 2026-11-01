@props(['name', 'label', 'options' => [], 'value' => null, 'vide' => null, 'aide' => null])
@php
    $id = $attributes->get('id', str_replace(['[', ']'], ['_', ''], $name));
    $cle = str_replace(['[', ']'], ['.', ''], $name);
    $val = (string) old($cle, $value);
@endphp
<div {{ $attributes->only('class') }}>
    <label for="{{ $id }}" class="etiquette">{{ $label }}</label>
    <select id="{{ $id }}" name="{{ $name }}" {{ $attributes->except(['class', 'id']) }} class="champ">
        @if ($vide !== null)<option value="">{{ $vide }}</option>@endif
        @foreach ($options as $k => $v)
            <option value="{{ $k }}" @selected($val === (string) $k)>{{ $v }}</option>
        @endforeach
    </select>
    @if ($aide)<p class="mt-1 text-xs text-stone-500">{{ $aide }}</p>@endif
    @error($cle)<p class="erreur">{{ $message }}</p>@enderror
</div>
