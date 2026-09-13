CREATE TABLE IF NOT EXISTS {{prefix}}customers (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    user_id BIGINT UNSIGNED DEFAULT NULL,
    name VARCHAR(150) NOT NULL,
    email VARCHAR(190) DEFAULT NULL,
    phone VARCHAR(40) DEFAULT NULL,
    metadata_json LONGTEXT DEFAULT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_customers_email (email),
    KEY idx_customers_phone (phone),
    CONSTRAINT fk_{{prefix}}customers_user FOREIGN KEY (user_id) REFERENCES {{prefix}}users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS {{prefix}}orders (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    order_number VARCHAR(50) NOT NULL,
    customer_id BIGINT UNSIGNED DEFAULT NULL,
    status VARCHAR(30) NOT NULL DEFAULT 'new',
    payment_status VARCHAR(30) NOT NULL DEFAULT 'unpaid',
    subtotal DECIMAL(16,2) NOT NULL DEFAULT 0,
    discount_total DECIMAL(16,2) NOT NULL DEFAULT 0,
    shipping_total DECIMAL(16,2) NOT NULL DEFAULT 0,
    total DECIMAL(16,2) NOT NULL DEFAULT 0,
    currency CHAR(3) NOT NULL DEFAULT 'IRR',
    shipping_address_json LONGTEXT DEFAULT NULL,
    customer_note TEXT DEFAULT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_orders_number (order_number),
    KEY idx_orders_customer_status (customer_id, status),
    CONSTRAINT fk_{{prefix}}orders_customer FOREIGN KEY (customer_id) REFERENCES {{prefix}}customers(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS {{prefix}}order_items (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    order_id BIGINT UNSIGNED NOT NULL,
    product_id BIGINT UNSIGNED DEFAULT NULL,
    sku VARCHAR(100) DEFAULT NULL,
    title VARCHAR(190) NOT NULL,
    quantity INT UNSIGNED NOT NULL DEFAULT 1,
    unit_price DECIMAL(16,2) NOT NULL DEFAULT 0,
    line_total DECIMAL(16,2) NOT NULL DEFAULT 0,
    options_json LONGTEXT DEFAULT NULL,
    PRIMARY KEY (id),
    KEY idx_order_items_order (order_id),
    CONSTRAINT fk_{{prefix}}order_items_order FOREIGN KEY (order_id) REFERENCES {{prefix}}orders(id) ON DELETE CASCADE,
    CONSTRAINT fk_{{prefix}}order_items_product FOREIGN KEY (product_id) REFERENCES {{prefix}}products(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS {{prefix}}payments (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    order_id BIGINT UNSIGNED DEFAULT NULL,
    provider VARCHAR(40) NOT NULL,
    authority VARCHAR(190) DEFAULT NULL,
    reference_id VARCHAR(190) DEFAULT NULL,
    amount DECIMAL(16,2) NOT NULL,
    currency CHAR(3) NOT NULL DEFAULT 'IRR',
    status VARCHAR(30) NOT NULL DEFAULT 'pending',
    metadata_json LONGTEXT DEFAULT NULL,
    requested_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    paid_at DATETIME DEFAULT NULL,
    failed_at DATETIME DEFAULT NULL,
    PRIMARY KEY (id),
    KEY idx_payments_order (order_id, status),
    KEY idx_payments_authority (provider, authority),
    CONSTRAINT fk_{{prefix}}payments_order FOREIGN KEY (order_id) REFERENCES {{prefix}}orders(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
