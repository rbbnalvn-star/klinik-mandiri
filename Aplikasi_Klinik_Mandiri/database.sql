-- ========================================================
-- SISTEM MANAJEMEN KLINIK PRAKTEK MANDIRI
-- Database Schema & Initial Data
-- ========================================================

CREATE DATABASE IF NOT EXISTS klinik_db
  CHARACTER SET utf8mb4
  COLLATE utf8mb4_unicode_ci;

USE klinik_db;

-- --------------------------------------------------------
-- 1. USERS (Admin / Dokter)
-- --------------------------------------------------------
CREATE TABLE IF NOT EXISTS users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(50) NOT NULL UNIQUE,
    password VARCHAR(255) NOT NULL,
    name VARCHAR(100) NOT NULL,
    role ENUM('admin','dokter') DEFAULT 'admin',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- Default login: admin / password123
INSERT INTO users (username, password, name, role) VALUES
('admin', '$2y$10$KS2tRZalhm6PiAvR.Yr9meV1UMOfCc2TBqtEjp5p6NclDF98Sy4VO', 'Dr. Admin', 'admin');

-- --------------------------------------------------------
-- 2. PATIENTS (Rekam Medis)
-- --------------------------------------------------------
CREATE TABLE IF NOT EXISTS patients (
    id INT AUTO_INCREMENT PRIMARY KEY,
    no_rm VARCHAR(20) NOT NULL UNIQUE,
    nik VARCHAR(16) DEFAULT NULL,
    name VARCHAR(150) NOT NULL,
    birth_date DATE DEFAULT NULL,
    gender ENUM('L','P') NOT NULL DEFAULT 'L',
    address TEXT DEFAULT NULL,
    phone VARCHAR(20) DEFAULT NULL,
    blood_type ENUM('A','B','AB','O','-') DEFAULT '-',
    allergy TEXT DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- --------------------------------------------------------
-- 3. VISITS (Kunjungan / Antrean)
-- --------------------------------------------------------
CREATE TABLE IF NOT EXISTS visits (
    id INT AUTO_INCREMENT PRIMARY KEY,
    patient_id INT NOT NULL,
    visit_date DATE NOT NULL,
    queue_number INT NOT NULL DEFAULT 1,
    status ENUM('waiting','examination','action','pharmacy','cashier','done','cancelled') DEFAULT 'waiting',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (patient_id) REFERENCES patients(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- --------------------------------------------------------
-- 4. VITALS (Pemeriksaan Fisik & Diagnosa)
-- --------------------------------------------------------
CREATE TABLE IF NOT EXISTS vitals (
    id INT AUTO_INCREMENT PRIMARY KEY,
    visit_id INT NOT NULL UNIQUE,
    blood_pressure VARCHAR(10) DEFAULT NULL,
    weight INT DEFAULT NULL,
    height INT DEFAULT NULL,
    temperature INT DEFAULT NULL,
    pulse INT DEFAULT NULL,
    respiratory_rate INT DEFAULT NULL,
    complaint TEXT DEFAULT NULL,
    physical_exam TEXT DEFAULT NULL,
    diagnosis TEXT DEFAULT NULL,
    icd_code VARCHAR(10) DEFAULT NULL,
    notes TEXT DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (visit_id) REFERENCES visits(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- --------------------------------------------------------
-- 5. MASTER PROCEDURES (Tindakan Medis)
-- --------------------------------------------------------
CREATE TABLE IF NOT EXISTS master_procedures (
    id INT AUTO_INCREMENT PRIMARY KEY,
    code VARCHAR(20) NOT NULL UNIQUE,
    name VARCHAR(150) NOT NULL,
    category VARCHAR(50) DEFAULT 'Umum',
    price DECIMAL(12,2) NOT NULL DEFAULT 0,
    is_active TINYINT(1) DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- --------------------------------------------------------
-- 6. MASTER DRUGS (Obat & Alat Kesehatan)
-- --------------------------------------------------------
CREATE TABLE IF NOT EXISTS master_drugs (
    id INT AUTO_INCREMENT PRIMARY KEY,
    code VARCHAR(20) NOT NULL UNIQUE,
    name VARCHAR(150) NOT NULL,
    category VARCHAR(50) DEFAULT 'Umum',
    unit VARCHAR(30) DEFAULT 'Tablet',
    price DECIMAL(12,2) NOT NULL DEFAULT 0,
    stock INT NOT NULL DEFAULT 0,
    min_stock INT DEFAULT 10,
    is_active TINYINT(1) DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- --------------------------------------------------------
-- 7. VISIT PROCEDURES (Tindakan per Kunjungan)
-- --------------------------------------------------------
CREATE TABLE IF NOT EXISTS visit_procedures (
    id INT AUTO_INCREMENT PRIMARY KEY,
    visit_id INT NOT NULL,
    procedure_id INT NOT NULL,
    quantity INT NOT NULL DEFAULT 1,
    price DECIMAL(12,2) NOT NULL,
    subtotal DECIMAL(12,2) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (visit_id) REFERENCES visits(id) ON DELETE CASCADE,
    FOREIGN KEY (procedure_id) REFERENCES master_procedures(id) ON DELETE RESTRICT
) ENGINE=InnoDB;

-- --------------------------------------------------------
-- 8. VISIT DRUGS (Resep Obat per Kunjungan)
-- --------------------------------------------------------
CREATE TABLE IF NOT EXISTS visit_drugs (
    id INT AUTO_INCREMENT PRIMARY KEY,
    visit_id INT NOT NULL,
    drug_id INT NOT NULL,
    quantity INT NOT NULL DEFAULT 1,
    price DECIMAL(12,2) NOT NULL,
    subtotal DECIMAL(12,2) NOT NULL,
    dosage_instructions VARCHAR(255) DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (visit_id) REFERENCES visits(id) ON DELETE CASCADE,
    FOREIGN KEY (drug_id) REFERENCES master_drugs(id) ON DELETE RESTRICT
) ENGINE=InnoDB;

-- --------------------------------------------------------
-- 9. INVOICES (Tagihan & Pembayaran)
-- --------------------------------------------------------
CREATE TABLE IF NOT EXISTS invoices (
    id INT AUTO_INCREMENT PRIMARY KEY,
    visit_id INT NOT NULL UNIQUE,
    invoice_number VARCHAR(30) NOT NULL UNIQUE,
    total_procedures DECIMAL(12,2) DEFAULT 0,
    total_drugs DECIMAL(12,2) DEFAULT 0,
    discount DECIMAL(12,2) DEFAULT 0,
    grand_total DECIMAL(12,2) DEFAULT 0,
    payment_amount DECIMAL(12,2) DEFAULT 0,
    change_amount DECIMAL(12,2) DEFAULT 0,
    payment_method ENUM('cash','debit','credit','transfer') DEFAULT 'cash',
    status ENUM('unpaid','paid') DEFAULT 'unpaid',
    paid_at TIMESTAMP NULL DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (visit_id) REFERENCES visits(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- ========================================================
-- INITIAL MASTER DATA
-- ========================================================

-- Sample Tindakan
INSERT INTO master_procedures (code, name, category, price) VALUES
('TDK-001', 'Konsultasi Dokter Umum', 'Konsultasi', 100000),
('TDK-002', 'Konsultasi Dokter Spesialis', 'Konsultasi', 200000),
('TDK-003', 'Jahit Luka (1-5 jahitan)', 'Bedah Minor', 150000),
('TDK-004', 'Jahit Luka (6-10 jahitan)', 'Bedah Minor', 250000),
('TDK-005', 'Cabut Kuku', 'Bedah Minor', 200000),
('TDK-006', 'Insisi Abses', 'Bedah Minor', 300000),
('TDK-007', 'Nebulizer', 'Tindakan', 75000),
('TDK-008', 'Injeksi IM/IV', 'Tindakan', 50000),
('TDK-009', 'Pemasangan Infus', 'Tindakan', 100000),
('TDK-010', 'Rawat Luka Sederhana', 'Tindakan', 75000),
('TDK-011', 'EKG', 'Diagnostik', 150000),
('TDK-012', 'Tes Gula Darah', 'Laboratorium', 50000),
('TDK-013', 'Surat Keterangan Sehat', 'Surat', 50000),
('TDK-014', 'Surat Keterangan Sakit', 'Surat', 25000),
('TDK-015', 'Circumcisi / Sunat', 'Bedah Minor', 500000);

-- Sample Obat
INSERT INTO master_drugs (code, name, category, unit, price, stock) VALUES
('OBT-001', 'Paracetamol 500mg', 'Analgesik', 'Tablet', 1500, 500),
('OBT-002', 'Ibuprofen 400mg', 'Analgesik', 'Tablet', 2500, 300),
('OBT-003', 'Amoxicillin 500mg', 'Antibiotik', 'Kapsul', 3000, 400),
('OBT-004', 'Cetirizine 10mg', 'Antihistamin', 'Tablet', 2000, 200),
('OBT-005', 'Omeprazole 20mg', 'Gastrointestinal', 'Kapsul', 4000, 250),
('OBT-006', 'Metformin 500mg', 'Antidiabetes', 'Tablet', 2500, 300),
('OBT-007', 'Amlodipine 5mg', 'Antihipertensi', 'Tablet', 3500, 200),
('OBT-008', 'Salbutamol Inhaler', 'Bronkodilator', 'Pcs', 35000, 50),
('OBT-009', 'Betadine Solution 60ml', 'Antiseptik', 'Botol', 25000, 100),
('OBT-010', 'Dexamethasone 0.5mg', 'Kortikosteroid', 'Tablet', 1500, 300),
('OBT-011', 'Ranitidine 150mg', 'Gastrointestinal', 'Tablet', 2000, 250),
('OBT-012', 'Vitamin B Complex', 'Vitamin', 'Tablet', 1000, 500),
('OBT-013', 'Vitamin C 500mg', 'Vitamin', 'Tablet', 1500, 400),
('OBT-014', 'Loperamide 2mg', 'Antidiare', 'Tablet', 2000, 200),
('OBT-015', 'ORS (Oralit)', 'Rehidrasi', 'Sachet', 3000, 300),
('OBT-016', 'Ciprofloxacin 500mg', 'Antibiotik', 'Tablet', 4000, 200),
('OBT-017', 'Domperidone 10mg', 'Antiemetik', 'Tablet', 2500, 250),
('OBT-018', 'Diclofenac Sodium 50mg', 'NSAID', 'Tablet', 2000, 200),
('OBT-019', 'Kasa Steril 16x16', 'Alat Kesehatan', 'Pcs', 5000, 200),
('OBT-020', 'Plester / Band-Aid', 'Alat Kesehatan', 'Pcs', 2000, 500);
