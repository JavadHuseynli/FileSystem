<?php
require_once __DIR__ . '/includes/error_handler.php';

// SQLite database - no server required
$db_file = __DIR__ . '/file_system.db';

try {
    $pdo = new PDO("sqlite:$db_file");
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    // Create tables if they don't exist
    $pdo->exec("CREATE TABLE IF NOT EXISTS students (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        student_code VARCHAR(50) NOT NULL,
        full_name VARCHAR(100) NOT NULL,
        group_number VARCHAR(20) NOT NULL,
        assigned_code VARCHAR(50),
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    )");

    $pdo->exec("CREATE TABLE IF NOT EXISTS uploads (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        student_id INTEGER,
        file_name VARCHAR(255) NOT NULL,
        original_name VARCHAR(255) NOT NULL,
        file_type VARCHAR(50) NOT NULL,
        folder_path VARCHAR(255),
        upload_date TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (student_id) REFERENCES students(id)
    )");

    $pdo->exec("CREATE TABLE IF NOT EXISTS admin_users (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        username VARCHAR(100) NOT NULL UNIQUE,
        password VARCHAR(255) NOT NULL,
        full_name VARCHAR(100) NOT NULL,
        role VARCHAR(50) DEFAULT 'teacher',
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    )");

    // Permissions table
    $pdo->exec("CREATE TABLE IF NOT EXISTS permissions (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        name VARCHAR(100) NOT NULL UNIQUE,
        description VARCHAR(255),
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    )");

    // User permissions table
    $pdo->exec("CREATE TABLE IF NOT EXISTS user_permissions (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        user_id INTEGER NOT NULL,
        permission_name VARCHAR(100) NOT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (user_id) REFERENCES admin_users(id) ON DELETE CASCADE
    )");

    // Teacher subjects table
    $pdo->exec("CREATE TABLE IF NOT EXISTS teacher_subjects (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        teacher_id INTEGER NOT NULL,
        subject_code VARCHAR(50) NOT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (teacher_id) REFERENCES admin_users(id) ON DELETE CASCADE,
        UNIQUE(teacher_id, subject_code)
    )");

    // Grades table - for grading student works
    $pdo->exec("CREATE TABLE IF NOT EXISTS grades (
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
    )");

    // Default admin istifadəçisini əlavə et
    $stmt = $pdo->query("SELECT COUNT(*) FROM admin_users");
    if ($stmt->fetchColumn() == 0) {
        $default_password = password_hash('testpassword', PASSWORD_DEFAULT); // Changed password
        $pdo->exec("INSERT INTO admin_users (username, password, full_name, role) VALUES ('admin', '$default_password', 'Administrator', 'admin')");
    }

    // Default səlahiyyətləri yarat
    $default_permissions = [
        ['view_students', 'Tələbələri görə bilmə'],
        ['view_statistics', 'Statistikaları görə bilmə'],
        ['download_files', 'Faylları yükləyə bilmə'],
        ['manage_users', 'İstifadəçiləri idarə etmə']
    ];

    $stmt = $pdo->query("SELECT COUNT(*) FROM permissions");
    if ($stmt->fetchColumn() == 0) {
        foreach ($default_permissions as $perm) {
            $pdo->prepare("INSERT INTO permissions (name, description) VALUES (?, ?)")
                ->execute($perm);
        }
    }

    // Add assigned_code column if it doesn't exist
    try {
        $pdo->exec("ALTER TABLE students ADD COLUMN assigned_code VARCHAR(50)");
    } catch (PDOException $e) {
        // Column already exists, ignore error
    }

    // Auto-backup (once per day)
    require_once __DIR__ . '/includes/backup.php';
    autoBackup($pdo);

} catch(PDOException $e) {
    die("Connection failed: " . $e->getMessage());
}

// Load cache system
require_once __DIR__ . '/includes/cache.php';
?>