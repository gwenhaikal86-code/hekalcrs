<?php
require_once __DIR__ . '/_auth.php';
require_once __DIR__ . '/_layout.php';

$errors = [];
$notice = '';
$formValues = ['name' => '', 'description' => '', 'price' => '', 'unit' => 'kg', 'estimated_time' => '', 'is_active' => 1];
$editingId = filter_input(INPUT_GET, 'edit', FILTER_VALIDATE_INT) ?: 0;

if ($editingId > 0) {
    $editStmt = $pdo->prepare('SELECT * FROM services WHERE id = :id LIMIT 1');
    $editStmt->execute(['id' => $editingId]);
    $editingService = $editStmt->fetch();
    if (!$editingService) {
        adminRedirect('/admin/services.php');
    }
    $formValues = $editingService;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!adminCsrfValid()) {
        $errors[] = 'Sesi formulir tidak valid. Muat ulang halaman dan coba lagi.';
    } else {
        $action = (string) ($_POST['action'] ?? '');
        $serviceId = filter_input(INPUT_POST, 'id', FILTER_VALIDATE_INT) ?: 0;

        if ($action === 'delete' && $serviceId > 0) {
            $usageStmt = $pdo->prepare('SELECT COUNT(*) FROM orders WHERE service_id = :id');
            $usageStmt->execute(['id' => $serviceId]);
            if ((int) $usageStmt->fetchColumn() > 0) {
                $deleteStmt = $pdo->prepare('UPDATE services SET is_active = 0 WHERE id = :id');
                $deleteStmt->execute(['id' => $serviceId]);
            } else {
                $deleteStmt = $pdo->prepare('DELETE FROM services WHERE id = :id');
                $deleteStmt->execute(['id' => $serviceId]);
            }
            adminRedirect('/admin/services.php?success=deleted');
        }

        $formValues = [
            'name' => trim((string) ($_POST['name'] ?? '')),
            'description' => trim((string) ($_POST['description'] ?? '')),
            'price' => trim((string) ($_POST['price'] ?? '')),
            'unit' => trim((string) ($_POST['unit'] ?? '')),
            'estimated_time' => trim((string) ($_POST['estimated_time'] ?? '')),
            'is_active' => isset($_POST['is_active']) ? 1 : 0,
        ];
        $editingId = $action === 'update' ? $serviceId : 0;

        if ($action === 'create' || $action === 'update') {
            if ($formValues['name'] === '' || mb_strlen($formValues['name']) > 150) {
                $errors[] = 'Nama layanan wajib diisi (maksimal 150 karakter).';
            }
            if (!is_numeric($formValues['price']) || (float) $formValues['price'] < 0) {
                $errors[] = 'Harga harus berupa angka nol atau lebih.';
            }
            if ($formValues['unit'] === '' || mb_strlen($formValues['unit']) > 50) {
                $errors[] = 'Satuan wajib diisi (maksimal 50 karakter).';
            }
            if (mb_strlen($formValues['estimated_time']) > 50) {
                $errors[] = 'Estimasi waktu maksimal 50 karakter.';
            }
            if (!$errors) {
                if ($action === 'create') {
                    $saveStmt = $pdo->prepare('INSERT INTO services (name, description, price, unit, estimated_time, is_active) VALUES (:name, :description, :price, :unit, :estimated_time, :is_active)');
                } else {
                    $saveStmt = $pdo->prepare('UPDATE services SET name = :name, description = :description, price = :price, unit = :unit, estimated_time = :estimated_time, is_active = :is_active WHERE id = :id');
                    $saveStmt->bindValue('id', $serviceId, PDO::PARAM_INT);
                }
                $saveStmt->bindValue('name', $formValues['name']);
                $saveStmt->bindValue('description', $formValues['description'] !== '' ? $formValues['description'] : null);
                $saveStmt->bindValue('price', (float) $formValues['price']);
                $saveStmt->bindValue('unit', $formValues['unit']);
                $saveStmt->bindValue('estimated_time', $formValues['estimated_time'] !== '' ? $formValues['estimated_time'] : null);
                $saveStmt->bindValue('is_active', $formValues['is_active'], PDO::PARAM_INT);
                $saveStmt->execute();
                adminRedirect('/admin/services.php?success=saved');
            }
        }
    }
}

$success = $_GET['success'] ?? '';
if ($success === 'saved') $notice = 'Data layanan berhasil disimpan.';
if ($success === 'deleted') $notice = 'Layanan berhasil dihapus atau dinonaktifkan karena masih digunakan.';
$services = $pdo->query('SELECT s.*, (SELECT COUNT(*) FROM orders o WHERE o.service_id = s.id) AS order_count FROM services s ORDER BY s.is_active DESC, s.name ASC')->fetchAll();

renderAdminHeader('services', 'Kelola Layanan');
?>
<section class="page-heading py-4"><div class="container d-flex justify-content-between gap-3"><div><h1 class="h3 fw-bold mb-1">Kelola layanan</h1><p class="text-secondary mb-0">Atur jenis perawatan, harga, dan ketersediaan.</p></div></div></section>
<div class="container py-4">
    <?php if ($notice): ?><div class="alert alert-success"><?= htmlspecialchars($notice) ?></div><?php endif; ?>
    <?php if ($errors): ?><div class="alert alert-danger"><ul class="mb-0"><?php foreach ($errors as $error): ?><li><?= htmlspecialchars($error) ?></li><?php endforeach; ?></ul></div><?php endif; ?>
    <div class="row g-4">
        <div class="col-lg-4"><section class="panel p-4"><h2 class="h5 fw-bold mb-3"><?= $editingId ? 'Ubah layanan' : 'Tambah layanan' ?></h2>
            <form method="post">
                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['admin_csrf_token']) ?>">
                <input type="hidden" name="action" value="<?= $editingId ? 'update' : 'create' ?>">
                <?php if ($editingId): ?><input type="hidden" name="id" value="<?= (int) $editingId ?>"><?php endif; ?>
                <div class="mb-3"><label class="form-label" for="name">Nama layanan</label><input class="form-control" id="name" name="name" maxlength="150" required value="<?= htmlspecialchars((string) $formValues['name']) ?>"></div>
                <div class="mb-3"><label class="form-label" for="description">Deskripsi</label><textarea class="form-control" id="description" name="description" rows="3"><?= htmlspecialchars((string) ($formValues['description'] ?? '')) ?></textarea></div>
                <div class="row g-2"><div class="col-7 mb-3"><label class="form-label" for="price">Harga</label><input class="form-control" id="price" name="price" type="number" min="0" step="0.01" required value="<?= htmlspecialchars((string) $formValues['price']) ?>"></div><div class="col-5 mb-3"><label class="form-label" for="unit">Satuan</label><input class="form-control" id="unit" name="unit" maxlength="50" required value="<?= htmlspecialchars((string) $formValues['unit']) ?>"></div></div>
                <div class="mb-3"><label class="form-label" for="estimated_time">Estimasi waktu</label><input class="form-control" id="estimated_time" name="estimated_time" maxlength="50" value="<?= htmlspecialchars((string) ($formValues['estimated_time'] ?? '')) ?>" placeholder="Contoh: 24 jam"></div>
                <div class="form-check mb-3"><input class="form-check-input" type="checkbox" name="is_active" id="is_active" value="1" <?= (int) $formValues['is_active'] === 1 ? 'checked' : '' ?>><label class="form-check-label" for="is_active">Layanan aktif</label></div>
                <button class="btn btn-primary" type="submit"><i class="fa-solid fa-floppy-disk me-2"></i><?= $editingId ? 'Simpan perubahan' : 'Tambah layanan' ?></button>
                <?php if ($editingId): ?><a class="btn btn-light ms-1" href="<?= htmlspecialchars(appUrlPath('/admin/services.php')) ?>">Batal</a><?php endif; ?>
            </form>
        </section></div>
        <div class="col-lg-8"><section class="panel p-3 p-lg-4"><div class="d-flex justify-content-between align-items-center mb-3"><h2 class="h5 fw-bold mb-0">Daftar layanan</h2><span class="badge badge-soft"><?= count($services) ?> layanan</span></div>
            <div class="table-responsive"><table class="table align-middle"><thead><tr><th>Layanan</th><th>Harga</th><th>Estimasi</th><th>Status</th><th>Aksi</th></tr></thead><tbody>
                <?php foreach ($services as $service): ?><tr>
                    <td><strong><?= htmlspecialchars($service['name']) ?></strong><small class="d-block text-secondary"><?= htmlspecialchars((string) $service['description']) ?></small></td>
                    <td>Rp<?= number_format((float) $service['price'], 0, ',', '.') ?>/<?= htmlspecialchars($service['unit']) ?></td><td><?= htmlspecialchars((string) ($service['estimated_time'] ?? '-')) ?></td>
                    <td><span class="badge <?= $service['is_active'] ? 'text-bg-success' : 'text-bg-secondary' ?>"><?= $service['is_active'] ? 'Aktif' : 'Nonaktif' ?></span></td>
                    <td><div class="d-flex gap-2"><a class="btn btn-sm btn-outline-primary" href="<?= htmlspecialchars(appUrlPath('/admin/services.php?edit=' . (int) $service['id'])) ?>" aria-label="Ubah layanan"><i class="fa-solid fa-pen"></i></a><form method="post" onsubmit="return confirm('Hapus atau nonaktifkan layanan ini?')"><input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['admin_csrf_token']) ?>"><input type="hidden" name="action" value="delete"><input type="hidden" name="id" value="<?= (int) $service['id'] ?>"><button class="btn btn-sm btn-outline-danger" type="submit" aria-label="Hapus layanan"><i class="fa-solid fa-trash"></i></button></form></div></td>
                </tr><?php endforeach; ?>
                <?php if (!$services): ?><tr><td colspan="5" class="text-center text-secondary py-4">Belum ada layanan.</td></tr><?php endif; ?>
            </tbody></table></div>
        </section></div>
    </div>
</div>
<?php renderAdminFooter(); ?>