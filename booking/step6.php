<?php
require_once '../config.php';
session_init();
$b = $_SESSION['booking'] ?? [];
if (empty($b['notary'])) { header('Location: step3.php'); exit; }

$notary   = $b['notary'];
$userLat  = (float)($b['lat']      ?? 0);
$userLng  = (float)($b['lng']      ?? 0);
$ntLat    = (float)($notary['lat'] ?? $notary['_lat'] ?? 0);
$ntLng    = (float)($notary['lng'] ?? $notary['_lng'] ?? 0);
$address  = $notary['direccion']   ?? '';
$ntName   = $notary['nombre']      ?? 'Notario';
$service  = htmlspecialchars($b['service_name'] ?? '');
$date     = htmlspecialchars($b['date']         ?? '');
$time     = htmlspecialchars($b['time']         ?? '');
$domicilio = (int)($b['domicilio'] ?? 0);
$modLabel = $domicilio ? 'Visita a domicilio' : 'En la notaría';
$invoice  = $b['pago']['numero_factura'] ?? $b['pago']['invoice'] ?? null;
$mapsUrl  = "https://www.google.com/maps/dir/?api=1&origin={$userLat},{$userLng}&destination={$ntLat},{$ntLng}&travelmode=driving";
?><!DOCTYPE html>
<html lang="es">
<head>
  <title>Notarize — Ruta al Notario</title>
  <?php include '../_head.php'; ?>
  <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css"/>
  <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
  <style>
    #map { height: 50vh; min-height: 200px; }
    .leaflet-control-zoom { margin-bottom:10px!important; margin-right:10px!important; }
  </style>
</head>
<body class="bg-white md:bg-slate-200 min-h-screen flex justify-center">
<div class="w-full md:max-w-sm min-h-screen bg-white flex flex-col md:shadow-2xl relative overflow-hidden">

  <div class="bg-[#1e3a8a] px-5 py-4 sticky top-0 z-50 flex items-center gap-3">
    <a href="step5.php" class="text-blue-300 text-xl leading-none">‹</a>
    <span class="text-white font-extrabold text-base flex-1">Ruta al Notario</span>
    <a href="<?= htmlspecialchars($mapsUrl) ?>" target="_blank"
       class="bg-white/20 text-white text-xs font-bold px-3 py-1.5 rounded-full hover:bg-white/30 transition whitespace-nowrap">
      Abrir Mapas
    </a>
  </div>

  <!-- Map -->
  <div id="map" class="w-full shrink-0"></div>

  <!-- Route info bar (shown after OSRM response) -->
  <div id="routeBar" class="hidden bg-blue-700 px-4 py-2 text-white text-xs font-semibold text-center"></div>

  <div class="flex-1 overflow-y-auto px-5 py-5 flex flex-col gap-4">

    <!-- Confirmed badge -->
    <div class="flex items-center gap-3 bg-green-50 border border-green-200 rounded-2xl px-4 py-3.5">
      <div class="w-10 h-10 rounded-full bg-green-500 flex items-center justify-center shrink-0">
        <svg class="w-5 h-5 text-white" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24">
          <path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"/>
        </svg>
      </div>
      <div>
        <p class="font-extrabold text-slate-900 text-sm">¡Cita confirmada!</p>
        <p class="text-xs text-slate-500 mt-0.5">Dirígete al notario a la hora indicada.</p>
      </div>
    </div>

    <!-- Notary detail card -->
    <div class="bg-white rounded-2xl border border-slate-200 overflow-hidden shadow-sm">
      <div class="flex items-center gap-3 px-4 py-3.5 border-b border-slate-100">
        <div class="w-10 h-10 rounded-full bg-blue-700 flex items-center justify-center font-extrabold text-white text-sm shrink-0">
          <?= strtoupper(mb_substr($ntName, 0, 1)) ?>
        </div>
        <div class="flex-1 min-w-0">
          <p class="font-extrabold text-slate-900 text-sm truncate"><?= htmlspecialchars($ntName) ?></p>
          <?php if ($address): ?>
            <p class="text-xs text-slate-400 truncate mt-0.5 inline-flex items-center gap-1"><i data-lucide="map-pin" class="w-3 h-3 shrink-0"></i> <?= htmlspecialchars($address) ?></p>
          <?php endif; ?>
        </div>
      </div>
      <div class="px-4 py-3 grid grid-cols-2 gap-x-4 gap-y-2.5">
        <div>
          <p class="text-[10px] text-slate-400 font-semibold uppercase tracking-wide">Servicio</p>
          <p class="font-bold text-slate-900 text-xs mt-0.5 truncate"><?= $service ?></p>
        </div>
        <div>
          <p class="text-[10px] text-slate-400 font-semibold uppercase tracking-wide">Cita</p>
          <p class="font-bold text-slate-900 text-xs mt-0.5"><?= $date ?> <?= $time ?></p>
        </div>
        <div>
          <p class="text-[10px] text-slate-400 font-semibold uppercase tracking-wide">Ubicación</p>
          <p class="font-bold text-slate-900 text-xs mt-0.5 inline-flex items-center gap-1.5">
            <i data-lucide="<?= $domicilio ? 'home' : 'building-2' ?>" class="w-3.5 h-3.5"></i>
            <?= htmlspecialchars($domicilio ? 'Visita a domicilio' : 'En la notaría') ?>
          </p>
        </div>
        <?php if ($invoice): ?>
        <div>
          <p class="text-[10px] text-slate-400 font-semibold uppercase tracking-wide">Factura</p>
          <p class="font-extrabold text-blue-700 text-xs mt-0.5"><?= htmlspecialchars($invoice) ?></p>
        </div>
        <?php endif; ?>
      </div>
    </div>

    <!-- Action buttons -->
    <div class="flex gap-3 pb-2">
      <a href="<?= htmlspecialchars($mapsUrl) ?>" target="_blank"
        class="flex-1 inline-flex items-center justify-center gap-1.5 border-[1.5px] border-blue-700 text-blue-700 font-bold rounded-2xl py-3.5 text-sm hover:bg-blue-50 active:scale-95 transition">
        <i data-lucide="map" class="w-4 h-4"></i> Abrir en Mapas
      </a>
      <a href="/step1.php"
        class="flex-1 text-center bg-blue-700 hover:bg-blue-800 active:scale-95 text-white font-bold rounded-2xl py-3.5 text-sm shadow-lg shadow-blue-700/30 transition">
        Subir Documento →
      </a>
    </div>

  </div>
  <?php include '../_nav.php'; ?>
</div>

<script>
const userLat = <?= $userLat ?>, userLng = <?= $userLng ?>;
const ntLat   = <?= $ntLat ?>,   ntLng   = <?= $ntLng ?>;

const map = L.map('map', { zoomControl: true, scrollWheelZoom: false });
L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
  attribution: '&copy; <a href="https://osm.org">OpenStreetMap</a>'
}).addTo(map);

const pin = (color, sz) => L.divIcon({
  html: `<div style="width:${sz}px;height:${sz}px;background:${color};border:3px solid #fff;border-radius:50%;box-shadow:0 3px 8px rgba(0,0,0,.3)"></div>`,
  iconSize:[sz,sz], iconAnchor:[sz/2,sz/2], className:''
});

L.marker([userLat,userLng],{icon:pin('#22c55e',16)}).addTo(map).bindPopup('<b>Tu ubicación</b>');

if (ntLat && ntLng) {
  L.marker([ntLat,ntLng],{icon:pin('#1d4ed8',20)}).addTo(map).bindPopup('<b>Notaría</b>');

  // Real driving route via OSRM — free, no API key needed
  fetch(`https://router.project-osrm.org/route/v1/driving/${userLng},${userLat};${ntLng},${ntLat}?overview=full&geometries=geojson`)
    .then(r => r.json())
    .then(d => {
      if (d.code === 'Ok' && d.routes.length) {
        const route = d.routes[0];
        const line  = L.polyline(
          route.geometry.coordinates.map(c => [c[1], c[0]]),
          { color:'#1d4ed8', weight:5, opacity:.85, lineCap:'round', lineJoin:'round' }
        ).addTo(map);
        map.fitBounds(line.getBounds(), { padding:[40,40] });
        const km   = (route.distance / 1000).toFixed(1);
        const mins = Math.round(route.duration / 60);
        const bar  = document.getElementById('routeBar');
        bar.innerHTML = `<span class="inline-flex items-center justify-center gap-1.5"><i data-lucide="car" class="w-3.5 h-3.5"></i> ${km} km  ·  ~${mins} min en auto</span>`;
        if (window.lucide) lucide.createIcons();
        bar.classList.remove('hidden');
      } else { fallback(); }
    })
    .catch(fallback);
} else {
  map.setView([userLat || 18.4655, userLng || -66.1057], 13);
}

function fallback() {
  const line = L.polyline([[userLat,userLng],[ntLat,ntLng]],
    { color:'#1d4ed8', weight:3, dashArray:'8,6', opacity:.7 }).addTo(map);
  map.fitBounds(line.getBounds(), { padding:[40,40] });
}
</script>
</body>
</html>