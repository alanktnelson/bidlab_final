<?php
session_set_cookie_params(['path' => '/', 'httponly' => true, 'samesite' => 'Lax', 'secure' => !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off']);
session_start();
date_default_timezone_set('UTC');

ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/../error/error.log');

require_once __DIR__ . '/db_class.php';

// Placeholders: the team has not agreed these yet (see the README).
define('CURRENCY', 'GHS');
define('MIN_BID_INCREMENT_MINOR', 500); // each new bid must beat the last by at least 5.00
define('MIN_AUCTION_MINUTES', 2);
define('MAX_AUCTION_DAYS', 30);

define('AUCTION_IMAGE_DIR', __DIR__ . '/../images/auctions/');
define('AUCTION_IMAGE_MAX_BYTES', 2 * 1024 * 1024);
define('AUCTION_MAX_PHOTOS', 4);

function url($path) {
    $root = realpath(__DIR__ . '/..');
    $script_dir = realpath(dirname($_SERVER['SCRIPT_FILENAME']));
    $relative = trim(substr($script_dir, strlen($root)), DIRECTORY_SEPARATOR);
    $depth = $relative === '' ? 0 : substr_count($relative, DIRECTORY_SEPARATOR) + 1;
    return str_repeat('../', $depth) . $path;
}

function redirect($path) {
    header('Location: ' . url($path));
    exit;
}

function flash($key) {
    $value = $_SESSION[$key] ?? null;
    unset($_SESSION[$key]);
    return $value;
}

function e($value) {
    return htmlspecialchars((string) ($value ?? ''), ENT_QUOTES, 'UTF-8');
}

function is_logged_in() {
    return isset($_SESSION['user_id']);
}

function current_user_id() {
    return is_logged_in() ? (int) $_SESSION['user_id'] : null;
}

/** This page's path from the project root, e.g. views/single_auction.php?id=3 */
function current_path() {
    $root = realpath(__DIR__ . '/..');
    $script = realpath($_SERVER['SCRIPT_FILENAME']);
    $path = ltrim(str_replace(DIRECTORY_SEPARATOR, '/', substr($script, strlen($root))), '/');
    $query = $_SERVER['QUERY_STRING'] ?? '';
    return $path . ($query === '' ? '' : '?' . $query);
}

/** Only pages inside this site are allowed, so a crafted link cannot send people elsewhere. */
function safe_return($path, $fallback = 'index.php') {
    $is_safe = is_string($path) && preg_match('#^(index\.php|views/[a-z_/]+\.php)(\?[\w=&%.+-]*)?$#', $path);
    return $is_safe ? $path : $fallback;
}

function require_login() {
    if (!is_logged_in()) {
        $_SESSION['errors'] = ['general' => 'Please log in first.'];
        redirect('views/login.php?return=' . urlencode(current_path()));
    }
}

function csrf_token() {
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function csrf_field() {
    return '<input type="hidden" name="csrf_token" value="' . e(csrf_token()) . '">';
}

/** Stops other websites from submitting our forms on a logged-in person's behalf. */
function verify_csrf() {
    $token = $_POST['csrf_token'] ?? '';
    if (!is_string($token) || !hash_equals(csrf_token(), $token)) {
        $_SESSION['error'] = 'That form has expired. Please try again.';
        redirect('index.php');
    }
}

/** Turns "120.50" into 12050. Returns null if it is not a valid amount. */
function parse_money($value) {
    if (!is_string($value) || !preg_match('/^(\d{1,9})(?:\.(\d{1,2}))?$/', trim($value), $match)) {
        return null;
    }
    return (int) $match[1] * 100 + (int) str_pad($match[2] ?? '', 2, '0');
}

function money($minor) {
    return CURRENCY . ' ' . number_format($minor / 100, 2);
}

function money_input($minor) {
    return number_format($minor / 100, 2, '.', '');
}

function plural($count, $word) {
    return $count . ' ' . $word . ($count === 1 ? '' : 's');
}

function iso_time($utc_datetime) {
    return gmdate('Y-m-d\TH:i:s\Z', strtotime($utc_datetime . ' UTC'));
}

function time_left($utc_datetime) {
    $seconds = strtotime($utc_datetime . ' UTC') - time();
    if ($seconds <= 0) {
        return 'Closed';
    }
    $days = intdiv($seconds, 86400);
    $hours = intdiv($seconds % 86400, 3600);
    $minutes = intdiv($seconds % 3600, 60);
    if ($days > 0) {
        return "{$days}d {$hours}h";
    }
    if ($hours > 0) {
        return "{$hours}h {$minutes}m";
    }
    return $minutes > 0 ? "{$minutes}m " . ($seconds % 60) . 's' : "{$seconds}s";
}

function time_ago($utc_datetime) {
    $seconds = max(0, time() - strtotime($utc_datetime . ' UTC'));
    if ($seconds < 60) {
        return 'just now';
    }
    if ($seconds < 3600) {
        return intdiv($seconds, 60) . ' min ago';
    }
    if ($seconds < 86400) {
        return plural(intdiv($seconds, 3600), 'hr') . ' ago';
    }
    return plural(intdiv($seconds, 86400), 'day') . ' ago';
}

function condition_label($value) {
    $labels = ['new' => 'New', 'like_new' => 'Like new', 'good' => 'Good', 'fair' => 'Fair', 'poor' => 'Poor'];
    return $labels[$value] ?? $value;
}

function initials($name) {
    $letters = '';
    foreach (array_slice(preg_split('/\s+/', trim($name)), 0, 2) as $part) {
        $letters .= mb_strtoupper(mb_substr($part, 0, 1));
    }
    return $letters;
}

function photo_url($filename) {
    return url('images/auctions/' . rawurlencode((string) $filename));
}

function icon($name) {
    $paths = [
        'search' => 'M21 21l-4.3-4.3M11 19a8 8 0 1 0 0-16 8 8 0 0 0 0 16z',
        'heart' => 'M20.8 5.6a5.5 5.5 0 0 0-7.8 0L12 6.7l-1-1.1a5.5 5.5 0 1 0-7.8 7.8L12 22l8.8-8.6a5.5 5.5 0 0 0 0-7.8z',
        'gavel' => 'M14 13l-7.5 7.5a2.1 2.1 0 0 1-3-3L11 10M16 16l6-6M8 8l6-6M9 7l8 8M21 11l-8-8',
        'plus' => 'M12 5v14M5 12h14',
        'back' => 'M19 12H5M12 19l-7-7 7-7',
        'pin' => 'M20 10c0 6-8 12-8 12s-8-6-8-12a8 8 0 0 1 16 0zM12 13a3 3 0 1 0 0-6 3 3 0 0 0 0 6z',
        'box' => 'M21 8l-9-5-9 5v8l9 5 9-5V8zM3 8l9 5 9-5M12 13v8',
        'lock' => 'M5 11h14v10H5zM8 11V7a4 4 0 0 1 8 0v4',
        'shield' => 'M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z',
        'store' => 'M3 9l1.5-5h15L21 9M3 9a3 3 0 0 0 6 0 3 3 0 0 0 6 0 3 3 0 0 0 6 0M5 12v8h14v-8',
        'tag' => 'M20.6 13.4l-7.2 7.2a2 2 0 0 1-2.8 0L2 12V2h10l8.6 8.6a2 2 0 0 1 0 2.8zM7 7h.01',
        'pulse' => 'M22 12h-4l-3 9L9 3l-3 9H2',
        'user' => 'M20 21a8 8 0 0 0-16 0M12 13a5 5 0 1 0 0-10 5 5 0 0 0 0 10z',
        'bag' => 'M6 7h12l1 14H5L6 7zM9 7V5a3 3 0 0 1 6 0v2',
    ];
    return '<svg class="icon" viewBox="0 0 24 24" aria-hidden="true"><path d="' . $paths[$name] . '"/></svg>';
}

/**
 * Saves one uploaded photo. The type comes from the file's contents, never from its
 * name or what the browser claims. Returns [filename, null] or [null, error message].
 */
function save_auction_image($file) {
    if ($file['error'] === UPLOAD_ERR_INI_SIZE || $file['error'] === UPLOAD_ERR_FORM_SIZE || $file['size'] > AUCTION_IMAGE_MAX_BYTES) {
        return [null, 'Each photo must be 2 MB or smaller.'];
    }
    if ($file['error'] !== UPLOAD_ERR_OK) {
        return [null, 'A photo could not be uploaded. Please try again.'];
    }

    $types = [IMAGETYPE_JPEG => 'jpg', IMAGETYPE_PNG => 'png', IMAGETYPE_WEBP => 'webp'];
    $info = @getimagesize($file['tmp_name']);
    $extension = $info === false ? null : ($types[$info[2]] ?? null);
    if ($extension === null) {
        return [null, 'Photos must be JPEG, PNG or WebP images.'];
    }

    if (!is_writable(AUCTION_IMAGE_DIR)) {
        error_log('images/auctions is not writable by the web server.');
        return [null, 'Photos cannot be saved right now. The images/auctions folder is not writable.'];
    }

    $filename = bin2hex(random_bytes(16)) . '.' . $extension;
    if (!move_uploaded_file($file['tmp_name'], AUCTION_IMAGE_DIR . $filename)) {
        return [null, 'A photo could not be saved. Please try again.'];
    }
    return [$filename, null];
}

function delete_auction_images($filenames) {
    foreach ($filenames as $filename) {
        @unlink(AUCTION_IMAGE_DIR . $filename);
    }
}
?>
