<?php

declare(strict_types=1);

/**
 * Single-port server for the whole app, with no Apache involved.
 *
 *     php deploy/serve.php                 # http://localhost:8080
 *     php deploy/serve.php 0.0.0.0 80      # reachable from the network
 *
 * It serves the built frontend (frontend/dist) and the JSON API from ONE
 * origin, exactly as the Apache vhost does — handy for trying the real
 * layout, or for a small office that would rather not configure Apache.
 *
 * It runs on PHP's built-in web server, which handles ONE request at a time.
 * That is fine for a few users; for a whole department use the Apache vhost
 * in deploy/apache/7civil.conf instead.
 */

$root = dirname(__DIR__);
$dist = $root . '/frontend/dist';

// Launcher mode: re-exec PHP's built-in server with this file as the router.
if (PHP_SAPI === 'cli') {
    if (!is_file($dist . '/index.html')) {
        fwrite(STDERR, "✗ فرانت‌اند ساخته نشده است. اول در پوشهٔ frontend دستور «npm run build» را اجرا کنید.\n");
        exit(1);
    }
    $host = $argv[1] ?? '127.0.0.1';
    $port = (int) ($argv[2] ?? 8080);

    fwrite(STDOUT, "سامانه روی http://{$host}:{$port} بالا آمد.\n");
    if ($host === '0.0.0.0') {
        fwrite(STDOUT, "از سیستم‌های دیگر با آی‌پیِ همین سرور باز کنید (و پورت {$port} را در فایروال باز بگذارید).\n");
    }
    $cmd = sprintf(
        '%s -S %s:%d -t %s %s',
        escapeshellarg(PHP_BINARY),
        $host,
        $port,
        escapeshellarg($dist),
        escapeshellarg(__FILE__),
    );
    passthru($cmd, $code);
    exit($code);
}

// ── Router mode: called by the built-in server for every request ──────────
$path = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';

// The API keeps its full /api/... path, because that is what the routes match.
if ($path === '/api' || str_starts_with($path, '/api/')) {
    require $root . '/backend/public/index.php';

    return true;
}

// Uploaded photos, signatures and logos live under the backend's public dir.
if (str_starts_with($path, '/uploads/')) {
    $file = $root . '/backend/public' . $path;
    $real = realpath($file);
    $base = realpath($root . '/backend/public/uploads');
    // realpath + prefix check stops ../ in the URL from reaching other files.
    if ($real !== false && $base !== false && str_starts_with($real, $base) && is_file($real)) {
        $mime = match (strtolower(pathinfo($real, PATHINFO_EXTENSION))) {
            'png' => 'image/png',
            'jpg', 'jpeg' => 'image/jpeg',
            'webp' => 'image/webp',
            'gif' => 'image/gif',
            default => 'application/octet-stream',
        };
        header('Content-Type: ' . $mime);
        header('X-Content-Type-Options: nosniff');
        readfile($real);

        return true;
    }
    http_response_code(404);

    return true;
}

// A real file in dist/ — let the built-in server send it as-is.
if ($path !== '/' && is_file($dist . $path)) {
    return false;
}

// Anything else is an SPA route (/letters/12, /settings, …) → index.html.
header('Content-Type: text/html; charset=utf-8');
header('Cache-Control: no-cache, must-revalidate');
readfile($dist . '/index.html');

return true;
