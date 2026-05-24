-- =====================================================
-- SECURE BANKING APPLICATION - DATABASE SCHEMA
-- MySQL Database Schema for phpMyAdmin
-- =====================================================

-- Create Users Table
CREATE TABLE users (
    user_id INT AUTO_INCREMENT PRIMARY KEY,
    email VARCHAR(255) UNIQUE NOT NULL,
    password_hash VARCHAR(255) NOT NULL,
    first_name VARCHAR(100) NOT NULL,
    last_name VARCHAR(100) NOT NULL,
    phone_number VARCHAR(20),
    date_of_birth DATE NOT NULL,
    address VARCHAR(255),
    city VARCHAR(100),
    state VARCHAR(100),
    zip_code VARCHAR(10),
    country VARCHAR(100),
    status ENUM('active', 'inactive', 'suspended') DEFAULT 'active',
    kyc_verified TINYINT(1) DEFAULT 0,
    last_login TIMESTAMP NULL DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_email (email),
    INDEX idx_status (status),
    INDEX idx_created_at (created_at)
);

-- Create Accounts Table
CREATE TABLE accounts (
    account_id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    account_number VARCHAR(20) UNIQUE NOT NULL,
    account_type ENUM('savings', 'checking', 'money_market') DEFAULT 'savings',
    balance DECIMAL(15, 2) DEFAULT 0.00,
    currency VARCHAR(3) DEFAULT 'USD',
    status ENUM('active', 'frozen', 'closed') DEFAULT 'active',
    interest_rate DECIMAL(5, 3) DEFAULT 0.500,
    account_opened_date TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    last_transaction_date TIMESTAMP NULL DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(user_id) ON DELETE CASCADE,
    UNIQUE KEY unique_user_account (user_id, account_type),
    INDEX idx_user_id (user_id),
    INDEX idx_account_status (status),
    INDEX idx_balance (balance)
);

-- Create Transactions Table
CREATE TABLE transactions (
    transaction_id INT AUTO_INCREMENT PRIMARY KEY,
    from_account_id INT,
    to_account_id INT,
    transaction_type ENUM('deposit', 'withdrawal', 'transfer', 'interest') NOT NULL,
    amount DECIMAL(15, 2) NOT NULL,
    description VARCHAR(255),
    status ENUM('pending', 'completed', 'failed', 'reversed') DEFAULT 'pending',
    reference_number VARCHAR(50) UNIQUE NOT NULL,
    metadata JSON,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    completed_at TIMESTAMP NULL DEFAULT NULL,
    INDEX idx_from_account (from_account_id),
    INDEX idx_to_account (to_account_id),
    INDEX idx_created_at (created_at),
    INDEX idx_status (status),
    INDEX idx_reference (reference_number),
    FOREIGN KEY (from_account_id) REFERENCES accounts(account_id) ON DELETE SET NULL,
    FOREIGN KEY (to_account_id) REFERENCES accounts(account_id) ON DELETE SET NULL
);

-- Create Session Management Table (for enhanced security)
CREATE TABLE sessions (
    session_id VARCHAR(255) PRIMARY KEY,
    user_id INT NOT NULL,
    ip_address VARCHAR(45),
    user_agent VARCHAR(500),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    last_activity TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    expires_at TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(user_id) ON DELETE CASCADE,
    INDEX idx_user_id (user_id),
    INDEX idx_expires_at (expires_at)
);

-- Create Audit Log Table (for compliance and security monitoring)
CREATE TABLE audit_logs (
    log_id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT,
    action VARCHAR(100) NOT NULL,
    entity_type VARCHAR(50),
    entity_id INT,
    old_values JSON,
    new_values JSON,
    ip_address VARCHAR(45),
    user_agent VARCHAR(500),
    status VARCHAR(20),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(user_id) ON DELETE SET NULL,
    INDEX idx_user_id (user_id),
    INDEX idx_created_at (created_at),
    INDEX idx_action (action)
);

-- Create Beneficiaries Table (for transfer recipients)
CREATE TABLE beneficiaries (
    beneficiary_id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    account_number VARCHAR(20) NOT NULL,
    beneficiary_name VARCHAR(100) NOT NULL,
    bank_name VARCHAR(100),
    relationship VARCHAR(50),
    verified TINYINT(1) DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(user_id) ON DELETE CASCADE,
    INDEX idx_user_id (user_id)
);

-- Create Demo Items Table (for assignment/demo CRUD operations)
CREATE TABLE demo_items (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(150) NOT NULL,
    email VARCHAR(255) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_email (email)
);

-- =====================================================
-- Initialization Queries (Optional sample data)
-- =====================================================

-- Sample: Generate unique account numbers for demo
-- ALTER TABLE accounts MODIFY account_number VARCHAR(20) GENERATED ALWAYS AS (CONCAT('ACC', LPAD(account_id, 10, '0'))) STORED;

-- Query to verify schema integrity
SELECT TABLE_NAME FROM INFORMATION_SCHEMA.TABLES WHERE TABLE_SCHEMA = DATABASE();
