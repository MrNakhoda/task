CREATE TABLE IF NOT EXISTS {{prefix}}teams (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    name VARCHAR(160) NOT NULL,
    description VARCHAR(500) DEFAULT NULL,
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    created_by BIGINT UNSIGNED DEFAULT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_teams_name (name),
    CONSTRAINT fk_{{prefix}}teams_creator FOREIGN KEY (created_by) REFERENCES {{prefix}}users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS {{prefix}}team_members (
    team_id BIGINT UNSIGNED NOT NULL,
    user_id BIGINT UNSIGNED NOT NULL,
    is_lead TINYINT(1) NOT NULL DEFAULT 0,
    joined_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (team_id, user_id),
    KEY idx_team_members_user (user_id),
    CONSTRAINT fk_{{prefix}}team_members_team FOREIGN KEY (team_id) REFERENCES {{prefix}}teams(id) ON DELETE CASCADE,
    CONSTRAINT fk_{{prefix}}team_members_user FOREIGN KEY (user_id) REFERENCES {{prefix}}users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS {{prefix}}task_types (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    name VARCHAR(160) NOT NULL,
    slug VARCHAR(100) NOT NULL,
    color VARCHAR(20) NOT NULL DEFAULT '#3157d5',
    description VARCHAR(500) DEFAULT NULL,
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_task_types_slug (slug)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS {{prefix}}projects (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    name VARCHAR(190) NOT NULL,
    code VARCHAR(60) DEFAULT NULL,
    description TEXT DEFAULT NULL,
    status VARCHAR(30) NOT NULL DEFAULT 'active',
    started_at DATE DEFAULT NULL,
    due_at DATE DEFAULT NULL,
    created_by BIGINT UNSIGNED DEFAULT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_projects_code (code),
    KEY idx_projects_status (status),
    CONSTRAINT fk_{{prefix}}projects_creator FOREIGN KEY (created_by) REFERENCES {{prefix}}users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS {{prefix}}project_members (
    project_id BIGINT UNSIGNED NOT NULL,
    user_id BIGINT UNSIGNED NOT NULL,
    role_label VARCHAR(100) DEFAULT NULL,
    joined_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (project_id, user_id),
    KEY idx_project_members_user (user_id),
    CONSTRAINT fk_{{prefix}}project_members_project FOREIGN KEY (project_id) REFERENCES {{prefix}}projects(id) ON DELETE CASCADE,
    CONSTRAINT fk_{{prefix}}project_members_user FOREIGN KEY (user_id) REFERENCES {{prefix}}users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS {{prefix}}customers (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    name VARCHAR(190) NOT NULL,
    phone VARCHAR(40) DEFAULT NULL,
    email VARCHAR(190) DEFAULT NULL,
    notes TEXT DEFAULT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    deleted_at DATETIME DEFAULT NULL,
    PRIMARY KEY (id),
    KEY idx_customers_name (name),
    KEY idx_customers_phone (phone)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS {{prefix}}order_priorities (
    id SMALLINT UNSIGNED NOT NULL AUTO_INCREMENT,
    key_name VARCHAR(60) NOT NULL,
    name VARCHAR(100) NOT NULL,
    color VARCHAR(20) NOT NULL DEFAULT '#64748b',
    sort_order INT NOT NULL DEFAULT 0,
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    PRIMARY KEY (id),
    UNIQUE KEY uq_order_priorities_key (key_name)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS {{prefix}}workflow_templates (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    name VARCHAR(190) NOT NULL,
    description TEXT DEFAULT NULL,
    version INT UNSIGNED NOT NULL DEFAULT 1,
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    created_by BIGINT UNSIGNED DEFAULT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    deleted_at DATETIME DEFAULT NULL,
    PRIMARY KEY (id),
    KEY idx_workflow_templates_active (is_active, deleted_at),
    CONSTRAINT fk_{{prefix}}workflow_templates_creator FOREIGN KEY (created_by) REFERENCES {{prefix}}users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS {{prefix}}workflow_template_steps (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    workflow_template_id BIGINT UNSIGNED NOT NULL,
    task_type_id BIGINT UNSIGNED NOT NULL,
    name VARCHAR(190) NOT NULL,
    description TEXT DEFAULT NULL,
    position INT NOT NULL DEFAULT 0,
    progress_weight DECIMAL(10,2) NOT NULL DEFAULT 1,
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_template_steps_order (workflow_template_id, position),
    CONSTRAINT fk_{{prefix}}template_steps_template FOREIGN KEY (workflow_template_id) REFERENCES {{prefix}}workflow_templates(id) ON DELETE CASCADE,
    CONSTRAINT fk_{{prefix}}template_steps_type FOREIGN KEY (task_type_id) REFERENCES {{prefix}}task_types(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS {{prefix}}workflow_template_step_dependencies (
    step_id BIGINT UNSIGNED NOT NULL,
    depends_on_step_id BIGINT UNSIGNED NOT NULL,
    PRIMARY KEY (step_id, depends_on_step_id),
    CONSTRAINT fk_{{prefix}}template_dep_step FOREIGN KEY (step_id) REFERENCES {{prefix}}workflow_template_steps(id) ON DELETE CASCADE,
    CONSTRAINT fk_{{prefix}}template_dep_parent FOREIGN KEY (depends_on_step_id) REFERENCES {{prefix}}workflow_template_steps(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS {{prefix}}workflow_template_step_users (
    step_id BIGINT UNSIGNED NOT NULL,
    user_id BIGINT UNSIGNED NOT NULL,
    PRIMARY KEY (step_id, user_id),
    CONSTRAINT fk_{{prefix}}template_step_users_step FOREIGN KEY (step_id) REFERENCES {{prefix}}workflow_template_steps(id) ON DELETE CASCADE,
    CONSTRAINT fk_{{prefix}}template_step_users_user FOREIGN KEY (user_id) REFERENCES {{prefix}}users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS {{prefix}}workflow_template_step_teams (
    step_id BIGINT UNSIGNED NOT NULL,
    team_id BIGINT UNSIGNED NOT NULL,
    PRIMARY KEY (step_id, team_id),
    CONSTRAINT fk_{{prefix}}template_step_teams_step FOREIGN KEY (step_id) REFERENCES {{prefix}}workflow_template_steps(id) ON DELETE CASCADE,
    CONSTRAINT fk_{{prefix}}template_step_teams_team FOREIGN KEY (team_id) REFERENCES {{prefix}}teams(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS {{prefix}}workflow_template_step_roles (
    step_id BIGINT UNSIGNED NOT NULL,
    role_id SMALLINT UNSIGNED NOT NULL,
    PRIMARY KEY (step_id, role_id),
    CONSTRAINT fk_{{prefix}}template_step_roles_step FOREIGN KEY (step_id) REFERENCES {{prefix}}workflow_template_steps(id) ON DELETE CASCADE,
    CONSTRAINT fk_{{prefix}}template_step_roles_role FOREIGN KEY (role_id) REFERENCES {{prefix}}roles(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS {{prefix}}work_orders (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    order_number VARCHAR(60) NOT NULL,
    title VARCHAR(190) NOT NULL,
    project_id BIGINT UNSIGNED DEFAULT NULL,
    workflow_template_id BIGINT UNSIGNED NOT NULL,
    priority_id SMALLINT UNSIGNED DEFAULT NULL,
    status VARCHAR(30) NOT NULL DEFAULT 'draft',
    progress_percent DECIMAL(5,2) NOT NULL DEFAULT 0,
    details_json LONGTEXT DEFAULT NULL,
    due_at DATETIME DEFAULT NULL,
    activated_at DATETIME DEFAULT NULL,
    completed_at DATETIME DEFAULT NULL,
    created_by BIGINT UNSIGNED DEFAULT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    deleted_at DATETIME DEFAULT NULL,
    PRIMARY KEY (id),
    UNIQUE KEY uq_work_orders_number (order_number),
    KEY idx_work_orders_status_priority (status, priority_id),
    KEY idx_work_orders_project (project_id, status),
    CONSTRAINT fk_{{prefix}}work_orders_project FOREIGN KEY (project_id) REFERENCES {{prefix}}projects(id) ON DELETE SET NULL,
    CONSTRAINT fk_{{prefix}}work_orders_template FOREIGN KEY (workflow_template_id) REFERENCES {{prefix}}workflow_templates(id),
    CONSTRAINT fk_{{prefix}}work_orders_priority FOREIGN KEY (priority_id) REFERENCES {{prefix}}order_priorities(id) ON DELETE SET NULL,
    CONSTRAINT fk_{{prefix}}work_orders_creator FOREIGN KEY (created_by) REFERENCES {{prefix}}users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS {{prefix}}order_customers (
    order_id BIGINT UNSIGNED NOT NULL,
    customer_id BIGINT UNSIGNED NOT NULL,
    weight DECIMAL(14,3) DEFAULT NULL,
    weight_unit VARCHAR(20) NOT NULL DEFAULT 'gram',
    notes VARCHAR(500) DEFAULT NULL,
    PRIMARY KEY (order_id, customer_id),
    KEY idx_order_customers_customer_weight (customer_id, weight),
    CONSTRAINT fk_{{prefix}}order_customers_order FOREIGN KEY (order_id) REFERENCES {{prefix}}work_orders(id) ON DELETE CASCADE,
    CONSTRAINT fk_{{prefix}}order_customers_customer FOREIGN KEY (customer_id) REFERENCES {{prefix}}customers(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS {{prefix}}order_attachments (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    order_id BIGINT UNSIGNED NOT NULL,
    file_id BIGINT UNSIGNED NOT NULL,
    uploaded_by BIGINT UNSIGNED DEFAULT NULL,
    caption VARCHAR(255) DEFAULT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_order_attachments_order (order_id, id),
    CONSTRAINT fk_{{prefix}}order_attachments_order FOREIGN KEY (order_id) REFERENCES {{prefix}}work_orders(id) ON DELETE CASCADE,
    CONSTRAINT fk_{{prefix}}order_attachments_file FOREIGN KEY (file_id) REFERENCES {{prefix}}files(id) ON DELETE CASCADE,
    CONSTRAINT fk_{{prefix}}order_attachments_user FOREIGN KEY (uploaded_by) REFERENCES {{prefix}}users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS {{prefix}}order_workflow_steps (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    order_id BIGINT UNSIGNED NOT NULL,
    source_template_step_id BIGINT UNSIGNED DEFAULT NULL,
    task_type_id BIGINT UNSIGNED NOT NULL,
    name VARCHAR(190) NOT NULL,
    description TEXT DEFAULT NULL,
    position INT NOT NULL DEFAULT 0,
    progress_weight DECIMAL(10,2) NOT NULL DEFAULT 1,
    status VARCHAR(30) NOT NULL DEFAULT 'pending',
    activated_at DATETIME DEFAULT NULL,
    completed_at DATETIME DEFAULT NULL,
    disabled_at DATETIME DEFAULT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_order_steps_order_status (order_id, status, position),
    CONSTRAINT fk_{{prefix}}order_steps_order FOREIGN KEY (order_id) REFERENCES {{prefix}}work_orders(id) ON DELETE CASCADE,
    CONSTRAINT fk_{{prefix}}order_steps_source FOREIGN KEY (source_template_step_id) REFERENCES {{prefix}}workflow_template_steps(id) ON DELETE SET NULL,
    CONSTRAINT fk_{{prefix}}order_steps_type FOREIGN KEY (task_type_id) REFERENCES {{prefix}}task_types(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS {{prefix}}order_step_dependencies (
    step_id BIGINT UNSIGNED NOT NULL,
    depends_on_step_id BIGINT UNSIGNED NOT NULL,
    PRIMARY KEY (step_id, depends_on_step_id),
    CONSTRAINT fk_{{prefix}}order_dep_step FOREIGN KEY (step_id) REFERENCES {{prefix}}order_workflow_steps(id) ON DELETE CASCADE,
    CONSTRAINT fk_{{prefix}}order_dep_parent FOREIGN KEY (depends_on_step_id) REFERENCES {{prefix}}order_workflow_steps(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS {{prefix}}order_step_candidate_assignees (
    step_id BIGINT UNSIGNED NOT NULL,
    user_id BIGINT UNSIGNED NOT NULL,
    PRIMARY KEY (step_id, user_id),
    CONSTRAINT fk_{{prefix}}step_candidates_step FOREIGN KEY (step_id) REFERENCES {{prefix}}order_workflow_steps(id) ON DELETE CASCADE,
    CONSTRAINT fk_{{prefix}}step_candidates_user FOREIGN KEY (user_id) REFERENCES {{prefix}}users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS {{prefix}}tasks (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    order_id BIGINT UNSIGNED NOT NULL,
    order_step_id BIGINT UNSIGNED NOT NULL,
    task_type_id BIGINT UNSIGNED NOT NULL,
    title VARCHAR(255) NOT NULL,
    description TEXT DEFAULT NULL,
    status VARCHAR(30) NOT NULL DEFAULT 'open',
    started_by BIGINT UNSIGNED DEFAULT NULL,
    completed_by BIGINT UNSIGNED DEFAULT NULL,
    started_at DATETIME DEFAULT NULL,
    completed_at DATETIME DEFAULT NULL,
    due_at DATETIME DEFAULT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_tasks_order_step (order_step_id),
    KEY idx_tasks_order_status (order_id, status),
    KEY idx_tasks_type_status (task_type_id, status),
    CONSTRAINT fk_{{prefix}}tasks_order FOREIGN KEY (order_id) REFERENCES {{prefix}}work_orders(id) ON DELETE CASCADE,
    CONSTRAINT fk_{{prefix}}tasks_step FOREIGN KEY (order_step_id) REFERENCES {{prefix}}order_workflow_steps(id) ON DELETE CASCADE,
    CONSTRAINT fk_{{prefix}}tasks_type FOREIGN KEY (task_type_id) REFERENCES {{prefix}}task_types(id),
    CONSTRAINT fk_{{prefix}}tasks_starter FOREIGN KEY (started_by) REFERENCES {{prefix}}users(id) ON DELETE SET NULL,
    CONSTRAINT fk_{{prefix}}tasks_completer FOREIGN KEY (completed_by) REFERENCES {{prefix}}users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS {{prefix}}task_assignees (
    task_id BIGINT UNSIGNED NOT NULL,
    user_id BIGINT UNSIGNED NOT NULL,
    assigned_by BIGINT UNSIGNED DEFAULT NULL,
    assigned_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (task_id, user_id),
    KEY idx_task_assignees_user (user_id, task_id),
    CONSTRAINT fk_{{prefix}}task_assignees_task FOREIGN KEY (task_id) REFERENCES {{prefix}}tasks(id) ON DELETE CASCADE,
    CONSTRAINT fk_{{prefix}}task_assignees_user FOREIGN KEY (user_id) REFERENCES {{prefix}}users(id) ON DELETE CASCADE,
    CONSTRAINT fk_{{prefix}}task_assignees_actor FOREIGN KEY (assigned_by) REFERENCES {{prefix}}users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS {{prefix}}task_reports (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    task_id BIGINT UNSIGNED NOT NULL,
    user_id BIGINT UNSIGNED NOT NULL,
    report_text TEXT NOT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_task_reports_task (task_id, created_at),
    CONSTRAINT fk_{{prefix}}task_reports_task FOREIGN KEY (task_id) REFERENCES {{prefix}}tasks(id) ON DELETE CASCADE,
    CONSTRAINT fk_{{prefix}}task_reports_user FOREIGN KEY (user_id) REFERENCES {{prefix}}users(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS {{prefix}}workflow_activity_logs (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    order_id BIGINT UNSIGNED DEFAULT NULL,
    task_id BIGINT UNSIGNED DEFAULT NULL,
    actor_id BIGINT UNSIGNED DEFAULT NULL,
    event_type VARCHAR(100) NOT NULL,
    message VARCHAR(500) NOT NULL,
    metadata_json LONGTEXT DEFAULT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_workflow_history_order (order_id, created_at),
    KEY idx_workflow_history_task (task_id, created_at),
    CONSTRAINT fk_{{prefix}}workflow_history_order FOREIGN KEY (order_id) REFERENCES {{prefix}}work_orders(id) ON DELETE CASCADE,
    CONSTRAINT fk_{{prefix}}workflow_history_task FOREIGN KEY (task_id) REFERENCES {{prefix}}tasks(id) ON DELETE CASCADE,
    CONSTRAINT fk_{{prefix}}workflow_history_actor FOREIGN KEY (actor_id) REFERENCES {{prefix}}users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS {{prefix}}user_notifications (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    user_id BIGINT UNSIGNED NOT NULL,
    event_type VARCHAR(100) NOT NULL,
    title VARCHAR(190) NOT NULL,
    body VARCHAR(1000) DEFAULT NULL,
    link_url VARCHAR(500) DEFAULT NULL,
    read_at DATETIME DEFAULT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_user_notifications_unread (user_id, read_at, created_at),
    CONSTRAINT fk_{{prefix}}user_notifications_user FOREIGN KEY (user_id) REFERENCES {{prefix}}users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT IGNORE INTO {{prefix}}order_priorities (key_name, name, color, sort_order) VALUES
('low', 'کم', '#64748b', 10),
('normal', 'عادی', '#3157d5', 20),
('high', 'زیاد', '#ea580c', 30),
('urgent', 'فوری', '#dc2626', 40);

INSERT IGNORE INTO {{prefix}}permissions (key_name, display_name) VALUES
('workflow.admin', 'Full workflow administration'),
('templates.manage', 'Manage workflow templates'),
('orders.manage', 'Manage work orders'),
('tasks.work', 'Work on assigned tasks'),
('tasks.manage', 'Manage every task'),
('teams.manage', 'Manage teams and projects');

INSERT IGNORE INTO {{prefix}}role_permissions (role_id, permission_id)
SELECT r.id, p.id FROM {{prefix}}roles r CROSS JOIN {{prefix}}permissions p
WHERE r.key_name = 'admin';

INSERT IGNORE INTO {{prefix}}role_permissions (role_id, permission_id)
SELECT r.id, p.id FROM {{prefix}}roles r CROSS JOIN {{prefix}}permissions p
WHERE r.key_name = 'manager' AND p.key_name IN ('templates.manage', 'orders.manage', 'tasks.manage', 'teams.manage');

INSERT IGNORE INTO {{prefix}}role_permissions (role_id, permission_id)
SELECT r.id, p.id FROM {{prefix}}roles r CROSS JOIN {{prefix}}permissions p
WHERE r.key_name = 'user' AND p.key_name = 'tasks.work';
