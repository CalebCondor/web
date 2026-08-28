<?php
require_once 'config.php';
session_init();

$tab   = $_GET['tab'] ?? 'register';
$error = flash('error');
$isReg = $tab !== 'login';

$lang = 'es';

// SIMULACIÓN (solo desarrollo): saltar directo a booking/step4.php
if (isset($_GET['sim']) && $_GET['sim'] === 'step4') {
    $_SESSION['booking'] = [
        'service_name' => 'Servicio demo',
        'service_id'   => 1,
        'notary'       => [
            'id_abogado'      => 1,
            'nombre'          => 'Notaría Demo',
            'tarifa_consulta' => 150,
            'moneda'          => 'USD',
        ],
        'domicilio' => 0,
        'fee_base'  => 150,
        'date'      => date('Y-m-d', strtotime('+3 days')),
        'time'      => '10:00',
    ];
    $_SESSION['user'] = [
        'id_cliente' => 1,
        'id'         => 1,
        'nombres'    => 'Cliente',
        'apellidos'  => 'Demo',
        'nombre'     => 'Cliente Demo',
        'correo'     => 'demo@notarize.test',
    ];
    $_SESSION['client_token'] = 'demo-token';
    header('Location: booking/step4.php'); exit;
}

$T = [
    'en' => [
        'page_title_register' => 'Create Account',
        'page_title_signin'   => 'Sign In',
        'header_title'        => 'Sign In / Register',
        'create_account'      => 'Create account',
        'sign_in'             => 'Sign in',
        'continue'            => 'Continue',
        'verify_title'        => 'Verify identity',
        'verify_subtitle'     => 'How would you like to receive your verification code?',
        'method_email'        => 'By email',
        'method_phone'        => 'By SMS',
        'phone_missing'       => 'Add a phone number in the form to use this option',
        'code_title'          => 'Enter the code',
        'code_subtitle_prefix'=> 'We sent a 6-digit code to',
        'verify'              => 'Verify',
        'resend'              => 'Resend code',
        'change_method'       => 'Change method',
        'no_code'             => "Didn't receive the code?",
        'err_code_length'     => 'Please enter all 6 digits.',
        'err_code_invalid'    => 'Incorrect code, please try again.',
        'tab_register'        => 'Register',
        'tab_signin'          => 'Sign In',
        'heading_h1'          => 'Enter your details',
        'heading_h2_register' => 'To prepare your reservation.',
        'heading_h2_signin'   => 'To continue your reservation.',
        'helper_text'         => 'Enter your name, email and phone so we can contact you.',
        'first_name'          => 'First name(s)',
        'last_name'           => 'Last name(s)',
        'phone'               => 'Phone',
        'email'               => 'Email address',
        'password'            => 'Password',
        'min_6_chars'         => 'Minimum 6 characters',
        'your_password'       => 'Your password',
        'show'                => 'Show',
        'hide'                => 'Hide',
        'err_fill_all'        => 'Please fill in all fields correctly (password min. 6 characters).',
        'err_connection'      => 'Connection error. Please try again later.',
        'err_register_failed' => 'Registration failed.',
        'err_fill_login'      => 'Please fill in all fields.',
        'err_invalid'         => 'Invalid email or password.',
    ],
    'es' => [
        'page_title_register' => 'Crear Cuenta',
        'page_title_signin'   => 'Iniciar Sesión',
        'header_title'        => 'Iniciar Sesión / Registrarse',
        'create_account'      => 'Crear cuenta',
        'sign_in'             => 'Iniciar sesión',
        'continue'            => 'Continuar',
        'verify_title'        => 'Verificar identidad',
        'verify_subtitle'     => '¿Cómo quieres recibir tu código de verificación?',
        'method_email'        => 'Por correo electrónico',
        'method_phone'        => 'Por mensaje de texto (SMS)',
        'phone_missing'       => 'Agrega un teléfono en el formulario para usar esta opción',
        'code_title'          => 'Ingresa el código',
        'code_subtitle_prefix'=> 'Te enviamos un código de 6 dígitos a',
        'verify'              => 'Verificar',
        'resend'              => 'Reenviar código',
        'change_method'       => 'Cambiar método',
        'no_code'             => '¿No recibiste el código?',
        'err_code_length'     => 'Ingresa los 6 dígitos del código.',
        'err_code_invalid'    => 'Código incorrecto, intenta de nuevo.',
        'tab_register'        => 'Registrarse',
        'tab_signin'          => 'Iniciar sesión',
        'heading_h1'          => 'Ingrese sus datos',
        'heading_h2_register' => 'Para poder prepararle su reserva.',
        'heading_h2_signin'   => 'Para continuar con su reserva.',
        'helper_text'         => 'Ingrese su nombre email y teléfono para poder comunicarse con usted',
        'first_name'          => 'Nombre(s)',
        'last_name'           => 'Apellido(s)',
        'phone'               => 'Teléfono',
        'email'               => 'Correo electrónico',
        'password'            => 'Contraseña',
        'min_6_chars'         => 'Mínimo 6 caracteres',
        'your_password'       => 'Tu contraseña',
        'show'                => 'Mostrar',
        'hide'                => 'Ocultar',
        'err_fill_all'        => 'Por favor completa todos los campos correctamente (contraseña mínimo 6 caracteres).',
        'err_connection'      => 'Error de conexión. Por favor intenta de nuevo más tarde.',
        'err_register_failed' => 'Registro fallido.',
        'err_fill_login'      => 'Por favor completa todos los campos.',
        'err_invalid'         => 'Correo o contraseña inválidos.',
    ],
];
$t = function (string $k) use ($T, $lang): string {
    return $T[$lang][$k] ?? $T['es'][$k] ?? $k;
};

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    $correo = trim($_POST['correo'] ?? '');
    $clave  = $_POST['clave']       ?? '';

    if ($action === 'register') {
        $nombres   = trim($_POST['nombres']   ?? '');
        $apellidos = trim($_POST['apellidos'] ?? '');
        $telefono  = trim($_POST['telefono']  ?? '');

        if (!$nombres || !$apellidos || strpos($correo, '@') === false || strlen($clave) < 6) {
            flash('error', $t('err_fill_all'));
            header('Location: auth.php?tab=register'); exit;
        }

        $authRes    = api_json('POST', '/auth/login', ['usuario' => 'admin', 'clave' => 'Admin2024']);
        $adminToken = $authRes['token'] ?? $authRes['access_token'] ?? $authRes['data']['token'] ?? null;
        if (!$adminToken) {
            flash('error', $t('err_connection'));
            header('Location: auth.php?tab=register'); exit;
        }

        $reg = api_json('POST', '/clientes', [
            'nombres' => $nombres, 'apellidos' => $apellidos,
            'correo'  => $correo,  'usuario'   => $correo,
            'clave'   => $clave,   'telefono'  => $telefono,
        ], $adminToken);

        if (!($reg['success'] ?? false)) {
            flash('error', $reg['message'] ?? $t('err_register_failed'));
            header('Location: auth.php?tab=register'); exit;
        }

        $login = api_json('POST', '/clientes/login', ['usuario' => $correo, 'clave' => $clave]);
        if ($login['success'] ?? false) {
            $_SESSION['client_token'] = $login['data']['token'];
            $_SESSION['user']         = $login['data'];
        } else {
            $_SESSION['user'] = $reg['data'];
        }
        $dest = !empty($_SESSION['booking']['notary']) ? 'booking/step4.php' : 'step2.php';
        header("Location: $dest"); exit;

    } elseif ($action === 'login') {
        if (!$correo || !$clave) {
            flash('error', $t('err_fill_login'));
            header('Location: auth.php?tab=login'); exit;
        }

        $res = api_json('POST', '/clientes/login', ['usuario' => $correo, 'clave' => $clave]);
        if (!($res['success'] ?? false)) {
            flash('error', $res['message'] ?? $t('err_invalid'));
            header('Location: auth.php?tab=login'); exit;
        }

        $_SESSION['client_token'] = $res['data']['token'];
        $_SESSION['user']         = $res['data'];
        $dest = !empty($_SESSION['booking']['notary']) ? 'booking/step4.php' : 'step2.php';
        header("Location: $dest"); exit;
    }
}
?>
<!DOCTYPE html>
<html lang="<?= htmlspecialchars($lang) ?>">
<head>
  <title>Notarize — <?= htmlspecialchars($t($isReg ? 'page_title_register' : 'page_title_signin')) ?></title>
  <?php include '_head.php'; ?>
  <script src="https://unpkg.com/lucide@latest"></script>
  <script>
    document.addEventListener('DOMContentLoaded', function () {
      if (window.lucide) lucide.createIcons();
    });
  </script>
</head>
<body class="bg-white md:bg-slate-200 min-h-screen flex justify-center">
<div class="w-full md:max-w-sm min-h-screen bg-slate-50 flex flex-col md:shadow-2xl relative">

  <div class="bg-[#1e3a8a] px-5 py-4 flex items-center gap-3">
    <a href="step1.php" class="text-blue-300 text-xl leading-none">‹</a>
    <span class="text-white font-extrabold text-base flex-1 text-center"><?= htmlspecialchars($t('header_title')) ?></span>
  </div>

  <div class="flex-1 w-full px-6 py-8">
    <h1 class="text-2xl font-extrabold text-slate-900 leading-tight">
      <?= htmlspecialchars($t('heading_h1')) ?>
    </h1>
    <h2 class="text-sm text-slate-500 mt-1 leading-snug">
      <?= htmlspecialchars($t($isReg ? 'heading_h2_register' : 'heading_h2_signin')) ?>
    </h2>

    <div class="grid grid-cols-2 gap-2 mt-6">
      <?php foreach ([['register', $t('tab_register')], ['login', $t('tab_signin')]] as [$tg, $lbl]): ?>
        <a href="?<?= http_build_query(['tab' => $tg]) ?>"
           class="text-center py-2.5 text-sm font-semibold rounded-xl transition
                  <?= $tab === $tg
                    ? 'bg-blue-600 text-white shadow-md shadow-blue-600/30'
                    : 'bg-blue-50 text-blue-700 hover:bg-blue-100' ?>">
          <?= htmlspecialchars($lbl) ?>
        </a>
      <?php endforeach; ?>
    </div>

    <?php if ($error): ?>
      <div class="bg-red-50 border border-red-200 text-red-700 text-sm rounded-xl px-4 py-3 mb-4">
        <?= htmlspecialchars($error) ?>
      </div>
    <?php endif; ?>

    <div class="bg-white rounded-2xl p-5 shadow-sm border border-slate-100">
      <form method="POST" class="flex flex-col gap-0">
        <input type="hidden" name="action" value="<?= $tab ?>">

        <?php if ($isReg): ?>
          <label class="text-xs font-bold text-slate-600 mt-3 mb-1"><?= htmlspecialchars($t('first_name')) ?></label>
          <input type="text" name="nombres" placeholder="Juan Carlos" required
            class="border-[1.5px] border-slate-200 rounded-xl px-4 py-3 text-sm bg-slate-50 outline-none focus:border-blue-500" />

          <label class="text-xs font-bold text-slate-600 mt-3 mb-1"><?= htmlspecialchars($t('last_name')) ?></label>
          <input type="text" name="apellidos" placeholder="García López" required
            class="border-[1.5px] border-slate-200 rounded-xl px-4 py-3 text-sm bg-slate-50 outline-none focus:border-blue-500" />

          <label class="text-xs font-bold text-slate-600 mt-3 mb-1"><?= htmlspecialchars($t('phone')) ?></label>
          <input type="tel" name="telefono" placeholder="787 000 0000"
            class="border-[1.5px] border-slate-200 rounded-xl px-4 py-3 text-sm bg-slate-50 outline-none focus:border-blue-500" />
        <?php endif; ?>

        <label class="text-xs font-bold text-slate-600 mt-3 mb-1"><?= htmlspecialchars($t('email')) ?></label>
        <input type="email" name="correo" placeholder="your@email.com" required autocomplete="email"
          class="border-[1.5px] border-slate-200 rounded-xl px-4 py-3 text-sm bg-slate-50 outline-none focus:border-blue-500" />

      

        <p class="text-xs text-slate-500 mt-4 leading-relaxed">
          <?= htmlspecialchars($t('helper_text')) ?>
        </p>

        <button type="submit" id="continueBtn"
          class="w-full bg-blue-700 hover:bg-blue-800 active:scale-95 text-white font-bold rounded-2xl py-4 mt-6 shadow-lg shadow-blue-700/30 transition">
          <?= htmlspecialchars($t('continue')) ?>
        </button>

        <a href="?sim=step4"
           class="block text-center mt-3 text-xs text-slate-500 hover:text-blue-700 underline">
          [DEV] Simular paso 4
        </a>
      </form>
    </div>
  </div>

  <div id="otpMethodModal" class="hidden fixed inset-0 bg-black/60 z-50 flex items-end md:items-center justify-center p-0 md:p-4 backdrop-blur-sm">
    <div class="bg-white w-full md:max-w-sm rounded-t-3xl md:rounded-3xl p-6 shadow-2xl animate-[slideUp_0.25s_ease-out]">
      <div class="flex items-center justify-between mb-1">
        <h3 class="text-lg font-extrabold text-slate-900"><?= htmlspecialchars($t('verify_title')) ?></h3>
        <button type="button" id="closeMethodModal" class="text-slate-400 hover:text-slate-700 w-8 h-8 flex items-center justify-center">
          <i data-lucide="x" class="w-5 h-5"></i>
        </button>
      </div>
      <p class="text-sm text-slate-500 mb-5"><?= htmlspecialchars($t('verify_subtitle')) ?></p>

      <div class="flex flex-col gap-3">
        <button type="button" data-method="email" class="method-btn flex items-center gap-3 border-2 border-slate-200 hover:border-blue-500 active:scale-[0.98] rounded-2xl p-4 text-left transition">
          <span class="w-11 h-11 rounded-full bg-blue-50 flex items-center justify-center text-blue-600"><i data-lucide="mail" class="w-5 h-5"></i></span>
          <div class="flex-1 min-w-0">
            <div class="font-bold text-sm text-slate-900"><?= htmlspecialchars($t('method_email')) ?></div>
            <div class="text-xs text-slate-500 truncate" id="emailPreview">—</div>
          </div>
          <i data-lucide="chevron-right" class="w-4 h-4 text-slate-300"></i>
        </button>

        <button type="button" data-method="phone" id="phoneMethodBtn" class="method-btn flex items-center gap-3 border-2 border-slate-200 hover:border-blue-500 active:scale-[0.98] rounded-2xl p-4 text-left transition">
          <span class="w-11 h-11 rounded-full bg-blue-50 flex items-center justify-center text-blue-600"><i data-lucide="message-square" class="w-5 h-5"></i></span>
          <div class="flex-1 min-w-0">
            <div class="font-bold text-sm text-slate-900"><?= htmlspecialchars($t('method_phone')) ?></div>
            <div class="text-xs text-slate-500 truncate" id="phonePreview">—</div>
          </div>
          <i data-lucide="chevron-right" class="w-4 h-4 text-slate-300"></i>
        </button>
      </div>
    </div>
  </div>

  <div id="otpVerifyModal" class="hidden fixed inset-0 bg-black/60 z-50 flex items-end md:items-center justify-center p-0 md:p-4 backdrop-blur-sm">
    <div class="bg-white w-full md:max-w-sm rounded-t-3xl md:rounded-3xl p-6 shadow-2xl animate-[slideUp_0.25s_ease-out]">
      <button type="button" id="backToMethod" class="text-blue-600 text-sm font-semibold mb-3 inline-flex items-center gap-1">
        <i data-lucide="arrow-left" class="w-4 h-4"></i> <?= htmlspecialchars($t('change_method')) ?>
      </button>
      <h3 class="text-lg font-extrabold text-slate-900 mb-1"><?= htmlspecialchars($t('code_title')) ?></h3>
      <p class="text-sm text-slate-500 mb-5 leading-relaxed">
        <?= htmlspecialchars($t('code_subtitle_prefix')) ?>
        <span id="codeDestination" class="font-semibold text-slate-700"></span>
      </p>

      <div class="flex gap-2 justify-center mb-2" id="otpInputs">
        <input type="text" inputmode="numeric" pattern="[0-9]" maxlength="1" class="otp-input w-11 h-14 text-center text-2xl font-bold border-2 border-slate-200 rounded-xl focus:border-blue-500 outline-none bg-slate-50" />
        <input type="text" inputmode="numeric" pattern="[0-9]" maxlength="1" class="otp-input w-11 h-14 text-center text-2xl font-bold border-2 border-slate-200 rounded-xl focus:border-blue-500 outline-none bg-slate-50" />
        <input type="text" inputmode="numeric" pattern="[0-9]" maxlength="1" class="otp-input w-11 h-14 text-center text-2xl font-bold border-2 border-slate-200 rounded-xl focus:border-blue-500 outline-none bg-slate-50" />
        <input type="text" inputmode="numeric" pattern="[0-9]" maxlength="1" class="otp-input w-11 h-14 text-center text-2xl font-bold border-2 border-slate-200 rounded-xl focus:border-blue-500 outline-none bg-slate-50" />
        <input type="text" inputmode="numeric" pattern="[0-9]" maxlength="1" class="otp-input w-11 h-14 text-center text-2xl font-bold border-2 border-slate-200 rounded-xl focus:border-blue-500 outline-none bg-slate-50" />
        <input type="text" inputmode="numeric" pattern="[0-9]" maxlength="1" class="otp-input w-11 h-14 text-center text-2xl font-bold border-2 border-slate-200 rounded-xl focus:border-blue-500 outline-none bg-slate-50" />
      </div>

      <div id="otpError" class="hidden text-xs text-red-600 text-center mb-3 font-semibold"></div>

      <button type="button" id="verifyOtpBtn" class="w-full bg-blue-700 hover:bg-blue-800 active:scale-95 text-white font-bold rounded-2xl py-4 mt-3 shadow-lg shadow-blue-700/30 transition">
        <?= htmlspecialchars($t('verify')) ?>
      </button>

      <p class="text-xs text-slate-500 text-center mt-4">
        <?= htmlspecialchars($t('no_code')) ?>
        <button type="button" id="resendCode" class="text-blue-600 font-semibold hover:underline"><?= htmlspecialchars($t('resend')) ?></button>
      </p>
    </div>
  </div>

  <?php include '_nav.php'; ?>
</div>
<style>
  @keyframes slideUp { from { transform: translateY(20px); opacity: 0; } to { transform: translateY(0); opacity: 1; } }
</style>
<script>
  (function () {
    var f = document.getElementById('pwdField');
    var b = document.getElementById('eyeBtn');
    if (f && b) {
      b.addEventListener('click', function () {
        f.type = f.type === 'password' ? 'text' : 'password';
        b.textContent = f.type === 'password' ? b.dataset.show : b.dataset.hide;
      });
    }

    var form          = document.querySelector('form');
    var methodModal   = document.getElementById('otpMethodModal');
    var verifyModal   = document.getElementById('otpVerifyModal');
    var closeBtn      = document.getElementById('closeMethodModal');
    var backBtn       = document.getElementById('backToMethod');
    var methodBtns    = document.querySelectorAll('.method-btn');
    var verifyBtn     = document.getElementById('verifyOtpBtn');
    var resendBtn     = document.getElementById('resendCode');
    var otpInputs     = document.querySelectorAll('.otp-input');
    var emailInput    = form.querySelector('input[name="correo"]');
    var phoneInput    = form.querySelector('input[name="telefono"]');
    var phoneMethodBtn= document.getElementById('phoneMethodBtn');
    var emailPreview  = document.getElementById('emailPreview');
    var phonePreview  = document.getElementById('phonePreview');
    var codeDest      = document.getElementById('codeDestination');
    var otpError      = document.getElementById('otpError');

    var i18n = {
      phone_missing: <?= json_encode($t('phone_missing')) ?>
    };

    var selectedMethod = null;
    var generatedCode  = null;

    function updateMethodAvailability() {
      if (!phoneMethodBtn) return;
      var phone = phoneInput ? phoneInput.value.trim() : '';
      if (!phone) {
        phoneMethodBtn.classList.add('opacity-50', 'pointer-events-none');
        phonePreview.textContent = i18n.phone_missing;
      } else {
        phoneMethodBtn.classList.remove('opacity-50', 'pointer-events-none');
        phonePreview.textContent = phone;
      }
      emailPreview.textContent = emailInput.value || '—';
    }

    form.addEventListener('submit', function (e) {
      e.preventDefault();
      if (!form.checkValidity()) { form.reportValidity(); return; }
      updateMethodAvailability();
      methodModal.classList.remove('hidden');
      document.body.style.overflow = 'hidden';
    });

    function closeMethodModal() {
      methodModal.classList.add('hidden');
      document.body.style.overflow = '';
    }
    function closeVerifyModal() {
      verifyModal.classList.add('hidden');
      document.body.style.overflow = '';
    }
    closeBtn.addEventListener('click', closeMethodModal);
    backBtn.addEventListener('click', function () { closeVerifyModal(); methodModal.classList.remove('hidden'); document.body.style.overflow = 'hidden'; });

    methodModal.addEventListener('click', function (e) { if (e.target === methodModal) closeMethodModal(); });
    verifyModal.addEventListener('click', function (e) { if (e.target === verifyModal) { /* keep open */ } });

    methodBtns.forEach(function (btn) {
      btn.addEventListener('click', function () {
        if (btn.classList.contains('pointer-events-none')) return;
        selectedMethod = btn.dataset.method;
        var dest = selectedMethod === 'email' ? emailInput.value : (phoneInput ? phoneInput.value : '');
        codeDest.textContent = dest;
        generatedCode = String(Math.floor(100000 + Math.random() * 900000));
        // En producción: llamar al backend para enviar el código por email/SMS.
        console.log('[OTP] código generado (demo):', generatedCode);
        closeMethodModal();
        verifyModal.classList.remove('hidden');
        document.body.style.overflow = 'hidden';
        otpInputs.forEach(function (i) { i.value = ''; });
        otpError.classList.add('hidden');
        otpInputs[0].focus();
      });
    });

    resendBtn.addEventListener('click', function () {
      generatedCode = String(Math.floor(100000 + Math.random() * 900000));
      console.log('[OTP] reenvío (demo):', generatedCode);
      otpInputs.forEach(function (i) { i.value = ''; });
      otpError.classList.add('hidden');
      otpInputs[0].focus();
    });

    otpInputs.forEach(function (input, idx) {
      input.addEventListener('input', function (e) {
        var v = e.target.value.replace(/\D/g, '');
        e.target.value = v;
        if (v && idx < otpInputs.length - 1) otpInputs[idx + 1].focus();
      });
      input.addEventListener('keydown', function (e) {
        if (e.key === 'Backspace' && !e.target.value && idx > 0) otpInputs[idx - 1].focus();
      });
      input.addEventListener('paste', function (e) {
        var data = (e.clipboardData || window.clipboardData).getData('text').replace(/\D/g, '').slice(0, 6);
        if (data.length === 6) {
          e.preventDefault();
          otpInputs.forEach(function (i, k) { i.value = data[k] || ''; });
          otpInputs[5].focus();
        }
      });
    });

    verifyBtn.addEventListener('click', function () {
      var entered = Array.from(otpInputs).map(function (i) { return i.value; }).join('');
      if (entered.length !== 6) {
        otpError.textContent = <?= json_encode($t('err_code_length')) ?>;
        otpError.classList.remove('hidden');
        return;
      }
      if (entered !== generatedCode) {
        otpError.textContent = <?= json_encode($t('err_code_invalid')) ?>;
        otpError.classList.remove('hidden');
        otpInputs.forEach(function (i) { i.value = ''; });
        otpInputs[0].focus();
        return;
      }
      closeVerifyModal();
      var otpField = form.querySelector('input[name="otp_verified"]');
      if (!otpField) {
        otpField = document.createElement('input');
        otpField.type = 'hidden';
        otpField.name = 'otp_verified';
        form.appendChild(otpField);
      }
      otpField.value = '1';
      var methodField = form.querySelector('input[name="otp_method"]');
      if (!methodField) {
        methodField = document.createElement('input');
        methodField.type = 'hidden';
        methodField.name = 'otp_method';
        form.appendChild(methodField);
      }
      methodField.value = selectedMethod || '';
      form.submit();
    });
  })();
</script>
</body>
</html>
