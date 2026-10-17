<?php
require_once __DIR__ . '/../config/auth.php';
requireRole(['customer']);

$user = currentUser();
$errors = [];
$successMessage = '';
$profileStmt = $pdo->prepare('SELECT name, email, phone, password FROM users WHERE id = :user_id LIMIT 1');
$profileStmt->execute(['user_id' => $user['id']]);
$profile = $profileStmt->fetch();

if (!$profile) {
    http_response_code(404);
    exit('Profile tidak ditemukan.');
}

if (empty($_SESSION['profile_csrf_token'])) {
    $_SESSION['profile_csrf_token'] = bin2hex(random_bytes(32));
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $csrfToken = $_POST['csrf_token'] ?? '';
    if (!is_string($csrfToken) || !hash_equals($_SESSION['profile_csrf_token'], $csrfToken)) {
        $errors[] = 'Sesi formulir tidak valid. Muat ulang halaman dan coba lagi.';
    }

    $name = trim((string) ($_POST['name'] ?? ''));
    $email = trim((string) ($_POST['email'] ?? ''));
    $phone = trim((string) ($_POST['phone'] ?? ''));
    $currentPassword = (string) ($_POST['current_password'] ?? '');
    $newPassword = (string) ($_POST['new_password'] ?? '');

    if ($name === '' || mb_strlen($name) > 255) {
        $errors[] = 'Nama wajib diisi dan maksimal 255 karakter.';
    }
    if (!filter_var($email, FILTER_VALIDATE_EMAIL) || strlen($email) > 255) {
        $errors[] = 'Alamat email tidak valid.';
    }
    if ($phone === '' || strlen($phone) > 50) {
        $errors[] = 'Nomor telepon wajib diisi dan maksimal 50 karakter.';
    }
    if ($newPassword !== '' && (strlen($newPassword) < 8 || !password_verify($currentPassword, $profile['password']))) {
        $errors[] = strlen($newPassword) < 8
            ? 'Password baru minimal 8 karakter.'
            : 'Password saat ini tidak sesuai.';
    }

    if (!$errors) {
        $duplicateStmt = $pdo->prepare('SELECT id FROM users WHERE email = :email AND id <> :user_id LIMIT 1');
        $duplicateStmt->execute(['email' => $email, 'user_id' => $user['id']]);
        if ($duplicateStmt->fetch()) {
            $errors[] = 'Email sudah digunakan akun lain.';
        }
    }

    if (!$errors) {
        if ($newPassword !== '') {
            $updateStmt = $pdo->prepare('UPDATE users SET name = :name, email = :email, phone = :phone, password = :password WHERE id = :user_id');
            $updateStmt->execute([
                'name' => $name,
                'email' => $email,
                'phone' => $phone,
                'password' => password_hash($newPassword, PASSWORD_DEFAULT),
                'user_id' => $user['id'],
            ]);
        } else {
            $updateStmt = $pdo->prepare('UPDATE users SET name = :name, email = :email, phone = :phone WHERE id = :user_id');
            $updateStmt->execute([
                'name' => $name,
                'email' => $email,
                'phone' => $phone,
                'user_id' => $user['id'],
            ]);
        }

        $_SESSION['user_name'] = $name;
        $_SESSION['user_email'] = $email;
        $successMessage = 'Profile berhasil diperbarui.';
        $profileStmt->execute(['user_id' => $user['id']]);
        $profile = $profileStmt->fetch();
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit Profile | CleanWash</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">
    <style>
        :root { --primary: #0d7ec9; --primary-dark: #0c4d79; --text: #1b2b3a; --muted: #5f7181; --bg: #f6f8fb; --border: #dfeaf1; }
        body { margin: 0; background: var(--bg); color: var(--text); font-family: Inter, sans-serif; }
        a { text-decoration: none; }
        .app-header { background: rgba(255,255,255,.96); border-bottom: 1px solid rgba(15,85,124,.08); }
        .header-inner { min-height: 76px; }
        .brand { color: var(--text); font-size: 1.45rem; font-weight: 800; }
        .brand-mark { width: 40px; height: 40px; display: inline-grid; place-items: center; border-radius: 15px; background: linear-gradient(135deg,#0ea5e9,var(--primary-dark)); color: #fff; }
        .page-band { background: linear-gradient(115deg,#eaf8ff 0%,#f3fbff 58%,#e8f8f2 100%); border-bottom: 1px solid #e5f0f3; }
        .page-band h1 { font-size: clamp(1.7rem,3vw,2.3rem); font-weight: 800; }
        .panel { background: #fff; border: 1px solid var(--border); border-radius: 18px; box-shadow: 0 12px 28px rgba(16,62,89,.045); }
        .form-control { border: 1px solid var(--border); border-radius: 11px; padding: .75rem .9rem; }
        .btn-primary { background: linear-gradient(135deg,var(--primary),#18a0d6); border: 0; border-radius: 12px; padding: .8rem 1.1rem; font-weight: 700; }
        @media(max-width:575.98px) { .header-inner { min-height: 66px; } }
    </style>
</head>
<body>
    <header class="app-header">
        <div class="container header-inner d-flex align-items-center justify-content-between">
            <a class="brand d-flex align-items-center gap-2" href="<?= htmlspecialchars(appUrlPath('/customer/dashboard.php')) ?>"><span class="brand-mark"><i class="fa-solid fa-droplet"></i></span>CleanWash</a>
            <a class="btn btn-outline-primary" href="<?= htmlspecialchars(appUrlPath('/customer/dashboard.php')) ?>"><i class="fa-solid fa-arrow-left me-2"></i>Dashboard</a>
        </div>
    </header>
    <section class="page-band py-4"><div class="container"><h1 class="mb-1">Edit profile</h1><p class="text-secondary mb-0">Perbarui informasi akun CleanWash Anda.</p></div></section>
    <main class="container py-4">
        <div class="row justify-content-center"><div class="col-lg-8 col-xl-7">
            <?php if ($errors): ?><div class="alert alert-danger"><ul class="mb-0"><?php foreach ($errors as $error): ?><li><?= htmlspecialchars($error) ?></li><?php endforeach; ?></ul></div><?php endif; ?>
            <?php if ($successMessage !== ''): ?><div class="alert alert-success"><?= htmlspecialchars($successMessage) ?></div><?php endif; ?>
            <section class="panel p-4 p-md-5">
                <form method="post">
                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['profile_csrf_token']) ?>">
                    <div class="mb-3"><label class="form-label fw-semibold" for="name">Nama lengkap</label><input class="form-control" id="name" name="name" maxlength="255" autocomplete="name" required value="<?= htmlspecialchars($profile['name']) ?>"></div>
                    <div class="mb-3"><label class="form-label fw-semibold" for="email">Email</label><input class="form-control" id="email" name="email" type="email" maxlength="255" autocomplete="email" required value="<?= htmlspecialchars($profile['email']) ?>"></div>
                    <div class="mb-4"><label class="form-label fw-semibold" for="phone">Nomor telepon</label><input class="form-control" id="phone" name="phone" maxlength="50" autocomplete="tel" required value="<?= htmlspecialchars($profile['phone']) ?>"></div>
                    <hr class="my-4">
                    <h2 class="h6 fw-bold mb-3">Ubah password <span class="text-secondary fw-normal">(opsional)</span></h2>
                    <div class="mb-3"><label class="form-label fw-semibold" for="current_password">Password saat ini</label><input class="form-control" id="current_password" name="current_password" type="password" autocomplete="current-password"></div>
                    <div class="mb-4"><label class="form-label fw-semibold" for="new_password">Password baru</label><input class="form-control" id="new_password" name="new_password" type="password" minlength="8" autocomplete="new-password"><div class="form-text">Kosongkan jika tidak ingin mengubah password.</div></div>
                    <button class="btn btn-primary" type="submit"><i class="fa-solid fa-floppy-disk me-2"></i>Simpan perubahan</button>
                </form>
            </section>
        </div></div>
    </main>
</body>
</html>