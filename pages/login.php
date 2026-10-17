<?php
require_once __DIR__ . '/../config/auth.php';

if (isLoggedIn()) {
    $redirectPath = (($_SESSION['user_role'] ?? '') === 'admin') ? '/admin/dashboard.php' : '/customer/dashboard.php';
    redirectTo($redirectPath);
}

$errors = [];
$successMessage = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = strtolower(trim($_POST['email'] ?? ''));
    $password = $_POST['password'] ?? '';

    if ($email === '' || $password === '') {
        $errors[] = 'Email and password are required.';
    } else {
        try {
            $stmt = $pdo->prepare('SELECT * FROM users WHERE email = :email LIMIT 1');
            $stmt->execute(['email' => $email]);
            $user = $stmt->fetch();

            if ($user && password_verify($password, $user['password'])) {
                session_regenerate_id(true);
                $_SESSION['user_id'] = (int) $user['id'];
                $_SESSION['user_name'] = $user['name'];
                $_SESSION['user_email'] = $user['email'];
                $_SESSION['user_role'] = $user['role'];

                $redirectPath = ($user['role'] === 'admin') ? '/admin/dashboard.php' : '/customer/dashboard.php';
                redirectTo($redirectPath);
            } else {
                $errors[] = 'Invalid email or password.';
            }
        } catch (PDOException $e) {
            error_log('Login failed: ' . $e->getMessage());
            $errors[] = 'Login failed. Please try again later.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login | Gween Laundry</title>
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

                <h2 class="fw-bold mb-3">Welcome back</h2>
                <p class="text-secondary mb-4">Sign in to manage your orders and laundry status.</p>

                <?php if (!empty($errors)): ?>
                    <div class="alert alert-danger">
                        <ul class="mb-0">
                            <?php foreach ($errors as $error): ?>
                                <li><?= htmlspecialchars($error) ?></li>
                            <?php endforeach; ?>
                        </ul>
                    </div>
                <?php endif; ?>

                <form method="POST" action="login.php">
                    <div class="mb-3">
                        <label for="email" class="form-label">Email</label>
                        <input type="email" class="form-control" id="email" name="email" placeholder="you@example.com" required>
                    </div>

                    <div class="mb-3">
                        <label for="password" class="form-label">Password</label>
                        <input type="password" class="form-control" id="password" name="password" placeholder="Enter password" required>
                    </div>

                    <button type="submit" class="btn btn-primary w-100">Login</button>
                </form>

                <div class="text-center mt-4">
                    <span class="text-secondary">New here?</span>
                    <a href="register.php" class="muted-link">Create account</a>
                </div>
            </div>

            <div class="col-lg-6 side-panel">
                <div class="content">
                    <div class="icon"><i class="fa-solid fa-shirt"></i></div>
                    <h3 class="fw-bold mb-3">Fresh, Clean, Ready.</h3>
                    <p class="text-secondary mb-0">Fast laundry service with pickup and delivery you can trust.</p>
                </div>
            </div>
        </div>
    </div>
</body>
</html>
