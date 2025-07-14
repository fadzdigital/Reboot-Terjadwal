<?php
// data/adb/php7/files/www/tools/reboot_schedule_checker.php
date_default_timezone_set('Asia/Jakarta');
$schedule_file = __DIR__ . '/schedule_reboot.json';
$log_file = '/data/adb/schedule_reboot.log';
$last_file = __DIR__ . '/last_reboot.txt';

// --- FUNGSI KIRIM TELEGRAM ---
/**function kirimTelegram($pesan) {
    $bot_token = '8107356011:AAEFF5ZnTata2txInZ66DOA5TmVu4PMAvlo';
    $chat_id = '6243379861';
    $pesan = urlencode($pesan);
    $url = "https://api.telegram.org/bot$bot_token/sendMessage?chat_id=$chat_id&text=$pesan&parse_mode=Markdown";
    $res = @file_get_contents($url);
    if ($res === false) {
        // log jika gagal kirim Telegram
        global $log_file;
        file_put_contents($log_file, date('Y-m-d H:i:s') . " - [ERROR] Gagal kirim notifikasi Telegram\n", FILE_APPEND);
    }
}**/

// --- FUNGSI LOG UTAMA ---
function logStatus($msg, $level = 'INFO', $sendTelegram = false) {
    global $log_file;
    $line = date('Y-m-d H:i:s') . " - [$level] $msg\n";
    file_put_contents($log_file, $line, FILE_APPEND);
    if ($sendTelegram) {
        kirimTelegram("[$level] $msg");
    }
}

// --- CATAT EKSEKUSI ---
$user = trim(shell_exec('whoami'));
logStatus("Script running as user: $user", 'CHECK');

// --- CEK PENGATURAN SCHEDULE ---
if (!file_exists($schedule_file)) {
    logStatus("schedule_reboot.json not found!", "ERROR", true);
    exit;
}

$schedule = json_decode(file_get_contents($schedule_file), true);
if (!$schedule || $schedule['mode'] == 'none') {
    logStatus("Schedule is empty or not active.", "INFO");
    exit;
}

$now = new DateTime();
$reboot = false;
$mode = $schedule['mode'];

if ($mode == 'daily') {
    if (empty($schedule['daily_time'])) {
        logStatus("Daily mode but no time set!", "ERROR", true);
        exit;
    }
    $target_time = DateTime::createFromFormat('Y-m-d H:i', date('Y-m-d') . ' ' . $schedule['daily_time']);
    if (!$target_time) {
        logStatus("Failed to parse target time (daily).", "ERROR", true);
        exit;
    }
    if ($now->format('H:i') == $target_time->format('H:i')) {
        $reboot = true;
        $reason = '[DAILY] Exact time match for reboot';
    }
} elseif ($mode == 'interval') {
    if (empty($schedule['interval_days']) || empty($schedule['interval_time'])) {
        logStatus("Interval mode but missing settings!", "ERROR", true);
        exit;
    }
    $last_restart = $schedule['last_restart'] ?? date('Y-m-d');
    $interval_days = intval($schedule['interval_days']);
    $interval_time = $schedule['interval_time'];
    $next_restart = (new DateTime($last_restart))->modify("+$interval_days days");
    $target_time = DateTime::createFromFormat('Y-m-d H:i', $next_restart->format('Y-m-d') . ' ' . $interval_time);
    if (!$target_time) {
        logStatus("Failed to parse target time (interval).", "ERROR", true);
        exit;
    }
    if ($now->format('Y-m-d H:i') == $target_time->format('Y-m-d H:i')) {
        $reboot = true;
        $reason = '[INTERVAL] Exact time match for reboot';
    }
}

// --- PROSES REBOOT OTOMATIS ---
if ($reboot) {
    // Anti double reboot: minimal 5 menit antar reboot
    if (file_exists($last_file)) {
        $last = file_get_contents($last_file);
        if (time() - intval($last) < 300) {
            logStatus("Reboot skipped, last reboot less than 5min ago.", "INFO");
            exit;
        }
    }
    file_put_contents($last_file, time());

    // Info sebelum reboot
    logStatus("$reason", "REBOOT");
    logStatus("[REBOOT] Running: su -c reboot", "REBOOT");

    // Notifikasi Telegram sebelum reboot
    $hostname = gethostname() ?: 'CLI';
    $tanggal = date('Y-m-d H:i:s');
    $pesan = "🚨 *REBOOT DIJALANKAN!*\n"
           . "Waktu: `$tanggal`\n"
           . "Mode: *$mode*\n"
           . "User: `$user`\n"
           . "Server: `$hostname`";
    kirimTelegram($pesan);

    // Tambahkan delay biar pesan sempat terkirim
    sleep(3);

    // Eksekusi reboot dan log output/error
    $output = shell_exec('su -c reboot 2>&1');
    logStatus("[REBOOT OUTPUT]: " . trim($output), "REBOOT");

    // Update interval last_restart
    if ($mode == 'interval') {
        $schedule['last_restart'] = $now->format('Y-m-d');
        file_put_contents($schedule_file, json_encode($schedule));
    }
}
?>
