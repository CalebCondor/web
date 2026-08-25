<meta charset="UTF-8" />
<meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0" />
<script src="https://cdn.tailwindcss.com"></script>
<script>
  tailwind.config = {
    theme: { extend: {
      colors: { brand: '#1d4ed8' },
      fontFamily: { sans: ['Inter','system-ui','sans-serif'] },
    }},
  };
</script>
<link rel="preconnect" href="https://fonts.googleapis.com" />
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet" />
<link rel="icon" href="<?= APP_BASE ?>/assets/logo.png" />
<script src="https://unpkg.com/lucide@latest"></script>
<style>body{font-family:'Inter',system-ui,sans-serif;}</style>
<script>
  document.addEventListener('DOMContentLoaded', () => {
    if (window.lucide) lucide.createIcons();
  });
</script>
