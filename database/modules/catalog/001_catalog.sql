CREATE TABLE IF NOT EXISTS {{prefix}}categories (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    parent_id BIGINT UNSIGNED DEFAULT NULL,
    name VARCHAR(160) NOT NULL,
    slug VARCHAR(160) NOT NULL,
    description TEXT DEFAULT NULL,
    position INT NOT NULL DEFAULT 0,
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_categories_slug (slug),
    KEY idx_categories_parent (parent_id, position),
    CONSTRAINT fk_{{prefix}}categories_parent FOREIGN KEY (parent_id) REFERENCES {{prefix}}categories(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS {{prefix}}products (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    category_id BIGINT UNSIGNED DEFAULT NULL,
    sku VARCHAR(100) DEFAULT NULL,
    slug VARCHAR(160) NOT NULL,
    title VARCHAR(190) NOT NULL,
    description TEXT DEFAULT NULL,
    price DECIMAL(16,2) DEFAULT NULL,
    sale_price DECIMAL(16,2) DEFAULT NULL,
    currency CHAR(3) NOT NULL DEFAULT 'IRR',
    stock_quantity INT DEFAULT NULL,
    manage_stock TINYINT(1) NOT NULL DEFAULT 0,
    status VARCHAR(20) NOT NULL DEFAULT 'draft',
    attributes_json LONGTEXT DEFAULT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    deleted_at DATETIME DEFAULT NULL,
    PRIMARY KEY (id),
    UNIQUE KEY uq_products_slug (slug),
    UNIQUE KEY uq_products_sku (sku),
    KEY idx_products_category_status (category_id, status),
    CONSTRAINT fk_{{prefix}}products_category FOREIGN KEY (category_id) REFERENCES {{prefix}}categories(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS {{prefix}}product_images (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    product_id BIGINT UNSIGNED NOT NULL,
    file_id BIGINT UNSIGNED DEFAULT NULL,
    alt_text VARCHAR(190) DEFAULT NULL,
    position INT NOT NULL DEFAULT 0,
    PRIMARY KEY (id),
    KEY idx_product_images_product (product_id, position),
    CONSTRAINT fk_{{prefix}}product_images_product FOREIGN KEY (product_id) REFERENCES {{prefix}}products(id) ON DELETE CASCADE,
    CONSTRAINT fk_{{prefix}}product_images_file FOREIGN KEY (file_id) REFERENCES {{prefix}}files(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
