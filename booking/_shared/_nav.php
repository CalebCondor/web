<?php $loggedIn = !empty($_SESSION['client_token']); ?>
<div class="px-3 pb-4 pt-2">
  <nav class="bg-[#253b8e] rounded-[22px] shadow-2xl overflow-hidden">
    <div class="flex divide-x divide-white/20">

      <a href="<?= APP_BASE ?>/index.php"
         class="flex-1 flex flex-col items-center py-4 gap-1.5 hover:bg-white/10 active:bg-white/20 transition">
        <svg class="w-6 h-6 text-white/70" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24">
          <path stroke-linecap="round" stroke-linejoin="round" d="M2.25 12l8.954-8.955a1.126 1.126 0 011.591 0L21.75 12M4.5 9.75v10.125c0 .621.504 1.125 1.125 1.125H9.75v-4.875c0-.621.504-1.125 1.125-1.125h2.25c.621 0 1.125.504 1.125 1.125V21h4.125c.621 0 1.125-.504 1.125-1.125V9.75M8.25 21h8.25" />
        </svg>
        <span class="text-white/70 text-[9px] font-semibold whitespace-nowrap">Inicio</span>
      </a>

      <a href="<?= APP_BASE ?>/step6.php"
         class="flex-1 flex flex-col items-center py-4 gap-1.5 hover:bg-white/10 active:bg-white/20 transition">
        <svg class="w-6 h-6 text-white/70" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24">
          <path stroke-linecap="round" stroke-linejoin="round" d="M19.5 14.25v-2.625a3.375 3.375 0 00-3.375-3.375h-1.5A1.125 1.125 0 0113.5 7.125v-1.5a3.375 3.375 0 00-3.375-3.375H8.25m0 12.75h7.5m-7.5 3H12M10.5 2.25H5.625c-.621 0-1.125.504-1.125 1.125v17.25c0 .621.504 1.125 1.125 1.125h12.75c.621 0 1.125-.504 1.125-1.125V11.25a9 9 0 00-9-9z" />
        </svg>
        <span class="text-white/70 text-[9px] font-semibold whitespace-nowrap">Mis Documentos</span>
      </a>

      <a href="<?= APP_BASE ?>/requests.php"
         class="flex-1 flex flex-col items-center py-4 gap-1.5 hover:bg-white/10 active:bg-white/20 transition">
        <svg class="w-6 h-6 text-white/70" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24">
          <path stroke-linecap="round" stroke-linejoin="round" d="M3.75 6.75h16.5M3.75 12h16.5m-16.5 5.25h16.5" />
        </svg>
        <span class="text-white/70 text-[9px] font-semibold whitespace-nowrap">Solicitudes</span>
      </a>

      <a href="<?= APP_BASE ?>/profile.php"
         class="flex-1 flex flex-col items-center py-4 gap-1.5 hover:bg-white/10 active:bg-white/20 transition">
        <svg class="w-6 h-6 text-white/70" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24">
          <path stroke-linecap="round" stroke-linejoin="round" d="M17.982 18.725A7.488 7.488 0 0012 15.75a7.488 7.488 0 00-5.982 2.975m11.963 0a9 9 0 10-11.963 0m11.963 0A8.966 8.966 0 0112 21a8.966 8.966 0 01-5.982-2.275M15 9.75a3 3 0 11-6 0 3 3 0 016 0z" />
        </svg>
        <span class="text-white/70 text-[9px] font-semibold whitespace-nowrap">Perfil</span>
      </a>

      <a href="<?= APP_BASE ?>/logout.php"
         class="flex-1 flex flex-col items-center py-4 gap-1.5 hover:bg-white/10 active:bg-white/20 transition">
        <svg class="w-6 h-6 text-white/70" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24">
          <path stroke-linecap="round" stroke-linejoin="round" d="M15.75 9V5.25A2.25 2.25 0 0013.5 3h-6a2.25 2.25 0 00-2.25 2.25v13.5A2.25 2.25 0 007.5 21h6a2.25 2.25 0 002.25-2.25V15M12 9l-3 3m0 0l3 3m-3-3h12.75" />
        </svg>
        <span class="text-white/70 text-[9px] font-semibold whitespace-nowrap">Cerrar Sesión</span>
      </a>

    </div>
  </nav>
</div>
