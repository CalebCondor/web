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

function svcIcon(string $n): string {
    $l = strtolower($n);
    if (strpos($l, 'escritura')  !== false) return '📜';
    if (strpos($l, 'firma')        !== false) return '✍️';
    if (strpos($l, 'testamento')   !== false) return '📋';
    if (strpos($l, 'poder')        !== false) return '⚖️';
    if (strpos($l, 'hipoteca')     !== false) return '🏠';
    if (strpos($l, 'sociedad')     !== false) return '🏢';
    if (strpos($l, 'divorcio')     !== false) return '💼';
    return '🏛️';
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

  <div class="bg-[#1e3a8a] px-5 py-4 sticky top-0 z-50 flex items-center gap-3">
    <div class="w-6"></div>
    <span class="text-white font-extrabold text-base">Seleccionar Servicio</span>
  </div>



  <div class="flex-1 w-full px-6 py-8">

    <h2 class="text-2xl font-extrabold text-slate-900 leading-tight mb-1">
      ¿Qué tipo de servicio<br>notarial necesitas?
    </h2>
    <p class="text-sm text-slate-500 mb-5">Selecciona el servicio que deseas completar.</p>

    <!-- Location status banner -->
    <div id="locBanner" class="rounded-xl px-4 py-3 text-sm mb-5 border bg-yellow-50 border-yellow-200 text-yellow-800">
      ⏳ Solicitando acceso a la ubicación…
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

      <?php if (empty($services)): ?>
        <p class="text-center text-slate-400 py-10 text-sm">No hay servicios disponibles en este momento.</p>
      <?php else: ?>
        <?php foreach ($services as $svc):
          $id   = $svc['id_servicio'] ?? 0;
          $name = htmlspecialchars($svc['nombre']      ?? '');
          $desc = htmlspecialchars($svc['descripcion'] ?? '');
          $icon = svcIcon($svc['nombre'] ?? '');
        ?>
          <label class="svc-card flex items-center gap-4 bg-white rounded-2xl p-4 border-[1.5px] border-slate-200
                         cursor-pointer transition select-none">
            <input type="radio" name="service_id" value="<?= $id ?>" class="sr-only"
                   data-name="<?= $name ?>" required />
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

    let locOk = false, svcOk = false;
    function checkReady() { btn.disabled = !(locOk && svcOk); }

    // Geolocation
    if (!navigator.geolocation) {
      banner.textContent = '⚠️ Tu navegador no soporta geolocalización.';
      banner.className = 'rounded-xl px-4 py-3 text-sm mb-5 border bg-red-50 border-red-200 text-red-600';
    } else {
      navigator.geolocation.getCurrentPosition(
        pos => {
          hidLat.value = pos.coords.latitude;
          hidLng.value = pos.coords.longitude;
          locOk = true;
          banner.textContent = '📍 Ubicación detectada — listo para buscar.';
          banner.className = 'rounded-xl px-4 py-3 text-sm mb-5 border bg-green-50 border-green-200 text-green-700';
          checkReady();
        },
        () => {
          banner.textContent = '📍 Acceso a la ubicación denegado. Por favor habilítalo en la configuración de tu navegador.';
          banner.className = 'rounded-xl px-4 py-3 text-sm mb-5 border bg-red-50 border-red-200 text-red-600';
        },
        { timeout: 10000 }
      );
    }

    // Service selection styling
    document.querySelectorAll('.svc-card input[type="radio"]').forEach(radio => {
      radio.addEventListener('change', () => {
        document.querySelectorAll('.svc-card').forEach(c => {
          c.classList.remove('border-blue-600', 'bg-blue-50');
          c.querySelector('.icon-wrap').classList.remove('bg-blue-100');
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
