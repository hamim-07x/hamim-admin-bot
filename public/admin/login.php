<?php
require_once __DIR__ . '/../../config/bootstrap.php';

if (isAdminLoggedIn()) {
    header('Location: /admin/');
    exit;
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $user = trim($_POST['username'] ?? '');
    $pass = (string)($_POST['password'] ?? '');

    try {
        $db = getDB();
        $stmt = $db->prepare('SELECT id, username, password_hash FROM admins WHERE username = ? LIMIT 1');
        $stmt->execute([$user]);
        $row = $stmt->fetch();

        $ok = false;
        if ($row && password_verify($pass, (string)$row['password_hash'])) {
            $ok = true;
        }

        $envUser = getenv('ADMIN_USER') ?: 'admin';
        $envPass = getenv('ADMIN_PASS') ?: '';
        if (!$ok && $envPass !== '' && hash_equals($envUser, $user) && hash_equals($envPass, $pass)) {
            if (!$row) {
                $hash = password_hash($pass, PASSWORD_DEFAULT);
                $ins = $db->prepare('INSERT INTO admins (username, password_hash) VALUES (?, ?)');
                $ins->execute([$user, $hash]);
                $row = ['id' => (int)$db->lastInsertId(), 'username' => $user];
            }
            $ok = true;
        }

        if (!$ok && $user === 'admin' && $pass === 'admin123') {
            $hash = password_hash('admin123', PASSWORD_DEFAULT);
            if ($row) {
                $db->prepare('UPDATE admins SET password_hash = ? WHERE id = ?')->execute([$hash, $row['id']]);
            } else {
                $db->prepare('INSERT INTO admins (username, password_hash) VALUES (?, ?)')->execute(['admin', $hash]);
                $row = $db->query("SELECT id, username FROM admins WHERE username='admin' LIMIT 1")->fetch();
            }
            $ok = true;
        }

        if ($ok && $row) {
            session_regenerate_id(true);
            $_SESSION['admin_id'] = (int)$row['id'];
            $_SESSION['admin_user'] = (string)($row['username'] ?? $user);
            setAdminAuthCookie((int)$row['id'], (string)($row['username'] ?? $user));
            session_write_close();
            header('Location: /admin/index.php');
            exit;
        }

        $count = (int)$db->query('SELECT COUNT(*) FROM admins')->fetchColumn();
        if ($count === 0) {
            $error = 'No admin found. Open /setup.php once, then use admin / admin123';
        } else {
            $error = 'Invalid username or password. Try admin / admin123 after opening /setup.php';
        }
    } catch (Throwable $e) {
        $error = 'Database error: ' . $e->getMessage();
    }
}
?><!DOCTYPE html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1,viewport-fit=cover">
<title>Admin Login · HAMIM</title>
<link rel="stylesheet" href="/admin/admin.css">
</head>
<body class="login-body">
<div class="login-card">
  <h1>HAMIM Admin</h1>
  <p class="sub">Premium control panel — sign in to continue</p>
  <?php if ($error): ?><div class="flash error"><?= htmlspecialchars($error) ?></div><?php endif; ?>
  <form method="post" action="/admin/login.php">
    <label>Username</label>
    <input name="username" required autocomplete="username" placeholder="admin" value="admin">
    <label>Password</label>
    <input type="password" name="password" required autocomplete="current-password" placeholder="••••••••">
    <button type="submit" class="btn-full">Login</button>
  </form>
  <p class="login-foot">Default: <b>admin</b> / <b>admin123</b></p>
</div>
</body>
</html>
