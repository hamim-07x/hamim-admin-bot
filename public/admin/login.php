<?php
require_once __DIR__ . '/../../config/bootstrap.php';
if (isAdminLoggedIn()) { header('Location: /admin/'); exit; }
$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $user = trim($_POST['username'] ?? '');
    $pass = (string)($_POST['password'] ?? '');
    $stmt = getDB()->prepare('SELECT id, password_hash FROM admins WHERE username = ? LIMIT 1');
    $stmt->execute([$user]);
    $row = $stmt->fetch();
    if ($row && password_verify($pass, $row['password_hash'])) {
        $_SESSION['admin_id'] = (int)$row['id'];
        header('Location: /admin/');
        exit;
    }
    $error = 'Invalid username or password';
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
    <input name="username" required autocomplete="username">
    <label>Password</label>
    <input type="password" name="password" required autocomplete="current-password">
    <button type="submit" class="btn-full">Login</button>
  </form>
</div></body></html>
