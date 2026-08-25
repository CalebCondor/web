<?php
require_once 'config.php';
session_init();

$error = flash('error');

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['archivo'])) {
    $file = $_FILES['archivo'];

    if ($file['error'] !== UPLOAD_ERR_OK) {
        flash('error', 'Upload failed. Please try again.');
        header('Location: step1.php'); exit;
    }

    $allowed = ['application/pdf','application/vnd.openxmlformats-officedocument.wordprocessingml.document',
                'application/msword','image/jpeg','image/png','text/plain'];
    if (!in_array($file['type'], $allowed)) {
        flash('error', 'Only PDF, DOCX, DOC, JPG, PNG and TXT files are accepted.');
        header('Location: step1.php'); exit;
    }

    if ($file['size'] > 10 * 1024 * 1024) {
        flash('error', 'File exceeds 10 MB limit.');
        header('Location: step1.php'); exit;
    }

    // Always persist file to server temp dir so it survives the redirect
    $tmpDir     = '/var/www/html/notarize/uploads';
    $safeName   = uniqid('doc_', true) . '.' . strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    $serverPath = $tmpDir . '/' . $safeName;
    if (!is_dir($tmpDir)) { mkdir($tmpDir, 0755, true); }
    if (!move_uploaded_file($file['tmp_name'], $serverPath)) {
        error_log('[step1] move_uploaded_file failed: ' . $file['tmp_name'] . ' -> ' . $serverPath);
        flash('error', 'Could not save file. Check server permissions on uploads directory.');
        header('Location: step1.php'); exit;
    }

    $_SESSION['doc'] = [
        'name'        => basename($file['name']),
        'size'        => $file['size'],
        'type'        => $file['type'],
        'server_path' => $serverPath,
    ];

    // Upload to API now if already authenticated; otherwise step2 will do it
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
            @unlink($serverPath); // clean up once uploaded
        }
    }

    header('Location: ' . (empty($_SESSION['client_token']) ? 'auth.php' : 'step2.php'));
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <title>Notarize — Upload Document</title>
  <?php include '_head.php'; ?>
</head>
<body class="bg-white md:bg-slate-200 min-h-screen flex justify-center">
<div class="w-full md:max-w-sm min-h-screen bg-white flex flex-col md:shadow-2xl relative">

  <div class="bg-[#1e3a8a] px-5 py-4 flex items-center justify-between">
    <div class="w-6"></div>
    <span class="text-white font-extrabold text-base">Upload Document</span>
    <div class="w-6"></div>
  </div>

  <div class="flex-1 w-full px-5 py-6 flex flex-col gap-5">

    <?php
    $bk = $_SESSION['booking'] ?? [];
    $bkNotary  = $bk['notary'] ?? null;
    $bkPago    = $bk['pago']   ?? [];
    $bkInvoice = $bkPago['numero_factura'] ?? $bkPago['invoice'] ?? null;
    if ($bkNotary): ?>
      <div class="bg-blue-50 border border-blue-200 rounded-2xl p-4 flex flex-col gap-2">
        <p class="text-[10px] font-extrabold text-blue-400 uppercase tracking-widest">Active Booking</p>
        <div class="flex items-center gap-2.5">
          <div class="w-9 h-9 rounded-full bg-blue-200 flex items-center justify-center font-extrabold text-blue-800 text-sm shrink-0">
            <?= strtoupper(substr($bkNotary['nombre'] ?? '?', 0, 1)) ?>
          </div>
          <div class="flex-1 min-w-0">
            <p class="font-bold text-slate-900 text-sm truncate"><?= htmlspecialchars($bkNotary['nombre'] ?? '—') ?></p>
            <?php if (!empty($bkNotary['horario'])): ?>
              <p class="text-xs text-slate-500">🕐 <?= htmlspecialchars($bkNotary['horario']) ?></p>
            <?php endif; ?>
          </div>
        </div>
        <?php if ($bkInvoice): ?>
          <div class="flex items-center justify-between bg-white rounded-xl px-3 py-2 border border-blue-200">
            <span class="text-xs text-slate-500 font-semibold">Invoice #</span>
            <span class="text-sm font-extrabold text-blue-700"><?= htmlspecialchars($bkInvoice) ?></span>
          </div>
        <?php endif; ?>
        <?php if (!empty($bk['date']) || !empty($bk['time'])): ?>
          <p class="text-xs text-slate-500 text-right">📅 <?= htmlspecialchars($bk['date'] ?? '') ?> <?= htmlspecialchars($bk['time'] ?? '') ?></p>
        <?php endif; ?>
      </div>
    <?php endif; ?>

    <div>
      <h2 class="text-xl font-extrabold text-slate-900 mb-1">Add your document</h2>
      <p class="text-sm text-slate-500">Upload a file or scan it directly with your camera.</p>
    </div>

    <?php if ($error): ?>
      <div class="bg-red-50 border border-red-200 text-red-700 text-sm rounded-xl px-4 py-3">
        <?= htmlspecialchars($error) ?>
      </div>
    <?php endif; ?>

    <!-- Two action cards -->
    <div class="grid grid-cols-2 gap-3">

      <!-- Upload from device -->
      <button type="button" id="btnUpload" onclick="triggerUpload()"
        class="option-card flex flex-col items-center gap-2.5 bg-white border-2 border-blue-700 rounded-2xl py-6 px-3 active:scale-95 transition shadow-sm">
        <div class="w-12 h-12 rounded-xl bg-blue-100 flex items-center justify-center">
          <svg class="w-6 h-6 text-blue-700" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" d="M3 16.5v2.25A2.25 2.25 0 005.25 21h13.5A2.25 2.25 0 0021 18.75V16.5m-13.5-9L12 3m0 0l4.5 4.5M12 3v13.5" />
          </svg>
        </div>
        <span class="text-xs font-bold text-slate-700 text-center leading-tight">Upload<br>from device</span>
      </button>

      <!-- Scan with camera -->
      <button type="button" id="btnScan" onclick="startCamera()"
        class="option-card flex flex-col items-center gap-2.5 bg-white border-2 border-slate-200 rounded-2xl py-6 px-3 active:scale-95 transition shadow-sm">
        <div class="w-12 h-12 rounded-xl bg-slate-100 flex items-center justify-center">
          <svg class="w-6 h-6 text-slate-600" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" d="M6.827 6.175A2.31 2.31 0 015.186 7.23c-.38.054-.757.112-1.134.175C2.999 7.58 2.25 8.507 2.25 9.574V18a2.25 2.25 0 002.25 2.25h15A2.25 2.25 0 0021.75 18V9.574c0-1.067-.75-1.994-1.802-2.169a47.865 47.865 0 00-1.134-.175 2.31 2.31 0 01-1.64-1.055l-.822-1.316a2.192 2.192 0 00-1.736-1.039 48.776 48.776 0 00-5.232 0 2.192 2.192 0 00-1.736 1.039l-.821 1.316z" />
            <path stroke-linecap="round" stroke-linejoin="round" d="M16.5 12.75a4.5 4.5 0 11-9 0 4.5 4.5 0 019 0zM18.75 10.5h.008v.008h-.008V10.5z" />
          </svg>
        </div>
        <span class="text-xs font-bold text-slate-600 text-center leading-tight">Scan with<br>camera</span>
      </button>
    </div>

    <!-- Camera view (hidden by default) -->
    <div id="cameraWrap" class="hidden flex flex-col gap-3">
      <div class="relative w-full rounded-2xl overflow-hidden bg-slate-900" style="height:220px">
        <video id="camFeed" autoplay playsinline muted class="w-full h-full object-cover"></video>
        <!-- Corner guides -->
        <div class="absolute inset-6 pointer-events-none border-2 border-white/40 rounded-xl"></div>
        <p class="absolute bottom-3 left-0 right-0 text-center text-white/70 text-xs">Align the document and tap Capture</p>
      </div>
      <div class="flex gap-3">
        <button type="button" onclick="stopCamera()"
          class="flex-1 border-[1.5px] border-slate-300 text-slate-600 font-semibold rounded-xl py-3 text-sm hover:bg-slate-50 transition">
          Cancel
        </button>
        <button type="button" id="captureBtn" onclick="capturePhoto()"
          class="flex-1 bg-blue-700 text-white font-bold rounded-xl py-3 text-sm hover:bg-blue-800 active:scale-95 transition">
          📸 Capture
        </button>
      </div>
    </div>

    <!-- Preview (hidden until file selected) -->
    <div id="previewWrap" class="hidden bg-slate-50 border border-slate-200 rounded-2xl p-4 flex items-center gap-3">
      <div id="previewThumb" class="w-14 h-14 rounded-xl bg-blue-100 flex items-center justify-center text-2xl shrink-0">📄</div>
      <div class="flex-1 min-w-0">
        <p id="fName" class="font-bold text-slate-900 text-sm truncate"></p>
        <p id="fSize" class="text-xs text-slate-400 mt-0.5"></p>
      </div>
      <button type="button" onclick="clearFile()" class="text-slate-400 hover:text-red-500 transition text-lg leading-none">✕</button>
    </div>

    <!-- Hidden form -->
    <form method="POST" enctype="multipart/form-data" id="uploadForm" class="flex flex-col gap-3">
      <input type="file" id="fileInput" name="archivo" class="hidden"
        accept=".pdf,.docx,.doc,.jpg,.jpeg,.png,.txt"
        onchange="previewFile(this)" />
      <!-- Canvas used to convert camera capture to JPEG for upload -->
      <canvas id="snapCanvas" class="hidden"></canvas>
      <input type="hidden" id="capturedData" />

      <button type="submit" id="submitBtn" disabled
        class="w-full bg-blue-700 hover:bg-blue-800 active:scale-95 text-white font-extrabold
               rounded-2xl py-4 shadow-lg shadow-blue-700/30 transition
               disabled:opacity-40 disabled:pointer-events-none">
        Continue →
      </button>
    </form>

    <p class="text-center text-xs text-slate-400">PDF · DOCX · JPG · PNG · TXT &nbsp;·&nbsp; Max 10 MB</p>
  </div>

  <script>
    const btn   = document.getElementById('submitBtn');
    let camStream = null;

    function fmt(b) { return b < 1048576 ? (b/1024).toFixed(1)+' KB' : (b/1048576).toFixed(1)+' MB'; }

    function showPreview(name, size, emoji) {
      document.getElementById('fName').textContent = name;
      document.getElementById('fSize').textContent = size;
      document.getElementById('previewThumb').textContent = emoji;
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
      const emoji = ext === 'pdf' ? '📕' : (ext.match(/doc/) ? '📘' : (ext.match(/jpg|jpeg|png/) ? '🖼️' : '📄'));
      showPreview(f.name, fmt(f.size), emoji);
    }

    function triggerUpload() {
      document.getElementById('fileInput').click();
    }

    // ── Camera ─────────────────────────────────────────────────────────────────
    async function startCamera() {
      try {
        camStream = await navigator.mediaDevices.getUserMedia({
          video: { facingMode: { ideal: 'environment' } }
        });
        document.getElementById('camFeed').srcObject = camStream;
        document.getElementById('cameraWrap').classList.remove('hidden');
        document.getElementById('cameraWrap').classList.add('flex');
      } catch(e) {
        alert('Could not access camera. Please check permissions.');
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
        showPreview(file.name, fmt(file.size), '📷');
        stopCamera();
      }, 'image/jpeg', 0.88);
    }

    // Drag & drop on whole page
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
