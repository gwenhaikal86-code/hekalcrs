<?php
require_once __DIR__ . '/../config/auth.php';
requireRole(['customer']);

$user = currentUser();
$orderId = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
if (!$orderId || $orderId < 1) {
    http_response_code(404);
    exit('Pesanan tidak ditemukan.');
}

$stmt = $pdo->prepare(
    'SELECT o.*, s.name AS service_name, s.unit, p.payment_method, p.payment_status
     FROM orders o
     INNER JOIN services s ON s.id = o.service_id
     LEFT JOIN payments p ON p.order_id = o.id
     WHERE o.id = :order_id AND o.user_id = :user_id
     LIMIT 1'
);
$stmt->execute(['order_id' => $orderId, 'user_id' => $user['id']]);
$order = $stmt->fetch();
if (!$order) {
    http_response_code(404);
    exit('Pesanan tidak ditemukan.');
}

$status = ucwords(str_replace('_', ' ', $order['status']));
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Detail Pesanan | CleanWash</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">
    <style>
        :root { --primary: #0d7ec9; --text: #1b2b3a; --muted: #5f7181; --bg: #f6f8fb; --border: #dfeaf1; }
        body { margin: 0; background: var(--bg); color: var(--text); font-family: Inter, sans-serif; }
        a { text-decoration: none; }
        .header { background: #fff; border-bottom: 1px solid var(--border); }
        .brand { color: var(--text); font-size: 1.55rem; font-weight: 800; }
        .brand-mark { width: 40px; height: 40px; display: inline-grid; place-items: center; border-radius: 15px; color: white; background: linear-gradient(135deg,#0ea5e9,#0c4d79); }
        .hero { background: linear-gradient(115deg,#eaf8ff,#f3fbff 60%,#e8f8f2); padding: 2.4rem 0; }
        .hero h1 { font-weight: 800; font-size: clamp(1.7rem,4vw,2.4rem); }
        .panel { background: #fff; border: 1px solid var(--border); border-radius: 18px; box-shadow: 0 12px 28px rgba(16,62,89,.045); padding: 1.4rem; }
        .detail-grid { display: grid; grid-template-columns: repeat(2,minmax(0,1fr)); gap: 1.3rem; }
        .detail-item span { display: block; color: var(--muted); font-size: .82rem; margin-bottom: .3rem; }
        .detail-item strong { overflow-wrap: anywhere; }
        .status { display: inline-block; padding: .45rem .75rem; background: #e8f6ff; color: #0877b9; border-radius: 999px; font-size: .8rem; font-weight: 700; }
        .price-row { display: flex; justify-content: space-between; gap: 1rem; padding: .65rem 0; border-bottom: 1px solid #edf2f5; }
        .price-row.total { border: 0; padding-top: 1rem; font-size: 1.15rem; font-weight: 800; }
        @media(max-width:575.98px) { .detail-grid { grid-template-columns: 1fr; } .panel { padding: 1rem; } }
    </style>
</head>
<body>
    <header class="header"><div class="container py-3 d-flex justify-content-between align-items-center">
        <a class="brand d-flex align-items-center gap-2" href="<?= htmlspecialchars(appUrlPath('/customer/dashboard.php')) ?>"><span class="brand-mark"><i class="fa-solid fa-droplet"></i></span>CleanWash</a>
        <a href="<?= htmlspecialchars(appUrlPath('/customer/dashboard.php')) ?>" class="btn btn-outline-primary"><i class="fa-solid fa-arrow-left me-2"></i>Dashboard</a>
    </div></header>
    <section class="hero"><div class="container"><h1 class="mb-2">Detail pesanan</h1><p class="text-secondary mb-0">Informasi lengkap untuk pesanan <?= htmlspecialchars($order['order_number']) ?>.</p></div></section>
    <main class="container py-4">
        <div class="row g-4">
            <div class="col-lg-8">
                <section class="panel">
                    <div class="d-flex justify-content-between align-items-start gap-3 mb-4"><div><small class="text-secondary">Nomor pesanan</small><h2 class="h4 fw-bold mt-1 mb-0"><?= htmlspecialchars($order['order_number']) ?></h2></div><span class="status"><?= htmlspecialchars($status) ?></span></div>
                    <div class="detail-grid">
                        <div class="detail-item"><span>Layanan</span><strong><?= htmlspecialchars($order['service_name']) ?></strong></div>
                        <div class="detail-item"><span>Tanggal pesanan</span><strong><?= date('d M Y, H:i', strtotime($order['order_date'])) ?></strong></div>
                        <div class="detail-item"><span>Berat</span><strong><?= number_format((float) $order['weight'], 1, ',', '.') ?> <?= htmlspecialchars($order['unit']) ?></strong></div>
                        <div class="detail-item"><span>Pembayaran</span><strong><?= htmlspecialchars(ucfirst($order['payment_method'] ?? 'Belum tersedia')) ?> · <?= htmlspecialchars(ucfirst($order['payment_status'] ?? 'pending')) ?></strong></div>
                        <div class="detail-item"><span>Alamat penjemputan</span><strong><?= $order['pickup_address'] ? nl2br(htmlspecialchars($order['pickup_address'])) : 'Tidak menggunakan pickup' ?></strong></div>
                        <div class="detail-item"><span>Alamat pengantaran</span><strong><?= $order['delivery_address'] ? nl2br(htmlspecialchars($order['delivery_address'])) : 'Tidak menggunakan delivery' ?></strong></div>
                        <div class="detail-item"><span>Catatan</span><strong><?= $order['notes'] ? nl2br(htmlspecialchars($order['notes'])) : 'Tidak ada catatan' ?></strong></div>
                    </div>
                </section>
            </div>
            <div class="col-lg-4">
                <section class="panel"><h2 class="h5 fw-bold mb-3">Rincian biaya</h2>
                    <div class="price-row"><span>Subtotal layanan</span><strong>Rp<?= number_format((float) $order['subtotal'], 0, ',', '.') ?></strong></div>
                    <div class="price-row"><span>Pickup</span><strong>Rp<?= number_format((float) $order['pickup_fee'], 0, ',', '.') ?></strong></div>
                    <div class="price-row"><span>Delivery</span><strong>Rp<?= number_format((float) $order['delivery_fee'], 0, ',', '.') ?></strong></div>
                    <div class="price-row total"><span>Total</span><span>Rp<?= number_format((float) $order['total_amount'], 0, ',', '.') ?></span></div>
                </section>
            </div>
        </div>
    </main>
</body>
</html>