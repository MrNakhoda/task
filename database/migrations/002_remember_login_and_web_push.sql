CREATE TABLE IF NOT EXISTS {{prefix}}remember_login_tokens (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    user_id BIGINT UNSIGNED NOT NULL,
    selector CHAR(32) NOT NULL,
    validator_hash CHAR(64) NOT NULL,
    auth_version INT UNSIGNED NOT NULL,
    expires_at DATETIME NOT NULL,
    last_used_at DATETIME DEFAULT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_remember_login_selector (selector),
    KEY idx_remember_login_user (user_id, expires_at),
    KEY idx_remember_login_expiry (expires_at),
    CONSTRAINT fk_{{prefix}}remember_login_user FOREIGN KEY (user_id) REFERENCES {{prefix}}users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS {{prefix}}push_subscriptions (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    user_id BIGINT UNSIGNED NOT NULL,
    endpoint VARCHAR(2048) NOT NULL,
    endpoint_hash CHAR(64) NOT NULL,
    public_key VARCHAR(190) NOT NULL,
    auth_token VARCHAR(190) NOT NULL,
    content_encoding VARCHAR(40) NOT NULL DEFAULT 'aes128gcm',
    user_agent VARCHAR(500) DEFAULT NULL,
    last_used_at DATETIME DEFAULT NULL,
    revoked_at DATETIME DEFAULT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_push_subscription_endpoint (endpoint_hash),
    KEY idx_push_subscription_user (user_id, revoked_at),
    CONSTRAINT fk_{{prefix}}push_subscription_user FOREIGN KEY (user_id) REFERENCES {{prefix}}users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
