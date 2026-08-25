<?php
require_once 'config.php';
require_auth();

$token = $_SESSION['client_token'];
$docs  = [];
$error = '';

// Fetch files
$res = api_json('GET', '/archivos', [], $token);

// Fetch pagos to get notary name and date per document
$resPagos = api_json('GET', '/pagos', [], $token);
$pagosByAbogado = [];
foreach ($resPagos['data'] ?? [] as $p) {
    $key = $p['id_abogado'] ?? null;
    if ($key && empty($pagosByAbogado[$key])) $pagosByAbogado[$key] = $p;
}

// Also check booking session for notary data
$bkNotary = $_SESSION['booking']['notary'] ?? null;

if ($res['success'] ?? false) {
    foreach ($res['data'] ?? [] as $a) {
        $idAbogado  = $a['id_abogado'] ?? null;
        $pago       = $pagosByAbogado[$idAbogado] ?? null;
        // Resolve notary name: from archivos, pagos, or booking session
        $notaryName = $a['nombre_abogado']
            ?? $pago['nombre_abogado']
            ?? ($bkNotary && $bkNotary['id_abogado'] == $idAbogado ? $bkNotary['nombre'] : null)
            ?? null;
        $pagoDate   = $pago['fecha']      ?? null;
        $invoice    = $pago['numero_invoice'] ?? null;

        $docs[] = [
            'id'         => $a['id_archivo'],
            'name'       => $a['nombre_archivo'],
            'date'       => substr($a['creado_en'] ?? '', 0, 10),
            'url'        => 'https://islandmedpr.com/notarize/uploads/' . $a['ruta_archivo'],
            'notary'     => $notaryName,
            'pago_date'  => $pagoDate,
            'invoice'    => $invoice,
        ];
    }
} else {
    $error = $res['message'] ?? 'Could not load documents.';
}

function fileIcon(string $name): string {
    if (substr($name, -4) === '.pdf')                    return '📕';
    if (preg_match('/\.(doc|docx)$/', $name))            return '📘';
    if (preg_match('/\.(jpg|jpeg|png|gif)$/i', $name))   return '🖼️';
    return '📄';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <title>Notarize — My Documents</title>
  <?php include '_head.php'; ?>
</head>
<body class="bg-white md:bg-slate-200 min-h-screen flex justify-center">
<div class="w-full md:max-w-sm min-h-screen bg-slate-50 flex flex-col md:shadow-2xl relative">

  <div class="bg-[#1e3a8a] px-5 py-4 flex items-center justify-between">
    <div class="w-6"></div>
    <div class="text-center">
      <p class="text-white font-extrabold text-base">My Documents</p>
      <p class="text-white/50 text-xs"><?= count($docs) ?> file<?= count($docs) !== 1 ? 's' : '' ?></p>
    </div>
    <div class="w-6"></div>
  </div>

  <div class="flex-1 w-full px-4 py-5 flex flex-col gap-3">

    <?php if ($error): ?>
      <div class="bg-red-50 border border-red-200 text-red-700 text-sm rounded-xl px-4 py-3">
        <?= htmlspecialchars($error) ?>
      </div>
    <?php elseif (empty($docs)): ?>
      <div class="flex flex-col items-center justify-center py-20 gap-3">
        <span class="text-5xl">📂</span>
        <p class="font-bold text-slate-700">No documents yet</p>
        <p class="text-sm text-slate-400">Upload a document to get started.</p>
        <a href="step1.php" class="mt-2 bg-blue-700 text-white font-bold text-sm rounded-xl px-6 py-3 hover:bg-blue-800 transition">
          Upload Document
        </a>
      </div>
    <?php else: ?>
      <?php foreach ($docs as $doc): ?>
        <div class="bg-white rounded-2xl border border-slate-100 shadow-sm overflow-hidden">
          <!-- Document row -->
          <div class="flex items-center gap-3 px-4 py-3.5">
            <div class="w-11 h-11 rounded-xl bg-blue-50 flex items-center justify-center text-2xl shrink-0">
              <?= fileIcon($doc['name']) ?>
            </div>
            <div class="flex-1 min-w-0">
              <p class="font-bold text-slate-900 text-sm leading-snug truncate"><?= htmlspecialchars($doc['name']) ?></p>
              <p class="text-xs text-slate-400 mt-0.5">Uploaded <?= htmlspecialchars($doc['date']) ?></p>
            </div>
            <a href="<?= htmlspecialchars($doc['url']) ?>" target="_blank"
              class="shrink-0 border-[1.5px] border-blue-700 text-blue-700 text-xs font-bold rounded-lg px-3 py-2 hover:bg-blue-50 transition">
              VIEW
            </a>
          </div>
          <!-- Notary / booking info -->
          <?php if ($doc['notary'] || $doc['pago_date'] || $doc['invoice']): ?>
            <div class="border-t border-slate-50 px-4 py-2.5 bg-slate-50 flex flex-wrap gap-x-4 gap-y-1">
              <?php if ($doc['notary']): ?>
                <span class="text-xs text-slate-500">⚖️ <?= htmlspecialchars($doc['notary']) ?></span>
              <?php endif; ?>
              <?php if ($doc['pago_date']): ?>
                <span class="text-xs text-slate-500">📅 <?= htmlspecialchars($doc['pago_date']) ?></span>
              <?php endif; ?>
              <?php if ($doc['invoice']): ?>
                <span class="text-xs font-bold text-blue-700"><?= htmlspecialchars($doc['invoice']) ?></span>
              <?php endif; ?>
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
