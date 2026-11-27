<?php
/**
 * Scannable QR codes (ISO/IEC 18004), drawn as SVG in PHP with no library.
 * Byte mode, error correction level M, versions 1-20 (up to 666 bytes: any tracking URL).
 * Used by receipts and labels so a phone camera opens the tracking page.
 */

/** Module matrix (true = dark) for $text, or null if it is too long. */
function sl_qrMatrix(string $text): ?array
{
    // version => [ecc codewords per block, [blocks, data codewords], [blocks, data codewords]] at level M
    static $blk = [
        1 => [10, [1, 16]], 2 => [16, [1, 28]], 3 => [26, [1, 44]], 4 => [18, [2, 32]], 5 => [24, [2, 43]],
        6 => [16, [4, 27]], 7 => [18, [4, 31]], 8 => [22, [2, 38], [2, 39]], 9 => [22, [3, 36], [2, 37]],
        10 => [26, [4, 43], [1, 44]], 11 => [30, [1, 50], [4, 51]], 12 => [22, [6, 36], [2, 37]],
        13 => [22, [8, 37], [1, 38]], 14 => [24, [4, 40], [5, 41]], 15 => [24, [5, 41], [5, 42]],
        16 => [28, [7, 45], [3, 46]], 17 => [28, [10, 46], [1, 47]], 18 => [26, [9, 43], [4, 44]],
        19 => [26, [3, 44], [11, 45]], 20 => [26, [3, 41], [13, 42]],
    ];
    static $align = [
        1 => [], 2 => [6, 18], 3 => [6, 22], 4 => [6, 26], 5 => [6, 30], 6 => [6, 34], 7 => [6, 22, 38], 8 => [6, 24, 42],
        9 => [6, 26, 46], 10 => [6, 28, 50], 11 => [6, 30, 54], 12 => [6, 32, 58], 13 => [6, 34, 62], 14 => [6, 26, 46, 66],
        15 => [6, 26, 48, 70], 16 => [6, 26, 50, 74], 17 => [6, 30, 54, 78], 18 => [6, 30, 56, 82], 19 => [6, 30, 58, 86], 20 => [6, 34, 62, 90],
    ];
    $bytes = array_values(unpack('C*', $text) ?: []);
    $n = count($bytes);
    $ver = 0;
    foreach ($blk as $v => $b) {
        $cap = $b[1][0] * $b[1][1] + (isset($b[2]) ? $b[2][0] * $b[2][1] : 0);
        if (4 + ($v < 10 ? 8 : 16) + 8 * $n <= $cap * 8) { $ver = $v; break; }
    }
    if (!$ver) return null;
    $b = $blk[$ver];
    $dataCw = $b[1][0] * $b[1][1] + (isset($b[2]) ? $b[2][0] * $b[2][1] : 0);

    // data bit stream
    $bits = [];
    $put = function (int $val, int $len) use (&$bits) { for ($i = $len - 1; $i >= 0; $i--) $bits[] = ($val >> $i) & 1; };
    $put(4, 4);
    $put($n, $ver < 10 ? 8 : 16);
    foreach ($bytes as $c) $put($c, 8);
    $put(0, min(4, $dataCw * 8 - count($bits)));
    while (count($bits) % 8) $bits[] = 0;
    $data = [];
    foreach (array_chunk($bits, 8) as $ch) $data[] = bindec(implode('', $ch));
    for ($pad = 0xEC; count($data) < $dataCw; $pad ^= 0xEC ^ 0x11) $data[] = $pad;

    // Reed-Solomon over GF(256), poly 0x11D
    $mul = function (int $x, int $y): int { $z = 0; for ($i = 7; $i >= 0; $i--) { $z = ($z << 1) ^ (($z >> 7) * 0x11D); $z ^= (($y >> $i) & 1) * $x; } return $z & 0xFF; };
    $ecLen = $b[0];
    $div = array_fill(0, $ecLen, 0);
    $div[$ecLen - 1] = 1;
    $root = 1;
    for ($i = 0; $i < $ecLen; $i++) {
        for ($j = 0; $j < $ecLen; $j++) {
            $div[$j] = $mul($div[$j], $root);
            if ($j + 1 < $ecLen) $div[$j] ^= $div[$j + 1];
        }
        $root = $mul($root, 2);
    }
    $blocks = [];
    $off = 0;
    foreach ([$b[1], $b[2] ?? [0, 0]] as [$cnt, $len]) {
        for ($k = 0; $k < $cnt; $k++) {
            $d = array_slice($data, $off, $len);
            $off += $len;
            $rem = array_fill(0, $ecLen, 0);
            foreach ($d as $byte) {
                $f = $byte ^ array_shift($rem);
                $rem[] = 0;
                for ($j = 0; $j < $ecLen; $j++) $rem[$j] ^= $mul($div[$j], $f);
            }
            $blocks[] = [$d, $rem];
        }
    }
    $final = [];
    $maxLen = max(array_map(fn($x) => count($x[0]), $blocks));
    for ($i = 0; $i < $maxLen; $i++) foreach ($blocks as $bl) if ($i < count($bl[0])) $final[] = $bl[0][$i];
    for ($i = 0; $i < $ecLen; $i++) foreach ($blocks as $bl) $final[] = $bl[1][$i];

    // function patterns
    $size = $ver * 4 + 17;
    $m = array_fill(0, $size, array_fill(0, $size, false));
    $fn = $m;
    $set = function (int $x, int $y, bool $dark) use (&$m, &$fn) { $m[$y][$x] = $dark; $fn[$y][$x] = true; };
    for ($i = 0; $i < $size; $i++) { $set(6, $i, $i % 2 === 0); $set($i, 6, $i % 2 === 0); }
    foreach ([[3, 3], [$size - 4, 3], [3, $size - 4]] as [$cx, $cy]) {
        for ($dy = -4; $dy <= 4; $dy++) for ($dx = -4; $dx <= 4; $dx++) {
            $x = $cx + $dx; $y = $cy + $dy;
            if ($x < 0 || $y < 0 || $x >= $size || $y >= $size) continue;
            $dist = max(abs($dx), abs($dy));
            $set($x, $y, $dist !== 2 && $dist !== 4);
        }
    }
    $al = $align[$ver];
    $na = count($al);
    for ($i = 0; $i < $na; $i++) for ($j = 0; $j < $na; $j++) {
        if (($i === 0 && $j === 0) || ($i === 0 && $j === $na - 1) || ($i === $na - 1 && $j === 0)) continue;
        for ($dy = -2; $dy <= 2; $dy++) for ($dx = -2; $dx <= 2; $dx++) $set($al[$i] + $dx, $al[$j] + $dy, max(abs($dx), abs($dy)) !== 1);
    }
    $drawFormat = function (int $mask) use ($set, $size) {
        $d = (0 << 3) | $mask; // level M = 00
        $r = $d;
        for ($i = 0; $i < 10; $i++) $r = ($r << 1) ^ (($r >> 9) * 0x537);
        $bits = (($d << 10) | $r) ^ 0x5412;
        $g = fn($i) => (($bits >> $i) & 1) === 1;
        for ($i = 0; $i <= 5; $i++) $set(8, $i, $g($i));
        $set(8, 7, $g(6)); $set(8, 8, $g(7)); $set(7, 8, $g(8));
        for ($i = 9; $i < 15; $i++) $set(14 - $i, 8, $g($i));
        for ($i = 0; $i < 8; $i++) $set($size - 1 - $i, 8, $g($i));
        for ($i = 8; $i < 15; $i++) $set(8, $size - 15 + $i, $g($i));
        $set(8, $size - 8, true);
    };
    $drawFormat(0); // reserve the format areas
    if ($ver >= 7) {
        $r = $ver;
        for ($i = 0; $i < 12; $i++) $r = ($r << 1) ^ (($r >> 11) * 0x1F25);
        $vb = ($ver << 12) | $r;
        for ($i = 0; $i < 18; $i++) {
            $bit = (($vb >> $i) & 1) === 1;
            $a = $size - 11 + $i % 3; $c = intdiv($i, 3);
            $set($a, $c, $bit); $set($c, $a, $bit);
        }
    }

    // codewords, zigzag from the bottom right
    $i = 0;
    $total = count($final) * 8;
    for ($right = $size - 1; $right >= 1; $right -= 2) {
        if ($right === 6) $right = 5;
        for ($vert = 0; $vert < $size; $vert++) {
            for ($j = 0; $j < 2; $j++) {
                $x = $right - $j;
                $up = (($right + 1) & 2) === 0;
                $y = $up ? $size - 1 - $vert : $vert;
                if (!$fn[$y][$x] && $i < $total) {
                    $m[$y][$x] = (($final[$i >> 3] >> (7 - ($i & 7))) & 1) === 1;
                    $i++;
                }
            }
        }
    }

    // pick the mask with the lowest penalty
    $masks = [
        fn($x, $y) => ($x + $y) % 2 === 0, fn($x, $y) => $y % 2 === 0, fn($x, $y) => $x % 3 === 0, fn($x, $y) => ($x + $y) % 3 === 0,
        fn($x, $y) => (intdiv($x, 3) + intdiv($y, 2)) % 2 === 0, fn($x, $y) => $x * $y % 2 + $x * $y % 3 === 0,
        fn($x, $y) => ($x * $y % 2 + $x * $y % 3) % 2 === 0, fn($x, $y) => (($x + $y) % 2 + $x * $y % 3) % 2 === 0,
    ];
    $best = null;
    $bestScore = PHP_INT_MAX;
    $base = $m;
    foreach ($masks as $k => $f) {
        $m = $base;
        for ($y = 0; $y < $size; $y++) for ($x = 0; $x < $size; $x++) if (!$fn[$y][$x] && $f($x, $y)) $m[$y][$x] = !$m[$y][$x];
        $drawFormat($k);
        $score = sl_qrPenalty($m, $size);
        if ($score < $bestScore) { $bestScore = $score; $best = $m; }
    }
    return $best;
}

/** Mask penalty: long runs, 2x2 blocks, finder-like patterns, dark/light balance. */
function sl_qrPenalty(array $m, int $size): int
{
    $p = 0;
    $dark = 0;
    for ($pass = 0; $pass < 2; $pass++) {
        for ($a = 0; $a < $size; $a++) {
            $run = 0; $prev = null; $line = '';
            for ($b = 0; $b < $size; $b++) {
                $v = $pass ? $m[$b][$a] : $m[$a][$b];
                $line .= $v ? '1' : '0';
                if ($v === $prev) { $run++; if ($run === 5) $p += 3; elseif ($run > 5) $p++; } else { $run = 1; $prev = $v; }
            }
            $p += 40 * (substr_count($line, '10111010000') + substr_count($line, '00001011101'));
        }
    }
    for ($y = 0; $y < $size; $y++) {
        for ($x = 0; $x < $size; $x++) {
            if ($m[$y][$x]) $dark++;
            if ($x < $size - 1 && $y < $size - 1 && $m[$y][$x] === $m[$y][$x + 1] && $m[$y][$x] === $m[$y + 1][$x] && $m[$y][$x] === $m[$y + 1][$x + 1]) $p += 3;
        }
    }
    $p += 10 * intdiv(abs($dark * 20 - $size * $size * 10), $size * $size);
    return $p;
}

/** QR code as an SVG square of $px pixels with a 4-module quiet zone. Empty string if the text does not fit. */
function sl_qrSvg(string $text, int $px, string $label = 'QR code'): string
{
    $m = sl_qrMatrix($text);
    if (!$m) return '';
    $n = count($m);
    $q = 4;
    $dim = $n + 2 * $q;
    $d = '';
    foreach ($m as $y => $row) {
        foreach ($row as $x => $on) {
            if ($on) $d .= 'M' . ($x + $q) . ' ' . ($y + $q) . 'h1v1h-1z';
        }
    }
    return '<svg class="sl-qr" viewBox="0 0 ' . $dim . ' ' . $dim . '" width="' . $px . '" height="' . $px . '" role="img" aria-label="' . h($label) . '" shape-rendering="crispEdges"><rect width="' . $dim . '" height="' . $dim . '" fill="#fff"/><path d="' . $d . '" fill="#000"/></svg>';
}
