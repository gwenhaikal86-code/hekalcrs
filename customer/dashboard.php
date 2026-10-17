<?php
require_once __DIR__ . '/../config/auth.php';
requireRole(['customer']);

$user = currentUser();
$view = ($_GET['view'] ?? '') === 'history' ? 'history' : 'dashboard';

$countStmt = $pdo->prepare(
    "SELECT COUNT(*) AS total,
        SUM(CASE WHEN status IN ('menunggu_konfirmasi', 'dikonfirmasi', 'menunggu_pickup', 'laundry_diterima', 'diproses', 'siap_diambil', 'sedang_diantar') THEN 1 ELSE 0 END) AS active,
        SUM(CASE WHEN status IN ('selesai', 'pesanan_selesai') THEN 1 ELSE 0 END) AS completed
    FROM orders WHERE user_id = :user_id"
);
$countStmt->execute(['user_id' => $user['id']]);
$stats = $countStmt->fetch() ?: ['total' => 0, 'active' => 0, 'completed' => 0];

$orderSql = 'SELECT o.id, o.order_number, o.order_date, o.status, o.weight, o.total_amount, s.name AS service_name
    FROM orders o INNER JOIN services s ON s.id = o.service_id
    WHERE o.user_id = :user_id';
if ($view === 'history') {
    $orderSql .= " AND o.status IN ('selesai', 'pesanan_selesai', 'dibatalkan')";
}
$orderSql .= ' ORDER BY o.order_date DESC';
if ($view !== 'history') {
    $orderSql .= ' LIMIT 6';
}
$ordersStmt = $pdo->prepare($orderSql);
$ordersStmt->execute(['user_id' => $user['id']]);
$orders = $ordersStmt->fetchAll();

$profileStmt = $pdo->prepare('SELECT name, email, phone FROM users WHERE id = :user_id LIMIT 1');
$profileStmt->execute(['user_id' => $user['id']]);
$profile = $profileStmt->fetch() ?: $user;

function statusLabel(string $status): string
{
    return ucwords(str_replace('_', ' ', $status));
}

function statusClass(string $status): string
{
    if (in_array($status, ['selesai', 'pesanan_selesai'], true)) {
        return 'status-complete';
    }
    if ($status === 'dibatalkan') {
        return 'status-cancelled';
    }
    return 'status-active';
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $view === 'history' ? 'Riwayat Pesanan' : 'Dashboard' ?> | CleanWash</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">
    <style>
        :root { --primary: #0d7ec9; --primary-dark: #0c4d79; --text: #1b2b3a; --muted: #5f7181; --bg: #f6f8fb; --border: #dfeaf1; --mint: #e4f8f1; --gold: #fff2dc; }
        * { box-sizing: border-box; }
        body { margin: 0; background: var(--bg); color: var(--text); font-family: Inter, sans-serif; }
        a { text-decoration: none; }
        .app-header { background: rgba(255,255,255,.96); border-bottom: 1px solid rgba(15,85,124,.08); }
        .header-inner { min-height: 76px; }
        .brand { color: var(--text); font-size: 1.55rem; font-weight: 800; }
        .brand-mark { width: 40px; height: 40px; display: inline-grid; place-items: center; border-radius: 15px; background: linear-gradient(135deg,#0ea5e9,var(--primary-dark)); color: #fff; box-shadow: 0 8px 18px rgba(12,77,121,.2); }
        .welcome-band { background: linear-gradient(115deg,#eaf8ff 0%,#f3fbff 58%,#e8f8f2 100%); border-bottom: 1px solid #e5f0f3; }
        .welcome-band h1 { font-size: clamp(1.65rem,3vw,2.35rem); font-weight: 800; margin: 0; }
        .welcome-band p { color: var(--muted); margin: .5rem 0 0; }
        .btn-primary { background: linear-gradient(135deg,var(--primary),#18a0d6); border: 0; border-radius: 13px; padding: .78rem 1.1rem; font-weight: 700; box-shadow: 0 10px 20px rgba(13,126,201,.18); }
        .btn-primary:hover { background: var(--primary-dark); }
        .app-layout { display: grid; grid-template-columns: 230px minmax(0,1fr); gap: 2rem; padding-top: 2rem; padding-bottom: 3rem; }
        .side-nav { align-self: start; position: sticky; top: 1rem; }
        .side-nav a { display: flex; gap: .8rem; align-items: center; color: #52697a; font-weight: 600; padding: .8rem .9rem; border-radius: 12px; margin-bottom: .25rem; }
        .side-nav a:hover, .side-nav a.active { background: #e8f6ff; color: var(--primary); }
        .side-nav .logout { color: #b24646; margin-top: 1rem; }
        .section-heading { display: flex; justify-content: space-between; align-items: end; gap: 1rem; margin-bottom: 1rem; }
        .section-heading h2 { font-size: 1.2rem; font-weight: 800; margin: 0; }
        .section-heading p { color: var(--muted); font-size: .9rem; margin: .35rem 0 0; }
        .stat-card, .panel { background: #fff; border: 1px solid var(--border); border-radius: 18px; box-shadow: 0 12px 28px rgba(16,62,89,.045); }
        .stat-card { display: flex; align-items: center; gap: 1rem; padding: 1.25rem; height: 100%; }
        .stat-icon { width: 48px; height: 48px; flex: 0 0 48px; display: grid; place-items: center; border-radius: 15px; color: var(--primary); background: #e7f6ff; font-size: 1.15rem; }
        .stat-icon.mint { background: var(--mint); color: #16815d; }
        .stat-icon.gold { background: var(--gold); color: #ba7915; }
        .stat-card small { color: var(--muted); font-weight: 600; }
        .stat-card strong { display: block; font-size: 1.7rem; line-height: 1.2; margin-top: .2rem; }
        .panel { padding: 1.35rem; }
        .order-table { margin: 0; min-width: 640px; }
        .order-table th { color: #708494; font-size: .76rem; font-weight: 700; text-transform: uppercase; letter-spacing: .04em; border-bottom-color: var(--border); }
        .order-table td { padding-top: 1rem; padding-bottom: 1rem; vertical-align: middle; border-bottom-color: #edf2f5; }
        .order-table tr:last-child td { border-bottom: 0; }
        .order-number { color: var(--primary-dark); font-weight: 700; }
        .status { display: inline-flex; align-items: center; gap: .4rem; border-radius: 999px; padding: .4rem .65rem; font-size: .76rem; font-weight: 700; white-space: nowrap; }
        .status-active { background: #e8f6ff; color: #0877b9; }
        .status-complete { background: #e5f8ef; color: #16815d; }
        .status-cancelled { background: #fff0ef; color: #b24646; }
        .detail-link { color: var(--primary); font-size: .88rem; font-weight: 700; white-space: nowrap; }
        .empty-state { padding: 2.5rem 1rem; text-align: center; color: var(--muted); }
        .empty-state i { font-size: 2rem; color: #7ebbdc; margin-bottom: .8rem; }
        .profile-panel { background: linear-gradient(125deg,#fff 0%,#f1fbff 100%); }
        .profile-avatar { width: 48px; height: 48px; display: grid; place-items: center; border-radius: 50%; color: #0d7ec9; background: #dff5ff; font-weight: 800; }
        .profile-data { display: grid; grid-template-columns: repeat(2,minmax(0,1fr)); gap: 1rem; margin-top: 1.2rem; }
        .profile-data span { display: block; color: var(--muted); font-size: .78rem; margin-bottom: .25rem; }
        .profile-data strong { overflow-wrap: anywhere; }
        @media (max-width: 991.98px) { .app-layout { grid-template-columns: minmax(0,1fr); gap: 1rem; } .app-layout > div, .side-nav { min-width: 0; width: 100%; } .side-nav { position: static; display: flex; overflow-x: auto; gap: .3rem; padding-bottom: .25rem; } .side-nav a { flex: 0 0 auto; margin: 0; } .side-nav .logout { margin: 0; } }
        @media (max-width: 575.98px) { .header-inner { min-height: 66px; gap: .5rem; } .header-inner .brand { font-size: 1.2rem; white-space: nowrap; } .header-inner .brand-mark { width: 36px; height: 36px; flex: 0 0 36px; } .header-inner .btn { padding: .6rem .65rem; font-size: .78rem; white-space: nowrap; } .welcome-inner { align-items: flex-start !important; flex-direction: column; } .welcome-inner .btn { width: 100%; } .app-layout { padding-top: 1.2rem; } .panel { padding: 1rem; } .stat-card { padding: 1rem; } .section-heading { align-items: flex-start; } .profile-data { grid-template-columns: 1fr; } }
    </style>
</head>
<body>
    <header class="app-header">
        <div class="container header-inner d-flex align-items-center justify-content-between">
            <a class="brand d-flex align-items-center gap-2" href="<?= htmlspecialchars(appUrlPath('/customer/dashboard.php')) ?>">
                <span class="brand-mark"><i class="fa-solid fa-droplet"></i></span><span>CleanWash</span>
            </a>
            <a class="btn btn-primary" href="<?= htmlspecialchars(appUrlPath('/customer/order.php')) ?>"><i class="fa-solid fa-plus me-2"></i>Buat pesanan</a>
        </div>
    </header>
    <section class="welcome-band py-4">
        <div class="container welcome-inner d-flex align-items-center justify-content-between gap-3">
            <div>
                <h1><?= $view === 'history' ? 'Riwayat pesanan' : 'Halo, ' . htmlspecialchars($profile['name']) ?></h1>
                <p><?= $view === 'history' ? 'Lihat kembali transaksi laundry Anda.' : 'Pantau pesanan laundry dan kelola akun Anda.' ?></p>
            </div>
            <?php if ($view === 'history'): ?>
                <a href="<?= htmlspecialchars(appUrlPath('/customer/dashboard.php')) ?>" class="btn btn-outline-primary rounded-3 px-3"><i class="fa-solid fa-arrow-left me-2"></i>Kembali ke dashboard</a>
            <?php endif; ?>
        </div>
    </section>
    <main class="container app-layout">
        <nav class="side-nav" aria-label="Navigasi customer">
            <a class="<?= $view === 'dashboard' ? 'active' : '' ?>" href="<?= htmlspecialchars(appUrlPath('/customer/dashboard.php')) ?>"><i class="fa-solid fa-grid-2"></i>Dashboard</a>
            <a href="<?= htmlspecialchars(appUrlPath('/customer/order.php')) ?>"><i class="fa-solid fa-plus"></i>Buat pesanan</a>
            <a class="<?= $view === 'history' ? 'active' : '' ?>" href="<?= htmlspecialchars(appUrlPath('/customer/dashboard.php?view=history')) ?>"><i class="fa-solid fa-clock-rotate-left"></i>Riwayat pesanan</a>
            <a href="<?= htmlspecialchars(appUrlPath('/customer/profile.php')) ?>"><i class="fa-regular fa-user"></i>Edit profil</a>
            <a class="logout" href="<?= htmlspecialchars(appUrlPath('/pages/logout.php')) ?>"><i class="fa-solid fa-arrow-right-from-bracket"></i>Keluar</a>
        </nav>
        <div>
            <?php if ($view === 'dashboard'): ?>
                <section class="row g-3 mb-4" aria-label="Ringkasan pesanan">
                    <div class="col-sm-6 col-xl-4"><div class="stat-card"><span class="stat-icon"><i class="fa-solid fa-basket-shopping"></i></span><div><small>Total pesanan</small><strong><?= (int) $stats['total'] ?></strong></div></div></div>
                    <div class="col-sm-6 col-xl-4"><div class="stat-card"><span class="stat-icon gold"><i class="fa-solid fa-spinner"></i></span><div><small>Pesanan aktif</small><strong><?= (int) $stats['active'] ?></strong></div></div></div>
                    <div class="col-sm-6 col-xl-4"><div class="stat-card"><span class="stat-icon mint"><i class="fa-solid fa-circle-check"></i></span><div><small>Pesanan selesai</small><strong><?= (int) $stats['completed'] ?></strong></div></div></div>
                </section>
            <?php endif; ?>
            <section class="panel mb-4">
                <div class="section-heading">
                    <div><h2><?= $view === 'history' ? 'Transaksi selesai' : 'Pesanan terbaru' ?></h2><p><?= $view === 'history' ? 'Pesanan selesai dan dibatalkan.' : 'Detail perkembangan pesanan laundry Anda.' ?></p></div>
                    <?php if ($view === 'dashboard'): ?><a class="detail-link" href="<?= htmlspecialchars(appUrlPath('/customer/dashboard.php?view=history')) ?>">Lihat riwayat <i class="fa-solid fa-arrow-right ms-1"></i></a><?php endif; ?>
                </div>
                <?php if ($orders): ?>
                    <div class="table-responsive"><table class="table order-table">
                        <thead><tr><th>Nomor pesanan</th><th>Layanan</th><th>Tanggal</th><th>Berat</th><th>Status</th><th>Total</th><th></th></tr></thead>
                        <tbody><?php foreach ($orders as $order): ?>
                            <tr>
                                <td><a class="order-number" href="<?= htmlspecialchars(appUrlPath('/customer/order-detail.php') . '?id=' . (int) $order['id']) ?>"><?= htmlspecialchars($order['order_number']) ?></a></td>
                                <td><?= htmlspecialchars($order['service_name']) ?></td><td><?= date('d M Y', strtotime($order['order_date'])) ?></td>
                                <td><?= number_format((float) $order['weight'], 1, ',', '.') ?> kg</td>
                                <td><span class="status <?= statusClass($order['status']) ?>"><?= htmlspecialchars(statusLabel($order['status'])) ?></span></td>
                                <td>Rp<?= number_format((float) $order['total_amount'], 0, ',', '.') ?></td>
                                <td><a class="detail-link" aria-label="Detail <?= htmlspecialchars($order['order_number']) ?>" href="<?= htmlspecialchars(appUrlPath('/customer/order-detail.php') . '?id=' . (int) $order['id']) ?>"><i class="fa-solid fa-chevron-right"></i></a></td>
                            </tr>
                        <?php endforeach; ?></tbody>
                    </table></div>
                <?php else: ?>
                    <div class="empty-state"><i class="fa-solid fa-shirt d-block"></i><strong class="d-block text-dark mb-1">Belum ada pesanan</strong><span><?= $view === 'history' ? 'Pesanan selesai akan muncul di sini.' : 'Mulai dengan membuat pesanan laundry pertama Anda.' ?></span><?php if ($view === 'dashboard'): ?><div class="mt-3"><a href="<?= htmlspecialchars(appUrlPath('/customer/order.php')) ?>" class="btn btn-primary"><i class="fa-solid fa-plus me-2"></i>Buat pesanan</a></div><?php endif; ?></div>
                <?php endif; ?>
            </section>
            <section id="profile" class="panel profile-panel">
                <div class="d-flex align-items-center gap-3"><div class="profile-avatar"><?= htmlspecialchars(strtoupper(substr($profile['name'], 0, 1))) ?></div><div><h2 class="h5 fw-bold mb-1">Profile</h2><p class="text-secondary mb-0">Informasi akun customer</p></div></div>
                <div class="profile-data">
                    <div><span>Nama lengkap</span><strong><?= htmlspecialchars($profile['name']) ?></strong></div>
                    <div><span>Email</span><strong><?= htmlspecialchars($profile['email']) ?></strong></div>
                    <div><span>Nomor telepon</span><strong><?= htmlspecialchars($profile['phone'] ?? 'Belum diisi') ?></strong></div>
                    <div><span>Tipe akun</span><strong>Customer</strong></div>
                </div>
            </section>
        </div>
    </main>
</body>
</html>