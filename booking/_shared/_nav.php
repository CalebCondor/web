<?php
  $current = basename($_SERVER['PHP_SELF']);
  $navItem = function(string $href, string $icon, string $label, string $file) use ($current) {
    $active = $current === $file;
    $base = 'flex-1 flex flex-col items-center justify-center gap-1 py-3 transition relative';
    if ($active) {
      $base .= ' text-white';
    } else {
      $base .= ' text-white/60 hover:text-white hover:bg-white/5';
    }
    $pill = $active
      ? '<span class="absolute top-0 left-1/2 -translate-x-1/2 w-8 h-1 bg-white rounded-b-full"></span>'
      : '';
    echo '<a href="' . htmlspecialchars($href) . '" class="' . $base . '">'
       . $pill
       . '<i data-lucide="' . $icon . '" class="w-6 h-6 ' . ($active ? 'text-white' : 'text-white/60') . '"></i>'
       . '<span class="text-[10px] font-bold whitespace-nowrap tracking-wide">' . $label . '</span>'
       . '</a>';
  };
?>
<div class="px-3 pb-4 pt-2">
  <nav class="bg-[#253b8e] rounded-[22px] shadow-2xl overflow-hidden">
    <div class="flex">

      <?php $navItem(APP_BASE . '/index.php',    'home',         'Inicio',          'index.php'); ?>
      <?php $navItem(APP_BASE . '/step6.php',    'file-text',    'Documentos',  'step6.php'); ?>
      <?php $navItem(APP_BASE . '/requests.php', 'list-checks',  'Solicitudes',     'requests.php'); ?>
      <?php $navItem(APP_BASE . '/profile.php',  'user-round',   'Perfil',          'profile.php'); ?>

      <a href="<?= APP_BASE ?>/logout.php"
         class="flex-1 flex flex-col items-center justify-center gap-1 py-3 transition text-red-200 hover:text-white hover:bg-red-500/20">
        <i data-lucide="log-out" class="w-6 h-6"></i>
        <span class="text-[10px] font-bold whitespace-nowrap tracking-wide">Salir</span>
      </a>

    </div>
  </nav>
</div>
