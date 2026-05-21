<?php
// ============================================================
//  reset_password.php — Fix Password Hash Aphelion Tea
//  Jalankan SEKALI di browser: http://localhost/aphelion-tea/reset_password.php
//  Setelah berhasil, HAPUS file ini dari server!
// ============================================================
require_once __DIR__ . '/config.php';

$results = [];
$errors  = [];

try {
    $db = getDB();

    // Daftar user dan password baru
    $users = [
        ['username' => 'admin',  'password' => 'admin123'],
        ['username' => 'booth1', 'password' => 'pass123'],
        ['username' => 'booth2', 'password' => 'pass123'],
        ['username' => 'booth3', 'password' => 'pass123'],
    ];

    foreach ($users as $u) {
        $hash = password_hash($u['password'], PASSWORD_BCRYPT);
        $stmt = $db->prepare('UPDATE users SET password = ? WHERE username = ?');
        $ok   = $stmt->execute([$hash, $u['username']]);

        if ($ok && $stmt->rowCount() > 0) {
            $results[] = "✅ User <b>{$u['username']}</b> → password direset ke <b>{$u['password']}</b>";
        } elseif ($stmt->rowCount() === 0) {
            $errors[] = "⚠️ User <b>{$u['username']}</b> tidak ditemukan di database.";
        } else {
            $errors[] = "❌ Gagal update user <b>{$u['username']}</b>.";
        }
    }
} catch (Exception $e) {
    $errors[] = "❌ Error koneksi database: " . htmlspecialchars($e->getMessage());
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<title>Reset Password — Aphelion Tea</title>
<style>
  body { font-family: Arial, sans-serif; max-width: 600px; margin: 60px auto; padding: 20px; }
  h2   { color: #2d6a4f; }
  .ok  { background: #d8f3dc; border-left: 4px solid #2d6a4f; padding: 10px; margin: 8px 0; border-radius: 4px; }
  .err { background: #ffe8e8; border-left: 4px solid #c00; padding: 10px; margin: 8px 0; border-radius: 4px; }
  .info{ background: #fff3cd; border: 1px solid #ffc107; padding: 15px; border-radius: 6px; margin-top: 20px; }
  table{ border-collapse: collapse; width: 100%; margin-top: 16px; }
  th,td{ border: 1px solid #ccc; padding: 8px 12px; text-align: left; }
  th   { background: #2d6a4f; color: #fff; }
</style>
</head>
<body>
<h2>🔧 Reset Password — Aphelion Tea</h2>

<?php foreach ($results as $r): ?>
  <div class="ok"><?= $r ?></div>
<?php endforeach; ?>

<?php foreach ($errors as $e): ?>
  <div class="err"><?= $e ?></div>
<?php endforeach; ?>

<?php if (empty($errors) && !empty($results)): ?>
<h3>✅ Semua password berhasil diperbaiki!</h3>

<table>
  <tr><th>Username</th><th>Password</th><th>Role</th></tr>
  <tr><td>admin</td><td>admin123</td><td>Admin</td></tr>
  <tr><td>booth1</td><td>pass123</td><td>Staff — Booth Alun-alun</td></tr>
  <tr><td>booth2</td><td>pass123</td><td>Staff — Booth Pasar Seni</td></tr>
  <tr><td>booth3</td><td>pass123</td><td>Staff — Booth Kampus</td></tr>
</table>

<div class="info">
  ⚠️ <b>Penting:</b> Setelah login berhasil, segera <b>hapus file <code>reset_password.php</code></b>
  dari folder project kamu agar tidak bisa diakses orang lain.<br><br>
  Login di: <a href="index.html">index.html</a>
</div>
<?php endif; ?>

</body>
</html>
