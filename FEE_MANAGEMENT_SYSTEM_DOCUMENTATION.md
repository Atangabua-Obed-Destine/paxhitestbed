# Fee Management System - Comprehensive Technical Documentation

## Overview

This document provides a complete technical specification of the fee management module in the Laravel-based academic management system. It is designed to enable replication as a standalone fee management application with future API integration.

---

## Table of Contents

1. [System Architecture](#system-architecture)
2. [Database Schema](#database-schema)
3. [Models & Relationships](#models--relationships)
4. [Fee Configuration System](#fee-configuration-system)
5. [Fee Assignment Logic](#fee-assignment-logic)
6. [Payment Processing](#payment-processing)
7. [Fee Calculations](#fee-calculations)
8. [Student Credits & Overpayments](#student-credits--overpayments)
9. [Payment Plans & Installments](#payment-plans--installments)
10. [Multi-Payment System](#multi-payment-system)
11. [Platform Fee System](#platform-fee-system)
12. [Payment Accounts](#payment-accounts)
13. [Routes & Controllers](#routes--controllers)
14. [Business Logic Flows](#business-logic-flows)
15. [Status Codes & Constants](#status-codes--constants)
16. [Integration Points](#integration-points)
17. [API Considerations](#api-considerations)

---

## System Architecture

```
┌─────────────────────────────────────────────────────────────────────┐
│                        FEE MANAGEMENT SYSTEM                        │
├─────────────────────────────────────────────────────────────────────┤
│                                                                     │
│  ┌─────────────────┐    ┌─────────────────┐    ┌─────────────────┐ │
│  │  FEE CONFIG     │    │  FEE ASSIGNMENT │    │  PAYMENT        │ │
│  │  ─────────────  │───▶│  ─────────────  │───▶│  PROCESSING     │ │
│  │  • Categories   │    │  • Student Fees │    │  • Receipts     │ │
│  │  • Fines        │    │  • Discounts    │    │  • Verification │ │
│  │  • Discounts    │    │  • Fine Calc    │    │  • Multi-Pay    │ │
│  │  • Program Fees │    │                 │    │  • Installments │ │
│  └─────────────────┘    └─────────────────┘    └─────────────────┘ │
│           │                      │                      │          │
│           ▼                      ▼                      ▼          │
│  ┌─────────────────────────────────────────────────────────────┐   │
│  │                    PAYMENT ACCOUNTS                          │   │
│  │  • Account Types • Transactions • Transfers • Balances      │   │
│  └─────────────────────────────────────────────────────────────┘   │
│                              │                                     │
│                              ▼                                     │
│  ┌─────────────────────────────────────────────────────────────┐   │
│  │                    STUDENT CREDITS                           │   │
│  │  • Overpayments • Refunds • Auto-Apply • Credit Applications │   │
│  └─────────────────────────────────────────────────────────────┘   │
│                                                                     │
└─────────────────────────────────────────────────────────────────────┘
```

---

## Database Schema

### Core Fee Tables

#### 1. `fees_categories` - Fee Category Definitions
```sql
CREATE TABLE fees_categories (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    title VARCHAR(191) UNIQUE NOT NULL,
    slug VARCHAR(191) UNIQUE NOT NULL,
    description TEXT NULL,
    status BOOLEAN DEFAULT 1,
    is_resit BOOLEAN DEFAULT 0,           -- For resit exam fees
    is_admission BOOLEAN DEFAULT 0,        -- For admission fees
    is_first_installment BOOLEAN DEFAULT 0,  -- First installment tuition
    is_second_installment BOOLEAN DEFAULT 0, -- Second installment tuition
    created_at TIMESTAMP,
    updated_at TIMESTAMP
);
```

#### 2. `fees_masters` - Fee Templates for Bulk Assignment
```sql
CREATE TABLE fees_masters (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    category_id INT UNSIGNED NOT NULL,
    faculty_id INT UNSIGNED NULL,
    program_id INT UNSIGNED NULL,
    session_id INT UNSIGNED NULL,
    semester_id INT UNSIGNED NULL,
    section_id INT UNSIGNED NULL,
    amount DECIMAL(10,2) NOT NULL,
    type TINYINT DEFAULT 1 COMMENT '1=Fixed, 2=Per Credit',
    assign_date DATE NOT NULL,
    due_date DATE NOT NULL,
    status BOOLEAN DEFAULT 1,
    created_by BIGINT UNSIGNED NULL,
    updated_by BIGINT UNSIGNED NULL,
    created_at TIMESTAMP,
    updated_at TIMESTAMP,
    
    FOREIGN KEY (category_id) REFERENCES fees_categories(id)
);
```

#### 3. `fees_master_student_enroll` - Junction for Bulk Assignments
```sql
CREATE TABLE fees_master_student_enroll (
    fees_master_id BIGINT UNSIGNED,
    student_enroll_id BIGINT UNSIGNED,
    
    FOREIGN KEY (fees_master_id) REFERENCES fees_masters(id) ON DELETE CASCADE,
    FOREIGN KEY (student_enroll_id) REFERENCES student_enrolls(id) ON DELETE CASCADE,
    PRIMARY KEY (fees_master_id, student_enroll_id)
);
```

#### 4. `fees` - Individual Student Fee Records
```sql
CREATE TABLE fees (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    student_enroll_id BIGINT UNSIGNED NOT NULL,
    category_id INT UNSIGNED NOT NULL,
    fee_amount DOUBLE(10,2) NOT NULL,
    fine_amount DOUBLE(10,2) DEFAULT 0,
    discount_amount DOUBLE(10,2) DEFAULT 0,
    paid_amount DOUBLE(10,2) DEFAULT 0,
    assign_date DATE NOT NULL,
    due_date DATE NOT NULL,
    pay_date DATE NULL,
    payment_method INT NULL,
    payment_account_id BIGINT UNSIGNED NULL,
    payment_plan_id BIGINT UNSIGNED NULL,
    note TEXT NULL,
    status TINYINT DEFAULT 0 COMMENT '0=Unpaid, 1=Paid, 2=Partially Paid, 3=Cancelled',
    created_by BIGINT UNSIGNED NULL,
    updated_by BIGINT UNSIGNED NULL,
    created_at TIMESTAMP,
    updated_at TIMESTAMP,
    
    FOREIGN KEY (student_enroll_id) REFERENCES student_enrolls(id),
    FOREIGN KEY (category_id) REFERENCES fees_categories(id),
    FOREIGN KEY (payment_account_id) REFERENCES payment_accounts(id),
    FOREIGN KEY (payment_plan_id) REFERENCES payment_plans(id)
);
```

#### 5. `fees_discounts` - Discount Definitions
```sql
CREATE TABLE fees_discounts (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    title VARCHAR(191) NOT NULL,
    start_date DATE NOT NULL,
    end_date DATE NOT NULL,
    amount DECIMAL(10,2) NOT NULL,
    type TINYINT DEFAULT 1 COMMENT '1=Fixed Amount, 2=Percentage',
    status BOOLEAN DEFAULT 1,
    created_at TIMESTAMP,
    updated_at TIMESTAMP
);
```

#### 6. `fees_category_fees_discount` - Category-Discount Junction
```sql
CREATE TABLE fees_category_fees_discount (
    fees_category_id INT UNSIGNED,
    fees_discount_id INT UNSIGNED,
    
    FOREIGN KEY (fees_category_id) REFERENCES fees_categories(id) ON DELETE CASCADE,
    FOREIGN KEY (fees_discount_id) REFERENCES fees_discounts(id) ON DELETE CASCADE
);
```

#### 7. `fees_discount_status_type` - Status-Based Discount Eligibility
```sql
CREATE TABLE fees_discount_status_type (
    fees_discount_id INT UNSIGNED,
    status_type_id INT UNSIGNED,
    
    FOREIGN KEY (fees_discount_id) REFERENCES fees_discounts(id) ON DELETE CASCADE,
    FOREIGN KEY (status_type_id) REFERENCES status_types(id) ON DELETE CASCADE
);
```

#### 8. `fees_fines` - Late Fee/Fine Definitions
```sql
CREATE TABLE fees_fines (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    start_day INT NOT NULL,          -- Days after due date (start range)
    end_day INT NOT NULL,            -- Days after due date (end range)
    amount DECIMAL(10,2) NOT NULL,
    type TINYINT DEFAULT 1 COMMENT '1=Fixed, 2=Percentage',
    status BOOLEAN DEFAULT 1,
    created_at TIMESTAMP,
    updated_at TIMESTAMP
);
```

#### 9. `fees_category_fees_fine` - Category-Fine Junction
```sql
CREATE TABLE fees_category_fees_fine (
    fees_category_id INT UNSIGNED,
    fees_fine_id INT UNSIGNED,
    
    FOREIGN KEY (fees_category_id) REFERENCES fees_categories(id) ON DELETE CASCADE,
    FOREIGN KEY (fees_fine_id) REFERENCES fees_fines(id) ON DELETE CASCADE
);
```

### Program-Based Fee Configuration

#### 10. `program_semester_fees` - Program/Semester Fee Amounts
```sql
CREATE TABLE program_semester_fees (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    program_id INT UNSIGNED NOT NULL,
    semester_id INT UNSIGNED NOT NULL,
    fees_category_id INT UNSIGNED NOT NULL,
    amount DECIMAL(10,2) DEFAULT 0,
    due_days INT NULL,                -- Days after assignment for due date
    fine_amount DECIMAL(10,2) NULL,
    fine_type ENUM('fixed', 'percentage') NULL,
    status BOOLEAN DEFAULT 1,
    created_at TIMESTAMP,
    updated_at TIMESTAMP,
    
    FOREIGN KEY (program_id) REFERENCES programs(id) ON DELETE CASCADE,
    FOREIGN KEY (semester_id) REFERENCES semesters(id) ON DELETE CASCADE,
    FOREIGN KEY (fees_category_id) REFERENCES fees_categories(id) ON DELETE CASCADE,
    UNIQUE KEY (program_id, semester_id, fees_category_id)
);
```

#### 11. `program_semester_fee_breakdowns` - Fee Component Breakdowns
```sql
CREATE TABLE program_semester_fee_breakdowns (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    program_semester_fee_id BIGINT UNSIGNED NOT NULL,
    title VARCHAR(191) NOT NULL,
    amount DECIMAL(10,2) NOT NULL,
    order INT DEFAULT 0,
    created_at TIMESTAMP,
    updated_at TIMESTAMP,
    
    FOREIGN KEY (program_semester_fee_id) REFERENCES program_semester_fees(id) ON DELETE CASCADE
);
```

### Payment Processing Tables

#### 12. `payment_receipts` - Payment Receipt Records
```sql
CREATE TABLE payment_receipts (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    fee_id BIGINT UNSIGNED NOT NULL,
    student_id BIGINT UNSIGNED NOT NULL,
    receipt_file VARCHAR(255) NOT NULL,
    payment_reference VARCHAR(255) NULL,
    payment_date DATE NOT NULL,
    amount DOUBLE(10,2) NOT NULL,
    payment_method INT NULL,
    student_note TEXT NULL,
    verification_status ENUM('pending', 'approved', 'rejected') DEFAULT 'pending',
    verification_note TEXT NULL,
    verified_by BIGINT UNSIGNED NULL,
    verified_at TIMESTAMP NULL,
    payment_account_id BIGINT UNSIGNED NULL,
    created_at TIMESTAMP,
    updated_at TIMESTAMP,
    
    FOREIGN KEY (fee_id) REFERENCES fees(id) ON DELETE CASCADE,
    FOREIGN KEY (student_id) REFERENCES students(id) ON DELETE CASCADE,
    FOREIGN KEY (verified_by) REFERENCES users(id) ON DELETE SET NULL,
    FOREIGN KEY (payment_account_id) REFERENCES payment_accounts(id)
);
```

#### 13. `payment_plans` - Installment Payment Plans
```sql
CREATE TABLE payment_plans (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    fee_id BIGINT UNSIGNED NOT NULL,
    student_id BIGINT UNSIGNED NOT NULL,
    total_amount DECIMAL(10,2) NOT NULL,
    installments_count INT DEFAULT 2,
    late_fee_percentage DECIMAL(5,2) DEFAULT 0,
    grace_period_days INT DEFAULT 7,
    created_by BIGINT UNSIGNED NULL,
    approved_by BIGINT UNSIGNED NULL,
    approved_at TIMESTAMP NULL,
    status ENUM('active', 'completed', 'cancelled', 'defaulted') DEFAULT 'active',
    notes TEXT NULL,
    cancellation_reason TEXT NULL,
    created_at TIMESTAMP,
    updated_at TIMESTAMP,
    
    FOREIGN KEY (fee_id) REFERENCES fees(id) ON DELETE CASCADE,
    FOREIGN KEY (student_id) REFERENCES students(id) ON DELETE CASCADE,
    FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL,
    FOREIGN KEY (approved_by) REFERENCES users(id) ON DELETE SET NULL
);
```

#### 14. `payment_plan_installments` - Installment Schedule
```sql
CREATE TABLE payment_plan_installments (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    payment_plan_id BIGINT UNSIGNED NOT NULL,
    installment_number INT NOT NULL,
    amount DECIMAL(10,2) NOT NULL,
    due_date DATE NOT NULL,
    paid_amount DECIMAL(10,2) DEFAULT 0,
    late_fee DECIMAL(10,2) DEFAULT 0,
    status ENUM('pending', 'partial', 'paid', 'overdue') DEFAULT 'pending',
    grace_period_ends DATE NULL,
    paid_at TIMESTAMP NULL,
    created_at TIMESTAMP,
    updated_at TIMESTAMP,
    
    FOREIGN KEY (payment_plan_id) REFERENCES payment_plans(id) ON DELETE CASCADE,
    UNIQUE KEY (payment_plan_id, installment_number)
);
```

#### 15. `payment_plan_payments` - Installment Payment Records
```sql
CREATE TABLE payment_plan_payments (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    installment_id BIGINT UNSIGNED NOT NULL,
    amount DECIMAL(10,2) NOT NULL,
    payment_method INT NULL,
    payment_date DATETIME NOT NULL,
    reference_no VARCHAR(255) NULL,
    receipt_path VARCHAR(255) NULL,
    paid_by_type VARCHAR(255) NULL,      -- Polymorphic type
    paid_by_id BIGINT UNSIGNED NULL,     -- Polymorphic ID
    note TEXT NULL,
    payment_account_id BIGINT UNSIGNED NULL,
    created_at TIMESTAMP,
    updated_at TIMESTAMP,
    
    FOREIGN KEY (installment_id) REFERENCES payment_plan_installments(id) ON DELETE CASCADE,
    FOREIGN KEY (payment_account_id) REFERENCES payment_accounts(id)
);
```

#### 16. `installment_payment_receipts` - Student-Submitted Installment Receipts
```sql
CREATE TABLE installment_payment_receipts (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    installment_id BIGINT UNSIGNED NOT NULL,
    student_id BIGINT UNSIGNED NOT NULL,
    receipt_file VARCHAR(255) NOT NULL,
    payment_reference VARCHAR(255) NULL,
    payment_date DATE NOT NULL,
    amount DECIMAL(10,2) NOT NULL,
    payment_method INT NULL,
    student_note TEXT NULL,
    status ENUM('pending', 'approved', 'rejected') DEFAULT 'pending',
    admin_note TEXT NULL,
    verified_by BIGINT UNSIGNED NULL,
    verified_at TIMESTAMP NULL,
    created_at TIMESTAMP,
    updated_at TIMESTAMP,
    
    FOREIGN KEY (installment_id) REFERENCES payment_plan_installments(id) ON DELETE CASCADE,
    FOREIGN KEY (student_id) REFERENCES students(id) ON DELETE CASCADE,
    FOREIGN KEY (verified_by) REFERENCES users(id) ON DELETE SET NULL
);
```

### Multi-Payment System

#### 17. `multi_payments` - Bulk Payment Transactions
```sql
CREATE TABLE multi_payments (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    student_id BIGINT UNSIGNED NOT NULL,
    total_amount DECIMAL(10,2) NOT NULL,
    amount_paid DECIMAL(10,2) NOT NULL,
    payment_method VARCHAR(50) NULL,
    transaction_id VARCHAR(255) NULL,
    receipt_path VARCHAR(255) NULL,
    status ENUM('pending', 'approved', 'rejected') DEFAULT 'pending',
    admin_note TEXT NULL,
    payment_date DATE NOT NULL,
    verified_by BIGINT UNSIGNED NULL,
    verified_at TIMESTAMP NULL,
    created_at TIMESTAMP,
    updated_at TIMESTAMP,
    
    FOREIGN KEY (student_id) REFERENCES students(id) ON DELETE CASCADE,
    FOREIGN KEY (verified_by) REFERENCES users(id) ON DELETE SET NULL
);
```

#### 18. `multi_payment_distributions` - Payment Distribution Across Fees
```sql
CREATE TABLE multi_payment_distributions (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    multi_payment_id BIGINT UNSIGNED NOT NULL,
    fee_id BIGINT UNSIGNED NOT NULL,
    installment_id BIGINT UNSIGNED NULL,
    fee_amount DECIMAL(10,2) NOT NULL,
    amount_applied DECIMAL(10,2) NOT NULL,
    balance_before DECIMAL(10,2) NOT NULL,
    balance_after DECIMAL(10,2) NOT NULL,
    fee_status_after ENUM('pending', 'partial', 'paid') NOT NULL,
    created_at TIMESTAMP,
    updated_at TIMESTAMP,
    
    FOREIGN KEY (multi_payment_id) REFERENCES multi_payments(id) ON DELETE CASCADE,
    FOREIGN KEY (fee_id) REFERENCES fees(id) ON DELETE CASCADE,
    FOREIGN KEY (installment_id) REFERENCES payment_plan_installments(id) ON DELETE SET NULL
);
```

### Student Credits System

#### 19. `student_credits` - Credit/Overpayment Records
```sql
CREATE TABLE student_credits (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    student_id BIGINT UNSIGNED NOT NULL,
    original_amount DECIMAL(12,2) NOT NULL,
    remaining_amount DECIMAL(12,2) NOT NULL,
    source_fee_id BIGINT UNSIGNED NULL,
    source_type ENUM('overpayment', 'refund_reversal', 'admin_adjustment', 'transfer') DEFAULT 'overpayment',
    status ENUM('available', 'partially_applied', 'fully_applied', 'refunded', 'expired') DEFAULT 'available',
    note TEXT NULL,
    
    -- Refund tracking
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
    created_at TIMESTAMP,
    updated_at TIMESTAMP,
    
    FOREIGN KEY (student_id) REFERENCES students(id) ON DELETE CASCADE,
    FOREIGN KEY (source_fee_id) REFERENCES fees(id) ON DELETE SET NULL
);
```

#### 20. `credit_applications` - Credit Usage Records
```sql
CREATE TABLE credit_applications (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    student_credit_id BIGINT UNSIGNED NOT NULL,
    fee_id BIGINT UNSIGNED NOT NULL,
    amount_applied DECIMAL(12,2) NOT NULL,
    application_type ENUM('auto', 'manual') DEFAULT 'auto',
    note TEXT NULL,
    created_by BIGINT UNSIGNED NULL,
    created_at TIMESTAMP,
    updated_at TIMESTAMP,
    
    FOREIGN KEY (student_credit_id) REFERENCES student_credits(id) ON DELETE CASCADE,
    FOREIGN KEY (fee_id) REFERENCES fees(id) ON DELETE CASCADE,
    FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL
);
```

### Platform Fee System

#### 21. `platform_fee_settings` - Global Platform Fee Config
```sql
CREATE TABLE platform_fee_settings (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    title VARCHAR(255) DEFAULT 'Platform Access Fee',
    welcome_message TEXT NULL,
    fee_amount DECIMAL(10,2) DEFAULT 0,
    is_enabled BOOLEAN DEFAULT FALSE,
    payment_instructions TEXT NULL,
    currency VARCHAR(10) DEFAULT 'FRW',
    status BOOLEAN DEFAULT 1,
    created_at TIMESTAMP,
    updated_at TIMESTAMP
);
```

#### 22. `platform_fee_payments` - Platform Fee Payments
```sql
CREATE TABLE platform_fee_payments (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    student_enroll_id BIGINT UNSIGNED NOT NULL,
    session_id INT UNSIGNED NOT NULL,
    fee_amount DECIMAL(10,2) NOT NULL,
    paid_amount DECIMAL(10,2) DEFAULT 0,
    receipt_path VARCHAR(255) NULL,
    student_note TEXT NULL,
    status ENUM('pending', 'approved', 'rejected') DEFAULT 'pending',
    admin_note TEXT NULL,
    verified_by BIGINT UNSIGNED NULL,
    verified_at TIMESTAMP NULL,
    payment_date TIMESTAMP NULL,
    created_at TIMESTAMP,
    updated_at TIMESTAMP,
    
    FOREIGN KEY (student_enroll_id) REFERENCES student_enrolls(id) ON DELETE CASCADE,
    FOREIGN KEY (session_id) REFERENCES sessions(id) ON DELETE CASCADE,
    UNIQUE KEY (student_enroll_id, session_id)
);
```

#### 23. `platform_fee_exemptions` - Fee Exemptions
```sql
CREATE TABLE platform_fee_exemptions (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    exemption_type ENUM('student', 'session') NOT NULL,
    student_enroll_id BIGINT UNSIGNED NULL,
    session_id INT UNSIGNED NULL,
    reason TEXT NULL,
    created_by BIGINT UNSIGNED NOT NULL,
    status BOOLEAN DEFAULT 1,
    created_at TIMESTAMP,
    updated_at TIMESTAMP,
    
    FOREIGN KEY (student_enroll_id) REFERENCES student_enrolls(id) ON DELETE CASCADE,
    FOREIGN KEY (session_id) REFERENCES sessions(id) ON DELETE CASCADE,
    FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE CASCADE
);
```

### Payment Account System

#### 24. `payment_account_types` - Account Type Definitions
```sql
CREATE TABLE payment_account_types (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    title VARCHAR(191) NOT NULL,
    slug VARCHAR(191) UNIQUE NOT NULL,
    description TEXT NULL,
    status BOOLEAN DEFAULT 1,
    created_at TIMESTAMP,
    updated_at TIMESTAMP
);
```

#### 25. `payment_accounts` - Payment Account Records
```sql
CREATE TABLE payment_accounts (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    title VARCHAR(191) NOT NULL,
    account_number VARCHAR(191) NULL,
    account_type_id BIGINT UNSIGNED NOT NULL,
    opening_balance DECIMAL(15,2) DEFAULT 0,
    current_balance DECIMAL(15,2) DEFAULT 0,
    description TEXT NULL,
    status TINYINT DEFAULT 1,
    created_by BIGINT UNSIGNED NULL,
    updated_by BIGINT UNSIGNED NULL,
    created_at TIMESTAMP,
    updated_at TIMESTAMP,
    
    FOREIGN KEY (account_type_id) REFERENCES payment_account_types(id) ON DELETE RESTRICT
);
```

#### 26. `payment_account_transactions` - Account Transaction Log
```sql
CREATE TABLE payment_account_transactions (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    payment_account_id BIGINT UNSIGNED NOT NULL,
    transaction_type ENUM('credit', 'debit') NOT NULL,
    amount DECIMAL(15,2) NOT NULL,
    transaction_date DATE NOT NULL,
    title VARCHAR(255) NOT NULL,
    description TEXT NULL,
    payment_method INT NULL,
    reference_type VARCHAR(100) NULL,   -- 'fees', 'expense', 'income', etc.
    reference_id BIGINT UNSIGNED NULL,
    balance_after DECIMAL(15,2) NOT NULL,
    created_by BIGINT UNSIGNED NULL,
    created_at TIMESTAMP,
    updated_at TIMESTAMP,
    
    FOREIGN KEY (payment_account_id) REFERENCES payment_accounts(id) ON DELETE CASCADE
);
```

---

## Models & Relationships

### Fee Model (`App\Models\Fee`)

```php
class Fee extends Model
{
    use Auditable;

    protected $fillable = [
        'student_enroll_id', 'category_id', 'fee_amount', 'fine_amount', 
        'discount_amount', 'paid_amount', 'assign_date', 'due_date', 
        'pay_date', 'payment_method', 'payment_account_id', 'note', 
        'status', 'payment_plan_id', 'created_by', 'updated_by',
    ];

    // Relationships
    public function studentEnroll()          // → StudentEnroll
    public function category()               // → FeesCategory
    public function paymentAccount()         // → PaymentAccount
    public function resitRequest()           // → ResitRequest (hasOne)
    public function paymentReceipts()        // → PaymentReceipt (hasMany)
    public function pendingReceipt()         // → PaymentReceipt (hasOne, pending)
    public function approvedReceipts()       // → PaymentReceipt (hasMany, approved)
    public function paymentPlan()            // → PaymentPlan
    public function creditApplications()     // → CreditApplication (hasMany)
    public function generatedCredits()       // → StudentCredit (hasMany)
    public function pendingMultiPaymentDistributions() // → MultiPaymentDistribution

    // Computed Attributes
    public function getTotalAmountAttribute()         // fee + fine - discount
    public function getRemainingBalanceAttribute()    // total - paid
    public function getDisplayRemainingBalanceAttribute() // max(0, remaining)
    public function getOverpaymentAmountAttribute()   // negative balance as positive
    
    // Status Checks
    public function isOverpaid()
    public function isFullyPaid()
    public function isPartiallyPaid()
    public function isUnpaid()
    public function hasActivePaymentPlan()
    public function hasPendingMultiPayment()
}
```

### FeesCategory Model (`App\Models\FeesCategory`)

```php
class FeesCategory extends Model
{
    use Auditable;

    protected $fillable = [
        'title', 'slug', 'description', 'status', 
        'is_resit', 'is_admission', 
        'is_first_installment', 'is_second_installment',
    ];

    // Relationships
    public function masters()    // → FeesMaster (hasMany)
    public function fees()       // → Fee (hasMany)
    public function fines()      // → FeesFine (belongsToMany)
    public function discounts()  // → FeesDiscount (belongsToMany)
}
```

### FeesMaster Model (`App\Models\FeesMaster`)

```php
class FeesMaster extends Model
{
    use Auditable;

    protected $fillable = [
        'category_id', 'faculty_id', 'program_id', 'session_id', 
        'semester_id', 'section_id', 'amount', 'type', 
        'assign_date', 'due_date', 'status', 'created_by', 'updated_by',
    ];

    // Relationships
    public function studentEnrolls()  // → StudentEnroll (belongsToMany)
    public function category()        // → FeesCategory
    public function faculty()         // → Faculty
    public function program()         // → Program
    public function session()         // → Session
    public function semester()        // → Semester
    public function section()         // → Section
}
```

### FeesDiscount Model (`App\Models\FeesDiscount`)

```php
class FeesDiscount extends Model
{
    use Auditable;

    protected $fillable = [
        'title', 'start_date', 'end_date', 'amount', 'type', 'status',
    ];

    // Relationships
    public function feesCategories()  // → FeesCategory (belongsToMany)
    public function statusTypes()     // → StatusType (belongsToMany)

    // Static Methods
    public static function availability($discount, $student)
    // Checks if student is eligible based on their status types
}
```

### FeesFine Model (`App\Models\FeesFine`)

```php
class FeesFine extends Model
{
    use Auditable;

    protected $fillable = [
        'start_day', 'end_day', 'amount', 'type', 'status',
    ];

    // Relationships
    public function feesCategories()  // → FeesCategory (belongsToMany)
}
```

### PaymentPlan Model (`App\Models\PaymentPlan`)

```php
class PaymentPlan extends Model
{
    use Auditable;

    protected $fillable = [
        'fee_id', 'student_id', 'total_amount', 'installments_count',
        'late_fee_percentage', 'grace_period_days', 'created_by',
        'approved_by', 'approved_at', 'status', 'notes', 'cancellation_reason',
    ];

    protected $casts = [
        'total_amount' => 'decimal:2',
        'late_fee_percentage' => 'decimal:2',
        'approved_at' => 'datetime',
    ];

    // Relationships
    public function fee()           // → Fee
    public function student()       // → Student
    public function creator()       // → User
    public function approver()      // → User
    public function installments()  // → PaymentPlanInstallment (hasMany)

    // Computed Attributes
    public function getTotalPaidAttribute()
    public function getRemainingBalanceAttribute()
    public function getProgressPercentageAttribute()
    public function getNextInstallmentAttribute()
    public function getOverdueInstallmentsAttribute()

    // Status Checks
    public function isActive()
    public function isCompleted()
    public function isCancelled()
    public function isDefaulted()

    // Actions
    public function markAsCompleted()
    public function cancel($reason = null)
}
```

### PaymentPlanInstallment Model (`App\Models\PaymentPlanInstallment`)

```php
class PaymentPlanInstallment extends Model
{
    use Auditable;

    protected $fillable = [
        'payment_plan_id', 'installment_number', 'amount', 'due_date',
        'paid_amount', 'late_fee', 'status', 'grace_period_ends', 'paid_at',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'paid_amount' => 'decimal:2',
        'late_fee' => 'decimal:2',
        'due_date' => 'date',
        'grace_period_ends' => 'date',
        'paid_at' => 'datetime',
    ];

    // Relationships
    public function paymentPlan()         // → PaymentPlan
    public function payments()            // → PaymentPlanPayment (hasMany)
    public function paymentReceipts()     // → InstallmentPaymentReceipt (hasMany)
    public function pendingReceipts()     // → InstallmentPaymentReceipt (hasMany, pending)
    public function pendingMultiPaymentDistributions()  // → MultiPaymentDistribution

    // Computed Attributes
    public function getRemainingBalanceAttribute()
    public function getIsOverdueAttribute()
    public function getIsGracePeriodActiveAttribute()
    public function getPendingReceiptAttribute()

    // Status Checks
    public function isPending()
    public function isPartial()
    public function isPaid()
    public function isOverdue()
    public function hasPendingMultiPayment()

    // Actions
    public function recordPayment($amount, $paymentData = [])
}
```

### StudentCredit Model (`App\Models\StudentCredit`)

```php
class StudentCredit extends Model
{
    use Auditable;

    // Constants
    const STATUS_AVAILABLE = 'available';
    const STATUS_PARTIALLY_APPLIED = 'partially_applied';
    const STATUS_FULLY_APPLIED = 'fully_applied';
    const STATUS_REFUNDED = 'refunded';
    const STATUS_EXPIRED = 'expired';

    const SOURCE_OVERPAYMENT = 'overpayment';
    const SOURCE_REFUND_REVERSAL = 'refund_reversal';
    const SOURCE_ADMIN_ADJUSTMENT = 'admin_adjustment';
    const SOURCE_TRANSFER = 'transfer';

    const REFUND_CASH = 'cash';
    const REFUND_BANK_TRANSFER = 'bank_transfer';
    const REFUND_CHEQUE = 'cheque';
    const REFUND_MOBILE_MONEY = 'mobile_money';

    protected $fillable = [
        'student_id', 'original_amount', 'remaining_amount', 'source_fee_id',
        'source_type', 'status', 'note', 'refund_requested', 'refund_requested_at',
        'refund_requested_by', 'refund_approved', 'refund_approved_at',
        'refund_approved_by', 'refund_processed_at', 'refund_processed_by',
        'refund_method', 'refund_reference', 'refund_note',
        'created_by', 'updated_by',
    ];

    // Relationships
    public function student()           // → Student
    public function sourceFee()         // → Fee
    public function applications()      // → CreditApplication (hasMany)
    public function createdBy()         // → User
    public function refundRequestedBy() // → User
    public function refundApprovedBy()  // → User
    public function refundProcessedBy() // → User

    // Query Scopes
    public function scopeAvailable($query)
    public function scopePendingRefund($query)
    public function scopeApprovedRefund($query)

    // Checks
    public function hasAvailableBalance()
    public function canBeRefunded()

    // Computed
    public function getAppliedAmountAttribute()
}
```

### MultiPayment Model (`App\Models\MultiPayment`)

```php
class MultiPayment extends Model
{
    use HasFactory, Auditable;

    protected $fillable = [
        'student_id', 'total_amount', 'amount_paid', 'payment_method',
        'transaction_id', 'receipt_path', 'status', 'admin_note',
        'payment_date', 'verified_by', 'verified_at'
    ];

    protected $casts = [
        'payment_date' => 'date',
        'verified_at' => 'datetime',
        'total_amount' => 'decimal:2',
        'amount_paid' => 'decimal:2',
    ];

    // Relationships
    public function student()       // → Student
    public function distributions() // → MultiPaymentDistribution (hasMany)
    public function verifiedBy()    // → User

    // Helper Methods
    public function getTotalDistributedAmount()
    public function getStatusBadgeClass()
    public function getStatusLabel()
}
```

### PaymentReceipt Model (`App\Models\PaymentReceipt`)

```php
class PaymentReceipt extends Model
{
    use Auditable;

    protected $fillable = [
        'fee_id', 'student_id', 'receipt_file', 'payment_reference',
        'payment_date', 'amount', 'payment_method', 'student_note',
        'verification_status', 'verification_note', 'verified_by',
        'verified_at', 'payment_account_id',
    ];

    protected $casts = [
        'payment_date' => 'date',
        'verified_at' => 'datetime',
        'amount' => 'decimal:2',
    ];

    // Relationships
    public function fee()       // → Fee
    public function student()   // → Student
    public function verifier()  // → User

    // Status Checks
    public function isPending()
    public function isApproved()
    public function isRejected()

    // Query Scopes
    public function scopePending($query)
    public function scopeApproved($query)
    public function scopeRejected($query)
}
```

---

## Fee Configuration System

### Fee Categories Configuration

Fee categories define the types of fees in the system:

| Category Type | Flags Set | Use Case |
|---------------|-----------|----------|
| Regular Fee | None | Standard miscellaneous fees |
| Resit Fee | `is_resit = 1` | Resit examination fees |
| Admission Fee | `is_admission = 1` | One-time admission fees |
| First Installment | `is_first_installment = 1` | First tuition payment |
| Second Installment | `is_second_installment = 1` | Second tuition payment |

### Program Semester Fee Configuration

Fees can be pre-configured per program and semester type:

```php
// Configuration structure
[
    'program_id' => 1,
    'semester_id' => 1,         // Or semester_type for bulk config
    'fees_category_id' => 1,    // First Installment
    'amount' => 500000,
    'due_days' => 30,           // Days after assignment
    'fine_amount' => 10000,
    'fine_type' => 'fixed',     // or 'percentage'
    'breakdowns' => [           // Optional fee breakdown
        ['title' => 'Tuition', 'amount' => 400000],
        ['title' => 'Library Fee', 'amount' => 50000],
        ['title' => 'ICT Fee', 'amount' => 50000],
    ]
]
```

### Discount Configuration

Discounts are configured with:
- **Date Range**: Start and end dates for validity
- **Amount**: Fixed amount or percentage
- **Type**: `1` = Fixed, `2` = Percentage
- **Fee Categories**: Which categories apply
- **Status Types**: Student status types eligible for discount

### Fine/Late Fee Configuration

Fines are configured with day ranges after due date:

```php
[
    'start_day' => 1,    // 1 day after due
    'end_day' => 30,     // Up to 30 days after due
    'amount' => 5000,    // Fine amount
    'type' => 1,         // 1=Fixed, 2=Percentage
]
```

---

## Fee Assignment Logic

### Manual Assignment via FeesMaster

```
┌──────────────────────────────────────────────────────────────┐
│                    FEE ASSIGNMENT FLOW                        │
├──────────────────────────────────────────────────────────────┤
│                                                              │
│  1. Admin selects:                                           │
│     • Faculty → Program → Session → Semester → Section       │
│     • Fee Category (first/second installment only)           │
│     • Amount, Assign Date, Due Date                          │
│                                                              │
│  2. System filters eligible students based on enrollment     │
│                                                              │
│  3. For each selected student:                               │
│     ┌─────────────────────────────────────────────────────┐  │
│     │ a. Create Fee record with:                          │  │
│     │    • student_enroll_id                              │  │
│     │    • category_id, fee_amount                        │  │
│     │    • assign_date, due_date                          │  │
│     │    • status = 0 (Unpaid)                            │  │
│     │                                                     │  │
│     │ b. Link to FeesMaster via pivot table               │  │
│     │                                                     │  │
│     │ c. Auto-apply available credits (if tuition)        │  │
│     │    StudentCreditService::autoApplyCreditsToNewFee() │  │
│     └─────────────────────────────────────────────────────┘  │
│                                                              │
└──────────────────────────────────────────────────────────────┘
```

### Auto Assignment on Progression

When a student progresses to a new semester, fees can be auto-assigned based on `program_semester_fees` configuration.

### Quick Assignment

Quick assignment allows assigning fees to individual students without the full filter workflow.

---

## Payment Processing

### Payment Methods

```php
// Payment method constants (used throughout)
1 => 'Card',
2 => 'Cash',
3 => 'Cheque',
4 => 'Bank Transfer',
5 => 'E-Wallet/Mobile Money',
6 => 'Manual/Other',
```

### Direct Admin Payment Flow

```
┌──────────────────────────────────────────────────────────────┐
│                 ADMIN DIRECT PAYMENT FLOW                     │
├──────────────────────────────────────────────────────────────┤
│                                                              │
│  1. Admin selects fee to process                             │
│                                                              │
│  2. CHECK: Fee has active payment plan?                      │
│     └── YES: Block direct payment, use installment system    │
│     └── NO: Continue                                         │
│                                                              │
│  3. CALCULATE DISCOUNT:                                      │
│     For each discount linked to fee category:                │
│       IF student eligible (status match) AND date valid:     │
│         IF type=1: discount += fixed_amount                  │
│         IF type=2: discount += (fee_amount * amount/100)     │
│                                                              │
│  4. CALCULATE FINE:                                          │
│     IF due_date < today:                                     │
│       days_overdue = today - due_date                        │
│       For each fine linked to category:                      │
│         IF start_day <= days <= end_day:                     │
│           IF type=1: fine += fixed_amount                    │
│           IF type=2: fine += (fee_amount * amount/100)       │
│                                                              │
│  5. CALCULATE NET: net = fee - discount + fine               │
│                                                              │
│  6. PROCESS PAYMENT:                                         │
│     total_paid = current_paid + new_payment                  │
│     IF total_paid > net AND !allow_overpayment: REJECT       │
│     IF total_paid >= net: status = PAID                      │
│     ELSE: status = PARTIALLY_PAID                            │
│                                                              │
│  7. UPDATE PAYMENT ACCOUNT:                                  │
│     IF payment_account_id:                                   │
│       Create transaction record                              │
│       Update account balance                                 │
│                                                              │
│  8. HANDLE OVERPAYMENT:                                      │
│     IF total_paid > net:                                     │
│       Create StudentCredit for overpayment amount            │
│                                                              │
│  9. CREATE RECORDS:                                          │
│     • PaymentReceipt (auto-approved)                         │
│     • Transaction (student ledger)                           │
│                                                              │
└──────────────────────────────────────────────────────────────┘
```

### Student Payment Receipt Upload Flow

```
┌──────────────────────────────────────────────────────────────┐
│              STUDENT PAYMENT RECEIPT FLOW                     │
├──────────────────────────────────────────────────────────────┤
│                                                              │
│  STUDENT SIDE:                                               │
│  1. Student views unpaid/partial fees                        │
│  2. Uploads payment receipt with:                            │
│     • Receipt file (PDF/Image)                               │
│     • Payment reference                                      │
│     • Payment date                                           │
│     • Amount paid                                            │
│     • Payment method                                         │
│     • Optional note                                          │
│  3. Receipt saved with status = 'pending'                    │
│                                                              │
│  ADMIN SIDE:                                                 │
│  4. Admin reviews pending receipts                           │
│  5. APPROVE:                                                 │
│     • Update receipt status = 'approved'                     │
│     • Update fee paid_amount += receipt_amount               │
│     • Update fee status based on total paid                  │
│     • Create payment account transaction                     │
│     • Handle overpayment if applicable                       │
│                                                              │
│  6. REJECT:                                                  │
│     • Update receipt status = 'rejected'                     │
│     • Add rejection reason                                   │
│     • No fee update                                          │
│                                                              │
└──────────────────────────────────────────────────────────────┘
```

---

## Fee Calculations

### Total Amount Formula

```php
$total = $fee_amount + $fine_amount - $discount_amount;
```

### Remaining Balance Formula

```php
$remaining = $total - $paid_amount;
// Can be negative (overpayment)
```

### Discount Calculation Logic

```php
public function calculateDiscount(Fee $fee): float
{
    $discount = 0;
    $today = date('Y-m-d');
    $studentId = $fee->studentEnroll->student_id;
    
    foreach ($fee->category->discounts->where('status', 1) as $discountRule) {
        // Check date validity
        if ($discountRule->start_date > $today || $discountRule->end_date < $today) {
            continue;
        }
        
        // Check student eligibility based on status
        $eligible = FeesDiscount::availability($discountRule->id, $studentId);
        if (!$eligible) {
            continue;
        }
        
        // Calculate discount
        if ($discountRule->type == 1) {
            // Fixed amount
            $discount += $discountRule->amount;
        } else {
            // Percentage
            $discount += ($fee->fee_amount / 100) * $discountRule->amount;
        }
    }
    
    return $discount;
}
```

### Fine Calculation Logic

```php
public function calculateFine(Fee $fee): float
{
    $fine = 0;
    
    // Only calculate if overdue
    if ($fee->due_date >= date('Y-m-d')) {
        return 0;
    }
    
    $daysOverdue = (int)((strtotime(date('Y-m-d')) - strtotime($fee->due_date)) / 86400);
    
    foreach ($fee->category->fines->where('status', 1) as $fineRule) {
        if ($fineRule->start_day <= $daysOverdue && $fineRule->end_day >= $daysOverdue) {
            if ($fineRule->type == 1) {
                // Fixed amount
                $fine += $fineRule->amount;
            } else {
                // Percentage
                $fine += ($fee->fee_amount / 100) * $fineRule->amount;
            }
        }
    }
    
    return $fine;
}
```

---

## Student Credits & Overpayments

### StudentCreditService Methods

```php
class StudentCreditService
{
    // Create credit from overpayment on a fee
    public function createFromOverpayment(Fee $fee, float $amount, ?int $createdBy): StudentCredit

    // Create manual credit (admin adjustment)
    public function createManualCredit(int $studentId, float $amount, ?string $note, ?int $createdBy): StudentCredit

    // Get total available credit for student
    public function getAvailableBalance(int $studentId): float

    // Get all available credits (FIFO order)
    public function getAvailableCredits(int $studentId): Collection

    // Apply credits to a fee
    public function applyCreditsToFee(
        Fee $fee, 
        ?float $maxAmount = null, 
        string $applicationType = 'auto', 
        ?int $createdBy = null
    ): array  // Returns ['total_applied', 'applications']

    // Auto-apply on new fee assignment (tuition only)
    public function autoApplyCreditsToNewFee(Fee $fee, ?int $createdBy): array
}
```

### Credit Application Flow

```
┌──────────────────────────────────────────────────────────────┐
│               CREDIT APPLICATION FLOW                         │
├──────────────────────────────────────────────────────────────┤
│                                                              │
│  1. GET AVAILABLE CREDITS:                                   │
│     Credits where:                                           │
│       • status IN ('available', 'partially_applied')         │
│       • remaining_amount > 0                                 │
│     ORDER BY created_at ASC (FIFO)                           │
│                                                              │
│  2. FOR EACH CREDIT (until fee paid):                        │
│     apply_amount = MIN(credit.remaining, fee.remaining)      │
│                                                              │
│     a. Create CreditApplication record                       │
│     b. Reduce credit.remaining_amount                        │
│     c. Update credit.status                                  │
│     d. Increase fee.paid_amount                              │
│     e. Update fee.status                                     │
│     f. Create Transaction record                             │
│                                                              │
│  3. RETURN: total_applied, applications[]                    │
│                                                              │
└──────────────────────────────────────────────────────────────┘
```

### Refund Processing

```
┌──────────────────────────────────────────────────────────────┐
│                    REFUND WORKFLOW                            │
├──────────────────────────────────────────────────────────────┤
│                                                              │
│  1. REQUEST REFUND (Admin/Student):                          │
│     SET refund_requested = true                              │
│     SET refund_requested_at = now()                          │
│     SET refund_requested_by = user_id                        │
│                                                              │
│  2. APPROVE REFUND (Admin):                                  │
│     SET refund_approved = true                               │
│     SET refund_approved_at = now()                           │
│     SET refund_approved_by = user_id                         │
│                                                              │
│  3. PROCESS REFUND (Admin):                                  │
│     SET refund_processed_at = now()                          │
│     SET refund_processed_by = user_id                        │
│     SET refund_method = 'cash'|'bank_transfer'|...           │
│     SET refund_reference = 'reference_number'                │
│     SET refund_note = 'notes'                                │
│     SET remaining_amount = 0                                 │
│     SET status = 'refunded'                                  │
│                                                              │
└──────────────────────────────────────────────────────────────┘
```

---

## Payment Plans & Installments

### Payment Plan Creation Flow

```
┌──────────────────────────────────────────────────────────────┐
│               PAYMENT PLAN CREATION                           │
├──────────────────────────────────────────────────────────────┤
│                                                              │
│  1. SELECT FEE (must be unpaid/partial, no existing plan)    │
│                                                              │
│  2. CONFIGURE PLAN:                                          │
│     • Number of installments (2-12)                          │
│     • Late fee percentage per installment                    │
│     • Grace period days                                      │
│     • Due dates for each installment                         │
│                                                              │
│  3. CREATE PAYMENT PLAN:                                     │
│     • total_amount = fee.remaining_balance                   │
│     • status = 'active'                                      │
│                                                              │
│  4. CREATE INSTALLMENTS:                                     │
│     For i = 1 to installments_count:                         │
│       • installment_number = i                               │
│       • amount = total_amount / installments_count           │
│       • due_date = scheduled_date                            │
│       • grace_period_ends = due_date + grace_days            │
│       • status = 'pending'                                   │
│                                                              │
│  5. LINK PLAN TO FEE:                                        │
│     fee.payment_plan_id = plan.id                            │
│     (Blocks direct payment to fee)                           │
│                                                              │
└──────────────────────────────────────────────────────────────┘
```

### Installment Payment Flow

```
┌──────────────────────────────────────────────────────────────┐
│             INSTALLMENT PAYMENT PROCESSING                    │
├──────────────────────────────────────────────────────────────┤
│                                                              │
│  1. SELECT INSTALLMENT (pending/partial/overdue)             │
│                                                              │
│  2. CALCULATE LATE FEE (if overdue):                         │
│     IF today > grace_period_ends:                            │
│       late_fee = installment.amount * plan.late_fee_pct/100  │
│                                                              │
│  3. RECORD PAYMENT:                                          │
│     • Update installment.paid_amount                         │
│     • Update installment.late_fee                            │
│     • Update installment.status                              │
│     • Update installment.paid_at                             │
│                                                              │
│  4. UPDATE ORIGINAL FEE:                                     │
│     • fee.paid_amount += payment_amount                      │
│     • Update fee.status                                      │
│                                                              │
│  5. CHECK PLAN COMPLETION:                                   │
│     IF all installments paid:                                │
│       plan.status = 'completed'                              │
│       fee.status = 'paid'                                    │
│                                                              │
│  6. CREATE PAYMENT ACCOUNT TRANSACTION                       │
│                                                              │
└──────────────────────────────────────────────────────────────┘
```

---

## Multi-Payment System

### Multi-Payment Flow

```
┌──────────────────────────────────────────────────────────────┐
│                MULTI-PAYMENT PROCESSING                       │
├──────────────────────────────────────────────────────────────┤
│                                                              │
│  STUDENT SUBMISSION:                                         │
│  1. Select multiple fees/installments                        │
│  2. Enter total payment amount                               │
│  3. Distribute amount (auto or manual):                      │
│     AUTO: Oldest due date first                              │
│     MANUAL: Student specifies per-fee amounts                │
│  4. Upload receipt                                           │
│  5. Submit (status = 'pending')                              │
│                                                              │
│  ADMIN VERIFICATION:                                         │
│  6. Review multi-payment details                             │
│  7. APPROVE:                                                 │
│     For each distribution:                                   │
│       • Update fee/installment paid_amount                   │
│       • Update fee/installment status                        │
│       • Create payment account transaction                   │
│     Set multi_payment.status = 'approved'                    │
│                                                              │
│  8. REJECT:                                                  │
│     Set multi_payment.status = 'rejected'                    │
│     No fee updates                                           │
│                                                              │
└──────────────────────────────────────────────────────────────┘
```

---

## Platform Fee System

### Platform Fee Access Control

```
┌──────────────────────────────────────────────────────────────┐
│           PLATFORM FEE MIDDLEWARE CHECK                       │
├──────────────────────────────────────────────────────────────┤
│                                                              │
│  1. IF not student authenticated → ALLOW                     │
│  2. IF platform fee disabled globally → ALLOW                │
│  3. IF no student enrollment found → ALLOW                   │
│  4. IF session is exempted → ALLOW                           │
│  5. IF student is exempted → ALLOW                           │
│  6. IF payment approved for current session → ALLOW          │
│  7. ELSE → REDIRECT to platform fee payment page             │
│                                                              │
└──────────────────────────────────────────────────────────────┘
```

### Platform Fee Configuration

```php
// Platform fee settings
[
    'title' => 'Platform Access Fee',
    'welcome_message' => 'Welcome text...',
    'fee_amount' => 50000,
    'is_enabled' => true,
    'payment_instructions' => 'Bank details...',
    'currency' => 'FRW',
]
```

---

## Payment Accounts

### Account Transaction Flow

```
┌──────────────────────────────────────────────────────────────┐
│            PAYMENT ACCOUNT TRANSACTION                        │
├──────────────────────────────────────────────────────────────┤
│                                                              │
│  ON FEE PAYMENT (with payment_account_id):                   │
│                                                              │
│  1. Get payment account                                      │
│  2. Calculate new balance:                                   │
│     new_balance = current_balance + payment_amount           │
│                                                              │
│  3. Create transaction record:                               │
│     • transaction_type = 'credit'                            │
│     • amount = payment_amount                                │
│     • reference_type = 'fees'                                │
│     • reference_id = fee.id                                  │
│     • balance_after = new_balance                            │
│                                                              │
│  4. Update account current_balance                           │
│                                                              │
└──────────────────────────────────────────────────────────────┘
```

---

## Routes & Controllers

### Admin Routes

```php
// Fee Configuration
Route::resource('fees-category', 'FeesCategoryController');
Route::resource('fees-master', 'FeesMasterController');
Route::resource('fees-discount', 'FeesDiscountController');
Route::resource('fees-fine', 'FeesFineController');
Route::resource('fees-receipt', 'ReceiptSettingController');
Route::resource('program-semester-fee', 'ProgramSemesterFeeController');

// Fee Collection
Route::get('fees-student', 'FeesStudentController@index');
Route::post('fees-student-pay', 'FeesStudentController@pay');
Route::post('fees-student-unpay/{id}', 'FeesStudentController@unpay');
Route::post('fees-student-cancel/{id}', 'FeesStudentController@cancel');
Route::get('fees-student-report', 'FeesStudentController@report');
Route::get('fees-student-print/{id}', 'FeesStudentController@print');
Route::get('fees-student-multiprint', 'FeesStudentController@multiPrint');
Route::get('fees-student-quick-received', 'FeesStudentController@quickReceived');
Route::post('fees-student-quick-received', 'FeesStudentController@quickReceivedStore');
Route::get('fees-student-quick-assign', 'FeesStudentController@quickAssign');
Route::post('fees-student-quick-assign', 'FeesStudentController@quickAssignStore');

// Student Credits
Route::get('student-credits', 'StudentCreditController@index');
Route::get('student-credits/{id}', 'StudentCreditController@show');
Route::post('student-credits/{id}/request-refund', 'StudentCreditController@requestRefund');
Route::post('student-credits/{id}/approve-refund', 'StudentCreditController@approveRefund');
Route::post('student-credits/{id}/reject-refund', 'StudentCreditController@rejectRefund');
Route::post('student-credits/{id}/process-refund', 'StudentCreditController@processRefund');
Route::post('student-credits/{id}/apply-to-fee', 'StudentCreditController@applyToFee');

// Payment Plans
Route::resource('payment-plan', 'PaymentPlanController');
Route::get('payment-plan/get-student-fees', 'PaymentPlanController@getStudentFees');
Route::post('payment-plan/process-payment', 'PaymentPlanController@processPayment');
Route::post('payment-plan/{id}/cancel', 'PaymentPlanController@cancel');

// Payment Verification
Route::get('payment-verification', 'PaymentVerificationController@index');
Route::get('payment-verification/{id}', 'PaymentVerificationController@show');
Route::post('payment-verification/{id}/approve', 'PaymentVerificationController@approve');
Route::post('payment-verification/{id}/reject', 'PaymentVerificationController@reject');
Route::get('payment-verification/multi/{id}', 'PaymentVerificationController@showMulti');
Route::post('payment-verification/multi/{id}/approve', 'PaymentVerificationController@approveMulti');
Route::post('payment-verification/multi/{id}/reject', 'PaymentVerificationController@rejectMulti');

// Installment Payment Verification
Route::get('installment-payment-verification', 'InstallmentPaymentVerificationController@index');
Route::post('installment-payment-verification/{id}/approve', 'InstallmentPaymentVerificationController@approve');
Route::post('installment-payment-verification/{id}/reject', 'InstallmentPaymentVerificationController@reject');

// Platform Fee (Admin)
Route::prefix('platform-fee')->name('platform-fee.')->group(function () {
    Route::get('settings', 'PlatformFeeController@settings');
    Route::post('settings', 'PlatformFeeController@updateSettings');
    Route::get('verifications', 'PlatformFeeController@verifications');
    Route::get('verifications/data', 'PlatformFeeController@getPaymentsData');
    Route::post('verifications/{id}/approve', 'PlatformFeeController@approvePayment');
    Route::post('verifications/{id}/reject', 'PlatformFeeController@rejectPayment');
    Route::get('statistics', 'PlatformFeeController@statistics');
    Route::get('exemptions', 'PlatformFeeController@exemptions');
    Route::post('exemptions', 'PlatformFeeController@storeExemption');
    Route::delete('exemptions/{id}', 'PlatformFeeController@deleteExemption');
    Route::get('pending-count', 'PlatformFeeController@getPendingCount');
});

// Payment Accounts
Route::get('payment-account', 'PaymentAccountController@index');
Route::get('payment-account/create', 'PaymentAccountController@create');
Route::post('payment-account/store', 'PaymentAccountController@store');
Route::get('payment-account/{id}/edit', 'PaymentAccountController@edit');
Route::put('payment-account/{id}/update', 'PaymentAccountController@update');
Route::delete('payment-account/{id}/delete', 'PaymentAccountController@destroy');
Route::get('payment-account/{id}/account-book', 'PaymentAccountController@accountBook');

// Fee Reports
Route::get('report/fees', 'ReportController@fees');
Route::get('report/student-fees', 'ReportController@studentFees');
```

### Student Routes

```php
// Fees
Route::get('fees', 'FeesController@index');
Route::get('fees/pay/{id}', 'FeesController@pay');
Route::get('fees/print/{id}', 'FeesController@print');

// Payment Plan
Route::get('payment-plan', 'PaymentPlanController@index');
Route::get('payment-plan/{id}', 'PaymentPlanController@show');

// Installment Payment
Route::get('installment-payment/create/{installment_id}', 'InstallmentPaymentController@create');
Route::post('installment-payment/store/{installment_id}', 'InstallmentPaymentController@store');
Route::get('installment-payment/{id}', 'InstallmentPaymentController@show');

// Manual Payment (Receipt Upload)
Route::get('manual-payment', 'ManualPaymentController@index');
Route::get('manual-payment/create/{fee_id}', 'ManualPaymentController@create');
Route::post('manual-payment/store', 'ManualPaymentController@store');
Route::get('manual-payment/{id}', 'ManualPaymentController@show');

// Multi-Payment
Route::get('multi-payment', 'MultiPaymentController@index');
Route::post('multi-payment/store', 'MultiPaymentController@store');
Route::get('multi-payment/{id}', 'MultiPaymentController@show');

// Platform Fee
Route::get('platform-fee/payment', 'PlatformFeePaymentController@index');
Route::post('platform-fee/payment/upload', 'PlatformFeePaymentController@uploadReceipt');
```

---

## Status Codes & Constants

### Fee Status

```php
const FEE_STATUS_UNPAID = 0;
const FEE_STATUS_PAID = 1;
const FEE_STATUS_PARTIALLY_PAID = 2;
const FEE_STATUS_CANCELLED = 3;
```

### Payment Receipt Verification Status

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

### Payment Methods

```php
const PAYMENT_CARD = 1;
const PAYMENT_CASH = 2;
const PAYMENT_CHEQUE = 3;
const PAYMENT_BANK_TRANSFER = 4;
const PAYMENT_EWALLET = 5;
const PAYMENT_MANUAL = 6;
```

### Discount/Fine Types

```php
const TYPE_FIXED = 1;
const TYPE_PERCENTAGE = 2;
```

### Platform Fee Exemption Types

```php
const EXEMPTION_STUDENT = 'student';
const EXEMPTION_SESSION = 'session';
```

---

## Integration Points

### With Student Enrollment

- Fees are linked to `student_enrolls` table
- Each fee belongs to one enrollment
- Student can have multiple enrollments (different programs)

### With Admission Process

- Admission fees (`is_admission = 1` category)
- Can be configured via `AdmissionFeeConfigController`
- Assigned during application acceptance

### With Resit/Retake Process

- Resit fees (`is_resit = 1` category)
- Linked to `resit_requests` table
- Fee created when resit request approved

### With Semester Progression

- Fees can be auto-assigned on semester progression
- Based on `program_semester_fees` configuration
- Auto-credit application on new fee assignment

### With Accounting System

- Fee payments can create journal entries
- Integration via `AccountMappingController`
- Maps fee transactions to chart of accounts

---

## API Considerations

### Suggested API Endpoints

```
GET    /api/fees                        # List student fees
GET    /api/fees/{id}                   # Fee details
POST   /api/fees/{id}/pay               # Process payment
GET    /api/payment-plans               # List payment plans
GET    /api/payment-plans/{id}          # Plan details
POST   /api/payment-plans/{id}/pay      # Pay installment
POST   /api/payments/receipt            # Upload payment receipt
GET    /api/credits                     # List student credits
POST   /api/credits/{id}/apply          # Apply credit to fee
GET    /api/platform-fee/status         # Check platform fee status
POST   /api/platform-fee/pay            # Submit platform fee payment
```

### Authentication Considerations

- Use Laravel Sanctum or Passport for API auth
- Scope tokens for student vs admin access
- Rate limiting on payment endpoints

### Webhook Suggestions

```
fee.assigned          # When fee is assigned to student
fee.paid              # When fee is fully paid
fee.partial           # When partial payment made
payment.pending       # When receipt uploaded
payment.approved      # When receipt approved
payment.rejected      # When receipt rejected
credit.created        # When credit/overpayment created
credit.applied        # When credit applied to fee
plan.created          # When payment plan created
plan.completed        # When plan fully paid
installment.due       # When installment due date approaching
installment.overdue   # When installment becomes overdue
```

---

## Files Summary

### Models (app/Models/)
- `Fee.php` - Core fee model
- `FeesCategory.php` - Fee category definitions
- `FeesMaster.php` - Bulk fee templates
- `FeesDiscount.php` - Discount rules
- `FeesFine.php` - Late fee rules
- `ProgramSemesterFee.php` - Program-based fee config
- `ProgramSemesterFeeBreakdown.php` - Fee breakdowns
- `PaymentReceipt.php` - Payment receipts
- `PaymentPlan.php` - Installment plans
- `PaymentPlanInstallment.php` - Installment schedule
- `PaymentPlanPayment.php` - Installment payments
- `InstallmentPaymentReceipt.php` - Installment receipts
- `MultiPayment.php` - Bulk payments
- `MultiPaymentDistribution.php` - Payment distribution
- `StudentCredit.php` - Student credits/overpayments
- `CreditApplication.php` - Credit usage tracking
- `PlatformFeeSetting.php` - Platform fee config
- `PlatformFeePayment.php` - Platform fee payments
- `PlatformFeeExemption.php` - Fee exemptions
- `PaymentAccount.php` - Payment accounts
- `PaymentAccountType.php` - Account types
- `PaymentAccountTransaction.php` - Account transactions

### Controllers (app/Http/Controllers/Admin/)
- `FeesCategoryController.php`
- `FeesMasterController.php`
- `FeesStudentController.php`
- `FeesDiscountController.php`
- `FeesFineController.php`
- `ProgramSemesterFeeController.php`
- `PaymentPlanController.php`
- `PaymentVerificationController.php`
- `InstallmentPaymentVerificationController.php`
- `PlatformFeeController.php`
- `StudentCreditController.php`
- `PaymentAccountController.php`

### Controllers (app/Http/Controllers/Student/)
- `FeesController.php`
- `ManualPaymentController.php`
- `MultiPaymentController.php`
- `PaymentPlanController.php`
- `InstallmentPaymentController.php`
- `PlatformFeePaymentController.php`

### Services (app/Services/)
- `StudentCreditService.php` - Credit management

### Migrations (database/migrations/)
- See Database Schema section for complete list

---

## Version Information

- **Laravel Version**: 8.x / 9.x compatible
- **PHP Version**: 7.4+ / 8.0+
- **Database**: MySQL 5.7+ / MariaDB 10.3+

---

*Document Generated: February 2026*
*System Version: Academic Management System v2.x*
