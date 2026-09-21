-- Medical Billing System Database
-- Created: 2026-06-08

CREATE DATABASE IF NOT EXISTS medical_billing;
USE medical_billing;

-- --------------------------------------------------------
-- Patients Table
-- --------------------------------------------------------
CREATE TABLE IF NOT EXISTS patients (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(150) NOT NULL,
    phone VARCHAR(20),
    address TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- --------------------------------------------------------
-- Doctors Table
-- --------------------------------------------------------
CREATE TABLE IF NOT EXISTS doctors (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(150) NOT NULL,
    specialization VARCHAR(100),
    phone VARCHAR(20),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- --------------------------------------------------------
-- Products / Medicines Table
-- --------------------------------------------------------
CREATE TABLE IF NOT EXISTS products (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(200) NOT NULL,
    salt VARCHAR(200),
    batch_no VARCHAR(50),
    expiry_date VARCHAR(10),
    rate DECIMAL(10,2) DEFAULT 0.00,
    gst_percent DECIMAL(5,2) DEFAULT 0.00,
    mrp DECIMAL(10,2) DEFAULT 0.00,
    stock INT DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- --------------------------------------------------------
-- Sales / Invoices Table
-- --------------------------------------------------------
CREATE TABLE IF NOT EXISTS sales (
    id INT AUTO_INCREMENT PRIMARY KEY,
    invoice_no VARCHAR(50) UNIQUE NOT NULL,
    invoice_date DATE NOT NULL,
    patient_name VARCHAR(150),
    patient_phone VARCHAR(20),
    patient_address TEXT,
    doctor_name VARCHAR(150),
    reminder TINYINT(1) DEFAULT 0,
    lab_charge DECIMAL(10,2) DEFAULT 0.00,
    doctor_charge DECIMAL(10,2) DEFAULT 0.00,
    injection_charge DECIMAL(10,2) DEFAULT 0.00,
    nursing_charge DECIMAL(10,2) DEFAULT 0.00,
    total_discount DECIMAL(10,2) DEFAULT 0.00,
    product_subtotal DECIMAL(10,2) DEFAULT 0.00,
    additional_charges DECIMAL(10,2) DEFAULT 0.00,
    rounding_off DECIMAL(5,2) DEFAULT 0.00,
    grand_total DECIMAL(10,2) DEFAULT 0.00,
    status ENUM('saved','submitted') DEFAULT 'saved',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- --------------------------------------------------------
-- Sale Items Table
-- --------------------------------------------------------
CREATE TABLE IF NOT EXISTS sale_items (
    id INT AUTO_INCREMENT PRIMARY KEY,
    sale_id INT NOT NULL,
    product_name VARCHAR(200), 
    salt VARCHAR(200),
    batch_no VARCHAR(50),
    expiry_date VARCHAR(10),
    qty INT DEFAULT 1,
    rate DECIMAL(10,2) DEFAULT 0.00,
    gst_percent DECIMAL(5,2) DEFAULT 0.00,
    mrp DECIMAL(10,2) DEFAULT 0.00,
    disc_percent DECIMAL(5,2) DEFAULT 0.00,
    total DECIMAL(10,2) DEFAULT 0.00,
    product_id INT NULL,
    ADD CONSTRAINT fk_product FOREIGN KEY(product_id) REFERENCES products(id)
    FOREIGN KEY (sale_id) REFERENCES sales(id) ON DELETE CASCADE
);


-- --------------------------------------------------------
-- Invoice Counter Table
-- --------------------------------------------------------
CREATE TABLE IF NOT EXISTS invoice_counter (
    id INT AUTO_INCREMENT PRIMARY KEY,
    last_number INT DEFAULT 777,
    last_date DATE
);

-- Initialize counter
INSERT INTO invoice_counter (last_number, last_date) VALUES (777, CURDATE())
ON DUPLICATE KEY UPDATE id = id;

-- --------------------------------------------------------
-- Sample Products Data
-- --------------------------------------------------------
INSERT INTO products (name, salt, batch_no, expiry_date, rate, gst_percent, mrp, stock) VALUES
('Paracetamol 500mg', 'Paracetamol', 'BT2024A', '12/2027', 2.50, 5.00, 2.80, 500),
('Crocin 500mg', 'Paracetamol', 'CR2024B', '06/2027', 5.00, 5.00, 5.50, 300),
('Amoxicillin 250mg', 'Amoxicillin', 'AM2024C', '03/2027', 8.00, 12.00, 9.50, 200),
('Augmentin 625mg', 'Amoxicillin + Clavulanic Acid', 'AU2024D', '09/2027', 45.00, 12.00, 52.00, 150),
('Azithromycin 250mg', 'Azithromycin', 'AZ2024E', '12/2027', 18.00, 12.00, 20.00, 100),
('Metformin 500mg', 'Metformin Hydrochloride', 'MF2024F', '06/2028', 3.50, 5.00, 4.00, 400),
('Glucophage 500mg', 'Metformin', 'GL2024G', '03/2028', 6.00, 5.00, 6.50, 250),
('Atorvastatin 10mg', 'Atorvastatin', 'AT2024H', '12/2027', 12.00, 12.00, 14.00, 180),
('Lipitor 20mg', 'Atorvastatin', 'LP2024I', '09/2027', 25.00, 12.00, 28.00, 120),
('Omeprazole 20mg', 'Omeprazole', 'OM2024J', '06/2027', 4.50, 5.00, 5.00, 350),
('Pantoprazole 40mg', 'Pantoprazole', 'PA2024K', '12/2027', 6.00, 5.00, 6.80, 280),
('Cetirizine 10mg', 'Cetirizine', 'CE2024L', '03/2028', 3.00, 5.00, 3.50, 600),
('Allegra 120mg', 'Fexofenadine', 'AL2024M', '06/2028', 14.00, 12.00, 16.00, 200),
('Ibuprofen 400mg', 'Ibuprofen', 'IB2024N', '09/2027', 5.00, 5.00, 5.80, 400),
('Brufen 400mg', 'Ibuprofen', 'BR2024O', '12/2027', 8.00, 5.00, 9.00, 300),
('Dolo 650mg', 'Paracetamol', 'DL2024P', '03/2028', 7.00, 5.00, 8.00, 350),
('Vitamin D3 60000IU', 'Cholecalciferol', 'VD2024Q', '06/2028', 35.00, 12.00, 40.00, 150),
('Calcium Carbonate 500mg', 'Calcium Carbonate', 'CC2024R', '12/2027', 4.00, 5.00, 4.50, 500),
('Insulin Regular', 'Human Insulin', 'IN2024S', '06/2027', 180.00, 5.00, 195.00, 80),
('Amlodipine 5mg', 'Amlodipine Besylate', 'AM2024T', '03/2028', 7.50, 12.00, 8.50, 220);

-- Sample Doctors
INSERT INTO doctors (name, specialization) VALUES
('Dr. Rajesh Kumar', 'General Physician'),
('Dr. Priya Sharma', 'Pediatrician'),
('Dr. Arun Mehta', 'Cardiologist'),
('Dr. Sunita Patel', 'Dermatologist'),
('Dr. Vikram Singh', 'Orthopedic');

-- Add Product ID

-- Add Indexes
CREATE INDEX idx_product_name ON products(name);
CREATE INDEX idx_product_salt ON products(salt);

CREATE INDEX idx_sales_invoice_no
ON sales(invoice_no);

CREATE INDEX idx_sales_date
ON sales(invoice_date);