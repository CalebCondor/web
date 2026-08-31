<?php
// Pantalla de bienvenida
?>
<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0" />
  <title>Notarizalo</title>
  <link rel="preload" as="image" href="assets/poster.jpg" fetchpriority="high" />
  <link rel="preload" as="video" href="assets/Fondo.mp4" type="video/mp4" />
  <script src="https://cdn.tailwindcss.com"></script>
  <script>
    tailwind.config = {
      theme: { extend: {
        colors: { brand: '#1d4ed8' },
        fontFamily: { sans: ['Inter', 'system-ui', 'sans-serif'] },
      }},
    };
  </script>
  <link rel="preconnect" href="https://fonts.googleapis.com" />
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet" />
  <style>
    html {
      -webkit-text-size-adjust: 100%;
    }
    html, body {
      height: 100%;
      margin: 0;
      font-family: 'Inter', system-ui, sans-serif;
      overflow: hidden;
    }
    body {
      display: flex;
      justify-content: center;
      background: #0f172a;
    }
    .full-h { min-height: 100svh; }
    .bg-video {
      position: absolute;
      inset: 0;
      width: 100%;
      height: 100%;
      object-fit: cover;
      z-index: 0;
    }
    .bg-overlay {
      position: absolute;
      inset: 0;
      background: rgba(15, 23, 42, 0.3);
      z-index: 1;
    }
    .safe-bottom {
      padding-bottom: max(5rem, env(safe-area-inset-bottom) + 2rem);
    }
  </style>
</head>

<body class="bg-slate-900 md:bg-slate-200">
<div class="w-full md:max-w-sm full-h flex flex-col md:shadow-2xl relative overflow-hidden" style="background:#0f172a url('assets/poster.jpg') center/cover no-repeat">

  <video class="bg-video" src="assets/Fondo.mp4" poster="assets/poster.jpg" autoplay muted loop playsinline preload="auto" fetchpriority="high"></video>
  <div class="bg-overlay"></div>

  <!-- Logo -->
  <img src="assets/LogoS2.png" alt="Notarize" class="absolute top-5 left-5 h-9 w-auto z-20 sm:top-6 sm:left-6 sm:h-10" />

  <!-- Content -->
  <div class="flex-1 flex flex-col items-center justify-end px-6 sm:px-8 safe-bottom gap-6 sm:gap-8 relative z-10 mb-14">

    <div class="text-center text-white">
      <h1 class="text-3xl sm:text-[30px] font-extrabold leading-tight tracking-tight mb-3 drop-shadow-lg">
        Encuentra una<br>notaría cerca de ti.
      </h1>
      <p class="text-base text-white/80 leading-[1.5]">
        Conecta con un notario certificado<br>
        en segundos. Seguro, legal y 100% en línea.
      </p>
    </div>

    <a
      href="booking/step1.php"
      class="w-full max-w-xs mx-auto bg-blue-700 hover:bg-blue-800 active:scale-95 transition-all
             text-white text-base font-bold text-center
             rounded-[14px] py-4 min-h-[52px] flex items-center justify-center mt-6"
      style="box-shadow: 0 8px 24px rgba(29,78,216,0.30)">
      Comenzar
    </a>

  </div>

</div>
</body>
</html>
