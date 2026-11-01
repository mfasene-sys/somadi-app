@php use App\Support\Montant; use App\Support\Format; $b = $r['bilan']; $c = $r['controles']; @endphp
<!DOCTYPE html>
<html lang="fr">
<head>
<meta charset="utf-8">
<style>
    @page { margin: 22mm 16mm 20mm; }
    body { font-family: 'DejaVu Sans', sans-serif; font-size: 9.5pt; color: #1c1917; }
    h1 { font-size: 17pt; margin: 0 0 2mm; color: #1e4d33; }
    h2 { font-size: 11.5pt; margin: 7mm 0 2mm; color: #1e4d33; border-bottom: 0.5pt solid #d6d3d1; padding-bottom: 1mm; }
    .sous { color: #57534e; margin-bottom: 5mm; }
    table { width: 100%; border-collapse: collapse; }
    th { text-align: left; font-size: 8.5pt; color: #57534e; border-bottom: 0.6pt solid #a8a29e; padding: 1.5mm 1mm; }
    td { padding: 1.3mm 1mm; border-bottom: 0.3pt solid #e7e5e4; vertical-align: top; }
    .n { text-align: right; white-space: nowrap; }
    .total td { font-weight: bold; border-top: 0.6pt solid #a8a29e; }
    .cadre { border: 0.6pt solid #d6d3d1; padding: 3mm; margin-top: 3mm; }
    .petit { font-size: 8pt; color: #57534e; }
    footer { position: fixed; bottom: -12mm; left: 0; right: 0; font-size: 7.5pt; color: #78716c; }
</style>
</head>
<body>
<footer>SOMADI Farming — Exercice {{ $meta['annee'] }} clôturé le {{ \Illuminate\Support\Carbon::parse($meta['clos_le'])->format('d/m/Y H:i') }} par {{ $meta['auteur'] }} — {{ $meta['version'] }}</footer>

<h1>Bilan de l’exercice {{ $meta['annee'] }}</h1>
<div class="sous">Arrêté au {{ Format::date($b['arrete']) }} · document définitif · montants en FCFA</div>

<h2>Synthèse</h2>
<table>
    <tr><td>Ventes de l’exercice ({{ $b['ventes_nombre'] }})</td><td class="n">{{ Montant::fcfa($b['ventes_total']) }}</td></tr>
    <tr><td>Recettes encaissées (opérations valides)</td><td class="n">{{ Montant::fcfa($b['recettes']) }}</td></tr>
    <tr><td>Dépenses (opérations valides)</td><td class="n">{{ Montant::fcfa($b['depenses']) }}</td></tr>
    <tr class="total"><td>Mouvement net de trésorerie (recettes − dépenses)</td><td class="n">{{ Montant::fcfa($b['mouvement_net']) }}</td></tr>
    <tr><td>Créances clients à l’arrêté</td><td class="n">{{ Montant::fcfa($b['creances_total']) }}</td></tr>
    <tr><td>Recettes de l’exercice pour des ventes antérieures ({{ $b['recettes_ventes_anterieures']['n'] }})</td><td class="n">{{ Montant::fcfa($b['recettes_ventes_anterieures']['total']) }}</td></tr>
</table>
<p class="petit">Le mouvement net n’est ni un solde bancaire ni un bénéfice : il compare les encaissements et les décaissements enregistrés.</p>

<h2>Ventes par produit</h2>
<table>
    <tr><th>Produit</th><th class="n">Ventes</th><th class="n">Quantité</th><th class="n">Montant</th></tr>
    @forelse ($b['ventes'] as $v)
        <tr><td>{{ $v['libelle'] }}</td><td class="n">{{ $v['n'] }}</td><td class="n">{{ Format::nombre($v['quantite']) }}</td><td class="n">{{ Montant::fcfa($v['total']) }}</td></tr>
    @empty
        <tr><td colspan="4">Aucune vente.</td></tr>
    @endforelse
</table>

<h2>Recettes et dépenses par catégorie</h2>
<table>
    <tr><th>Type</th><th>Catégorie</th><th class="n">Opérations</th><th class="n">Montant</th></tr>
    @forelse ($b['operations'] as $o)
        <tr><td>{{ $o['type'] === 'recette' ? 'Recette' : 'Dépense' }}</td><td>{{ $o['categorie'] }}</td><td class="n">{{ $o['n'] }}</td><td class="n">{{ Montant::fcfa($o['total']) }}</td></tr>
    @empty
        <tr><td colspan="4">Aucune opération.</td></tr>
    @endforelse
</table>

<h2>Contrôles de clôture</h2>
<table>
    <tr><th>Contrôle</th><th class="n">Résultat</th></tr>
    @foreach ($c['bloquants'] as $x)
        <tr><td>{{ $x['libelle'] }}</td><td class="n">{{ $x['disponible'] ? ($x['nombre'] ? $x['nombre'].' anomalie(s)' : 'Aucune anomalie') : 'Vérification indisponible' }}</td></tr>
    @endforeach
    @foreach ($c['a_examiner'] as $x)
        <tr><td>{{ $x['libelle'] }} (à examiner)</td><td class="n">{{ $x['disponible'] ? $x['nombre'] : 'Vérification indisponible' }}</td></tr>
    @endforeach
</table>

<h2>Créances à l’arrêté</h2>
<table>
    <tr><th>Vente</th><th>Date</th><th>Client</th><th>Produit</th><th class="n">Total</th><th class="n">Créance</th></tr>
    @forelse ($b['creances'] as $x)
        <tr><td>n° {{ $x['id'] }}</td><td>{{ Format::date($x['date']) }}</td><td>{{ $x['client'] }}</td><td>{{ $x['produit'] }}</td><td class="n">{{ Montant::fcfa($x['total']) }}</td><td class="n">{{ Montant::fcfa($x['creance']) }}</td></tr>
    @empty
        <tr><td colspan="6">Aucune créance.</td></tr>
    @endforelse
</table>

<h2>Annexe : registre historique des commandes</h2>
<p>{{ $b['annexe_commandes']['n'] }} commande(s) enregistrée(s) dans l’ancien registre pour l’exercice, pour {{ Montant::fcfa($b['annexe_commandes']['total']) }}. Ce registre est conservé à titre d’information et n’est pas additionné aux ventes.</p>

<div class="cadre">
    <strong>Commentaire de clôture</strong><br>{{ $meta['commentaire'] }}
</div>
</body>
</html>
