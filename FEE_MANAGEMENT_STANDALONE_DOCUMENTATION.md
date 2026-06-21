# Fee Management System - Standalone Replication Documentation

## Executive Summary

This document provides complete technical specifications for replicating the fee management module as a standalone application with future API integration capabilities. The system handles:

- Fee configuration (categories, discounts, fines)
- Fee assignment (manual, bulk, auto-progression)
- Payment processing (direct, receipt upload, verification)
- Payment plans (installments with late fees)
- Multi-payment support (pay multiple fees at once)
- Student credits (overpayments, refunds)
- Platform/access fees
- Payment accounts (bank/cash tracking)

---

## Table of Contents

1. [Architecture Overview](#architecture-overview)
2. [Database Schema](#database-schema)
3. [Models & Relationships](#models--relationships)
4. [Fee Configuration](#fee-configuration)
5. [Fee Assignment Logic](#fee-assignment-logic)
6. [Payment Processing](#payment-processing)
7. [Payment Plans & Installments](#payment-plans--installments)
8. [Multi-Payment System](#multi-payment-system)
9. [Student Credits & Overpayments](#student-credits--overpayments)
10. [Platform Fee System](#platform-fee-system)
11. [Payment Accounts](#payment-accounts)
12. [API Design Recommendations](#api-design-recommendations)
13. [Constants & Status Codes](#constants--status-codes)
14. [Business Logic Flows](#business-logic-flows)
15. [Implementation Checklist](#implementation-checklist)

---

## Architecture Overview

```
┌─────────────────────────────────────────────────────────────────────────────┐
│                         FEE MANAGEMENT SYSTEM                                │
├─────────────────────────────────────────────────────────────────────────────┤
│                                                                             │
│  ┌──────────────────┐    ┌──────────────────┐    ┌───────────────────────┐ │
│  │  CONFIGURATION   │    │   ASSIGNMENT     │    │  PAYMENT PROCESSING   │ │
│  │  ──────────────  │───▶│  ──────────────  │───▶│  ───────────────────  │ │
│  │  • Categories    │    │  • Bulk Assign   │    │  • Direct Payment     │ │
│  │  • Fines Rules   │    │  • Quick Assign  │    │  • Receipt Upload     │ │
│  │  • Discount Rules│    │  • Auto-Assign   │    │  • Verification       │ │
│  │  • Program Fees  │    │  • Credits Apply │    │  • Multi-Payment      │ │
│  └──────────────────┘    └──────────────────┘    └───────────────────────┘ │
│           │                       │                        │               │
│           ▼                       ▼                        ▼               │
│  ┌───────────────────────────────────────────────────────────────────────┐ │
│  │                       FINANCIAL TRACKING                               │ │
│  │  ┌─────────────┐  ┌──────────────────┐  ┌────────────────────────┐   │ │
│  │  │  PAYMENT    │  │  STUDENT CREDITS │  │  PAYMENT PLANS         │   │ │
│  │  │  ACCOUNTS   │  │  ──────────────  │  │  ────────────          │   │ │
│  │  │  ─────────  │  │  • Overpayments  │  │  • Installments        │   │ │
│  │  │  • Types    │  │  • Refunds       │  │  • Late Fees           │   │ │
│  │  │  • Balances │  │  • Auto-Apply    │  │  • Grace Periods       │   │ │
│  │  │  • Transfers│  │                  │  │                        │   │ │
│  │  └─────────────┘  └──────────────────┘  └────────────────────────┘   │ │
│  └───────────────────────────────────────────────────────────────────────┘ │
│                                                                             │
│  ┌───────────────────────────────────────────────────────────────────────┐ │
│  │                       PLATFORM/ACCESS FEES                             │ │
│  │  • Global Settings • Session/Student Exemptions • Payment Verification │ │
│  └───────────────────────────────────────────────────────────────────────┘ │
│                                                                             │
└─────────────────────────────────────────────────────────────────────────────┘
```

### Core Entities Relationship

```
┌─────────────────┐       ┌─────────────────┐       ┌─────────────────┐
│ Student/User    │───────│ StudentEnroll   │───────│ Fee             │
│                 │  1:N  │ (Enrollment)    │  1:N  │                 │
└─────────────────┘       └─────────────────┘       └─────────────────┘
                                                           │
                          ┌────────────────────────────────┼────────────────────────────────┐
                          │                                │                                │
                          ▼                                ▼                                ▼
                  ┌───────────────┐              ┌─────────────────┐              ┌──────────────┐
                  │ FeesCategory  │              │ PaymentReceipt  │              │ PaymentPlan  │
                  │               │              │                 │              │              │
                  └───────────────┘              └─────────────────┘              └──────────────┘
                          │                                                               │
           ┌──────────────┼──────────────┐                                               │
           │              │              │                                               ▼
           ▼              ▼              ▼                                    ┌───────────────────────┐
   ┌───────────┐  ┌────────────┐  ┌────────────┐                             │ PaymentPlanInstallment│
   │ FeesFine  │  │FeesDiscount│  │ FeesMaster │                             └───────────────────────┘
   │ (M:N)     │  │ (M:N)      │  │ (Template) │                                        │
   └───────────┘  └────────────┘  └────────────┘                                        ▼
                         │                                                   ┌───────────────────────┐
                         ▼                                                   │ PaymentPlanPayment    │
                 ┌────────────┐                                              │ / InstallmentReceipt  │
                 │ StatusType │                                              └───────────────────────┘
                 │ (Eligibility)│
                 └────────────┘
```

---

## Database Schema

### 1. Core Fee Tables

#### `fees_categories` - Fee Type Definitions
```sql
CREATE TABLE fees_categories (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    title VARCHAR(191) UNIQUE NOT NULL,
    slug VARCHAR(191) UNIQUE NOT NULL,
    description TEXT NULL,
    status BOOLEAN DEFAULT 1,
    
    -- Category Type Flags (mutually exclusive recommended)
    is_resit BOOLEAN DEFAULT 0,              -- Resit/retake exam fees
    is_admission BOOLEAN DEFAULT 0,          -- One-time admission fees
    is_first_installment BOOLEAN DEFAULT 0,  -- First tuition installment
    is_second_installment BOOLEAN DEFAULT 0, -- Second tuition installment
    
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- Sample Data
INSERT INTO fees_categories (title, slug, is_first_installment) VALUES 
('First Installment Tuition', 'first-installment-tuition', 1);
INSERT INTO fees_categories (title, slug, is_second_installment) VALUES 
('Second Installment Tuition', 'second-installment-tuition', 1);
INSERT INTO fees_categories (title, slug, is_resit) VALUES 
('Resit Examination Fee', 'resit-examination-fee', 1);
INSERT INTO fees_categories (title, slug, is_admission) VALUES 
('Admission Fee', 'admission-fee', 1);
```

#### `fees` - Individual Student Fee Records (CORE TABLE)
```sql
CREATE TABLE fees (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    
    -- Foreign Keys
    student_enroll_id BIGINT UNSIGNED NOT NULL,  -- Links to student enrollment
    category_id INT UNSIGNED NOT NULL,           -- Fee category
    payment_account_id BIGINT UNSIGNED NULL,     -- Payment destination account
    payment_plan_id BIGINT UNSIGNED NULL,        -- If under installment plan
    
    -- Financial Data
    fee_amount DECIMAL(12,2) NOT NULL DEFAULT 0,      -- Base fee amount
    fine_amount DECIMAL(12,2) NOT NULL DEFAULT 0,     -- Calculated late fees
    discount_amount DECIMAL(12,2) NOT NULL DEFAULT 0, -- Applied discounts
    paid_amount DECIMAL(12,2) NOT NULL DEFAULT 0,     -- Total amount paid
    
    -- Dates
    assign_date DATE NOT NULL,           -- When fee was assigned
    due_date DATE NOT NULL,              -- Payment deadline
    pay_date DATE NULL,                  -- When fully/last paid
    
    -- Payment Info
    payment_method INT NULL,             -- Last payment method used
    note TEXT NULL,                      -- Admin/system notes
    
    -- Status: 0=Unpaid, 1=Paid, 2=Partially Paid, 3=Cancelled
    status TINYINT NOT NULL DEFAULT 0,
    
    -- Audit
    created_by BIGINT UNSIGNED NULL,
    updated_by BIGINT UNSIGNED NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    
    -- Indexes
    INDEX idx_student_enroll (student_enroll_id),
    INDEX idx_category (category_id),
    INDEX idx_status (status),
    INDEX idx_due_date (due_date),
    INDEX idx_status_due (status, due_date),
    
    FOREIGN KEY (category_id) REFERENCES fees_categories(id),
    FOREIGN KEY (payment_account_id) REFERENCES payment_accounts(id),
    FOREIGN KEY (payment_plan_id) REFERENCES payment_plans(id)
);

-- Computed columns (implement in application layer):
-- total_amount = fee_amount + fine_amount - discount_amount
-- remaining_balance = total_amount - paid_amount
```

#### `fees_masters` - Bulk Fee Assignment Templates
```sql
CREATE TABLE fees_masters (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    category_id INT UNSIGNED NOT NULL,
    
    -- Scope/Filter Criteria (all nullable for flexibility)
    faculty_id INT UNSIGNED NULL,
    program_id INT UNSIGNED NULL,
    session_id INT UNSIGNED NULL,
    semester_id INT UNSIGNED NULL,
    section_id INT UNSIGNED NULL,
    
    -- Fee Configuration
    amount DECIMAL(12,2) NOT NULL,
    type TINYINT NOT NULL DEFAULT 1,    -- 1=Fixed Amount, 2=Per Credit Hour
    assign_date DATE NOT NULL,
    due_date DATE NOT NULL,
    
    status BOOLEAN DEFAULT 1,
    created_by BIGINT UNSIGNED NULL,
    updated_by BIGINT UNSIGNED NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    
    FOREIGN KEY (category_id) REFERENCES fees_categories(id)
);

-- Junction table for tracking which students were assigned via which template
CREATE TABLE fees_master_student_enroll (
    fees_master_id BIGINT UNSIGNED NOT NULL,
    student_enroll_id BIGINT UNSIGNED NOT NULL,
    
    PRIMARY KEY (fees_master_id, student_enroll_id),
    FOREIGN KEY (fees_master_id) REFERENCES fees_masters(id) ON DELETE CASCADE,
    FOREIGN KEY (student_enroll_id) REFERENCES student_enrolls(id) ON DELETE CASCADE
);
```

### 2. Discount & Fine Rules

#### `fees_discounts` - Discount Definitions
```sql
CREATE TABLE fees_discounts (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    title VARCHAR(255) NOT NULL,
    
    -- Validity Period
    start_date DATE NOT NULL,
    end_date DATE NOT NULL,
    
    -- Discount Value
    amount DECIMAL(12,2) NOT NULL,
    type TINYINT NOT NULL DEFAULT 1,  -- 1=Fixed Amount, 2=Percentage
    
    status BOOLEAN DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- Which fee categories this discount applies to
CREATE TABLE fees_category_fees_discount (
    fees_category_id INT UNSIGNED NOT NULL,
    fees_discount_id INT UNSIGNED NOT NULL,
    
    PRIMARY KEY (fees_category_id, fees_discount_id),
    FOREIGN KEY (fees_category_id) REFERENCES fees_categories(id) ON DELETE CASCADE,
    FOREIGN KEY (fees_discount_id) REFERENCES fees_discounts(id) ON DELETE CASCADE
);

-- Which student status types are eligible for this discount
-- (e.g., "Scholarship", "Staff Dependent", "Government Sponsored")
CREATE TABLE fees_discount_status_type (
    fees_discount_id INT UNSIGNED NOT NULL,
    status_type_id INT UNSIGNED NOT NULL,
    
    PRIMARY KEY (fees_discount_id, status_type_id),
    FOREIGN KEY (fees_discount_id) REFERENCES fees_discounts(id) ON DELETE CASCADE
    -- status_type_id references a status_types table
);
```

#### `fees_fines` - Late Payment Penalty Rules
```sql
CREATE TABLE fees_fines (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    
    -- Day range after due date when this fine applies
    start_day INT NOT NULL,   -- e.g., 1 = first day after due
    end_day INT NOT NULL,     -- e.g., 30 = up to 30 days after due
    
    -- Fine Value
    amount DECIMAL(12,2) NOT NULL,
    type TINYINT NOT NULL DEFAULT 1,  -- 1=Fixed Amount, 2=Percentage of fee_amount
    
    status BOOLEAN DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- Which fee categories these fines apply to
CREATE TABLE fees_category_fees_fine (
    fees_category_id INT UNSIGNED NOT NULL,
    fees_fine_id INT UNSIGNED NOT NULL,
    
    PRIMARY KEY (fees_category_id, fees_fine_id),
    FOREIGN KEY (fees_category_id) REFERENCES fees_categories(id) ON DELETE CASCADE,
    FOREIGN KEY (fees_fine_id) REFERENCES fees_fines(id) ON DELETE CASCADE
);
```

### 3. Program-Based Fee Configuration

#### `program_semester_fees` - Pre-configured Fees per Program/Semester
```sql
CREATE TABLE program_semester_fees (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    program_id INT UNSIGNED NOT NULL,
    semester_id INT UNSIGNED NOT NULL,
    fees_category_id INT UNSIGNED NOT NULL,
    
    amount DECIMAL(12,2) NOT NULL DEFAULT 0,
    due_days INT NULL,                          -- Days after assignment for due date
    fine_amount DECIMAL(12,2) NULL,             -- Optional fine override
    fine_type ENUM('fixed', 'percentage') NULL,
    
    status BOOLEAN DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    
    UNIQUE KEY unique_program_semester_fee (program_id, semester_id, fees_category_id),
    FOREIGN KEY (program_id) REFERENCES programs(id) ON DELETE CASCADE,
    FOREIGN KEY (semester_id) REFERENCES semesters(id) ON DELETE CASCADE,
    FOREIGN KEY (fees_category_id) REFERENCES fees_categories(id) ON DELETE CASCADE
);

-- Fee breakdown components (for detailed invoices/receipts)
CREATE TABLE program_semester_fee_breakdowns (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    program_semester_fee_id BIGINT UNSIGNED NOT NULL,
    title VARCHAR(255) NOT NULL,              -- e.g., "Tuition", "Library Fee", "ICT Fee"
    amount DECIMAL(12,2) NOT NULL,
    order INT DEFAULT 0,                       -- Display order
    
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    
    FOREIGN KEY (program_semester_fee_id) REFERENCES program_semester_fees(id) ON DELETE CASCADE
);
```

### 4. Payment Processing Tables

#### `payment_receipts` - Student-Submitted Payment Receipts
```sql
CREATE TABLE payment_receipts (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    fee_id BIGINT UNSIGNED NOT NULL,
    student_id BIGINT UNSIGNED NOT NULL,
    
    -- Payment Details
    receipt_file VARCHAR(500) NOT NULL,        -- Uploaded file path
    payment_reference VARCHAR(255) NULL,       -- Bank reference/transaction ID
    payment_date DATE NOT NULL,
    amount DECIMAL(12,2) NOT NULL,
    payment_method INT NULL,
    payment_account_id BIGINT UNSIGNED NULL,   -- Target account (optional)
    
    -- Student Input
    student_note TEXT NULL,
    
    -- Verification
    verification_status ENUM('pending', 'approved', 'rejected') DEFAULT 'pending',
    verification_note TEXT NULL,
    verified_by BIGINT UNSIGNED NULL,
    verified_at TIMESTAMP NULL,
    
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    
    INDEX idx_verification_status (verification_status),
    INDEX idx_fee_student (fee_id, student_id),
    
    FOREIGN KEY (fee_id) REFERENCES fees(id) ON DELETE CASCADE,
    FOREIGN KEY (student_id) REFERENCES students(id) ON DELETE CASCADE,
    FOREIGN KEY (verified_by) REFERENCES users(id) ON DELETE SET NULL,
    FOREIGN KEY (payment_account_id) REFERENCES payment_accounts(id)
);
```

### 5. Payment Plans (Installments)

#### `payment_plans` - Installment Plan Headers
```sql
CREATE TABLE payment_plans (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    fee_id BIGINT UNSIGNED NOT NULL,
    student_id BIGINT UNSIGNED NOT NULL,
    
    total_amount DECIMAL(12,2) NOT NULL,
    installments_count INT NOT NULL DEFAULT 2,
    late_fee_percentage DECIMAL(5,2) DEFAULT 0,  -- % per installment if late
    grace_period_days INT DEFAULT 7,              -- Days after due before late fee
    
    -- Workflow
    created_by BIGINT UNSIGNED NULL,
    approved_by BIGINT UNSIGNED NULL,
    approved_at TIMESTAMP NULL,
    status ENUM('active', 'completed', 'cancelled', 'defaulted') DEFAULT 'active',
    notes TEXT NULL,
    cancellation_reason TEXT NULL,
    
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    
    INDEX idx_student_status (student_id, status),
    
    FOREIGN KEY (fee_id) REFERENCES fees(id) ON DELETE CASCADE,
    FOREIGN KEY (student_id) REFERENCES students(id) ON DELETE CASCADE,
    FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL,
    FOREIGN KEY (approved_by) REFERENCES users(id) ON DELETE SET NULL
);
```

#### `payment_plan_installments` - Individual Installments
```sql
CREATE TABLE payment_plan_installments (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    payment_plan_id BIGINT UNSIGNED NOT NULL,
    
    installment_number INT NOT NULL,
    amount DECIMAL(12,2) NOT NULL,
    due_date DATE NOT NULL,
    grace_period_ends DATE NULL,
    
    -- Payment Tracking
    paid_amount DECIMAL(12,2) DEFAULT 0,
    late_fee DECIMAL(12,2) DEFAULT 0,
    status ENUM('pending', 'partial', 'paid', 'overdue') DEFAULT 'pending',
    paid_at TIMESTAMP NULL,
    
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    
    UNIQUE KEY unique_plan_installment (payment_plan_id, installment_number),
    INDEX idx_status (status),
    INDEX idx_due_date (due_date),
    
    FOREIGN KEY (payment_plan_id) REFERENCES payment_plans(id) ON DELETE CASCADE
);
```

#### `payment_plan_payments` - Installment Payment Records
```sql
CREATE TABLE payment_plan_payments (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    installment_id BIGINT UNSIGNED NOT NULL,
    
    amount DECIMAL(12,2) NOT NULL,
    payment_method INT NOT NULL,
    payment_date DATETIME NOT NULL,
    reference_no VARCHAR(255) NULL,
    receipt_path VARCHAR(500) NULL,
    payment_account_id BIGINT UNSIGNED NULL,
    
    -- Who made the payment (polymorphic)
    paid_by_type VARCHAR(255) NULL,   -- 'App\Models\User' or 'App\Models\Student'
    paid_by_id BIGINT UNSIGNED NULL,
    
    note TEXT NULL,
    
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    
    FOREIGN KEY (installment_id) REFERENCES payment_plan_installments(id) ON DELETE CASCADE,
    FOREIGN KEY (payment_account_id) REFERENCES payment_accounts(id)
);
```

#### `installment_payment_receipts` - Student-Submitted Installment Receipts
```sql
CREATE TABLE installment_payment_receipts (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    installment_id BIGINT UNSIGNED NOT NULL,
    student_id BIGINT UNSIGNED NOT NULL,
    
    receipt_file VARCHAR(500) NOT NULL,
    payment_reference VARCHAR(255) NULL,
    payment_date DATE NOT NULL,
    amount DECIMAL(12,2) NOT NULL,
    payment_method INT NULL,
    note TEXT NULL,
    
    -- Verification
    status ENUM('pending', 'approved', 'rejected') DEFAULT 'pending',
    verification_note TEXT NULL,
    verified_by BIGINT UNSIGNED NULL,
    verified_at TIMESTAMP NULL,
    
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    
    FOREIGN KEY (installment_id) REFERENCES payment_plan_installments(id) ON DELETE CASCADE,
    FOREIGN KEY (student_id) REFERENCES students(id) ON DELETE CASCADE,
    FOREIGN KEY (verified_by) REFERENCES users(id) ON DELETE SET NULL
);
```

### 6. Multi-Payment System

#### `multi_payments` - Bulk Payment Transactions
```sql
CREATE TABLE multi_payments (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    student_id BIGINT UNSIGNED NOT NULL,
    
    total_amount DECIMAL(12,2) NOT NULL,    -- Total balance of selected fees
    amount_paid DECIMAL(12,2) NOT NULL,     -- Actual payment amount
    payment_method VARCHAR(50) NULL,
    transaction_id VARCHAR(255) NULL,
    receipt_path VARCHAR(500) NULL,
    payment_date DATE NOT NULL,
    payment_account_id BIGINT UNSIGNED NULL,
    
    -- Verification
    status ENUM('pending', 'approved', 'rejected') DEFAULT 'pending',
    admin_note TEXT NULL,
    verified_by BIGINT UNSIGNED NULL,
    verified_at TIMESTAMP NULL,
    
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    
    INDEX idx_status (status),
    
    FOREIGN KEY (student_id) REFERENCES students(id) ON DELETE CASCADE,
    FOREIGN KEY (verified_by) REFERENCES users(id) ON DELETE SET NULL,
    FOREIGN KEY (payment_account_id) REFERENCES payment_accounts(id)
);
```

#### `multi_payment_distributions` - Payment Allocation to Fees
```sql
CREATE TABLE multi_payment_distributions (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    multi_payment_id BIGINT UNSIGNED NOT NULL,
    fee_id BIGINT UNSIGNED NOT NULL,
    installment_id BIGINT UNSIGNED NULL,     -- If paying an installment specifically
    
    fee_amount DECIMAL(12,2) NOT NULL,       -- Original fee/balance amount
    amount_applied DECIMAL(12,2) NOT NULL,   -- Amount from this payment
    balance_before DECIMAL(12,2) NOT NULL,
    balance_after DECIMAL(12,2) NOT NULL,
    fee_status_after ENUM('pending', 'partial', 'paid') NOT NULL,
    
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    
    INDEX idx_multi_payment_fee (multi_payment_id, fee_id),
    
    FOREIGN KEY (multi_payment_id) REFERENCES multi_payments(id) ON DELETE CASCADE,
    FOREIGN KEY (fee_id) REFERENCES fees(id) ON DELETE CASCADE,
    FOREIGN KEY (installment_id) REFERENCES payment_plan_installments(id) ON DELETE SET NULL
);
```

### 7. Student Credits System

#### `student_credits` - Credit/Overpayment Records
```sql
CREATE TABLE student_credits (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    student_id BIGINT UNSIGNED NOT NULL,
    
    original_amount DECIMAL(12,2) NOT NULL,
    remaining_amount DECIMAL(12,2) NOT NULL,
    
    source_fee_id BIGINT UNSIGNED NULL,   -- Fee that generated overpayment
    source_type ENUM('overpayment', 'refund_reversal', 'admin_adjustment', 'transfer') DEFAULT 'overpayment',
    status ENUM('available', 'partially_applied', 'fully_applied', 'refunded', 'expired') DEFAULT 'available',
    note TEXT NULL,
    
    -- Refund Workflow
    refund_requested BOOLEAN DEFAULT FALSE,
    refund_requested_at DATETIME NULL,
    refund_requested_by BIGINT UNSIGNED NULL,
    refund_approved BOOLEAN DEFAULT FALSE,
    refund_approved_at DATETIME NULL,
    refund_approved_by BIGINT UNSIGNED NULL,
    refund_processed_at DATETIME NULL,
    refund_processed_by BIGINT UNSIGNED NULL,
    refund_method ENUM('cash', 'bank_transfer', 'cheque', 'mobile_money') NULL,
    refund_reference VARCHAR(255) NULL,
    refund_note TEXT NULL,
    
    created_by BIGINT UNSIGNED NULL,
    updated_by BIGINT UNSIGNED NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    
    INDEX idx_student_status (student_id, status),
    INDEX idx_status_remaining (status, remaining_amount),
    
    FOREIGN KEY (student_id) REFERENCES students(id) ON DELETE CASCADE,
    FOREIGN KEY (source_fee_id) REFERENCES fees(id) ON DELETE SET NULL
);
```

#### `credit_applications` - Credit Usage Tracking
```sql
CREATE TABLE credit_applications (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    student_credit_id BIGINT UNSIGNED NOT NULL,
    fee_id BIGINT UNSIGNED NOT NULL,
    
    amount_applied DECIMAL(12,2) NOT NULL,
    application_type ENUM('auto', 'manual') DEFAULT 'auto',
    note TEXT NULL,
    created_by BIGINT UNSIGNED NULL,
    
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    
    FOREIGN KEY (student_credit_id) REFERENCES student_credits(id) ON DELETE CASCADE,
    FOREIGN KEY (fee_id) REFERENCES fees(id) ON DELETE CASCADE,
    FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL
);
```

### 8. Platform/Access Fee

#### `platform_fee_settings` - Global Configuration
```sql
CREATE TABLE platform_fee_settings (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    title VARCHAR(255) DEFAULT 'Platform Access Fee',
    welcome_message TEXT NULL,
    fee_amount DECIMAL(12,2) DEFAULT 0,
    is_enabled BOOLEAN DEFAULT FALSE,
    payment_instructions TEXT NULL,
    currency VARCHAR(10) DEFAULT 'USD',
    status BOOLEAN DEFAULT 1,
    
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);
```

#### `platform_fee_payments` - Payment Records
```sql
CREATE TABLE platform_fee_payments (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    student_enroll_id BIGINT UNSIGNED NOT NULL,
    session_id INT UNSIGNED NULL,
    
    fee_amount DECIMAL(12,2) NOT NULL,
    paid_amount DECIMAL(12,2) DEFAULT 0,
    receipt_path VARCHAR(500) NULL,
    student_note TEXT NULL,
    payment_date TIMESTAMP NULL,
    
    -- Verification
    status ENUM('pending', 'approved', 'rejected') DEFAULT 'pending',
    admin_note TEXT NULL,
    verified_by BIGINT UNSIGNED NULL,
    verified_at TIMESTAMP NULL,
    
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    
    UNIQUE KEY unique_student_session (student_enroll_id, session_id),
    
    FOREIGN KEY (student_enroll_id) REFERENCES student_enrolls(id) ON DELETE CASCADE,
    FOREIGN KEY (verified_by) REFERENCES users(id) ON DELETE SET NULL
);
```

#### `platform_fee_exemptions` - Exemption Rules
```sql
CREATE TABLE platform_fee_exemptions (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    exemption_type ENUM('student', 'session') NOT NULL,
    student_enroll_id BIGINT UNSIGNED NULL,  -- If type='student'
    session_id INT UNSIGNED NULL,             -- If type='session'
    reason TEXT NULL,
    created_by BIGINT UNSIGNED NOT NULL,
    status BOOLEAN DEFAULT 1,
    
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    
    FOREIGN KEY (student_enroll_id) REFERENCES student_enrolls(id) ON DELETE CASCADE,
    FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE CASCADE
);
```

### 9. Payment Accounts

#### `payment_account_types` - Account Type Definitions
```sql
CREATE TABLE payment_account_types (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    title VARCHAR(255) NOT NULL,
    slug VARCHAR(255) UNIQUE NOT NULL,
    description TEXT NULL,
    status BOOLEAN DEFAULT 1,
    
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- Sample Data
INSERT INTO payment_account_types (title, slug) VALUES 
('Bank Account', 'bank-account'),
('Cash Account', 'cash-account'),
('Mobile Money', 'mobile-money'),
('Online Payment Gateway', 'online-gateway');
```

#### `payment_accounts` - Account Records
```sql
CREATE TABLE payment_accounts (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    title VARCHAR(255) NOT NULL,
    account_number VARCHAR(255) NULL,
    account_type_id BIGINT UNSIGNED NOT NULL,
    
    opening_balance DECIMAL(15,2) DEFAULT 0,
    current_balance DECIMAL(15,2) DEFAULT 0,
    description TEXT NULL,
    status BOOLEAN DEFAULT 1,
    
    created_by BIGINT UNSIGNED NULL,
    updated_by BIGINT UNSIGNED NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    
    FOREIGN KEY (account_type_id) REFERENCES payment_account_types(id) ON DELETE RESTRICT
);
```

#### `payment_account_transactions` - Transaction Log
```sql
CREATE TABLE payment_account_transactions (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    payment_account_id BIGINT UNSIGNED NOT NULL,
    
    transaction_type ENUM('credit', 'debit') NOT NULL,
    amount DECIMAL(15,2) NOT NULL,
    transaction_date DATE NOT NULL,
    title VARCHAR(255) NOT NULL,
    description TEXT NULL,
    
    -- Reference to source entity
    reference_type VARCHAR(100) NULL,   -- 'fees', 'expense', 'income', 'payroll', 'transfer'
    reference_id BIGINT UNSIGNED NULL,
    
    payment_method VARCHAR(50) NULL,
    payment_reference VARCHAR(255) NULL,
    balance_after DECIMAL(15,2) NOT NULL,
    attach VARCHAR(500) NULL,            -- Attachment file path
    
    created_by BIGINT UNSIGNED NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    
    INDEX idx_account_date (payment_account_id, transaction_date),
    INDEX idx_reference (reference_type, reference_id),
    
    FOREIGN KEY (payment_account_id) REFERENCES payment_accounts(id) ON DELETE CASCADE
);
```

#### `payment_account_transfers` - Inter-Account Transfers
```sql
CREATE TABLE payment_account_transfers (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    from_account_id BIGINT UNSIGNED NOT NULL,
    to_account_id BIGINT UNSIGNED NOT NULL,
    
    amount DECIMAL(15,2) NOT NULL,
    transfer_date DATE NOT NULL,
    note TEXT NULL,
    attach VARCHAR(500) NULL,
    
    created_by BIGINT UNSIGNED NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    
    FOREIGN KEY (from_account_id) REFERENCES payment_accounts(id) ON DELETE RESTRICT,
    FOREIGN KEY (to_account_id) REFERENCES payment_accounts(id) ON DELETE RESTRICT
);
```

---

## Models & Relationships

### Fee Model - Key Methods

```php
class Fee
{
    // === RELATIONSHIPS ===
    public function studentEnroll()  // belongsTo StudentEnroll
    public function category()       // belongsTo FeesCategory
    public function paymentAccount() // belongsTo PaymentAccount
    public function paymentPlan()    // belongsTo PaymentPlan
    public function paymentReceipts() // hasMany PaymentReceipt
    public function creditApplications() // hasMany CreditApplication
    public function generatedCredits()   // hasMany StudentCredit (source_fee_id)
    
    // === COMPUTED ATTRIBUTES ===
    
    // Net amount due (fee + fine - discount)
    public function getTotalAmountAttribute(): float
    {
        return $this->fee_amount + $this->fine_amount - $this->discount_amount;
    }
    
    // Balance remaining (can be negative for overpayments)
    public function getRemainingBalanceAttribute(): float
    {
        return $this->total_amount - $this->paid_amount;
    }
    
    // Display balance (always >= 0)
    public function getDisplayRemainingBalanceAttribute(): float
    {
        return max(0, $this->remaining_balance);
    }
    
    // Overpayment amount if any
    public function getOverpaymentAmountAttribute(): float
    {
        return $this->remaining_balance < 0 ? abs($this->remaining_balance) : 0;
    }
    
    // === STATUS CHECKS ===
    public function isFullyPaid(): bool
    {
        return $this->total_amount > 0 && $this->paid_amount >= $this->total_amount;
    }
    
    public function isPartiallyPaid(): bool
    {
        return $this->paid_amount > 0 && $this->paid_amount < $this->total_amount;
    }
    
    public function isUnpaid(): bool
    {
        return $this->paid_amount == 0;
    }
    
    public function isOverpaid(): bool
    {
        return $this->remaining_balance < 0;
    }
    
    public function hasActivePaymentPlan(): bool
    {
        return $this->payment_plan_id && 
               $this->paymentPlan && 
               $this->paymentPlan->status === 'active';
    }
}
```

### FeesCategory Model

```php
class FeesCategory
{
    // === RELATIONSHIPS ===
    public function fees()       // hasMany Fee
    public function masters()    // hasMany FeesMaster
    public function fines()      // belongsToMany FeesFine (pivot: fees_category_fees_fine)
    public function discounts()  // belongsToMany FeesDiscount (pivot: fees_category_fees_discount)
    
    // === SCOPES ===
    public function scopeActive($query) { return $query->where('status', 1); }
    public function scopeResit($query) { return $query->where('is_resit', 1); }
    public function scopeAdmission($query) { return $query->where('is_admission', 1); }
    public function scopeTuition($query) 
    { 
        return $query->where('is_first_installment', 1)
                     ->orWhere('is_second_installment', 1); 
    }
}
```

### StudentCredit Model

```php
class StudentCredit
{
    // === CONSTANTS ===
    const STATUS_AVAILABLE = 'available';
    const STATUS_PARTIALLY_APPLIED = 'partially_applied';
    const STATUS_FULLY_APPLIED = 'fully_applied';
    const STATUS_REFUNDED = 'refunded';
    const STATUS_EXPIRED = 'expired';
    
    const SOURCE_OVERPAYMENT = 'overpayment';
    const SOURCE_REFUND_REVERSAL = 'refund_reversal';
    const SOURCE_ADMIN_ADJUSTMENT = 'admin_adjustment';
    const SOURCE_TRANSFER = 'transfer';
    
    // === RELATIONSHIPS ===
    public function student()     // belongsTo Student
    public function sourceFee()   // belongsTo Fee (source_fee_id)
    public function applications() // hasMany CreditApplication
    
    // === SCOPES ===
    public function scopeAvailable($query)
    {
        return $query->whereIn('status', [self::STATUS_AVAILABLE, self::STATUS_PARTIALLY_APPLIED])
                     ->where('remaining_amount', '>', 0);
    }
    
    // === METHODS ===
    public function hasAvailableBalance(): bool
    {
        return $this->remaining_amount > 0;
    }
    
    public function canBeRefunded(): bool
    {
        return $this->hasAvailableBalance() && 
               !$this->refund_requested;
    }
}
```

---

## Fee Configuration

### Fee Category Types

| Type | Flag | Description | Use Case |
|------|------|-------------|----------|
| Regular | No flags | General-purpose fee | Library fee, ID card fee, etc. |
| Resit | `is_resit=1` | Resit examination fee | Charged when student retakes exam |
| Admission | `is_admission=1` | One-time admission fee | Charged during enrollment |
| First Installment | `is_first_installment=1` | First tuition payment | Semester tuition part 1 |
| Second Installment | `is_second_installment=1` | Second tuition payment | Semester tuition part 2 |

### Discount Rules

```php
// Discount eligibility check
public static function checkDiscountEligibility($discountId, $studentId): bool
{
    $discount = FeesDiscount::find($discountId);
    
    // Check date validity
    $today = date('Y-m-d');
    if ($discount->start_date > $today || $discount->end_date < $today) {
        return false;
    }
    
    // Check if discount is active
    if (!$discount->status) {
        return false;
    }
    
    // Check student status eligibility
    foreach ($discount->statusTypes as $statusType) {
        $hasStatus = Student::where('id', $studentId)
            ->whereHas('statuses', fn($q) => $q->where('status_type_id', $statusType->id))
            ->exists();
            
        if ($hasStatus) {
            return true;
        }
    }
    
    return false;
}
```

### Fine Calculation

```php
// Fine calculation based on days overdue
public function calculateFine(Fee $fee): float
{
    $fine = 0;
    
    if ($fee->due_date >= date('Y-m-d')) {
        return 0; // Not overdue yet
    }
    
    $daysOverdue = (int)((strtotime(date('Y-m-d')) - strtotime($fee->due_date)) / 86400);
    
    foreach ($fee->category->fines->where('status', 1) as $fineRule) {
        if ($fineRule->start_day <= $daysOverdue && $fineRule->end_day >= $daysOverdue) {
            if ($fineRule->type == 1) {
                $fine += $fineRule->amount;  // Fixed amount
            } else {
                $fine += ($fee->fee_amount / 100) * $fineRule->amount;  // Percentage
            }
        }
    }
    
    return $fine;
}
```

---

## Fee Assignment Logic

### Bulk Assignment Flow

```
┌────────────────────────────────────────────────────────────────────┐
│                    BULK FEE ASSIGNMENT                              │
├────────────────────────────────────────────────────────────────────┤
│                                                                    │
│  1. Admin selects criteria:                                        │
│     • Faculty, Program, Session, Semester, Section (filters)       │
│     • Fee Category (must be installment type for FeesMaster)       │
│     • Amount, Type (Fixed/Per Credit), Dates                       │
│                                                                    │
│  2. System filters eligible students by enrollment                  │
│                                                                    │
│  3. FOR EACH selected student:                                      │
│     ┌──────────────────────────────────────────────────────────┐   │
│     │ a. Calculate fee amount:                                  │   │
│     │    IF type = Fixed: amount = base_amount                  │   │
│     │    IF type = Per Credit: amount = credits × base_amount   │   │
│     │                                                           │   │
│     │ b. Create Fee record:                                     │   │
│     │    - student_enroll_id = enrollment.id                    │   │
│     │    - category_id, fee_amount                              │   │
│     │    - assign_date, due_date                                │   │
│     │    - status = 0 (Unpaid)                                  │   │
│     │                                                           │   │
│     │ c. Link to FeesMaster template                            │   │
│     │                                                           │   │
│     │ d. Auto-apply available credits (if tuition category):    │   │
│     │    StudentCreditService->autoApplyCreditsToNewFee($fee)   │   │
│     └──────────────────────────────────────────────────────────┘   │
│                                                                    │
│  4. Create FeesMaster record tracking this batch assignment         │
│                                                                    │
└────────────────────────────────────────────────────────────────────┘
```

### Quick Assignment (Individual)

```php
// Quick assign fee to single student
public function quickAssign($studentEnrollId, $categoryId, $amount, $dueDate)
{
    $fee = Fee::create([
        'student_enroll_id' => $studentEnrollId,
        'category_id' => $categoryId,
        'fee_amount' => $amount,
        'assign_date' => now()->format('Y-m-d'),
        'due_date' => $dueDate,
        'status' => 0,  // Unpaid
        'created_by' => auth()->id(),
    ]);
    
    // Auto-apply credits if tuition
    $creditService = new StudentCreditService();
    $creditService->autoApplyCreditsToNewFee($fee, auth()->id());
    
    return $fee;
}
```

---

## Payment Processing

### Payment Method Codes

```php
const PAYMENT_METHODS = [
    1 => 'Card',
    2 => 'Cash',
    3 => 'Cheque',
    4 => 'Bank Transfer',
    5 => 'E-Wallet / Mobile Money',
    6 => 'Manual / Other',
    7 => 'Online Gateway',
];
```

### Admin Direct Payment Flow

```php
public function processPayment(Request $request, Fee $fee)
{
    // 1. Block if fee has active payment plan
    if ($fee->hasActivePaymentPlan()) {
        throw new Exception('Fee is under active payment plan');
    }
    
    // 2. Calculate discount (check eligibility & date validity)
    $discount = $this->calculateDiscount($fee);
    
    // 3. Calculate fine (based on days overdue)
    $fine = $this->calculateFine($fee);
    
    // 4. Calculate net amount
    $netAmount = $fee->fee_amount - $discount + $fine;
    
    // 5. Process payment
    $currentPaid = $fee->paid_amount ?? 0;
    $newPayment = $request->paid_amount;
    $totalPaid = $currentPaid + $newPayment;
    
    // 6. Check for overpayment
    $overpayment = 0;
    if ($totalPaid > $netAmount) {
        if (!$request->allow_overpayment) {
            throw new Exception('Overpayment not allowed');
        }
        $overpayment = $totalPaid - $netAmount;
    }
    
    // 7. Determine new status
    $status = $totalPaid >= $netAmount ? 1 : 2;  // Paid or Partial
    
    // 8. Update fee record
    $fee->update([
        'discount_amount' => $discount,
        'fine_amount' => $fine,
        'paid_amount' => $totalPaid,
        'pay_date' => $request->pay_date,
        'payment_method' => $request->payment_method,
        'payment_account_id' => $request->payment_account_id,
        'status' => $status,
    ]);
    
    // 9. Create payment account transaction
    if ($request->payment_account_id) {
        $this->createAccountTransaction($request->payment_account_id, $newPayment, $fee);
    }
    
    // 10. Create credit for overpayment
    if ($overpayment > 0) {
        $creditService = new StudentCreditService();
        $creditService->createFromOverpayment($fee, $overpayment, auth()->id());
    }
    
    return $fee;
}
```

### Student Receipt Upload → Verification Flow

```
┌────────────────────────────────────────────────────────────────────┐
│                    RECEIPT VERIFICATION FLOW                        │
├────────────────────────────────────────────────────────────────────┤
│                                                                    │
│  STUDENT SUBMITS:                                                   │
│  ─────────────────                                                  │
│  • Uploads receipt image/PDF                                        │
│  • Enters: payment reference, date, amount, method                  │
│  • PaymentReceipt created with status='pending'                     │
│                                                                    │
│  ADMIN VERIFIES:                                                    │
│  ────────────────                                                   │
│  ┌─────────────────────────────────────────────────────────────┐   │
│  │ APPROVE:                                                     │   │
│  │  1. Update receipt: status='approved', verified_by, at      │   │
│  │  2. Update fee: paid_amount += receipt.amount               │   │
│  │  3. Update fee: status based on new total                   │   │
│  │  4. Create payment account transaction (if account set)     │   │
│  │  5. Create student credit (if overpayment)                  │   │
│  │  6. Create Transaction record (student ledger)              │   │
│  └─────────────────────────────────────────────────────────────┘   │
│  ┌─────────────────────────────────────────────────────────────┐   │
│  │ REJECT:                                                      │   │
│  │  1. Update receipt: status='rejected', verification_note    │   │
│  │  2. No fee updates                                          │   │
│  │  3. Student can resubmit                                    │   │
│  └─────────────────────────────────────────────────────────────┘   │
│                                                                    │
└────────────────────────────────────────────────────────────────────┘
```

---

## Payment Plans & Installments

### Creating a Payment Plan

```php
public function createPaymentPlan(Fee $fee, array $installments, array $options = [])
{
    // Validate: fee must be unpaid/partial, no existing active plan
    if ($fee->hasActivePaymentPlan()) {
        throw new Exception('Fee already has active plan');
    }
    
    DB::beginTransaction();
    
    // Create plan header
    $plan = PaymentPlan::create([
        'fee_id' => $fee->id,
        'student_id' => $fee->studentEnroll->student_id,
        'total_amount' => $fee->remaining_balance,
        'installments_count' => count($installments),
        'late_fee_percentage' => $options['late_fee_percentage'] ?? 0,
        'grace_period_days' => $options['grace_period_days'] ?? 7,
        'status' => 'active',
        'created_by' => auth()->id(),
        'approved_by' => auth()->id(),
        'approved_at' => now(),
    ]);
    
    // Create installments
    $number = 1;
    foreach ($installments as $inst) {
        $dueDate = Carbon::parse($inst['due_date']);
        
        PaymentPlanInstallment::create([
            'payment_plan_id' => $plan->id,
            'installment_number' => $number,
            'amount' => $inst['amount'],
            'due_date' => $dueDate,
            'grace_period_ends' => $dueDate->copy()->addDays($plan->grace_period_days),
            'status' => 'pending',
        ]);
        
        $number++;
    }
    
    // Link plan to fee (blocks direct payment)
    $fee->update(['payment_plan_id' => $plan->id]);
    
    DB::commit();
    
    return $plan;
}
```

### Processing Installment Payment

```php
public function payInstallment(PaymentPlanInstallment $installment, float $amount, array $paymentData)
{
    DB::beginTransaction();
    
    // Calculate late fee if overdue
    $lateFee = 0;
    if ($installment->is_overdue && $installment->late_fee == 0) {
        $lateFee = $installment->amount * ($installment->paymentPlan->late_fee_percentage / 100);
        $installment->update(['late_fee' => $lateFee]);
    }
    
    // Update installment
    $newPaidAmount = $installment->paid_amount + $amount;
    $totalDue = $installment->amount + $installment->late_fee;
    
    $status = 'partial';
    if ($newPaidAmount >= $totalDue) {
        $status = 'paid';
    }
    
    $installment->update([
        'paid_amount' => $newPaidAmount,
        'status' => $status,
        'paid_at' => $status === 'paid' ? now() : null,
    ]);
    
    // Create payment record
    PaymentPlanPayment::create([
        'installment_id' => $installment->id,
        'amount' => $amount,
        'payment_method' => $paymentData['payment_method'],
        'payment_date' => $paymentData['payment_date'],
        'reference_no' => $paymentData['reference_no'] ?? null,
        'payment_account_id' => $paymentData['payment_account_id'] ?? null,
        'paid_by_type' => get_class(auth()->user()),
        'paid_by_id' => auth()->id(),
    ]);
    
    // Update parent fee
    $fee = $installment->paymentPlan->fee;
    $fee->update([
        'paid_amount' => $fee->paid_amount + $amount,
        'status' => $this->determineFeeStatus($fee),
    ]);
    
    // Create payment account transaction
    if (isset($paymentData['payment_account_id'])) {
        $this->createAccountTransaction($paymentData['payment_account_id'], $amount, $fee);
    }
    
    // Check if plan completed
    $plan = $installment->paymentPlan;
    if ($plan->installments()->whereIn('status', ['pending', 'partial', 'overdue'])->count() === 0) {
        $plan->update(['status' => 'completed']);
    }
    
    DB::commit();
}
```

---

## Multi-Payment System

### Multi-Payment Processing

```php
public function processMultiPayment(MultiPayment $multiPayment)
{
    DB::beginTransaction();
    
    foreach ($multiPayment->distributions as $dist) {
        if ($dist->installment_id) {
            // Payment to installment
            $installment = $dist->installment;
            $installment->update([
                'paid_amount' => $installment->paid_amount + $dist->amount_applied,
                'status' => $dist->fee_status_after === 'paid' ? 'paid' : 'partial',
            ]);
            
            // Also update parent fee
            $fee = $installment->paymentPlan->fee;
            $fee->update([
                'paid_amount' => $fee->paid_amount + $dist->amount_applied,
                'status' => $this->determineFeeStatus($fee),
            ]);
        } else {
            // Direct payment to fee
            $fee = $dist->fee;
            $fee->update([
                'paid_amount' => $fee->paid_amount + $dist->amount_applied,
                'status' => $this->mapStatusCode($dist->fee_status_after),
            ]);
        }
    }
    
    // Create account transaction if specified
    if ($multiPayment->payment_account_id) {
        $this->createAccountTransaction(
            $multiPayment->payment_account_id, 
            $multiPayment->amount_paid, 
            null, // Multiple fees
            'Multi-payment #' . $multiPayment->id
        );
    }
    
    $multiPayment->update([
        'status' => 'approved',
        'verified_by' => auth()->id(),
        'verified_at' => now(),
    ]);
    
    DB::commit();
}
```

---

## Student Credits & Overpayments

### StudentCreditService

```php
class StudentCreditService
{
    /**
     * Create credit from fee overpayment
     */
    public function createFromOverpayment(Fee $fee, float $amount, ?int $createdBy = null): StudentCredit
    {
        return StudentCredit::create([
            'student_id' => $fee->studentEnroll->student_id,
            'original_amount' => $amount,
            'remaining_amount' => $amount,
            'source_fee_id' => $fee->id,
            'source_type' => StudentCredit::SOURCE_OVERPAYMENT,
            'status' => StudentCredit::STATUS_AVAILABLE,
            'note' => "Overpayment from {$fee->category->title}",
            'created_by' => $createdBy ?? auth()->id(),
        ]);
    }
    
    /**
     * Create manual credit (admin adjustment)
     */
    public function createManualCredit(int $studentId, float $amount, ?string $note = null): StudentCredit
    {
        return StudentCredit::create([
            'student_id' => $studentId,
            'original_amount' => $amount,
            'remaining_amount' => $amount,
            'source_type' => StudentCredit::SOURCE_ADMIN_ADJUSTMENT,
            'status' => StudentCredit::STATUS_AVAILABLE,
            'note' => $note ?? 'Admin adjustment',
            'created_by' => auth()->id(),
        ]);
    }
    
    /**
     * Get available balance for student
     */
    public function getAvailableBalance(int $studentId): float
    {
        return StudentCredit::where('student_id', $studentId)
            ->available()
            ->sum('remaining_amount');
    }
    
    /**
     * Apply credits to a fee using FIFO
     */
    public function applyCreditsToFee(Fee $fee, ?float $maxAmount = null, string $type = 'auto'): array
    {
        $studentId = $fee->studentEnroll->student_id;
        $remainingBalance = $fee->remaining_balance;
        
        if ($remainingBalance <= 0) {
            return ['total_applied' => 0, 'applications' => []];
        }
        
        // Get available credits (FIFO - oldest first)
        $credits = StudentCredit::where('student_id', $studentId)
            ->available()
            ->orderBy('created_at', 'asc')
            ->get();
        
        $amountToApply = $maxAmount ? min($maxAmount, $remainingBalance) : $remainingBalance;
        $totalApplied = 0;
        $applications = [];
        
        DB::beginTransaction();
        
        foreach ($credits as $credit) {
            if ($amountToApply <= 0) break;
            
            $applyAmount = min($credit->remaining_amount, $amountToApply);
            
            // Create application record
            $application = CreditApplication::create([
                'student_credit_id' => $credit->id,
                'fee_id' => $fee->id,
                'amount_applied' => $applyAmount,
                'application_type' => $type,
                'created_by' => auth()->id(),
            ]);
            
            // Update credit
            $credit->remaining_amount -= $applyAmount;
            $credit->status = $credit->remaining_amount <= 0 
                ? StudentCredit::STATUS_FULLY_APPLIED 
                : StudentCredit::STATUS_PARTIALLY_APPLIED;
            $credit->save();
            
            // Update fee
            $fee->paid_amount += $applyAmount;
            $fee->status = $fee->paid_amount >= $fee->total_amount ? 1 : 2;
            $fee->save();
            
            $totalApplied += $applyAmount;
            $amountToApply -= $applyAmount;
            $applications[] = $application;
        }
        
        DB::commit();
        
        return ['total_applied' => $totalApplied, 'applications' => $applications];
    }
    
    /**
     * Auto-apply credits on new fee assignment (tuition only)
     */
    public function autoApplyCreditsToNewFee(Fee $fee, ?int $createdBy = null): array
    {
        $category = $fee->category;
        
        // Only apply to tuition fees
        if (!$category->is_first_installment && !$category->is_second_installment) {
            return ['total_applied' => 0, 'applications' => [], 'eligible' => false];
        }
        
        return $this->applyCreditsToFee($fee, null, 'auto');
    }
}
```

---

## Platform Fee System

### Middleware Access Control

```php
class PlatformFeeMiddleware
{
    public function handle($request, Closure $next)
    {
        // Skip if not student
        if (!auth('student')->check()) {
            return $next($request);
        }
        
        // Check if platform fee is enabled
        $settings = PlatformFeeSetting::first();
        if (!$settings || !$settings->is_enabled) {
            return $next($request);
        }
        
        $student = auth('student')->user();
        $enrollment = $student->currentEnroll;
        
        if (!$enrollment) {
            return $next($request);
        }
        
        // Check session exemption
        $sessionExempt = PlatformFeeExemption::where('exemption_type', 'session')
            ->where('session_id', $enrollment->session_id)
            ->where('status', 1)
            ->exists();
        
        if ($sessionExempt) {
            return $next($request);
        }
        
        // Check student exemption
        $studentExempt = PlatformFeeExemption::where('exemption_type', 'student')
            ->where('student_enroll_id', $enrollment->id)
            ->where('status', 1)
            ->exists();
        
        if ($studentExempt) {
            return $next($request);
        }
        
        // Check if already paid
        $payment = PlatformFeePayment::where('student_enroll_id', $enrollment->id)
            ->where('session_id', $enrollment->session_id)
            ->where('status', 'approved')
            ->first();
        
        if ($payment) {
            return $next($request);
        }
        
        // Redirect to payment page
        return redirect()->route('student.platform-fee.payment');
    }
}
```

---

## Payment Accounts

### Account Transaction Creation

```php
public function createAccountTransaction(
    int $accountId, 
    float $amount, 
    ?Fee $fee = null, 
    ?string $title = null
): PaymentAccountTransaction 
{
    $account = PaymentAccount::findOrFail($accountId);
    $newBalance = $account->current_balance + $amount;
    
    $transaction = PaymentAccountTransaction::create([
        'payment_account_id' => $accountId,
        'transaction_type' => 'credit',
        'amount' => $amount,
        'transaction_date' => now()->format('Y-m-d'),
        'title' => $title ?? ($fee 
            ? "Fee Payment - {$fee->studentEnroll->student->full_name}" 
            : 'Payment'),
        'description' => $fee ? "Fee payment for {$fee->category->title}" : null,
        'reference_type' => $fee ? 'fees' : null,
        'reference_id' => $fee?->id,
        'balance_after' => $newBalance,
        'created_by' => auth()->id(),
    ]);
    
    $account->update(['current_balance' => $newBalance]);
    
    return $transaction;
}
```

---

## API Design Recommendations

### Suggested REST API Endpoints

```
# Authentication
POST   /api/auth/login              # Student/Admin login
POST   /api/auth/logout             # Logout
GET    /api/auth/me                 # Current user info

# Fees (Student)
GET    /api/fees                    # List student's fees (with filters)
GET    /api/fees/{id}               # Fee details
GET    /api/fees/{id}/history       # Payment history for fee

# Payments (Student)
POST   /api/fees/{id}/receipt       # Upload payment receipt
GET    /api/receipts                # List submitted receipts
GET    /api/receipts/{id}           # Receipt details

# Payment Plans (Student)
GET    /api/payment-plans           # List student's payment plans
GET    /api/payment-plans/{id}      # Plan details with installments
POST   /api/installments/{id}/receipt  # Upload installment receipt

# Multi-Payment (Student)
POST   /api/multi-payment           # Create multi-payment
GET    /api/multi-payments          # List multi-payments
GET    /api/multi-payments/{id}     # Multi-payment details

# Credits (Student)
GET    /api/credits                 # List student credits
GET    /api/credits/balance         # Total available balance
POST   /api/credits/{id}/apply      # Apply credit to fee

# Platform Fee (Student)
GET    /api/platform-fee/status     # Check if payment required
POST   /api/platform-fee/payment    # Submit platform fee payment

# Admin Endpoints
GET    /api/admin/fees              # List all fees (with filters)
POST   /api/admin/fees              # Assign fee to student
POST   /api/admin/fees/{id}/pay     # Record direct payment
POST   /api/admin/fees/{id}/cancel  # Cancel fee

POST   /api/admin/receipts/{id}/approve  # Approve receipt
POST   /api/admin/receipts/{id}/reject   # Reject receipt

POST   /api/admin/payment-plans          # Create payment plan
POST   /api/admin/payment-plans/{id}/cancel # Cancel plan
POST   /api/admin/installments/{id}/pay  # Record installment payment

GET    /api/admin/credits           # List all credits
POST   /api/admin/credits           # Create manual credit
POST   /api/admin/credits/{id}/refund-approve
POST   /api/admin/credits/{id}/refund-process

# Reports
GET    /api/reports/fees-summary    # Fee collection summary
GET    /api/reports/outstanding     # Outstanding fees
GET    /api/reports/collections     # Collection report by date
GET    /api/reports/account-statement/{id}  # Account statement
```

### Webhook Events

```php
// Fee events
'fee.assigned'     // When fee is assigned
'fee.paid'         // When fee is fully paid
'fee.partial'      // When partial payment made
'fee.overdue'      // When fee becomes overdue (scheduled job)

// Receipt events
'receipt.submitted' // When student uploads receipt
'receipt.approved'  // When admin approves
'receipt.rejected'  // When admin rejects

// Payment plan events
'plan.created'      // When plan is created
'plan.completed'    // When plan is fully paid
'plan.defaulted'    // When plan defaults (scheduled job)
'installment.due'   // 7 days before installment due
'installment.overdue' // When installment becomes overdue

// Credit events
'credit.created'    // When credit is created
'credit.applied'    // When credit is applied to fee
'credit.refund_requested' // When refund requested
'credit.refunded'   // When refund processed
```

---

## Constants & Status Codes

### Fee Status Codes

```php
const FEE_UNPAID = 0;
const FEE_PAID = 1;
const FEE_PARTIALLY_PAID = 2;
const FEE_CANCELLED = 3;
```

### Payment Receipt Status

```php
const RECEIPT_PENDING = 'pending';
const RECEIPT_APPROVED = 'approved';
const RECEIPT_REJECTED = 'rejected';
```

### Payment Plan Status

```php
const PLAN_ACTIVE = 'active';
const PLAN_COMPLETED = 'completed';
const PLAN_CANCELLED = 'cancelled';
const PLAN_DEFAULTED = 'defaulted';
```

### Installment Status

```php
const INSTALLMENT_PENDING = 'pending';
const INSTALLMENT_PARTIAL = 'partial';
const INSTALLMENT_PAID = 'paid';
const INSTALLMENT_OVERDUE = 'overdue';
```

### Student Credit Status

```php
const CREDIT_AVAILABLE = 'available';
const CREDIT_PARTIALLY_APPLIED = 'partially_applied';
const CREDIT_FULLY_APPLIED = 'fully_applied';
const CREDIT_REFUNDED = 'refunded';
const CREDIT_EXPIRED = 'expired';
```

### Credit Source Types

```php
const SOURCE_OVERPAYMENT = 'overpayment';
const SOURCE_REFUND_REVERSAL = 'refund_reversal';
const SOURCE_ADMIN_ADJUSTMENT = 'admin_adjustment';
const SOURCE_TRANSFER = 'transfer';
```

### Discount/Fine Types

```php
const TYPE_FIXED = 1;
const TYPE_PERCENTAGE = 2;
```

### Payment Methods

```php
const PAYMENT_CARD = 1;
const PAYMENT_CASH = 2;
const PAYMENT_CHEQUE = 3;
const PAYMENT_BANK_TRANSFER = 4;
const PAYMENT_EWALLET = 5;
const PAYMENT_MANUAL = 6;
const PAYMENT_ONLINE = 7;
```

---

## Business Logic Flows

### Complete Payment Flow Diagram

```
┌────────────────────────────────────────────────────────────────────────────┐
│                        COMPLETE PAYMENT FLOW                                │
├────────────────────────────────────────────────────────────────────────────┤
│                                                                            │
│  ┌──────────────────┐                                                      │
│  │ FEE ASSIGNED     │                                                      │
│  │ status=0 (Unpaid)│                                                      │
│  └────────┬─────────┘                                                      │
│           │                                                                │
│           ├─────────────────────────────────────────────────────┐          │
│           │                                                     │          │
│           ▼                                                     ▼          │
│  ┌──────────────────┐                               ┌───────────────────┐  │
│  │ PAYMENT PLAN     │                               │ DIRECT PAYMENT    │  │
│  │ Created          │                               │ (Admin/Student)   │  │
│  │ fee.plan_id set  │                               │                   │  │
│  └────────┬─────────┘                               └─────────┬─────────┘  │
│           │                                                   │            │
│           ▼                                                   ▼            │
│  ┌──────────────────┐                              ┌────────────────────┐  │
│  │ INSTALLMENTS     │                              │ CALCULATE:         │  │
│  │ Created          │                              │ • Discounts        │  │
│  │                  │                              │ • Fines (if late)  │  │
│  └────────┬─────────┘                              │ • Net Amount       │  │
│           │                                        └──────────┬─────────┘  │
│           │                                                   │            │
│           ▼                                                   ▼            │
│  ┌───────────────────────────────────────────────────────────────────────┐ │
│  │                       PAYMENT MADE                                     │ │
│  │  • Via Receipt Upload (requires verification)                          │ │
│  │  • Via Admin Direct Entry (auto-approved)                              │ │
│  │  • Via Multi-Payment (requires verification)                           │ │
│  └─────────────────────────────┬─────────────────────────────────────────┘ │
│                                │                                           │
│                                ▼                                           │
│  ┌───────────────────────────────────────────────────────────────────────┐ │
│  │  UPDATE FEE:                                                           │ │
│  │  paid_amount += payment_amount                                         │ │
│  │  status = paid_amount >= total_amount ? PAID : PARTIAL                 │ │
│  └─────────────────────────────┬─────────────────────────────────────────┘ │
│                                │                                           │
│         ┌──────────────────────┴──────────────────────┐                    │
│         │                                             │                    │
│         ▼                                             ▼                    │
│  ┌──────────────────┐                      ┌────────────────────┐          │
│  │ OVERPAYMENT?     │                      │ PAYMENT ACCOUNT    │          │
│  │ paid > total     │                      │ TRANSACTION        │          │
│  └────────┬─────────┘                      │ Created            │          │
│           │ YES                            └────────────────────┘          │
│           ▼                                                                │
│  ┌──────────────────┐                                                      │
│  │ STUDENT CREDIT   │                                                      │
│  │ Created          │                                                      │
│  │ remaining = overpayment                                                 │
│  └──────────────────┘                                                      │
│                                                                            │
└────────────────────────────────────────────────────────────────────────────┘
```

---

## Implementation Checklist

### Phase 1: Core Fee Management
- [ ] Database tables: fees_categories, fees, fees_masters
- [ ] Fee Model with computed attributes
- [ ] Fee assignment (bulk & quick)
- [ ] Basic payment recording
- [ ] Fee status management

### Phase 2: Discounts & Fines
- [ ] Database tables: fees_discounts, fees_fines, junction tables
- [ ] Discount eligibility checking
- [ ] Fine calculation based on days overdue
- [ ] Integration with payment processing

### Phase 3: Payment Processing
- [ ] Payment receipt upload
- [ ] Receipt verification workflow
- [ ] Multi-payment support
- [ ] Payment account transactions

### Phase 4: Payment Plans
- [ ] Payment plan creation
- [ ] Installment generation
- [ ] Installment payment processing
- [ ] Late fee calculation
- [ ] Plan completion/cancellation

### Phase 5: Student Credits
- [ ] Credit creation from overpayment
- [ ] Manual credit adjustment
- [ ] Credit application to fees
- [ ] Auto-apply on fee assignment
- [ ] Refund workflow

### Phase 6: Platform Fee
- [ ] Platform fee settings
- [ ] Payment submission & verification
- [ ] Exemption management
- [ ] Access control middleware

### Phase 7: Reporting
- [ ] Fee collection reports
- [ ] Outstanding fees report
- [ ] Payment account statements
- [ ] Student fee history

### Phase 8: API Development
- [ ] RESTful endpoints
- [ ] Authentication (Sanctum/Passport)
- [ ] Webhook system
- [ ] API documentation

---

## File Structure (Recommended)

```
app/
├── Models/
│   ├── Fee.php
│   ├── FeesCategory.php
│   ├── FeesMaster.php
│   ├── FeesDiscount.php
│   ├── FeesFine.php
│   ├── ProgramSemesterFee.php
│   ├── ProgramSemesterFeeBreakdown.php
│   ├── PaymentReceipt.php
│   ├── PaymentPlan.php
│   ├── PaymentPlanInstallment.php
│   ├── PaymentPlanPayment.php
│   ├── InstallmentPaymentReceipt.php
│   ├── MultiPayment.php
│   ├── MultiPaymentDistribution.php
│   ├── StudentCredit.php
│   ├── CreditApplication.php
│   ├── PlatformFeeSetting.php
│   ├── PlatformFeePayment.php
│   ├── PlatformFeeExemption.php
│   ├── PaymentAccount.php
│   ├── PaymentAccountType.php
│   ├── PaymentAccountTransaction.php
│   └── PaymentAccountTransfer.php
├── Services/
│   ├── FeeService.php
│   ├── FeeCalculationService.php
│   ├── PaymentService.php
│   ├── PaymentPlanService.php
│   ├── StudentCreditService.php
│   └── PaymentAccountService.php
├── Http/
│   ├── Controllers/
│   │   ├── Admin/
│   │   │   ├── FeesCategoryController.php
│   │   │   ├── FeesMasterController.php
│   │   │   ├── FeesStudentController.php
│   │   │   ├── FeesDiscountController.php
│   │   │   ├── FeesFineController.php
│   │   │   ├── PaymentPlanController.php
│   │   │   ├── PaymentVerificationController.php
│   │   │   ├── StudentCreditController.php
│   │   │   ├── PaymentAccountController.php
│   │   │   └── PlatformFeeController.php
│   │   └── Student/
│   │       ├── FeesController.php
│   │       ├── ManualPaymentController.php
│   │       ├── MultiPaymentController.php
│   │       ├── PaymentPlanController.php
│   │       └── PlatformFeePaymentController.php
│   └── Middleware/
│       └── PlatformFeeMiddleware.php
└── Events/
    ├── FeeAssigned.php
    ├── FeePaid.php
    ├── CreditCreated.php
    └── PaymentReceived.php

database/
└── migrations/
    └── [all migration files listed in schema section]
```

---

*Document Version: 2.0*
*Generated: February 2026*
*Based on: PAXHI Academic Management System Fee Module*
