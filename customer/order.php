<?php
require_once __DIR__ . '/../config/auth.php';
requireRole(['customer']);

$errors = [];
$successMessage = (string) ($_SESSION['order_success'] ?? '');
unset($_SESSION['order_success']);
$selectedServiceId = '';
$selectedWeight = '';
$pickupOption = 0;
$deliveryOption = 0;
$pickupAddress = '';
$deliveryAddress = '';
$notes = '';
$estimatedSubtotal = 0;
$estimatedPickupFee = 0;
$estimatedDeliveryFee = 0;
$estimatedTotal = 0;

try {
    $stmt = $pdo->query('SELECT id, name, description, price, unit, estimated_time FROM services WHERE is_active = 1 ORDER BY price ASC');
    $services = $stmt->fetchAll();
} catch (PDOException $e) {
    $services = [];
    $errors[] = 'Unable to load services right now.';
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $selectedServiceId = (int) ($_POST['service_id'] ?? 0);
    $selectedWeight = trim((string) ($_POST['weight'] ?? ''));
    $pickupOption = isset($_POST['pickup_option']) ? 1 : 0;
    $deliveryOption = isset($_POST['delivery_option']) ? 1 : 0;
    $pickupAddress = trim((string) ($_POST['pickup_address'] ?? ''));
    $deliveryAddress = trim((string) ($_POST['delivery_address'] ?? ''));
    $notes = trim((string) ($_POST['notes'] ?? ''));

    if ($selectedServiceId <= 0) {
        $errors[] = 'Please choose a service.';
    }

    if (
        $selectedWeight === ''
        || !is_numeric($selectedWeight)
        || !is_finite((float) $selectedWeight)
        || (float) $selectedWeight < 0.1
        || (float) $selectedWeight > 99999999.99
    ) {
        $errors[] = 'Weight must be a positive number.';
    }

    if ($pickupOption === 1 && $pickupAddress === '') {
        $errors[] = 'Pickup address is required when pickup is selected.';
    }

    if ($deliveryOption === 1 && $deliveryAddress === '') {
        $errors[] = 'Delivery address is required when delivery is selected.';
    }

    $service = null;
    foreach ($services as $item) {
        if ((int) $item['id'] === $selectedServiceId) {
            $service = $item;
            break;
        }
    }

    if (!$service) {
        $errors[] = 'Selected service is not available.';
    }

    if (empty($errors) && $service) {
        $weightValue = (float) $selectedWeight;
        $servicePrice = (float) $service['price'];
        $estimatedSubtotal = $servicePrice * $weightValue;
        $estimatedPickupFee = $pickupOption ? 5000.00 : 0.00;
        $estimatedDeliveryFee = $deliveryOption ? 5000.00 : 0.00;
        $estimatedTotal = $estimatedSubtotal + $estimatedPickupFee + $estimatedDeliveryFee;

        $orderNumber = 'GL-' . date('Ymd') . '-' . strtoupper(bin2hex(random_bytes(5)));

        try {
            $pdo->beginTransaction();
            $insertOrder = $pdo->prepare(
                'INSERT INTO orders (
                    user_id,
                    service_id,
                    order_number,
                    order_date,
                    status,
                    quantity,
                    weight,
                    pickup_option,
                    delivery_option,
                    pickup_address,
                    delivery_address,
                    notes,
                    subtotal,
                    pickup_fee,
                    delivery_fee,
                    total_amount
                ) VALUES (
                    :user_id,
                    :service_id,
                    :order_number,
                    NOW(),
                    :status,
                    :quantity,
                    :weight,
                    :pickup_option,
                    :delivery_option,
                    :pickup_address,
                    :delivery_address,
                    :notes,
                    :subtotal,
                    :pickup_fee,
                    :delivery_fee,
                    :total_amount
                )'
            );

            $insertOrder->execute([
                'user_id' => (int) $_SESSION['user_id'],
                'service_id' => (int) $selectedServiceId,
                'order_number' => $orderNumber,
                'status' => 'menunggu_konfirmasi',
                'quantity' => $weightValue,
                'weight' => $weightValue,
                'pickup_option' => $pickupOption,
                'delivery_option' => $deliveryOption,
                'pickup_address' => $pickupOption ? $pickupAddress : null,
                'delivery_address' => $deliveryOption ? $deliveryAddress : null,
                'notes' => $notes !== '' ? $notes : null,
                'subtotal' => $estimatedSubtotal,
                'pickup_fee' => $estimatedPickupFee,
                'delivery_fee' => $estimatedDeliveryFee,
                'total_amount' => $estimatedTotal,
            ]);

            $orderId = (int) $pdo->lastInsertId();

            $insertPayment = $pdo->prepare(
                'INSERT INTO payments (order_id, payment_method, payment_status) VALUES (:order_id, :payment_method, :payment_status)'
            );
            $insertPayment->execute([
                'order_id' => $orderId,
                'payment_method' => 'cash',
                'payment_status' => 'pending',
            ]);
            $pdo->commit();

            $_SESSION['order_success'] = 'Pesanan berhasil dibuat. Nomor pesanan Anda: ' . $orderNumber;
            redirectTo('/customer/order.php');
        } catch (PDOException $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            error_log('Order creation failed: ' . $e->getMessage());
            $errors[] = 'Pesanan gagal dibuat. Silakan coba lagi.';
        }
    } else {
        $serviceForEstimate = $service ?? null;
        if ($serviceForEstimate) {
            $weightValue = (float) $selectedWeight;
            $estimatedSubtotal = $serviceForEstimate['price'] * $weightValue;
            $estimatedPickupFee = $pickupOption ? 5000.00 : 0.00;
            $estimatedDeliveryFee = $deliveryOption ? 5000.00 : 0.00;
            $estimatedTotal = $estimatedSubtotal + $estimatedPickupFee + $estimatedDeliveryFee;
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Buat Pesanan | CleanWash</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">
    <style>
        body { background: #f5f8fb; color: #1a2d3f; font-family: Arial, sans-serif; }
        .sidebar { background: #fff; min-height: 100vh; border-right: 1px solid #e5edf2; padding: 2rem 1.2rem; }
        .brand { display: flex; align-items: center; gap: 0.8rem; font-size: 2rem; font-weight: 800; margin-bottom: 2rem; }
        .brand-mark { width: 42px; height: 42px; border-radius: 16px; background: linear-gradient(135deg, #0ea5e9, #0c4d79); color: white; display: inline-flex; align-items: center; justify-content: center; }
        .nav-link { color: #1a2d3f; font-weight: 600; border-radius: 12px; padding: 0.85rem 1rem; }
        .nav-link.active { background: #e7f6ff; color: #0d7ec9; }
        .content { padding: 2rem; }
        .topbar { display: flex; justify-content: space-between; align-items: center; gap: 1rem; margin-bottom: 2rem; }
        .topbar h2 { margin: 0; font-weight: 800; }
        .topbar small { color: #5f7181; }
        .card { background: #fff; border: 1px solid #e5edf2; border-radius: 24px; box-shadow: 0 12px 28px rgba(15,62,90,0.04); }
        .service-option { border: 1px solid #dfeaf1; border-radius: 18px; padding: 1rem; margin-bottom: 1rem; cursor: pointer; transition: 0.2s ease; }
        .service-option.active { border-color: #0d7ec9; background: #eaf7ff; }
        .service-option input { display: none; }
        .weight-box { display: flex; align-items: center; gap: 1rem; border: 1px solid #dfeaf1; border-radius: 16px; padding: 0.6rem 1rem; width: fit-content; }
        .weight-box button { border: none; background: #f1f7fb; width: 40px; height: 40px; border-radius: 12px; font-size: 1.6rem; }
        .weight-box input { width: 86px; border: none; background: transparent; text-align: center; font-size: 2rem; font-weight: 700; }
        .summary-card { padding: 1.5rem; }
        .summary-row, .summary-total { display: flex; justify-content: space-between; align-items: center; gap: 1rem; }
        .summary-row { margin-bottom: 0.75rem; }
        .summary-total { border-top: 1px solid #e5edf2; padding-top: 1rem; margin-top: 1rem; font-size: 1.25rem; font-weight: 800; }
        .form-control, .form-select { border-radius: 14px; border: 1px solid #dfeaf1; padding: 0.8rem 1rem; }
        .check-row { display: flex; align-items: center; gap: 0.75rem; border: 1px solid #dfeaf1; border-radius: 16px; padding: 0.9rem 1rem; margin-bottom: 0.8rem; }
        .btn-primary { background: linear-gradient(135deg, #0d7ec9, #18a0d6); border: none; border-radius: 14px; padding: 0.9rem 1.2rem; font-weight: 700; }
        .alert { border-radius: 14px; }
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
                    <a class="nav-link" href="<?= htmlspecialchars(appUrlPath('/customer/dashboard.php')) ?>">Dashboard</a>
                    <a class="nav-link active" href="<?= htmlspecialchars(appUrlPath('/customer/order.php')) ?>">Buat Pesanan</a>
                    <a class="nav-link" href="<?= htmlspecialchars(appUrlPath('/customer/dashboard.php')) ?>">Pesanan Saya</a>
                    <a class="nav-link" href="<?= htmlspecialchars(appUrlPath('/customer/dashboard.php?view=history')) ?>">Riwayat</a>
                    <a class="nav-link" href="<?= htmlspecialchars(appUrlPath('/customer/profile.php')) ?>">Edit Profil</a>
                    <a class="nav-link text-danger" href="<?= htmlspecialchars(appUrlPath('/pages/logout.php')) ?>">Logout</a>
                </nav>
            </aside>

            <main class="col-lg-9 content">
                <div class="topbar">
                    <div>
                        <h2>Buat pesanan baru</h2>
                        <small>Isi detail cucian Anda, kami akan menjumpainya.</small>
                    </div>
                </div>

                <?php if (!empty($errors)): ?>
                    <div class="alert alert-danger mb-4">
                        <ul class="mb-0">
                            <?php foreach ($errors as $error): ?>
                                <li><?= htmlspecialchars($error) ?></li>
                            <?php endforeach; ?>
                        </ul>
                    </div>
                <?php endif; ?>

                <?php if ($successMessage !== ''): ?>
                    <div class="alert alert-success mb-4"><?= htmlspecialchars($successMessage) ?></div>
                <?php endif; ?>

                <div class="row g-4">
                    <div class="col-lg-8">
                        <form method="POST" action="order.php">
                            <div class="card p-4 mb-4">
                                <div class="d-flex align-items-center gap-3 mb-4">
                                    <span class="badge rounded-circle bg-primary-subtle text-primary" style="width: 36px; height: 36px; display: inline-flex; align-items: center; justify-content: center; font-weight: 700;">1</span>
                                    <div>
                                        <h4 class="mb-0 fw-bold">Pilih jenis layanan</h4>
                                        <small class="text-muted">Pilih layanan yang sesuai kebutuhan</small>
                                    </div>
                                </div>

                                <?php foreach ($services as $service): ?>
                                    <label class="service-option <?= (string) $selectedServiceId === (string) $service['id'] ? 'active' : '' ?>">
                                        <input type="radio" name="service_id" value="<?= (int) $service['id'] ?>" data-service-name="<?= htmlspecialchars($service['name']) ?>" data-service-price="<?= htmlspecialchars((string) $service['price']) ?>" <?= (string) $selectedServiceId === (string) $service['id'] ? 'checked' : '' ?> required>
                                        <div class="d-flex justify-content-between align-items-center gap-2">
                                            <div>
                                                <div class="fw-bold"><?= htmlspecialchars($service['name']) ?></div>
                                                <small class="text-muted"><?= htmlspecialchars((string) ($service['description'] ?? '')) ?></small>
                                            </div>
                                            <div class="fw-bold text-end">
                                                Rp<?= number_format((float) $service['price'], 0, ',', '.') ?> / <?= htmlspecialchars($service['unit']) ?>
                                            </div>
                                        </div>
                                    </label>
                                <?php endforeach; ?>
                            </div>

                            <div class="card p-4 mb-4">
                                <div class="d-flex align-items-center gap-3 mb-4">
                                    <span class="badge rounded-circle bg-primary-subtle text-primary" style="width: 36px; height: 36px; display: inline-flex; align-items: center; justify-content: center; font-weight: 700;">2</span>
                                    <div>
                                        <h4 class="mb-0 fw-bold">Estimasi berat cucian</h4>
                                        <small class="text-muted">Berat dapat dikonfirmasi ulang oleh admin</small>
                                    </div>
                                </div>

                                <div class="weight-box">
                                    <button type="button" id="minusWeight" aria-label="Kurangi berat">−</button>
                                    <input type="number" id="weightInput" name="weight" min="0.1" max="99999999.99" step="0.1" value="<?= htmlspecialchars((string) $selectedWeight !== '' ? $selectedWeight : '5') ?>" required>
                                    <span class="fw-bold">kg</span>
                                    <button type="button" id="plusWeight" aria-label="Tambah berat">+</button>
                                </div>
                            </div>

                            <div class="card p-4 mb-4">
                                <div class="d-flex align-items-center gap-3 mb-4">
                                    <span class="badge rounded-circle bg-primary-subtle text-primary" style="width: 36px; height: 36px; display: inline-flex; align-items: center; justify-content: center; font-weight: 700;">3</span>
                                    <div>
                                        <h4 class="mb-0 fw-bold">Penjemputan & pengantaran</h4>
                                        <small class="text-muted">Pilih layanan dan isi alamat. Jika tidak memilih layanan tambahan, antar dan ambil cucian sendiri.</small>
                                    </div>
                                </div>

                                <div class="check-row">
                                    <input type="checkbox" id="pickup_option" name="pickup_option" value="1" <?= $pickupOption ? 'checked' : '' ?>>
                                    <label for="pickup_option" class="mb-0 fw-semibold">Butuh penjemputan</label>
                                    <span class="ms-auto text-muted">Tambahan Rp5.000</span>
                                </div>

                                <div class="mb-3">
                                    <label class="form-label fw-semibold">Alamat pickup</label>
                                    <textarea class="form-control" name="pickup_address" rows="3" placeholder="Masukkan alamat lengkap untuk pickup"><?= htmlspecialchars($pickupAddress) ?></textarea>
                                </div>

                                <div class="check-row">
                                    <input type="checkbox" id="delivery_option" name="delivery_option" value="1" <?= $deliveryOption ? 'checked' : '' ?>>
                                    <label for="delivery_option" class="mb-0 fw-semibold">Butuh pengantaran</label>
                                    <span class="ms-auto text-muted">Tambahan Rp5.000</span>
                                </div>

                                <div class="mb-3">
                                    <label class="form-label fw-semibold">Alamat delivery</label>
                                    <textarea class="form-control" name="delivery_address" rows="3" placeholder="Masukkan alamat lengkap untuk pengantaran"><?= htmlspecialchars($deliveryAddress) ?></textarea>
                                </div>

                                <div>
                                    <label class="form-label fw-semibold">Catatan</label>
                                    <textarea class="form-control" name="notes" rows="3" placeholder="Tambahkan catatan jika diperlukan"><?= htmlspecialchars($notes) ?></textarea>
                                </div>
                            </div>

                            <button type="submit" class="btn btn-primary w-100">Kirim pesanan</button>
                        </form>
                    </div>

                    <div class="col-lg-4">
                        <div class="card summary-card">
                            <h4 class="fw-bold mb-3">Ringkasan pesanan</h4>
                            <div class="summary-row">
                                <span>Layanan</span>
                                <strong id="summaryService">Belum dipilih</strong>
                            </div>
                            <div class="summary-row">
                                <span>Subtotal</span>
                                <strong id="summarySubtotal">Rp0</strong>
                            </div>
                            <div class="summary-row">
                                <span>Biaya pickup</span>
                                <strong id="summaryPickup">Rp0</strong>
                            </div>
                            <div class="summary-row">
                                <span>Biaya delivery</span>
                                <strong id="summaryDelivery">Rp0</strong>
                            </div>
                            <div class="summary-total">
                                <span>Total</span>
                                <span id="summaryTotal">Rp0</span>
                            </div>
                        </div>
                    </div>
                </div>
            </main>
        </div>
    </div>

    <script>
        const weightInput = document.getElementById('weightInput');
        const minusBtn = document.getElementById('minusWeight');
        const plusBtn = document.getElementById('plusWeight');
        const serviceInputs = document.querySelectorAll('input[name="service_id"]');
        const pickupInput = document.getElementById('pickup_option');
        const deliveryInput = document.getElementById('delivery_option');
        const formatRupiah = amount => 'Rp' + new Intl.NumberFormat('id-ID', {
            maximumFractionDigits: 0
        }).format(amount);

        function updateOrderSummary() {
            const selectedService = document.querySelector('input[name="service_id"]:checked');
            const servicePrice = selectedService ? Number(selectedService.dataset.servicePrice) : 0;
            const weight = Number(weightInput.value) || 0;
            const subtotal = servicePrice * weight;
            const pickupFee = pickupInput.checked ? 5000 : 0;
            const deliveryFee = deliveryInput.checked ? 5000 : 0;

            document.getElementById('summaryService').textContent = selectedService
                ? selectedService.dataset.serviceName
                : 'Belum dipilih';
            document.getElementById('summarySubtotal').textContent = formatRupiah(subtotal);
            document.getElementById('summaryPickup').textContent = formatRupiah(pickupFee);
            document.getElementById('summaryDelivery').textContent = formatRupiah(deliveryFee);
            document.getElementById('summaryTotal').textContent = formatRupiah(subtotal + pickupFee + deliveryFee);

            serviceInputs.forEach(input => input.closest('.service-option').classList.toggle('active', input.checked));
            document.querySelector('textarea[name="pickup_address"]').required = pickupInput.checked;
            document.querySelector('textarea[name="delivery_address"]').required = deliveryInput.checked;
        }

        serviceInputs.forEach(input => input.addEventListener('change', updateOrderSummary));
        weightInput.addEventListener('input', updateOrderSummary);
        pickupInput.addEventListener('change', updateOrderSummary);
        deliveryInput.addEventListener('change', updateOrderSummary);

        minusBtn.addEventListener('click', () => {
            const current = parseFloat(weightInput.value || 0);
            if (current > 0.1) {
                weightInput.value = (current - 0.1).toFixed(1);
                updateOrderSummary();
            }
        });

        plusBtn.addEventListener('click', () => {
            const current = parseFloat(weightInput.value || 0);
            weightInput.value = (current + 0.1).toFixed(1);
            updateOrderSummary();
        });

        updateOrderSummary();
    </script>
</body>
</html>
