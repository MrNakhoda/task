ALTER TABLE {{prefix}}tasks
    MODIFY order_id BIGINT UNSIGNED DEFAULT NULL,
    MODIFY order_step_id BIGINT UNSIGNED DEFAULT NULL,
    ADD COLUMN is_standalone TINYINT(1) NOT NULL DEFAULT 0 AFTER description,
    ADD COLUMN created_by BIGINT UNSIGNED DEFAULT NULL AFTER due_at,
    ADD KEY idx_tasks_standalone_status (is_standalone, status),
    ADD CONSTRAINT fk_{{prefix}}tasks_creator FOREIGN KEY (created_by) REFERENCES {{prefix}}users(id) ON DELETE SET NULL;
