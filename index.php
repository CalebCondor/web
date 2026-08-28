<?php
// Pantalla de bienvenida
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover" />
  <title>Notarizalo</title>

  <!-- Inter font: preloaded + size-adjusted to prevent layout shift on swap -->
  <link rel="preconnect" href="https://fonts.googleapis.com" />
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin />
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet" />

  <style>
    /* Fallback stack with metrics matched to Inter so swapping in doesn't reflow */
    @font-face {
      font-family: 'Inter Fallback';
      src: local('Arial');
      size-adjust: 107%;
      ascent-override: 90%;
      descent-override: 22%;
      line-gap-override: 0%;
    }
    body { font-family: 'Inter', 'Inter Fallback', system-ui, sans-serif; }
    html, body { height: 100%; margin: 0; }
    body {
      /* Lock the viewport so iOS Safari URL bar can't cause jumps */
      min-height: 100dvh;
      min-height: 100vh;
      display: flex;
      justify-content: center;
      position: relative;
      overflow: hidden;
    }
    .bg-video {
      position: fixed;
      inset: 0;
      width: 100%;
      height: 100%;
      object-fit: cover;
      z-index: 0;
    }
    .bg-overlay {
      position: fixed;
      inset: 0;
      background: rgba(15, 23, 42, 0.55);
      z-index: 1;
    }
    .app-card {
      min-height: 100dvh;
      min-height: 100vh;
    }
  </style>
</head>

<body>
  <video class="bg-video" src="assets/Fondo.mp4" autoplay muted loop playsinline preload="auto"></video>
  <div class="bg-overlay"></div>

  <div class="app-card w-full md:max-w-sm flex flex-col md:shadow-2xl relative z-10">

    <!-- Logo -->
    <img src="assets/LogoS2.png" alt="Notarize" class="logo-white absolute top-6 left-6 h-10 w-auto z-20" />

    <!-- Mobile-width card -->
    <div class="flex-1 flex flex-col items-center justify-end px-8 pt-16 pb-20 gap-8">

      <!-- Headline + subtitle -->
      <div class="text-center text-white">
        <h1 class="text-[30px] font-extrabold leading-tight tracking-tight mb-3 drop-shadow-lg">
          Encuentra una<br>notaría cerca de ti,<br>al instante.
        </h1>
        <p class="text-sm text-white/80 leading-relaxed">
          Conecta con un notario certificado<br>
          en segundos. Seguro, legal y 100% en línea.
        </p>
      </div>

      <!-- CTA button -->
      <a
        href="booking/step1.php"
        class="w-full bg-blue-700 hover:bg-blue-800 active:scale-95 transition-all
               text-white text-base font-bold text-center
               rounded-[14px] py-4 shadow-btn block">
        Comenzar
      </a>

    </div>

  </div>

  <!-- Tailwind CDN loaded at end of body so it doesn't block initial paint -->
  <script src="https://cdn.tailwindcss.com"></script>
  <script>
    tailwind.config = {
      theme: {
        extend: {
          colors: { brand: '#1d4ed8' },
          fontFamily: { sans: ['Inter', 'Inter Fallback', 'system-ui', 'sans-serif'] },
          borderRadius: { app: '14px', video: '18px' },
          boxShadow: {
            btn:   '0 8px 24px rgba(29,78,216,0.30)',
            video: '0 8px 32px rgba(0,0,0,0.12)',
          },
        },
      },
    };
  </script>
</body>
</html>
