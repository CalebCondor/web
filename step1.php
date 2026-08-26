<?php
require_once 'config.php';
session_init();

$error = flash('error');

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['archivo'])) {
    $file = $_FILES['archivo'];

    if ($file['error'] !== UPLOAD_ERR_OK) {
        flash('error', 'Error al subir el archivo. Inténtalo de nuevo.');
        header('Location: step1.php'); exit;
    }

    $allowed = ['application/pdf','application/vnd.openxmlformats-officedocument.wordprocessingml.document',
                'application/msword','image/jpeg','image/png','text/plain'];
    if (!in_array($file['type'], $allowed)) {
        flash('error', 'Solo se aceptan archivos PDF, DOCX, DOC, JPG, PNG y TXT.');
        header('Location: step1.php'); exit;
    }

    if ($file['size'] > 10 * 1024 * 1024) {
        flash('error', 'El archivo supera el límite de 10 MB.');
        header('Location: step1.php'); exit;
    }

    $tmpDir     = '/var/www/html/notarize/uploads';
    $safeName   = uniqid('doc_', true) . '.' . strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    $serverPath = $tmpDir . '/' . $safeName;
    if (!is_dir($tmpDir)) { mkdir($tmpDir, 0755, true); }
    if (!move_uploaded_file($file['tmp_name'], $serverPath)) {
        error_log('[step1] move_uploaded_file failed: ' . $file['tmp_name'] . ' -> ' . $serverPath);
        flash('error', 'No se pudo guardar el archivo. Verifica los permisos del servidor.');
        header('Location: step1.php'); exit;
    }

    $_SESSION['doc'] = [
        'name'        => basename($file['name']),
        'size'        => $file['size'],
        'type'        => $file['type'],
        'server_path' => $serverPath,
    ];

    $token    = $_SESSION['client_token'] ?? null;
    $userId   = $_SESSION['user']['id_cliente'] ?? null;
    $bkNotary = $_SESSION['booking']['notary'] ?? null;
    if ($token && file_exists($serverPath)) {
        $fields = [
            'archivo'        => new CURLFile($serverPath, $file['type'], $file['name']),
            'nombre_archivo' => $file['name'],
        ];
        if ($userId)                          $fields['id_cliente'] = (string)$userId;
        if ($bkNotary['id_abogado'] ?? null)  $fields['id_abogado'] = (string)$bkNotary['id_abogado'];
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
            error_log('[step1 upload] HTTP '.$http.' err='.$err.' body='.$raw);
        }
        if ($res['success'] ?? false) {
            $_SESSION['doc']['api_id']   = $res['data']['id_archivo'] ?? null;
            $_SESSION['doc']['api_name'] = $res['data']['nombre_archivo'] ?? $file['name'];
            @unlink($serverPath);
        }
    }

    $back = $_SESSION['after_upload'] ?? null;
    unset($_SESSION['after_upload']);
    if ($back) {
        header('Location: ' . $back); exit;
    }
    header('Location: ' . (empty($_SESSION['client_token']) ? 'auth.php' : 'requests.php'));
    exit;
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
  <title>Notarize — Subir Documento</title>
  <?php include '_head.php'; ?>
</head>
<body class="bg-white md:bg-slate-200 min-h-screen flex justify-center">
<div class="w-full md:max-w-sm min-h-screen bg-white flex flex-col md:shadow-2xl relative">

  <div class="bg-[#1e3a8a] px-5 py-4 sticky top-0 z-50 flex items-center gap-3">
    <a href="javascript:history.back()" class="text-blue-300 text-xl leading-none">‹</a>
    <img src="assets/LogoS2.png" alt="Notarize" class="h-11 w-auto brightness-0 invert">
  </div>

  <div class="flex-1 w-full px-5 py-6 flex flex-col gap-5">

    <?php
    $bk = $_SESSION['booking'] ?? [];
    $bkNotary  = $bk['notary'] ?? null;
    $bkPago    = $bk['pago']   ?? [];
    $bkInvoice = $bkPago['numero_factura'] ?? $bkPago['invoice'] ?? null;
    if ($bkNotary): ?>
      <div class="bg-blue-50 border border-blue-200 rounded-2xl p-4 flex flex-col gap-2">
        <p class="text-[10px] font-extrabold text-blue-400 uppercase tracking-widest">Reserva Activa</p>
        <div class="flex items-center gap-2.5">
          <div class="w-9 h-9 rounded-full bg-blue-200 flex items-center justify-center font-extrabold text-blue-800 text-sm shrink-0">
            <?= strtoupper(substr($bkNotary['nombre'] ?? '?', 0, 1)) ?>
          </div>
          <div class="flex-1 min-w-0">
            <p class="font-bold text-slate-900 text-sm truncate"><?= htmlspecialchars($bkNotary['nombre'] ?? '—') ?></p>
            <?php if (!empty($bkNotary['horario'])): ?>
              <p class="text-xs text-slate-500 inline-flex items-center gap-1"><i data-lucide="clock" class="w-3 h-3"></i> <?= htmlspecialchars($bkNotary['horario']) ?></p>
            <?php endif; ?>
          </div>
        </div>
        <?php if ($bkInvoice): ?>
          <div class="flex items-center justify-between bg-white rounded-xl px-3 py-2 border border-blue-200">
            <span class="text-xs text-slate-500 font-semibold">Factura #</span>
            <span class="text-sm font-extrabold text-blue-700"><?= htmlspecialchars($bkInvoice) ?></span>
          </div>
        <?php endif; ?>
        <?php if (!empty($bk['date']) || !empty($bk['time'])): ?>
          <p class="text-xs text-slate-500 text-right inline-flex items-center justify-end gap-1"><i data-lucide="calendar" class="w-3 h-3"></i> <?= htmlspecialchars($bk['date'] ?? '') ?> <?= htmlspecialchars($bk['time'] ?? '') ?></p>
        <?php endif; ?>
      </div>
    <?php endif; ?>

    <div>
      <p class="text-sm font-bold text-blue-900 uppercase tracking-wide mb-2">Subir Documento</p>
      <h2 class="text-xl font-extrabold text-slate-900 mb-1">Sube tu documento</h2>
      <p class="text-sm text-slate-500">Sube un archivo o escanéalo directamente con tu cámara.</p>
    </div>

    <?php if ($error): ?>
      <div class="bg-red-50 border border-red-200 text-red-700 text-sm rounded-xl px-4 py-3 inline-flex items-start gap-1.5">
        <i data-lucide="alert-triangle" class="w-4 h-4 mt-0.5 shrink-0"></i>
        <span><?= htmlspecialchars($error) ?></span>
      </div>
    <?php endif; ?>

    <div class="grid grid-cols-2 gap-3">

      <button type="button" id="btnUpload" onclick="triggerUpload()"
        class="option-card flex flex-col items-center gap-2.5 bg-white border-2 border-blue-700 rounded-2xl py-6 px-3 active:scale-95 transition shadow-sm">
        <div class="w-12 h-12 rounded-xl bg-blue-100 flex items-center justify-center">
          <i data-lucide="upload" class="w-6 h-6 text-blue-700"></i>
        </div>
        <span class="text-xs font-bold text-slate-700 text-center leading-tight">Subir desde<br>dispositivo</span>
      </button>

      <button type="button" id="btnScan" onclick="startCamera()"
        class="option-card flex flex-col items-center gap-2.5 bg-white border-2 border-slate-200 rounded-2xl py-6 px-3 active:scale-95 transition shadow-sm">
        <div class="w-12 h-12 rounded-xl bg-slate-100 flex items-center justify-center">
          <i data-lucide="camera" class="w-6 h-6 text-slate-600"></i>
        </div>
        <span class="text-xs font-bold text-slate-600 text-center leading-tight">Escanear con<br>cámara</span>
      </button>
    </div>

    <div id="cameraWrap" class="hidden flex flex-col gap-3">
      <div class="relative w-full rounded-2xl overflow-hidden bg-slate-900" style="height:220px">
        <video id="camFeed" autoplay playsinline muted class="w-full h-full object-cover"></video>
        <div class="absolute inset-6 pointer-events-none border-2 border-white/40 rounded-xl"></div>
        <p class="absolute bottom-3 left-0 right-0 text-center text-white/70 text-xs">Alinea el documento y toca Capturar</p>
      </div>
      <div class="flex gap-3">
        <button type="button" onclick="stopCamera()"
          class="flex-1 border-[1.5px] border-slate-300 text-slate-600 font-semibold rounded-xl py-3 text-sm hover:bg-slate-50 transition">
          Cancelar
        </button>
        <button type="button" id="captureBtn" onclick="capturePhoto()"
          class="flex-1 bg-blue-700 text-white font-bold rounded-xl py-3 text-sm hover:bg-blue-800 active:scale-95 transition inline-flex items-center justify-center gap-1.5">
          <i data-lucide="camera" class="w-4 h-4"></i> Capturar
        </button>
      </div>
    </div>

    <div id="previewWrap" class="hidden bg-slate-50 border border-slate-200 rounded-2xl p-4 flex items-center gap-3">
      <div id="previewThumb" class="w-14 h-14 rounded-xl bg-blue-100 flex items-center justify-center shrink-0">
        <i data-lucide="file-text" class="w-7 h-7 text-blue-700"></i>
      </div>
      <div class="flex-1 min-w-0">
        <p id="fName" class="font-bold text-slate-900 text-sm truncate"></p>
        <p id="fSize" class="text-xs text-slate-400 mt-0.5"></p>
      </div>
      <button type="button" onclick="clearFile()" class="text-slate-400 hover:text-red-500 transition">
        <i data-lucide="x" class="w-5 h-5"></i>
      </button>
    </div>

    <form method="POST" enctype="multipart/form-data" id="uploadForm" class="flex flex-col gap-3">
      <input type="file" id="fileInput" name="archivo" class="hidden"
        accept=".pdf,.docx,.doc,.jpg,.jpeg,.png,.txt"
        onchange="previewFile(this)" />
      <canvas id="snapCanvas" class="hidden"></canvas>

      <button type="submit" id="submitBtn" disabled
        class="w-full bg-blue-700 hover:bg-blue-800 active:scale-95 text-white font-extrabold
               rounded-2xl py-4 shadow-lg shadow-blue-700/30 transition
               disabled:opacity-40 disabled:pointer-events-none">
        Subir →
      </button>
    </form>

    <p class="text-center text-xs text-slate-400">PDF · DOCX · JPG · PNG · TXT &nbsp;·&nbsp; Máx. 10 MB</p>
  </div>

  <script>
    const btn   = document.getElementById('submitBtn');
    let camStream = null;

    function fmt(b) { return b < 1048576 ? (b/1024).toFixed(1)+' KB' : (b/1048576).toFixed(1)+' MB'; }

    function showPreview(name, size, icon) {
      document.getElementById('fName').textContent = name;
      document.getElementById('fSize').textContent = size;
      document.getElementById('previewThumb').innerHTML = '<i data-lucide="' + icon + '" class="w-7 h-7 text-blue-700"></i>';
      if (window.lucide) lucide.createIcons();
      document.getElementById('previewWrap').classList.remove('hidden');
      btn.disabled = false;
    }

    function clearFile() {
      document.getElementById('fileInput').value = '';
      document.getElementById('previewWrap').classList.add('hidden');
      btn.disabled = true;
    }

    function previewFile(inp) {
      if (!inp.files.length) return;
      const f = inp.files[0];
      const ext = f.name.split('.').pop().toLowerCase();
      const icon = ext === 'pdf' ? 'file-text'
                 : (ext.match(/doc/) ? 'file-text'
                 : (ext.match(/jpg|jpeg|png/) ? 'image' : 'file'));
      showPreview(f.name, fmt(f.size), icon);
    }

    function triggerUpload() {
      document.getElementById('fileInput').click();
    }

    async function startCamera() {
      try {
        camStream = await navigator.mediaDevices.getUserMedia({
          video: { facingMode: { ideal: 'environment' } }
        });
        document.getElementById('camFeed').srcObject = camStream;
        document.getElementById('cameraWrap').classList.remove('hidden');
        document.getElementById('cameraWrap').classList.add('flex');
      } catch(e) {
        alert('No se pudo acceder a la cámara. Verifica los permisos.');
      }
    }

    function stopCamera() {
      if (camStream) camStream.getTracks().forEach(t => t.stop());
      camStream = null;
      document.getElementById('cameraWrap').classList.add('hidden');
      document.getElementById('cameraWrap').classList.remove('flex');
    }

    function capturePhoto() {
      const video  = document.getElementById('camFeed');
      const canvas = document.getElementById('snapCanvas');
      canvas.width  = video.videoWidth;
      canvas.height = video.videoHeight;
      canvas.getContext('2d').drawImage(video, 0, 0);
      canvas.toBlob(blob => {
        const file = new File([blob], 'scan_' + Date.now() + '.jpg', { type: 'image/jpeg' });
        const dt   = new DataTransfer();
        dt.items.add(file);
        const inp  = document.getElementById('fileInput');
        inp.files  = dt.files;
        showPreview(file.name, fmt(file.size), 'image');
        stopCamera();
      }, 'image/jpeg', 0.88);
    }

    document.addEventListener('dragover', e => e.preventDefault());
    document.addEventListener('drop', e => {
      e.preventDefault();
      const f = e.dataTransfer.files[0];
      if (!f) return;
      const dt = new DataTransfer();
      dt.items.add(f);
      const inp = document.getElementById('fileInput');
      inp.files = dt.files;
      previewFile(inp);
    });
  </script>
  <?php include '_nav.php'; ?>
</div>
</body>
</html>
