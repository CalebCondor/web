<?php
require_once 'config.php';
session_init();

$tab   = $_GET['tab'] ?? 'register';
$error = flash('error');
$isReg = $tab !== 'login';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    $correo = trim($_POST['correo'] ?? '');
    $clave  = $_POST['clave']       ?? '';

    if ($action === 'register') {
        $nombres   = trim($_POST['nombres']   ?? '');
        $apellidos = trim($_POST['apellidos'] ?? '');
        $telefono  = trim($_POST['telefono']  ?? '');

        if (!$nombres || !$apellidos || strpos($correo, '@') === false || strlen($clave) < 6) {
            flash('error', 'Please fill in all fields correctly (password min. 6 characters).');
            header('Location: auth.php?tab=register'); exit;
        }

        // Obtain admin token to create client
        $authRes    = api_json('POST', '/auth/login', ['usuario' => 'admin', 'clave' => 'Admin2024']);
        $adminToken = $authRes['token'] ?? $authRes['access_token'] ?? $authRes['data']['token'] ?? null;
        if (!$adminToken) {
            flash('error', 'Connection error. Please try again later.');
            header('Location: auth.php?tab=register'); exit;
        }

        $reg = api_json('POST', '/clientes', [
            'nombres' => $nombres, 'apellidos' => $apellidos,
            'correo'  => $correo,  'usuario'   => $correo,
            'clave'   => $clave,   'telefono'  => $telefono,
        ], $adminToken);

        if (!($reg['success'] ?? false)) {
            flash('error', $reg['message'] ?? 'Registration failed.');
            header('Location: auth.php?tab=register'); exit;
        }

        // Auto-login after registration
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
            flash('error', 'Please fill in all fields.');
            header('Location: auth.php?tab=login'); exit;
        }

        $res = api_json('POST', '/clientes/login', ['usuario' => $correo, 'clave' => $clave]);
        if (!($res['success'] ?? false)) {
            flash('error', $res['message'] ?? 'Invalid email or password.');
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
<html lang="en">
<head>
  <title>Notarize — <?= $isReg ? 'Create Account' : 'Sign In' ?></title>
  <?php include '_head.php'; ?>
</head>
<body class="bg-white md:bg-slate-200 min-h-screen flex justify-center">
<div class="w-full md:max-w-sm min-h-screen bg-slate-50 flex flex-col md:shadow-2xl relative">

  <div class="bg-[#1e3a8a] px-5 py-4 flex items-center gap-3">
    <a href="step1.php" class="text-blue-300 text-xl leading-none">‹</a>
    <span class="text-white font-extrabold text-base flex-1 text-center">Sign In / Register</span>
    <div class="w-6"></div>
  </div>

  <div class="flex-1 w-full px-6 py-8">
    <h1 class="text-2xl font-extrabold text-slate-900 mb-1">
      <?= $isReg ? 'Create account' : 'Sign in' ?>
    </h1>
    <p class="text-sm text-slate-500 mb-6">
      <?= $isReg ? 'Register to manage your notary services.' : 'Enter your email and password to continue.' ?>
    </p>

    <!-- Tabs -->
    <div class="bg-slate-200 rounded-xl p-1 flex gap-1 mb-6">
      <?php foreach ([['register','Register'],['login','Sign In']] as [$t,$lbl]): ?>
        <a href="?tab=<?= $t ?>"
           class="flex-1 text-center py-2.5 text-sm font-semibold rounded-lg transition
                  <?= $tab === $t ? 'bg-white text-slate-900 shadow' : 'text-slate-500 hover:text-slate-700' ?>">
          <?= $lbl ?>
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
          <label class="text-xs font-bold text-slate-600 mt-3 mb-1">First name(s)</label>
          <input type="text" name="nombres" placeholder="Juan Carlos" required
            class="border-[1.5px] border-slate-200 rounded-xl px-4 py-3 text-sm bg-slate-50 outline-none focus:border-blue-500" />

          <label class="text-xs font-bold text-slate-600 mt-3 mb-1">Last name(s)</label>
          <input type="text" name="apellidos" placeholder="García López" required
            class="border-[1.5px] border-slate-200 rounded-xl px-4 py-3 text-sm bg-slate-50 outline-none focus:border-blue-500" />

          <label class="text-xs font-bold text-slate-600 mt-3 mb-1">Phone</label>
          <input type="tel" name="telefono" placeholder="787 000 0000"
            class="border-[1.5px] border-slate-200 rounded-xl px-4 py-3 text-sm bg-slate-50 outline-none focus:border-blue-500" />
        <?php endif; ?>

        <label class="text-xs font-bold text-slate-600 mt-3 mb-1">Email address</label>
        <input type="email" name="correo" placeholder="your@email.com" required autocomplete="email"
          class="border-[1.5px] border-slate-200 rounded-xl px-4 py-3 text-sm bg-slate-50 outline-none focus:border-blue-500" />

        <label class="text-xs font-bold text-slate-600 mt-3 mb-1">Password</label>
        <div class="relative">
          <input id="pwdField" type="password" name="clave"
            placeholder="<?= $isReg ? 'Minimum 6 characters' : 'Your password' ?>" required autocomplete="current-password"
            class="w-full border-[1.5px] border-slate-200 rounded-xl px-4 py-3 text-sm bg-slate-50 outline-none focus:border-blue-500" />
          <button type="button" id="eyeBtn"
            onclick="const f=document.getElementById('pwdField');const b=document.getElementById('eyeBtn');f.type=f.type==='password'?'text':'password';b.textContent=f.type==='password'?'Show':'Hide'"
            class="absolute right-3 top-1/2 -translate-y-1/2 text-blue-700 text-xs font-bold">Show</button>
        </div>

        <button type="submit"
          class="w-full bg-blue-700 hover:bg-blue-800 active:scale-95 text-white font-bold rounded-2xl py-4 mt-6 shadow-lg shadow-blue-700/30 transition">
          <?= $isReg ? 'Create account' : 'Sign in' ?>
        </button>
      </form>
    </div>
  </div>
  <?php include '_nav.php'; ?>
</div>
</body>
</html>
