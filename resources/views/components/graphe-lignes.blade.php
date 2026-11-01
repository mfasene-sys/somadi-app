@props(['dates' => [], 'series' => [], 'unite' => '%', 'max' => null, 'decimales' => 1, 'titre' => ''])
@php
    // Graphique en lignes rendu côté serveur (SVG) : une ligne par série, interruptions là où il manque des jours.
    // Deux variantes : large (PC, étiquettes directes en bout de ligne si ≤ 4 séries) et étroite (téléphone,
    // textes à taille réelle). Infobulle au survol ou au toucher : public/js/app.js.
    $n = max(1, count($dates));
    $valeurs = collect($series)->flatMap(fn ($s) => array_values($s['points']));
    $haut = $max ?? max(5, (int) (ceil(((float) $valeurs->max() ?: 1) / 5) * 5));
    $fmt = fn ($v) => number_format($v, $decimales, ',', ' ');
    $fmtGrad = fn ($v) => number_format($v, fmod($v, 1) == 0 ? 0 : 1, ',', ' ');
    $suffixe = $unite === '%' ? ' %' : '';
    $libellesX = $n > 1 ? array_unique([0, intdiv($n - 1, 2), $n - 1]) : [0];

    $variantes = [
        ['W' => 800, 'H' => 260, 'g' => 56, 'd' => count($series) <= 4 && count($series) > 0 ? 150 : 16, 'classe' => 'hidden sm:block'],
        ['W' => 360, 'H' => 230, 'g' => 44, 'd' => 10, 'classe' => 'sm:hidden'],
    ];
    foreach ($variantes as &$v) {
        $v['h'] = 14; $v['b'] = 28;
        $v['lw'] = $v['W'] - $v['g'] - $v['d']; $v['lh'] = $v['H'] - $v['h'] - $v['b'];
        $x = fn ($i) => round($v['g'] + ($n > 1 ? $i / ($n - 1) : 0.5) * $v['lw'], 1);
        $y = fn ($val) => round($v['h'] + $v['lh'] - ($val / $haut) * $v['lh'], 1);
        $v['x'] = $x; $v['y'] = $y;
        $v['traces'] = collect($series)->map(function ($s) use ($dates, $x, $y) {
            $segments = []; $courant = [];
            foreach ($dates as $i => $date) {
                if (array_key_exists($date, $s['points'])) { $courant[] = [$x($i), $y($s['points'][$date])]; }
                elseif ($courant) { $segments[] = $courant; $courant = []; }
            }
            if ($courant) { $segments[] = $courant; }
            $dernier = collect($s['points'])->keys()->last();

            return $s + ['segments' => $segments, 'fin' => $dernier ? [$x(array_search($dernier, $dates)), $y($s['points'][$dernier]), $s['points'][$dernier]] : null];
        });
        // Étiquettes directes (variante large) : espacement minimal de 14 px
        $pos = []; $precedent = -INF;
        foreach ($v['traces']->filter(fn ($t) => $t['fin'])->sortBy(fn ($t) => $t['fin'][1]) as $t) { $yy = max($t['fin'][1], $precedent + 14); $pos[$t['nom']] = $yy; $precedent = $yy; }
        $v['pos'] = $pos;
        $v['json'] = ['dates' => $dates, 'unite' => $unite, 'decimales' => $decimales, 'g' => $v['g'], 'lw' => $v['lw'], 'W' => $v['W'], 'h' => $v['h'], 'lh' => $v['lh'], 'haut' => $haut,
            'series' => collect($series)->map(fn ($s) => ['nom' => $s['nom'], 'couleur' => $s['couleur'], 'valeurs' => array_map(fn ($dt) => $s['points'][$dt] ?? null, $dates)])->values()];
    }
    unset($v);
@endphp
<figure {{ $attributes->merge(['class' => 'carte p-4']) }}>
    @if ($titre)<figcaption class="mb-2 font-semibold text-stone-900">{{ $titre }}</figcaption>@endif
    @if (count($series) >= 2)
        <ul class="mb-2 flex flex-wrap gap-x-4 gap-y-1 text-xs text-stone-600" aria-label="Légende">
            @foreach ($series as $s)<li class="flex items-center gap-1.5"><span class="inline-block h-0.5 w-4 rounded" style="background: {{ $s['couleur'] }}"></span>{{ $s['nom'] }}</li>@endforeach
        </ul>
    @endif
    @if ($valeurs->isEmpty())
        <p class="py-10 text-center text-sm text-stone-500">Aucune donnée sur la période.</p>
    @else
        @foreach ($variantes as $v)
            <div class="relative {{ $v['classe'] }}" data-graphe='@json($v['json'])'>
                <svg viewBox="0 0 {{ $v['W'] }} {{ $v['H'] }}" class="h-auto w-full touch-pan-y select-none" role="img" aria-label="{{ $titre }}">
                    @foreach (collect(range(0, 4))->map(fn ($k) => $haut * $k / 4) as $grad)
                        <line x1="{{ $v['g'] }}" x2="{{ $v['g'] + $v['lw'] }}" y1="{{ ($v['y'])($grad) }}" y2="{{ ($v['y'])($grad) }}" stroke="{{ $grad == 0 ? '#d6d3d1' : '#e7e5e4' }}" stroke-width="1"/>
                        <text x="{{ $v['g'] - 8 }}" y="{{ ($v['y'])($grad) + 4 }}" text-anchor="end" font-size="11" fill="#78716c">{{ $fmtGrad($grad) }}{{ $suffixe }}</text>
                    @endforeach
                    @foreach ($libellesX as $i)
                        <text x="{{ ($v['x'])($i) }}" y="{{ $v['H'] - 8 }}" text-anchor="{{ $i === 0 && $n > 1 ? 'start' : ($i === $n - 1 && $n > 1 ? 'end' : 'middle') }}" font-size="11" fill="#78716c">{{ \Illuminate\Support\Carbon::parse($dates[$i])->format('d/m') }}</text>
                    @endforeach
                    @foreach ($v['traces'] as $t)
                        @foreach ($t['segments'] as $seg)
                            @if (count($seg) === 1)
                                <circle cx="{{ $seg[0][0] }}" cy="{{ $seg[0][1] }}" r="3" fill="{{ $t['couleur'] }}"/>
                            @else
                                <polyline fill="none" stroke="{{ $t['couleur'] }}" stroke-width="2" stroke-linejoin="round" stroke-linecap="round" points="{{ collect($seg)->map(fn ($p) => $p[0].','.$p[1])->implode(' ') }}"/>
                            @endif
                        @endforeach
                        @if ($v['d'] > 16 && $t['fin'])
                            <text x="{{ $v['g'] + $v['lw'] + 8 }}" y="{{ $v['pos'][$t['nom']] + 4 }}" font-size="11" fill="#44403c">{{ \Illuminate\Support\Str::limit($t['nom'], 13, '…') }} · {{ $fmt($t['fin'][2]) }}{{ $suffixe }}</text>
                        @endif
                    @endforeach
                    <line data-curseur x1="0" x2="0" y1="{{ $v['h'] }}" y2="{{ $v['h'] + $v['lh'] }}" stroke="#a8a29e" stroke-width="1" visibility="hidden"/>
                    <g data-marqueurs></g>
                    <rect data-zone x="{{ $v['g'] }}" y="{{ $v['h'] }}" width="{{ $v['lw'] }}" height="{{ $v['lh'] }}" fill="transparent"/>
                </svg>
                <div data-infobulle class="pointer-events-none absolute top-0 z-10 hidden min-w-36 rounded-lg border border-stone-200 bg-white px-3 py-2 text-xs shadow-lg"></div>
            </div>
        @endforeach
    @endif
    {{ $slot }}
</figure>
