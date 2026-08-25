<?php
require_once 'config.php';
require_auth();

$user = $_SESSION['user'] ?? [];
$name = trim(($user['nombres'] ?? '') . ' ' . ($user['apellidos'] ?? '')) ?: ($user['nombre'] ?? '—');
$email    = $user['correo']    ?? $user['email']     ?? '';
$initials = implode('', array_map(fn($w) => strtoupper($w[0] ?? ''), array_slice(array_filter(explode(' ', $name)), 0, 2)));

// Upload pending file if not already sent (user wasn't logged in during step1)
$doc = $_SESSION['doc'] ?? null;
// Migrate old 'tmp' key to 'server_path' if needed
if ($doc && empty($doc['server_path']) && !empty($doc['tmp'])) {
    $doc['server_path'] = $doc['tmp'];
    $_SESSION['doc']['server_path'] = $doc['tmp'];
}
$serverPath = $doc['server_path'] ?? '';

if ($doc && empty($doc['api_id']) && !empty($serverPath) && file_exists($serverPath)) {
    $token  = $_SESSION['client_token'] ?? '';
    $userId = $user['id_cliente'] ?? null;
    $bkNotary = $_SESSION['booking']['notary'] ?? null;

    $fields = [
        'archivo'        => new CURLFile($serverPath, $doc['type'], $doc['name']),
        'nombre_archivo' => $doc['name'],
    ];
    if ($userId)                         $fields['id_cliente'] = (string)$userId;
    if ($bkNotary['id_abogado'] ?? null) $fields['id_abogado'] = (string)$bkNotary['id_abogado'];
    if (!empty($_SESSION['booking']['pago']['id_pago'])) {
        $fields['id_pago'] = (string)$_SESSION['booking']['pago']['id_pago'];
    }

    $ch = curl_init(API_BASE . '/archivos/upload');
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST           => true,
        CURLOPT_HTTPHEADER     => ["Authorization: Bearer $token"],
        CURLOPT_POSTFIELDS     => $fields,
        CURLOPT_TIMEOUT        => 30,
        CURLOPT_SSL_VERIFYPEER => false,
    ]);
    $raw  = curl_exec($ch);
    $err  = curl_error($ch);
    $http = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    $res  = json_decode($raw, true) ?? [];
    if ($err || (!($res['success'] ?? false) && $http !== 200)) {
        error_log('[step2 upload] HTTP '.$http.' err='.$err.' body='.$raw);
    }

    if ($res['success'] ?? false) {
        $_SESSION['doc']['api_id']   = $res['data']['id_archivo'] ?? null;
        $_SESSION['doc']['api_name'] = $res['data']['nombre_archivo'] ?? $doc['name'];
        @unlink($serverPath);
    }
    $doc = $_SESSION['doc'];

} elseif (!empty($doc['api_id'])) {
    // File already uploaded — ensure it's linked to the current client and notary
    $token     = $_SESSION['client_token'] ?? '';
    $userId    = $user['id_cliente'] ?? null;
    $bkNotary2 = $_SESSION['booking']['notary'] ?? null;
    $apiId     = $doc['api_id'];
    $putBody   = [];
    if ($userId)                          $putBody['id_cliente'] = (int)$userId;
    if ($bkNotary2['id_abogado'] ?? null) $putBody['id_abogado'] = (int)$bkNotary2['id_abogado'];
    if (!empty($putBody) && $apiId) {
        api_json('PUT', "/archivos/$apiId", $putBody, $token);
    }
}

$bk        = $_SESSION['booking'] ?? [];
$bkNotary  = $bk['notary'] ?? null;
$bkPago    = $bk['pago']   ?? [];
$bkInvoice = $bkPago['numero_factura'] ?? $bkPago['invoice'] ?? null;
$bkTxId    = $bkPago['transaction_id'] ?? null;
$doc       = $_SESSION['doc'] ?? null;
$docSent   = !empty($doc['api_id']);
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <title>Notarize — Account Ready</title>
  <?php include '_head.php'; ?>
</head>
<body class="bg-white md:bg-slate-200 min-h-screen flex justify-center">
<div class="w-full md:max-w-sm min-h-screen bg-white flex flex-col md:shadow-2xl relative">

  <div class="bg-[#1e3a8a] px-5 py-4 flex items-center gap-3">
    <span class="text-white font-extrabold text-base flex-1 text-center">Account Ready</span>
  </div>

  <div class="flex-1 w-full flex flex-col overflow-y-auto pb-4">

    <!-- Hero section with gradient -->
    <div class="bg-gradient-to-b from-[#1e3a8a] to-[#1e40af] px-6 pt-8 pb-10 flex flex-col items-center gap-2">
      <div class="w-16 h-16 rounded-full bg-white/20 flex items-center justify-center mb-1">
        <svg class="w-8 h-8 text-white" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24">
          <path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75L11.25 15 15 9.75M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
        </svg>
      </div>
      <h1 class="text-2xl font-extrabold text-white text-center">All set, <?= htmlspecialchars(explode(' ', $name)[0]) ?>!</h1>
      <p class="text-blue-200 text-sm text-center">Your account is verified and ready.</p>
    </div>

    <!-- Cards pulled up over the gradient -->
    <div class="px-5 -mt-5 flex flex-col gap-3">

      <!-- Document status card -->
      <?php if ($doc):
        $ext   = strtolower(pathinfo($doc['name'] ?? '', PATHINFO_EXTENSION));
        $icon  = $ext === 'pdf' ? '📕' : (in_array($ext, ['doc','docx']) ? '📘' : (in_array($ext, ['jpg','jpeg','png']) ? '🖼️' : '📄'));
        $bytes = (int)($doc['size'] ?? 0);
        $size  = $bytes < 1048576 ? round($bytes/1024,1).' KB' : round($bytes/1048576,1).' MB';
      ?>
        <div class="bg-white rounded-2xl shadow-md border border-slate-100 overflow-hidden">
          <div class="flex items-center gap-1.5 px-4 py-2 <?= $docSent ? 'bg-green-500' : 'bg-amber-400' ?>">
            <span class="text-white text-xs font-extrabold">
              <?= $docSent ? '✓  Document sent to notary' : '⏳  Document pending upload' ?>
            </span>
          </div>
          <div class="flex items-center gap-3 px-4 py-3.5">
            <span class="text-3xl"><?= $icon ?></span>
            <div class="flex-1 min-w-0">
              <p class="font-bold text-slate-900 text-sm truncate"><?= htmlspecialchars($doc['name'] ?? '') ?></p>
              <p class="text-xs text-slate-400 mt-0.5"><?= $size ?></p>
            </div>
            <?php if ($docSent): ?>
              <span class="text-green-500 text-xl">✓</span>
            <?php endif; ?>
          </div>
        </div>
      <?php endif; ?>

      <!-- Booking summary card -->
      <?php if ($bkNotary): ?>
        <div class="bg-white rounded-2xl shadow-md border border-slate-100 overflow-hidden">
          <div class="px-4 pt-4 pb-1">
            <p class="text-[10px] font-extrabold text-slate-400 uppercase tracking-widest">Booking Summary</p>
          </div>
          <!-- Notary header -->
          <div class="flex items-center gap-3 px-4 py-3 border-b border-slate-50">
            <div class="w-10 h-10 rounded-full bg-blue-700 flex items-center justify-center font-extrabold text-white text-sm shrink-0">
              <?= strtoupper(substr($bkNotary['nombre'] ?? '?', 0, 1)) ?>
            </div>
            <div class="flex-1 min-w-0">
              <p class="font-extrabold text-slate-900 text-sm truncate"><?= htmlspecialchars($bkNotary['nombre'] ?? '—') ?></p>
              <?php if (!empty($bkNotary['horario'])): ?>
                <p class="text-xs text-slate-400 mt-0.5">🕐 <?= htmlspecialchars($bkNotary['horario']) ?></p>
              <?php endif; ?>
            </div>
          </div>
          <!-- Details -->
          <div class="px-4 py-3 flex flex-col gap-0">
            <?php if (!empty($bk['date']) || !empty($bk['time'])): ?>
              <div class="flex justify-between items-center py-2 border-b border-slate-50">
                <span class="text-xs text-slate-400">Appointment</span>
                <span class="text-xs font-semibold text-slate-700">📅 <?= htmlspecialchars($bk['date'] ?? '') ?> <?= htmlspecialchars($bk['time'] ?? '') ?></span>
              </div>
            <?php endif; ?>
            <?php if (!empty($bk['service_name'])): ?>
              <div class="flex justify-between items-center py-2 border-b border-slate-50">
                <span class="text-xs text-slate-400">Service</span>
                <span class="text-xs font-semibold text-slate-700"><?= htmlspecialchars($bk['service_name']) ?></span>
              </div>
            <?php endif; ?>
            <?php if ($bkInvoice): ?>
              <div class="flex justify-between items-center py-2 border-b border-slate-50">
                <span class="text-xs text-slate-400">Invoice #</span>
                <span class="text-sm font-extrabold text-blue-700"><?= htmlspecialchars($bkInvoice) ?></span>
              </div>
            <?php endif; ?>
            <?php if ($bkTxId): ?>
              <div class="flex justify-between items-center py-2">
                <span class="text-xs text-slate-400">Transaction</span>
                <span class="text-xs font-mono font-semibold text-slate-600"><?= htmlspecialchars($bkTxId) ?></span>
              </div>
            <?php endif; ?>
          </div>
        </div>
      <?php endif; ?>

      <a href="step6.php"
         class="w-full bg-blue-700 hover:bg-blue-800 active:scale-95 text-white font-bold text-center
                rounded-2xl py-4 shadow-lg shadow-blue-700/30 transition mt-2">
        Go to My Documents →
      </a>

    </div>
  </div>
  <?php include '_nav.php'; ?>
</div>
</body>
</html>
