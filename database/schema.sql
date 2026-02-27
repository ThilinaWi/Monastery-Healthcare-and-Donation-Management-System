-- Monastery Healthcare and Donation Management System Database Schema
-- Created: February 27, 2026
-- Database: monastery_system

-- Create database
CREATE DATABASE IF NOT EXISTS monastery_system;
USE monastery_system;

-- 1. Admins table
CREATE TABLE admins (
    admin_id INT PRIMARY KEY AUTO_INCREMENT,
    username VARCHAR(50) UNIQUE NOT NULL,
    email VARCHAR(100) UNIQUE NOT NULL,
    password VARCHAR(255) NOT NULL,
    full_name VARCHAR(100) NOT NULL,
    phone VARCHAR(15),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    is_active BOOLEAN DEFAULT TRUE
);

-- 2. Rooms table (created before monks since monks reference rooms)
CREATE TABLE rooms (
    room_id INT PRIMARY KEY AUTO_INCREMENT,
    room_number VARCHAR(20) UNIQUE NOT NULL,
    room_type ENUM('single', 'double', 'dormitory', 'isolation') DEFAULT 'single',
    capacity INT DEFAULT 1,
    current_occupancy INT DEFAULT 0,
    description TEXT,
    is_available BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- 3. Monks table
CREATE TABLE monks (
    monk_id INT PRIMARY KEY AUTO_INCREMENT,
    username VARCHAR(50) UNIQUE NOT NULL,
    email VARCHAR(100) UNIQUE NULL,
    password VARCHAR(255) NOT NULL,
    full_name VARCHAR(100) NOT NULL,
    ordination_name VARCHAR(100),
    date_of_birth DATE,
    phone VARCHAR(15),
    emergency_contact VARCHAR(100),
    emergency_phone VARCHAR(15),
    blood_type ENUM('A+', 'A-', 'B+', 'B-', 'AB+', 'AB-', 'O+', 'O-'),
    medical_conditions TEXT,
    allergies TEXT,
    room_id INT,
    ordination_date DATE,
    temple_entry_date DATE,
    is_active BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (room_id) REFERENCES rooms(room_id) ON DELETE SET NULL
);

-- 4. Doctors table
CREATE TABLE doctors (
    doctor_id INT PRIMARY KEY AUTO_INCREMENT,
    username VARCHAR(50) UNIQUE NOT NULL,
    email VARCHAR(100) UNIQUE NOT NULL,
    password VARCHAR(255) NOT NULL,
    full_name VARCHAR(100) NOT NULL,
    phone VARCHAR(15),
    specialization VARCHAR(100),
    license_number VARCHAR(50),
    qualifications TEXT,
    availability_schedule JSON, -- Store weekly schedule as JSON
    consultation_fee DECIMAL(10,2) DEFAULT 0.00,
    is_active BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- 5. Donators table
CREATE TABLE donators (
    donator_id INT PRIMARY KEY AUTO_INCREMENT,
    username VARCHAR(50) UNIQUE NOT NULL,
    email VARCHAR(100) UNIQUE NOT NULL,
    password VARCHAR(255) NOT NULL,
    full_name VARCHAR(100) NOT NULL,
    phone VARCHAR(15),
    address TEXT,
    organization VARCHAR(100),
    preferred_contact ENUM('email', 'phone', 'both') DEFAULT 'email',
    is_anonymous BOOLEAN DEFAULT FALSE,
    total_donated DECIMAL(15,2) DEFAULT 0.00,
    is_active BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- 6. Donation categories table
CREATE TABLE donation_categories (
    category_id INT PRIMARY KEY AUTO_INCREMENT,
    category_name VARCHAR(50) UNIQUE NOT NULL,
    description TEXT,
    target_amount DECIMAL(15,2),
    current_balance DECIMAL(15,2) DEFAULT 0.00,
    priority_level ENUM('low', 'medium', 'high', 'critical') DEFAULT 'medium',
    is_active BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- 7. Donations table
CREATE TABLE donations (
    donation_id INT PRIMARY KEY AUTO_INCREMENT,
    donator_id INT NOT NULL,
    category_id INT NOT NULL,
    amount DECIMAL(15,2) NOT NULL,
    donation_method ENUM('cash', 'bank_transfer', 'cheque', 'online', 'in_kind') DEFAULT 'cash',
    reference_number VARCHAR(100),
    notes TEXT,
    receipt_number VARCHAR(100) UNIQUE,
    is_anonymous BOOLEAN DEFAULT FALSE,
    donation_date DATE NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (donator_id) REFERENCES donators(donator_id) ON DELETE CASCADE,
    FOREIGN KEY (category_id) REFERENCES donation_categories(category_id) ON DELETE RESTRICT,
    INDEX idx_donation_date (donation_date),
    INDEX idx_category (category_id),
    INDEX idx_donator (donator_id)
);

-- 8. Expenses table
CREATE TABLE expenses (
    expense_id INT PRIMARY KEY AUTO_INCREMENT,
    category_id INT NOT NULL,
    admin_id INT NOT NULL,
    amount DECIMAL(15,2) NOT NULL,
    description TEXT NOT NULL,
    receipt_number VARCHAR(100),
    vendor VARCHAR(100),
    expense_date DATE NOT NULL,
    approved_by INT,
    approval_date TIMESTAMP NULL,
    status ENUM('pending', 'approved', 'rejected') DEFAULT 'pending',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (category_id) REFERENCES donation_categories(category_id) ON DELETE RESTRICT,
    FOREIGN KEY (admin_id) REFERENCES admins(admin_id) ON DELETE RESTRICT,
    FOREIGN KEY (approved_by) REFERENCES admins(admin_id) ON DELETE SET NULL,
    INDEX idx_expense_date (expense_date),
    INDEX idx_category_expense (category_id),
    INDEX idx_status (status)
);

-- 9. Appointments table
CREATE TABLE appointments (
    appointment_id INT PRIMARY KEY AUTO_INCREMENT,
    monk_id INT NOT NULL,
    doctor_id INT NOT NULL,
    appointment_date DATE NOT NULL,
    appointment_time TIME NOT NULL,
    symptoms TEXT,
    priority ENUM('low', 'medium', 'high', 'emergency') DEFAULT 'medium',
    status ENUM('requested', 'confirmed', 'in_progress', 'completed', 'cancelled', 'no_show') DEFAULT 'requested',
    notes TEXT,
    created_by_monk BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (monk_id) REFERENCES monks(monk_id) ON DELETE CASCADE,
    FOREIGN KEY (doctor_id) REFERENCES doctors(doctor_id) ON DELETE CASCADE,
    INDEX idx_appointment_date (appointment_date),
    INDEX idx_doctor_appointments (doctor_id, appointment_date),
    INDEX idx_monk_appointments (monk_id),
    INDEX idx_status_appointments (status)
);

-- 10. Medical records table
CREATE TABLE medical_records (
    record_id INT PRIMARY KEY AUTO_INCREMENT,
    monk_id INT NOT NULL,
    doctor_id INT NOT NULL,
    appointment_id INT,
    visit_date DATE NOT NULL,
    chief_complaint TEXT,
    symptoms TEXT,
    vital_signs JSON, -- Blood pressure, temperature, pulse, etc.
    diagnosis TEXT,
    prescription TEXT,
    treatment_plan TEXT,
    follow_up_date DATE,
    notes TEXT,
    is_confidential BOOLEAN DEFAULT FALSE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (monk_id) REFERENCES monks(monk_id) ON DELETE CASCADE,
    FOREIGN KEY (doctor_id) REFERENCES doctors(doctor_id) ON DELETE RESTRICT,
    FOREIGN KEY (appointment_id) REFERENCES appointments(appointment_id) ON DELETE SET NULL,
    INDEX idx_monk_records (monk_id, visit_date),
    INDEX idx_doctor_records (doctor_id),
    INDEX idx_visit_date (visit_date)
);

-- 11. System logs table (for audit trail)
CREATE TABLE system_logs (
    log_id INT PRIMARY KEY AUTO_INCREMENT,
    user_type ENUM('admin', 'monk', 'doctor', 'donator') NOT NULL,
    user_id INT NOT NULL,
    action VARCHAR(100) NOT NULL,
    table_affected VARCHAR(50),
    record_id INT,
    old_values JSON,
    new_values JSON,
    ip_address VARCHAR(45),
    user_agent TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_user_logs (user_type, user_id),
    INDEX idx_action_logs (action),
    INDEX idx_date_logs (created_at)
);

-- 12. Sessions table (for session management)
CREATE TABLE user_sessions (
    session_id VARCHAR(128) PRIMARY KEY,
    user_type ENUM('admin', 'monk', 'doctor', 'donator') NOT NULL,
    user_id INT NOT NULL,
    login_time TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    last_activity TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    ip_address VARCHAR(45),
    user_agent TEXT,
    is_active BOOLEAN DEFAULT TRUE,
    INDEX idx_user_sessions (user_type, user_id),
    INDEX idx_last_activity (last_activity)
);

-- Insert default admin user (password: admin123)
INSERT INTO admins (username, email, password, full_name, phone) VALUES 
('admin', 'admin@monastery.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'System Administrator', '+1234567890');

-- Insert default donation categories
INSERT INTO donation_categories (category_name, description, target_amount, priority_level) VALUES 
('Food', 'Daily meals and food supplies for monks', 50000.00, 'high'),
('Electricity', 'Monthly electricity bills and power infrastructure', 20000.00, 'medium'),
('Water', 'Water supply, filtration, and plumbing maintenance', 15000.00, 'medium'),
('Medical', 'Healthcare, medicines, and medical equipment', 30000.00, 'critical');

-- Insert sample rooms
INSERT INTO rooms (room_number, room_type, capacity) VALUES 
('R001', 'single', 1),
('R002', 'single', 1),
('R003', 'double', 2),
('R004', 'double', 2),
('R005', 'dormitory', 6),
('ISO1', 'isolation', 1);

-- Create indexes for better performance
CREATE INDEX idx_monks_active ON monks(is_active);
CREATE INDEX idx_doctors_active ON doctors(is_active);
CREATE INDEX idx_donators_active ON donators(is_active);
CREATE INDEX idx_donations_date_amount ON donations(donation_date, amount);
CREATE INDEX idx_expenses_date_amount ON expenses(expense_date, amount);

-- Create views for commonly used data
CREATE VIEW active_monks AS 
SELECT monk_id, username, full_name, ordination_name, room_id, phone, is_active
FROM monks 
WHERE is_active = TRUE;

CREATE VIEW active_doctors AS 
SELECT doctor_id, username, full_name, specialization, phone, is_active
FROM doctors 
WHERE is_active = TRUE;

CREATE VIEW donation_summary AS 
SELECT 
    dc.category_name,
    dc.target_amount,
    COALESCE(SUM(d.amount), 0) as total_donated,
    COALESCE(SUM(e.amount), 0) as total_expenses,
    (COALESCE(SUM(d.amount), 0) - COALESCE(SUM(e.amount), 0)) as current_balance
FROM donation_categories dc
LEFT JOIN donations d ON dc.category_id = d.category_id
LEFT JOIN expenses e ON dc.category_id = e.category_id AND e.status = 'approved'
WHERE dc.is_active = TRUE
GROUP BY dc.category_id, dc.category_name, dc.target_amount;