<?php
require_once __DIR__ . '/_auth.php';
require_once __DIR__ . '/_layout.php';

$presets = ['today', 'week', 'month', 'year', 'custom'];
$preset = (string) ($_GET['range'] ?? 'month');
if (!in_array($preset, $presets, true)) {
    $preset = 'month';
}

$today = new DateTimeImmutable('today');
$startDate = $today;
$endDate = $today;
if ($preset === 'week') {
    $startDate = $today->modify('monday this week');
    $endDate = $startDate->modify('+6 days');
} elseif ($preset === 'month') {
    $startDate = $today->modify('first day of this month');
    $endDate = $today->modify('last day of this month');
} elseif ($preset === 'year') {
    $startDate = $today->setDate((int) $today->format('Y'), 1, 1);
    $endDate = $today->setDate((int) $today->format('Y'), 12, 31);
} elseif ($preset === 'custom') {
    $startInput = (string) ($_GET['start_date'] ?? '');
    $endInput = (string) ($_GET['end_date'] ?? '');
    $parsedStart = DateTimeImmutable::createFromFormat('!Y-m-d', $startInput);
    $parsedEnd = DateTimeImmutable::createFromFormat('!Y-m-d', $endInput);
    if ($parsedStart && $parsedEnd && $parsedStart->format('Y-m-d') === $startInput && $parsedEnd->format('Y-m-d') === $endInput && $parsedStart <= $parsedEnd) {
        $startDate = $parsedStart;
        $endDate = $parsedEnd;
    } else {
        $preset = 'month';
        $startDate = $today->modify('first day of this month');
        $endDate = $today->modify('last day of this month');
    }
}

$statusFilter = (string) ($_GET['order_status'] ?? 'all');
if (!in_array($statusFilter, ['all', 'completed', 'cancelled'], true)) {
    $statusFilter = 'all';
}
$serviceId = filter_var($_GET['service_id'] ?? '', FILTER_VALIDATE_INT);
if (!$serviceId || $serviceId < 1) {
    $serviceId = null;
}

$services = $pdo->query('SELECT id, name, unit FROM services ORDER BY name')->fetchAll();
$validServiceIds = array_map(static fn (array $service): int => (int) $service['id'], $services);
if ($serviceId !== null && !in_array($serviceId, $validServiceIds, true)) {
    $serviceId = null;
}

$where = ['o.order_date >= :start_at', 'o.order_date < :end_at'];
$params = [
    'start_at' => $startDate->format('Y-m-d 00:00:00'),
    'end_at' => $endDate->modify('+1 day')->format('Y-m-d 00:00:00'),
];
if ($statusFilter === 'completed') {
    $where[] = "o.status IN ('selesai', 'pesanan_selesai')";
} elseif ($statusFilter === 'cancelled') {
    $where[] = "o.status = 'dibatalkan'";
}
if ($serviceId !== null) {
    $where[] = 'o.service_id = :service_id';
    $params['service_id'] = $serviceId;
}
$whereSql = implode(' AND ', $where);
$paymentsJoin = "LEFT JOIN (
    SELECT order_id,
        CASE WHEN MAX(payment_status = 'paid') = 1 THEN 'paid' ELSE COALESCE(MAX(payment_status), 'pending') END AS payment_status,
        MAX(payment_method) AS payment_method
    FROM payments GROUP BY order_id
) p ON p.order_id = o.id";
$baseFrom = 'FROM orders o INNER JOIN users u ON u.id = o.user_id INNER JOIN services s ON s.id = o.service_id ' . $paymentsJoin . ' WHERE ' . $whereSql;

$summaryStmt = $pdo->prepare(
    "SELECT COUNT(*) AS total_orders,
        COALESCE(SUM(CASE WHEN o.status IN ('selesai', 'pesanan_selesai') THEN o.weight ELSE 0 END), 0) AS processed_weight,
        COALESCE(SUM(CASE WHEN o.status IN ('selesai', 'pesanan_selesai') AND p.payment_status = 'paid' THEN 1 ELSE 0 END), 0) AS paid_completed_count,
        COALESCE(SUM(CASE WHEN o.status IN ('selesai', 'pesanan_selesai') AND p.payment_status = 'paid' THEN o.total_amount ELSE 0 END), 0) AS revenue
     " . $baseFrom
);
$summaryStmt->execute($params);
$summary = $summaryStmt->fetch() ?: ['total_orders' => 0, 'processed_weight' => 0, 'paid_completed_count' => 0, 'revenue' => 0];
$averageValue = (int) $summary['paid_completed_count'] > 0
    ? (float) $summary['revenue'] / (int) $summary['paid_completed_count']
    : 0;

$ordersStmt = $pdo->prepare(
    "SELECT o.order_number, o.order_date, o.status, o.weight, o.quantity, o.total_amount,
        u.name AS customer_name, s.name AS service_name, s.unit,
        COALESCE(p.payment_status, 'pending') AS payment_status
     " . $baseFrom . ' ORDER BY o.order_date DESC'
);
$ordersStmt->execute($params);
$orders = $ordersStmt->fetchAll();

$rangeDays = (int) $startDate->diff($endDate)->days;
$groupExpression = $rangeDays <= 62 ? 'DATE(o.order_date)' : "DATE_FORMAT(o.order_date, '%Y-%m-01')";
$revenueChartStmt = $pdo->prepare(
    "SELECT {$groupExpression} AS period,
        SUM(CASE WHEN o.status IN ('selesai', 'pesanan_selesai') AND p.payment_status = 'paid' THEN o.total_amount ELSE 0 END) AS revenue
     " . $baseFrom . " GROUP BY period ORDER BY period"
);
$revenueChartStmt->execute($params);
$revenueRows = $revenueChartStmt->fetchAll();
$revenueLabels = array_map(static function (array $row) use ($rangeDays): string {
    $date = new DateTimeImmutable($row['period']);
    return $rangeDays <= 62 ? $date->format('d M') : $date->format('M Y');
}, $revenueRows);
$revenueValues = array_map(static fn (array $row): float => (float) $row['revenue'], $revenueRows);

$serviceChartStmt = $pdo->prepare(
    "SELECT s.name, SUM(o.total_amount) AS revenue
     " . $baseFrom . " AND o.status IN ('selesai', 'pesanan_selesai') AND p.payment_status = 'paid'
     GROUP BY s.id, s.name ORDER BY revenue DESC"
);
$serviceChartStmt->execute($params);
$serviceRows = $serviceChartStmt->fetchAll();
$serviceLabels = array_column($serviceRows, 'name');
$serviceValues = array_map(static fn (array $row): float => (float) $row['revenue'], $serviceRows);

$topCustomersStmt = $pdo->prepare(
    "SELECT u.name, COUNT(*) AS order_count,
        SUM(CASE WHEN o.status IN ('selesai', 'pesanan_selesai') AND p.payment_status = 'paid' THEN o.total_amount ELSE 0 END) AS revenue
     " . $baseFrom . ' GROUP BY u.id, u.name ORDER BY revenue DESC, order_count DESC LIMIT 5'
);
$topCustomersStmt->execute($params);
$topCustomers = $topCustomersStmt->fetchAll();

$serviceRecapStmt = $pdo->prepare(
    "SELECT s.name, s.unit,
        SUM(CASE WHEN o.status IN ('selesai', 'pesanan_selesai') THEN o.weight ELSE 0 END) AS processed_weight,
        SUM(CASE WHEN o.status IN ('selesai', 'pesanan_selesai') AND p.payment_status = 'paid' THEN o.total_amount ELSE 0 END) AS revenue
     " . $baseFrom . ' GROUP BY s.id, s.name, s.unit ORDER BY processed_weight DESC'
);
$serviceRecapStmt->execute($params);
$serviceRecap = $serviceRecapStmt->fetchAll();

$queryForExport = [
    'range' => $preset,
    'start_date' => $startDate->format('Y-m-d'),
    'end_date' => $endDate->format('Y-m-d'),
    'order_status' => $statusFilter,
];
if ($serviceId !== null) {
    $queryForExport['service_id'] = $serviceId;
}
if (($_GET['export'] ?? '') === 'csv') {
    $filename = 'gween-laundry-report-' . $startDate->format('Ymd') . '-' . $endDate->format('Ymd') . '.csv';
    header('Content-Type: text/csv; charset=UTF-8');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    $output = fopen('php://output', 'wb');
    fwrite($output, "\xEF\xBB\xBF");
    fputcsv($output, ['Kode Order', 'Tanggal Transaksi', 'Nama Pelanggan', 'Jenis Layanan', 'Berat / Qty', 'Total Biaya', 'Status Pesanan', 'Status Bayar']);
    foreach ($orders as $order) {
        $safeCell = static fn (string $value): string => preg_match('/^[=+@\\-]/', $value) ? "'" . $value : $value;
        fputcsv($output, [
            $safeCell((string) $order['order_number']),
            date('d/m/Y H:i', strtotime($order['order_date'])),
            $safeCell((string) $order['customer_name']),
            $safeCell((string) $order['service_name']),
            number_format((float) ($order['weight'] ?: $order['quantity']), 2, '.', '') . ' ' . $order['unit'],
            number_format((float) $order['total_amount'], 2, '.', ''),
            ucwords(str_replace('_', ' ', $order['status'])),
            ucfirst($order['payment_status']),
        ]);
    }
    fclose($output);
    exit;
}

$baseUrl = appUrlPath('/admin/reports.php?' . http_build_query($queryForExport));
$formatCurrency = static fn (float $amount): string => 'Rp' . number_format($amount, 0, ',', '.');
$statusLabels = ['all' => 'Semua', 'completed' => 'Selesai', 'cancelled' => 'Batal'];

renderAdminHeader('reports', 'Laporan');
?>
<style>
    .report-grid { display: grid; grid-template-columns: repeat(4,minmax(0,1fr)); gap: 1rem; }
    .report-metric { padding: 1.2rem; height: 100%; }
    .report-metric span { display: block; color: #607586; font-size: .85rem; }
    .report-metric strong { display: block; font-size: clamp(1.35rem,2.3vw,1.9rem); margin-top: .45rem; overflow-wrap: anywhere; }
    .report-chart { min-height: 310px; position: relative; }
    .chart-panel { height: 100%; padding: 1.25rem; }
    .report-table { min-width: 930px; }
    .print-only { display: none; }
    @media(max-width:991.98px) { .report-grid { grid-template-columns: repeat(2,minmax(0,1fr)); } }
    @media(max-width:575.98px) { .report-grid { grid-template-columns: 1fr 1fr; gap: .65rem; } .report-metric { padding: .9rem; } .report-metric span { font-size: .76rem; } .report-chart { min-height: 260px; } }
    @media print {
        @page { size: landscape; margin: 12mm; }
        body { background: #fff !important; font-size: 10pt; }
        .admin-header, .page-heading, .filter-panel, .screen-only, .btn, form { display: none !important; }
        .print-only { display: block; }
        .container { max-width: none !important; width: 100% !important; }
        .panel { box-shadow: none !important; border: 1px solid #ccd5dc !important; break-inside: avoid; }
        .report-grid { grid-template-columns: repeat(4,1fr); }
        .report-chart { min-height: 230px; }
        .table-responsive { overflow: visible !important; }
        .report-table { min-width: 0; font-size: 8pt; }
        .report-table th, .report-table td { padding: 4px !important; }
    }
</style>
<section class="page-heading py-4 screen-only"><div class="container"><h1 class="h3 fw-bold mb-1">Laporan bisnis</h1><p class="text-secondary mb-0">Pantau pendapatan, operasional, layanan, dan transaksi.</p></div></section>
<div class="container py-4">
    <div class="print-only mb-3"><h1 class="h3 fw-bold">Gween Laundry · Laporan</h1><p>Periode <?= $startDate->format('d/m/Y') ?> – <?= $endDate->format('d/m/Y') ?> · Status <?= htmlspecialchars($statusLabels[$statusFilter]) ?><?= $serviceId ? ' · Layanan ' . htmlspecialchars((string) ($services[array_search($serviceId, $validServiceIds, true)]['name'] ?? '')) : '' ?></p></div>
    <section class="panel filter-panel p-3 p-lg-4 mb-4">
        <form method="get" class="row g-3 align-items-end">
            <div class="col-6 col-lg-2"><label class="form-label fw-semibold" for="range">Rentang waktu</label><select class="form-select" id="range" name="range"><option value="today" <?= $preset === 'today' ? 'selected' : '' ?>>Hari ini</option><option value="week" <?= $preset === 'week' ? 'selected' : '' ?>>Minggu ini</option><option value="month" <?= $preset === 'month' ? 'selected' : '' ?>>Bulan ini</option><option value="year" <?= $preset === 'year' ? 'selected' : '' ?>>Tahun ini</option><option value="custom" <?= $preset === 'custom' ? 'selected' : '' ?>>Custom range</option></select></div>
            <div class="col-6 col-lg-2 custom-date"><label class="form-label fw-semibold" for="start_date">Tanggal mulai</label><input class="form-control" id="start_date" type="date" name="start_date" value="<?= $startDate->format('Y-m-d') ?>"></div>
            <div class="col-6 col-lg-2 custom-date"><label class="form-label fw-semibold" for="end_date">Tanggal selesai</label><input class="form-control" id="end_date" type="date" name="end_date" value="<?= $endDate->format('Y-m-d') ?>"></div>
            <div class="col-6 col-lg-2"><label class="form-label fw-semibold" for="order_status">Status pesanan</label><select class="form-select" id="order_status" name="order_status"><?php foreach ($statusLabels as $value => $label): ?><option value="<?= htmlspecialchars($value) ?>" <?= $statusFilter === $value ? 'selected' : '' ?>><?= htmlspecialchars($label) ?></option><?php endforeach; ?></select></div>
            <div class="col-8 col-lg-2"><label class="form-label fw-semibold" for="service_id">Jenis layanan</label><select class="form-select" id="service_id" name="service_id"><option value="">Semua layanan</option><?php foreach ($services as $service): ?><option value="<?= (int) $service['id'] ?>" <?= $serviceId === (int) $service['id'] ? 'selected' : '' ?>><?= htmlspecialchars($service['name']) ?></option><?php endforeach; ?></select></div>
            <div class="col-4 col-lg-2 d-grid"><button type="submit" class="btn btn-primary"><i class="fa-solid fa-filter me-1"></i> Terapkan</button></div>
            <div class="col-12 d-flex flex-wrap gap-2 justify-content-end screen-only"><a class="btn btn-outline-success" href="<?= htmlspecialchars($baseUrl . '&export=csv') ?>"><i class="fa-solid fa-file-csv me-1"></i> Ekspor Excel / CSV</a><button class="btn btn-outline-danger" type="button" onclick="window.print()"><i class="fa-solid fa-file-pdf me-1"></i> Ekspor PDF</button></div>
        </form>
    </section>

    <section class="report-grid mb-4" aria-label="Ringkasan laporan">
        <div class="panel report-metric"><span>Total pendapatan (lunas)</span><strong><?= $formatCurrency((float) $summary['revenue']) ?></strong></div>
        <div class="panel report-metric"><span>Berat selesai diproses</span><strong><?= number_format((float) $summary['processed_weight'], 1, ',', '.') ?> kg</strong></div>
        <div class="panel report-metric"><span>Transaksi selesai & lunas</span><strong><?= (int) $summary['paid_completed_count'] ?></strong></div>
        <div class="panel report-metric"><span>Rata-rata nilai transaksi</span><strong><?= $formatCurrency($averageValue) ?></strong></div>
    </section>

    <section class="row g-3 mb-4">
        <div class="col-lg-8"><div class="panel chart-panel"><h2 class="h6 fw-bold mb-3">Tren pendapatan lunas</h2><div class="report-chart"><canvas id="revenueChart" aria-label="Grafik pendapatan" role="img"></canvas></div></div></div>
        <div class="col-lg-4"><div class="panel chart-panel"><h2 class="h6 fw-bold mb-3">Kontribusi layanan</h2><div class="report-chart"><canvas id="serviceChart" aria-label="Diagram layanan" role="img"></canvas><?php if (!$serviceRows): ?><p class="text-secondary text-center mt-3">Belum ada transaksi selesai dan lunas pada filter ini.</p><?php endif; ?></div></div></div>
    </section>

    <section class="panel p-3 p-lg-4 mb-4">
        <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3"><div><h2 class="h5 fw-bold mb-1">Detail transaksi</h2><small class="text-secondary"><?= count($orders) ?> transaksi · <?= $startDate->format('d/m/Y') ?> – <?= $endDate->format('d/m/Y') ?></small></div><span class="badge badge-soft">Pendapatan dihitung dari order selesai dan lunas</span></div>
        <div class="table-responsive"><table class="table align-middle report-table"><thead><tr><th>Kode order</th><th>Tanggal transaksi</th><th>Nama pelanggan</th><th>Jenis layanan</th><th>Berat / Qty</th><th>Total biaya</th><th>Status bayar</th></tr></thead><tbody>
            <?php foreach ($orders as $order): ?><tr>
                <td class="fw-semibold"><?= htmlspecialchars($order['order_number']) ?></td><td><?= date('d/m/Y H:i', strtotime($order['order_date'])) ?></td><td><?= htmlspecialchars($order['customer_name']) ?></td><td><?= htmlspecialchars($order['service_name']) ?></td>
                <td><?= number_format((float) ($order['weight'] ?: $order['quantity']), 1, ',', '.') ?> <?= htmlspecialchars($order['unit']) ?></td><td><?= $formatCurrency((float) $order['total_amount']) ?></td>
                <td><span class="badge <?= $order['payment_status'] === 'paid' ? 'text-bg-success' : ($order['payment_status'] === 'refunded' ? 'text-bg-warning' : 'text-bg-secondary') ?>"><?= $order['payment_status'] === 'paid' ? 'Lunas' : htmlspecialchars(ucfirst($order['payment_status'])) ?></span></td>
            </tr><?php endforeach; ?>
            <?php if (!$orders): ?><tr><td colspan="7" class="text-center text-secondary py-4">Tidak ada transaksi pada filter ini.</td></tr><?php endif; ?>
        </tbody></table></div>
    </section>

    <section class="row g-3">
        <div class="col-lg-6"><div class="panel p-3 p-lg-4 h-100"><h2 class="h6 fw-bold mb-3">Top 5 pelanggan</h2><div class="table-responsive"><table class="table"><thead><tr><th>Pelanggan</th><th>Pesanan</th><th>Nilai lunas</th></tr></thead><tbody><?php foreach ($topCustomers as $customer): ?><tr><td><?= htmlspecialchars($customer['name']) ?></td><td><?= (int) $customer['order_count'] ?></td><td><?= $formatCurrency((float) $customer['revenue']) ?></td></tr><?php endforeach; ?><?php if (!$topCustomers): ?><tr><td colspan="3" class="text-secondary text-center">Belum ada data.</td></tr><?php endif; ?></tbody></table></div></div></div>
        <div class="col-lg-6"><div class="panel p-3 p-lg-4 h-100"><h2 class="h6 fw-bold mb-3">Rekap layanan selesai</h2><div class="table-responsive"><table class="table"><thead><tr><th>Layanan</th><th>Berat</th><th>Pendapatan lunas</th></tr></thead><tbody><?php foreach ($serviceRecap as $row): ?><tr><td><?= htmlspecialchars($row['name']) ?></td><td><?= number_format((float) $row['processed_weight'], 1, ',', '.') ?> <?= htmlspecialchars($row['unit']) ?></td><td><?= $formatCurrency((float) $row['revenue']) ?></td></tr><?php endforeach; ?><?php if (!$serviceRecap): ?><tr><td colspan="3" class="text-secondary text-center">Belum ada data.</td></tr><?php endif; ?></tbody></table></div></div></div>
    </section>
</div>
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
    const revenueLabels = <?= json_encode($revenueLabels, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT) ?>;
    const revenueValues = <?= json_encode($revenueValues, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT) ?>;
    const serviceLabels = <?= json_encode($serviceLabels, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT) ?>;
    const serviceValues = <?= json_encode($serviceValues, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT) ?>;
    new Chart(document.getElementById('revenueChart'), {
        type: 'line',
        data: { labels: revenueLabels, datasets: [{ label: 'Pendapatan', data: revenueValues, borderColor: '#0d7ec9', backgroundColor: 'rgba(13,126,201,.12)', fill: true, tension: .32, pointRadius: 3 }] },
        options: { responsive: true, maintainAspectRatio: false, plugins: { legend: { display: false }, tooltip: { callbacks: { label: context => 'Rp' + new Intl.NumberFormat('id-ID').format(context.raw) } } }, scales: { y: { beginAtZero: true, ticks: { callback: value => 'Rp' + new Intl.NumberFormat('id-ID', { notation: 'compact' }).format(value) } } } }
    });
    new Chart(document.getElementById('serviceChart'), {
        type: 'doughnut',
        data: { labels: serviceLabels, datasets: [{ data: serviceValues, backgroundColor: ['#0d7ec9', '#1ec98a', '#f0b44d', '#8b72cf', '#e27070'], borderWidth: 2, borderColor: '#fff' }] },
        options: { responsive: true, maintainAspectRatio: false, plugins: { legend: { position: 'bottom' }, tooltip: { callbacks: { label: context => context.label + ': Rp' + new Intl.NumberFormat('id-ID').format(context.raw) } } } }
    });
    const rangeSelect = document.getElementById('range');
    const customDateFields = document.querySelectorAll('.custom-date');
    const updateCustomDateFields = () => customDateFields.forEach(field => field.hidden = rangeSelect.value !== 'custom');
    rangeSelect.addEventListener('change', updateCustomDateFields);
    updateCustomDateFields();
</script>
<?php renderAdminFooter(); ?>