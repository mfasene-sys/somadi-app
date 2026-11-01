<!DOCTYPE html>
<html lang="fr"><head><meta charset="utf-8"><title>Inventaire {{ $inv->id }}</title>
<style>
    body { font-family: 'DejaVu Sans', sans-serif; font-size: 9pt; color: #1c1917; }
    h1 { font-size: 14pt; margin: 0; } p { margin: 2px 0; }
    table { width: 100%; border-collapse: collapse; margin-top: 10px; }
    th, td { border: 0.5pt solid #a8a29e; padding: 4px 5px; text-align: left; }
    th { background: #f5f5f4; font-size: 8pt; }
    .c { width: 22px; text-align: center; } .n { width: 26px; }
</style></head>
<body>
    <h1>SOMADI Farming · {{ $inv->titre }}</h1>
    <p>{{ $inv->perimetre }} · ouvert le {{ \App\Support\Format::date(substr($inv->ouvert_le, 0, 10)) }} par {{ $inv->ouvert_par }} · {{ $inv->total_attendu }} animaux attendus</p>
    <p>Contrôleur : ______________________ Date : ____/____/________</p>
    <table>
        <thead><tr><th class="n">N°</th><th>Boucle</th><th>Nom</th><th>Sexe</th><th>Site · lot</th><th class="c">P</th><th class="c">A</th><th class="c">X</th><th>Observation</th></tr></thead>
        <tbody>
            @foreach ($lignes as $l)
                <tr><td>{{ $l->numero }}</td><td><strong>{{ $l->code_animal }}</strong></td><td>{{ $l->nom_animal }}</td><td>{{ $l->sexe }}</td><td>{{ $l->nom_site }}{{ $l->nom_lot ? ' · '.$l->nom_lot : '' }}</td><td></td><td></td><td></td><td style="width: 30%"></td></tr>
            @endforeach
            @for ($i = 0; $i < 10; $i++)<tr><td>+</td><td></td><td></td><td></td><td></td><td></td><td></td><td></td><td></td></tr>@endfor
        </tbody>
    </table>
    <p style="margin-top: 8px">P : présent · A : anomalie · X : absent. Les lignes « + » servent aux animaux vus mais non attendus.</p>
</body></html>
