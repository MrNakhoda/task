INSERT IGNORE INTO {{prefix}}permissions (key_name, display_name) VALUES
('tasks.create.self', 'Create standalone tasks for self'),
('tasks.create.assign', 'Create and assign standalone tasks');

INSERT IGNORE INTO {{prefix}}role_permissions (role_id, permission_id)
SELECT DISTINCT current_permission.role_id, new_permission.id
FROM {{prefix}}role_permissions current_permission
JOIN {{prefix}}permissions task_manager
  ON task_manager.id=current_permission.permission_id
 AND task_manager.key_name='tasks.manage'
CROSS JOIN {{prefix}}permissions new_permission
WHERE new_permission.key_name IN ('tasks.create.self','tasks.create.assign');
