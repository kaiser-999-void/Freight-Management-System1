-- Freight Management System HR2 Database Schema
-- MySQL Database

CREATE DATABASE IF NOT EXISTS freight_hr_system CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE freight_hr_system;

-- Users/Employees Table
CREATE TABLE IF NOT EXISTS users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(50) UNIQUE NOT NULL,
    password VARCHAR(255) NOT NULL,
    employee_id VARCHAR(20) UNIQUE,
    full_name VARCHAR(100) NOT NULL,
    email VARCHAR(100) NOT NULL,
    phone VARCHAR(20),
    address TEXT,
    city VARCHAR(100),
    date_of_birth DATE,
    hire_date DATE NOT NULL,
    department VARCHAR(100),
    position VARCHAR(100),
    role VARCHAR(50) NOT NULL DEFAULT 'Employee',
    manager_id INT,
    employment_type VARCHAR(50) DEFAULT 'Full-time',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (manager_id) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB;

-- Critical Roles Table
CREATE TABLE IF NOT EXISTS critical_roles (
    id INT AUTO_INCREMENT PRIMARY KEY,
    title VARCHAR(200) NOT NULL,
    department VARCHAR(100),
    current_holder_id INT,
    risk_level ENUM('Low', 'Medium', 'High') DEFAULT 'Medium',
    retirement_date DATE,
    succession_readiness INT DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (current_holder_id) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB;

-- Successors Table (Many-to-Many: Critical Roles to Employees)
CREATE TABLE IF NOT EXISTS successors (
    id INT AUTO_INCREMENT PRIMARY KEY,
    critical_role_id INT NOT NULL,
    employee_id INT NOT NULL,
    readiness_score INT DEFAULT 0,
    notes TEXT,
    added_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (critical_role_id) REFERENCES critical_roles(id) ON DELETE CASCADE,
    FOREIGN KEY (employee_id) REFERENCES users(id) ON DELETE CASCADE,
    UNIQUE KEY unique_successor (critical_role_id, employee_id)
) ENGINE=InnoDB;

-- High Potential Employees
CREATE TABLE IF NOT EXISTS high_potential_employees (
    id INT AUTO_INCREMENT PRIMARY KEY,
    employee_id INT NOT NULL,
    `current_role` VARCHAR(100),
    years_of_service INT,
    performance_rating DECIMAL(3,1),
    potential_score INT,
    target_role VARCHAR(200),
    development_areas TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (employee_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- Training Programs Table
CREATE TABLE IF NOT EXISTS training_programs (
    id INT AUTO_INCREMENT PRIMARY KEY,
    title VARCHAR(200) NOT NULL,
    category VARCHAR(100),
    duration VARCHAR(50),
    status ENUM('Upcoming', 'In Progress', 'Completed') DEFAULT 'Upcoming',
    start_date DATE,
    end_date DATE,
    instructor VARCHAR(100),
    description TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- Training Participants (Many-to-Many)
CREATE TABLE IF NOT EXISTS training_participants (
    id INT AUTO_INCREMENT PRIMARY KEY,
    training_program_id INT NOT NULL,
    employee_id INT NOT NULL,
    completion_percentage INT DEFAULT 0,
    status ENUM('Enrolled', 'In Progress', 'Completed', 'Dropped') DEFAULT 'Enrolled',
    enrolled_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    completed_at TIMESTAMP NULL,
    FOREIGN KEY (training_program_id) REFERENCES training_programs(id) ON DELETE CASCADE,
    FOREIGN KEY (employee_id) REFERENCES users(id) ON DELETE CASCADE,
    UNIQUE KEY unique_participant (training_program_id, employee_id)
) ENGINE=InnoDB;

-- Training Schedule
CREATE TABLE IF NOT EXISTS training_schedule (
    id INT AUTO_INCREMENT PRIMARY KEY,
    training_program_id INT NOT NULL,
    session_date DATE NOT NULL,
    session_time TIME NOT NULL,
    session_type VARCHAR(200),
    location VARCHAR(200),
    instructor VARCHAR(100),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (training_program_id) REFERENCES training_programs(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- Competency Gaps
CREATE TABLE IF NOT EXISTS competency_gaps (
    id INT AUTO_INCREMENT PRIMARY KEY,
    employee_id INT NOT NULL,
    role VARCHAR(100),
    department VARCHAR(100),
    required_competencies INT DEFAULT 0,
    current_competencies INT DEFAULT 0,
    gap_percentage INT DEFAULT 0,
    critical_gaps TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (employee_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- Skill Assessments
CREATE TABLE IF NOT EXISTS skill_assessments (
    id INT AUTO_INCREMENT PRIMARY KEY,
    employee_id INT NOT NULL,
    role VARCHAR(100),
    assessment_date DATE NOT NULL,
    overall_score INT,
    status ENUM('Scheduled', 'In Progress', 'Completed') DEFAULT 'Scheduled',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (employee_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- Assessment Categories
CREATE TABLE IF NOT EXISTS assessment_categories (
    id INT AUTO_INCREMENT PRIMARY KEY,
    assessment_id INT NOT NULL,
    category_name VARCHAR(100) NOT NULL,
    score INT,
    level ENUM('Beginner', 'Intermediate', 'Advanced', 'Expert') DEFAULT 'Beginner',
    FOREIGN KEY (assessment_id) REFERENCES skill_assessments(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- Competency Matrix
CREATE TABLE IF NOT EXISTS competency_matrix (
    id INT AUTO_INCREMENT PRIMARY KEY,
    competency VARCHAR(200) NOT NULL,
    required_level ENUM('Beginner', 'Intermediate', 'Advanced', 'Expert') DEFAULT 'Intermediate',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- Employee Competencies
CREATE TABLE IF NOT EXISTS employee_competencies (
    id INT AUTO_INCREMENT PRIMARY KEY,
    competency_id INT NOT NULL,
    employee_id INT NOT NULL,
    level ENUM('Beginner', 'Intermediate', 'Advanced', 'Expert') DEFAULT 'Beginner',
    has_gap BOOLEAN DEFAULT FALSE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (competency_id) REFERENCES competency_matrix(id) ON DELETE CASCADE,
    FOREIGN KEY (employee_id) REFERENCES users(id) ON DELETE CASCADE,
    UNIQUE KEY unique_competency (competency_id, employee_id)
) ENGINE=InnoDB;

-- Payslips
CREATE TABLE IF NOT EXISTS payslips (
    id INT AUTO_INCREMENT PRIMARY KEY,
    employee_id INT NOT NULL,
    month VARCHAR(20) NOT NULL,
    period_start DATE,
    period_end DATE,
    gross_pay DECIMAL(10,2) NOT NULL,
    deductions DECIMAL(10,2) DEFAULT 0,
    net_pay DECIMAL(10,2) NOT NULL,
    status ENUM('Pending', 'Paid', 'Cancelled') DEFAULT 'Pending',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (employee_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- Leave Requests
CREATE TABLE IF NOT EXISTS leave_requests (
    id INT AUTO_INCREMENT PRIMARY KEY,
    employee_id INT NOT NULL,
    leave_type ENUM('Annual Leave', 'Sick Leave', 'Personal Leave', 'Other') NOT NULL,
    start_date DATE NOT NULL,
    end_date DATE NOT NULL,
    days INT NOT NULL,
    status ENUM('Pending', 'Approved', 'Rejected', 'Cancelled') DEFAULT 'Pending',
    applied_date DATE NOT NULL,
    approver_id INT,
    notes TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (employee_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (approver_id) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB;

-- Leave Balance
CREATE TABLE IF NOT EXISTS leave_balance (
    id INT AUTO_INCREMENT PRIMARY KEY,
    employee_id INT NOT NULL,
    annual_total INT DEFAULT 20,
    annual_used INT DEFAULT 0,
    annual_remaining INT DEFAULT 20,
    sick_total INT DEFAULT 10,
    sick_used INT DEFAULT 0,
    sick_remaining INT DEFAULT 10,
    personal_total INT DEFAULT 5,
    personal_used INT DEFAULT 0,
    personal_remaining INT DEFAULT 5,
    year INT NOT NULL,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (employee_id) REFERENCES users(id) ON DELETE CASCADE,
    UNIQUE KEY unique_balance (employee_id, year)
) ENGINE=InnoDB;

-- Attendance Records
CREATE TABLE IF NOT EXISTS attendance_records (
    id INT AUTO_INCREMENT PRIMARY KEY,
    employee_id INT NOT NULL,
    attendance_date DATE NOT NULL,
    check_in TIME,
    check_out TIME,
    hours DECIMAL(4,2),
    status ENUM('Present', 'Absent', 'Sick Leave', 'Annual Leave', 'Personal Leave', 'Holiday') DEFAULT 'Present',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (employee_id) REFERENCES users(id) ON DELETE CASCADE,
    UNIQUE KEY unique_attendance (employee_id, attendance_date)
) ENGINE=InnoDB;

-- Notifications
CREATE TABLE IF NOT EXISTS notifications (
    id INT AUTO_INCREMENT PRIMARY KEY,
    employee_id INT NOT NULL,
    type ENUM('training', 'promotion', 'leave', 'certificate', 'succession', 'assessment', 'other') NOT NULL,
    title VARCHAR(200) NOT NULL,
    message TEXT NOT NULL,
    priority ENUM('low', 'normal', 'high') DEFAULT 'normal',
    is_read BOOLEAN DEFAULT FALSE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (employee_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- Learning Courses
CREATE TABLE IF NOT EXISTS learning_courses (
    id INT AUTO_INCREMENT PRIMARY KEY,
    title VARCHAR(200) NOT NULL,
    category VARCHAR(100),
    duration VARCHAR(50),
    level ENUM('Beginner', 'Intermediate', 'Advanced') DEFAULT 'Beginner',
    rating DECIMAL(3,1) DEFAULT 0,
    reviews_count INT DEFAULT 0,
    enrolled_count INT DEFAULT 0,
    instructor VARCHAR(100),
    description TEXT,
    modules_count INT DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- Course Enrollments
CREATE TABLE IF NOT EXISTS course_enrollments (
    id INT AUTO_INCREMENT PRIMARY KEY,
    course_id INT NOT NULL,
    employee_id INT NOT NULL,
    progress INT DEFAULT 0,
    completed_modules INT DEFAULT 0,
    total_modules INT DEFAULT 0,
    time_spent VARCHAR(50),
    status ENUM('Enrolled', 'In Progress', 'Completed', 'Dropped') DEFAULT 'Enrolled',
    last_accessed DATE,
    enrolled_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    completed_at TIMESTAMP NULL,
    FOREIGN KEY (course_id) REFERENCES learning_courses(id) ON DELETE CASCADE,
    FOREIGN KEY (employee_id) REFERENCES users(id) ON DELETE CASCADE,
    UNIQUE KEY unique_enrollment (course_id, employee_id)
) ENGINE=InnoDB;

-- Certificates
CREATE TABLE IF NOT EXISTS certificates (
    id INT AUTO_INCREMENT PRIMARY KEY,
    employee_id INT NOT NULL,
    course_id INT,
    certificate_name VARCHAR(200) NOT NULL,
    certificate_id VARCHAR(50) UNIQUE,
    issue_date DATE NOT NULL,
    instructor VARCHAR(100),
    score INT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (employee_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (course_id) REFERENCES learning_courses(id) ON DELETE SET NULL
) ENGINE=InnoDB;

-- Badges
CREATE TABLE IF NOT EXISTS badges (
    id INT AUTO_INCREMENT PRIMARY KEY,
    employee_id INT NOT NULL,
    badge_name VARCHAR(100) NOT NULL,
    description TEXT,
    icon VARCHAR(10),
    earned_date DATE NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (employee_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- Examinations
CREATE TABLE IF NOT EXISTS examinations (
    id INT AUTO_INCREMENT PRIMARY KEY,
    course_id INT NOT NULL,
    employee_id INT NOT NULL,
    exam_date DATE NOT NULL,
    duration VARCHAR(50),
    status ENUM('Scheduled', 'In Progress', 'Passed', 'Failed', 'Cancelled') DEFAULT 'Scheduled',
    attempts_allowed INT DEFAULT 3,
    passing_score INT DEFAULT 80,
    score INT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (course_id) REFERENCES learning_courses(id) ON DELETE CASCADE,
    FOREIGN KEY (employee_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- Retirement Forecasts
CREATE TABLE IF NOT EXISTS retirement_forecasts (
    id INT AUTO_INCREMENT PRIMARY KEY,
    year INT NOT NULL,
    total_retirements INT DEFAULT 0,
    critical_roles_count INT DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY unique_year (year)
) ENGINE=InnoDB;
-- Insert Sample Data
-- (All sample data removed; schema1.sql now defines tables only.)
INSERT INTO users (username, password, employee_id, full_name, email, phone, address, city, date_of_birth, hire_date, department, position, role, employment_type)
SELECT 'employee', '$2y$10$uUhM55hXYVJ6OI5JxBpN5uQF9.SC01JR4K7qMdv/kzumnZKGEPRW2', 'EMP002', 'John Smith', 'john.smith@freighthr.com', '+1 234 567 8900', '123 Main St', 'New York', '1990-05-15', '2020-01-10', 'Operations', 'Logistics Coordinator', 'Employee', 'Full-time'
WHERE NOT EXISTS (SELECT 1 FROM users WHERE username = 'employee');

INSERT INTO users (username, password, employee_id, full_name, email, phone, address, city, date_of_birth, hire_date, department, position, role, employment_type)
SELECT 'admin', '$2y$10$uUhM55hXYVJ6OI5JxBpN5uQF9.SC01JR4K7qMdv/kzumnZKGEPRW2', 'EMP003', 'John Lemon', 'johnlemon@freighthr.com', '+1 234 567 8901', '456 Oak Ave', 'Los Angeles', '1985-03-20', '2015-06-01', 'Operations', 'Senior Operations Manager', 'Employee', 'Full-time'
WHERE NOT EXISTS (SELECT 1 FROM users WHERE username = 'david.lee');

INSERT INTO users (username, password, employee_id, full_name, email, phone, address, city, date_of_birth, hire_date, department, position, role, employment_type)
SELECT 'sarah.martinez', '$2y$10$uUhM55hXYVJ6OI5JxBpN5uQF9.SC01JR4K7qMdv/kzumnZKGEPRW2', 'EMP004', 'Sarah Martinez', 'sarah.martinez@freighthr.com', '+1 234 567 8902', '789 Pine St', 'Chicago', '1988-07-12', '2017-02-15', 'Operations', 'Operations Manager', 'Employee', 'Full-time'
WHERE NOT EXISTS (SELECT 1 FROM users WHERE username = 'sarah.martinez');

-- Update manager relationships
UPDATE users SET manager_id = 1 WHERE id IN (2, 3, 4);

INSERT INTO critical_roles (title, department, current_holder_id, risk_level, retirement_date, succession_readiness)
SELECT 'Fleet Operations Director', 'Operations', u.id, 'High', '2025-08-15', 75
FROM users u
WHERE u.username = 'david.lee'
  AND NOT EXISTS (SELECT 1 FROM critical_roles WHERE title = 'Fleet Operations Director');

INSERT INTO critical_roles (title, department, current_holder_id, risk_level, retirement_date, succession_readiness)
SELECT 'Regional Logistics Manager', 'Logistics', u.id, 'Medium', '2026-03-20', 60
FROM users u
WHERE u.username = 'sarah.martinez'
  AND NOT EXISTS (SELECT 1 FROM critical_roles WHERE title = 'Regional Logistics Manager');

INSERT INTO critical_roles (title, department, current_holder_id, risk_level, retirement_date, succession_readiness)
SELECT 'Safety & Compliance Head', 'Compliance', NULL, 'High', '2025-11-30', 45
WHERE NOT EXISTS (SELECT 1 FROM critical_roles WHERE title = 'Safety & Compliance Head');

INSERT INTO successors (critical_role_id, employee_id, readiness_score, added_at)
SELECT cr.id, u.id, 75, NOW()
FROM critical_roles cr
JOIN users u ON u.username = 'david.lee'
WHERE cr.title = 'Fleet Operations Director'
  AND NOT EXISTS (
      SELECT 1
      FROM successors s
      JOIN critical_roles cr2 ON cr2.id = s.critical_role_id
      JOIN users u2 ON u2.id = s.employee_id
      WHERE cr2.title = 'Fleet Operations Director'
        AND u2.username = 'david.lee'
  );

INSERT INTO successors (critical_role_id, employee_id, readiness_score, added_at)
SELECT cr.id, u.id, 70, NOW()
FROM critical_roles cr
JOIN users u ON u.username = 'sarah.martinez'
WHERE cr.title = 'Fleet Operations Director'
  AND NOT EXISTS (
      SELECT 1
      FROM successors s
      JOIN critical_roles cr2 ON cr2.id = s.critical_role_id
      JOIN users u2 ON u2.id = s.employee_id
      WHERE cr2.title = 'Fleet Operations Director'
        AND u2.username = 'sarah.martinez'
  );

INSERT INTO successors (critical_role_id, employee_id, readiness_score, added_at)
SELECT cr.id, u.id, 65, NOW()
FROM critical_roles cr
JOIN users u ON u.username = 'david.lee'
WHERE cr.title = 'Regional Logistics Manager'
  AND NOT EXISTS (
      SELECT 1
      FROM successors s
      JOIN critical_roles cr2 ON cr2.id = s.critical_role_id
      JOIN users u2 ON u2.id = s.employee_id
      WHERE cr2.title = 'Regional Logistics Manager'
        AND u2.username = 'david.lee'
  );

INSERT INTO successors (critical_role_id, employee_id, readiness_score, added_at)
SELECT cr.id, u.id, 60, NOW()
FROM critical_roles cr
JOIN users u ON u.username = 'sarah.martinez'
WHERE cr.title = 'Regional Logistics Manager'
  AND NOT EXISTS (
      SELECT 1
      FROM successors s
      JOIN critical_roles cr2 ON cr2.id = s.critical_role_id
      JOIN users u2 ON u2.id = s.employee_id
      WHERE cr2.title = 'Regional Logistics Manager'
        AND u2.username = 'sarah.martinez'
  );

INSERT INTO high_potential_employees (employee_id, `current_role`, years_of_service, performance_rating, potential_score, target_role, development_areas)
SELECT u.id, 'Senior Operations Manager', 8, 4.8, 92, 'Fleet Operations Director', 'Strategic Planning, Executive Leadership'
FROM users u
WHERE u.username = 'david.lee'
  AND NOT EXISTS (
      SELECT 1 FROM high_potential_employees hpe
      WHERE hpe.employee_id = u.id
        AND hpe.target_role = 'Fleet Operations Director'
  );

INSERT INTO high_potential_employees (employee_id, `current_role`, years_of_service, performance_rating, potential_score, target_role, development_areas)
SELECT u.id, 'Operations Manager', 6, 4.6, 88, 'Fleet Operations Director', 'Financial Management, Change Management'
FROM users u
WHERE u.username = 'sarah.martinez'
  AND NOT EXISTS (
      SELECT 1 FROM high_potential_employees hpe
      WHERE hpe.employee_id = u.id
        AND hpe.target_role = 'Fleet Operations Director'
  );

INSERT INTO high_potential_employees (employee_id, `current_role`, years_of_service, performance_rating, potential_score, target_role, development_areas)
SELECT u.id, 'Logistics Coordinator', 5, 4.7, 85, 'Regional Logistics Manager', 'Team Leadership, Budget Management'
FROM users u
WHERE u.username = 'employee'
  AND NOT EXISTS (
      SELECT 1 FROM high_potential_employees hpe
      WHERE hpe.employee_id = u.id
        AND hpe.target_role = 'Regional Logistics Manager'
  );

-- Training Programs
INSERT INTO training_programs (title, category, duration, status, start_date, end_date, instructor, description) VALUES
('New Hire Orientation', 'Onboarding', '3 days', 'In Progress', '2025-01-15', '2025-01-17', 'Dr. Michael Roberts', 'Comprehensive orientation for new employees'),
('Safety & Compliance Orientation', 'Safety', '2 days', 'Completed', '2024-12-01', '2024-12-02', 'Sarah Thompson', 'Safety protocols and compliance requirements'),
('Operations Department Orientation', 'Department', '1 week', 'Upcoming', '2025-02-01', '2025-02-07', 'Jennifer Martinez', 'Department-specific orientation'),
('Fleet Management Orientation', 'Operations', '5 days', 'In Progress', '2025-01-20', '2025-01-24', 'David Chen', 'Fleet management fundamentals');

INSERT INTO training_participants (training_program_id, employee_id, completion_percentage, status)
SELECT tp.id, u.id, 65, 'In Progress'
FROM users u
JOIN training_programs tp ON tp.title = 'New Hire Orientation'
WHERE u.username = 'employee'
  AND NOT EXISTS (
      SELECT 1 FROM training_participants tp2
      WHERE tp2.training_program_id = tp.id
        AND tp2.employee_id = u.id
  );

INSERT INTO training_participants (training_program_id, employee_id, completion_percentage, status)
SELECT tp.id, u.id, 70, 'In Progress'
FROM users u
JOIN training_programs tp ON tp.title = 'New Hire Orientation'
WHERE u.username = 'david.lee'
  AND NOT EXISTS (
      SELECT 1 FROM training_participants tp2
      WHERE tp2.training_program_id = tp.id
        AND tp2.employee_id = u.id
  );

INSERT INTO training_participants (training_program_id, employee_id, completion_percentage, status)
SELECT tp.id, u.id, 100, 'Completed'
FROM users u
JOIN training_programs tp ON tp.title = 'Safety & Compliance Orientation'
WHERE u.username = 'employee'
  AND NOT EXISTS (
      SELECT 1 FROM training_participants tp2
      WHERE tp2.training_program_id = tp.id
        AND tp2.employee_id = u.id
  );

INSERT INTO training_participants (training_program_id, employee_id, completion_percentage, status)
SELECT tp.id, u.id, 100, 'Completed'
FROM users u
JOIN training_programs tp ON tp.title = 'Safety & Compliance Orientation'
WHERE u.username = 'david.lee'
  AND NOT EXISTS (
      SELECT 1 FROM training_participants tp2
      WHERE tp2.training_program_id = tp.id
        AND tp2.employee_id = u.id
  );

INSERT INTO training_participants (training_program_id, employee_id, completion_percentage, status)
SELECT tp.id, u.id, 40, 'In Progress'
FROM users u
JOIN training_programs tp ON tp.title = 'Fleet Management Orientation'
WHERE u.username = 'employee'
  AND NOT EXISTS (
      SELECT 1 FROM training_participants tp
      WHERE tp.training_program_id = 4
        AND tp.employee_id = u.id
  );

-- Learning Courses
INSERT INTO learning_courses (title, category, duration, level, rating, reviews_count, enrolled_count, instructor, description, modules_count) VALUES
('Advanced Fleet Management Techniques', 'Operations', '6 hours', 'Advanced', 4.8, 124, 89, 'Dr. Michael Roberts', 'Master advanced strategies for managing large-scale fleet operations', 8),
('Safety & Compliance Fundamentals', 'Compliance', '4 hours', 'Beginner', 4.9, 256, 187, 'Sarah Thompson', 'Essential safety protocols and compliance requirements for freight operations', 6),
('Leadership in Logistics', 'Leadership', '8 hours', 'Intermediate', 4.7, 98, 67, 'Jennifer Martinez', 'Develop leadership skills specific to logistics and supply chain management', 10),
('Route Optimization & Analytics', 'Technology', '5 hours', 'Advanced', 4.6, 76, 54, 'David Chen', 'Learn data-driven approaches to optimize delivery routes and reduce costs', 7);

INSERT INTO course_enrollments (course_id, employee_id, progress, completed_modules, total_modules, time_spent, status, last_accessed)
SELECT 1, u.id, 45, 4, 8, '2.5 hours', 'In Progress', '2025-01-12'
FROM users u
WHERE u.username = 'admin'
  AND NOT EXISTS (
      SELECT 1 FROM course_enrollments ce
      WHERE ce.course_id = 1
        AND ce.employee_id = u.id
  );

INSERT INTO course_enrollments (course_id, employee_id, progress, completed_modules, total_modules, time_spent, status, last_accessed)
SELECT 2, u.id, 100, 6, 6, '4 hours', 'Completed', '2024-12-28'
FROM users u
WHERE u.username = 'admin'
  AND NOT EXISTS (
      SELECT 1 FROM course_enrollments ce
      WHERE ce.course_id = 2
        AND ce.employee_id = u.id
  );

-- Certificate seed data
INSERT INTO certificates (employee_id, course_id, certificate_name, certificate_id, issue_date, instructor, score)
SELECT u.id, 2, 'Safety & Compliance Fundamentals', 'CERT-2024-12345', '2024-12-28', 'Sarah Thompson', 95
FROM users u
WHERE u.username = 'admin'
  AND NOT EXISTS (
      SELECT 1 FROM certificates c
      WHERE c.certificate_id = 'CERT-2024-12345'
  );

-- Examinations seed data
INSERT INTO examinations (course_id, employee_id, exam_date, duration, status, attempts_allowed, passing_score, score)
SELECT 2, u.id, '2024-12-28', '60 minutes', 'Passed', 1, 80, 95
FROM users u
WHERE u.username = 'admin'
  AND NOT EXISTS (
      SELECT 1 FROM examinations e
      WHERE e.course_id = 2
        AND e.employee_id = u.id
  );

-- Badges
INSERT INTO badges (employee_id, badge_name, description, icon, earned_date) VALUES
(1, 'Quick Learner', 'Completed 3 courses in a month', '🚀', '2024-12-28'),
(1, 'Perfect Score', 'Achieved 100% on a course exam', '💯', '2024-12-28'),
(1, 'Compliance Expert', 'Completed all compliance courses', '🛡️', '2024-11-20'),
(1, 'Knowledge Seeker', 'Enrolled in 5+ courses', '📚', '2024-10-15');

-- Leave Balance
INSERT INTO leave_balance (employee_id, annual_total, annual_used, annual_remaining, sick_total, sick_used, sick_remaining, personal_total, personal_used, personal_remaining, year) VALUES
(1, 20, 7, 13, 10, 2, 8, 5, 1, 4, 2025);

-- Leave Requests
INSERT INTO leave_requests (employee_id, leave_type, start_date, end_date, days, status, applied_date, approver_id) VALUES
(1, 'Annual Leave', '2025-02-10', '2025-02-14', 5, 'Approved', '2025-01-05', 1),
(1, 'Sick Leave', '2025-01-08', '2025-01-09', 2, 'Approved', '2025-01-07', 1),
(1, 'Annual Leave', '2025-03-15', '2025-03-20', 6, 'Pending', '2025-01-10', 1);

-- Payslips
INSERT INTO payslips (employee_id, month, period_start, period_end, gross_pay, deductions, net_pay, status) VALUES
(1, 'December 2024', '2024-12-01', '2024-12-31', 6500.00, 1450.00, 5050.00, 'Paid'),
(1, 'November 2024', '2024-11-01', '2024-11-30', 6500.00, 1450.00, 5050.00, 'Paid'),
(1, 'October 2024', '2024-10-01', '2024-10-31', 6500.00, 1450.00, 5050.00, 'Paid');

-- Attendance Records
INSERT INTO attendance_records (employee_id, attendance_date, check_in, check_out, hours, status) VALUES
(1, '2025-01-13', '08:45:00', '17:30:00', 8.75, 'Present'),
(1, '2025-01-12', '08:50:00', '17:25:00', 8.58, 'Present'),
(1, '2025-01-11', '09:00:00', '17:30:00', 8.50, 'Present'),
(1, '2025-01-10', '08:40:00', '17:35:00', 8.92, 'Present'),
(1, '2025-01-09', NULL, NULL, 0, 'Sick Leave');

-- Notifications
INSERT INTO notifications (employee_id, type, title, message, priority, is_read) VALUES
(1, 'training', 'New Training Program Available', 'Leadership Development Program is now available for enrollment', 'normal', FALSE),
(1, 'promotion', 'Promotion Eligibility Update', 'You are now eligible for Senior Operations Manager position', 'high', FALSE),
(1, 'leave', 'Leave Request Approved', 'Your annual leave request from Feb 10-14 has been approved', 'normal', TRUE),
(1, 'certificate', 'Certificate Awarded', 'Congratulations! You have earned Safety & Compliance Fundamentals certificate', 'normal', TRUE),
(1, 'succession', 'Succession Planning Update', 'You have been identified as a successor for Fleet Operations Director role', 'high', TRUE);

-- Retirement Forecasts
INSERT INTO retirement_forecasts (year, total_retirements, critical_roles_count) VALUES
(2025, 8, 3),
(2026, 12, 5),
(2027, 15, 4),
(2028, 10, 2);

-- Competency Gaps
INSERT INTO competency_gaps (employee_id, role, department, required_competencies, current_competencies, gap_percentage, critical_gaps) VALUES
(2, 'Logistics Coordinator', 'Operations', 8, 6, 25, 'Advanced Route Optimization, Fleet Cost Analysis'),
(4, 'Operations Manager', 'Operations', 10, 9, 10, 'Strategic Planning'),
(3, 'Fleet Supervisor', 'Fleet Management', 7, 5, 29, 'Team Leadership, Performance Management');

-- Skill Assessments
INSERT INTO skill_assessments (employee_id, role, assessment_date, overall_score, status) VALUES
(3, 'Senior Operations Manager', '2025-01-05', 88, 'Completed'),
(4, 'Operations Manager', '2025-01-08', 82, 'Completed'),
(2, 'Logistics Coordinator', '2025-01-12', NULL, 'Scheduled');

-- Assessment Categories
INSERT INTO assessment_categories (assessment_id, category_name, score, level) VALUES
(1, 'Technical Skills', 92, 'Expert'),
(1, 'Leadership', 85, 'Advanced'),
(1, 'Communication', 90, 'Expert'),
(1, 'Problem Solving', 86, 'Advanced'),
(2, 'Technical Skills', 88, 'Advanced'),
(2, 'Leadership', 78, 'Intermediate'),
(2, 'Communication', 85, 'Advanced'),
(2, 'Problem Solving', 80, 'Advanced');

-- Competency Matrix
INSERT INTO competency_matrix (competency, required_level) VALUES
('Fleet Operations Management', 'Expert'),
('Safety & Compliance', 'Advanced'),
('Route Optimization', 'Advanced');

-- Employee Competencies
INSERT INTO employee_competencies (competency_id, employee_id, level, has_gap) VALUES
(1, 3, 'Expert', FALSE),
(1, 4, 'Advanced', TRUE),
(1, 2, 'Intermediate', TRUE),
(2, 3, 'Advanced', FALSE),
(2, 4, 'Advanced', FALSE),
(2, 2, 'Intermediate', TRUE),
(3, 3, 'Expert', FALSE),
(3, 4, 'Intermediate', TRUE),
(3, 2, 'Beginner', TRUE);

-- Examinations
INSERT INTO examinations (course_id, employee_id, exam_date, duration, status, attempts_allowed, passing_score, score) VALUES
(1, 1, '2025-01-20', '90 minutes', 'Scheduled', 3, 80, NULL),
(2, 1, '2024-12-28', '60 minutes', 'Passed', 3, 80, 95);

