<?php
/**
 * MSHW-proxy - Entry Point
 * Clean, ngrok-ready, ephemeral-optimized
 */

declare(strict_types=1);

// Bootstrap
require_once __DIR__ . '/../vendor/autoload.php';

// Load environment config
if (file_exists(__DIR__ . '/../.env')) {
    $dotenv = Dotenv\Dotenv::createImmutable(__DIR__ . '/..');
    $dotenv->load();
}

// Simple config() helper for ephemeral use
if (!function_exists('config')) {
    function config(string $key, mixed $default = null): mixed
    {
        static $cache = null;
        if ($cache === null) {
            $files = glob(__DIR__ . '/../config/*.php');
            foreach ($files as $file) {
                $name = pathinfo($file, PATHINFO_FILENAME);
                $cache[$name] = require $file;
            }
        }
        $parts = explode('.', $key, 2);
        $file = $parts[0] ?? null;
        $subkey = $parts[1] ?? null;
        if ($subkey === null) {
            return $cache[$file] ?? $default;
        }
        $subParts = explode('.', $subkey);
        $value = $cache[$file] ?? null;
        foreach ($subParts as $part) {
            if (is_array($value) && array_key_exists($part, $value)) {
                $value = $value[$part];
            } else {
                return $default;
            }
        }
        return $value;
    }
}

// Security headers (apply to all responses)
foreach (config('security', []) as $header => $value) {
    header("$header: $value");
}
header('X-Proxied-By: MSHW-proxy/1.0');

// Simple router (PSR-15 style, no framework bloat)
$uri = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH);
$method = $_SERVER['REQUEST_METHOD'];

// Dashboard route
if (str_starts_with($uri, config('dashboard.path', '/dashboard'))) {
    require __DIR__ . '/../app/Http/Controllers/DashboardController.php';
    (new MSHW\Proxy\Http\Controllers\DashboardController())->handle($method, $uri);
    exit;
}

// Proxy route: ?q=<encoded_url>
if (isset($_GET['q']) || isset($_POST['q'])) {
    require __DIR__ . '/../app/Http/Controllers/ProxyController.php';
    (new MSHW\Proxy\Http\Controllers\ProxyController())->handle($method, $uri);
    exit;
}

// Default: show minimal info or redirect to dashboard
if (config('dashboard.enabled', true)) {
    header('Location: ' . config('dashboard.path', '/dashboard'));
    exit;
}

http_response_code(404);
echo '<!DOCTYPE html><title>MSHW-proxy</title><h1>404</h1><p>Endpoint not found.</p>';
