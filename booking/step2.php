<?php
require_once '../config.php';
session_init();
if (empty($_SESSION['booking'])) { header('Location: step1.php'); exit; }

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $date      = $_POST['date']      ?? '';
    $time      = $_POST['time']      ?? '';
    $domicilio = (int)($_POST['domicilio'] ?? 0);

    if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $date) && preg_match('/^\d{2}:\d{2}$/', $time)) {
        $_SESSION['booking'] = array_merge($_SESSION['booking'], [
            'date'        => $date,
            'time'        => $time,
            'domicilio'   => $domicilio,
            'show_modal'  => true,
        ]);
        header('Location: step3.php'); exit;
    }
    $error = 'Por favor selecciona una fecha y hora válidas.';
}

$booking = $_SESSION['booking'];
$todayY  = (int)date('Y');
$todayM  = (int)date('n') - 1; // 0-based for JS
$todayD  = (int)date('j');
?>
<!DOCTYPE html>
<html lang="es">
<head>
  <title>Notarize — Fecha y Hora</title>
  <?php include '../_head.php'; ?>
</head>
<body class="bg-white md:bg-slate-200 min-h-screen flex justify-center">
<style>
  .fluid-section {
    overflow: hidden;
    max-height: 0;
    min-height: 0;
    opacity: 0;
    transform: translateY(18px);
    transition: opacity 380ms ease-out, transform 380ms ease-out, max-height 500ms ease-out;
  }
  .fluid-section.fluid-shown {
    max-height: 1200px;
    opacity: 1;
    transform: translateY(0);
  }
  .fluid-pop {
    animation: fluidPop 420ms cubic-bezier(0.22, 1, 0.36, 1);
  }
  @keyframes fluidPop {
    0%   { opacity: 0; transform: translateY(14px) scale(0.98); }
    60%  { opacity: 1; transform: translateY(-2px) scale(1.01); }
    100% { opacity: 1; transform: translateY(0) scale(1); }
  }
  #periodSel, #customTimeWrap {
    transition: all 320ms ease-out;
  }
  #calendarWrap {
    animation: fluidPop 380ms ease-out;
  }
</style>
<div class="w-full md:max-w-sm min-h-screen bg-slate-50 flex flex-col md:shadow-2xl relative">

  <div class="bg-[#1e3a8a] px-5 py-4 sticky top-0 z-50 flex items-center gap-3">
    <img src="../assets/LogoS2.png" alt="Notarize" class="h-11 w-auto brightness-0 invert">
  </div>



  <form method="POST" class="flex-1 w-full px-6 py-6 flex flex-col gap-5">
    <input type="hidden" id="hidDate" name="date" value="" />
    <input type="hidden" id="hidTime" name="time" value="" />
    <input type="hidden" id="hidDom"  name="domicilio" value="0" />

    <!-- Progress stepper -->
    <nav aria-label="progress" class="py-3">
      <!-- Progress bar -->
      <div class="flex items-center gap-1 mb-2">
        <div class="flex-1 h-1.5 rounded-full bg-blue-700"></div>
        <div class="flex-1 h-1.5 rounded-full bg-blue-700"></div>
        <div class="flex-1 h-1.5 rounded-full bg-slate-200"></div>
        <div class="flex-1 h-1.5 rounded-full bg-slate-200"></div>
      </div>
      <!-- Step label -->
      <div class="flex items-center justify-between">
        <span class="text-[10px] font-bold text-blue-700 uppercase tracking-wider">Paso 2 de 4</span>
        <span class="text-xs font-extrabold text-slate-900">Fecha y Hora</span>
      </div>
    </nav>

    <?php if (!empty($error)): ?>
      <div class="bg-red-50 border border-red-200 text-red-700 text-sm rounded-xl px-4 py-3">
        <?= htmlspecialchars($error) ?>
      </div>
    <?php endif; ?>

    <!-- Date selection -->
    <div>
      <h1 class="text-sm font-bold text-blue-900 uppercase tracking-wide mb-2">Fecha y Hora</h1>
      <p class="text-xl font-extrabold text-slate-900 mb-3">¿Cuándo te gustaría<br>realizar tu servicio?</p>

      <div class="flex flex-col gap-2">
        <?php
          $dias   = ['Dom','Lun','Mar','Mié','Jue','Vie','Sáb'];
          $meses  = ['Ene','Feb','Mar','Abr','May','Jun','Jul','Ago','Sep','Oct','Nov','Dic'];
          $fmt = function(string $iso) use ($dias, $meses): string {
            $ts = strtotime($iso);
            return $dias[(int)date('w', $ts)] . ', ' . $meses[(int)date('n', $ts) - 1] . ' ' . date('j', $ts);
          };
          $today    = $fmt(date('Y-m-d'));
          $tomorrow = $fmt(date('Y-m-d', strtotime('+1 day')));
          foreach ([
            ['today',    'zap',           'Hoy',                  $today],
            ['tomorrow', 'calendar',      'Mañana',                $tomorrow],
            ['custom',   'calendar-days', 'Elegir otra fecha',     'Selecciona una fecha específica'],
          ] as [$key, $icon, $label, $sub]):
        ?>
          <label class="date-opt flex items-center gap-3 bg-white rounded-2xl p-4 border-[1.5px] border-slate-200 cursor-pointer transition" data-key="<?= $key ?>">
            <input type="radio" name="_dateOpt" value="<?= $key ?>" class="sr-only" />
            <div class="opt-icon w-10 h-10 rounded-xl bg-slate-100 flex items-center justify-center text-slate-600 shrink-0 transition"><i data-lucide="<?= $icon ?>" class="w-5 h-5"></i></div>
            <div class="flex-1">
              <p class="opt-label font-bold text-slate-900 text-sm"><?= $label ?></p>
              <p class="text-xs text-slate-400"><?= $sub ?></p>
            </div>
            <div class="opt-radio w-5 h-5 rounded-full border-2 border-slate-300 flex items-center justify-center shrink-0">
              <div class="opt-dot w-2.5 h-2.5 rounded-full bg-blue-700 hidden"></div>
            </div>
          </label>
        <?php endforeach; ?>
      </div>

      <!-- Mini calendar (shown for "custom") -->
      <div id="calendarWrap" class="hidden mt-3">
        <div class="bg-white border border-slate-200 rounded-2xl p-4">
          <div class="flex items-center justify-between mb-3">
            <button type="button" id="calPrev" class="w-9 h-9 flex items-center justify-center text-blue-700 text-xl font-bold hover:bg-blue-50 rounded-xl transition">‹</button>
            <span id="calTitle" class="font-extrabold text-slate-900 text-sm"></span>
            <button type="button" id="calNext" class="w-9 h-9 flex items-center justify-center text-blue-700 text-xl font-bold hover:bg-blue-50 rounded-xl transition">›</button>
          </div>
          <div class="grid grid-cols-7 gap-0.5 mb-1 text-center">
            <?php foreach (['Lu','Ma','Mi','Ju','Vi','Sá','Do'] as $d): ?>
              <span class="text-[10px] font-bold text-slate-400"><?= $d ?></span>
            <?php endforeach; ?>
          </div>
          <div id="calGrid" class="grid grid-cols-7 gap-0.5 text-center"></div>
        </div>
      </div>
    </div>

    <!-- Time picker -->
    <div id="timeSection" class="fluid-section hidden">
      <p class="text-sm font-bold text-slate-700 mb-2">Disponibilidad de horas</p>

      <select id="periodSel" onchange="onPeriodChange()"
        class="w-full bg-white border border-slate-200 rounded-xl px-4 py-3 text-sm font-semibold text-slate-900 outline-none">
        <option value="">Selecciona un horario</option>
        <option value="morning">Mañana — 8:00 AM a 12:00 PM</option>
        <option value="afternoon">Tarde — 1:00 PM a 5:00 PM</option>
        <option value="evening">Por las noches</option>
        <option value="custom">Otros (escoge la hora)</option>
      </select>

      <div id="customTimeWrap" class="hidden mt-3 fluid-pop-target">
        <div class="flex gap-2 items-center">
          <select id="hourSel" onchange="buildTime()"
            class="flex-1 bg-white border border-slate-200 rounded-xl px-4 py-3 text-sm font-semibold text-slate-900 outline-none">
          </select>
          <div class="flex rounded-xl border border-slate-200 overflow-hidden bg-white">
            <?php foreach (['AM','PM'] as $p): ?>
              <button type="button" id="btn<?= $p ?>" onclick="setAmpm('<?= $p ?>')"
                class="ampm-btn px-5 py-3 text-sm font-bold transition">
                <?= $p ?>
              </button>
            <?php endforeach; ?>
          </div>
        </div>
      </div>

      <p id="timeDisplay" class="text-xs text-slate-400 mt-1.5 text-right hidden"></p>
    </div>

    <!-- Location preference -->
      <div id="locSection" class="fluid-section hidden">
      <p class="text-sm font-bold text-slate-700 mb-2">¿Dónde se realizará el servicio?</p>
      <div class="flex gap-3">
      <?php foreach ([            ['notary','building-2','En la notaría',0],['home','home','A mi domicilio',1]] as [$key,$icon,$lbl,$val]): ?>
          <label class="place-opt flex-1 flex flex-col items-center justify-center gap-3 aspect-[5/4] bg-white rounded-xl py-4 px-3 border-[1.5px] border-slate-200 cursor-pointer transition text-center" data-val="<?= $val ?>" data-key="<?= $key ?>">
            <input type="radio" name="_place" value="<?= $key ?>" class="sr-only" />
            <i data-lucide="<?= $icon ?>" class="place-icon w-12 h-12 text-slate-500 transition"></i>
            <span class="place-label text-xs font-semibold text-slate-700 transition"><?= $lbl ?></span>
          </label>
        <?php endforeach; ?>
      </div>
    </div>

    <div class="mt-auto flex flex-col gap-2">
      <p id="missingHint" class="hidden text-center text-xs text-slate-500">
        Completa los pasos para continuar
      </p>
      <button type="submit" id="continueBtn" disabled
        class="w-full bg-blue-700 hover:bg-blue-800 active:scale-95 text-white font-extrabold
               rounded-2xl py-4 shadow-lg shadow-blue-700/30 transition
               disabled:opacity-40 disabled:pointer-events-none">
        Continuar →
      </button>
    </div>
  </form>

  <script>
    const todayISO   = new Date().toISOString().slice(0, 10);

    // ── Fluid show/hide helpers ───────────────────────────────────────────────
    function revealSection(el, scroll = true) {
      if (!el) return;
      if (el.classList.contains('fluid-shown')) {
        if (scroll) setTimeout(() => el.scrollIntoView({ behavior: 'smooth', block: 'center' }), 80);
        return;
      }
      el.classList.remove('hidden');
      void el.offsetHeight;
      el.classList.add('fluid-shown');
      const popChildren = el.querySelectorAll('.fluid-pop-target');
      popChildren.forEach(c => {
        c.classList.remove('fluid-pop');
        void c.offsetWidth;
        c.classList.add('fluid-pop');
      });
      if (scroll) {
        setTimeout(() => el.scrollIntoView({ behavior: 'smooth', block: 'center' }), 120);
      }
    }
    function hideSection(el) {
      if (!el) return;
      el.classList.remove('fluid-shown');
      setTimeout(() => {
        if (!el.classList.contains('fluid-shown')) el.classList.add('hidden');
      }, 420);
    }
    const MONTHS     = ['Enero','Febrero','Marzo','Abril','Mayo','Junio','Julio','Agosto','Septiembre','Octubre','Noviembre','Diciembre'];
    const nowH       = new Date().getHours();   // 0-23, current hour
    const minH24Today = nowH + 1;               // first bookable hour today

    // 12h + ampm → 24h
    function to24(h, ampm) {
      if (ampm === 'AM') return h === 12 ? 0 : h;
      return h === 12 ? 12 : h + 12;
    }
    // 24h → {h, ampm}
    function from24(h24) {
      if (h24 === 0)  return { h: 12, ampm: 'AM' };
      if (h24 < 12)  return { h: h24, ampm: 'AM' };
      if (h24 === 12) return { h: 12, ampm: 'PM' };
      return { h: h24 - 12, ampm: 'PM' };
    }

    const initT    = from24(Math.min(minH24Today, 23));
    let selectedAmpm = initT.ampm;  // default to current+1 period

    let selectedDate = '';
    let calYear  = <?= $todayY ?>;
    let calMonth = <?= $todayM ?>;

    // User-interaction flags — button only enables after user actively picks all 3
    let dateTouched  = false;
    let timeTouched  = false;
    let placeTouched = false;
    let internalBuild = false;  // distinguishes auto-fill vs user action in buildTime

    const hidDate = document.getElementById('hidDate');
    const hidTime = document.getElementById('hidTime');
    const hidDom  = document.getElementById('hidDom');
    const btn     = document.getElementById('continueBtn');
    const summary = document.getElementById('summaryChip');

    // ── Date options ──────────────────────────────────────────────────────────
    function getDateForOpt(opt) {
      const d = new Date();
      if (opt === 'today')    return d.toISOString().slice(0,10);
      if (opt === 'tomorrow') { d.setDate(d.getDate()+1); return d.toISOString().slice(0,10); }
      return '';
    }

    document.querySelectorAll('.date-opt input').forEach(r => {
      r.addEventListener('change', () => {
        document.querySelectorAll('.date-opt').forEach(c => {
          c.classList.remove('border-blue-600','bg-blue-50');
          c.querySelector('.opt-icon').classList.remove('bg-blue-100');
          c.querySelector('.opt-label').classList.remove('text-blue-700');
          c.querySelector('.opt-radio').classList.remove('border-blue-700');
          c.querySelector('.opt-dot').classList.add('hidden');
        });
        const card = r.closest('.date-opt');
        card.classList.add('border-blue-600','bg-blue-50');
        card.querySelector('.opt-icon').classList.add('bg-blue-100');
        card.querySelector('.opt-label').classList.add('text-blue-700');
        card.querySelector('.opt-radio').classList.add('border-blue-700');
        card.querySelector('.opt-dot').classList.remove('hidden');

        dateTouched = true;
        const opt = r.value;
        document.getElementById('calendarWrap').classList.toggle('hidden', opt !== 'custom');
        const timeSec = document.getElementById('timeSection');
        const locSec  = document.getElementById('locSection');
        if (opt !== 'custom') {
          selectedDate = getDateForOpt(opt);
          refreshTimePicker(selectedDate);
          updateSummary();
          revealSection(timeSec);
        } else {
          selectedDate = '';
          refreshTimePicker('');
          updateSummary();
          hideSection(timeSec);
          hideSection(locSec);
          setTimeout(() => {
            document.getElementById('calendarWrap').scrollIntoView({ behavior: 'smooth', block: 'center' });
          }, 200);
        }
      });
    });

    // ── Mini calendar ─────────────────────────────────────────────────────────
    function renderCal() {
      document.getElementById('calTitle').textContent = MONTHS[calMonth] + ' ' + calYear;
      const grid      = document.getElementById('calGrid');
      const firstDay  = new Date(calYear, calMonth, 1).getDay();
      const offset    = firstDay === 0 ? 6 : firstDay - 1;
      const daysInMon = new Date(calYear, calMonth + 1, 0).getDate();
      let html = '';
      for (let i = 0; i < offset; i++) html += '<div></div>';
      for (let d = 1; d <= daysInMon; d++) {
        const iso  = `${calYear}-${String(calMonth+1).padStart(2,'0')}-${String(d).padStart(2,'0')}`;
        const past = iso < todayISO;
        const sel  = iso === selectedDate;
        html += `<button type="button" onclick="selectCalDay('${iso}')" ${past ? 'disabled' : ''}
          class="aspect-square rounded-full text-xs font-semibold transition
          ${sel ? 'bg-blue-700 text-white' : past ? 'text-slate-300 cursor-not-allowed' : 'hover:bg-blue-100 text-slate-900'}">
          ${d}
        </button>`;
      }
      grid.innerHTML = html;
    }

    function selectCalDay(iso) {
      dateTouched = true;
      selectedDate = iso;
      renderCal();
      refreshTimePicker(selectedDate);
      updateSummary();
      revealSection(document.getElementById('timeSection'));
    }

    document.getElementById('calPrev').onclick = () => {
      calMonth--; if (calMonth < 0) { calMonth = 11; calYear--; } renderCal();
    };
    document.getElementById('calNext').onclick = () => {
      calMonth++; if (calMonth > 11) { calMonth = 0; calYear++; } renderCal();
    };
    renderCal();

    // ── Time ──────────────────────────────────────────────────────────────────
    function refreshTimePicker(date) {
      const isToday = date === todayISO;
      const minH24  = isToday ? minH24Today : 0;
      const amAvail = !isToday || minH24 < 12;

      // If AM is unavailable and currently selected, switch to PM
      if (!amAvail && selectedAmpm === 'AM') selectedAmpm = 'PM';

      const btnAM = document.getElementById('btnAM');
      const btnPM = document.getElementById('btnPM');
      btnAM.disabled = !amAvail;
      btnAM.className = 'ampm-btn px-5 py-3 text-sm font-bold transition ' +
        (!amAvail
          ? 'opacity-30 cursor-not-allowed text-slate-400'
          : selectedAmpm === 'AM' ? 'bg-blue-700 text-white' : 'text-slate-500 hover:bg-slate-50');
      btnPM.disabled = false;
      btnPM.className = 'ampm-btn px-5 py-3 text-sm font-bold transition ' +
        (selectedAmpm === 'PM' ? 'bg-blue-700 text-white' : 'text-slate-500 hover:bg-slate-50');

      // Rebuild hour dropdown with only available hours
      const sel   = document.getElementById('hourSel');
      const prevH = parseInt(sel.value || '0', 10);
      let html = '', firstAvail = null;
      for (let h = 1; h <= 12; h++) {
        const h24 = to24(h, selectedAmpm);
        if (h24 < minH24) continue;
        if (firstAvail === null) firstAvail = h;
        html += `<option value="${h}">${String(h).padStart(2,'0')}:00</option>`;
      }
      sel.innerHTML = html;
      // Keep previous selection if still valid, else pick first available
      const stillValid = [...sel.options].some(o => parseInt(o.value) === prevH);
      sel.value = stillValid ? prevH : (firstAvail ?? 1);

      internalBuild = true;
      buildTime();
      internalBuild = false;
    }

    function setAmpm(p) {
      if (document.getElementById('btn' + p).disabled) return;
      timeTouched = true;
      selectedAmpm = p;
      refreshTimePicker(selectedDate);
      updateSummary();
    }

    function buildTime() {
      if (!internalBuild) timeTouched = true;
      const customWrap = document.getElementById('customTimeWrap');
      if (customWrap.classList.contains('hidden') &&
          document.getElementById('periodSel').value === 'custom') {
        customWrap.classList.remove('hidden');
        customWrap.classList.remove('fluid-pop');
        void customWrap.offsetWidth;
        customWrap.classList.add('fluid-pop');
      }
      const h   = parseInt(document.getElementById('hourSel').value, 10);
      const h24 = to24(h, selectedAmpm);
      hidTime.value = String(h24).padStart(2,'0') + ':00';
      document.getElementById('timeDisplay').textContent =
        'Seleccionado: ' + String(h).padStart(2,'0') + ':00 ' + selectedAmpm;
      updateSummary();
    }

    function onPeriodChange() {
      const period = document.getElementById('periodSel').value;
      if (period) timeTouched = true;
      const customWrap = document.getElementById('customTimeWrap');
      const display = document.getElementById('timeDisplay');
      const loc = document.getElementById('locSection');

      if (!period) {
        hidTime.value = '';
        hideSection(customWrap);
        display.textContent = '';
        hideSection(loc);
        updateSummary();
        return;
      }

      if (period === 'custom') {
        customWrap.classList.remove('hidden');
        customWrap.classList.remove('fluid-pop');
        void customWrap.offsetWidth;
        customWrap.classList.add('fluid-pop');
        refreshTimePicker(selectedDate);
        if (selectedDate) {
          revealSection(loc);
        }
        setTimeout(() => {
          customWrap.scrollIntoView({ behavior: 'smooth', block: 'center' });
        }, 200);
        return;
      }

      // Predefined periods → default representative time
      const presets = {
        morning:   { h: 9,  ampm: 'AM', label: 'Mañana (8:00 AM – 12:00 PM)' },
        afternoon: { h: 2,  ampm: 'PM', label: 'Tarde (1:00 PM – 5:00 PM)' },
        evening:   { h: 7,  ampm: 'PM', label: 'Por las noches' },
      };
      const t = presets[period];
      selectedAmpm = t.ampm;
      const h24 = to24(t.h, t.ampm);

      // For "today", ensure default isn't before the earliest bookable hour
      const isToday  = selectedDate === todayISO;
      const minH24   = isToday ? minH24Today : 0;
      let finalH = h24;
      if (isToday && h24 < minH24) finalH = Math.min(minH24, 23);

      hidTime.value = String(finalH).padStart(2,'0') + ':00';
      const final12 = from24(finalH);
      display.textContent = 'Seleccionado: ' + t.label + ' — hora tentativa ' +
        String(final12.h).padStart(2,'0') + ':00 ' + final12.ampm;
      hideSection(customWrap);

      if (selectedDate) {
        revealSection(loc);
      }
      updateSummary();
    }

    refreshTimePicker(selectedDate);

    // ── Location preference ───────────────────────────────────────────────────
    document.querySelectorAll('.place-opt input').forEach(r => {
      r.addEventListener('change', () => {
        const card = r.closest('.place-opt');
        if (card.dataset.key === 'home') {
          document.getElementById('homeModal').classList.remove('hidden');
          if (window.lucide) lucide.createIcons();
        }
        document.querySelectorAll('.place-opt').forEach(c => {
          c.classList.remove('border-blue-600','bg-blue-50');
          c.querySelector('.place-icon').classList.remove('text-blue-700');
          c.querySelector('.place-icon').classList.add('text-slate-500');
          c.querySelector('.place-label').classList.remove('text-blue-700');
          c.querySelector('.place-label').classList.add('text-slate-700');
        });
        card.classList.add('border-blue-600','bg-blue-50');
        card.querySelector('.place-icon').classList.remove('text-slate-500');
        card.querySelector('.place-icon').classList.add('text-blue-700');
        card.querySelector('.place-label').classList.remove('text-slate-700');
        card.querySelector('.place-label').classList.add('text-blue-700');
        hidDom.value = card.dataset.val;
        placeTouched = true;
        updateSummary();
      });
    });

    function confirmHome() { document.getElementById('homeModal').classList.add('hidden'); }
    function cancelHome()  {
      document.getElementById('homeModal').classList.add('hidden');
      // Switch back to notary office
      document.querySelector('.place-opt[data-key="notary"] input').checked = true;
      document.querySelector('.place-opt[data-key="notary"] input').dispatchEvent(new Event('change'));
    }

    // ── Summary & validation ──────────────────────────────────────────────────
    function updateSummary() {
      hidDate.value = selectedDate;
      // Enable continue button ONLY when user has actively selected:
      // 1. fecha (dateTouched), 2. disponibilidad/hora (timeTouched), 3. lugar (placeTouched)
      const ready = !!(dateTouched && timeTouched && placeTouched);
      btn.disabled = !ready;
      const hint = document.getElementById('missingHint');
      if (hint) hint.classList.toggle('hidden', ready);
    }

    updateSummary();

    // Fallback: populate hidden fields on submit in case JS init was partial
    document.querySelector('form').addEventListener('submit', () => {
      if (!hidDate.value) hidDate.value = selectedDate || todayISO;
      if (!hidTime.value) buildTime();
    });

  </script>

  <!-- Home visit fee modal -->
  <div id="homeModal" class="hidden fixed inset-0 z-50 flex items-end justify-center bg-black/50 px-4 pb-6"
       onclick="if(event.target===this) cancelHome()">
    <div class="bg-white rounded-2xl w-full max-w-sm p-6 shadow-2xl">
      <div class="flex flex-col items-center text-center mb-5">
        <i data-lucide="home" class="w-12 h-12 text-blue-700 mb-3"></i>
        <h3 class="text-lg font-extrabold text-blue-500 mb-2">Visita a Domicilio Seleccionada</h3>
        <p class="text-sm text-slate-600 leading-relaxed">
          Se agregará un cargo por visita a domicilio de <strong class="text-blue-700">$80.00 USD</strong>
          a la tarifa de consulta del notario al finalizar.
        </p>
      </div>
      <div class="bg-blue-50 border border-blue-100 rounded-xl px-4 py-3 mb-5 flex justify-between items-center text-sm">
        <span class="text-slate-600 flex items-center gap-2">
          <i data-lucide="circle-dollar-sign" class="w-4 h-4 text-slate-500"></i>
          Cargo por visita a domicilio
        </span>
        <span class="font-extrabold text-blue-700">+ $80.00 USD</span>
      </div>
      <div class="flex gap-3">
        <button onclick="cancelHome()"
          class="flex-1 border-[1.5px] border-slate-300 text-slate-600 font-semibold rounded-xl py-3 hover:bg-slate-50 active:scale-95 transition text-sm">
          Cancelar
        </button>
        <button onclick="confirmHome()"
          class="flex-1 bg-blue-700 hover:bg-blue-800 active:scale-95 text-white font-bold rounded-xl py-3 shadow-lg shadow-blue-700/30 transition text-sm">
          Entendido, continuar
        </button>
      </div>
    </div>
  </div>

  <?php include '../_nav.php'; ?>
</div>
</body>
</html>
