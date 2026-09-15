ALTER TABLE {{prefix}}user_notifications
    ADD COLUMN dedupe_key VARCHAR(160) DEFAULT NULL AFTER link_url,
    ADD UNIQUE KEY uq_user_notification_dedupe (user_id, dedupe_key);

CREATE TABLE IF NOT EXISTS {{prefix}}push_notification_deliveries (
    notification_id BIGINT UNSIGNED NOT NULL,
    subscription_id BIGINT UNSIGNED NOT NULL,
    attempts SMALLINT UNSIGNED NOT NULL DEFAULT 0,
    delivered_at DATETIME DEFAULT NULL,
    last_attempt_at DATETIME DEFAULT NULL,
    last_error VARCHAR(1000) DEFAULT NULL,
    PRIMARY KEY (notification_id, subscription_id),
    KEY idx_push_delivery_pending (delivered_at, attempts),
    CONSTRAINT fk_{{prefix}}push_delivery_notification FOREIGN KEY (notification_id) REFERENCES {{prefix}}user_notifications(id) ON DELETE CASCADE,
    CONSTRAINT fk_{{prefix}}push_delivery_subscription FOREIGN KEY (subscription_id) REFERENCES {{prefix}}push_subscriptions(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
