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

    // Card details from form
    $cardNumber = preg_replace('/\s+/', '', $_POST['card_number'] ?? '');
    $cardName   = trim($_POST['card_name'] ?? '');
    $cardExpRaw = trim($_POST['card_exp']   ?? '');
    $cardCvv    = trim($_POST['card_cvv']   ?? '');
    $mes_exp    = '12';
    $anio_exp   = '2027';
    if (preg_match('/^(\d{2})\s*\/\s*(\d{2})$/', $cardExpRaw, $m)) {
        $mes_exp  = $m[1];
        $anio_exp = $m[2];
    }
    // Demo fallback: si el usuario usa la tarjeta de prueba 4242...
    if ($cardNumber === '') {
        $cardNumber = '4242424242424242';
        $cardName   = $cardName ?: ($_SESSION['user']['nombre'] ?? 'CLIENTE DEMO');
        $cardCvv    = $cardCvv ?: '123';
    }

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

            // Step 2: POST /pagos/{id}/checkout with the card the user entered
            if ($idPago) {
                $ch2 = curl_init(API_BASE . "/pagos/$idPago/checkout");
                curl_setopt_array($ch2, [
                    CURLOPT_RETURNTRANSFER => true,
                    CURLOPT_POST           => true,
                    CURLOPT_HTTPHEADER     => ['Content-Type: application/json', "Authorization: Bearer $token"],
                    CURLOPT_POSTFIELDS     => json_encode([
                        'numero_tarjeta' => $cardNumber,
                        'nombre_titular' => $cardName,
                        'mes_exp'        => $mes_exp,
                        'anio_exp'       => $anio_exp,
                        'cvv'            => $cardCvv,
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
                    $_SESSION['booking']['payment_just_made'] = true;
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
      <h1 class="text-2xl font-extrabold text-slate-900 mb-1">Método de Pago</h1>
      <p class="text-sm text-slate-500">Ingresa los datos de tu tarjeta para continuar</p>
    </div>

    <?php if ($error): ?>
      <div class="bg-red-50 border border-red-200 text-red-700 text-sm rounded-xl px-4 py-3">
        <?= htmlspecialchars($error) ?>
      </div>
    <?php endif; ?>

    <!-- MÓDULO DE PAGO -->
    <form method="POST" id="paymentForm" class="flex flex-col gap-4">
      <input type="hidden" name="card_cvv_real" id="cardCvvReal" value="">

      <!-- Visual card preview -->
      <div class="relative bg-gradient-to-br from-blue-700 via-blue-800 to-indigo-900 rounded-2xl p-5 shadow-xl shadow-blue-700/30 overflow-hidden aspect-[1.6/1]">
        <div class="absolute -top-12 -right-12 w-40 h-40 bg-white/10 rounded-full"></div>
        <div class="absolute -bottom-10 -left-10 w-32 h-32 bg-white/5 rounded-full"></div>
        <div class="absolute top-1/2 right-6 w-20 h-20 bg-white/5 rounded-full"></div>

        <div class="relative z-10 h-full flex flex-col justify-between text-white">
          <div class="flex justify-between items-start">
            <span class="text-white/70 text-[10px] font-semibold uppercase tracking-widest">Tarjeta de crédito</span>
            <i data-lucide="contactless" class="w-6 h-6 text-white/70"></i>
          </div>

          <div class="flex items-center gap-2">
            <div class="w-10 h-8 bg-gradient-to-br from-yellow-200 to-yellow-400 rounded-md flex items-center justify-center">
              <div class="w-6 h-5 border border-yellow-600/40 rounded-sm grid grid-cols-3 gap-px p-0.5">
                <div class="bg-yellow-600/40 rounded-sm"></div><div class="bg-yellow-600/40 rounded-sm"></div><div class="bg-yellow-600/40 rounded-sm"></div>
                <div class="bg-yellow-600/40 rounded-sm"></div><div class="bg-yellow-600/40 rounded-sm"></div><div class="bg-yellow-600/40 rounded-sm"></div>
                <div class="bg-yellow-600/40 rounded-sm"></div><div class="bg-yellow-600/40 rounded-sm"></div><div class="bg-yellow-600/40 rounded-sm"></div>
              </div>
            </div>
          </div>

          <p id="cardPreviewNumber" class="font-mono text-lg tracking-[0.18em] font-semibold">•••• •••• •••• ••••</p>

          <div class="flex justify-between items-end gap-3">
            <div class="min-w-0 flex-1">
              <p class="text-white/60 text-[9px] uppercase tracking-wider mb-0.5">Titular</p>
              <p id="cardPreviewName" class="text-sm font-semibold uppercase truncate">Tu nombre</p>
            </div>
            <div>
              <p class="text-white/60 text-[9px] uppercase tracking-wider mb-0.5">Expira</p>
              <p id="cardPreviewExp" class="text-sm font-semibold font-mono">MM/YY</p>
            </div>
            <span class="text-white font-extrabold italic text-lg shrink-0">VISA</span>
          </div>
        </div>
      </div>

      <!-- Inputs -->
      <div class="bg-white rounded-2xl shadow-sm border border-slate-100 p-5 space-y-4">
        <div>
          <label class="text-[11px] font-bold text-slate-500 uppercase tracking-wider mb-1.5 block">Número de tarjeta</label>
          <input type="text" name="card_number" id="cardNumber" maxlength="19" inputmode="numeric" placeholder="1234 5678 9012 3456" required autocomplete="cc-number"
            class="w-full border-[1.5px] border-slate-200 rounded-xl px-4 py-3 text-sm font-mono tracking-widest bg-slate-50 outline-none focus:border-blue-500 focus:bg-white transition">
        </div>

        <div>
          <label class="text-[11px] font-bold text-slate-500 uppercase tracking-wider mb-1.5 block">Nombre del titular</label>
          <input type="text" name="card_name" id="cardName" placeholder="Como aparece en la tarjeta" required autocomplete="cc-name"
            class="w-full border-[1.5px] border-slate-200 rounded-xl px-4 py-3 text-sm uppercase bg-slate-50 outline-none focus:border-blue-500 focus:bg-white transition">
        </div>

        <div class="grid grid-cols-2 gap-3">
          <div>
            <label class="text-[11px] font-bold text-slate-500 uppercase tracking-wider mb-1.5 block">Expira</label>
            <input type="text" name="card_exp" id="cardExp" maxlength="5" inputmode="numeric" placeholder="MM/YY" required autocomplete="cc-exp"
              class="w-full border-[1.5px] border-slate-200 rounded-xl px-4 py-3 text-sm font-mono bg-slate-50 outline-none focus:border-blue-500 focus:bg-white transition">
          </div>
          <div>
            <label class="text-[11px] font-bold text-slate-500 uppercase tracking-wider mb-1.5 block">CVV</label>
            <input type="text" name="card_cvv" id="cardCvv" maxlength="4" inputmode="numeric" placeholder="123" required autocomplete="cc-csc"
              class="w-full border-[1.5px] border-slate-200 rounded-xl px-4 py-3 text-sm font-mono bg-slate-50 outline-none focus:border-blue-500 focus:bg-white transition">
          </div>
        </div>

        <p class="text-[10px] text-slate-400 text-center pt-1 inline-flex items-center justify-center gap-1 w-full">
          <i data-lucide="lock" class="w-3 h-3"></i> Pago seguro · Demo: 4242 4242 4242 4242
        </p>
      </div>
    </form>

    <!-- RESUMEN -->
    <div class="bg-white rounded-2xl shadow-sm border border-slate-100 overflow-hidden">
      <div class="px-5 py-3 border-b border-slate-100 flex items-center gap-2">
        <i data-lucide="receipt" class="w-3.5 h-3.5 text-slate-400"></i>
        <p class="text-xs font-bold text-slate-400 uppercase tracking-wider">Resumen</p>
      </div>
      <div class="px-5 py-3 space-y-1">
        <?php foreach ([
          ['Notario',   $notary['nombre'] ?? 'Notario'],
          ['Servicio',  $service],
          ['Fecha',     $date],
          ['Hora',      $time],
          ['Ubicación', $modalidad],
        ] as [$lbl, $val]): ?>
          <div class="flex justify-between items-center py-2.5 border-b border-slate-100 last:border-0 gap-3">
            <span class="text-xs text-slate-500 shrink-0"><?= htmlspecialchars($lbl) ?></span>
            <span class="text-xs font-semibold text-slate-900 text-right truncate"><?= htmlspecialchars($val) ?></span>
          </div>
        <?php endforeach; ?>
      </div>
      <div class="px-5 py-3 border-t border-slate-100 space-y-1.5">
        <div class="flex justify-between items-center text-sm">
          <span class="text-slate-500">Tarifa de consulta</span>
          <span class="font-semibold text-slate-900"><?= $currency ?> <?= number_format($fee, 2) ?></span>
        </div>
        <?php if ($homeFee): ?>
          <div class="flex justify-between items-center text-sm">
            <span class="text-slate-500 inline-flex items-center gap-1.5">
              <i data-lucide="home" class="w-3.5 h-3.5"></i> Visita a domicilio
            </span>
            <span class="font-semibold text-slate-900"><?= $currency ?> <?= number_format($homeFee, 2) ?></span>
          </div>
        <?php endif; ?>
      </div>
      <div class="bg-gradient-to-r from-blue-50 to-indigo-50 px-5 py-4 flex justify-between items-center border-t border-blue-100">
        <span class="font-extrabold text-slate-900">Total a pagar</span>
        <span class="text-2xl font-extrabold text-blue-700"><?= $currency ?> <?= number_format($total, 2) ?></span>
      </div>
    </div>

  </div>

  <!-- Bottom action bar -->
  <div class="bg-white border-t border-slate-200 px-4 py-4 flex gap-3">
    <a href="step3.php"
      class="px-5 py-4 border-[1.5px] border-slate-300 text-slate-600 font-semibold rounded-2xl text-sm hover:bg-slate-50 active:scale-95 transition whitespace-nowrap">
      ← Atrás
    </a>
    <button type="submit" form="paymentForm"
      class="flex-1 bg-blue-700 hover:bg-blue-800 active:scale-95 text-white font-extrabold rounded-2xl py-4 shadow-lg shadow-blue-700/30 transition text-sm inline-flex items-center justify-center gap-1.5">
      <i data-lucide="lock" class="w-4 h-4"></i>
      Pagar <?= $currency ?> <?= number_format($total, 2) ?>
    </button>
  </div>

  <?php include '../_nav.php'; ?>
</div>

<!-- Overlay del sello de "PAGO REALIZADO" -->
<div id="stampOverlay" class="hidden fixed inset-0 z-[100] bg-white/95 backdrop-blur-md flex flex-col items-center justify-center overflow-hidden">
  <div class="stamp-wrap">
    <div class="stamp" id="theStamp">
      <span class="stamp-top">★ PAGO ★</span>
      <span class="stamp-mid">REALIZADO</span>
      <span class="stamp-bot">✓ CONFIRMADO</span>
    </div>
  </div>
  <p id="stampLabel" class="mt-8 text-slate-500 text-sm font-semibold tracking-wider uppercase">Procesando tu pago…</p>
</div>

<style>
  /* ── Sello animado (lento y dramático) ──────────────────────── */
  .stamp-wrap {
    perspective: 900px;
    position: relative;
  }

  .stamp {
    width: 230px; height: 230px;
    border: 7px solid #dc2626;
    border-radius: 50%;
    display: flex; flex-direction: column; align-items: center; justify-content: center;
    color: #dc2626;
    font-family: 'Impact', 'Arial Black', sans-serif;
    text-align: center;
    position: relative;
    opacity: 0;
    transform: scale(4) rotate(-35deg) translateY(-300px);
    background: rgba(254, 226, 226, 0.15);
    box-shadow:
      inset 0 0 0 5px rgba(255,255,255,0.7),
      inset 0 0 0 11px #dc2626,
      inset 0 0 0 17px rgba(255,255,255,0.7),
      0 0 0 3px rgba(220,38,38,0.15);
    /* 3 fases: anticipación (rise) → slam (down) → settle (bounces) */
    animation:
      stampRise   0.45s cubic-bezier(.4, 0, .6, 1) forwards,
      stampSlam   0.55s cubic-bezier(.55, 1.7, .5, 1) 0.45s forwards,
      stampSettle 0.85s cubic-bezier(.3, .8, .4, 1)    1.0s  forwards;
  }

  .stamp::before {
    content: '';
    position: absolute; inset: -16px;
    border: 3px dashed #dc2626;
    border-radius: 50%;
    opacity: 0.55;
  }
  .stamp::after {
    content: '';
    position: absolute; inset: 0;
    border-radius: 50%;
    background-image:
      radial-gradient(circle at 28% 38%, rgba(220,38,38,0.22) 0, transparent 9%),
      radial-gradient(circle at 72% 60%, rgba(220,38,38,0.16) 0, transparent 11%),
      radial-gradient(circle at 50% 28%, rgba(220,38,38,0.18) 0, transparent 7%),
      radial-gradient(circle at 60% 75%, rgba(220,38,38,0.14) 0, transparent 8%);
    mix-blend-mode: multiply;
  }
  .stamp-top, .stamp-mid, .stamp-bot {
    position: relative; z-index: 1;
  }
  .stamp-top { font-size: 13px; letter-spacing: 3px; opacity: 0.85; margin-bottom: 2px; }
  .stamp-mid { font-size: 28px; letter-spacing: 2.5px; line-height: 1; margin: 5px 0; }
  .stamp-bot { font-size: 11px; letter-spacing: 2.5px; opacity: 0.85; margin-top: 4px; }

  /* ── Partículas de tinta que salen disparadas al impacto ────── */
  .ink-splat {
    position: absolute;
    background: #dc2626;
    border-radius: 50%;
    opacity: 0;
    pointer-events: none;
  }
  .ink-splat.go {
    animation: splatFly 0.9s cubic-bezier(.2, .7, .4, 1) forwards;
  }

  @keyframes stampRise {
    0%   { opacity: 0; transform: scale(0.4) rotate(15deg)  translateY(120px); }
    40%  { opacity: 1; }
    100% { opacity: 1; transform: scale(4.0) rotate(-35deg) translateY(-300px); }
  }
  @keyframes stampSlam {
    0%   { opacity: 1; transform: scale(4.0) rotate(-35deg) translateY(-300px); }
    60%  { opacity: 1; transform: scale(0.86) rotate(-5deg) translateY(0); }
    100% { opacity: 1; transform: scale(1.10) rotate(-6deg) translateY(0); }
  }
  @keyframes stampSettle {
    0%   { transform: scale(1.10) rotate(-6deg) translateY(0); }
    25%  { transform: scale(0.94) rotate(-7deg) translateY(0); }
    50%  { transform: scale(1.05) rotate(-6deg) translateY(0); }
    70%  { transform: scale(0.97) rotate(-6deg) translateY(0); }
    85%  { transform: scale(1.02) rotate(-6deg) translateY(0); }
    100% { transform: scale(1)    rotate(-6deg) translateY(0); }
  }

  @keyframes splatFly {
    0%   { opacity: 0; transform: translate(0,0) scale(0.3); }
    20%  { opacity: 0.95; }
    100% { opacity: 0;   transform: var(--fly-end) scale(0.6); }
  }

  /* Camera shake de toda la pantalla al impacto */
  #stampOverlay.shake {
    animation: cameraShake 0.45s ease-out;
  }
  @keyframes cameraShake {
    0%   { transform: translate(0,0); }
    10%  { transform: translate(-8px, 6px); }
    20%  { transform: translate(7px, -5px); }
    30%  { transform: translate(-6px, 4px); }
    40%  { transform: translate(5px, -3px); }
    50%  { transform: translate(-4px, 3px); }
    60%  { transform: translate(3px, -2px); }
    70%  { transform: translate(-2px, 1px); }
    100% { transform: translate(0,0); }
  }

  #stampLabel {
    opacity: 0;
    animation: fadeLabel 0.5s ease-out 0.6s forwards;
  }
  @keyframes fadeLabel {
    to { opacity: 1; }
  }

  /* Check ✓ dentro del sello: aparece después del impacto */
  .stamp-mid::after {
    content: '';
    display: block;
  }
</style>

<script>
  (function () {
    const cardNumber = document.getElementById('cardNumber');
    const cardName   = document.getElementById('cardName');
    const cardExp    = document.getElementById('cardExp');
    const cardCvv    = document.getElementById('cardCvv');
    const previewNum = document.getElementById('cardPreviewNumber');
    const previewNm  = document.getElementById('cardPreviewName');
    const previewEx  = document.getElementById('cardPreviewExp');
    const form       = document.getElementById('paymentForm');
    const overlay    = document.getElementById('stampOverlay');
    const wrap       = overlay.querySelector('.stamp-wrap');

    function formatNum(raw) {
      const v = raw.replace(/\D/g, '').slice(0, 16);
      return v.match(/.{1,4}/g)?.join(' ') ?? v;
    }
    cardNumber.addEventListener('input', e => {
      const raw = e.target.value.replace(/\D/g, '').slice(0, 16);
      e.target.value = raw.match(/.{1,4}/g)?.join(' ') ?? raw;
      const padded = raw.padEnd(16, '•');
      previewNum.textContent = padded.match(/.{1,4}/g).join(' ');
    });
    cardName.addEventListener('input', e => {
      previewNm.textContent = e.target.value.toUpperCase() || 'Tu nombre';
    });
    cardExp.addEventListener('input', e => {
      let v = e.target.value.replace(/\D/g, '').slice(0, 4);
      if (v.length >= 3) v = v.slice(0, 2) + '/' + v.slice(2);
      e.target.value = v;
      previewEx.textContent = v || 'MM/YY';
    });
    cardCvv.addEventListener('input', e => {
      e.target.value = e.target.value.replace(/\D/g, '').slice(0, 4);
    });

    // ── Spawn ink splats cuando el sello "impacta" ─────────────
    function spawnSplats(count) {
      const rect = wrap.getBoundingClientRect();
      const cx = rect.left + rect.width / 2;
      const cy = rect.top + rect.height / 2;
      for (let i = 0; i < count; i++) {
        const dot = document.createElement('div');
        dot.className = 'ink-splat';
        const angle = (Math.PI * 2 * i) / count + (Math.random() * 0.4 - 0.2);
        const dist  = 110 + Math.random() * 70;
        const dx = Math.cos(angle) * dist;
        const dy = Math.sin(angle) * dist;
        const size = 6 + Math.random() * 10;
        dot.style.width = dot.style.height = size + 'px';
        dot.style.left = (cx - size / 2) + 'px';
        dot.style.top  = (cy - size / 2) + 'px';
        dot.style.setProperty('--fly-end', `translate(${dx}px, ${dy}px)`);
        document.body.appendChild(dot);
        // trigger animation next frame
        requestAnimationFrame(() => requestAnimationFrame(() => dot.classList.add('go')));
        setTimeout(() => dot.remove(), 1100);
      }
    }

    // ── Interceptar submit para mostrar el sello antes de enviar ──
    form.addEventListener('submit', function (e) {
      if (form.dataset.stamping === '1') return;
      e.preventDefault();

      if (!form.checkValidity()) {
        form.reportValidity();
        return;
      }

      form.dataset.stamping = '1';
      document.body.style.overflow = 'hidden';
      overlay.classList.remove('hidden');
      if (window.lucide) lucide.createIcons();

      // 0.45s rise + 0.55s slam → impacto en ~1.0s
      // Camera shake + ink splats al momento del impacto
      setTimeout(() => {
        overlay.classList.add('shake');
        spawnSplats(14);
      }, 1000);

      // Cambiar label mientras se procesa
      setTimeout(() => {
        const lbl = document.getElementById('stampLabel');
        if (lbl) {
          lbl.textContent = 'Confirmando reserva…';
          lbl.style.animation = 'none';
          lbl.offsetHeight;
          lbl.style.animation = 'fadeLabel 0.4s ease-out forwards';
        }
      }, 1900);

      // Enviar el formulario (PHP redirige a step5.php)
      setTimeout(() => form.submit(), 3200);
    });
  })();
</script>
</body>
</html>
