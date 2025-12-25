-- Database Backup
-- Generated: 2025-12-13 18:38:14
-- Database File: file_system.db


-- Table: students
DROP TABLE IF EXISTS students;
CREATE TABLE students (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        student_code VARCHAR(50) NOT NULL,
        full_name VARCHAR(100) NOT NULL,
        group_number VARCHAR(20) NOT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    , assigned_code VARCHAR(50));

-- Data for table: students
INSERT INTO students (id, student_code, full_name, group_number, created_at, assigned_code) VALUES ('4', 'TEST', 'Allahverdiyev Azər', '1002', '2025-12-13 18:02:30', '1002a002');
INSERT INTO students (id, student_code, full_name, group_number, created_at, assigned_code) VALUES ('5', 'TEST2', 'Javad', '1002', '2025-12-13 18:30:58', '1002a003');
INSERT INTO students (id, student_code, full_name, group_number, created_at, assigned_code) VALUES ('6', 'TEST', 'Javad', '1002', '2025-12-13 18:32:02', NULL);


-- Table: uploads
DROP TABLE IF EXISTS uploads;
CREATE TABLE uploads (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        student_id INTEGER,
        file_name VARCHAR(255) NOT NULL,
        original_name VARCHAR(255) NOT NULL,
        file_type VARCHAR(50) NOT NULL,
        folder_path VARCHAR(255),
        upload_date TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (student_id) REFERENCES students(id)
    );

-- Data for table: uploads
INSERT INTO uploads (id, student_id, file_name, original_name, file_type, folder_path, upload_date) VALUES ('4', '4', '1765648960_693daa40ad223.xlsx', '+ İqtisadiyyat və idarəetmə fakültəsi 2025-2026  3-cü aralıq.xlsx', 'xlsx', 'TEST_Allahverdiyev_Azər_1002', '2025-12-13 18:02:40');
INSERT INTO uploads (id, student_id, file_name, original_name, file_type, folder_path, upload_date) VALUES ('5', '5', '1765650666_693db0ea248e9.docx', 'cavad huseynli esyalar interneti suallar.docx', 'docx', 'TEST2_Javad_1002', '2025-12-13 18:31:06');
INSERT INTO uploads (id, student_id, file_name, original_name, file_type, folder_path, upload_date) VALUES ('6', '6', '1765650726_693db126aee46.xlsx', '+ İqtisadiyyat və idarəetmə fakültəsi 2025-2026  3-cü aralıq.xlsx', 'xlsx', 'TEST_Javad_1002', '2025-12-13 18:32:06');


-- Table: admin_users
DROP TABLE IF EXISTS admin_users;
CREATE TABLE admin_users (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        username VARCHAR(100) NOT NULL UNIQUE,
        password VARCHAR(255) NOT NULL,
        full_name VARCHAR(100) NOT NULL,
        role VARCHAR(50) DEFAULT 'teacher',
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    );

-- Data for table: admin_users
INSERT INTO admin_users (id, username, password, full_name, role, created_at) VALUES ('1', 'admin', '$2y$12$JYUXKgWDD6M5m1Lcxqg5ueICUYUwhDbRLu1a84g6SqhGy7dabOGVq', 'Administrator', 'admin', '2025-12-13 08:45:20');
INSERT INTO admin_users (id, username, password, full_name, role, created_at) VALUES ('2', 'ADMIN001', '$2y$12$YgPEDfuE1eN.4Tg7Q59PteoZraawqLIYw28rkV1m2uF/57BB0bvEa', 'ADMIN001', 'teacher', '2025-12-13 09:03:36');


-- Table: permissions
DROP TABLE IF EXISTS permissions;
CREATE TABLE permissions (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        name VARCHAR(100) NOT NULL UNIQUE,
        description VARCHAR(255),
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    );

-- Data for table: permissions
INSERT INTO permissions (id, name, description, created_at) VALUES ('1', 'view_students', 'Tələbələri görə bilmə', '2025-12-13 08:54:40');
INSERT INTO permissions (id, name, description, created_at) VALUES ('2', 'view_statistics', 'Statistikaları görə bilmə', '2025-12-13 08:54:40');
INSERT INTO permissions (id, name, description, created_at) VALUES ('3', 'download_files', 'Faylları yükləyə bilmə', '2025-12-13 08:54:40');
INSERT INTO permissions (id, name, description, created_at) VALUES ('4', 'manage_users', 'İstifadəçiləri idarə etmə', '2025-12-13 08:54:40');


-- Table: user_permissions
DROP TABLE IF EXISTS user_permissions;
CREATE TABLE user_permissions (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        user_id INTEGER NOT NULL,
        permission_name VARCHAR(100) NOT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (user_id) REFERENCES admin_users(id) ON DELETE CASCADE
    );

-- Data for table: user_permissions
INSERT INTO user_permissions (id, user_id, permission_name, created_at) VALUES ('1', '2', 'download_files', '2025-12-13 09:03:36');
INSERT INTO user_permissions (id, user_id, permission_name, created_at) VALUES ('2', '2', 'view_students', '2025-12-13 09:03:36');


-- Table: teacher_subjects
DROP TABLE IF EXISTS teacher_subjects;
CREATE TABLE teacher_subjects (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        teacher_id INTEGER NOT NULL,
        subject_code VARCHAR(50) NOT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (teacher_id) REFERENCES admin_users(id) ON DELETE CASCADE,
        UNIQUE(teacher_id, subject_code)
    );

-- Data for table: teacher_subjects
INSERT INTO teacher_subjects (id, teacher_id, subject_code, created_at) VALUES ('1', '2', 'TEST', '2025-12-13 14:44:41');


-- Table: grades
DROP TABLE IF EXISTS grades;
CREATE TABLE grades (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        student_id INTEGER NOT NULL,
        teacher_id INTEGER NOT NULL,
        work_number INTEGER NOT NULL,
        grade VARCHAR(10),
        comment TEXT,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (student_id) REFERENCES students(id) ON DELETE CASCADE,
        FOREIGN KEY (teacher_id) REFERENCES admin_users(id) ON DELETE CASCADE,
        UNIQUE(student_id, work_number)
    );

