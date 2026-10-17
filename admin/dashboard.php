<?php
require_once __DIR__ . '/../config/auth.php';
requireRole(['admin']);

$user = currentUser() ?? ['name' => 'Admin'];
$stats = $pdo->query(
    "SELECT COUNT(*) AS total,
        SUM(status = 'menunggu_konfirmasi') AS waiting,
        SUM(status IN ('laundry_diterima', 'diproses')) AS processing,
        SUM(status IN ('selesai', 'pesanan_selesai')) AS completed
     FROM orders"
)->fetch() ?: ['total' => 0, 'waiting' => 0, 'processing' => 0, 'completed' => 0];
$recentOrders = $pdo->query(
    'SELECT o.id, o.order_number, o.status, u.name AS customer_name, s.name AS service_name
     FROM orders o INNER JOIN users u ON u.id = o.user_id
     INNER JOIN services s ON s.id = o.service_id
     ORDER BY o.order_date DESC LIMIT 5'
)->fetchAll() ?: [];
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">
    <style>
        body {
            background: #f5f8fb;
            font-family: Arial, sans-serif;
            color: #1a2d3f;
        }
        .sidebar {
            background: #102b3e;
            min-height: 100vh;
            color: white;
            padding: 2rem 1.2rem;
        }
        .brand {
            display: flex;
            align-items: center;
            gap: 0.8rem;
            font-weight: 800;
            font-size: 2rem;
            margin-bottom: 2rem;
        }
        .brand-mark {
            width: 42px;
            height: 42px;
            border-radius: 16px;
            background: linear-gradient(135deg, #0ea5e9, #0c4d79);
            color: white;
            display: inline-flex;
            align-items: center;
            justify-content: center;
        }
        .nav-link {
            color: rgba(255,255,255,0.9);
            font-weight: 600;
            border-radius: 12px;
            padding: 0.85rem 1rem;
        }
        .nav-link.active {
            background: rgba(255,255,255,0.12);
            color: white;
        }
        .content {
            padding: 2rem;
        }
        .topbar {
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 1rem;
            margin-bottom: 2rem;
        }
        .topbar h2 {
            font-weight: 800;
            margin: 0;
        }
        .topbar small {
            color: #5f7181;
        }
        .profile-pill {
            background: #fff;
            border: 1px solid #e5edf2;
            border-radius: 14px;
            padding: 0.5rem 0.85rem;
            display: flex;
            align-items: center;
            gap: 0.7rem;
        }
        .avatar {
            width: 38px;
            height: 38px;
            border-radius: 50%;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            background: #dff5ff;
            color: #0d7ec9;
            font-weight: 800;
        }
        .card {
            border: 1px solid #e5edf2;
            border-radius: 24px;
            box-shadow: 0 12px 28px rgba(15, 62, 90, 0.04);
            background: #fff;
        }
        .stat-box {
            padding: 1.5rem;
        }
        .stat-box h3 {
            font-size: 2rem;
            font-weight: 800;
        }
        .status-badge {
            display: inline-block;
            background: #eafaf3;
            color: #169c67;
            padding: 0.45rem 0.7rem;
            border-radius: 999px;
            font-size: 0.8rem;
            font-weight: 700;
        }
    </style>
</head>
<body>
    <div class="container-fluid">
        <div class="row">
            <aside class="col-lg-3 sidebar">
                <div class="brand">
                    <span class="brand-mark"><i class="fa-solid fa-droplet"></i></span>
                    <span>CleanWash</span>
                </div>

                <nav class="nav flex-column gap-2">
                    <a class="nav-link active" href="<?= htmlspecialchars(appUrlPath('/admin/dashboard.php')) ?>">Dashboard</a>
                    <a class="nav-link" href="<?= htmlspecialchars(appUrlPath('/admin/orders.php')) ?>">Pesanan</a>
                    <a class="nav-link" href="<?= htmlspecialchars(appUrlPath('/admin/services.php')) ?>">Layanan</a>
                    <a class="nav-link" href="<?= htmlspecialchars(appUrlPath('/admin/customers.php')) ?>">Pelanggan</a>
                    <a class="nav-link" href="<?= htmlspecialchars(appUrlPath('/admin/reports.php')) ?>">Laporan</a>
                    <a class="nav-link text-white-50" href="<?= htmlspecialchars(appUrlPath('/pages/logout.php')) ?>">Logout</a>
                </nav>
            </aside>

            <main class="col-lg-9 content">
                <div class="topbar">
                    <div>
                        <h2>Admin Dashboard</h2>
                        <small>Welcome back, <?= htmlspecialchars($user['name'] ?? 'Admin') ?>.</small>
                    </div>

                    <div class="profile-pill">
                        <div class="avatar"><?= strtoupper(substr($user['name'] ?? 'Admin', 0, 2)) ?></div>
                        <span>Admin</span>
                    </div>
                </div>

                <div class="row g-4">
                    <div class="col-md-3">
                        <div class="card stat-box">
                            <small class="text-muted">Total Pesanan</small>
                            <h3><?= (int) ($stats['total'] ?? 0) ?></h3>
                            <span class="status-badge">Live</span>
                        </div>
                    </div>

                    <div class="col-md-3">
                        <div class="card stat-box">
                            <small class="text-muted">Menunggu</small>
                            <h3><?= (int) ($stats['waiting'] ?? 0) ?></h3>
                            <span class="status-badge">New</span>
                        </div>
                    </div>

                    <div class="col-md-3">
                        <div class="card stat-box">
                            <small class="text-muted">Diproses</small>
                            <h3><?= (int) ($stats['processing'] ?? 0) ?></h3>
                            <span class="status-badge">Active</span>
                        </div>
                    </div>

                    <div class="col-md-3">
                        <div class="card stat-box">
                            <small class="text-muted">Selesai</small>
                            <h3><?= (int) ($stats['completed'] ?? 0) ?></h3>
                            <span class="status-badge">Done</span>
                        </div>
                    </div>
                </div>

                <div class="card mt-4 p-4">
                    <h4 class="fw-bold">Pesanan terbaru</h4>
                    <div class="table-responsive mt-3">
                        <table class="table align-middle">
                            <thead>
                                <tr>
                                    <th>ID</th>
                                    <th>Pelanggan</th>
                                    <th>Layanan</th>
                                    <th>Status</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($recentOrders as $order): ?>
                                    <tr>
                                        <td><a href="<?= htmlspecialchars(appUrlPath('/admin/orders.php') . '?detail=' . (int) $order['id']) ?>"><?= htmlspecialchars($order['order_number']) ?></a></td>
                                        <td><?= htmlspecialchars($order['customer_name']) ?></td>
                                        <td><?= htmlspecialchars($order['service_name']) ?></td>
                                        <td><span class="status-badge"><?= htmlspecialchars(ucwords(str_replace('_', ' ', $order['status']))) ?></span></td>
                                    </tr>
                                <?php endforeach; ?>
                                <?php if (!$recentOrders): ?>
                                    <tr><td colspan="4" class="text-center text-muted">Belum ada pesanan.</td></tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </main>
        </div>
    </div>
</body>
</html>
