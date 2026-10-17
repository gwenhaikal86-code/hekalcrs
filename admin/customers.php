<?php
require_once __DIR__ . '/_auth.php';
require_once __DIR__ . '/_layout.php';

$errors = [];
$notice = '';
$search = trim((string) ($_GET['q'] ?? ''));
$formValues = ['name' => '', 'email' => '', 'phone' => ''];
$editingId = filter_input(INPUT_GET, 'edit', FILTER_VALIDATE_INT) ?: 0;

if ($editingId > 0) {
    $editStmt = $pdo->prepare("SELECT id, name, email, phone FROM users WHERE id = :id AND role = 'customer' LIMIT 1");
    $editStmt->execute(['id' => $editingId]);
    $editingCustomer = $editStmt->fetch();
    if (!$editingCustomer) {
        adminRedirect('/admin/customers.php');
    }
    $formValues = $editingCustomer;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!adminCsrfValid()) {
        $errors[] = 'Sesi formulir tidak valid. Muat ulang halaman dan coba lagi.';
    } else {
        $action = (string) ($_POST['action'] ?? '');
        $customerId = filter_input(INPUT_POST, 'id', FILTER_VALIDATE_INT) ?: 0;

        if ($action === 'delete' && $customerId > 0) {
            $ordersStmt = $pdo->prepare('SELECT COUNT(*) FROM orders WHERE user_id = :id');
            $ordersStmt->execute(['id' => $customerId]);
            if ((int) $ordersStmt->fetchColumn() > 0) {
                $errors[] = 'Customer masih memiliki riwayat pesanan dan tidak dapat dihapus.';
            } else {
                $deleteStmt = $pdo->prepare("DELETE FROM users WHERE id = :id AND role = 'customer'");
                $deleteStmt->execute(['id' => $customerId]);
                adminRedirect('/admin/customers.php?success=deleted');
            }
        } else {
            $formValues = [
                'name' => trim((string) ($_POST['name'] ?? '')),
                'email' => trim((string) ($_POST['email'] ?? '')),
                'phone' => trim((string) ($_POST['phone'] ?? '')),
            ];
            $password = (string) ($_POST['password'] ?? '');
            $editingId = $action === 'update' ? $customerId : 0;

            if ($formValues['name'] === '' || mb_strlen($formValues['name']) > 255) {
                $errors[] = 'Nama wajib diisi (maksimal 255 karakter).';
            }
            if (!filter_var($formValues['email'], FILTER_VALIDATE_EMAIL) || strlen($formValues['email']) > 255) {
                $errors[] = 'Alamat email tidak valid.';
            }
            if ($formValues['phone'] === '' || strlen($formValues['phone']) > 50) {
                $errors[] = 'Nomor telepon wajib diisi (maksimal 50 karakter).';
            }
            if (($action === 'create' || $password !== '') && strlen($password) < 8) {
                $errors[] = 'Password minimal 8 karakter.';
            }
            if (!$errors) {
                $duplicateStmt = $pdo->prepare('SELECT id FROM users WHERE email = :email AND id <> :id LIMIT 1');
                $duplicateStmt->execute(['email' => $formValues['email'], 'id' => $customerId]);
                if ($duplicateStmt->fetch()) {
                    $errors[] = 'Email sudah digunakan akun lain.';
                }
            }
            if (!$errors && $action === 'create') {
                $insertStmt = $pdo->prepare("INSERT INTO users (name, email, phone, password, role) VALUES (:name, :email, :phone, :password, 'customer')");
                $insertStmt->execute([
                    'name' => $formValues['name'], 'email' => $formValues['email'], 'phone' => $formValues['phone'],
                    'password' => password_hash($password, PASSWORD_DEFAULT),
                ]);
                adminRedirect('/admin/customers.php?success=saved');
            }
            if (!$errors && $action === 'update') {
                if ($password !== '') {
                    $updateStmt = $pdo->prepare("UPDATE users SET name = :name, email = :email, phone = :phone, password = :password WHERE id = :id AND role = 'customer'");
                    $updateStmt->execute([
                        'name' => $formValues['name'], 'email' => $formValues['email'], 'phone' => $formValues['phone'],
                        'password' => password_hash($password, PASSWORD_DEFAULT), 'id' => $customerId,
                    ]);
                } else {
                    $updateStmt = $pdo->prepare("UPDATE users SET name = :name, email = :email, phone = :phone WHERE id = :id AND role = 'customer'");
                    $updateStmt->execute([
                        'name' => $formValues['name'], 'email' => $formValues['email'], 'phone' => $formValues['phone'], 'id' => $customerId,
                    ]);
                }
                adminRedirect('/admin/customers.php?success=saved');
            }
        }
    }
}

$success = $_GET['success'] ?? '';
if ($success === 'saved') $notice = 'Data customer berhasil disimpan.';
if ($success === 'deleted') $notice = 'Customer berhasil dihapus.';
$customersStmt = $pdo->prepare("SELECT u.id, u.name, u.email, u.phone, u.created_at, COUNT(o.id) AS order_count FROM users u LEFT JOIN orders o ON o.user_id = u.id WHERE u.role = 'customer' AND (u.name LIKE :name_search OR u.email LIKE :email_search OR u.phone LIKE :phone_search) GROUP BY u.id ORDER BY u.created_at DESC");
$term = '%' . $search . '%';
$customersStmt->execute(['name_search' => $term, 'email_search' => $term, 'phone_search' => $term]);
$customers = $customersStmt->fetchAll();

renderAdminHeader('customers', 'Kelola Customer');
?>
<section class="page-heading py-4"><div class="container"><h1 class="h3 fw-bold mb-1">Kelola customer</h1><p class="text-secondary mb-0">Buat, perbarui, dan lihat akun pelanggan.</p></div></section>
<div class="container py-4">
    <?php if ($notice): ?><div class="alert alert-success"><?= htmlspecialchars($notice) ?></div><?php endif; ?>
    <?php if ($errors): ?><div class="alert alert-danger"><ul class="mb-0"><?php foreach ($errors as $error): ?><li><?= htmlspecialchars($error) ?></li><?php endforeach; ?></ul></div><?php endif; ?>
    <div class="row g-4">
        <div class="col-lg-4"><section class="panel p-4"><h2 class="h5 fw-bold mb-3"><?= $editingId ? 'Ubah customer' : 'Tambah customer' ?></h2>
            <form method="post">
                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['admin_csrf_token']) ?>">
                <input type="hidden" name="action" value="<?= $editingId ? 'update' : 'create' ?>">
                <?php if ($editingId): ?><input type="hidden" name="id" value="<?= (int) $editingId ?>"><?php endif; ?>
                <div class="mb-3"><label class="form-label" for="name">Nama lengkap</label><input class="form-control" id="name" name="name" maxlength="255" required value="<?= htmlspecialchars($formValues['name']) ?>"></div>
                <div class="mb-3"><label class="form-label" for="email">Email</label><input class="form-control" id="email" name="email" type="email" maxlength="255" required value="<?= htmlspecialchars($formValues['email']) ?>"></div>
                <div class="mb-3"><label class="form-label" for="phone">Nomor telepon</label><input class="form-control" id="phone" name="phone" maxlength="50" required value="<?= htmlspecialchars($formValues['phone']) ?>"></div>
                <div class="mb-3"><label class="form-label" for="password">Password <?= $editingId ? '(opsional)' : '' ?></label><input class="form-control" id="password" name="password" type="password" minlength="8" <?= $editingId ? '' : 'required' ?> autocomplete="new-password"><small class="text-secondary"><?= $editingId ? 'Kosongkan jika tidak ingin mengganti password.' : 'Minimal 8 karakter.' ?></small></div>
                <button class="btn btn-primary" type="submit"><i class="fa-solid fa-floppy-disk me-2"></i><?= $editingId ? 'Simpan perubahan' : 'Tambah customer' ?></button>
                <?php if ($editingId): ?><a class="btn btn-light ms-1" href="<?= htmlspecialchars(appUrlPath('/admin/customers.php')) ?>">Batal</a><?php endif; ?>
            </form>
        </section></div>
        <div class="col-lg-8"><section class="panel p-3 p-lg-4">
            <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3"><h2 class="h5 fw-bold mb-0">Daftar customer</h2><form method="get" class="d-flex gap-2"><input class="form-control" name="q" aria-label="Cari customer" placeholder="Nama, email, telepon" value="<?= htmlspecialchars($search) ?>"><button class="btn btn-outline-primary" aria-label="Cari"><i class="fa-solid fa-magnifying-glass"></i></button></form></div>
            <div class="table-responsive"><table class="table align-middle"><thead><tr><th>Customer</th><th>Telepon</th><th>Pesanan</th><th>Terdaftar</th><th>Aksi</th></tr></thead><tbody>
                <?php foreach ($customers as $customer): ?><tr>
                    <td><strong><?= htmlspecialchars($customer['name']) ?></strong><small class="d-block text-secondary"><?= htmlspecialchars($customer['email']) ?></small></td>
                    <td><?= htmlspecialchars($customer['phone']) ?></td><td><span class="badge badge-soft"><?= (int) $customer['order_count'] ?></span></td><td><?= date('d M Y', strtotime($customer['created_at'])) ?></td>
                    <td><div class="d-flex gap-2"><a class="btn btn-sm btn-outline-primary" href="<?= htmlspecialchars(appUrlPath('/admin/customers.php?edit=' . (int) $customer['id'])) ?>" aria-label="Ubah customer"><i class="fa-solid fa-pen"></i></a><form method="post" onsubmit="return confirm('Hapus customer ini? Customer dengan riwayat pesanan tidak dapat dihapus.')"><input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['admin_csrf_token']) ?>"><input type="hidden" name="action" value="delete"><input type="hidden" name="id" value="<?= (int) $customer['id'] ?>"><button class="btn btn-sm btn-outline-danger" aria-label="Hapus customer"><i class="fa-solid fa-trash"></i></button></form></div></td>
                </tr><?php endforeach; ?>
                <?php if (!$customers): ?><tr><td colspan="5" class="text-center text-secondary py-4">Customer tidak ditemukan.</td></tr><?php endif; ?>
            </tbody></table></div>
        </section></div>
    </div>
</div>
<?php renderAdminFooter(); ?>