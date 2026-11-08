<?php

namespace App\Support;

/**
 * Générateur de QR code minimal (mode octet, correction d'erreurs niveau M, versions 1 à 10, soit jusqu'à
 * 213 octets), rendu en SVG. Sert à afficher la clé de l'application d'authentification sans bibliothèque
 * externe ni service en ligne : la clé secrète ne quitte pas le serveur.
 * Algorithme d'après la norme ISO/IEC 18004 (placement, masques et pénalités comme les implémentations de référence).
 */
class QrCode
{
    /** Par version : [octets de données, octets de correction par bloc, nombre de blocs] (niveau M). */
    private const VERSIONS = [
        1 => [16, 10, 1], 2 => [28, 16, 1], 3 => [44, 26, 1], 4 => [64, 18, 2], 5 => [86, 24, 2],
        6 => [108, 16, 4], 7 => [124, 18, 4], 8 => [154, 22, 4], 9 => [182, 22, 5], 10 => [216, 26, 5],
    ];

    private const ALIGNEMENT = [
        1 => [], 2 => [6, 18], 3 => [6, 22], 4 => [6, 26], 5 => [6, 30],
        6 => [6, 34], 7 => [6, 22, 38], 8 => [6, 24, 42], 9 => [6, 26, 46], 10 => [6, 28, 50],
    ];

    /** @var array<int, array<int, bool>> modules[y][x] */
    private array $modules = [];

    /** @var array<int, array<int, bool>> */
    private array $fonction = [];

    private int $taille;

    private function __construct(private int $version)
    {
        $this->taille = $version * 4 + 17;
        $ligne = array_fill(0, $this->taille, false);
        $this->modules = array_fill(0, $this->taille, $ligne);
        $this->fonction = array_fill(0, $this->taille, $ligne);
    }

    /** @return list<list<bool>> matrice des modules (true = noir), sans marge. */
    public static function matrice(string $donnees): array
    {
        $n = strlen($donnees);
        $version = null;
        foreach (self::VERSIONS as $v => [$octets]) {
            if (4 + ($v < 10 ? 8 : 16) + 8 * $n <= $octets * 8) {
                $version = $v;
                break;
            }
        }
        if ($version === null) {
            throw new \InvalidArgumentException('Texte trop long pour le QR code.');
        }

        $qr = new self($version);
        $qr->dessinerMotifs();
        $qr->placer($qr->codewords($donnees));
        $qr->appliquerMeilleurMasque();

        return $qr->modules;
    }

    /** QR code en SVG (marge de 4 modules), à la taille d'affichage voulue. */
    public static function svg(string $donnees, int $pixels = 220): string
    {
        $m = self::matrice($donnees);
        $t = count($m);
        $chemin = '';
        foreach ($m as $y => $ligne) {
            foreach ($ligne as $x => $noir) {
                if ($noir) {
                    $chemin .= 'M'.($x + 4).' '.($y + 4).'h1v1h-1z';
                }
            }
        }
        $cote = $t + 8;

        return '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 '.$cote.' '.$cote.'" width="'.$pixels.'" height="'.$pixels
            .'" shape-rendering="crispEdges" role="img" aria-label="QR code"><rect width="100%" height="100%" fill="#fff"/><path fill="#000" d="'.$chemin.'"/></svg>';
    }

    // ---------- Données et correction d'erreurs ----------

    /** @return list<int> */
    private function codewords(string $donnees): array
    {
        [$capacite, $eccParBloc, $nbBlocs] = self::VERSIONS[$this->version];
        $bits = '0100'.str_pad(decbin(strlen($donnees)), $this->version < 10 ? 8 : 16, '0', STR_PAD_LEFT);
        foreach (str_split($donnees) as $c) {
            $bits .= str_pad(decbin(ord($c)), 8, '0', STR_PAD_LEFT);
        }
        $bits .= str_repeat('0', min(4, $capacite * 8 - strlen($bits)));
        $bits .= str_repeat('0', (8 - strlen($bits) % 8) % 8);
        $octets = array_map('bindec', str_split($bits, 8));
        for ($i = 0; count($octets) < $capacite; $i++) {
            $octets[] = $i % 2 === 0 ? 0xEC : 0x11;
        }

        // Découpage en blocs : les premiers blocs ont un octet de données de moins si la division ne tombe pas juste.
        $court = intdiv($capacite, $nbBlocs);
        $nbLongs = $capacite % $nbBlocs;
        $diviseur = self::diviseurRs($eccParBloc);
        $blocs = [];
        $corrections = [];
        $pos = 0;
        for ($b = 0; $b < $nbBlocs; $b++) {
            $longueur = $court + ($b >= $nbBlocs - $nbLongs ? 1 : 0);
            $bloc = array_slice($octets, $pos, $longueur);
            $pos += $longueur;
            $blocs[] = $bloc;
            $corrections[] = self::resteRs($bloc, $diviseur);
        }

        $sortie = [];
        for ($i = 0; $i <= $court; $i++) {
            foreach ($blocs as $bloc) {
                if ($i < count($bloc)) {
                    $sortie[] = $bloc[$i];
                }
            }
        }
        for ($i = 0; $i < $eccParBloc; $i++) {
            foreach ($corrections as $c) {
                $sortie[] = $c[$i];
            }
        }

        return $sortie;
    }

    private static function mulGf(int $x, int $y): int
    {
        $z = 0;
        for ($i = 7; $i >= 0; $i--) {
            $z = (($z << 1) ^ (($z >> 7) * 0x11D)) & 0xFF;
            $z ^= (($y >> $i) & 1) * $x;
        }

        return $z;
    }

    /** @return list<int> */
    private static function diviseurRs(int $degre): array
    {
        $r = array_fill(0, $degre, 0);
        $r[$degre - 1] = 1;
        $racine = 1;
        for ($i = 0; $i < $degre; $i++) {
            for ($j = 0; $j < $degre; $j++) {
                $r[$j] = self::mulGf($r[$j], $racine);
                if ($j + 1 < $degre) {
                    $r[$j] ^= $r[$j + 1];
                }
            }
            $racine = self::mulGf($racine, 0x02);
        }

        return $r;
    }

    /** @param list<int> $donnees  @param list<int> $diviseur  @return list<int> */
    private static function resteRs(array $donnees, array $diviseur): array
    {
        $r = array_fill(0, count($diviseur), 0);
        foreach ($donnees as $octet) {
            $facteur = $octet ^ array_shift($r);
            $r[] = 0;
            foreach ($diviseur as $i => $d) {
                $r[$i] ^= self::mulGf($d, $facteur);
            }
        }

        return $r;
    }

    // ---------- Motifs fixes ----------

    private function poser(int $x, int $y, bool $noir): void
    {
        $this->modules[$y][$x] = $noir;
        $this->fonction[$y][$x] = true;
    }

    private function dessinerMotifs(): void
    {
        $t = $this->taille;
        for ($i = 0; $i < $t; $i++) {
            $this->poser(6, $i, $i % 2 === 0);
            $this->poser($i, 6, $i % 2 === 0);
        }
        foreach ([[3, 3], [$t - 4, 3], [3, $t - 4]] as [$cx, $cy]) {
            for ($dy = -4; $dy <= 4; $dy++) {
                for ($dx = -4; $dx <= 4; $dx++) {
                    $x = $cx + $dx;
                    $y = $cy + $dy;
                    if ($x >= 0 && $x < $t && $y >= 0 && $y < $t) {
                        $d = max(abs($dx), abs($dy));
                        $this->poser($x, $y, $d !== 2 && $d !== 4);
                    }
                }
            }
        }
        $pos = self::ALIGNEMENT[$this->version];
        $n = count($pos);
        for ($i = 0; $i < $n; $i++) {
            for ($j = 0; $j < $n; $j++) {
                if (($i === 0 && $j === 0) || ($i === 0 && $j === $n - 1) || ($i === $n - 1 && $j === 0)) {
                    continue;
                }
                for ($dy = -2; $dy <= 2; $dy++) {
                    for ($dx = -2; $dx <= 2; $dx++) {
                        $this->poser($pos[$i] + $dx, $pos[$j] + $dy, max(abs($dx), abs($dy)) !== 1);
                    }
                }
            }
        }
        $this->dessinerFormat(0);
        if ($this->version >= 7) {
            $reste = $this->version;
            for ($i = 0; $i < 12; $i++) {
                $reste = ($reste << 1) ^ (($reste >> 11) * 0x1F25);
            }
            $bits = ($this->version << 12) | $reste;
            for ($i = 0; $i < 18; $i++) {
                $bit = (($bits >> $i) & 1) === 1;
                $a = $t - 11 + $i % 3;
                $b = intdiv($i, 3);
                $this->poser($a, $b, $bit);
                $this->poser($b, $a, $bit);
            }
        }
    }

    private function dessinerFormat(int $masque): void
    {
        $donnees = (0 << 3) | $masque; // niveau M = 00
        $reste = $donnees;
        for ($i = 0; $i < 10; $i++) {
            $reste = ($reste << 1) ^ (($reste >> 9) * 0x537);
        }
        $bits = (($donnees << 10) | $reste) ^ 0x5412;
        $bit = fn (int $i) => (($bits >> $i) & 1) === 1;
        $t = $this->taille;

        for ($i = 0; $i <= 5; $i++) {
            $this->poser(8, $i, $bit($i));
        }
        $this->poser(8, 7, $bit(6));
        $this->poser(8, 8, $bit(7));
        $this->poser(7, 8, $bit(8));
        for ($i = 9; $i < 15; $i++) {
            $this->poser(14 - $i, 8, $bit($i));
        }
        for ($i = 0; $i < 8; $i++) {
            $this->poser($t - 1 - $i, 8, $bit($i));
        }
        for ($i = 8; $i < 15; $i++) {
            $this->poser(8, $t - 15 + $i, $bit($i));
        }
        $this->poser(8, $t - 8, true);
    }

    // ---------- Placement et masque ----------

    /** @param list<int> $octets */
    private function placer(array $octets): void
    {
        $t = $this->taille;
        $total = count($octets) * 8;
        $i = 0;
        for ($droite = $t - 1; $droite >= 1; $droite -= 2) {
            if ($droite === 6) {
                $droite = 5;
            }
            for ($vert = 0; $vert < $t; $vert++) {
                for ($j = 0; $j < 2; $j++) {
                    $x = $droite - $j;
                    $montant = (($droite + 1) & 2) === 0;
                    $y = $montant ? $t - 1 - $vert : $vert;
                    if (! $this->fonction[$y][$x] && $i < $total) {
                        $this->modules[$y][$x] = (($octets[$i >> 3] >> (7 - ($i & 7))) & 1) === 1;
                        $i++;
                    }
                }
            }
        }
    }

    private function masquer(int $masque): void
    {
        for ($y = 0; $y < $this->taille; $y++) {
            for ($x = 0; $x < $this->taille; $x++) {
                if ($this->fonction[$y][$x]) {
                    continue;
                }
                $inverser = match ($masque) {
                    0 => ($x + $y) % 2 === 0,
                    1 => $y % 2 === 0,
                    2 => $x % 3 === 0,
                    3 => ($x + $y) % 3 === 0,
                    4 => (intdiv($x, 3) + intdiv($y, 2)) % 2 === 0,
                    5 => ($x * $y) % 2 + ($x * $y) % 3 === 0,
                    6 => (($x * $y) % 2 + ($x * $y) % 3) % 2 === 0,
                    default => (($x + $y) % 2 + ($x * $y) % 3) % 2 === 0,
                };
                if ($inverser) {
                    $this->modules[$y][$x] = ! $this->modules[$y][$x];
                }
            }
        }
    }

    private function appliquerMeilleurMasque(): void
    {
        $meilleur = 0;
        $minimum = PHP_INT_MAX;
        for ($m = 0; $m < 8; $m++) {
            $this->masquer($m);
            $this->dessinerFormat($m);
            $p = $this->penalite();
            if ($p < $minimum) {
                $minimum = $p;
                $meilleur = $m;
            }
            $this->masquer($m); // le masque est sa propre inverse
        }
        $this->masquer($meilleur);
        $this->dessinerFormat($meilleur);
    }

    private function penalite(): int
    {
        $t = $this->taille;
        $p = 0;
        $m = $this->modules;
        // Règles 1 et 3, en lignes puis en colonnes.
        for ($sens = 0; $sens < 2; $sens++) {
            for ($a = 0; $a < $t; $a++) {
                $ligne = [];
                for ($b = 0; $b < $t; $b++) {
                    $ligne[] = $sens === 0 ? $m[$a][$b] : $m[$b][$a];
                }
                $suite = 1;
                for ($b = 1; $b <= $t; $b++) {
                    if ($b < $t && $ligne[$b] === $ligne[$b - 1]) {
                        $suite++;
                    } else {
                        if ($suite >= 5) {
                            $p += $suite - 2;
                        }
                        $suite = 1;
                    }
                }
                $chaine = implode('', array_map(fn ($v) => $v ? '1' : '0', $ligne));
                $etendue = '0000'.$chaine.'0000';
                $p += 40 * (substr_count($etendue, '10111010000') + substr_count($etendue, '00001011101'));
            }
        }
        // Règle 2 : blocs 2×2 de même couleur.
        for ($y = 0; $y < $t - 1; $y++) {
            for ($x = 0; $x < $t - 1; $x++) {
                $c = $m[$y][$x];
                if ($c === $m[$y][$x + 1] && $c === $m[$y + 1][$x] && $c === $m[$y + 1][$x + 1]) {
                    $p += 3;
                }
            }
        }
        // Règle 4 : équilibre noir / blanc.
        $noirs = 0;
        foreach ($m as $ligne) {
            $noirs += count(array_filter($ligne));
        }
        $total = $t * $t;
        $k = intdiv(abs($noirs * 20 - $total * 10) + $total - 1, $total) - 1;
        $p += max(0, $k) * 10;

        return $p;
    }
}
