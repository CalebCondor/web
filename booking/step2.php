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
            'date'      => $date,
            'time'      => $time,
            'domicilio' => $domicilio,
        ]);
        header('Location: step3.php'); exit;
    }
    $error = 'Please select a valid date and time.';
}

$booking = $_SESSION['booking'];
$todayY  = (int)date('Y');
$todayM  = (int)date('n') - 1; // 0-based for JS
$todayD  = (int)date('j');
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <title>Notarize — Date &amp; Time</title>
  <?php include '../_head.php'; ?>
</head>
<body class="bg-white md:bg-slate-200 min-h-screen flex justify-center">
<div class="w-full md:max-w-sm min-h-screen bg-slate-50 flex flex-col md:shadow-2xl relative">

  <div class="bg-[#1e3a8a] px-5 py-4 sticky top-0 z-50 flex items-center gap-3">
    <div class="w-6"></div>
    <span class="text-white font-extrabold text-base">Date &amp; Time</span>
  </div>



  <form method="POST" class="flex-1 w-full px-6 py-6 flex flex-col gap-5">
    <input type="hidden" id="hidDate" name="date" value="" />
    <input type="hidden" id="hidTime" name="time" value="" />
    <input type="hidden" id="hidDom"  name="domicilio" value="0" />

    <!-- Context chip -->
    <div class="inline-flex self-start bg-blue-100 text-blue-800 text-xs font-semibold rounded-full px-3 py-1.5">
      🏛️ <?= htmlspecialchars($booking['service_name'] ?? '') ?>
    </div>

    <?php if (!empty($error)): ?>
      <div class="bg-red-50 border border-red-200 text-red-700 text-sm rounded-xl px-4 py-3">
        <?= htmlspecialchars($error) ?>
      </div>
    <?php endif; ?>

    <!-- Date selection -->
    <div>
      <p class="text-xl font-extrabold text-slate-900 mb-3">When would you like to<br>complete your service?</p>

      <div class="flex flex-col gap-2">
        <?php
          $today    = date('D, M j');
          $tomorrow = date('D, M j', strtotime('+1 day'));
          foreach ([
            ['today',    '⚡', 'Today',               $today],
            ['tomorrow', '📅', 'Tomorrow',             $tomorrow],
            ['custom',   '🗓️', 'Choose another date',  'Pick a specific date'],
          ] as [$key, $icon, $label, $sub]):
        ?>
          <label class="date-opt flex items-center gap-3 bg-white rounded-2xl p-4 border-[1.5px] border-slate-200 cursor-pointer transition" data-key="<?= $key ?>">
            <input type="radio" name="_dateOpt" value="<?= $key ?>" class="sr-only" <?= $key === 'today' ? 'checked' : '' ?> />
            <div class="opt-icon w-10 h-10 rounded-xl bg-slate-100 flex items-center justify-center text-xl shrink-0 transition"><?= $icon ?></div>
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
            <?php foreach (['Mo','Tu','We','Th','Fr','Sa','Su'] as $d): ?>
              <span class="text-[10px] font-bold text-slate-400"><?= $d ?></span>
            <?php endforeach; ?>
          </div>
          <div id="calGrid" class="grid grid-cols-7 gap-0.5 text-center"></div>
        </div>
      </div>
    </div>

    <!-- Time picker -->
    <div>
      <p class="text-sm font-bold text-slate-700 mb-2">Preferred time</p>
      <div class="flex gap-2 items-center">
        <select id="hourSel" onchange="buildTime()"
          class="flex-1 bg-white border border-slate-200 rounded-xl px-4 py-3 text-sm font-semibold text-slate-900 outline-none">
          <!-- populated by JS -->
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
      <p id="timeDisplay" class="text-xs text-slate-400 mt-1.5 text-right"></p>
    </div>

    <!-- Location preference -->
      <div>
      <p class="text-sm font-bold text-slate-700 mb-2">Where will the service take place?</p>
      <div class="flex gap-2">
      <?php foreach ([['notary','building-2','At notary office',0],['home','home','At my address',1]] as [$key,$icon,$lbl,$val]): ?>
          <label class="place-opt flex-1 flex flex-col items-center gap-1.5 bg-white rounded-2xl py-3 px-2 border-[1.5px] border-slate-200 cursor-pointer transition text-center" data-val="<?= $val ?>" data-key="<?= $key ?>">
            <input type="radio" name="_place" value="<?= $key ?>" class="sr-only" <?= $key === 'notary' ? 'checked' : '' ?> />
            <i data-lucide="<?= $icon ?>" class="place-icon w-6 h-6 text-slate-500 transition"></i>
            <span class="place-label text-xs font-semibold text-slate-700 transition"><?= $lbl ?></span>
          </label>
        <?php endforeach; ?>
      </div>
    </div>

    <!-- Summary chip -->
    <div id="summaryChip" class="hidden bg-blue-50 border border-blue-200 rounded-xl px-4 py-3 text-sm text-blue-800 font-semibold text-center"></div>

    <button type="submit" id="continueBtn"
      class="w-full mt-auto bg-blue-700 hover:bg-blue-800 active:scale-95 text-white font-extrabold
             rounded-2xl py-4 shadow-lg shadow-blue-700/30 transition">
      Continue →
    </button>
  </form>

  <script>
    const todayISO   = new Date().toISOString().slice(0, 10);
    const MONTHS     = ['January','February','March','April','May','June','July','August','September','October','November','December'];
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

    let selectedDate = todayISO;
    let calYear  = <?= $todayY ?>;
    let calMonth = <?= $todayM ?>;

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

        const opt = r.value;
        document.getElementById('calendarWrap').classList.toggle('hidden', opt !== 'custom');
        if (opt !== 'custom') {
          selectedDate = getDateForOpt(opt);
          refreshTimePicker(selectedDate);
          updateSummary();
        } else {
          selectedDate = '';
          refreshTimePicker('');
          updateSummary();
        }
      });
      if (r.checked) r.dispatchEvent(new Event('change'));
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
      selectedDate = iso;
      renderCal();
      refreshTimePicker(selectedDate);
      updateSummary();
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

      buildTime();
    }

    function setAmpm(p) {
      if (document.getElementById('btn' + p).disabled) return;
      selectedAmpm = p;
      refreshTimePicker(selectedDate);
      updateSummary();
    }

    function buildTime() {
      const h   = parseInt(document.getElementById('hourSel').value, 10);
      const h24 = to24(h, selectedAmpm);
      hidTime.value = String(h24).padStart(2,'0') + ':00';
      document.getElementById('timeDisplay').textContent =
        'Selected: ' + String(h).padStart(2,'0') + ':00 ' + selectedAmpm;
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
        updateSummary();
      });
      if (r.checked) r.dispatchEvent(new Event('change'));
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
      if (selectedDate && hidTime.value) {
        const h = document.getElementById('hourSel').value;
        summary.innerHTML = `<i data-lucide="calendar" class="inline w-4 h-4 -mt-0.5"></i> ${selectedDate}  <i data-lucide="clock" class="inline w-4 h-4 -mt-0.5"></i> ${String(h).padStart(2,'0')}:00 ${selectedAmpm}`;
        if (window.lucide) lucide.createIcons();
        summary.classList.remove('hidden');
      } else {
        summary.classList.add('hidden');
      }
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
        <h3 class="text-lg font-extrabold text-blue-500 mb-2">Home Visit Selected</h3>
        <p class="text-sm text-slate-600 leading-relaxed">
          A home visit fee of <strong class="text-blue-700">$80.00 USD</strong> will be added
          to the notary's consultation rate at checkout.
        </p>
      </div>
      <div class="bg-blue-50 border border-blue-100 rounded-xl px-4 py-3 mb-5 flex justify-between items-center text-sm">
        <span class="text-slate-600 flex items-center gap-2">
          <i data-lucide="circle-dollar-sign" class="w-4 h-4 text-slate-500"></i>
          Home visit surcharge
        </span>
        <span class="font-extrabold text-blue-700">+ $80.00 USD</span>
      </div>
      <div class="flex gap-3">
        <button onclick="cancelHome()"
          class="flex-1 border-[1.5px] border-slate-300 text-slate-600 font-semibold rounded-xl py-3 hover:bg-slate-50 active:scale-95 transition text-sm">
          Cancel
        </button>
        <button onclick="confirmHome()"
          class="flex-1 bg-blue-700 hover:bg-blue-800 active:scale-95 text-white font-bold rounded-xl py-3 shadow-lg shadow-blue-700/30 transition text-sm">
          Got it, continue
        </button>
      </div>
    </div>
  </div>

  <?php include '../_nav.php'; ?>
</div>
</body>
</html>
