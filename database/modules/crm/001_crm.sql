CREATE TABLE IF NOT EXISTS {{prefix}}contacts (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    owner_id BIGINT UNSIGNED DEFAULT NULL,
    first_name VARCHAR(100) NOT NULL,
    last_name VARCHAR(120) DEFAULT NULL,
    company VARCHAR(190) DEFAULT NULL,
    email VARCHAR(190) DEFAULT NULL,
    phone VARCHAR(40) DEFAULT NULL,
    source VARCHAR(80) DEFAULT NULL,
    status VARCHAR(30) NOT NULL DEFAULT 'active',
    tags_json LONGTEXT DEFAULT NULL,
    notes TEXT DEFAULT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    deleted_at DATETIME DEFAULT NULL,
    PRIMARY KEY (id),
    KEY idx_contacts_owner_status (owner_id, status),
    KEY idx_contacts_email (email),
    CONSTRAINT fk_{{prefix}}contacts_owner FOREIGN KEY (owner_id) REFERENCES {{prefix}}users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS {{prefix}}pipelines (
    id SMALLINT UNSIGNED NOT NULL AUTO_INCREMENT,
    name VARCHAR(120) NOT NULL,
    is_default TINYINT(1) NOT NULL DEFAULT 0,
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    PRIMARY KEY (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS {{prefix}}pipeline_stages (
    id SMALLINT UNSIGNED NOT NULL AUTO_INCREMENT,
    pipeline_id SMALLINT UNSIGNED NOT NULL,
    name VARCHAR(120) NOT NULL,
    position INT NOT NULL DEFAULT 0,
    probability TINYINT UNSIGNED NOT NULL DEFAULT 0,
    PRIMARY KEY (id),
    KEY idx_pipeline_stages_order (pipeline_id, position),
    CONSTRAINT fk_{{prefix}}pipeline_stages_pipeline FOREIGN KEY (pipeline_id) REFERENCES {{prefix}}pipelines(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS {{prefix}}deals (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    contact_id BIGINT UNSIGNED DEFAULT NULL,
    owner_id BIGINT UNSIGNED DEFAULT NULL,
    stage_id SMALLINT UNSIGNED NOT NULL,
    title VARCHAR(190) NOT NULL,
    value_amount DECIMAL(16,2) DEFAULT NULL,
    currency CHAR(3) NOT NULL DEFAULT 'IRR',
    expected_close_date DATE DEFAULT NULL,
    status VARCHAR(30) NOT NULL DEFAULT 'open',
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_deals_stage_status (stage_id, status),
    KEY idx_deals_owner (owner_id, status),
    CONSTRAINT fk_{{prefix}}deals_contact FOREIGN KEY (contact_id) REFERENCES {{prefix}}contacts(id) ON DELETE SET NULL,
    CONSTRAINT fk_{{prefix}}deals_owner FOREIGN KEY (owner_id) REFERENCES {{prefix}}users(id) ON DELETE SET NULL,
    CONSTRAINT fk_{{prefix}}deals_stage FOREIGN KEY (stage_id) REFERENCES {{prefix}}pipeline_stages(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS {{prefix}}activities (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    actor_id BIGINT UNSIGNED DEFAULT NULL,
    contact_id BIGINT UNSIGNED DEFAULT NULL,
    deal_id BIGINT UNSIGNED DEFAULT NULL,
    activity_type VARCHAR(50) NOT NULL,
    subject VARCHAR(190) NOT NULL,
    body TEXT DEFAULT NULL,
    due_at DATETIME DEFAULT NULL,
    completed_at DATETIME DEFAULT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_activities_due (completed_at, due_at),
    CONSTRAINT fk_{{prefix}}activities_actor FOREIGN KEY (actor_id) REFERENCES {{prefix}}users(id) ON DELETE SET NULL,
    CONSTRAINT fk_{{prefix}}activities_contact FOREIGN KEY (contact_id) REFERENCES {{prefix}}contacts(id) ON DELETE CASCADE,
    CONSTRAINT fk_{{prefix}}activities_deal FOREIGN KEY (deal_id) REFERENCES {{prefix}}deals(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
