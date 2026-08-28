<?php
require_once '../config.php';
session_init();

// TEST MODE: precarga datos de demo si ?test=1 (para ver step5 sin pasar por todo el flujo)
if (isset($_GET['test']) && $_GET['test'] === '1') {
    $_SESSION['booking'] = [
        'service_name' => 'Servicio Demo',
        'service_id'   => 1,
        'date'         => date('Y-m-d', strtotime('+3 days')),
        'time'         => '10:00',
        'domicilio'    => 0,
        'fee_base'     => 150,
        'notary'       => [
            'nombre'          => 'Notaría Demo',
            'id_abogado'      => 1,
            'moneda'          => 'USD',
            'tarifa_consulta' => 150,
            'horario'         => 'Lun-Vie 9:00-17:00',
            'direccion'       => 'Calle Demo 123',
        ],
        'pago' => ['id_transaccion' => 'DEMO123', 'numero_factura' => 'NTZ00001'],
    ];
    $_SESSION['user'] = ['id_cliente' => 1, 'nombre' => 'Cliente Demo'];
    $_SESSION['client_token'] = 'demo';
}

$b = $_SESSION['booking'] ?? [];
if (empty($b['notary'])) { header('Location: step3.php'); exit; }

$notary   = $b['notary'];
$pago     = $b['pago']           ?? [];
$service  = $b['service_name']   ?? '';
$date     = $b['date']           ?? '';
$time     = $b['time']           ?? '';
$currency = $notary['moneda']    ?? 'USD';
$domicilio = (int)($b['domicilio'] ?? 0);
$homeFee   = $domicilio ? 80 : 0;
$fee       = isset($b['fee_base'])
    ? (float)$b['fee_base']
    : max(0, (float)($notary['tarifa_consulta'] ?? 0) - $homeFee);
$total     = $fee + $homeFee;

// Use real payment data or generate demo values
$txId      = $pago['id_transaccion']  ?? $pago['transaction_id'] ?? 'LOCAL_' . time() . rand(10, 99);
$invoice   = $pago['numero_factura']  ?? $pago['invoice']        ?? 'NTZ' . str_pad((string)rand(1, 9999), 5, '0', STR_PAD_LEFT);
$paidAtRaw = $pago['fecha_pago']      ?? $pago['created_at']     ?? date('Y-m-d H:i:s');
$paidAt    = strtotime($paidAtRaw) ? date('M j, Y g:i A', strtotime($paidAtRaw)) : date('M j, Y g:i A');
$notaryName  = $notary['nombre']             ?? 'Notario seleccionado';
$horario     = $notary['horario']            ?? '';
$direccion   = $notary['direccion']          ?? '';
$domicilio   = (int)($b['domicilio']        ?? 0);
$modLabel    = $domicilio ? 'Visita a domicilio' : 'En la notaría';

// Animación de éxito deshabilitada (el video no funciona).
$showSuccessAnim = false;
?>
<!DOCTYPE html>
<html lang="es">
<head>
  <title>Notarize — Pago Confirmado</title>
  <?php include '../_head.php'; ?>
</head>
<body class="bg-white md:bg-slate-200 min-h-screen flex justify-center">

<?php if ($showSuccessAnim): ?>
<!-- Success animation overlay -->
<div id="successOverlay" class="fixed inset-0 z-[100] bg-white/95 backdrop-blur-sm flex items-center justify-center transition-opacity duration-700">
  <div class="text-center flex flex-col items-center px-6">
    <video id="successVideo" autoplay muted playsinline preload="auto"
      poster="../assets/pago-realizado.jpg"
      src="../assets/pago-realizado.mp4"
      class="w-72 h-72 md:w-96 md:h-96 object-contain rounded-2xl bg-transparent">
    </video>
    <p class="text-2xl font-extrabold text-slate-900 mt-3">¡Pago realizado!</p>
    <p class="text-sm text-slate-500 mt-1">Procesando tu reserva…</p>
  </div>
</div>
<?php endif; ?>

<div class="w-full md:max-w-sm min-h-screen bg-slate-50 flex flex-col md:shadow-2xl relative">

  <div class="bg-[#1e3a8a] px-5 py-4 sticky top-0 z-50 flex items-center gap-3">
    <img src="../assets/LogoS2.png" alt="Notarize" class="h-11 w-auto">
    <span class="text-white/50 text-xs ml-auto">5/6</span>
  </div>

  <div class="flex-1 px-4 py-4 flex flex-col gap-3 overflow-y-auto">

    <?php
    $checkoutMsg = $pago['_checkout_msg'] ?? null;
    $createErr   = $pago['_create_err']   ?? null;
    ?>
    <?php if ($createErr): ?>
      <div class="bg-red-50 border border-red-200 rounded-xl px-3 py-2 text-xs text-red-700 break-all inline-flex items-start gap-1.5">
        <i data-lucide="alert-triangle" class="w-3.5 h-3.5 mt-0.5 shrink-0"></i>
        <span>Error al crear el pago: <?= htmlspecialchars($createErr) ?></span>
      </div>
    <?php elseif ($checkoutMsg): ?>
      <div class="bg-amber-50 border border-amber-200 rounded-xl px-3 py-2 text-xs text-amber-800 break-all inline-flex items-start gap-1.5">
        <i data-lucide="alert-triangle" class="w-3.5 h-3.5 mt-0.5 shrink-0"></i>
        <span>Checkout: <?= htmlspecialchars($checkoutMsg) ?></span>
      </div>
    <?php endif; ?>

    <!-- Success icon + title compact -->
    <div class="flex items-center gap-3">
      <div class="w-12 h-12 rounded-full bg-green-100 flex items-center justify-center shrink-0">
        <svg class="w-6 h-6 text-green-600" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24">
          <path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7" />
        </svg>
      </div>
      <div>
        <p class="text-xs font-bold text-blue-900 uppercase tracking-wide mb-1">Pago Confirmado</p>
        <h1 class="text-lg font-extrabold text-slate-900">¡Pago confirmado!</h1>
        <p class="text-xs text-slate-500 mt-0.5">Tu servicio ha sido reservado con éxito.</p>
      </div>
    </div>

    <!-- Notary card -->
    <div class="bg-white rounded-2xl border border-slate-200 overflow-hidden">
      <div class="bg-slate-50 px-4 py-3 border-b border-slate-100">
        <div class="flex items-center gap-3">
          <div class="w-10 h-10 rounded-full bg-blue-100 flex items-center justify-center font-extrabold text-blue-700 text-sm shrink-0">
            <?= strtoupper(substr($notaryName,0,1)) ?>
          </div>
          <div class="flex-1 min-w-0">
            <p class="font-extrabold text-slate-900 text-sm truncate"><?= htmlspecialchars($notaryName) ?></p>
            <?php if ($direccion): ?><p class="text-xs text-slate-400 truncate inline-flex items-center gap-1"><i data-lucide="map-pin" class="w-3 h-3 shrink-0"></i> <?= htmlspecialchars($direccion) ?></p><?php endif; ?>
          </div>
        </div>
      </div>
      <div class="px-4 py-3 flex flex-col gap-0">
        <?php if ($horario): ?>
          <div class="flex justify-between items-center py-2 border-b border-slate-50">
            <span class="text-xs text-slate-400">Horario de oficina</span>
            <span class="text-xs font-semibold text-slate-700"><?= htmlspecialchars($horario) ?></span>
          </div>
        <?php endif; ?>
        <div class="flex justify-between items-center py-2 border-b border-slate-50">
          <span class="text-xs text-slate-400">Servicio</span>
          <span class="text-xs font-semibold text-slate-700"><?= htmlspecialchars($service) ?></span>
        </div>
        <div class="flex justify-between items-center py-2 border-b border-slate-50">
          <span class="text-xs text-slate-400">Cita</span>
          <span class="text-xs font-semibold text-slate-700"><?= htmlspecialchars($date) ?> <?= htmlspecialchars($time) ?></span>
        </div>
        <div class="flex justify-between items-center py-2">
          <span class="text-xs text-slate-400">Ubicación</span>
          <span class="text-xs font-semibold text-slate-700"><?= $modLabel ?></span>
        </div>
      </div>
    </div>

    <!-- Payment detail card -->
    <div class="bg-white rounded-2xl border border-slate-200">
      <div class="px-4 pt-4 pb-3">
        <p class="text-[10px] font-extrabold text-slate-400 uppercase tracking-widest mb-3">Detalles del Pago</p>

        <div class="flex justify-between items-center py-2.5 border-b border-slate-100">
          <span class="text-sm text-slate-400">Factura #</span>
          <span class="text-sm font-extrabold text-blue-700"><?= htmlspecialchars($invoice) ?></span>
        </div>
        <div class="flex justify-between items-center py-2.5 border-b border-slate-100">
          <span class="text-sm text-slate-400">ID de Transacción</span>
          <span class="text-xs font-mono font-bold text-slate-700"><?= htmlspecialchars($txId) ?></span>
        </div>
        <div class="flex justify-between items-center py-2.5 border-b border-slate-100">
          <span class="text-sm text-slate-400">Fecha y hora</span>
          <span class="text-sm font-semibold text-slate-700"><?= $paidAt ?></span>
        </div>
        <div class="flex justify-between items-center py-2.5 border-b border-slate-100">
          <span class="text-sm text-slate-400">Estado</span>
          <span class="text-sm font-extrabold text-green-600 inline-flex items-center gap-1"><i data-lucide="check" class="w-3.5 h-3.5"></i> Aprobado</span>
        </div>

        <!-- Price breakdown -->
        <div class="flex justify-between items-center py-2.5 border-b border-slate-100">
          <span class="text-sm text-slate-400">Tarifa del notario</span>
          <span class="text-sm font-semibold text-slate-900"><?= $currency ?> <?= number_format($fee, 2) ?></span>
        </div>
        <?php if ($homeFee): ?>
          <div class="flex justify-between items-center py-2.5 border-b border-slate-100">
            <span class="text-sm text-slate-400 inline-flex items-center gap-1.5"><i data-lucide="home" class="w-3.5 h-3.5"></i> Cargo por visita a domicilio</span>
            <span class="text-sm font-semibold text-orange-600">+ <?= $currency ?> <?= number_format($homeFee, 2) ?></span>
          </div>
        <?php endif; ?>
        <div class="flex justify-between items-center pt-3">
          <span class="font-extrabold text-slate-900">Total pagado</span>
          <span class="text-lg font-extrabold text-blue-700"><?= $currency ?> <?= number_format($total, 2) ?></span>
        </div>
      </div>
    </div>

  </div>

  <!-- Sticky bottom button -->
  <div class="sticky bottom-0 px-4 py-4 bg-white border-t border-slate-200">
    <button type="button" id="openNotifyModal" onclick="showNotifyModal(); return false;"
      class="w-full bg-blue-700 hover:bg-blue-800 active:scale-95 text-white font-extrabold text-center
             rounded-2xl py-4 shadow-lg shadow-blue-700/30 transition block">
      Ver Ruta →
    </button>
  </div>

  <?php include '../_nav.php'; ?>
</div>

<!-- Modal: estilo slide-up como step3 (priceModal) -->
<div id="notifyModal" class="hidden fixed inset-0 z-[60] flex items-end justify-center bg-black/50 px-4 pb-6"
     onclick="if(event.target===this) hideNotifyModal()">
  <div class="bg-white rounded-2xl w-full max-w-sm shadow-2xl overflow-hidden">

    <!-- Header azul (igual al priceModal) -->
    <div class="bg-blue-700 px-5 py-3.5 flex items-center gap-2">
      <i data-lucide="bell-ring" class="w-5 h-5 text-white"></i>
      <p class="text-white font-extrabold text-base flex-1">Recibir notificaciones</p>
      <button type="button" id="closeNotifyModal" onclick="hideNotifyModal()" class="text-white/80 hover:text-white">
        <i data-lucide="x" class="w-5 h-5"></i>
      </button>
    </div>

    <!-- Body -->
    <div class="px-5 py-5 flex flex-col gap-4">
      <!-- Icono central + pregunta -->
      <div class="flex flex-col items-center text-center gap-3">
        <div class="w-16 h-16 rounded-full bg-blue-50 flex items-center justify-center">
          <i data-lucide="bell-ring" class="w-8 h-8 text-blue-700"></i>
        </div>
        <div>
          <p class="text-base font-bold text-slate-900 mb-1">¿Deseas recibir notificaciones antes de tu cita?</p>
          <p class="text-xs text-slate-500 leading-relaxed">Te avisaremos con anticipación para que no olvides tu reserva.</p>
        </div>
      </div>

      <!-- Divisor punteado -->
      <div class="border-t border-dashed border-slate-200"></div>

      <!-- Opciones Sí / No -->
      <div class="flex gap-3">
        <button type="button" id="notifyNo" onclick="goStep6('0')"
          class="flex-1 border-[1.5px] border-slate-300 text-slate-600 font-semibold rounded-2xl py-3.5 hover:bg-slate-50 active:scale-95 transition text-sm inline-flex items-center justify-center gap-1.5">
          <i data-lucide="bell-off" class="w-4 h-4"></i>
          No, gracias
        </button>
        <button type="button" id="notifyYes" onclick="goStep6('1')"
          class="flex-1 bg-blue-700 hover:bg-blue-800 active:scale-95 text-white font-extrabold rounded-2xl py-3.5 shadow-lg shadow-blue-700/30 transition text-sm inline-flex items-center justify-center gap-1.5">
          <i data-lucide="bell" class="w-4 h-4"></i>
          Sí, notificarme
        </button>
      </div>
    </div>
  </div>
</div>

<script>
  (function () {
    var modal      = document.getElementById('notifyModal');
    var openBtn    = document.getElementById('openNotifyModal');

    function showModal() {
      if (!modal) return;
      modal.classList.remove('hidden');
      document.body.style.overflow = 'hidden';
      if (window.lucide) lucide.createIcons();
    }
    function hideModal() {
      if (!modal) return;
      modal.classList.add('hidden');
      document.body.style.overflow = '';
    }
    function goStep6(answer) {
      try {
        var fd = new FormData();
        fd.append('notify', answer);
        if (navigator.sendBeacon) navigator.sendBeacon('notify_save.php', fd);
      } catch (e) {}
      window.location.href = 'step6.php';
    }

    // Globales para los onclick inline
    window.showNotifyModal = showModal;
    window.hideNotifyModal = hideModal;
    window.goStep6         = goStep6;

    if (openBtn) openBtn.addEventListener('click', showModal);
  })();
</script>

</body>
</html>
