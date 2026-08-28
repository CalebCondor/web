<?php
require_once '../config.php';
session_init();

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $serviceId   = (int)($_POST['service_id']   ?? 0);
    $serviceName = trim($_POST['service_name']  ?? '');
    $lat         = (float)($_POST['lat']        ?? 0);
    $lng         = (float)($_POST['lng']        ?? 0);

    if (!$serviceId)    { $error = 'Por favor selecciona un servicio.'; }
    elseif (!$lat || !$lng) { $error = 'Se requiere tu ubicación para encontrar notarios cercanos. Por favor permite el acceso a la ubicación.'; }
    else {
        $_SESSION['booking'] = [
            'service_id'   => $serviceId,
            'service_name' => $serviceName,
            'lat'          => $lat,
            'lng'          => $lng,
        ];
        header('Location: step2.php'); exit;
    }
}

$raw      = api_json('GET', '/servicios');
$services = isset($raw[0]) ? $raw : ($raw['data'] ?? []);

// 3 featured services — exact names as returned by the API
$featuredNames = ['affidabbit', 'poderes', 'living while'];

$featured = [];
$others   = [];
$used     = [];
foreach ($featuredNames as $target) {
    foreach ($services as $svc) {
        if (strtolower(trim($svc['nombre'] ?? '')) === $target) {
            $featured[] = $svc;
            $used[]     = (int)($svc['id_servicio'] ?? 0);
            break;
        }
    }
}
foreach ($services as $svc) {
    if (!in_array((int)($svc['id_servicio'] ?? 0), $used, true)) {
        $others[] = $svc;
    }
}

function svcIcon(string $n): string {
    $l = strtolower($n);
    if (str_contains($l, 'affidav') || str_contains($l, 'afidav')) return '<i data-lucide="pen-line" class="w-6 h-6"></i>';
    if (str_contains($l, 'escritura'))   return '<i data-lucide="scroll" class="w-6 h-6"></i>';
    if (str_contains($l, 'firma'))       return '<i data-lucide="pen-line" class="w-6 h-6"></i>';
    if (str_contains($l, 'testamento'))  return '<i data-lucide="file-text" class="w-6 h-6"></i>';
    if (str_contains($l, 'living'))      return '<i data-lucide="heart-pulse" class="w-6 h-6"></i>';
    if (str_contains($l, 'poder'))       return '<i data-lucide="scale" class="w-6 h-6"></i>';
    if (str_contains($l, 'hipoteca'))    return '<i data-lucide="home" class="w-6 h-6"></i>';
    if (str_contains($l, 'sociedad'))    return '<i data-lucide="building" class="w-6 h-6"></i>';
    if (str_contains($l, 'divorcio'))    return '<i data-lucide="briefcase" class="w-6 h-6"></i>';
    if (str_contains($l, 'legaliz'))     return '<i data-lucide="stamp" class="w-6 h-6"></i>';
    return '<i data-lucide="building-2" class="w-6 h-6"></i>';
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
  <title>Notarize — Seleccionar Servicio</title>
  <?php include '../_head.php'; ?>
</head>
<body class="bg-white md:bg-slate-200 min-h-screen flex justify-center">
<div class="w-full md:max-w-sm min-h-screen bg-slate-50 flex flex-col md:shadow-2xl relative">

  <div class="bg-[#1e3a8a] px-5 py-4 flex items-center gap-3">
    <img src="../assets/LogoS2.png" alt="Notarize" class="h-11 w-auto">
  </div>



  <div class="flex-1 w-full px-6 py-8">

    <!-- Progress stepper -->
    <nav aria-label="progress" class="py-3">
      <div class="flex items-center gap-1 mb-2">
        <div class="flex-1 h-1.5 rounded-full bg-blue-700"></div>
        <div class="flex-1 h-1.5 rounded-full bg-slate-200"></div>
        <div class="flex-1 h-1.5 rounded-full bg-slate-200"></div>
        <div class="flex-1 h-1.5 rounded-full bg-slate-200"></div>
      </div>
      <div class="flex items-center justify-between">
        <span class="text-[10px] font-bold text-blue-700 uppercase tracking-wider">Paso 1 de 4</span>
        <span class="text-xs font-extrabold text-slate-900">Servicio</span>
      </div>
    </nav>

    <h1 class="text-sm font-bold text-blue-900 uppercase tracking-wide mb-2">Seleccionar Servicio</h1>
    <h2 class="text-2xl font-extrabold text-slate-900 leading-tight mb-1">
      ¿Qué tipo de servicio<br>notarial necesitas?
    </h2>
    <p class="text-sm text-slate-500 mb-5">Selecciona el servicio que deseas completar.</p>

    <!-- Location status banner -->
    <div id="locBanner" class="rounded-xl px-4 py-3 text-sm mb-5 border bg-yellow-50 border-yellow-200 text-yellow-800 flex items-center gap-2">
      <span id="locMsg" class="flex-1 inline-flex items-center gap-1.5"><i data-lucide="loader" class="w-4 h-4"></i> Solicitando acceso a la ubicación…</span>
      <button type="button" id="locRetry" class="hidden font-bold underline whitespace-nowrap" onclick="requestLocation()">Reintentar</button>
    </div>

    <?php if ($error): ?>
      <div class="bg-red-50 border border-red-200 text-red-700 text-sm rounded-xl px-4 py-3 mb-4">
        <?= htmlspecialchars($error) ?>
      </div>
    <?php endif; ?>

    <form method="POST" id="svcForm" class="flex flex-col gap-3">
      <input type="hidden" id="hidLat"  name="lat"          value="" />
      <input type="hidden" id="hidLng"  name="lng"          value="" />
      <input type="hidden" id="hidName" name="service_name" value="" />

      <?php foreach ($featured as $i => $svc):
        $id    = (int)($svc['id_servicio'] ?? 0);
        $name  = htmlspecialchars($svc['nombre']      ?? '');
        $desc  = htmlspecialchars($svc['descripcion'] ?? '');
        $icon  = svcIcon($svc['nombre'] ?? '');
        $first = $i === 0;
        $cardCls = $first
          ? 'svc-card relative flex items-center gap-4 bg-gradient-to-br from-blue-50 via-white to-white rounded-2xl p-4 border-[1.5px] border-blue-300 border-l-[5px] border-l-blue-700 shadow-md cursor-pointer transition select-none'
          : 'svc-card flex items-center gap-4 bg-white rounded-2xl p-4 border-[1.5px] border-slate-200 cursor-pointer transition select-none';
      ?>
        <label class="<?= $cardCls ?>" data-first="<?= $first ? '1' : '0' ?>">
          <input type="radio" name="service_id" value="<?= $id ?>" class="sr-only"
                 data-name="<?= $name ?>" required />
          <?php if ($first): ?>
            <i data-lucide="star" class="absolute top-3 right-3 w-4 h-4 text-yellow-500 fill-yellow-500"></i>
          <?php endif; ?>
          <div class="icon-wrap <?= $first ? 'w-14 h-14' : 'w-12 h-12' ?> rounded-xl <?= $first ? 'bg-blue-100' : 'bg-slate-100' ?> flex items-center justify-center text-2xl shrink-0 transition">
            <?= $icon ?>
          </div>
          <div class="flex-1 min-w-0">
            <p class="card-title font-bold <?= $first ? 'text-blue-900' : 'text-slate-900' ?> text-sm transition"><?= $name ?></p>
            <?php if ($desc): ?>
              <p class="text-xs text-slate-400 mt-0.5 line-clamp-2"><?= $desc ?></p>
            <?php endif; ?>
          </div>
          <div class="radio-outer w-5 h-5 rounded-full border-2 <?= $first ? 'border-blue-400' : 'border-slate-300' ?> flex items-center justify-center shrink-0 transition">
            <div class="radio-dot w-2.5 h-2.5 rounded-full bg-blue-700 hidden"></div>
          </div>
        </label>
      <?php endforeach; ?>

      <?php if (!empty($others)): ?>
      <div id="moreServices" class="hidden flex flex-col gap-3">
        <?php foreach ($others as $svc):
          $id   = (int)($svc['id_servicio'] ?? 0);
          $name = htmlspecialchars($svc['nombre']      ?? '');
          $desc = htmlspecialchars($svc['descripcion'] ?? '');
          $icon = svcIcon($svc['nombre'] ?? '');
        ?>
          <label class="svc-card flex items-center gap-4 bg-white rounded-2xl p-4 border-[1.5px] border-slate-200 cursor-pointer transition select-none" data-first="0">
            <input type="radio" name="service_id" value="<?= $id ?>" class="sr-only"
                   data-name="<?= $name ?>" />
            <div class="icon-wrap w-12 h-12 rounded-xl bg-slate-100 flex items-center justify-center text-2xl shrink-0 transition">
              <?= $icon ?>
            </div>
            <div class="flex-1 min-w-0">
              <p class="card-title font-bold text-slate-900 text-sm transition"><?= $name ?></p>
              <?php if ($desc): ?>
                <p class="text-xs text-slate-400 mt-0.5 line-clamp-2"><?= $desc ?></p>
              <?php endif; ?>
            </div>
            <div class="radio-outer w-5 h-5 rounded-full border-2 border-slate-300 flex items-center justify-center shrink-0 transition">
              <div class="radio-dot w-2.5 h-2.5 rounded-full bg-blue-700 hidden"></div>
            </div>
          </label>
        <?php endforeach; ?>
      </div>

      <button type="button" id="toggleMore" onclick="toggleMoreServices()"
        class="w-full mt-1 border-[1.5px] border-blue-700 text-blue-700 font-bold rounded-2xl py-3 hover:bg-blue-50 active:scale-95 transition text-sm inline-flex items-center justify-center gap-1.5">
        <i data-lucide="plus-circle" class="w-4 h-4"></i>
        <span id="toggleMoreLabel">Ver más servicios</span>
      </button>
      <?php endif; ?>

      <button type="submit" id="submitBtn" disabled
        class="w-full mt-2 bg-blue-700 hover:bg-blue-800 active:scale-95 text-white font-extrabold
               rounded-2xl py-4 shadow-lg shadow-blue-700/30 transition
               disabled:opacity-40 disabled:pointer-events-none">
        Continuar →
      </button>
    </form>
  </div>

  <script>
    const btn     = document.getElementById('submitBtn');
    const hidLat  = document.getElementById('hidLat');
    const hidLng  = document.getElementById('hidLng');
    const hidName = document.getElementById('hidName');
    const banner  = document.getElementById('locBanner');
    const locMsg  = document.getElementById('locMsg');
    const locRetry= document.getElementById('locRetry');

    let locOk = false, svcOk = false, locAsked = false;
    function checkReady() { btn.disabled = !svcOk; }

    function setBanner(msg, kind, showRetry = false) {
      locMsg.innerHTML = msg;
      if (window.lucide) lucide.createIcons({ nameAttr: 'data-lucide' });
      const colors = {
        warn:  'bg-yellow-50 border-yellow-200 text-yellow-800',
        ok:    'bg-green-50 border-green-200 text-green-700',
        err:   'bg-red-50 border-red-200 text-red-600',
        info:  'bg-blue-50 border-blue-200 text-blue-700',
      };
      banner.className = 'rounded-xl px-4 py-3 text-sm mb-5 border flex items-center gap-2 ' + (colors[kind] || colors.warn);
      locRetry.classList.toggle('hidden', !showRetry);
    }

    function requestLocation() {
      if (!('geolocation' in navigator)) {
        setBanner('<span class="inline-flex items-center gap-1.5"><i data-lucide="alert-triangle" class="w-4 h-4"></i> Tu navegador no soporta geolocalización. Puedes continuar sin ella.</span>', 'err', false);
        return;
      }
      locAsked = true;
      setBanner('<span class="inline-flex items-center gap-1.5"><i data-lucide="loader" class="w-4 h-4"></i> Solicitando acceso a la ubicación…</span>', 'warn', false);

      navigator.geolocation.getCurrentPosition(
        pos => {
          hidLat.value = pos.coords.latitude;
          hidLng.value = pos.coords.longitude;
          locOk = true;
          setBanner('<span class="inline-flex items-center gap-1.5"><i data-lucide="map-pin" class="w-4 h-4"></i> Ubicación detectada — listo para buscar.</span>', 'ok', false);
          checkReady();
        },
        err => {
          const msgs = {
            1: '<i data-lucide="map-pin" class="inline w-4 h-4 mr-1"></i>Permiso denegado. Puedes continuar sin ubicación o reintentar.',
            2: '<i data-lucide="map-pin" class="inline w-4 h-4 mr-1"></i>Posición no disponible. Continúa sin ubicación o reintenta.',
            3: '<i data-lucide="map-pin" class="inline w-4 h-4 mr-1"></i>Tardó demasiado. Reintenta o continúa sin ubicación.',
          };
          setBanner(msgs[err.code] || '<i data-lucide="map-pin" class="inline w-4 h-4 mr-1"></i>No se pudo obtener la ubicación.', 'err', true);
        },
        { timeout: 15000, enableHighAccuracy: false, maximumAge: 60000 }
      );
    }

    requestLocation();

    // Toggle "Ver más servicios"
    function toggleMoreServices() {
      const wrap  = document.getElementById('moreServices');
      const label = document.getElementById('toggleMoreLabel');
      const btn   = document.getElementById('toggleMore');
      const open  = wrap.classList.toggle('hidden');
      label.textContent = open ? 'Ver más servicios' : 'Ver menos';
      btn.querySelector('i').setAttribute('data-lucide', open ? 'plus-circle' : 'minus-circle');
      if (window.lucide) lucide.createIcons({ nameAttr: 'data-lucide' });
    }

    // Service selection styling
    document.querySelectorAll('.svc-card input[type="radio"]').forEach(radio => {
      radio.addEventListener('change', () => {
        document.querySelectorAll('.svc-card').forEach(c => {
          c.classList.remove('border-blue-600', 'bg-blue-50');
          if (c.dataset.first !== '1') {
            c.querySelector('.icon-wrap').classList.remove('bg-blue-100');
          }
          c.querySelector('.card-title').classList.remove('text-blue-700');
          c.querySelector('.radio-outer').classList.remove('border-blue-700');
          c.querySelector('.radio-dot').classList.add('hidden');
        });
        const card = radio.closest('.svc-card');
        card.classList.add('border-blue-600', 'bg-blue-50');
        card.querySelector('.icon-wrap').classList.add('bg-blue-100');
        card.querySelector('.card-title').classList.add('text-blue-700');
        card.querySelector('.radio-outer').classList.add('border-blue-700');
        card.querySelector('.radio-dot').classList.remove('hidden');
        hidName.value = radio.dataset.name;
        svcOk = true;
        checkReady();
        btn.scrollIntoView({ behavior: 'smooth', block: 'center' });
      });
    });
  </script>

  <?php include '../_nav.php'; ?>
</div>
</body>
</html>
