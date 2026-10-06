CREATE TABLE IF NOT EXISTS {{prefix}}workflow_template_attachments (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    workflow_template_id BIGINT UNSIGNED NOT NULL,
    file_id BIGINT UNSIGNED NOT NULL,
    title VARCHAR(190) NOT NULL,
    sort_order INT NOT NULL DEFAULT 0,
    uploaded_by BIGINT UNSIGNED DEFAULT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    deleted_at DATETIME DEFAULT NULL,
    PRIMARY KEY (id),
    KEY idx_template_attachments_order (workflow_template_id, deleted_at, sort_order, id),
    CONSTRAINT fk_{{prefix}}template_attachment_template FOREIGN KEY (workflow_template_id) REFERENCES {{prefix}}workflow_templates(id) ON DELETE CASCADE,
    CONSTRAINT fk_{{prefix}}template_attachment_file FOREIGN KEY (file_id) REFERENCES {{prefix}}files(id),
    CONSTRAINT fk_{{prefix}}template_attachment_user FOREIGN KEY (uploaded_by) REFERENCES {{prefix}}users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS {{prefix}}order_template_attachments (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    order_id BIGINT UNSIGNED NOT NULL,
    source_template_attachment_id BIGINT UNSIGNED DEFAULT NULL,
    file_id BIGINT UNSIGNED NOT NULL,
    title VARCHAR(190) NOT NULL,
    sort_order INT NOT NULL DEFAULT 0,
    captured_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_order_template_attachment (order_id, source_template_attachment_id),
    KEY idx_order_template_attachments_order (order_id, sort_order, id),
    CONSTRAINT fk_{{prefix}}order_template_attachment_order FOREIGN KEY (order_id) REFERENCES {{prefix}}work_orders(id) ON DELETE CASCADE,
    CONSTRAINT fk_{{prefix}}order_template_attachment_source FOREIGN KEY (source_template_attachment_id) REFERENCES {{prefix}}workflow_template_attachments(id) ON DELETE SET NULL,
    CONSTRAINT fk_{{prefix}}order_template_attachment_file FOREIGN KEY (file_id) REFERENCES {{prefix}}files(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
