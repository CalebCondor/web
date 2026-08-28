<?php
require_once '../config.php';
session_init();
$b = $_SESSION['booking'] ?? [];
if (empty($b['notary'])) { header('Location: step3.php'); exit; }

$notary    = $b['notary'];
$currency  = $notary['moneda'] ?? 'USD';
$domicilio = (int)($b['domicilio'] ?? 0);
$homeFee   = $domicilio ? 80 : 0;
// Use stored base fee; fall back to subtracting homeFee for legacy sessions that had it baked in
$fee       = isset($b['fee_base'])
    ? (float)$b['fee_base']
    : max(0, (float)($notary['tarifa_consulta'] ?? 0) - $homeFee);
$total     = $fee + $homeFee;
$service   = $b['service_name'] ?? '';
$date      = $b['date']         ?? '';
$time      = $b['time']         ?? '';
$modalidad = $domicilio ? 'Visita a domicilio' : 'En la notaría';

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $token    = $_SESSION['client_token'] ?? '';
    // Try multiple possible field names for IDs
    $userId   = $_SESSION['user']['id_cliente'] ?? $_SESSION['user']['id'] ?? null;
    $notaryId = $notary['id_abogado'] ?? $notary['id'] ?? $notary['abogado_id'] ?? null;

    if ($token && $userId && $notaryId) {
        // Step 1: Create the pago record (pendiente)
        $ch = curl_init(API_BASE . '/pagos');
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CUSTOMREQUEST  => 'POST',
            CURLOPT_HTTPHEADER     => ['Content-Type: application/json', "Authorization: Bearer $token"],
            CURLOPT_POSTFIELDS     => json_encode([
                'id_cliente'  => (int)$userId,
                'id_abogado'  => (int)$notaryId,
                'monto'       => $fee,
                'fecha'       => $date,
                'estado_pago' => 'pendiente',
            ]),
            CURLOPT_TIMEOUT        => 15,
            CURLOPT_SSL_VERIFYPEER => false,
        ]);
        $rawCreate = curl_exec($ch);
        $httpCreate = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        $resCreate  = json_decode($rawCreate, true) ?? [];

        if (!($resCreate['success'] ?? false)) {
            // Pago creation failed — store message for step5
            $_SESSION['booking']['pago'] = [
                '_create_err' => ($resCreate['message'] ?? '') . ' [HTTP ' . $httpCreate . '] ' . substr($rawCreate, 0, 300),
            ];
        } else {
            $idPago = $resCreate['data']['id_pago'] ?? null;

            // Step 2: POST /pagos/{id}/checkout with demo card
            if ($idPago) {
                $ch2 = curl_init(API_BASE . "/pagos/$idPago/checkout");
                curl_setopt_array($ch2, [
                    CURLOPT_RETURNTRANSFER => true,
                    CURLOPT_POST           => true,
                    CURLOPT_HTTPHEADER     => ['Content-Type: application/json', "Authorization: Bearer $token"],
                    CURLOPT_POSTFIELDS     => json_encode([
                        'numero_tarjeta' => '4242424242424242',
                        'nombre_titular' => $_SESSION['user']['nombre'] ?? 'CLIENTE DEMO',
                        'mes_exp'        => '12',
                        'anio_exp'       => '2027',
                        'cvv'            => '123',
                        'moneda'         => $currency,
                    ]),
                    CURLOPT_TIMEOUT        => 15,
                    CURLOPT_SSL_VERIFYPEER => false,
                ]);
                $rawCheckout  = curl_exec($ch2);
                $httpCheckout = curl_getinfo($ch2, CURLINFO_HTTP_CODE);
                curl_close($ch2);
                $resCheckout = json_decode($rawCheckout, true) ?? [];

                if ($resCheckout['success'] ?? false) {
                    $_SESSION['booking']['pago'] = $resCheckout['data']['pago'] ?? $resCheckout['data'] ?? [];
                    $_SESSION['booking']['pago']['transaction_id'] = $resCheckout['data']['transaction_id'] ?? null;
                } else {
                    $_SESSION['booking']['pago'] = $resCreate['data'] ?? [];
                    $_SESSION['booking']['pago']['_checkout_msg'] =
                        ($resCheckout['message'] ?? '') . ' [HTTP ' . $httpCheckout . '] ' . substr($rawCheckout, 0, 200);
                }
            }
        }
    } else {
        $_SESSION['booking']['pago'] = [
            '_create_err' => 'Missing token/userId/notaryId — token:' . (!empty($token)?'ok':'MISSING')
                . ' userId:' . ($userId ?? 'null')
                . ' notaryId:' . ($notaryId ?? 'null'),
        ];
    }
    header('Location: step5.php'); exit;
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
  <title>Notarize — Pago</title>
  <?php include '../_head.php'; ?>
</head>
<body class="bg-white md:bg-slate-200 min-h-screen flex justify-center">
<div class="w-full md:max-w-sm min-h-screen bg-slate-50 flex flex-col md:shadow-2xl relative">

  <div class="bg-[#1e3a8a] px-5 py-4 flex items-center gap-3">
    <img src="../assets/LogoS2.png" alt="Notarize" class="h-11 w-auto">
    <span class="text-white/50 text-xs ml-auto">4/5</span>
  </div>

  <div class="flex-1 px-5 py-6 flex flex-col gap-5">

    <div>
      <span class="inline-flex items-center gap-1.5 bg-blue-100 text-blue-800 text-xs font-semibold rounded-full px-3 py-1.5 mb-3">
        <i data-lucide="building-2" class="w-3.5 h-3.5"></i>
        <?= htmlspecialchars($service) ?>
      </span>
      <p class="text-sm font-bold text-blue-900 uppercase tracking-wide mb-1">Pago</p>
      <h1 class="text-2xl font-extrabold text-slate-900 mb-1">Resumen del Pago</h1>
      <p class="text-sm text-slate-500">Revisa los detalles antes de continuar</p>
    </div>

    <?php if ($error): ?>
      <div class="bg-red-50 border border-red-200 text-red-700 text-sm rounded-xl px-4 py-3">
        <?= htmlspecialchars($error) ?>
      </div>
    <?php endif; ?>

    <!-- Service detail card -->
    <div class="bg-white rounded-2xl shadow-sm border border-slate-100 overflow-hidden">
      <div class="px-5 py-3 border-b border-slate-100">
        <p class="text-xs font-bold text-slate-400 uppercase tracking-wider">Detalles del Servicio</p>
      </div>
      <div class="px-5 py-1">
        <?php foreach ([
          ['Notario',   $notary['nombre'] ?? 'Notario'],
          ['Servicio',  $service],
          ['Fecha',     $date],
          ['Hora',      $time],
          ['Ubicación', $modalidad],
        ] as [$lbl, $val]): ?>
          <div class="flex justify-between items-center py-3 border-b border-slate-100 last:border-0 gap-3">
            <span class="text-sm text-slate-500 shrink-0"><?= htmlspecialchars($lbl) ?></span>
            <span class="text-sm font-semibold text-slate-900 text-right truncate"><?= htmlspecialchars($val) ?></span>
          </div>
        <?php endforeach; ?>
      </div>
    </div>

    <!-- Price summary card -->
    <div class="bg-white rounded-2xl shadow-sm border border-slate-100 overflow-hidden">
      <div class="px-5 py-3 border-b border-slate-100">
        <p class="text-xs font-bold text-slate-400 uppercase tracking-wider">Resumen</p>
      </div>
      <div class="px-5 py-3">
        <div class="flex justify-between items-center py-2">
          <span class="text-sm text-slate-500">Precio del producto</span>
          <span class="text-sm font-semibold text-slate-900"><?= $currency ?> <?= number_format($fee, 2) ?></span>
        </div>
        <?php if ($homeFee): ?>
          <div class="flex justify-between items-center py-2">
            <span class="text-sm text-slate-500 inline-flex items-center gap-1.5">
              <i data-lucide="home" class="w-3.5 h-3.5"></i> Cargo por visita a domicilio
            </span>
            <span class="text-sm font-semibold text-slate-900"><?= $currency ?> <?= number_format($homeFee, 2) ?></span>
          </div>
        <?php endif; ?>
      </div>
      <div class="bg-blue-50/70 px-5 py-4 flex justify-between items-center border-t border-blue-100">
        <span class="font-extrabold text-slate-900">Total a pagar</span>
        <span class="text-2xl font-extrabold text-blue-700"><?= $currency ?> <?= number_format($total, 2) ?></span>
      </div>
    </div>

    <!-- Payment method card (demo) -->
    <div class="bg-white rounded-2xl shadow-sm border border-slate-100 overflow-hidden">
      <div class="px-5 py-3 border-b border-slate-100">
        <p class="text-xs font-bold text-slate-400 uppercase tracking-wider">Método de Pago</p>
      </div>
      <div class="px-5 py-4">
        <div class="flex items-center gap-3">
          <div class="w-12 h-8 bg-gradient-to-r from-yellow-400 to-yellow-500 rounded-md flex items-center justify-center shrink-0">
            <span class="text-white text-[10px] font-extrabold">VISA</span>
          </div>
          <div class="flex-1 min-w-0">
            <p class="font-bold text-slate-900 text-sm">VISA ···· 4242</p>
            <p class="text-xs text-slate-400 truncate">CLIENTE DEMO</p>
          </div>
          <span class="bg-yellow-100 text-yellow-700 text-[10px] font-extrabold px-2 py-1 rounded-lg shrink-0">DEMO</span>
        </div>
      </div>
    </div>

  </div>

  <!-- Bottom action bar -->
  <div class="bg-white border-t border-slate-200 px-4 py-4 flex gap-3">
    <a href="step3.php"
      class="px-5 py-4 border-[1.5px] border-slate-300 text-slate-600 font-semibold rounded-2xl text-sm hover:bg-slate-50 active:scale-95 transition whitespace-nowrap">
      ← Atrás
    </a>
    <form method="POST" class="flex-1">
      <button type="submit"
        class="w-full bg-blue-700 hover:bg-blue-800 active:scale-95 text-white font-extrabold rounded-2xl py-4 shadow-lg shadow-blue-700/30 transition text-sm">
        Pagar <?= $currency ?> <?= number_format($total, 2) ?> →
      </button>
    </form>
  </div>

  <?php include '../_nav.php'; ?>
</div>
</body>
</html>
