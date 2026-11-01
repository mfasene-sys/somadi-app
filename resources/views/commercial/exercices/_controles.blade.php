{{-- $c : contrôles (Exercices::controles). --}}
<section class="carte p-4">
    <h3 class="mb-3 font-semibold text-stone-900">Contrôles de clôture</h3>
    <ul class="space-y-2 text-sm">
        @foreach ($c['bloquants'] as $cle => $k)
            @php($ok = $k['disponible'] && $k['nombre'] === 0)
            <li class="flex items-start gap-2">
                <span class="mt-0.5 grid size-5 shrink-0 place-items-center rounded-full {{ $ok ? 'bg-ferme-100 text-ferme-700' : 'bg-red-100 text-red-700' }}"><x-icone :nom="$ok ? 'check' : 'x'" class="size-3.5"/></span>
                <span class="flex-1">{{ $k['libelle'] }}
                    @if (! $k['disponible'])<span class="block text-xs text-red-700">Contrôle impossible : il bloque la clôture.</span>
                    @elseif ($k['nombre'])<span class="block text-xs text-red-700">{{ $k['nombre'] }} cas · n° {{ implode(', ', array_slice($k['ids'], 0, 20)) }}{{ $k['nombre'] > 20 ? '…' : '' }}</span>@endif
                </span>
            </li>
        @endforeach
    </ul>
    @if (! empty($c['a_examiner']))
        <h4 class="mb-2 mt-4 text-xs font-semibold uppercase tracking-wide text-stone-500">À examiner (non bloquant)</h4>
        <ul class="space-y-1 text-sm text-stone-600">
            @foreach ($c['a_examiner'] as $k)
                <li>{{ $k['libelle'] }} : <strong>{{ $k['disponible'] ? $k['nombre'] : '?' }}</strong></li>
            @endforeach
        </ul>
    @endif
</section>
