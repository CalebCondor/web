<?php
const API_BASE  = 'https://islandmedpr.com/notarize/api/v1';
const APP_BASE  = 'https://islandmedpr.com/notarize/web';

function session_init(): void {
    if (session_status() === PHP_SESSION_NONE) session_start();
}

function require_auth(): void {
    session_init();
    if (empty($_SESSION['client_token'])) { header('Location: auth.php'); exit; }
}

function api_json(string $method, string $path, array $body = [], string $token = ''): array {
    $ch = curl_init(API_BASE . $path);
    $h  = ['Content-Type: application/json'];
    if ($token) $h[] = "Authorization: Bearer $token";
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_CUSTOMREQUEST  => strtoupper($method),
        CURLOPT_HTTPHEADER     => $h,
        CURLOPT_TIMEOUT        => 15,
        CURLOPT_SSL_VERIFYPEER => true,
        CURLOPT_POSTFIELDS     => $body ? json_encode($body) : null,
    ]);
    $raw = curl_exec($ch);
    curl_close($ch);
    return json_decode($raw, true) ?? [];
}

function flash(?string $key = null, ?string $msg = null): ?string {
    session_init();
    if ($msg !== null) { $_SESSION["fl_$key"] = $msg; return null; }
    $v = $_SESSION["fl_$key"] ?? null;
    unset($_SESSION["fl_$key"]);
    return $v;
}

function booking_progress(int $current): void {
    $steps = ['Service', 'Date & Time', 'Notary', 'Route'];
    echo '<div class="w-full bg-[#1e3a8a] px-5 pb-4 pt-1">';
    echo '<div class="flex items-center gap-1 max-w-sm mx-auto">';
    foreach ($steps as $i => $label) {
        $n    = $i + 1;
        $done = $n < $current;
        $cur  = $n === $current;
        $dot  = $done ? 'bg-green-400' : ($cur ? 'bg-white' : 'bg-white/30');
        $txt  = $done ? 'text-green-400' : ($cur ? 'text-white font-bold' : 'text-white/40');
        echo "<div class='flex flex-col items-center gap-1 flex-1'>";
        echo "<div class='w-5 h-5 rounded-full $dot flex items-center justify-center text-[9px] font-bold text-[#1e3a8a]'>" . ($done ? '✓' : $n) . "</div>";
        echo "<span class='text-[9px] $txt text-center leading-tight'>$label</span>";
        echo "</div>";
        if ($n < count($steps)) echo "<div class='h-px flex-1 mb-3 " . ($done ? 'bg-green-400' : 'bg-white/20') . "'></div>";
    }
    echo '</div></div>';
}

function step_progress(int $current): void {
    $steps = ['Upload', 'Account', 'Payment', 'Documents', 'Send'];
    echo '<div class="w-full bg-[#1e3a8a] px-5 pb-4 pt-1">';
    echo '<div class="flex items-center gap-1 max-w-sm mx-auto">';
    foreach ($steps as $i => $label) {
        $n    = $i + 1;
        $done = $n < $current;
        $cur  = $n === $current;
        $dot  = $done ? 'bg-green-400' : ($cur ? 'bg-white' : 'bg-white/30');
        $txt  = $done ? 'text-green-400' : ($cur ? 'text-white font-bold' : 'text-white/40');
        echo "<div class='flex flex-col items-center gap-1 flex-1'>";
        echo "<div class='w-5 h-5 rounded-full $dot flex items-center justify-center text-[9px] font-bold text-[#1e3a8a]'>" . ($done ? '✓' : $n) . "</div>";
        echo "<span class='text-[9px] $txt text-center leading-tight'>$label</span>";
        echo "</div>";
        if ($n < count($steps)) echo "<div class='h-px flex-1 mb-3 " . ($done ? 'bg-green-400' : 'bg-white/20') . "'></div>";
    }
    echo '</div></div>';
}
