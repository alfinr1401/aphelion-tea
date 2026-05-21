<?php
// ============================================================
//  api/users.php — CRUD Manajemen User
//  GET    /api/users.php             — list semua user
//  GET    /api/users.php?id=1        — detail user
//  POST   /api/users.php             — tambah user baru
//  PUT    /api/users.php?id=1        — update user
//  DELETE /api/users.php?id=1        — hapus user
//  PUT    /api/users.php?action=change_password&id=1  — ganti password
//  PUT    /api/users.php?action=reset_password&id=1   — reset password (admin)
// ============================================================
session_start();
require_once __DIR__ . '/../config.php';

$method = $_SERVER['REQUEST_METHOD'];
$action = $_GET['action'] ?? '';
$id     = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$db     = getDB();

// ---- Helper: require login ----
function requireLogin(): void {
    if (empty($_SESSION['user_id'])) {
        jsonResponse(false, null, 'Belum login.');
    }
}
function requireAdmin(): void {
    requireLogin();
    // Ambil role dari DB
    $db   = getDB();
    $stmt = $db->prepare('SELECT role FROM users WHERE id = ? LIMIT 1');
    $stmt->execute([$_SESSION['user_id']]);
    $u = $stmt->fetch();
    if (!$u || $u['role'] !== 'admin') {
        jsonResponse(false, null, 'Akses ditolak. Hanya Admin.');
    }
}

// ======================================================
// GET — list / detail
// ======================================================
if ($method === 'GET') {
    requireLogin();
    if ($id) {
        $stmt = $db->prepare('SELECT id,name,username,role,booth_id,phone,email,address,join_date,active,created_at FROM users WHERE id=? LIMIT 1');
        $stmt->execute([$id]);
        $user = $stmt->fetch();
        if (!$user) jsonResponse(false, null, 'User tidak ditemukan.');
        jsonResponse(true, $user);
    }
    // List semua
    $role = $_GET['role'] ?? '';
    $sql  = 'SELECT id,name,username,role,booth_id,phone,email,address,join_date,active,created_at FROM users';
    $params = [];
    if ($role) { $sql .= ' WHERE role = ?'; $params[] = $role; }
    $sql .= ' ORDER BY id ASC';
    $stmt = $db->prepare($sql);
    $stmt->execute($params);
    jsonResponse(true, $stmt->fetchAll());
}

// ======================================================
// POST — tambah user
// ======================================================
if ($method === 'POST') {
    requireAdmin();
    $body     = json_decode(file_get_contents('php://input'), true);
    $name     = trim($body['name']     ?? '');
    $username = strtolower(trim($body['username'] ?? ''));
    $password = trim($body['password'] ?? '');
    $role     = $body['role']     ?? 'staff';
    $boothId  = !empty($body['booth_id']) ? (int)$body['booth_id'] : null;
    $phone    = trim($body['phone']   ?? '');
    $email    = trim($body['email']   ?? '');
    $address  = trim($body['address'] ?? '');

    if (!$name || !$username || !$password) jsonResponse(false, null, 'Nama, username, dan password wajib.');
    if (strlen($password) < 6) jsonResponse(false, null, 'Password minimal 6 karakter.');
    if ($role === 'staff' && !$boothId) jsonResponse(false, null, 'Booth wajib untuk karyawan.');

    // Cek duplikat username
    $chk = $db->prepare('SELECT id FROM users WHERE username = ?');
    $chk->execute([$username]);
    if ($chk->fetch()) jsonResponse(false, null, 'Username sudah digunakan.');

    $hash = password_hash($password, PASSWORD_BCRYPT);
    $stmt = $db->prepare(
        'INSERT INTO users (name,username,password,role,booth_id,phone,email,address,join_date,active)
         VALUES (?,?,?,?,?,?,?,?,CURDATE(),1)'
    );
    $stmt->execute([$name,$username,$hash,$role,$boothId,$phone,$email,$address]);
    $newId = $db->lastInsertId();
    jsonResponse(true, ['id' => (int)$newId], 'User berhasil ditambahkan.');
}

// ======================================================
// PUT — update / change_password / reset_password
// ======================================================
if ($method === 'PUT') {
    requireLogin();
    $body = json_decode(file_get_contents('php://input'), true);

    // -- Ganti password sendiri --
    if ($action === 'change_password') {
        $uid     = (int)$_SESSION['user_id'];
        $current = trim($body['current_password'] ?? '');
        $newPass = trim($body['new_password'] ?? '');
        $confirm = trim($body['confirm_password'] ?? '');

        if (!$current || !$newPass) jsonResponse(false, null, 'Semua field password wajib diisi.');
        if (strlen($newPass) < 6) jsonResponse(false, null, 'Password baru minimal 6 karakter.');
        if ($newPass !== $confirm) jsonResponse(false, null, 'Konfirmasi password tidak cocok.');

        $stmt = $db->prepare('SELECT password FROM users WHERE id=?');
        $stmt->execute([$uid]);
        $row = $stmt->fetch();
        if (!$row || !password_verify($current, $row['password'])) {
            jsonResponse(false, null, 'Password saat ini salah.');
        }
        $hash = password_hash($newPass, PASSWORD_BCRYPT);
        $db->prepare('UPDATE users SET password=? WHERE id=?')->execute([$hash, $uid]);
        jsonResponse(true, null, 'Password berhasil diubah.');
    }

    // -- Reset password (admin only) --
    if ($action === 'reset_password') {
        requireAdmin();
        if (!$id) jsonResponse(false, null, 'ID user wajib.');
        $newPass = trim($body['new_password'] ?? '');
        $confirm = trim($body['confirm_password'] ?? '');
        if (!$newPass || strlen($newPass) < 6) jsonResponse(false, null, 'Password minimal 6 karakter.');
        if ($newPass !== $confirm) jsonResponse(false, null, 'Konfirmasi password tidak cocok.');
        $hash = password_hash($newPass, PASSWORD_BCRYPT);
        $db->prepare('UPDATE users SET password=? WHERE id=?')->execute([$hash, $id]);
        jsonResponse(true, null, 'Password berhasil direset.');
    }

    // -- Update data user --
    requireAdmin();
    if (!$id) jsonResponse(false, null, 'ID user wajib.');
    $name     = trim($body['name']     ?? '');
    $username = strtolower(trim($body['username'] ?? ''));
    $role     = $body['role']     ?? 'staff';
    $boothId  = !empty($body['booth_id']) ? (int)$body['booth_id'] : null;
    $phone    = trim($body['phone']   ?? '');
    $email    = trim($body['email']   ?? '');
    $address  = trim($body['address'] ?? '');
    $active   = isset($body['active']) ? (int)$body['active'] : 1;

    if (!$name || !$username) jsonResponse(false, null, 'Nama dan username wajib.');
    $chk = $db->prepare('SELECT id FROM users WHERE username=? AND id!=?');
    $chk->execute([$username, $id]);
    if ($chk->fetch()) jsonResponse(false, null, 'Username sudah digunakan.');

    $db->prepare(
        'UPDATE users SET name=?,username=?,role=?,booth_id=?,phone=?,email=?,address=?,active=? WHERE id=?'
    )->execute([$name,$username,$role,$boothId,$phone,$email,$address,$active,$id]);

    // Update password jika disertakan
    if (!empty($body['password']) && strlen(trim($body['password'])) >= 6) {
        $hash = password_hash(trim($body['password']), PASSWORD_BCRYPT);
        $db->prepare('UPDATE users SET password=? WHERE id=?')->execute([$hash, $id]);
    }
    jsonResponse(true, null, 'User berhasil diperbarui.');
}

// ======================================================
// DELETE — hapus user
// ======================================================
if ($method === 'DELETE') {
    requireAdmin();
    if (!$id) jsonResponse(false, null, 'ID user wajib.');
    if ($id === (int)$_SESSION['user_id']) jsonResponse(false, null, 'Tidak bisa menghapus akun sendiri.');
    $db->prepare('DELETE FROM users WHERE id=?')->execute([$id]);
    jsonResponse(true, null, 'User berhasil dihapus.');
}

jsonResponse(false, null, 'Method tidak dikenal.');
