INSERT IGNORE INTO {{prefix}}permissions (key_name, display_name) VALUES
('system.admin', 'Full system administration'),
('users.manage', 'Manage users'),
('roles.manage', 'Manage roles and permissions'),
('catalog.manage', 'Manage catalog'),
('orders.read', 'Read assigned projects'),
('orders.manage', 'Manage every project'),
('crm.manage', 'Manage CRM'),
('hr.manage', 'Manage HR'),
('workflow.admin', 'Full workflow administration'),
('templates.manage', 'Manage workflow templates'),
('tasks.work', 'Work on assigned tasks'),
('tasks.manage', 'Manage every task'),
('teams.manage', 'Manage teams');

INSERT IGNORE INTO {{prefix}}role_permissions (role_id, permission_id)
SELECT r.id, p.id
FROM {{prefix}}roles r
CROSS JOIN {{prefix}}permissions p
WHERE r.key_name = 'admin'
  AND p.key_name IN ('system.admin','users.manage','roles.manage','catalog.manage','orders.read','orders.manage','crm.manage','hr.manage','workflow.admin','templates.manage','tasks.work','tasks.manage','teams.manage');

INSERT IGNORE INTO {{prefix}}role_permissions (role_id, permission_id)
SELECT r.id, p.id
FROM {{prefix}}roles r
CROSS JOIN {{prefix}}permissions p
WHERE r.key_name = 'user'
  AND p.key_name IN ('orders.read','tasks.work');

INSERT IGNORE INTO {{prefix}}role_permissions (role_id, permission_id)
SELECT DISTINCT rp.role_id, project_read.id
FROM {{prefix}}role_permissions rp
JOIN {{prefix}}permissions task_work ON task_work.id=rp.permission_id AND task_work.key_name='tasks.work'
JOIN {{prefix}}permissions project_read ON project_read.key_name='orders.read';
