CREATE TABLE IF NOT EXISTS users (
 id INTEGER PRIMARY KEY AUTO_INCREMENT,
 name VARCHAR(120) NOT NULL,
 email VARCHAR(190) NOT NULL UNIQUE,
 password_hash VARCHAR(255) NOT NULL,
 role VARCHAR(20) NOT NULL,
 active INTEGER NOT NULL DEFAULT 1,
 must_change_password INTEGER NOT NULL DEFAULT 1,
 employee_number VARCHAR(60) NULL UNIQUE,
 designation VARCHAR(120) NOT NULL DEFAULT 'Nurse',
 district_id INTEGER NULL,
 location_id INTEGER NULL,
 employment_status VARCHAR(60) NOT NULL DEFAULT 'Permanent',
 phone VARCHAR(40) NOT NULL DEFAULT '',
 specialization VARCHAR(190) NOT NULL DEFAULT '',
 skills TEXT NULL,
 photo VARCHAR(100) NULL,
 created_at DATETIME NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
CREATE TABLE IF NOT EXISTS districts (
 id INTEGER PRIMARY KEY AUTO_INCREMENT, name VARCHAR(150) NOT NULL UNIQUE, active INTEGER NOT NULL DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
CREATE TABLE IF NOT EXISTS municipalities (
 id INTEGER PRIMARY KEY AUTO_INCREMENT, name VARCHAR(150) NOT NULL UNIQUE, active INTEGER NOT NULL DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
CREATE TABLE IF NOT EXISTS locations (
 id INTEGER PRIMARY KEY AUTO_INCREMENT, name VARCHAR(190) NOT NULL,
 type VARCHAR(60) NOT NULL, district_id INTEGER NULL, municipality_id INTEGER NULL,
 address TEXT NULL, contact_person VARCHAR(120) NOT NULL DEFAULT '', contact_info VARCHAR(150) NOT NULL DEFAULT '',
 required_nurses INTEGER NOT NULL DEFAULT 0, active INTEGER NOT NULL DEFAULT 1,
 FOREIGN KEY (district_id) REFERENCES districts(id), FOREIGN KEY (municipality_id) REFERENCES municipalities(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
CREATE TABLE IF NOT EXISTS nurse_locations (
 nurse_id INTEGER NOT NULL, location_id INTEGER NOT NULL,
 PRIMARY KEY (nurse_id,location_id),
 FOREIGN KEY (nurse_id) REFERENCES users(id), FOREIGN KEY (location_id) REFERENCES locations(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
CREATE TABLE IF NOT EXISTS duty_types (
 id INTEGER PRIMARY KEY AUTO_INCREMENT, name VARCHAR(150) NOT NULL UNIQUE, active INTEGER NOT NULL DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
CREATE TABLE IF NOT EXISTS deployment_reasons (
 id INTEGER PRIMARY KEY AUTO_INCREMENT, name VARCHAR(150) NOT NULL UNIQUE, active INTEGER NOT NULL DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
CREATE TABLE IF NOT EXISTS shifts (
 id INTEGER PRIMARY KEY AUTO_INCREMENT, name VARCHAR(150) NOT NULL, start_time VARCHAR(5) NOT NULL, end_time VARCHAR(5) NOT NULL, active INTEGER NOT NULL DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
CREATE TABLE IF NOT EXISTS schedules (
 id INTEGER PRIMARY KEY AUTO_INCREMENT, nurse_id INTEGER NOT NULL, location_id INTEGER NOT NULL, duty_type_id INTEGER NOT NULL,
 starts_at DATETIME NOT NULL, ends_at DATETIME NOT NULL, status VARCHAR(20) NOT NULL DEFAULT 'Scheduled',
 remarks TEXT NULL, created_by INTEGER NOT NULL, created_at DATETIME NOT NULL, updated_at DATETIME NOT NULL,
 FOREIGN KEY (nurse_id) REFERENCES users(id), FOREIGN KEY (location_id) REFERENCES locations(id), FOREIGN KEY (duty_type_id) REFERENCES duty_types(id), FOREIGN KEY (created_by) REFERENCES users(id),
 INDEX schedule_window (nurse_id, starts_at, ends_at), INDEX schedule_dates (starts_at, ends_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
CREATE TABLE IF NOT EXISTS activities (
 id INTEGER PRIMARY KEY AUTO_INCREMENT, title VARCHAR(190) NOT NULL, location_id INTEGER NOT NULL,
 starts_at DATETIME NOT NULL, ends_at DATETIME NOT NULL, organizer VARCHAR(190) NOT NULL DEFAULT '',
 required_nurses INTEGER NOT NULL, required_skill VARCHAR(190) NOT NULL DEFAULT '',
 reason_id INTEGER NOT NULL, instructions TEXT NULL, status VARCHAR(20) NOT NULL DEFAULT 'Open',
 created_by INTEGER NOT NULL, created_at DATETIME NOT NULL,
 FOREIGN KEY (location_id) REFERENCES locations(id), FOREIGN KEY (reason_id) REFERENCES deployment_reasons(id), FOREIGN KEY (created_by) REFERENCES users(id),
 INDEX activity_dates (starts_at, ends_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
CREATE TABLE IF NOT EXISTS deployments (
 id INTEGER PRIMARY KEY AUTO_INCREMENT, activity_id INTEGER NOT NULL, nurse_id INTEGER NOT NULL,
 location_id INTEGER NOT NULL, starts_at DATETIME NOT NULL, ends_at DATETIME NOT NULL,
 reason VARCHAR(190) NOT NULL, instructions TEXT NULL, original_assignment TEXT NULL,
 authorized_by INTEGER NOT NULL, status VARCHAR(20) NOT NULL DEFAULT 'Assigned',
 acknowledged_at DATETIME NULL, cancelled_at DATETIME NULL, cancellation_reason TEXT NULL, created_at DATETIME NOT NULL,
 FOREIGN KEY (activity_id) REFERENCES activities(id), FOREIGN KEY (nurse_id) REFERENCES users(id), FOREIGN KEY (location_id) REFERENCES locations(id), FOREIGN KEY (authorized_by) REFERENCES users(id),
 INDEX deployment_window (nurse_id, starts_at, ends_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
CREATE TABLE IF NOT EXISTS leave_records (
 id INTEGER PRIMARY KEY AUTO_INCREMENT, nurse_id INTEGER NOT NULL, type VARCHAR(60) NOT NULL,
 starts_at DATETIME NOT NULL, ends_at DATETIME NOT NULL, reason TEXT NOT NULL,
 status VARCHAR(20) NOT NULL DEFAULT 'Submitted', reviewed_by INTEGER NULL, reviewed_at DATETIME NULL,
 review_note TEXT NULL, attachment VARCHAR(100) NULL, created_at DATETIME NOT NULL,
 FOREIGN KEY (nurse_id) REFERENCES users(id), FOREIGN KEY (reviewed_by) REFERENCES users(id), INDEX leave_window (nurse_id, starts_at, ends_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
CREATE TABLE IF NOT EXISTS availability_records (
 id INTEGER PRIMARY KEY AUTO_INCREMENT, nurse_id INTEGER NOT NULL, starts_at DATETIME NOT NULL, ends_at DATETIME NOT NULL,
 status VARCHAR(30) NOT NULL, reason TEXT NULL, created_at DATETIME NOT NULL,
 FOREIGN KEY (nurse_id) REFERENCES users(id), INDEX availability_window (nurse_id, starts_at, ends_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
CREATE TABLE IF NOT EXISTS notifications (
 id INTEGER PRIMARY KEY AUTO_INCREMENT, user_id INTEGER NOT NULL, title VARCHAR(190) NOT NULL,
 message TEXT NOT NULL, link VARCHAR(190) NOT NULL DEFAULT '?page=dashboard', read_at DATETIME NULL, created_at DATETIME NOT NULL,
 FOREIGN KEY (user_id) REFERENCES users(id), INDEX notification_user (user_id, read_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
CREATE TABLE IF NOT EXISTS audit_logs (
 id INTEGER PRIMARY KEY AUTO_INCREMENT, user_id INTEGER NULL, actor_name VARCHAR(120) NOT NULL, role VARCHAR(20) NOT NULL,
 action VARCHAR(80) NOT NULL, record_type VARCHAR(60) NOT NULL, record_id INTEGER NULL,
 old_value MEDIUMTEXT NULL, new_value MEDIUMTEXT NULL, reason TEXT NULL, created_at DATETIME NOT NULL,
 INDEX audit_date (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
CREATE TABLE IF NOT EXISTS login_attempts (
 id INTEGER PRIMARY KEY AUTO_INCREMENT, identity_hash VARCHAR(64) NOT NULL, ip_hash VARCHAR(64) NOT NULL,
 succeeded INTEGER NOT NULL DEFAULT 0, created_at DATETIME NOT NULL, INDEX login_identity (identity_hash, created_at), INDEX login_ip (ip_hash, created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
CREATE TABLE IF NOT EXISTS system_settings (
 setting_key VARCHAR(100) PRIMARY KEY, setting_value TEXT NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
