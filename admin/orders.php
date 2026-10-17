<?php
require_once __DIR__ . '/_auth.php';
require_once __DIR__ . '/_layout.php';

$statuses = [
    'menunggu_konfirmasi', 'dikonfirmasi', 'menunggu_pickup', 'laundry_diterima',
    'diproses', 'selesai', 'siap_diambil', 'sedang_diantar', 'pesanan_selesai', 'dibatalkan',
];
$paymentStatuses = ['pending' => 'Menunggu pembayaran', 'paid' => 'Lunas'];
$errors = [];
$notice = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!adminCsrfValid()) {
        $errors[] = 'Sesi formulir tidak valid. Muat ulang halaman dan coba lagi.';
    } else {
        $action = (string) ($_POST['action'] ?? '');
        $orderId = filter_input(INPUT_POST, 'id', FILTER_VALIDATE_INT) ?: 0;
        if ($action === 'status' && $orderId > 0) {
            $newStatus = (string) ($_POST['status'] ?? '');
            if (!in_array($newStatus, $statuses, true)) {
                $errors[] = 'Status pesanan tidak valid.';
            } else {
                $updateStmt = $pdo->prepare('UPDATE orders SET status = :status WHERE id = :id');
                $updateStmt->execute(['status' => $newStatus, 'id' => $orderId]);
                redirectTo('/admin/orders.php?success=status');
            }
        } elseif ($action === 'payment' && $orderId > 0) {
            $paymentStatus = (string) ($_POST['payment_status'] ?? '');
            if (!isset($paymentStatuses[$paymentStatus])) {
                $errors[] = 'Status pembayaran tidak valid.';
            } else {
                $orderStmt = $pdo->prepare('SELECT id FROM orders WHERE id = :id LIMIT 1');
                $orderStmt->execute(['id' => $orderId]);
                if (!$orderStmt->fetch()) {
                    $errors[] = 'Pesanan tidak ditemukan.';
                } else {
                    $paymentStmt = $pdo->prepare('SELECT id FROM payments WHERE order_id = :id ORDER BY id DESC LIMIT 1');
                    $paymentStmt->execute(['id' => $orderId]);
                    $payment = $paymentStmt->fetch();
                    if ($payment) {
                        $savePayment = $pdo->prepare('UPDATE payments SET payment_status = :status WHERE id = :id');
                        $savePayment->execute(['status' => $paymentStatus, 'id' => $payment['id']]);
                    } else {
                        $savePayment = $pdo->prepare('INSERT INTO payments (order_id, payment_method, payment_status) VALUES (:order_id, :method, :status)');
                        $savePayment->execute(['order_id' => $orderId, 'method' => 'cash', 'status' => $paymentStatus]);
                    }
                    redirectTo('/admin/orders.php?success=payment');
                }
            }
        } elseif ($action === 'cancel' && $orderId > 0) {
            $cancelStmt = $pdo->prepare("UPDATE orders SET status = 'dibatalkan' WHERE id = :id AND status NOT IN ('selesai', 'pesanan_selesai', 'dibatalkan')");
            $cancelStmt->execute(['id' => $orderId]);
            if ($cancelStmt->rowCount() > 0) {
                redirectTo('/admin/orders.php?success=cancelled');
            }
            $errors[] = 'Order tidak ditemukan atau sudah selesai/dibatalkan.';
        }
    }
}

if (($_GET['success'] ?? '') === 'status') $notice = 'Status pesanan berhasil diperbarui.';
if (($_GET['success'] ?? '') === 'payment') $notice = 'Status pembayaran berhasil diperbarui.';
if (($_GET['success'] ?? '') === 'cancelled') $notice = 'Pesanan dibatalkan; riwayat order dan pembayaran tetap disimpan.';
$filterStatus = (string) ($_GET['status'] ?? '');
$filterSql = '';
$params = [];
if ($filterStatus !== '' && in_array($filterStatus, $statuses, true)) {
    $filterSql = ' WHERE o.status = :status';
    $params['status'] = $filterStatus;
}
$ordersStmt = $pdo->prepare(
    "SELECT o.id, o.order_number, o.order_date, o.status, o.weight, o.total_amount, u.name AS customer_name, u.email AS customer_email, s.name AS service_name,
        COALESCE((SELECT p.payment_status FROM payments p WHERE p.order_id = o.id ORDER BY p.id DESC LIMIT 1), 'pending') AS payment_status
     FROM orders o INNER JOIN users u ON u.id = o.user_id INNER JOIN services s ON s.id = o.service_id"
     . $filterSql . ' ORDER BY o.order_date DESC'
);
$ordersStmt->execute($params);
$orders = $ordersStmt->fetchAll();

$detailId = filter_input(INPUT_GET, 'detail', FILTER_VALIDATE_INT) ?: 0;
$detail = null;
if ($detailId > 0) {
    $detailStmt = $pdo->prepare('SELECT o.*, u.name AS customer_name, u.email AS customer_email, u.phone AS customer_phone, s.name AS service_name, s.unit, p.payment_method, p.payment_status FROM orders o INNER JOIN users u ON u.id = o.user_id INNER JOIN services s ON s.id = o.service_id LEFT JOIN payments p ON p.order_id = o.id WHERE o.id = :id LIMIT 1');
    $detailStmt->execute(['id' => $detailId]);
    $detail = $detailStmt->fetch();
}

$statusLabels = array_combine($statuses, array_map(static fn (string $status): string => ucwords(str_replace('_', ' ', $status)), $statuses));
renderAdminHeader('orders', 'Kelola Pesanan');
?>
<section class="page-heading py-4"><div class="container"><h1 class="h3 fw-bold mb-1">Kelola pesanan</h1><p class="text-secondary mb-0">Periksa transaksi customer dan perbarui progres laundry.</p></div></section>
<div class="container py-4">
    <?php if ($notice): ?><div class="alert alert-success"><?= htmlspecialchars($notice) ?></div><?php endif; ?>
    <?php if ($errors): ?><div class="alert alert-danger"><ul class="mb-0"><?php foreach ($errors as $error): ?><li><?= htmlspecialchars($error) ?></li><?php endforeach; ?></ul></div><?php endif; ?>
    <?php if ($detail): ?>
        <section class="panel p-4 mb-4">
            <div class="d-flex flex-wrap justify-content-between align-items-start gap-3 mb-3"><div><small class="text-secondary">Detail pesanan</small><h2 class="h5 fw-bold mb-0"><?= htmlspecialchars($detail['order_number']) ?></h2></div><a class="btn btn-sm btn-outline-secondary" href="<?= htmlspecialchars(appUrlPath('/admin/orders.php' . ($filterStatus !== '' ? '?status=' . urlencode($filterStatus) : ''))) ?>">Tutup detail</a></div>
            <div class="row g-3">
                <div class="col-sm-6 col-lg-3"><small class="d-block text-secondary">Customer</small><strong><?= htmlspecialchars($detail['customer_name']) ?></strong><small class="d-block text-secondary"><?= htmlspecialchars($detail['customer_email']) ?> · <?= htmlspecialchars($detail['customer_phone']) ?></small></div>
                <div class="col-sm-6 col-lg-3"><small class="d-block text-secondary">Layanan & berat</small><strong><?= htmlspecialchars($detail['service_name']) ?></strong><small class="d-block text-secondary"><?= number_format((float) $detail['weight'], 1, ',', '.') ?> <?= htmlspecialchars($detail['unit']) ?></small></div>
                <div class="col-sm-6 col-lg-3"><small class="d-block text-secondary">Pembayaran</small><strong><?= htmlspecialchars(ucfirst($detail['payment_method'] ?? '-')) ?> · <?= htmlspecialchars(ucfirst($detail['payment_status'] ?? '-')) ?></strong></div>
                <div class="col-sm-6 col-lg-3"><small class="d-block text-secondary">Total</small><strong>Rp<?= number_format((float) $detail['total_amount'], 0, ',', '.') ?></strong></div>
                <div class="col-sm-6"><small class="d-block text-secondary">Alamat pickup</small><strong><?= $detail['pickup_address'] ? nl2br(htmlspecialchars($detail['pickup_address'])) : '-' ?></strong></div>
                <div class="col-sm-6"><small class="d-block text-secondary">Alamat delivery</small><strong><?= $detail['delivery_address'] ? nl2br(htmlspecialchars($detail['delivery_address'])) : '-' ?></strong></div>
                <div class="col-12"><small class="d-block text-secondary">Catatan</small><strong><?= $detail['notes'] ? nl2br(htmlspecialchars($detail['notes'])) : '-' ?></strong></div>
            </div>
        </section>
    <?php elseif ($detailId > 0): ?>
        <div class="alert alert-warning">Pesanan tidak ditemukan.</div>
    <?php endif; ?>
    <section class="panel p-3 p-lg-4">
        <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3"><div><h2 class="h5 fw-bold mb-1">Daftar pesanan</h2><small class="text-secondary"><?= count($orders) ?> pesanan</small></div><form method="get" class="d-flex gap-2"><select class="form-select" name="status" aria-label="Filter status"><option value="">Semua status</option><?php foreach ($statusLabels as $value => $label): ?><option value="<?= htmlspecialchars($value) ?>" <?= $filterStatus === $value ? 'selected' : '' ?>><?= htmlspecialchars($label) ?></option><?php endforeach; ?></select><button class="btn btn-outline-primary">Filter</button></form></div>
        <div class="table-responsive"><table class="table align-middle"><thead><tr><th>Order</th><th>Customer</th><th>Layanan</th><th>Tanggal</th><th>Total</th><th>Status</th><th>Pembayaran</th><th>Aksi</th></tr></thead><tbody>
            <?php foreach ($orders as $order): ?><tr>
                <td><a class="fw-bold" href="<?= htmlspecialchars(appUrlPath('/admin/orders.php?detail=' . (int) $order['id'] . ($filterStatus !== '' ? '&status=' . urlencode($filterStatus) : ''))) ?>"><?= htmlspecialchars($order['order_number']) ?></a></td>
                <td><?= htmlspecialchars($order['customer_name']) ?><small class="d-block text-secondary"><?= htmlspecialchars($order['customer_email']) ?></small></td>
                <td><?= htmlspecialchars($order['service_name']) ?><small class="d-block text-secondary"><?= number_format((float) $order['weight'], 1, ',', '.') ?> kg</small></td>
                <td><?= date('d M Y', strtotime($order['order_date'])) ?></td><td>Rp<?= number_format((float) $order['total_amount'], 0, ',', '.') ?></td>
                <td><span class="badge <?= in_array($order['status'], ['selesai', 'pesanan_selesai'], true) ? 'text-bg-success' : ($order['status'] === 'dibatalkan' ? 'text-bg-secondary' : 'badge-soft') ?>"><?= htmlspecialchars($statusLabels[$order['status']] ?? $order['status']) ?></span></td>
                <td><form method="post" class="d-flex gap-1"><input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['admin_csrf_token']) ?>"><input type="hidden" name="action" value="payment"><input type="hidden" name="id" value="<?= (int) $order['id'] ?>"><select class="form-select form-select-sm" name="payment_status" aria-label="Pembayaran order <?= htmlspecialchars($order['order_number']) ?>"><?php foreach ($paymentStatuses as $value => $label): ?><option value="<?= htmlspecialchars($value) ?>" <?= $order['payment_status'] === $value ? 'selected' : '' ?>><?= htmlspecialchars($label) ?></option><?php endforeach; ?></select><button class="btn btn-sm btn-outline-success" aria-label="Simpan pembayaran"><i class="fa-solid fa-check"></i></button></form></td>
                <td><div class="d-flex flex-column flex-sm-row gap-2">
                    <form method="post" class="d-flex gap-1"><input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['admin_csrf_token']) ?>"><input type="hidden" name="action" value="status"><input type="hidden" name="id" value="<?= (int) $order['id'] ?>"><select class="form-select form-select-sm" name="status" aria-label="Status order <?= htmlspecialchars($order['order_number']) ?>"><?php foreach ($statusLabels as $value => $label): ?><option value="<?= htmlspecialchars($value) ?>" <?= $order['status'] === $value ? 'selected' : '' ?>><?= htmlspecialchars($label) ?></option><?php endforeach; ?></select><button class="btn btn-sm btn-primary" aria-label="Simpan status"><i class="fa-solid fa-check"></i></button></form>
                    <?php if (!in_array($order['status'], ['selesai', 'pesanan_selesai', 'dibatalkan'], true)): ?><form method="post" onsubmit="return confirm('Batalkan pesanan ini? Riwayat tetap disimpan.')"><input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['admin_csrf_token']) ?>"><input type="hidden" name="action" value="cancel"><input type="hidden" name="id" value="<?= (int) $order['id'] ?>"><button class="btn btn-sm btn-outline-danger" aria-label="Batalkan pesanan"><i class="fa-solid fa-ban"></i></button></form><?php endif; ?>
                </div></td>
            </tr><?php endforeach; ?>
            <?php if (!$orders): ?><tr><td colspan="8" class="text-center text-secondary py-4">Belum ada pesanan.</td></tr><?php endif; ?>
        </tbody></table></div>
    </section>
</div>
<?php renderAdminFooter(); ?>