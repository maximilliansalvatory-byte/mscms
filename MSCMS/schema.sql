-- =====================================================================
-- Medical Supply Chain Management System (MSCMS)
-- Educational / Portfolio Project - Inspired by public health logistics
-- Database Schema (MySQL 8)
-- =====================================================================

SET FOREIGN_KEY_CHECKS = 0;
SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
SET time_zone = "+00:00";

CREATE DATABASE IF NOT EXISTS mscms_db
    CHARACTER SET utf8mb4
    COLLATE utf8mb4_unicode_ci;

USE mscms_db;

-- =====================================================================
-- 1. SECURITY & USER MANAGEMENT
-- =====================================================================

CREATE TABLE roles (
    role_id INT AUTO_INCREMENT PRIMARY KEY,
    role_name VARCHAR(50) NOT NULL UNIQUE,
    description VARCHAR(255),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

CREATE TABLE permissions (
    permission_id INT AUTO_INCREMENT PRIMARY KEY,
    permission_name VARCHAR(100) NOT NULL UNIQUE,
    module VARCHAR(50) NOT NULL,
    description VARCHAR(255)
) ENGINE=InnoDB;

CREATE TABLE role_permissions (
    role_id INT NOT NULL,
    permission_id INT NOT NULL,
    PRIMARY KEY (role_id, permission_id),
    FOREIGN KEY (role_id) REFERENCES roles(role_id) ON DELETE CASCADE,
    FOREIGN KEY (permission_id) REFERENCES permissions(permission_id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE users (
    user_id INT AUTO_INCREMENT PRIMARY KEY,
    full_name VARCHAR(150) NOT NULL,
    username VARCHAR(50) NOT NULL UNIQUE,
    email VARCHAR(150) NOT NULL UNIQUE,
    phone VARCHAR(20),
    password_hash VARCHAR(255) NOT NULL,
    role_id INT NOT NULL,
    warehouse_id INT NULL,
    is_active TINYINT(1) DEFAULT 1,
    two_factor_enabled TINYINT(1) DEFAULT 0,
    two_factor_secret VARCHAR(100) NULL,
    failed_login_attempts INT DEFAULT 0,
    locked_until DATETIME NULL,
    last_login DATETIME NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (role_id) REFERENCES roles(role_id)
) ENGINE=InnoDB;

CREATE TABLE password_resets (
    reset_id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    token VARCHAR(255) NOT NULL,
    expires_at DATETIME NOT NULL,
    used TINYINT(1) DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(user_id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE user_sessions (
    session_id VARCHAR(128) PRIMARY KEY,
    user_id INT NOT NULL,
    ip_address VARCHAR(45),
    user_agent VARCHAR(255),
    last_activity TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(user_id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE audit_logs (
    audit_id BIGINT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NULL,
    action VARCHAR(100) NOT NULL,
    module VARCHAR(50) NOT NULL,
    record_id VARCHAR(50) NULL,
    old_value TEXT NULL,
    new_value TEXT NULL,
    ip_address VARCHAR(45),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(user_id) ON DELETE SET NULL
) ENGINE=InnoDB;

-- =====================================================================
-- 2. GEOGRAPHY / ORGANIZATION
-- =====================================================================

CREATE TABLE regions (
    region_id INT AUTO_INCREMENT PRIMARY KEY,
    region_name VARCHAR(100) NOT NULL UNIQUE
) ENGINE=InnoDB;

CREATE TABLE hospitals (
    hospital_id INT AUTO_INCREMENT PRIMARY KEY,
    hospital_name VARCHAR(150) NOT NULL,
    region_id INT NOT NULL,
    hospital_level ENUM('National','Regional','District','Health Center','Dispensary') NOT NULL,
    contact_person VARCHAR(100),
    phone VARCHAR(20),
    email VARCHAR(150),
    address VARCHAR(255),
    is_active TINYINT(1) DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (region_id) REFERENCES regions(region_id)
) ENGINE=InnoDB;

-- =====================================================================
-- 3. WAREHOUSE MANAGEMENT
-- =====================================================================

CREATE TABLE warehouses (
    warehouse_id INT AUTO_INCREMENT PRIMARY KEY,
    warehouse_name VARCHAR(100) NOT NULL,
    region_id INT NOT NULL,
    warehouse_type ENUM('Zonal Store','Central Store','Sales Point') NOT NULL,
    address VARCHAR(255),
    manager_user_id INT NULL,
    is_active TINYINT(1) DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (region_id) REFERENCES regions(region_id)
) ENGINE=InnoDB;

CREATE TABLE warehouse_zones (
    zone_id INT AUTO_INCREMENT PRIMARY KEY,
    warehouse_id INT NOT NULL,
    zone_name VARCHAR(50) NOT NULL,
    zone_type ENUM('Ambient','Cold Room','Controlled Drug','Quarantine') DEFAULT 'Ambient',
    FOREIGN KEY (warehouse_id) REFERENCES warehouses(warehouse_id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE warehouse_locations (
    location_id INT AUTO_INCREMENT PRIMARY KEY,
    zone_id INT NOT NULL,
    shelf_code VARCHAR(20) NOT NULL,
    bin_code VARCHAR(20) NOT NULL,
    FOREIGN KEY (zone_id) REFERENCES warehouse_zones(zone_id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- Add FK for warehouses.manager_user_id after users table exists
ALTER TABLE warehouses
    ADD CONSTRAINT fk_warehouse_manager
    FOREIGN KEY (manager_user_id) REFERENCES users(user_id) ON DELETE SET NULL;

ALTER TABLE users
    ADD CONSTRAINT fk_user_warehouse
    FOREIGN KEY (warehouse_id) REFERENCES warehouses(warehouse_id) ON DELETE SET NULL;

-- =====================================================================
-- 4. MEDICINE MASTER DATA
-- =====================================================================

CREATE TABLE categories (
    category_id INT AUTO_INCREMENT PRIMARY KEY,
    category_name VARCHAR(100) NOT NULL UNIQUE,
    description VARCHAR(255)
) ENGINE=InnoDB;

CREATE TABLE manufacturers (
    manufacturer_id INT AUTO_INCREMENT PRIMARY KEY,
    manufacturer_name VARCHAR(150) NOT NULL,
    country VARCHAR(100),
    contact_email VARCHAR(150),
    contact_phone VARCHAR(20)
) ENGINE=InnoDB;

CREATE TABLE medicines (
    medicine_id INT AUTO_INCREMENT PRIMARY KEY,
    generic_name VARCHAR(150) NOT NULL,
    brand_name VARCHAR(150),
    category_id INT NOT NULL,
    manufacturer_id INT NOT NULL,
    strength VARCHAR(50),
    dosage_form ENUM('Tablet','Capsule','Syrup','Injection','Ointment','Drops','Inhaler','Other') NOT NULL,
    unit_of_measure VARCHAR(30) NOT NULL,
    storage_temperature ENUM('Room Temperature','Refrigerated (2-8C)','Frozen') DEFAULT 'Room Temperature',
    who_tfda_registration VARCHAR(100),
    barcode VARCHAR(100) UNIQUE,
    reorder_level INT DEFAULT 0,
    is_controlled_drug TINYINT(1) DEFAULT 0,
    is_active TINYINT(1) DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (category_id) REFERENCES categories(category_id),
    FOREIGN KEY (manufacturer_id) REFERENCES manufacturers(manufacturer_id)
) ENGINE=InnoDB;

-- =====================================================================
-- 5. SUPPLIER MANAGEMENT
-- =====================================================================

CREATE TABLE suppliers (
    supplier_id INT AUTO_INCREMENT PRIMARY KEY,
    company_name VARCHAR(150) NOT NULL,
    contact_person VARCHAR(100),
    email VARCHAR(150),
    phone VARCHAR(20),
    address VARCHAR(255),
    country VARCHAR(100),
    performance_rating DECIMAL(3,2) DEFAULT 0.00,
    is_active TINYINT(1) DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

CREATE TABLE supplier_documents (
    document_id INT AUTO_INCREMENT PRIMARY KEY,
    supplier_id INT NOT NULL,
    document_type VARCHAR(100) NOT NULL,
    file_path VARCHAR(255) NOT NULL,
    uploaded_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (supplier_id) REFERENCES suppliers(supplier_id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE supplier_contracts (
    contract_id INT AUTO_INCREMENT PRIMARY KEY,
    supplier_id INT NOT NULL,
    contract_number VARCHAR(100) NOT NULL,
    start_date DATE NOT NULL,
    end_date DATE NOT NULL,
    contract_value DECIMAL(15,2),
    file_path VARCHAR(255),
    FOREIGN KEY (supplier_id) REFERENCES suppliers(supplier_id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- =====================================================================
-- 6. PROCUREMENT
-- =====================================================================

CREATE TABLE purchase_requisitions (
    requisition_id INT AUTO_INCREMENT PRIMARY KEY,
    requisition_no VARCHAR(50) NOT NULL UNIQUE,
    requested_by INT NOT NULL,
    warehouse_id INT NOT NULL,
    status ENUM('Pending','Approved','Rejected','Converted') DEFAULT 'Pending',
    notes TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (requested_by) REFERENCES users(user_id),
    FOREIGN KEY (warehouse_id) REFERENCES warehouses(warehouse_id)
) ENGINE=InnoDB;

CREATE TABLE requisition_items (
    item_id INT AUTO_INCREMENT PRIMARY KEY,
    requisition_id INT NOT NULL,
    medicine_id INT NOT NULL,
    quantity_requested INT NOT NULL,
    FOREIGN KEY (requisition_id) REFERENCES purchase_requisitions(requisition_id) ON DELETE CASCADE,
    FOREIGN KEY (medicine_id) REFERENCES medicines(medicine_id)
) ENGINE=InnoDB;

CREATE TABLE purchase_orders (
    po_id INT AUTO_INCREMENT PRIMARY KEY,
    po_number VARCHAR(50) NOT NULL UNIQUE,
    supplier_id INT NOT NULL,
    requisition_id INT NULL,
    order_date DATE NOT NULL,
    expected_delivery_date DATE,
    status ENUM('Draft','Submitted','Approved','Partially Received','Received','Cancelled') DEFAULT 'Draft',
    total_amount DECIMAL(15,2) DEFAULT 0.00,
    created_by INT NOT NULL,
    approved_by INT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (supplier_id) REFERENCES suppliers(supplier_id),
    FOREIGN KEY (requisition_id) REFERENCES purchase_requisitions(requisition_id),
    FOREIGN KEY (created_by) REFERENCES users(user_id),
    FOREIGN KEY (approved_by) REFERENCES users(user_id)
) ENGINE=InnoDB;

CREATE TABLE purchase_order_items (
    po_item_id INT AUTO_INCREMENT PRIMARY KEY,
    po_id INT NOT NULL,
    medicine_id INT NOT NULL,
    quantity_ordered INT NOT NULL,
    quantity_received INT DEFAULT 0,
    unit_price DECIMAL(12,2) NOT NULL,
    subtotal DECIMAL(15,2) GENERATED ALWAYS AS (quantity_ordered * unit_price) STORED,
    FOREIGN KEY (po_id) REFERENCES purchase_orders(po_id) ON DELETE CASCADE,
    FOREIGN KEY (medicine_id) REFERENCES medicines(medicine_id)
) ENGINE=InnoDB;

CREATE TABLE goods_receipts (
    receipt_id INT AUTO_INCREMENT PRIMARY KEY,
    po_id INT NOT NULL,
    received_by INT NOT NULL,
    receipt_date DATE NOT NULL,
    is_partial TINYINT(1) DEFAULT 0,
    notes TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (po_id) REFERENCES purchase_orders(po_id),
    FOREIGN KEY (received_by) REFERENCES users(user_id)
) ENGINE=InnoDB;

CREATE TABLE goods_receipt_items (
    receipt_item_id INT AUTO_INCREMENT PRIMARY KEY,
    receipt_id INT NOT NULL,
    po_item_id INT NOT NULL,
    batch_number VARCHAR(50) NOT NULL,
    quantity_received INT NOT NULL,
    manufacture_date DATE,
    expiry_date DATE NOT NULL,
    FOREIGN KEY (receipt_id) REFERENCES goods_receipts(receipt_id) ON DELETE CASCADE,
    FOREIGN KEY (po_item_id) REFERENCES purchase_order_items(po_item_id)
) ENGINE=InnoDB;

-- =====================================================================
-- 7. QUALITY ASSURANCE
-- =====================================================================

CREATE TABLE quality_inspections (
    inspection_id INT AUTO_INCREMENT PRIMARY KEY,
    receipt_item_id INT NOT NULL,
    inspected_by INT NOT NULL,
    inspection_date DATE NOT NULL,
    result ENUM('Approved','Rejected','Quarantined') NOT NULL,
    lab_test_reference VARCHAR(100),
    remarks TEXT,
    FOREIGN KEY (receipt_item_id) REFERENCES goods_receipt_items(receipt_item_id),
    FOREIGN KEY (inspected_by) REFERENCES users(user_id)
) ENGINE=InnoDB;

CREATE TABLE recalls (
    recall_id INT AUTO_INCREMENT PRIMARY KEY,
    batch_id INT NOT NULL,
    reason TEXT NOT NULL,
    initiated_by INT NOT NULL,
    status ENUM('Initiated','In Progress','Completed') DEFAULT 'Initiated',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (initiated_by) REFERENCES users(user_id)
) ENGINE=InnoDB;

-- =====================================================================
-- 8. INVENTORY MANAGEMENT (Batch-level stock)
-- =====================================================================

CREATE TABLE batches (
    batch_id INT AUTO_INCREMENT PRIMARY KEY,
    medicine_id INT NOT NULL,
    batch_number VARCHAR(50) NOT NULL,
    manufacture_date DATE,
    expiry_date DATE NOT NULL,
    supplier_id INT NULL,
    quality_status ENUM('Approved','Rejected','Quarantined','Pending') DEFAULT 'Pending',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (medicine_id) REFERENCES medicines(medicine_id),
    FOREIGN KEY (supplier_id) REFERENCES suppliers(supplier_id),
    UNIQUE KEY uq_medicine_batch (medicine_id, batch_number)
) ENGINE=InnoDB;

CREATE TABLE inventory (
    inventory_id INT AUTO_INCREMENT PRIMARY KEY,
    warehouse_id INT NOT NULL,
    batch_id INT NOT NULL,
    location_id INT NULL,
    quantity_on_hand INT NOT NULL DEFAULT 0,
    last_updated TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (warehouse_id) REFERENCES warehouses(warehouse_id),
    FOREIGN KEY (batch_id) REFERENCES batches(batch_id),
    FOREIGN KEY (location_id) REFERENCES warehouse_locations(location_id),
    UNIQUE KEY uq_warehouse_batch (warehouse_id, batch_id)
) ENGINE=InnoDB;

CREATE TABLE stock_transactions (
    transaction_id BIGINT AUTO_INCREMENT PRIMARY KEY,
    inventory_id INT NOT NULL,
    transaction_type ENUM('Stock In','Stock Out','Adjustment','Transfer In','Transfer Out') NOT NULL,
    quantity INT NOT NULL,
    reference_type VARCHAR(50),
    reference_id INT NULL,
    performed_by INT NOT NULL,
    remarks VARCHAR(255),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (inventory_id) REFERENCES inventory(inventory_id),
    FOREIGN KEY (performed_by) REFERENCES users(user_id)
) ENGINE=InnoDB;

CREATE TABLE stock_transfers (
    transfer_id INT AUTO_INCREMENT PRIMARY KEY,
    from_warehouse_id INT NOT NULL,
    to_warehouse_id INT NOT NULL,
    batch_id INT NOT NULL,
    quantity INT NOT NULL,
    status ENUM('Pending','In Transit','Completed','Cancelled') DEFAULT 'Pending',
    requested_by INT NOT NULL,
    approved_by INT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (from_warehouse_id) REFERENCES warehouses(warehouse_id),
    FOREIGN KEY (to_warehouse_id) REFERENCES warehouses(warehouse_id),
    FOREIGN KEY (batch_id) REFERENCES batches(batch_id),
    FOREIGN KEY (requested_by) REFERENCES users(user_id),
    FOREIGN KEY (approved_by) REFERENCES users(user_id)
) ENGINE=InnoDB;

-- =====================================================================
-- 9. DISTRIBUTION
-- =====================================================================

CREATE TABLE orders (
    order_id INT AUTO_INCREMENT PRIMARY KEY,
    order_number VARCHAR(50) NOT NULL UNIQUE,
    hospital_id INT NOT NULL,
    warehouse_id INT NOT NULL,
    status ENUM('Pending','Approved','Dispatched','Delivered','Cancelled') DEFAULT 'Pending',
    ordered_by INT NOT NULL,
    order_date DATE NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (hospital_id) REFERENCES hospitals(hospital_id),
    FOREIGN KEY (warehouse_id) REFERENCES warehouses(warehouse_id),
    FOREIGN KEY (ordered_by) REFERENCES users(user_id)
) ENGINE=InnoDB;

CREATE TABLE order_items (
    order_item_id INT AUTO_INCREMENT PRIMARY KEY,
    order_id INT NOT NULL,
    medicine_id INT NOT NULL,
    quantity_ordered INT NOT NULL,
    quantity_dispatched INT DEFAULT 0,
    FOREIGN KEY (order_id) REFERENCES orders(order_id) ON DELETE CASCADE,
    FOREIGN KEY (medicine_id) REFERENCES medicines(medicine_id)
) ENGINE=InnoDB;

CREATE TABLE vehicles (
    vehicle_id INT AUTO_INCREMENT PRIMARY KEY,
    plate_number VARCHAR(30) NOT NULL UNIQUE,
    vehicle_type VARCHAR(50),
    capacity_kg DECIMAL(10,2),
    is_active TINYINT(1) DEFAULT 1
) ENGINE=InnoDB;

CREATE TABLE dispatches (
    dispatch_id INT AUTO_INCREMENT PRIMARY KEY,
    order_id INT NOT NULL,
    vehicle_id INT NULL,
    driver_name VARCHAR(100),
    dispatch_date DATE NOT NULL,
    route VARCHAR(255),
    status ENUM('Planned','In Transit','Delivered','Delayed') DEFAULT 'Planned',
    dispatched_by INT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (order_id) REFERENCES orders(order_id),
    FOREIGN KEY (vehicle_id) REFERENCES vehicles(vehicle_id),
    FOREIGN KEY (dispatched_by) REFERENCES users(user_id)
) ENGINE=InnoDB;

CREATE TABLE delivery_notes (
    delivery_note_id INT AUTO_INCREMENT PRIMARY KEY,
    dispatch_id INT NOT NULL,
    delivered_at DATETIME NULL,
    received_by_name VARCHAR(100),
    signature_file VARCHAR(255),
    confirmed TINYINT(1) DEFAULT 0,
    FOREIGN KEY (dispatch_id) REFERENCES dispatches(dispatch_id)
) ENGINE=InnoDB;

-- =====================================================================
-- 10. FORECASTING
-- =====================================================================

CREATE TABLE consumption_history (
    history_id BIGINT AUTO_INCREMENT PRIMARY KEY,
    hospital_id INT NOT NULL,
    medicine_id INT NOT NULL,
    period_month DATE NOT NULL,
    quantity_consumed INT NOT NULL,
    FOREIGN KEY (hospital_id) REFERENCES hospitals(hospital_id),
    FOREIGN KEY (medicine_id) REFERENCES medicines(medicine_id)
) ENGINE=InnoDB;

CREATE TABLE demand_forecasts (
    forecast_id INT AUTO_INCREMENT PRIMARY KEY,
    medicine_id INT NOT NULL,
    warehouse_id INT NOT NULL,
    forecast_period DATE NOT NULL,
    forecasted_quantity INT NOT NULL,
    method VARCHAR(50) DEFAULT 'Moving Average',
    generated_by INT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (medicine_id) REFERENCES medicines(medicine_id),
    FOREIGN KEY (warehouse_id) REFERENCES warehouses(warehouse_id),
    FOREIGN KEY (generated_by) REFERENCES users(user_id)
) ENGINE=InnoDB;

-- =====================================================================
-- 11. NOTIFICATIONS
-- =====================================================================

CREATE TABLE notifications (
    notification_id BIGINT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    type ENUM('Low Stock','Expiring','Expired','Pending Procurement','Pending Approval','Delivery Delay','Other') NOT NULL,
    message VARCHAR(255) NOT NULL,
    reference_type VARCHAR(50),
    reference_id INT NULL,
    is_read TINYINT(1) DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(user_id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- =====================================================================
-- 12. DOCUMENT MANAGEMENT
-- =====================================================================

CREATE TABLE documents (
    document_id INT AUTO_INCREMENT PRIMARY KEY,
    document_type ENUM('Purchase Order','Invoice','Delivery Note','Inspection Report','Certificate','Other') NOT NULL,
    reference_type VARCHAR(50),
    reference_id INT NULL,
    file_path VARCHAR(255) NOT NULL,
    uploaded_by INT NOT NULL,
    uploaded_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (uploaded_by) REFERENCES users(user_id)
) ENGINE=InnoDB;

-- =====================================================================
-- 13. FINANCE (supporting Finance Officer role / reports)
-- =====================================================================

CREATE TABLE invoices (
    invoice_id INT AUTO_INCREMENT PRIMARY KEY,
    po_id INT NOT NULL,
    invoice_number VARCHAR(50) NOT NULL UNIQUE,
    amount DECIMAL(15,2) NOT NULL,
    invoice_date DATE NOT NULL,
    due_date DATE,
    status ENUM('Unpaid','Paid','Overdue','Disputed') DEFAULT 'Unpaid',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (po_id) REFERENCES purchase_orders(po_id)
) ENGINE=InnoDB;

-- =====================================================================
-- INDEXES for performance
-- =====================================================================

CREATE INDEX idx_medicines_generic_name ON medicines(generic_name);
CREATE INDEX idx_batches_expiry ON batches(expiry_date);
CREATE INDEX idx_inventory_warehouse ON inventory(warehouse_id);
CREATE INDEX idx_orders_status ON orders(status);
CREATE INDEX idx_po_status ON purchase_orders(status);
CREATE INDEX idx_stock_txn_created ON stock_transactions(created_at);
CREATE INDEX idx_audit_created ON audit_logs(created_at);
CREATE INDEX idx_notifications_user_read ON notifications(user_id, is_read);

SET FOREIGN_KEY_CHECKS = 1;

-- =====================================================================
-- SEED DATA: Roles & Permissions (minimal starter set)
-- =====================================================================

INSERT INTO roles (role_name, description) VALUES
('Super Administrator', 'Full system access'),
('ICT Administrator', 'Manages system configuration and users'),
('Procurement Officer', 'Handles requisitions and purchase orders'),
('Warehouse Manager', 'Manages warehouse operations'),
('Inventory Officer', 'Manages stock levels and transactions'),
('Quality Assurance Officer', 'Inspects and approves incoming goods'),
('Distribution Officer', 'Manages dispatch and delivery'),
('Finance Officer', 'Manages invoices and payments'),
('Regional Medical Store Officer', 'Oversees regional stores'),
('Hospital Pharmacist', 'Places orders on behalf of a hospital'),
('Auditor', 'Read-only access to audit trails and reports'),
('Executive', 'Read-only dashboard access');

INSERT INTO regions (region_name) VALUES
('Dar es Salaam'), ('Mwanza'), ('Iringa'), ('Moshi'),
('Mbeya'), ('Tabora'), ('Dodoma'), ('Mtwara');
