<?php
require_once __DIR__ . '/../config/auth.php';

if (isLoggedIn()) {
    $redirectPath = (($_SESSION['user_role'] ?? '') === 'admin') ? '/admin/dashboard.php' : '/customer/dashboard.php';
    redirectTo($redirectPath);
}

$errors = [];
$successMessage = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = trim($_POST['name'] ?? '');
    $email = strtolower(trim($_POST['email'] ?? ''));
    $phone = trim($_POST['phone'] ?? '');
    $password = $_POST['password'] ?? '';
    $confirmPassword = $_POST['confirm_password'] ?? '';

    if ($name === '' || $email === '' || $phone === '' || $password === '' || $confirmPassword === '') {
        $errors[] = 'All fields are required.';
    }

    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = 'Please enter a valid email address.';
    }

    if (mb_strlen($name) > 255 || strlen($email) > 255 || strlen($phone) > 50) {
        $errors[] = 'Name, email, or phone exceeds the allowed length.';
    }

    if (strlen($password) < 8) {
        $errors[] = 'Password must be at least 8 characters long.';
    }

    if ($password !== $confirmPassword) {
        $errors[] = 'Passwords do not match.';
    }

    if (empty($errors)) {
        try {
            $checkStmt = $pdo->prepare('SELECT id FROM users WHERE email = :email LIMIT 1');
            $checkStmt->execute(['email' => $email]);

            if ($checkStmt->fetch()) {
                $errors[] = 'This email is already registered.';
            } else {
                $passwordHash = password_hash($password, PASSWORD_DEFAULT);

                $insertStmt = $pdo->prepare(
                    'INSERT INTO users (name, email, phone, password, role) VALUES (:name, :email, :phone, :password, :role)'
                );

                $insertStmt->execute([
                    'name' => $name,
                    'email' => $email,
                    'phone' => $phone,
                    'password' => $passwordHash,
                    'role' => 'customer',
                ]);

                $successMessage = 'Registration successful. Please login with your account.';
            }
        } catch (PDOException $e) {
            error_log('Registration failed: ' . $e->getMessage());
            $errors[] = 'Unable to register your account right now.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Daftar | Gween Laundry</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">
    <style>
        body {
            background: linear-gradient(135deg, #edf9ff, #f5f7fb);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            font-family: Arial, sans-serif;
        }
        .auth-card {
            background: #fff;
            border: 1px solid #dfeaf1;
            border-radius: 24px;
            box-shadow: 0 20px 45px rgba(10, 47, 75, 0.08);
            overflow: hidden;
        }
        .login-panel {
            padding: 2.5rem;
        }
        .brand {
            display: flex;
            align-items: center;
            gap: 0.8rem;
            margin-bottom: 1.5rem;
        }
        .brand-mark {
            width: 44px;
            height: 44px;
            border-radius: 16px;
            background: linear-gradient(135deg, #0ea5e9, #0c4d79);
            color: white;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            font-size: 1.2rem;
        }
        .brand-name {
            font-size: 2rem;
            font-weight: 800;
            color: #1a2d3f;
        }
        .form-control {
            border-radius: 14px;
            border: 1px solid #dfeaf1;
            padding: 0.9rem 1rem;
        }
        .btn-primary {
            background: linear-gradient(135deg, #0d7ec9, #18a0d6);
            border: none;
            border-radius: 14px;
            padding: 0.9rem 1.2rem;
            font-weight: 700;
        }
        .alert {
            border-radius: 14px;
        }
        .muted-link {
            color: #0d7ec9;
            text-decoration: none;
            font-weight: 600;
        }
        .side-panel {
            background: linear-gradient(180deg, #dff8ff, #edf7ff);
            padding: 2.5rem;
            display: flex;
            align-items: center;
            justify-content: center;
            min-height: 100%;
        }
        .side-panel .content {
            text-align: center;
        }
        .side-panel .icon {
            width: 105px;
            height: 105px;
            border-radius: 28px;
            background: rgba(255,255,255,0.6);
            color: #0d7ec9;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            font-size: 3rem;
            margin-bottom: 1rem;
        }
        @media (max-width: 767.98px) {
            .auth-card {
                margin: 1rem;
            }
            .side-panel {
                padding: 2rem 1rem;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="row auth-card mx-auto" style="max-width: 1000px;">
            <div class="col-lg-6 login-panel">
                <div class="brand">
                    <span class="brand-mark"><i class="fa-solid fa-droplet"></i></span>
                    <span class="brand-name">Gween Laundry</span>
                </div>

                <h2 class="fw-bold mb-3">Create account</h2>
                <p class="text-secondary mb-4">Register to order your laundry service.</p>

                <?php if (!empty($errors)): ?>
                    <div class="alert alert-danger">
                        <ul class="mb-0">
                            <?php foreach ($errors as $error): ?>
                                <li><?= htmlspecialchars($error) ?></li>
                            <?php endforeach; ?>
                        </ul>
                    </div>
                <?php endif; ?>

                <?php if ($successMessage !== ''): ?>
                    <div class="alert alert-success"><?= htmlspecialchars($successMessage) ?></div>
                <?php endif; ?>

                <form method="POST" action="register.php">
                    <div class="mb-3">
                        <label for="name" class="form-label">Full Name</label>
                        <input type="text" class="form-control" id="name" name="name" maxlength="255" placeholder="Your name" required>
                    </div>

                    <div class="mb-3">
                        <label for="email" class="form-label">Email</label>
                        <input type="email" class="form-control" id="email" name="email" maxlength="255" placeholder="you@example.com" required>
                    </div>

                    <div class="mb-3">
                        <label for="phone" class="form-label">Phone</label>
                        <input type="tel" class="form-control" id="phone" name="phone" maxlength="50" placeholder="08xxxxxxxxxx" required>
                    </div>

                    <div class="mb-3">
                        <label for="password" class="form-label">Password</label>
                        <input type="password" class="form-control" id="password" name="password" minlength="8" placeholder="Min 8 characters" required>
                    </div>

                    <div class="mb-3">
                        <label for="confirm_password" class="form-label">Confirm Password</label>
                        <input type="password" class="form-control" id="confirm_password" name="confirm_password" placeholder="Repeat password" required>
                    </div>

                    <button type="submit" class="btn btn-primary w-100">Register</button>
                </form>

                <div class="text-center mt-4">
                    <span class="text-secondary">Already have an account?</span>
                    <a href="login.php" class="muted-link">Login here</a>
                </div>
            </div>

            <div class="col-lg-6 side-panel">
                <div class="content">
                    <div class="icon"><i class="fa-solid fa-bag-shopping"></i></div>
                    <h3 class="fw-bold mb-3">Simple laundry order</h3>
                    <p class="text-secondary mb-0">Book pickup, track your order, and enjoy cleaner clothes without the hassle.</p>
                </div>
            </div>
        </div>
    </div>
</body>
</html>
