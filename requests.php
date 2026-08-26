<?php
require_once 'config.php';
session_init();
require_auth();

$token  = $_SESSION['client_token'] ?? '';
$userId = $_SESSION['user']['id_cliente'] ?? null;

// --- Archivos: group by id_pago then by id_abogado as fallback ---
$resArch  = api_json('GET', '/archivos', [], $token);
$archivos = [];
if (!empty($resArch['success']) && is_array($resArch['data'] ?? null)) {
    foreach ($resArch['data'] as $arch) {
        $key = $arch['id_pago'] ?? ('abogado_' . ($arch['id_abogado'] ?? 0));
        $archivos[$key][] = $arch;
    }
}

// --- Pagos ---
$qs  = $userId ? "?id_cliente=$userId" : '';
$res = api_json('GET', "/pagos$qs", [], $token);
$error = '';
$pagos = [];

if (!empty($res['success']) && isset($res['data'])) {
    $list = is_array($res['data']) ? $res['data'] : [];
} elseif (isset($res[0]) || (is_array($res) && array_key_exists(0, $res))) {
    $list = $res;
} elseif (isset($res['pagos'])) {
    $list = $res['pagos'];
} else {
    $list  = [];
    $error = $res['message'] ?? (!empty($res) ? '' : 'Could not load requests.');
}

if (!empty($list)) {
    usort($list, fn($a, $b) => strtotime($b['creado_en'] ?? 0) - strtotime($a['creado_en'] ?? 0));
    $pagos = $list;
}

$statusMap = [
    'pagado'    => ['bg' => 'bg-green-100',  'txt' => 'text-green-700',  'label' => 'Paid'],
    'pendiente' => ['bg' => 'bg-yellow-100', 'txt' => 'text-yellow-800', 'label' => 'Pending'],
    'rechazado' => ['bg' => 'bg-red-100',    'txt' => 'text-red-600',    'label' => 'Failed'],
];
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <title>Notarize — My Requests</title>
  <?php include '_head.php'; ?>
</head>
<body class="bg-white md:bg-slate-200 min-h-screen flex justify-center">
<div class="w-full md:max-w-sm min-h-screen bg-slate-50 flex flex-col md:shadow-2xl relative">

  <div class="bg-[#1e3a8a] px-5 py-4 flex items-center justify-center">
    <span class="text-white font-extrabold text-base">My Requests</span>
  </div>

  <div class="flex-1 overflow-y-auto px-4 py-5 pb-4 flex flex-col gap-3">

    <?php if ($error): ?>
      <div class="flex flex-col items-center py-16 gap-3">
        <i data-lucide="alert-triangle" class="w-12 h-12 text-red-400"></i>
        <p class="text-red-500 text-sm text-center"><?= htmlspecialchars($error) ?></p>
      </div>

    <?php elseif (empty($pagos)): ?>
      <div class="flex flex-col items-center py-16 gap-2">
        <i data-lucide="clipboard-list" class="w-14 h-14 text-slate-300"></i>
        <p class="font-bold text-slate-600 mt-2">No requests yet.</p>
        <p class="text-sm text-slate-400">Your bookings will appear here.</p>
      </div>

    <?php else: ?>
      <?php foreach ($pagos as $p):
        $estado = $p['estado_pago'] ?? 'pendiente';
        $st     = $statusMap[$estado] ?? $statusMap['pendiente'];
        $notary = $p['nombre_abogado'] ?? $p['nombre'] ?? '—';
        $amount = number_format((float)($p['monto'] ?? 0), 2);
        $moneda = $p['moneda'] ?? 'USD';
        $date   = substr($p['fecha'] ?? $p['creado_en'] ?? '', 0, 10);
        $inv    = $p['numero_invoice'] ?? null;
        $idPago    = $p['id_pago']       ?? null;
        $idAbogado = $p['id_abogado']    ?? null;
        // Match files by id_pago first, then by notary id
        $pagoFiles = $archivos[$idPago] ?? $archivos['abogado_' . $idAbogado] ?? [];
      ?>
        <div class="bg-white rounded-2xl border border-slate-100 p-4 shadow-sm">
          <!-- Header row -->
          <div class="flex items-center justify-between mb-3">
            <p class="font-bold text-slate-900 text-sm flex-1 mr-2 truncate"><?= htmlspecialchars($notary) ?></p>
            <span class="text-xs font-bold rounded-full px-3 py-1 <?= $st['bg'] ?> <?= $st['txt'] ?>">
              <?= $st['label'] ?>
            </span>
          </div>
          <!-- Detail rows -->
          <div class="flex justify-between py-2 border-t border-slate-50">
            <span class="text-xs text-slate-400 font-semibold">Amount</span>
            <span class="text-sm font-semibold text-slate-900"><?= $moneda ?> <?= $amount ?></span>
          </div>
          <?php if ($inv): ?>
            <div class="flex justify-between py-2 border-t border-slate-50">
              <span class="text-xs text-slate-400 font-semibold">Invoice</span>
              <span class="text-xs font-mono font-semibold text-slate-700"><?= htmlspecialchars($inv) ?></span>
            </div>
          <?php endif; ?>
          <div class="flex justify-between py-2 border-t border-slate-50">
            <span class="text-xs text-slate-400 font-semibold">Date</span>
            <span class="text-sm font-semibold text-slate-900"><?= htmlspecialchars($date ?: '—') ?></span>
          </div>

          <!-- Attached documents -->
          <?php if (!empty($pagoFiles)): ?>
            <div class="mt-3 pt-3 border-t border-slate-100">
              <p class="text-[10px] font-bold text-slate-400 uppercase tracking-widest mb-2">Documents</p>
              <?php foreach ($pagoFiles as $arch):
                $fname = $arch['nombre_archivo'] ?? 'Document';
                $ext   = strtolower(pathinfo($fname, PATHINFO_EXTENSION));
                if     ($ext === 'pdf')                              $icon = 'file-text';
                elseif (in_array($ext, ['doc','docx']))               $icon = 'file-text';
                elseif (in_array($ext, ['jpg','jpeg','png']))         $icon = 'image';
                else                                                  $icon = 'file';
                $color = $ext === 'pdf' ? 'text-red-500' : ($icon === 'image' ? 'text-purple-500' : 'text-slate-500');
              ?>
                <div class="flex items-center gap-2 py-1.5">
                  <i data-lucide="<?= $icon ?>" class="w-4 h-4 <?= $color ?>"></i>
                  <span class="text-xs text-slate-700 font-semibold truncate flex-1"><?= htmlspecialchars($fname) ?></span>
                </div>
              <?php endforeach; ?>
            </div>
          <?php else: ?>
            <div class="mt-3 pt-3 border-t border-slate-100 flex items-center justify-between">
              <span class="text-xs text-slate-400">No documents attached</span>
              <a href="<?= APP_BASE ?>/step1.php" class="text-xs font-bold text-blue-700 hover:underline">Upload →</a>
            </div>
          <?php endif; ?>
        </div>
      <?php endforeach; ?>
    <?php endif; ?>

  </div>

  <?php include '_nav.php'; ?>
</div>
</body>
</html>
