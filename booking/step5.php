<?php
require_once '../config.php';
session_init();
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
$notaryName  = $notary['nombre']             ?? 'Selected Notary';
$horario     = $notary['horario']            ?? '';
$direccion   = $notary['direccion']          ?? '';
$domicilio   = (int)($b['domicilio']        ?? 0);
$modLabel    = $domicilio ? 'Home visit' : 'At notary office';
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <title>Notarize — Payment Confirmed</title>
  <?php include '../_head.php'; ?>
</head>
<body class="bg-white md:bg-slate-200 min-h-screen flex justify-center">
<div class="w-full md:max-w-sm min-h-screen bg-slate-50 flex flex-col md:shadow-2xl relative">

  <div class="bg-[#1e3a8a] px-5 py-4 sticky top-0 z-50 flex items-center gap-3">
    <span class="text-white font-extrabold text-base flex-1 text-center">Payment Confirmed</span>
    <span class="text-white/50 text-xs">5/6</span>
  </div>

  <div class="flex-1 px-4 py-4 flex flex-col gap-3 overflow-y-auto">

    <?php
    $checkoutMsg = $pago['_checkout_msg'] ?? null;
    $createErr   = $pago['_create_err']   ?? null;
    ?>
    <?php if ($createErr): ?>
      <div class="bg-red-50 border border-red-200 rounded-xl px-3 py-2 text-xs text-red-700 break-all">
        ⚠️ Pago creation failed: <?= htmlspecialchars($createErr) ?>
      </div>
    <?php elseif ($checkoutMsg): ?>
      <div class="bg-amber-50 border border-amber-200 rounded-xl px-3 py-2 text-xs text-amber-800 break-all">
        ⚠️ Checkout: <?= htmlspecialchars($checkoutMsg) ?>
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
        <h1 class="text-lg font-extrabold text-slate-900">Payment confirmed!</h1>
        <p class="text-xs text-slate-500 mt-0.5">Your service has been successfully booked.</p>
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
            <?php if ($direccion): ?><p class="text-xs text-slate-400 truncate">📍 <?= htmlspecialchars($direccion) ?></p><?php endif; ?>
          </div>
        </div>
      </div>
      <div class="px-4 py-3 flex flex-col gap-0">
        <?php if ($horario): ?>
          <div class="flex justify-between items-center py-2 border-b border-slate-50">
            <span class="text-xs text-slate-400">Office hours</span>
            <span class="text-xs font-semibold text-slate-700"><?= htmlspecialchars($horario) ?></span>
          </div>
        <?php endif; ?>
        <div class="flex justify-between items-center py-2 border-b border-slate-50">
          <span class="text-xs text-slate-400">Service</span>
          <span class="text-xs font-semibold text-slate-700"><?= htmlspecialchars($service) ?></span>
        </div>
        <div class="flex justify-between items-center py-2 border-b border-slate-50">
          <span class="text-xs text-slate-400">Appointment</span>
          <span class="text-xs font-semibold text-slate-700"><?= htmlspecialchars($date) ?> <?= htmlspecialchars($time) ?></span>
        </div>
        <div class="flex justify-between items-center py-2">
          <span class="text-xs text-slate-400">Location</span>
          <span class="text-xs font-semibold text-slate-700"><?= $modLabel ?></span>
        </div>
      </div>
    </div>

    <!-- Payment detail card -->
    <div class="bg-white rounded-2xl border border-slate-200">
      <div class="px-4 pt-4 pb-3">
        <p class="text-[10px] font-extrabold text-slate-400 uppercase tracking-widest mb-3">Payment Details</p>

        <div class="flex justify-between items-center py-2.5 border-b border-slate-100">
          <span class="text-sm text-slate-400">Invoice #</span>
          <span class="text-sm font-extrabold text-blue-700"><?= htmlspecialchars($invoice) ?></span>
        </div>
        <div class="flex justify-between items-center py-2.5 border-b border-slate-100">
          <span class="text-sm text-slate-400">Transaction ID</span>
          <span class="text-xs font-mono font-bold text-slate-700"><?= htmlspecialchars($txId) ?></span>
        </div>
        <div class="flex justify-between items-center py-2.5 border-b border-slate-100">
          <span class="text-sm text-slate-400">Date &amp; time</span>
          <span class="text-sm font-semibold text-slate-700"><?= $paidAt ?></span>
        </div>
        <div class="flex justify-between items-center py-2.5 border-b border-slate-100">
          <span class="text-sm text-slate-400">Status</span>
          <span class="text-sm font-extrabold text-green-600">✓ Approved</span>
        </div>

        <!-- Price breakdown -->
        <div class="flex justify-between items-center py-2.5 border-b border-slate-100">
          <span class="text-sm text-slate-400">Notary fee</span>
          <span class="text-sm font-semibold text-slate-900"><?= $currency ?> <?= number_format($fee, 2) ?></span>
        </div>
        <?php if ($homeFee): ?>
          <div class="flex justify-between items-center py-2.5 border-b border-slate-100">
            <span class="text-sm text-slate-400">🏠 Home visit fee</span>
            <span class="text-sm font-semibold text-orange-600">+ <?= $currency ?> <?= number_format($homeFee, 2) ?></span>
          </div>
        <?php endif; ?>
        <div class="flex justify-between items-center pt-3">
          <span class="font-extrabold text-slate-900">Total paid</span>
          <span class="text-lg font-extrabold text-blue-700"><?= $currency ?> <?= number_format($total, 2) ?></span>
        </div>
      </div>
    </div>

  </div>

  <!-- Sticky bottom button -->
  <div class="sticky bottom-0 px-4 py-4 bg-white border-t border-slate-200">
    <a href="step6.php"
      class="w-full bg-blue-700 hover:bg-blue-800 active:scale-95 text-white font-extrabold text-center
             rounded-2xl py-4 shadow-lg shadow-blue-700/30 transition block">
      View Route →
    </a>
  </div>

  <?php include '../_nav.php'; ?>
</div>
</body>
</html>
