CREATE DATABASE IF NOT EXISTS glowwell
    CHARACTER SET utf8mb4
    COLLATE utf8mb4_unicode_ci;

USE glowwell;

CREATE TABLE IF NOT EXISTS users (
    user_id INT NOT NULL AUTO_INCREMENT,
    fullname VARCHAR(100) NOT NULL,
    email VARCHAR(254) NOT NULL,
    phone VARCHAR(20) NOT NULL,
    password VARCHAR(255) NOT NULL,
    role ENUM('admin', 'customer') NOT NULL DEFAULT 'customer',
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (user_id),
    UNIQUE KEY uq_users_email (email)
) ENGINE=InnoDB;

SET @glowwell_users_phone_exists = (
    SELECT COUNT(*)
    FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'users'
      AND COLUMN_NAME = 'phone'
);
SET @glowwell_add_users_phone = IF(
    @glowwell_users_phone_exists = 0,
    'ALTER TABLE users ADD COLUMN phone VARCHAR(20) NOT NULL DEFAULT '''' AFTER email',
    'SELECT 1'
);
PREPARE glowwell_phone_statement FROM @glowwell_add_users_phone;
EXECUTE glowwell_phone_statement;
DEALLOCATE PREPARE glowwell_phone_statement;

CREATE TABLE IF NOT EXISTS staff (
    staff_id INT NOT NULL AUTO_INCREMENT,
    fullname VARCHAR(100) NOT NULL,
    phone VARCHAR(20) NULL,
    speciality VARCHAR(100) NULL,
    status ENUM('Active', 'Inactive') NOT NULL DEFAULT 'Active',
    PRIMARY KEY (staff_id)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS staff_schedules (
    schedule_id INT NOT NULL AUTO_INCREMENT,
    staff_id INT NOT NULL,
    weekday TINYINT UNSIGNED NOT NULL COMMENT 'ISO weekday: Monday = 1, Sunday = 7',
    start_time TIME NOT NULL,
    end_time TIME NOT NULL,
    PRIMARY KEY (schedule_id),
    UNIQUE KEY uq_staff_schedules_staff_weekday (staff_id, weekday),
    CONSTRAINT chk_staff_schedules_weekday CHECK (weekday BETWEEN 1 AND 7),
    CONSTRAINT chk_staff_schedules_times CHECK (start_time < end_time),
    CONSTRAINT fk_staff_schedules_staff FOREIGN KEY (staff_id)
        REFERENCES staff (staff_id)
        ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS services (
    service_id INT NOT NULL AUTO_INCREMENT,
    service_name VARCHAR(100) NOT NULL,
    description TEXT NULL,
    duration SMALLINT UNSIGNED NOT NULL,
    price DECIMAL(10, 2) NOT NULL,
    PRIMARY KEY (service_id),
    CONSTRAINT chk_services_duration CHECK (duration > 0),
    CONSTRAINT chk_services_price CHECK (price > 0)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS appointments (
    appointment_id INT NOT NULL AUTO_INCREMENT,
    user_id INT NOT NULL,
    service_id INT NOT NULL,
    staff_id INT NOT NULL,
    appointment_date DATE NOT NULL,
    appointment_time TIME NOT NULL,
    status ENUM('Pending', 'Confirmed', 'Completed', 'Cancelled') NOT NULL DEFAULT 'Pending',
    PRIMARY KEY (appointment_id),
    KEY idx_appointments_user_date (user_id, appointment_date, appointment_time),
    KEY idx_appointments_staff_date (staff_id, appointment_date, appointment_time),
    KEY idx_appointments_service (service_id),
    CONSTRAINT fk_appointments_user FOREIGN KEY (user_id)
        REFERENCES users (user_id)
        ON DELETE RESTRICT,
    CONSTRAINT fk_appointments_service FOREIGN KEY (service_id)
        REFERENCES services (service_id)
        ON DELETE RESTRICT,
    CONSTRAINT fk_appointments_staff FOREIGN KEY (staff_id)
        REFERENCES staff (staff_id)
        ON DELETE RESTRICT
) ENGINE=InnoDB;
