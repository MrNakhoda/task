ALTER TABLE {{prefix}}user_notifications
    ADD COLUMN dedupe_key VARCHAR(160) DEFAULT NULL AFTER link_url,
    ADD UNIQUE KEY uq_user_notification_dedupe (user_id, dedupe_key);

ALTER TABLE {{prefix}}push_notification_deliveries
    ADD CONSTRAINT fk_{{prefix}}push_delivery_notification FOREIGN KEY (notification_id) REFERENCES {{prefix}}user_notifications(id) ON DELETE CASCADE;
