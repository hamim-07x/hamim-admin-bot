# HAMIM Admin Bot

Telegram bot + Web Admin Panel (PHP 8.2 · MySQL · Railway)

## Deploy (Railway)
1. Create a **MySQL** service
2. Deploy this repo as a web service (uses Dockerfile)
3. Link MySQL variables (`MYSQL_PRIVATE_URL` or MYSQLHOST / MYSQLUSER / MYSQLPASSWORD / MYSQLDATABASE)
4. Open `/setup.php` once
5. Login `/admin/` → `admin` / `admin123`
6. Settings → Bot Token → Webhook **ON**

Health check: `/ping.php` (no DB required)
Webhook URL: `https://YOUR-APP.up.railway.app/webhook.php`
