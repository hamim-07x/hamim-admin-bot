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
        $stmt = $db->prepare('SELECT id, password_hash FROM admins WHERE username = ? LIMIT 1');
        $stmt->execute([$user]);
        $row = $stmt->fetch();

        if ($row && password_verify($pass, (string)$row['password_hash'])) {
            $_SESSION['admin_id'] = (int)$row['id'];
            $_SESSION['admin_user'] = $user;
            header('Location: /admin/');
            exit;
        }

        // Helpful hint if table empty / setup not run
        $count = (int)$db->query('SELECT COUNT(*) FROM admins')->fetchColumn();
        if ($count === 0) {
            $error = 'No admin found. Open /setup.php once, then login with admin / admin123';
        } else {
            $error = 'Invalid username or password. Default: admin / admin123 (run /setup.php to repair)';
        }
    } catch (Throwable $e) {
        $error = 'Database error. Check MySQL variables, then open /setup.php. ' . $e->getMessage();
    }
}
?><!DOCTYPE html>
<html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title>Admin Login</title>
<link rel="stylesheet" href="/admin/admin.css">
</head><body class="login-body">
<div class="login-card">
  <h1>Admin Login</h1>
  <?php if ($error): ?><div class="flash error"><?= htmlspecialchars($error) ?></div><?php endif; ?>
  <form method="post">
    <label>Username</label>
    <input name="username" required autocomplete="username" placeholder="admin" value="admin">
    <label>Password</label>
    <input type="password" name="password" required autocomplete="current-password" placeholder="admin123">
    <button type="submit" class="btn-full">Login</button>
  </form>
  <p style="margin-top:1rem;font-size:12px;color:#71717a;text-align:center">Default: <b>admin</b> / <b>admin123</b></p>
</div></body></html>
