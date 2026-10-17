CREATE DATABASE IF NOT EXISTS gween_laundry;
USE gween_laundry;

CREATE TABLE IF NOT EXISTS users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(255) NOT NULL,
    email VARCHAR(255) NOT NULL UNIQUE,
    phone VARCHAR(50) NOT NULL,
    password VARCHAR(255) NOT NULL,
    role ENUM('customer', 'admin') NOT NULL DEFAULT 'customer',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS services (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(150) NOT NULL,
    description TEXT,
    price DECIMAL(12,2) NOT NULL DEFAULT 0.00,
    unit VARCHAR(50) NOT NULL DEFAULT 'kg',
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    estimated_time VARCHAR(50) DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS orders (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    service_id INT NOT NULL,
    order_number VARCHAR(50) NOT NULL UNIQUE,
    order_date DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    status ENUM(
        'menunggu_konfirmasi',
        'dikonfirmasi',
        'menunggu_pickup',
        'laundry_diterima',
        'diproses',
        'selesai',
        'siap_diambil',
        'sedang_diantar',
        'pesanan_selesai',
        'dibatalkan'
    ) NOT NULL DEFAULT 'menunggu_konfirmasi',
    quantity DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    weight DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    pickup_option TINYINT(1) NOT NULL DEFAULT 0,
    delivery_option TINYINT(1) NOT NULL DEFAULT 0,
    pickup_address TEXT DEFAULT NULL,
    delivery_address TEXT DEFAULT NULL,
    notes TEXT DEFAULT NULL,
    subtotal DECIMAL(14,2) NOT NULL DEFAULT 0.00,
    pickup_fee DECIMAL(14,2) NOT NULL DEFAULT 0.00,
    delivery_fee DECIMAL(14,2) NOT NULL DEFAULT 0.00,
    total_amount DECIMAL(14,2) NOT NULL DEFAULT 0.00,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_orders_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT fk_orders_service FOREIGN KEY (service_id) REFERENCES services(id) ON DELETE RESTRICT ON UPDATE CASCADE
);

CREATE TABLE IF NOT EXISTS payments (
    id INT AUTO_INCREMENT PRIMARY KEY,
    order_id INT NOT NULL,
    payment_method ENUM('cash', 'transfer', 'gateway') NOT NULL DEFAULT 'cash',
    payment_status ENUM('pending', 'paid', 'failed', 'refunded') NOT NULL DEFAULT 'pending',
    transfer_name VARCHAR(150) DEFAULT NULL,
    proof_image VARCHAR(255) DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_payments_order FOREIGN KEY (order_id) REFERENCES orders(id) ON DELETE CASCADE ON UPDATE CASCADE
);

-- Default admin account
INSERT INTO users (name, email, phone, password, role)
SELECT 'Administrator', 'admin@cleanwash.test', '081234567890', '$2y$10$8q93fNW0bt4hqJIU85J26eAVslAbXSCH2FNVGDTTyY7rXNA9K0G.O', 'admin'
WHERE NOT EXISTS (
    SELECT 1 FROM users WHERE email = 'admin@cleanwash.test'
);

INSERT INTO services (name, description, price, unit, is_active, estimated_time)
SELECT 'Cuci Kering', 'Bersih, harum, dan siap dipakai', 7000.00, 'kg', 1, '24 jam'
WHERE NOT EXISTS (SELECT 1 FROM services WHERE name = 'Cuci Kering');

INSERT INTO services (name, description, price, unit, is_active, estimated_time)
SELECT 'Cuci + Setrika', 'Paket lengkap dengan setrika rapi', 10000.00, 'kg', 1, '24 jam'
WHERE NOT EXISTS (SELECT 1 FROM services WHERE name = 'Cuci + Setrika');

INSERT INTO services (name, description, price, unit, is_active, estimated_time)
SELECT 'Express 1 Hari', 'Untuk kebutuhan mendesak', 15000.00, 'kg', 1, '1 hari'
WHERE NOT EXISTS (SELECT 1 FROM services WHERE name = 'Express 1 Hari');
