# MSHW-proxy (Modern Shadow PHProxy)

> **A lightweight, ephemeral, and Cloudflare-aware PHP proxy designed for GitHub Actions + ngrok deployment.**

[![PHP 8.2+](https://img.shields.io/badge/PHP-8.2+-777BB4?logo=php)](https://php.net)
[![License: MIT](https://img.shields.io/badge/License-MIT-blue.svg)](LICENSE)
[![Deploy with GitHub Actions](https://img.shields.io/badge/Deploy-GitHub_Actions-2088FF?logo=githubactions)](.github/workflows/deploy.yml)

## 🚀 Overview

**MSHW-proxy** is a modern rewrite of legacy PHP proxy scripts (like PHProxy/Glype), engineered for **personal, ephemeral use** on GitHub Actions runners. It combines:

- ✅ **RFC 6265-compliant Cookie Management** — observable & editable via dashboard
- ✅ **Cloudflare bypass engine** — header spoofing + cookie injection + manual fallback
- ✅ **Lightweight SPA Dashboard** — real-time logs, cookie editor, TTY shell access
- ✅ **GitHub Actions + ngrok deployment** — zero VPS, 6-hour ephemeral sessions
- ✅ **Streaming responses** — memory-efficient for medium-traffic personal use

> ⚠️ **Not for production hosting**. Designed for temporary, personal proxy sessions with full control.

## ✨ Key Features

### 🔐 Advanced Cookie Management
- Server-side `CookieJar` (in-memory, thread-safe)
- Full RFC 6265 compliance — no regex hacks
- Dashboard API to **view, edit, import, or delete** cookies in real-time
- Strict domain/path/secure matching — zero leakage

### 🛡️ Cloudflare Bypass Engine
- **Tier 1**: Header & TLS fingerprint spoofing (`Sec-Ch-Ua`, `Priority`, Chrome 124 profile)
- **Tier 2**: Automatic `cf_clearance` injection + exponential backoff retry
- **Tier 3**: Manual fallback — dashboard displays challenge page; user solves → cookie auto-saved

### 🎛️ Single-User Dashboard
- Live request/error logs (WebSocket-streamed)
- Cookie manager with validation layer
- Proxy controls: clear cache, toggle strategies, view active sessions
- **Web TTY shell** (`ttyd`) — full bash access via browser (password-protected)

### 🌐 Ephemeral-First Architecture
- Zero external dependencies (no Redis/MySQL)
- All state in RAM (`APCu`/`ArrayAdapter`) — auto-purged after job ends
- Streaming HTTP responses — no full-body buffering
- Optimized for GitHub Actions runners (2 vCPU, ~7GB RAM, 6h limit)

## 🗂️ Project Structure

```
MSHW-proxy/
├── .github/workflows/deploy.yml   # CI/CD: build → start proxy + ttyd + ngrok
├── app/Core/                      # Core logic: CookieJar, ProxyEngine, CfSolver
├── app/Http/                      # PSR-15 style: Router, Middleware, Controllers
├── config/                        # Proxy, Cloudflare, Dashboard settings
├── public/                        # Entry point + dashboard assets
├── resources/views/               # Blade templates for dashboard
├── storage/                       # Runtime cache/sessions (in tmpfs)
├── composer.json                  # Lightweight deps: symfony/http-client, guzzle/psr7, etc.
├── ngrok.yml                      # Dual-tunnel config (proxy:8080, tty:7681)
├── start.sh                       # Bootstrap script for Actions runner
└── README.md                      # You are here
```

## ⚙️ Quick Start (GitHub Actions + ngrok)

### 1. Prepare Secrets (GitHub Repo → Settings → Secrets and variables → Actions)
| Secret | Description |
|--------|-------------|
| `NGROK_AUTH_TOKEN` | Your ngrok auth token (required for static hostname) |
| `NGROK_HOSTNAME` | Your reserved ngrok hostname (e.g., `myproxy.ngrok.dev`) |
| `DASHBOARD_PASS` | Password for dashboard & TTY access (bcrypt-hashed recommended) |
| `CF_STRATEGY` | (Optional) `aggressive` / `balanced` / `manual` |

### 2. Trigger Deployment
- Push to `main` branch, or use **Actions → "Deploy Proxy" → Run workflow**
- Wait ~60 seconds for runner to start, install deps, and establish tunnels

### 3. Access Your Proxy
- **Proxy Endpoint**: `https://<NGROK_HOSTNAME>/`
- **Dashboard**: `https://<NGROK_HOSTNAME>/dashboard` (login with `DASHBOARD_PASS`)
- **Web TTY**: `https://<NGROK_HOSTNAME>:7681` (same credentials)

> 🔗 The same `NGROK_HOSTNAME` is reused across runs (requires ngrok Pro/Enterprise). For free tier, check workflow logs for the new URL each time.

## 🍪 Cookie Management via Dashboard

1. Open `/dashboard` → **Cookies** tab
2. View cookies grouped by domain
3. Edit any field: `value`, `expires`, `secure`, `httpOnly`, `sameSite`
4. Import raw `Set-Cookie` header (auto-parsed & validated)
5. Changes apply **instantly** to subsequent proxied requests

## 🛠️ Development

```bash
# Clone & install
git clone https://github.com/dr4cary5/mrdp1/MSHW-proxy
cd MSHW-proxy
composer install

# Local testing (optional ngrok)
php -S localhost:8080 -t public/
# Access: http://localhost:8080/?q=<base64_url>
```

### Core Dependencies
- `symfony/http-client` — Async HTTP/2 client with TLS 1.3
- `guzzlehttp/psr7` — PSR-7 message utilities (UriResolver, Stream)
- `masterminds/html5` — Standards-compliant HTML5 parser
- `symfony/dom-crawler` + `css-selector` — Safe DOM manipulation
- `vlucas/phpdotenv` — Environment config loading

## ⚠️ Limitations & Best Practices

| Limitation | Mitigation |
|------------|------------|
| **6-hour max runtime** | Design for stateless sessions; auto-restart workflow via API if needed |
| **GitHub IP ranges** | Use header/TLS spoofing; manual cookie fallback for strict CF challenges |
| **No persistent storage** | All data in RAM; export critical cookies via dashboard before session ends |
| **Medium traffic only** | Streaming responses + request concurrency limit (default: 5) |

## 🔒 Security Notes
- Dashboard & TTY require strong `DASHBOARD_PASS` (use `password_hash()`)
- Never commit `.env` or secrets to repo
- Input sanitization & CSP headers enforced by default
- CookieJar validates all fields before injection — no arbitrary code execution

## 📄 License
MIT © 2026 — Free for personal use. Not for commercial redistribution.

---

> 💡 **Pro Tip**: For persistent Cloudflare sessions, solve the challenge once manually via dashboard, then export the `cf_clearance` cookie. Reuse it across workflow runs by injecting via `DASHBOARD_PASS`-protected API.

**Built for power users who need control, not complexity.** 🛠️✨
