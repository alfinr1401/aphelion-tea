<?php
// ============================================================
//  api/auth.php — Login, Logout, Get Current User
//  POST /api/auth.php?action=login
//  POST /api/auth.php?action=logout
//  GET  /api/auth.php?action=me
// ============================================================
session_start();
require_once __DIR__ . '/../config.php';

$action = $_GET['action'] ?? '';

// ---- LOGIN ----
if ($action === 'login' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $body     = json_decode(file_get_contents('php://input'), true);
    $username = trim($body['username'] ?? '');
    $password = trim($body['password'] ?? '');

    if (!$username || !$password) {
        jsonResponse(false, null, 'Username dan password wajib diisi.');
    }

    $db   = getDB();
    $stmt = $db->prepare('SELECT * FROM users WHERE username = ? AND active = 1 LIMIT 1');
    $stmt->execute([$username]);
    $user = $stmt->fetch();

    if (!$user || !password_verify($password, $user['password'])) {
        jsonResponse(false, null, 'Username atau password salah.');
    }

    // Simpan session
    $_SESSION['user_id'] = $user['id'];

    unset($user['password']); // Jangan kirim hash ke client
    jsonResponse(true, $user, 'Login berhasil.');
}

// ---- LOGOUT ----
if ($action === 'logout') {
    session_destroy();
    jsonResponse(true, null, 'Logout berhasil.');
}

// ---- ME (get current logged-in user) ----
if ($action === 'me' && $_SERVER['REQUEST_METHOD'] === 'GET') {
    if (empty($_SESSION['user_id'])) {
        jsonResponse(false, null, 'Belum login.');
    }
    $db   = getDB();
    $stmt = $db->prepare('SELECT id,name,username,role,booth_id,phone,email,address,join_date,active FROM users WHERE id = ? LIMIT 1');
    $stmt->execute([$_SESSION['user_id']]);
    $user = $stmt->fetch();
    if (!$user) jsonResponse(false, null, 'User tidak ditemukan.');
    jsonResponse(true, $user);
}

jsonResponse(false, null, 'Action tidak dikenal.');
