@props(['icone' => 'box'])
<div class="carte flex flex-col items-center px-6 py-10 text-center">
    <span class="grid size-12 place-items-center rounded-full bg-stone-100 text-stone-400"><x-icone :nom="$icone" class="size-6"/></span>
    <p class="mt-3 max-w-sm text-sm text-stone-500">{{ $slot }}</p>
</div>
