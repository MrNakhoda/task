CREATE TABLE IF NOT EXISTS {{prefix}}departments (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    parent_id BIGINT UNSIGNED DEFAULT NULL,
    name VARCHAR(160) NOT NULL,
    code VARCHAR(40) DEFAULT NULL,
    manager_employee_id BIGINT UNSIGNED DEFAULT NULL,
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_departments_code (code),
    KEY idx_departments_parent (parent_id),
    KEY idx_departments_manager (manager_employee_id),
    CONSTRAINT fk_{{prefix}}departments_parent FOREIGN KEY (parent_id) REFERENCES {{prefix}}departments(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS {{prefix}}employees (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    user_id BIGINT UNSIGNED DEFAULT NULL,
    department_id BIGINT UNSIGNED DEFAULT NULL,
    employee_number VARCHAR(60) NOT NULL,
    first_name VARCHAR(100) NOT NULL,
    last_name VARCHAR(120) NOT NULL,
    email VARCHAR(190) DEFAULT NULL,
    phone VARCHAR(40) DEFAULT NULL,
    job_title VARCHAR(160) DEFAULT NULL,
    employment_type VARCHAR(40) DEFAULT NULL,
    started_at DATE DEFAULT NULL,
    ended_at DATE DEFAULT NULL,
    status VARCHAR(30) NOT NULL DEFAULT 'active',
    metadata_json LONGTEXT DEFAULT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    deleted_at DATETIME DEFAULT NULL,
    PRIMARY KEY (id),
    UNIQUE KEY uq_employees_number (employee_number),
    KEY idx_employees_department_status (department_id, status),
    CONSTRAINT fk_{{prefix}}employees_user FOREIGN KEY (user_id) REFERENCES {{prefix}}users(id) ON DELETE SET NULL,
    CONSTRAINT fk_{{prefix}}employees_department FOREIGN KEY (department_id) REFERENCES {{prefix}}departments(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS {{prefix}}leave_requests (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    employee_id BIGINT UNSIGNED NOT NULL,
    leave_type VARCHAR(50) NOT NULL,
    starts_on DATE NOT NULL,
    ends_on DATE NOT NULL,
    reason TEXT DEFAULT NULL,
    status VARCHAR(30) NOT NULL DEFAULT 'pending',
    reviewed_by BIGINT UNSIGNED DEFAULT NULL,
    reviewed_at DATETIME DEFAULT NULL,
    review_note TEXT DEFAULT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_leave_employee_status (employee_id, status),
    KEY idx_leave_dates (starts_on, ends_on),
    CONSTRAINT fk_{{prefix}}leave_employee FOREIGN KEY (employee_id) REFERENCES {{prefix}}employees(id) ON DELETE CASCADE,
    CONSTRAINT fk_{{prefix}}leave_reviewer FOREIGN KEY (reviewed_by) REFERENCES {{prefix}}users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
