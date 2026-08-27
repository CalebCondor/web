<?php
require_once '../config.php';
session_init();
$b = $_SESSION['booking'] ?? [];
if (empty($b['service_id']) || empty($b['date'])) { header('Location: step1.php'); exit; }

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $notaryJson = $_POST['notary_json'] ?? '';
    $notary     = json_decode($notaryJson, true);
    if ($notary) {
        $_SESSION['booking']['notary']   = $notary;
        // Store original base fee separately so step4 always has the unmodified price
        $_SESSION['booking']['fee_base'] = (float)($notary['tarifa_consulta'] ?? 0);
        // Auth check happens here: redirect to login if not logged in
        $dest = empty($_SESSION['client_token']) ? '../auth.php' : 'step4.php';
        header("Location: $dest"); exit;
    }
}

$radius     = (float)($_GET['radius'] ?? $b['radius'] ?? 5);
$RADII      = [0.5, 1, 2, 5, 10];
$HOME_FEE   = 80;
$domicilio  = (int)($b['domicilio'] ?? 0);

// Show summary modal only when arriving from step 2's POST submit
$showModal = !empty($b['show_modal']);
if ($showModal) {
    unset($_SESSION['booking']['show_modal']);
}

// Search notaries via API
$qs = http_build_query([
    'id_servicio' => $b['service_id'],
    'fecha'       => $b['date'],
    'lat'         => $b['lat'],
    'lng'         => $b['lng'],
    'radio'       => $radius,
    'domicilio'   => $domicilio,
]);
$res      = api_json('GET', "/notarias/buscar?$qs");
$notaries = $res['success'] ? ($res['data'] ?? []) : [];
    $apiErr   = $res['success'] ? '' : ($res['message'] ?? 'No se pudieron cargar los notarios.');

function distLabel($km): string {
    if ($km === null) return '?';
    return $km < 1 ? round($km * 1000).' m de distancia' : number_format($km, 1).' km de distancia';
}

// Format date for the modal summary
$dateLabel = '';
if (!empty($b['date'])) {
    $meses = ['Ene','Feb','Mar','Abr','May','Jun','Jul','Ago','Sep','Oct','Nov','Dic'];
    $dias  = ['Dom','Lun','Mar','Mié','Jue','Vie','Sáb'];
    $ts       = strtotime($b['date']);
    $todayT   = strtotime(date('Y-m-d'));
    $tomT     = strtotime('+1 day', $todayT);
    $base     = $dias[(int)date('w', $ts)] . ', ' . $meses[(int)date('n', $ts) - 1] . ' ' . date('j', $ts);
    if ($ts === $todayT)      $dateLabel = 'Hoy, '     . $base;
    elseif ($ts === $tomT)    $dateLabel = 'Mañana, '  . $base;
    else                      $dateLabel = $base;
}

// Format time HH:MM → H:MM AM/PM
$timeLabel = '';
if (!empty($b['time'])) {
    $parts = explode(':', $b['time']);
    $h = (int)($parts[0] ?? 0);
    $m = $parts[1] ?? '00';
    $ampm = $h >= 12 ? 'PM' : 'AM';
    $h12  = $h % 12; if ($h12 === 0) $h12 = 12;
    $timeLabel = $h12 . ':' . $m . ' ' . $ampm;
}

$locationLabel = $domicilio ? 'A tu domicilio' : 'En la notaría';
?>
<!DOCTYPE html>
<html lang="es">
<head>
  <title>Notarize — Notarios Disponibles</title>
  <?php include '../_head.php'; ?>
</head>
<body class="bg-white md:bg-slate-200 min-h-screen flex justify-center">
<div class="w-full md:max-w-sm min-h-screen bg-slate-50 flex flex-col md:shadow-2xl relative overflow-hidden">

  <div class="bg-[#1e3a8a] px-5 py-4 sticky top-0 z-50 flex items-center gap-3">
    <img src="../assets/LogoS2.png" alt="Notarize" class="h-11 w-auto brightness-0 invert">
  </div>





  <!-- Page intro -->
  <div class="w-full max-w-sm mx-auto px-4 pt-5 pb-3">
    <h1 class="text-sm font-bold text-blue-900 uppercase tracking-wide mb-2">Notarios Disponibles</h1>
    <h2 class="text-lg font-extrabold text-slate-900 mb-2">¡Listo! Ahora vamos a encontrar tu notario</h2>
    <p class="text-sm text-slate-500">Escoge una opción para encontrar el notario más cerca de ti.</p>
  </div>

  <!-- Radius filter -->
  <div class="w-full max-w-sm mx-auto px-4 pb-3">
    <div class="bg-white border border-slate-200 rounded-2xl p-4 shadow-sm">
      <div class="flex items-center gap-1.5 text-slate-500 mb-2">
        <i data-lucide="map-pin" class="w-3.5 h-3.5"></i>
        <span class="text-[11px] font-extrabold uppercase tracking-wider">Radio</span>
      </div>
      <form method="get" id="radiusForm" class="flex items-center gap-1.5 bg-slate-100 rounded-full p-1">
        <?php foreach ($RADII as $i => $r):
          $active = $r == $radius;
        ?>
          <label class="flex-1 cursor-pointer text-center px-2 py-1.5 rounded-full text-xs font-bold transition select-none
                 <?= $active
                   ? 'bg-blue-700 text-white shadow-sm'
                   : 'text-slate-600 hover:bg-white hover:text-blue-700' ?>">
            <input type="radio" name="radius" value="<?= $r ?>" class="sr-only"
              <?= $active ? 'checked' : '' ?> onchange="document.getElementById('radiusForm').submit()">
            <?= $r < 1 ? ($r * 1000).' m' : $r.' km' ?>
          </label>
        <?php endforeach; ?>
      </form>

      <button type="submit" form="radiusForm"
        class="w-full mt-3 bg-blue-700 hover:bg-blue-800 active:scale-95 text-white font-extrabold
               rounded-2xl py-4 shadow-lg shadow-blue-700/30 transition">
        Buscar →
      </button>
    </div>
  </div>

  <!-- Notaries list -->
  <div class="w-full max-w-sm mx-auto px-4 py-4 flex flex-col gap-3">

    <?php if ($apiErr): ?>
      <div class="flex flex-col items-center py-16 gap-3">
        <i data-lucide="alert-triangle" class="w-12 h-12 text-red-400"></i>
        <p class="text-red-500 text-sm text-center"><?= htmlspecialchars($apiErr) ?></p>
      </div>

    <?php elseif (empty($notaries)): ?>
      <div class="flex flex-col items-center py-16 gap-3">
        <i data-lucide="search-x" class="w-14 h-14 text-slate-300"></i>
        <p class="font-bold text-slate-700">No se encontraron notarios</p>
        <p class="text-sm text-slate-400">Intenta aumentar el radio de búsqueda arriba.</p>
      </div>

    <?php else: ?>
      <form method="POST" class="flex flex-col gap-3" id="notaryForm">
        <input type="hidden" id="hidNotary" name="notary_json" value="" />

        <?php foreach ($notaries as $i => $n):
          $fee  = (float)($n['tarifa_consulta'] ?? 0);
          $total = $domicilio ? $fee + $HOME_FEE : $fee;
          $dist  = distLabel($n['distancia_km'] ?? null);
          $avail = !empty($n['horario_dia']);
          $json  = htmlspecialchars(json_encode($n), ENT_QUOTES);
        ?>
          <label class="notary-card flex items-center gap-3 bg-white rounded-2xl p-4 border-[1.5px] border-slate-200 cursor-pointer transition"
                 data-json='<?= $json ?>'>
            <input type="radio" name="_notary" value="<?= $i ?>" class="sr-only" required />

            <div class="n-badge w-10 h-10 rounded-xl bg-slate-100 flex items-center justify-center font-extrabold text-slate-500 shrink-0 transition">
              <?= $i + 1 ?>
            </div>

            <div class="flex-1 min-w-0">
              <div class="flex justify-between items-center mb-1.5">
                <span class="text-sm font-bold text-slate-700"><?= htmlspecialchars($dist) ?></span>
                <span class="text-sm font-extrabold text-slate-900"><?= $n['moneda'] ?? 'USD' ?> <?= number_format($fee, 2) ?></span>
              </div>
              <div class="flex flex-wrap gap-1.5">
                <?php if ($avail): ?>
                  <span class="inline-flex items-center gap-1 bg-green-50 border border-green-200 text-green-700 text-[10px] font-bold rounded-full px-2 py-0.5">
                    <i data-lucide="clock" class="w-3 h-3"></i>
                    <?= substr($n['horario_dia']['hora_inicio'] ?? '', 0, 5) ?>–<?= substr($n['horario_dia']['hora_fin'] ?? '', 0, 5) ?>
                  </span>
                <?php endif; ?>
                <span class="inline-flex items-center gap-1 bg-green-50 border border-green-200 text-green-700 text-[10px] font-bold rounded-full px-2 py-0.5">
                  <i data-lucide="check" class="w-3 h-3"></i> Disponible
                </span>
                <?php if ($n['tramites_domicilio'] ?? false): ?>
                  <span class="inline-flex items-center gap-1 bg-slate-100 text-slate-600 text-[10px] font-bold rounded-full px-2 py-0.5">
                    <i data-lucide="home" class="w-3 h-3"></i> A domicilio
                  </span>
                <?php endif; ?>
              </div>
            </div>

            <div class="n-radio w-5 h-5 rounded-full border-2 border-slate-300 flex items-center justify-center shrink-0">
              <div class="n-dot w-2.5 h-2.5 rounded-full bg-blue-700 hidden"></div>
            </div>
          </label>
        <?php endforeach; ?>

        </form>

        <button type="button" id="continueBtn" disabled
          onclick="openSummary()"
          class="w-full mt-2 bg-blue-300 cursor-not-allowed text-white font-extrabold rounded-2xl py-4 transition text-base">
          Selecciona un notario
        </button>
      <?php endif; ?>
  </div>

  <!-- Booking Summary + Price Modal -->
  <div id="priceModal" class="hidden fixed inset-0 z-50 flex items-end justify-center bg-black/50 px-4 pb-6"
       onclick="if(event.target===this) closeModal()">
    <div class="bg-white rounded-2xl w-full max-w-sm shadow-2xl overflow-hidden">
      <div class="bg-blue-700 px-5 py-3.5 flex items-center gap-2">
        <i data-lucide="clipboard-list" class="w-5 h-5 text-white"></i>
        <p class="text-white font-extrabold text-base">Resumen de tu reserva</p>
      </div>

      <div class="px-5 py-4 flex flex-col gap-3.5">
        <!-- Booking details -->
        <div class="flex flex-col gap-2.5">
          <?php if (!empty($b['service_name'])): ?>
          <div class="flex items-start gap-2.5">
            <div class="w-8 h-8 rounded-lg bg-blue-100 text-blue-700 flex items-center justify-center shrink-0">
              <i data-lucide="file-text" class="w-4 h-4"></i>
            </div>
            <div class="min-w-0">
              <p class="text-[10px] font-bold text-slate-400 uppercase tracking-wider">Servicio</p>
              <p class="text-sm font-bold text-slate-900 leading-snug"><?= htmlspecialchars($b['service_name']) ?></p>
            </div>
          </div>
          <?php endif; ?>

          <?php if ($dateLabel): ?>
          <div class="flex items-start gap-2.5">
            <div class="w-8 h-8 rounded-lg bg-blue-100 text-blue-700 flex items-center justify-center shrink-0">
              <i data-lucide="calendar" class="w-4 h-4"></i>
            </div>
            <div class="min-w-0">
              <p class="text-[10px] font-bold text-slate-400 uppercase tracking-wider">Fecha</p>
              <p class="text-sm font-bold text-slate-900"><?= htmlspecialchars($dateLabel) ?></p>
            </div>
          </div>
          <?php endif; ?>

          <?php if ($timeLabel): ?>
          <div class="flex items-start gap-2.5">
            <div class="w-8 h-8 rounded-lg bg-blue-100 text-blue-700 flex items-center justify-center shrink-0">
              <i data-lucide="clock" class="w-4 h-4"></i>
            </div>
            <div class="min-w-0">
              <p class="text-[10px] font-bold text-slate-400 uppercase tracking-wider">Hora</p>
              <p class="text-sm font-bold text-slate-900"><?= htmlspecialchars($timeLabel) ?></p>
            </div>
          </div>
          <?php endif; ?>

          <div class="flex items-start gap-2.5">
            <div class="w-8 h-8 rounded-lg bg-blue-100 text-blue-700 flex items-center justify-center shrink-0">
              <i data-lucide="<?= $domicilio ? 'home' : 'building-2' ?>" class="w-4 h-4"></i>
            </div>
            <div class="min-w-0">
              <p class="text-[10px] font-bold text-slate-400 uppercase tracking-wider">Lugar</p>
              <p class="text-sm font-bold text-slate-900"><?= htmlspecialchars($locationLabel) ?></p>
            </div>
          </div>
        </div>

        <!-- Divider -->
        <div class="border-t border-dashed border-slate-200"></div>

        <!-- Price details -->
        <div class="flex flex-col gap-2 text-sm">
          <div class="flex justify-between">
            <span class="text-slate-500">Tarifa del notario</span>
            <span id="mBase" class="font-semibold text-slate-900">—</span>
          </div>
          <div id="mHomeFee" class="hidden flex justify-between items-center">
            <span class="text-slate-500 inline-flex items-center gap-1.5">
              <i data-lucide="home" class="w-3.5 h-3.5"></i> Cargo por visita a domicilio
            </span>
            <span id="mHomeFeeVal" class="font-semibold text-orange-600">—</span>
          </div>
          <div class="flex justify-between pt-2.5 border-t border-slate-200">
            <span class="font-extrabold text-slate-900">Total</span>
            <span id="mTotal" class="font-extrabold text-blue-700 text-lg">—</span>
          </div>
        </div>
      </div>

      <div class="flex gap-3 px-5 pb-5">
        <button type="button" onclick="closeModal()"
          class="flex-1 border-[1.5px] border-slate-300 text-slate-600 font-semibold rounded-2xl py-3.5 hover:bg-slate-50 active:scale-95 transition text-sm">
          Cerrar
        </button>
        <form method="POST" class="flex-1">
          <input type="hidden" name="notary_json" id="modalNotaryField" value="" />
          <button type="submit" id="confirmBtn" disabled
            onclick="document.getElementById('modalNotaryField').value=document.getElementById('hidNotary').value"
            class="w-full bg-blue-300 cursor-not-allowed text-white font-extrabold rounded-2xl py-3.5 transition text-sm">
            Selecciona un notario
          </button>
        </form>
      </div>
    </div>
  </div>

  <script>
    const hidNotary   = document.getElementById('hidNotary');
    const HOME_FEE    = <?= $HOME_FEE ?>;
    const domicilio   = <?= $domicilio ?>;

    // Auto-show booking summary modal only when arriving fresh from step 2's POST
    <?php if ($showModal): ?>
    document.getElementById('mBase').textContent  = '—';
    document.getElementById('mTotal').textContent = '—';
    document.getElementById('mHomeFee').classList.add('hidden');
    document.getElementById('priceModal').classList.remove('hidden');
    if (window.lucide) lucide.createIcons();
    <?php endif; ?>

    document.querySelectorAll('.notary-card input').forEach(radio => {
      radio.addEventListener('change', () => {
        // Reset all cards
        document.querySelectorAll('.notary-card').forEach(c => {
          c.classList.remove('border-blue-600','bg-blue-50');
          c.querySelector('.n-badge').classList.remove('bg-blue-700','text-white');
          c.querySelector('.n-badge').classList.add('bg-slate-100','text-slate-500');
          c.querySelector('.n-radio').classList.remove('border-blue-700');
          c.querySelector('.n-dot').classList.add('hidden');
        });
        // Highlight selected card
        const card = radio.closest('.notary-card');
        card.classList.add('border-blue-600','bg-blue-50');
        card.querySelector('.n-badge').classList.add('bg-blue-700','text-white');
        card.querySelector('.n-badge').classList.remove('bg-slate-100','text-slate-500');
        card.querySelector('.n-radio').classList.add('border-blue-700');
        card.querySelector('.n-dot').classList.remove('hidden');

        hidNotary.value = card.dataset.json;

        // Enable the continue button
        const contBtn = document.getElementById('continueBtn');
        contBtn.disabled = false;
        contBtn.className = 'w-full bg-blue-700 hover:bg-blue-800 active:scale-95 text-white font-extrabold rounded-2xl py-4 shadow-lg shadow-blue-700/30 transition text-base';
      });
    });

    function openSummary() {
      if (!hidNotary.value) return;
      const card = document.querySelector('.notary-card input:checked').closest('.notary-card');
      const n    = JSON.parse(card.dataset.json);
      const fee  = parseFloat(n.tarifa_consulta ?? 0);
      const cur  = n.moneda ?? 'USD';
      const total = domicilio ? fee + HOME_FEE : fee;

      document.getElementById('mBase').textContent  = cur + ' ' + fee.toFixed(2);
      document.getElementById('mTotal').textContent = cur + ' ' + total.toFixed(2);
      document.getElementById('mHomeFee').classList.toggle('hidden', !domicilio);
      document.getElementById('mHomeFeeVal').textContent = cur + ' ' + HOME_FEE.toFixed(2);
      document.getElementById('modalNotaryField').value = hidNotary.value;

      document.getElementById('priceModal').classList.remove('hidden');
    }

    function closeModal() {
      document.getElementById('priceModal').classList.add('hidden');
    }

    // Backdrop click on the page deselects card and resets continue button
    document.getElementById('priceModal').addEventListener('click', (e) => {
      if (e.target.id !== 'priceModal') return;
      closeModal();
    });
  </script>

  <?php include '../_nav.php'; ?>
</div>
</body>
</html>
