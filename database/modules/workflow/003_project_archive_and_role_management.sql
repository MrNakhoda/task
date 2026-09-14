ALTER TABLE {{prefix}}work_orders
    ADD COLUMN archived_at DATETIME DEFAULT NULL AFTER completed_at,
    ADD KEY idx_work_orders_archive (archived_at, status, deleted_at);

ALTER TABLE {{prefix}}tasks
    ADD COLUMN archived_at DATETIME DEFAULT NULL AFTER completed_at,
    ADD KEY idx_tasks_archive (archived_at, status, order_id);

INSERT IGNORE INTO {{prefix}}permissions (key_name, display_name) VALUES
('roles.manage', 'Manage roles and permissions');

INSERT IGNORE INTO {{prefix}}role_permissions (role_id, permission_id)
SELECT r.id, p.id FROM {{prefix}}roles r CROSS JOIN {{prefix}}permissions p
WHERE r.key_name = 'admin' AND p.key_name = 'roles.manage';
