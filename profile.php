<?php
require_once 'config.php';
session_init();
require_auth();

$user   = $_SESSION['user'] ?? [];
$id     = $user['id_cliente'] ?? null;
$token  = $_SESSION['client_token'] ?? '';
$saved  = false;
$error  = '';

// Fetch fresh data from API
$data = $user;
if ($id) {
    $res = api_json('GET', "/clientes/$id", [], $token);
    if ($res['success'] ?? false) $data = $res['data'];
}

$nombres   = $data['nombres']   ?? $data['nombre'] ?? '';
$apellidos = $data['apellidos'] ?? '';
$telefono  = $data['telefono']  ?? '';
$correo    = $data['correo']    ?? $data['email']  ?? '';
$initials  = strtoupper(substr($nombres,0,1) . substr($apellidos,0,1)) ?: '?';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $nombres   = trim($_POST['nombres']   ?? '');
    $apellidos = trim($_POST['apellidos'] ?? '');
    $telefono  = trim($_POST['telefono']  ?? '');

    if (!$nombres || !$apellidos) {
        $error = 'First name and last name are required.';
    } else {
        $res = api_json('PUT', "/clientes/$id", [
            'nombres'   => $nombres,
            'apellidos' => $apellidos,
            'telefono'  => $telefono,
        ], $token);

        if ($res['success'] ?? false) {
            $_SESSION['user'] = array_merge($_SESSION['user'], $res['data'] ?? [
                'nombres' => $nombres, 'apellidos' => $apellidos, 'telefono' => $telefono,
            ]);
            $saved = true;
            $initials = strtoupper(substr($nombres,0,1) . substr($apellidos,0,1)) ?: '?';
        } else {
            $error = $res['message'] ?? 'Could not update profile.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <title>Notarize — Profile</title>
  <?php include '_head.php'; ?>
</head>
<body class="bg-white md:bg-slate-200 min-h-screen flex justify-center">
<div class="w-full md:max-w-sm min-h-screen bg-slate-50 flex flex-col md:shadow-2xl relative">

  <div class="bg-[#1e3a8a] px-5 py-4 flex items-center justify-center">
    <span class="text-white font-extrabold text-base">My Profile</span>
  </div>

  <div class="flex-1 overflow-y-auto pb-4">

    <!-- Avatar -->
    <div class="flex flex-col items-center pt-8 pb-6 gap-1.5">
      <div class="w-16 h-16 rounded-full bg-blue-100 flex items-center justify-center font-extrabold text-blue-700 text-2xl mb-1">
        <?= htmlspecialchars($initials) ?>
      </div>
      <p class="text-lg font-bold text-slate-900"><?= htmlspecialchars($nombres . ' ' . $apellidos) ?></p>
      <p class="text-sm text-slate-500"><?= htmlspecialchars($correo) ?></p>
    </div>

    <?php if ($saved): ?>
      <div class="mx-5 mb-4 bg-green-50 border border-green-200 text-green-700 text-sm rounded-xl px-4 py-3 inline-flex items-center gap-1.5">
        <i data-lucide="check" class="w-4 h-4"></i> Profile updated successfully.
      </div>
    <?php endif; ?>

    <?php if ($error): ?>
      <div class="mx-5 mb-4 bg-red-50 border border-red-200 text-red-700 text-sm rounded-xl px-4 py-3">
        <?= htmlspecialchars($error) ?>
      </div>
    <?php endif; ?>

    <!-- Form -->
    <form method="POST" class="mx-5">
      <div class="bg-white rounded-2xl border border-slate-200 p-5 mb-5">
        <p class="text-sm font-extrabold text-slate-900 mb-4">Personal information</p>

        <label class="block text-xs font-bold text-slate-500 mb-1 mt-3">First name(s)</label>
        <input type="text" name="nombres" value="<?= htmlspecialchars($nombres) ?>"
          placeholder="Juan Carlos" required autocomplete="given-name"
          class="w-full border-[1.5px] border-slate-200 rounded-xl px-4 py-3 text-sm bg-slate-50 outline-none focus:border-blue-500" />

        <label class="block text-xs font-bold text-slate-500 mb-1 mt-3">Last name(s)</label>
        <input type="text" name="apellidos" value="<?= htmlspecialchars($apellidos) ?>"
          placeholder="García López" required autocomplete="family-name"
          class="w-full border-[1.5px] border-slate-200 rounded-xl px-4 py-3 text-sm bg-slate-50 outline-none focus:border-blue-500" />

        <label class="block text-xs font-bold text-slate-500 mb-1 mt-3">Phone</label>
        <input type="tel" name="telefono" value="<?= htmlspecialchars($telefono) ?>"
          placeholder="787 000 0000" autocomplete="tel"
          class="w-full border-[1.5px] border-slate-200 rounded-xl px-4 py-3 text-sm bg-slate-50 outline-none focus:border-blue-500" />

        <label class="block text-xs font-bold text-slate-500 mb-1 mt-3">Email (login)</label>
        <input type="email" value="<?= htmlspecialchars($correo) ?>" disabled
          class="w-full border-[1.5px] border-slate-100 rounded-xl px-4 py-3 text-sm bg-slate-100 text-slate-400 cursor-not-allowed" />
        <p class="text-[11px] text-slate-400 italic mt-1">Email cannot be changed — it is your login username.</p>
      </div>

      <button type="submit"
        class="w-full bg-blue-700 hover:bg-blue-800 active:scale-95 text-white font-bold rounded-2xl py-4 shadow-lg shadow-blue-700/30 transition">
        Save changes
      </button>
    </form>

  </div>

  <?php include '_nav.php'; ?>
</div>
</body>
</html>
