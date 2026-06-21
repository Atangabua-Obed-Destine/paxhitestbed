# Program Semester Fees Configuration - Quick Reference

## Quick Access
**Menu:** Admin Panel → Fees Collection → Program Semester Fees  
**URL:** `/admin/program-semester-fee`

## Overview
Configure fee amounts per program per semester. When students progress to a new regular semester, configured fees are automatically assigned to their account.

---

## Business Rules

### ✅ What CAN Be Configured
- ✓ Regular semesters only (is_resit = 0)
- ✓ Semesters with enrolled courses
- ✓ First Installment fees
- ✓ Second Installment fees
- ✓ Any active program

### ❌ What CANNOT Be Configured
- ✗ Resit semesters
- ✗ Semesters without enrolled courses
- ✗ Non-installment fee categories
- ✗ Resit fee categories

---

## How To Use

### 1. Create Fee Configuration

1. Go to **Fees Collection → Program Semester Fees**
2. Click **"Add"** button
3. **Select Faculty** → Programs will load
4. **Select Program** → Semesters with courses will load
5. **Select Semester** → Only regular semesters shown
6. Click **"Load Fee Categories"** → Only installment categories shown
7. **Check categories** you want to configure
8. **Enter amounts** for each selected category
9. Click **"Save"**

**Result:** Multiple fee configurations created (one per category)

### 2. View Configurations

**Filter by:**
- Faculty
- Program  
- Semester

**Table shows:**
- Faculty name
- Program title
- Semester (with year)
- Fee category (with installment badge)
- Amount
- Status (Active/Inactive)

### 3. Edit Configuration

1. Find configuration in list
2. Click **Edit** button
3. Modify **amount** or **status**
4. Click **"Update"**

**Note:** Cannot change program, semester, or category. Delete and recreate if needed.

### 4. Delete Configuration

1. Find configuration in list
2. Click **Delete** button
3. Confirm deletion

---

## Auto-Assignment Logic

### When Does It Happen?
When a student **progresses to a new regular semester** via:
- Automatic semester progression
- Manual semester progression
- Program transfer (if configured)

### What Gets Assigned?
For each **active** configuration matching:
- Student's program
- New semester

System creates a **Fee record** with:
- Category from configuration
- Amount from configuration
- Assign date = progression date
- Due date = assign date + 30 days (configurable)

### What If No Configuration Exists?
- Student progresses normally
- No fees auto-assigned
- Admin can manually assign fees later

---

## Example Scenarios

### Scenario 1: Basic Setup
**Program:** BSc Computer Science  
**Semester:** Year 1 Semester 1  

**Configurations:**
- First Installment: 150,000 XAF
- Second Installment: 150,000 XAF

**Result:** Every new student in Y1S1 automatically gets both installment fees totaling 300,000 XAF

### Scenario 2: Different Amounts Per Semester
**Program:** BSc Computer Science  

| Semester | First Installment | Second Installment |
|----------|-------------------|-------------------|
| Y1S1 | 150,000 XAF | 150,000 XAF |
| Y1S2 | 140,000 XAF | 140,000 XAF |
| Y2S1 | 160,000 XAF | 160,000 XAF |
| Y2S2 | 160,000 XAF | 160,000 XAF |

**Result:** Fees adjust automatically as student progresses through semesters

### Scenario 3: Program-Specific Fees
**Same Semester, Different Programs:**

| Program | First Installment |
|---------|-------------------|
| BSc Computer Science | 150,000 XAF |
| BA Economics | 120,000 XAF |
| Engineering | 200,000 XAF |

**Result:** Each program has different fee amounts for same semester

---

## Validation Rules

### Creating Configuration
| Field | Rule |
|-------|------|
| Faculty | Required, must exist |
| Program | Required, must exist, must belong to faculty |
| Semester | Required, must exist, must be regular (is_resit=0), must have enrolled courses |
| Fee Category | Required, at least 1, must be installment type |
| Amount | Required per category, numeric, ≥ 0 |

### Editing Configuration
| Field | Rule |
|-------|------|
| Amount | Required, numeric, ≥ 0 |
| Status | Required, boolean (Active/Inactive) |

### Uniqueness
**One configuration per:** Program + Semester + Fee Category  
System prevents duplicates via database unique constraint

---

## Integration Points

### 1. SemesterProgressionService
**File:** `app/Services/SemesterProgressionService.php`  
**Method:** `progressToNextSemester()`

**Integration Code:**
```php
// After creating new enrollment
$configuredFees = ProgramSemesterFee::where('program_id', $newEnrollment->program_id)
    ->where('semester_id', $newEnrollment->semester_id)
    ->where('status', 1)
    ->get();

foreach ($configuredFees as $feeConfig) {
    Fee::create([
        'student_enroll_id' => $newEnrollment->id,
        'fees_category_id' => $feeConfig->fees_category_id,
        'amount' => $feeConfig->amount,
        'assign_date' => now(),
        'due_date' => now()->addDays(30),
        'note' => 'Auto-assigned from program semester fee configuration',
        'status' => 1,
    ]);
}
```

### 2. Manual Progression
Any custom progression logic should check for configurations and auto-assign

### 3. Reports
Fee assignment reports can reference configuration source

---

## Database Schema

### Table: `program_semester_fees`
```sql
id                  BIGINT UNSIGNED PRIMARY KEY
program_id          INT UNSIGNED (FK: programs.id)
semester_id         INT UNSIGNED (FK: semesters.id)
fees_category_id    INT UNSIGNED (FK: fees_categories.id)
amount             DECIMAL(10,2)
status             TINYINT (1=Active, 0=Inactive)
created_at         TIMESTAMP
updated_at         TIMESTAMP
created_by         BIGINT UNSIGNED (FK: users.id)
updated_by         BIGINT UNSIGNED (FK: users.id)

UNIQUE KEY (program_id, semester_id, fees_category_id)
```

---

## Permissions

| Permission | Access Level |
|-----------|-------------|
| `program-semester-fee-view` | View configurations |
| `program-semester-fee-create` | Create new configurations |
| `program-semester-fee-edit` | Edit existing configurations |
| `program-semester-fee-delete` | Delete configurations |

**Assigned to:** Admin, Accountant

---

## Troubleshooting

### Problem: No semesters appear when selecting program
**Cause:** No courses enrolled for that program in any semester  
**Solution:** Go to Enroll Subject and enroll courses first

### Problem: No fee categories appear when clicking "Load Fee Categories"
**Cause:** No installment fee categories exist  
**Solution:** Go to Fees Category and create/mark categories as installment type

### Problem: Duplicate error when saving
**Cause:** Configuration already exists for this combination  
**Solution:** Find existing configuration and edit it instead

### Problem: Fees not auto-assigned when student progresses
**Cause 1:** Configuration status is Inactive  
**Solution:** Edit configuration and set status to Active

**Cause 2:** Integration not implemented yet  
**Solution:** Implement auto-assignment in SemesterProgressionService

### Problem: Permission denied
**Cause:** User role doesn't have required permissions  
**Solution:** Run `php assign_program_semester_fee_permissions.php` or assign manually

---

## API Endpoints (for developers)

| Method | URL | Purpose |
|--------|-----|---------|
| GET | `/admin/program-semester-fee` | List configurations |
| GET | `/admin/program-semester-fee/create` | Show create form |
| POST | `/admin/program-semester-fee` | Store new configurations |
| GET | `/admin/program-semester-fee/{id}/edit` | Show edit form |
| PUT | `/admin/program-semester-fee/{id}` | Update configuration |
| DELETE | `/admin/program-semester-fee/{id}` | Delete configuration |
| GET | `/admin/program-semester-fee/get-programs?faculty_id={id}` | AJAX: Get programs by faculty |
| GET | `/admin/program-semester-fee/get-semesters?program_id={id}` | AJAX: Get semesters by program |
| GET | `/admin/program-semester-fee/get-fee-categories` | AJAX: Get eligible categories |

---

## Files Reference

| Type | Path |
|------|------|
| Controller | `app/Http/Controllers/Admin/ProgramSemesterFeeController.php` |
| Model | `app/Models/ProgramSemesterFee.php` |
| Migration | `database/migrations/2025_11_23_073453_create_program_semester_fees_table.php` |
| Views | `resources/views/admin/program-semester-fee/` |
| Routes | `routes/web.php` (lines ~350) |
| Menu | `resources/views/admin/layouts/inc/sidebar.blade.php` (line ~412) |

---

## Testing Checklist

- [ ] Create configuration for program/semester
- [ ] Verify only regular semesters shown
- [ ] Verify only installment categories shown
- [ ] Edit existing configuration
- [ ] Toggle status Active/Inactive
- [ ] Delete configuration
- [ ] Test filters (faculty, program, semester)
- [ ] Verify duplicate prevention
- [ ] Test multi-category creation
- [ ] Verify permissions work
- [ ] Test auto-assignment (after integration)

---

## Support

For issues or questions:
1. Check troubleshooting section above
2. Review implementation documentation: `PROGRAM_SEMESTER_FEES_IMPLEMENTATION.md`
3. Check audit logs for configuration changes
4. Contact system administrator

---

**Last Updated:** {{ date('Y-m-d') }}  
**Version:** 1.0  
**Status:** ✓ Ready for Use (Auto-assignment pending integration)
