<?php
require_once __DIR__ . '/../../config/bootstrap.php';
requireAdmin();

$id = (int)($_POST['id'] ?? 0);
$title = trim($_POST['title'] ?? '');
$username = ltrim(preg_replace('#^https?://t\.me/#i', '', trim($_POST['username'] ?? '')), '@');
$invite = trim($_POST['invite_link'] ?? '');

if ($title === '' || $username === '' || $invite === '') {
    $_SESSION['flash'] = 'Title, username and invite link required.';
    header('Location: /admin/?page=channels');
    exit;
}
if (!preg_match('#^https?://#i', $invite)) {
    $invite = 'https://t.me/' . ltrim($invite, '/');
}

$db = getDB();
if ($id > 0) {
    $db->prepare('UPDATE channels SET title=?, username=?, invite_link=? WHERE id=?')
        ->execute([$title, $username, $invite, $id]);
    $_SESSION['flash'] = 'Channel updated.';
} else {
    $db->prepare('INSERT INTO channels (title, username, invite_link, is_required, is_active) VALUES (?,?,?,1,1)')
        ->execute([$title, $username, $invite]);
    $_SESSION['flash'] = 'Channel added.';
}
header('Location: /admin/?page=channels');
exit;
