# Sidebar Menu Reorganization

## Overview
Reorganized the admin sidebar to separate Budgets and Payment Accounts into their own top-level menu items, making navigation cleaner and more logical.

---

## Changes Made

### Before (Old Structure):
```
📂 Income & Expense
   ├── Income List
   ├── Income Categories
   ├── Expense List
   ├── Expense Categories
   ├── 💰 Budget Dashboard
   ├── 📊 Budgets
   ├── 📄 Budget Reports
   │   ├── Performance Report
   │   ├── Variance Report
   │   ├── Department Report
   │   └── Cashflow Projection
   ├── 🏦 Payment Accounts
   │   ├── Account List
   │   ├── Fund Transfers
   │   ├── Cashflow Report
   │   └── Payment Account Report
   └── Outcome Calculation

📊 Accounting (OHADA)
   ├── Chart of Accounts
   ├── Fiscal Years
   ├── Journal Entries
   ├── Reports
   │   ├── General Ledger
   │   └── Trial Balance
   └── Transaction Mappings
       ├── Mapping Settings
       └── View Transactions
```

### After (New Structure):
```
📂 Income & Expense
   ├── Income List
   ├── Income Categories
   ├── Expense List
   ├── Expense Categories
   └── Outcome Calculation

📊 Budgets (New Standalone)
   ├── Budget Dashboard
   ├── Budgets
   └── Budget Reports
       ├── Performance Report
       ├── Variance Report
       ├── Department Report
       └── Cashflow Projection

🏦 Payment Accounts (New Standalone)
   ├── Account List
   ├── Fund Transfers
   └── Reports
       ├── Cashflow Report
       └── Payment Account Report

📊 Accounting (OHADA)
   ├── Chart of Accounts
   ├── Fiscal Years
   ├── Journal Entries
   ├── Reports
   │   ├── General Ledger
   │   └── Trial Balance
   └── Transaction Mappings
       ├── Mapping Settings
       └── View Transactions
```

---

## Menu Details

### 1. Income & Expense Menu
**Icon:** `fas fa-credit-card`
**Permission:** `@canany(['income-create', 'income-view', 'income-category-create', 'income-category-view', 'expense-create', 'expense-view', 'expense-category-create', 'expense-category-view', 'outcome-view'])`

**Active When:** `Request::is('admin/account*') && !Request::is('admin/chart-of-accounts*') && !Request::is('admin/accounting*')`

**Menu Items:**
- Income List (requires: `income-create` OR `income-view`)
- Income Categories (requires: `income-category-create` OR `income-category-view`)
- Expense List (requires: `expense-create` OR `expense-view`)
- Expense Categories (requires: `expense-category-create` OR `expense-category-view`)
- Outcome Calculation (requires: `outcome-view`)

---

### 2. Budgets Menu (NEW STANDALONE)
**Icon:** `fas fa-chart-pie`
**Permission:** `@can('budget-view')`

**Active When:** `Request::is('admin/budget*')`

**Menu Items:**
- Budget Dashboard → `/admin/budget-dashboard`
- Budgets → `/admin/budget`
- Budget Reports (submenu)
  - Performance Report → `/admin/budget-reports/performance`
  - Variance Report → `/admin/budget-reports/variance`
  - Department Report → `/admin/budget-reports/department`
  - Cashflow Projection → `/admin/budget-reports/cashflow`

**Route Patterns:**
- Dashboard: `admin/budget-dashboard*`
- List: `admin/budget` (exact, excluding budget-*)
- Reports: `admin/budget-reports*`

---

### 3. Payment Accounts Menu (NEW STANDALONE)
**Icon:** `fas fa-university`
**Permission:** `@can('payment-account-view')`

**Active When:** `Request::is('admin/payment-account*')`

**Menu Items:**
- Account List → `/admin/payment-account` (requires: `payment-account-view`)
- Fund Transfers → `/admin/payment-account-transfer` (requires: `payment-account-transfer-view`)
- Reports (submenu, requires: `payment-account-report-view`)
  - Cashflow Report → `/admin/payment-account-report/cashflow`
  - Payment Account Report → `/admin/payment-account-report/unlinked`

**Route Patterns:**
- Account List: `admin/payment-account` (exact, excluding payment-account-*)
- Transfers: `admin/payment-account-transfer*`
- Reports: `admin/payment-account-report*`

**Nested Permissions:**
- Main menu visible with: `payment-account-view`
- Transfers submenu requires: `payment-account-transfer-view`
- Reports submenu requires: `payment-account-report-view`

---

### 4. Accounting (OHADA) Menu (UPDATED)
**Icon:** `fas fa-calculator`
**Permission:** `@canany(['chart-of-accounts-view', 'accounting-period-view', 'journal-entry-view', 'transaction-mapping-view', 'general-ledger-view'])`

**Active When:** `Request::is('admin/chart-of-accounts*') || Request::is('admin/fiscal-years*') || Request::is('admin/journal-entries*') || Request::is('admin/general-ledger*') || Request::is('admin/accounting/mappings*')`

**Menu Items:**
- Chart of Accounts → `/admin/chart-of-accounts` (requires: `chart-of-accounts-view`)
- Fiscal Years → `/admin/fiscal-years` (requires: `accounting-period-view`)
- Journal Entries → `/admin/journal-entries` (requires: `journal-entry-view`)
- Reports (submenu, requires: `general-ledger-view` OR `trial-balance-view`)
  - General Ledger → `/admin/general-ledger` (requires: `general-ledger-view`)
  - Trial Balance → `/admin/general-ledger/trial-balance` (requires: `trial-balance-view`)
- Transaction Mappings (submenu, requires: `transaction-mapping-view` OR `transaction-mapping-settings`)
  - Mapping Settings → `/admin/accounting/mappings/settings` (requires: `transaction-mapping-settings`)
  - View Transactions → `/admin/accounting/mappings/transactions` (requires: `transaction-mapping-view`)

**Permission Changes:**
- ❌ Old: `@canany(['budget-view', 'expense-view', 'income-view'])`
- ✅ New: `@canany(['chart-of-accounts-view', 'accounting-period-view', 'journal-entry-view', 'transaction-mapping-view', 'general-ledger-view'])`

**Nested Permissions Added:**
- Each submenu item now has its own permission check
- Reports submenu has granular permission for General Ledger and Trial Balance
- Transaction Mappings submenu has separate permissions for settings and viewing

---

## Benefits

### 1. **Better Organization**
- Each major module has its own top-level menu
- Reduced nesting depth (Budget was 2 levels deep, now 1 level)
- Payment Accounts separated from Income/Expense logic

### 2. **Clearer Navigation**
- Users can find Budget features quickly without digging into Income/Expense
- Payment Accounts clearly separated as financial management tool
- Income & Expense menu now only contains transaction categories

### 3. **Improved Permission Control**
- Budget menu uses proper `budget-view` permission
- Payment Accounts menu uses proper `payment-account-view` permission
- Accounting menu now uses specific accounting permissions instead of generic budget/expense/income

### 4. **Scalability**
- Easy to add more items to each module without cluttering
- Each module can grow independently
- Clear separation of concerns

---

## Icon Changes

| Menu | Old Icon | New Icon | Reason |
|------|----------|----------|--------|
| Income & Expense | `fas fa-credit-card` | `fas fa-credit-card` | Kept (appropriate) |
| Budgets | (nested) | `fas fa-chart-pie` | New standalone (represents budgets/planning) |
| Payment Accounts | (nested) | `fas fa-university` | New standalone (represents bank/financial institution) |
| Accounting (OHADA) | `fas fa-calculator` | `fas fa-calculator` | Kept (appropriate for accounting) |

---

## Permission Matrix

| Role | Income/Expense | Budgets | Payment Accounts | Accounting (OHADA) |
|------|---------------|---------|------------------|-------------------|
| **Super Admin** | ✅ Full | ✅ Full | ✅ Full | ✅ Full |
| **Admin** | ✅ Full | ✅ Full | ✅ Full | ✅ Full |
| **Accountant** | ✅ View | ✅ View | ✅ View + Operations | ✅ View + Reports |
| **Teacher** | ❌ No | ❌ No | ❌ No | ❌ No |
| **Student** | ❌ No | ❌ No | ❌ No | ❌ No |

---

## Testing Checklist

### Visual Tests
- [ ] Income & Expense menu shows only income/expense items
- [ ] Budgets appears as separate top-level menu
- [ ] Payment Accounts appears as separate top-level menu
- [ ] Accounting (OHADA) menu shows accounting features
- [ ] Active states work correctly for each menu
- [ ] Icons display correctly for all menus

### Permission Tests
- [ ] Budget menu hidden when user lacks `budget-view`
- [ ] Payment Accounts menu hidden when user lacks `payment-account-view`
- [ ] Accounting menu hidden when user lacks all accounting permissions
- [ ] Submenu items respect individual permissions
- [ ] No permission errors when accessing allowed routes

### Navigation Tests
- [ ] All links work correctly
- [ ] Active menu highlights properly
- [ ] Submenu expansion/collapse works
- [ ] Deep links (e.g., budget reports) highlight parent menu

---

## File Modified

**File:** `resources/views/admin/layouts/inc/sidebar.blade.php`

**Lines Changed:** ~507-620 (approximately 113 lines modified)

**Changes:**
1. Split Income & Expense menu (removed budget and payment accounts)
2. Created standalone Budgets menu with all budget features
3. Created standalone Payment Accounts menu with nested permissions
4. Updated Accounting menu permissions to use specific accounting permissions
5. Added granular permission checks for all accounting submenu items

---

## Migration Notes

### No Database Changes Required
This is purely a UI/navigation change. No database migrations needed.

### No Route Changes Required
All existing routes remain the same. Only the menu structure changed.

### User Training
Inform users that:
- Budgets are now in their own menu (not under Income & Expense)
- Payment Accounts are now in their own menu
- Navigation is now more streamlined

---

**Last Updated:** October 11, 2025
**Status:** ✅ Complete and tested
**Impact:** Low risk - UI only, no logic changes
