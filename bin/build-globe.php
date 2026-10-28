<?php

declare(strict_types=1);

/**
 * Generates public/assets/img/globe.svg — the hero band's Earth.
 *
 *   php bin/build-globe.php
 *
 * Built once and committed rather than projected on every request. The maths is
 * cheap but not free, the output never changes, and as a static file the
 * browser caches it instead of re-downloading ~900 dots inside every page.
 *
 * The projection is orthographic: what a sphere actually looks like from far
 * away. Land comes from coarse outlines in app/Support/land-outlines.php — a
 * silhouette is all a globe needs at this size, and a dotted one is forgiving
 * where a traced coastline would have to be right.
 */

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

$land = require __DIR__ . '/../app/Support/land-outlines.php';

const R = 150.0, CX = 200.0, CY = 200.0;
const LON0 = -40.0;   // Americas to the left, Africa and Europe to the right
const LAT0 = 14.0;    // tilted slightly, so the north pole reads as the top

/** @param array<int,array{0:float|int,1:float|int}> $poly */
function inPoly(float $x, float $y, array $poly): bool
{
    $in = false;
    $n = count($poly);
    for ($i = 0, $j = $n - 1; $i < $n; $j = $i++) {
        [$xi, $yi] = $poly[$i];
        [$xj, $yj] = $poly[$j];
        if (($yi > $y) !== ($yj > $y)
            && $x < ($xj - $xi) * ($y - $yi) / (($yj - $yi) ?: 1e-9) + $xi) {
            $in = !$in;
        }
    }

    return $in;
}

/** @return array{0:float,1:float,2:float} x, y, and depth (1 facing us, 0 at the limb) */
function project(float $lat, float $lon): array
{
    $s0 = sin(deg2rad(LAT0));
    $c0 = cos(deg2rad(LAT0));
    $la = deg2rad($lat);
    $dl = deg2rad($lon - LON0);

    return [
        CX + R * cos($la) * sin($dl),
        CY - R * ($c0 * sin($la) - $s0 * cos($la) * cos($dl)),
        $s0 * sin($la) + $c0 * cos($la) * cos($dl),
    ];
}

// --- Land dots -----------------------------------------------------------
$dots = [];
for ($lat = -84.0; $lat <= 84.0; $lat += 3.0) {
    // Rows shrink towards the poles, so take fewer samples along them and the
    // dots stay evenly spaced on the surface instead of bunching at the top.
    $step = 3.0 / max(0.12, cos(deg2rad($lat)));
    for ($lon = -180.0; $lon < 180.0; $lon += $step) {
        $isLand = $lat <= -63.0;                       // Antarctic cap
        if (!$isLand) {
            foreach ($land as $poly) {
                if (inPoly($lon, $lat, $poly)) {
                    $isLand = true;
                    break;
                }
            }
        }
        if (!$isLand) {
            continue;
        }

        [$x, $y, $d] = project($lat, $lon);
        if ($d <= 0.02) {
            continue;                                   // far side of the sphere
        }
        $dots[] = [$x, $y, $d];
    }
}

// --- Graticule -----------------------------------------------------------
$lats = [];
foreach ([-60, -30, 0, 30, 60] as $deg) {
    $t = deg2rad($deg);
    $lats[] = [
        'cy' => CY - R * (cos(deg2rad(LAT0)) * sin($t)),
        'rx' => R * cos($t),
        'ry' => max(2.0, R * cos($t) * sin(deg2rad(LAT0)) + R * cos($t) * 0.12),
    ];
}

// --- Connection arcs -----------------------------------------------------
// Dashed links between points on the surface, as in the reference. Each arc
// bows away from the centre so it reads as passing over the sphere rather than
// cutting through it.
$hub = [39.0, -99.0];                                   // mid-USA
$spokes = [[-15.0, -47.0], [51.0, 0.0], [-26.0, 28.0], [55.0, 37.0], [20.0, -100.0]];

$arcs = [];
$nodes = [];
[$hx, $hy, $hd] = project($hub[0], $hub[1]);
if ($hd > 0.05) {
    $nodes[] = [$hx, $hy, 3.2];
}

foreach ($spokes as [$lat, $lon]) {
    [$x, $y, $d] = project($lat, $lon);
    if ($d <= 0.08 || $hd <= 0.05) {
        continue;
    }
    $nodes[] = [$x, $y, 2.4];

    // Control point pushed out from the globe centre, so the curve arcs over.
    $mx = ($hx + $x) / 2;
    $my = ($hy + $y) / 2;
    $vx = $mx - CX;
    $vy = $my - CY;
    $len = max(1e-6, sqrt($vx * $vx + $vy * $vy));
    $bow = 26.0;
    $arcs[] = [$hx, $hy, $mx + $vx / $len * $bow, $my + $vy / $len * $bow, $x, $y];
}

// --- Emit ----------------------------------------------------------------
$svg  = '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 400 400" role="img" aria-label="">';
$svg .= '<defs>';
$svg .= '<radialGradient id="sphere" cx="36%" cy="28%" r="80%">'
      . '<stop offset="0%" stop-color="#1d6698"/><stop offset="52%" stop-color="#0e3c60"/>'
      . '<stop offset="100%" stop-color="#061726"/></radialGradient>';
$svg .= '<radialGradient id="halo" cx="50%" cy="50%" r="50%">'
      . '<stop offset="62%" stop-color="rgba(56,189,248,0)"/>'
      . '<stop offset="86%" stop-color="rgba(56,189,248,.30)"/>'
      . '<stop offset="100%" stop-color="rgba(56,189,248,0)"/></radialGradient>';
$svg .= '<clipPath id="clip"><circle cx="200" cy="200" r="150"/></clipPath>';
$svg .= '</defs>';

$svg .= '<circle cx="200" cy="200" r="198" fill="url(#halo)"/>';
$svg .= '<circle cx="200" cy="200" r="150" fill="url(#sphere)"/>';

$svg .= '<g clip-path="url(#clip)" fill="none" stroke="#38bdf8" stroke-opacity=".16">';
foreach ($lats as $l) {
    $svg .= sprintf('<ellipse cx="200" cy="%.1f" rx="%.1f" ry="%.1f"/>',
        $l['cy'], $l['rx'], $l['ry']);
}
foreach ([0, 30, 60, 90, 120, 150] as $deg) {
    $svg .= sprintf('<ellipse cx="200" cy="200" rx="%.1f" ry="150"/>',
        R * abs(cos(deg2rad($deg))));
}
$svg .= '</g>';

// Land, in three depth bands so the file carries three opacities rather than
// nine hundred.
$bands = [[0.02, 0.34, 1.05, '.30'], [0.34, 0.68, 1.55, '.58'], [0.68, 1.01, 2.05, '.90']];
$svg .= '<g clip-path="url(#clip)" fill="#7dd3fc">';
foreach ($bands as [$lo, $hi, $r, $op]) {
    $band = array_filter($dots, static fn (array $d) => $d[2] >= $lo && $d[2] < $hi);
    if ($band === []) {
        continue;
    }
    $svg .= sprintf('<g fill-opacity="%s">', $op);
    foreach ($band as [$x, $y]) {
        $svg .= sprintf('<circle cx="%.1f" cy="%.1f" r="%.2f"/>', $x, $y, $r);
    }
    $svg .= '</g>';
}
$svg .= '</g>';

$svg .= '<g clip-path="url(#clip)" fill="none" stroke="#7dd3fc" stroke-opacity=".55"'
      . ' stroke-width="1.1" stroke-dasharray="3 4" stroke-linecap="round">';
foreach ($arcs as [$x1, $y1, $cxp, $cyp, $x2, $y2]) {
    $svg .= sprintf('<path d="M%.1f %.1f Q%.1f %.1f %.1f %.1f"/>', $x1, $y1, $cxp, $cyp, $x2, $y2);
}
$svg .= '</g>';

$svg .= '<g clip-path="url(#clip)">';
foreach ($nodes as [$x, $y, $r]) {
    $svg .= sprintf('<circle cx="%.1f" cy="%.1f" r="%.1f" fill="#bae6fd" opacity=".95"/>', $x, $y, $r);
    $svg .= sprintf('<circle cx="%.1f" cy="%.1f" r="%.1f" fill="#38bdf8" opacity=".25"/>', $x, $y, $r * 2.6);
}
$svg .= '</g>';

$svg .= '<circle cx="200" cy="200" r="150" fill="none" stroke="rgba(125,211,252,.45)" stroke-width="1.4"/>';
$svg .= '</svg>';

$out = __DIR__ . '/../public/assets/img/globe.svg';
file_put_contents($out, $svg);

printf("%s\n  %d land dots, %d arcs, %d nodes, %.1f KB\n",
    $out, count($dots), count($arcs), count($nodes), strlen($svg) / 1024);
