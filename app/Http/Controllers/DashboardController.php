<?php
/**
 * MSHW-proxy - Dashboard Controller
 * Single-user admin panel with cookie management & live logs
 */

declare(strict_types=1);

namespace MSHW\Proxy\Http\Controllers;

use MSHW\Proxy\Core\CookieJar;

class DashboardController
{
    private CookieJar $cookieJar;
    private string $sessionId;

    public function __construct()
    {
        $this->sessionId = $_SERVER['HTTP_X_SESSION_ID'] ?? bin2hex(random_bytes(16));
        $this->cookieJar = new CookieJar($this->sessionId);
    }

    /**
     * Route handler for /dashboard and API endpoints
     */
    public function handle(string $method, string $uri): void
    {
        // Simple auth check (password from env)
        if (!$this->checkAuth()) {
            if ($this->isApiRequest($uri)) {
                http_response_code(401);
                echo json_encode(['error' => 'Unauthorized']);
                return;
            }
            $this->renderLogin();
            return;
        }

        // API routes
        if (str_starts_with($uri, '/dashboard/api')) {
            $this->handleApi($method, $uri);
            return;
        }

        // Dashboard UI
        $this->renderDashboard();
    }

    /**
     * Check authentication via session cookie or basic auth
     */
    private function checkAuth(): bool
    {
        $expectedHash = config('dashboard.auth.password_hash');
        if (!$expectedHash) return true; // No auth configured

        // Check session cookie
        if (isset($_COOKIE['dash_auth']) && $_COOKIE['dash_auth'] === $expectedHash) {
            return true;
        }

        // Check HTTP Basic Auth
        if (isset($_SERVER['PHP_AUTH_PW'])) {
            if (password_verify($_SERVER['PHP_AUTH_PW'], $expectedHash)) {
                // Set session cookie for subsequent requests
                setcookie('dash_auth', $expectedHash, [
                    'expires' => time() + config('dashboard.auth.session_lifetime', 3600),
                    'path' => '/dashboard',
                    'secure' => true,
                    'httponly' => true,
                    'samesite' => 'Strict',
                ]);
                return true;
            }
        }

        return false;
    }

    /**
     * Render login page
     */
    private function renderLogin(): void
    {
        header('WWW-Authenticate: Basic realm="MSHW-proxy Dashboard"');
        http_response_code(401);
        ?>
        <!DOCTYPE html>
        <html>
        <head>
            <title>Login - MSHW-proxy</title>
            <meta charset="utf-8">
            <style>
                body { font-family: system-ui; display: flex; align-items: center; justify-content: center; 
                       height: 100vh; margin: 0; background: #f5f5f5; }
                .card { background: white; padding: 2rem; border-radius: 8px; box-shadow: 0 2px 10px rgba(0,0,0,0.1); }
                input { padding: 0.5rem; margin: 0.5rem 0; width: 100%; box-sizing: border-box; }
                button { padding: 0.5rem 1.5rem; background: #007bff; color: white; border: none; 
                         border-radius: 4px; cursor: pointer; }
            </style>
        </head>
        <body>
            <div class="card">
                <h2>🔐 MSHW-proxy Dashboard</h2>
                <form method="post">
                    <input type="password" name="password" placeholder="Enter password" required autofocus>
                    <button type="submit">Login</button>
                </form>
            </div>
        </body>
        </html>
        <?php
    }

    /**
     * Render main dashboard UI
     */
    private function renderDashboard(): void
    {
        $cookies = $this->cookieJar->listAll();
        ?>
        <!DOCTYPE html>
        <html>
        <head>
            <title>Dashboard - MSHW-proxy</title>
            <meta charset="utf-8">
            <meta name="viewport" content="width=device-width, initial-scale=1">
            <link rel="stylesheet" href="/assets/css/dashboard.css">
        </head>
        <body>
            <header>
                <h1>🎛️ MSHW-proxy Dashboard</h1>
                <div class="status">
                    <span class="badge">Session: <?= substr($this->sessionId, 0, 8) ?>...</span>
                    <span class="badge">Uptime: <span id="uptime">--</span></span>
                </div>
            </header>

            <main>
                <!-- Cookie Manager -->
                <section class="card">
                    <h2>🍪 Cookie Manager</h2>
                    <div class="toolbar">
                        <button id="refreshCookies">🔄 Refresh</button>
                        <button id="importCookie">📥 Import Raw</button>
                        <button id="clearAll">🗑️ Clear All</button>
                    </div>
                    <div id="cookieList" class="cookie-list">
                        <?php foreach ($cookies as $group): ?>
                            <div class="cookie-group">
                                <h3><?= htmlspecialchars($group['domain']) ?><?= htmlspecialchars($group['path']) ?></h3>
                                <?php foreach ($group['cookies'] as $name => $data): ?>
                                    <div class="cookie-item" data-domain="<?= htmlspecialchars($group['domain']) ?>" 
                                         data-path="<?= htmlspecialchars($group['path']) ?>" data-name="<?= htmlspecialchars($name) ?>">
                                        <div class="cookie-header">
                                            <strong><?= htmlspecialchars($name) ?></strong>
                                            <span class="actions">
                                                <button class="edit">✏️</button>
                                                <button class="delete">🗑️</button>
                                            </span>
                                        </div>
                                        <div class="cookie-details">
                                            <code><?= htmlspecialchars($data['value']) ?></code>
                                            <small>
                                                Expires: <?= $data['expires'] ? date('Y-m-d H:i', $data['expires']) : 'Session' ?> |
                                                Secure: <?= $data['secure'] ? '✓' : '✗' ?> |
                                                HttpOnly: <?= $data['httpOnly'] ? '✓' : '✗' ?>
                                            </small>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </section>

                <!-- Live Logs -->
                <section class="card">
                    <h2>📋 Live Logs</h2>
                    <div id="logConsole" class="log-console"></div>
                </section>

                <!-- Proxy Controls -->
                <section class="card">
                    <h2>⚙️ Quick Actions</h2>
                    <div class="actions-grid">
                        <button id="clearCache">🧹 Clear Cache</button>
                        <button id="toggleStrategy">🔄 Toggle CF Strategy</button>
                        <button id="viewStats">📊 View Stats</button>
                    </div>
                </section>
            </main>

            <!-- Modal for cookie edit/import -->
            <dialog id="cookieModal">
                <form method="dialog">
                    <h3 id="modalTitle">Edit Cookie</h3>
                    <input type="hidden" id="modalDomain">
                    <input type="hidden" id="modalPath">
                    <input type="hidden" id="modalName">
                    
                    <label>Value:
                        <textarea id="modalValue" rows="3"></textarea>
                    </label>
                    <label>Expires (timestamp):
                        <input type="number" id="modalExpires">
                    </label>
                    <label>
                        <input type="checkbox" id="modalSecure"> Secure
                    </label>
                    <label>
                        <input type="checkbox" id="modalHttpOnly"> HttpOnly
                    </label>
                    <label>SameSite:
                        <select id="modalSameSite">
                            <option value="Lax">Lax</option>
                            <option value="Strict">Strict</option>
                            <option value="None">None</option>
                        </select>
                    </label>
                    <div class="modal-actions">
                        <button value="cancel">Cancel</button>
                        <button value="save">Save</button>
                    </div>
                </form>
            </dialog>

            <script src="/assets/js/dashboard.js"></script>
        </body>
        </html>
        <?php
    }

    /**
     * Handle API requests (/dashboard/api/*)
     */
    private function handleApi(string $method, string $uri): void
    {
        header('Content-Type: application/json');
        
        try {
            $path = parse_url($uri, PHP_URL_PATH);
            
            match (true) {
                $path === '/dashboard/api/cookies' && $method === 'GET' => 
                    $this->apiListCookies(),
                $path === '/dashboard/api/cookies/import' && $method === 'POST' => 
                    $this->apiImportCookie(),
                preg_match('#^/dashboard/api/cookies/([^/]+)/([^/]+)/([^/]+)$#', $path, $m) && $method === 'PUT' => 
                    $this->apiUpdateCookie($m[1], $m[2], $m[3]),
                preg_match('#^/dashboard/api/cookies/([^/]+)/([^/]+)/([^/]+)$#', $path, $m) && $method === 'DELETE' => 
                    $this->apiDeleteCookie($m[1], $m[2], $m[3]),
                $path === '/dashboard/api/logs' && $method === 'GET' => 
                    $this->apiStreamLogs(),
                default => throw new \Exception('Not Found', 404),
            };
        } catch (\Throwable $e) {
            http_response_code($e->getCode() ?: 500);
            echo json_encode(['error' => $e->getMessage()]);
        }
    }

    private function apiListCookies(): void
    {
        echo json_encode(['cookies' => $this->cookieJar->listAll()]);
    }

    private function apiImportCookie(): void
    {
        $input = json_decode(file_get_contents('php://input'), true);
        $rawHeader = $input['raw'] ?? '';
        $domain = $input['domain'] ?? $_SERVER['HTTP_HOST'];
        $path = $input['path'] ?? '/';
        
        $this->cookieJar->importRaw($rawHeader, $domain, $path);
        echo json_encode(['success' => true]);
    }

    private function apiUpdateCookie(string $domain, string $path, string $name): void
    {
        $updates = json_decode(file_get_contents('php://input'), true);
        $success = $this->cookieJar->update($domain, $path, $name, $updates);
        echo json_encode(['success' => $success]);
    }

    private function apiDeleteCookie(string $domain, string $path, string $name): void
    {
        $success = $this->cookieJar->delete($domain, $path, $name);
        echo json_encode(['success' => $success]);
    }

    private function apiStreamLogs(): void
    {
        // WebSocket/SSE endpoint placeholder
        // In production: use Ratchet or Swoole for real WS
        header('Content-Type: text/event-stream');
        header('Cache-Control: no-cache');
        echo "data: " . json_encode(['type' => 'heartbeat', 'time' => time()]) . "\n\n";
        flush();
    }

    private function isApiRequest(string $uri): bool
    {
        return str_starts_with($uri, '/dashboard/api');
    }
}
