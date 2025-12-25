<?php
// Error Handler and Logger

// Create logs directory if it doesn't exist
$logs_dir = __DIR__ . '/../logs';
if (!file_exists($logs_dir)) {
    mkdir($logs_dir, 0755, true);
}

// Custom error logging function
function logError($error_type, $message, $context = []) {
    $logs_dir = __DIR__ . '/../logs';
    $log_file = $logs_dir . '/error_' . date('Y-m-d') . '.log';

    $log_entry = [
        'timestamp' => date('Y-m-d H:i:s'),
        'type' => $error_type,
        'message' => $message,
        'context' => $context,
        'ip' => $_SERVER['REMOTE_ADDR'] ?? 'unknown',
        'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? 'unknown'
    ];

    $log_line = json_encode($log_entry, JSON_UNESCAPED_UNICODE) . PHP_EOL;
    file_put_contents($log_file, $log_line, FILE_APPEND);

    // Keep only last 30 days of logs
    cleanOldLogs($logs_dir, 30);
}

// Clean old log files
function cleanOldLogs($logs_dir, $days_to_keep = 30) {
    $log_files = glob($logs_dir . '/error_*.log');
    $cutoff_time = time() - ($days_to_keep * 24 * 60 * 60);

    foreach ($log_files as $file) {
        if (filemtime($file) < $cutoff_time) {
            unlink($file);
        }
    }
}

// Database error handler
function handleDatabaseError($e, $user_message = "Verilənlər bazası xətası baş verdi") {
    logError('DATABASE', $e->getMessage(), [
        'file' => $e->getFile(),
        'line' => $e->getLine(),
        'trace' => $e->getTraceAsString()
    ]);

    return $user_message;
}

// File system error handler
function handleFileSystemError($error_message, $context = []) {
    logError('FILESYSTEM', $error_message, $context);
    return "Fayl əməliyyatı xətası baş verdi";
}

// General error handler
function handleGeneralError($error_message, $context = []) {
    logError('GENERAL', $error_message, $context);
    return "Xəta baş verdi. Zəhmət olmasa yenidən cəhd edin";
}

// Set custom error handler for PHP errors
set_error_handler(function($errno, $errstr, $errfile, $errline) {
    // Don't log suppressed errors
    if (!(error_reporting() & $errno)) {
        return false;
    }

    $error_types = [
        E_ERROR => 'ERROR',
        E_WARNING => 'WARNING',
        E_NOTICE => 'NOTICE',
        E_USER_ERROR => 'USER_ERROR',
        E_USER_WARNING => 'USER_WARNING',
        E_USER_NOTICE => 'USER_NOTICE'
    ];

    $type = $error_types[$errno] ?? 'UNKNOWN';

    logError($type, $errstr, [
        'file' => $errfile,
        'line' => $errline
    ]);

    return true;
});

// Set custom exception handler
set_exception_handler(function($exception) {
    logError('EXCEPTION', $exception->getMessage(), [
        'file' => $exception->getFile(),
        'line' => $exception->getLine(),
        'trace' => $exception->getTraceAsString()
    ]);

    // Display user-friendly error page
    http_response_code(500);
    echo "<!DOCTYPE html>
<html>
<head>
    <meta charset='UTF-8'>
    <title>Xəta</title>
    <style>
        body { font-family: Arial, sans-serif; background: #f4f4f4; padding: 50px; }
        .error-container { background: white; padding: 30px; border-radius: 8px; max-width: 600px; margin: 0 auto; text-align: center; }
        h1 { color: #c62828; }
        p { color: #666; }
        a { color: #4CAF50; text-decoration: none; }
    </style>
</head>
<body>
    <div class='error-container'>
        <h1>⚠️ Xəta Baş Verdi</h1>
        <p>Üzr istəyirik, xəta baş verdi. Zəhmət olmasa yenidən cəhd edin.</p>
        <p><a href='javascript:history.back()'>← Geri qayıt</a></p>
    </div>
</body>
</html>";
    exit();
});
?>
