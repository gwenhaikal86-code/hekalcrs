<?php
function renderAdminHeader(string $activePage, string $pageTitle): void
{
    ?>
    <!DOCTYPE html>
    <html lang="id">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title><?= htmlspecialchars($pageTitle) ?> | CleanWash Admin</title>
        <link rel="preconnect" href="https://fonts.googleapis.com">
        <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
        <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
        <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
        <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">
        <style>
            :root { --primary: #0d7ec9; --primary-dark: #0c4d79; --text: #1b2b3a; --muted: #5f7181; --bg: #f6f8fb; --border: #dfeaf1; }
            body { margin: 0; background: var(--bg); color: var(--text); font-family: Inter, sans-serif; }
            a { text-decoration: none; }
            .admin-header { background: rgba(255,255,255,.97); border-bottom: 1px solid rgba(15,85,124,.08); }
            .brand { color: var(--text); font-size: 1.45rem; font-weight: 800; }
            .brand-mark { width: 40px; height: 40px; display: inline-grid; place-items: center; border-radius: 15px; background: linear-gradient(135deg,#0ea5e9,var(--primary-dark)); color: #fff; }
            .navbar-nav .nav-link { color: #52697a; font-weight: 600; border-radius: 10px; padding: .65rem .85rem; }
            .navbar-nav .nav-link:hover, .navbar-nav .nav-link.active { color: var(--primary); background: #e8f6ff; }
            main { min-height: calc(100vh - 74px); }
            .page-heading { background: linear-gradient(115deg,#eaf8ff 0%,#f3fbff 58%,#e8f8f2 100%); border-bottom: 1px solid #e5f0f3; }
            .panel { background: #fff; border: 1px solid var(--border); border-radius: 16px; box-shadow: 0 12px 28px rgba(16,62,89,.045); }
            .form-control, .form-select { border-color: var(--border); border-radius: 10px; padding: .68rem .8rem; }
            .btn-primary { background: linear-gradient(135deg,var(--primary),#18a0d6); border: 0; border-radius: 10px; font-weight: 700; }
            .table { margin-bottom: 0; }
            .table th { color: #708494; font-size: .76rem; font-weight: 700; text-transform: uppercase; }
            .table td { vertical-align: middle; }
            .badge-soft { background: #e8f6ff; color: #0877b9; }
            @media(max-width:575.98px) { .page-heading .container { align-items: flex-start !important; flex-direction: column; } }
        </style>
    </head>
    <body>
        <header class="admin-header">
            <nav class="navbar navbar-expand-lg container py-3">
                <a class="brand d-flex align-items-center gap-2" href="<?= htmlspecialchars(appUrlPath('/admin/dashboard.php')) ?>"><span class="brand-mark"><i class="fa-solid fa-droplet"></i></span>CleanWash</a>
                <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#adminNavigation" aria-controls="adminNavigation" aria-expanded="false" aria-label="Buka navigasi"><span class="navbar-toggler-icon"></span></button>
                <div class="collapse navbar-collapse" id="adminNavigation">
                    <div class="navbar-nav ms-auto gap-lg-1 mt-3 mt-lg-0">
                        <a class="nav-link <?= $activePage === 'dashboard' ? 'active' : '' ?>" href="<?= htmlspecialchars(appUrlPath('/admin/dashboard.php')) ?>">Dashboard</a>
                        <a class="nav-link <?= $activePage === 'orders' ? 'active' : '' ?>" href="<?= htmlspecialchars(appUrlPath('/admin/orders.php')) ?>">Pesanan</a>
                        <a class="nav-link <?= $activePage === 'services' ? 'active' : '' ?>" href="<?= htmlspecialchars(appUrlPath('/admin/services.php')) ?>">Layanan</a>
                        <a class="nav-link <?= $activePage === 'customers' ? 'active' : '' ?>" href="<?= htmlspecialchars(appUrlPath('/admin/customers.php')) ?>">Pelanggan</a>
                        <a class="nav-link <?= $activePage === 'reports' ? 'active' : '' ?>" href="<?= htmlspecialchars(appUrlPath('/admin/reports.php')) ?>">Laporan</a>
                        <a class="nav-link" href="<?= htmlspecialchars(appUrlPath('/pages/logout.php')) ?>">Keluar</a>
                    </div>
                </div>
            </nav>
        </header>
        <main>
    <?php
}

function renderAdminFooter(): void
{
    ?>
        </main>
        <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    </body>
    </html>
    <?php
}