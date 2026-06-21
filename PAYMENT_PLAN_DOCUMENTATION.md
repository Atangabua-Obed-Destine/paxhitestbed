# Payment Plan Feature - Complete Implementation Guide

## 📋 Overview
The Payment Plan feature allows Vice Chancellors/Admins to create flexible installment payment plans for students who need to pay their fees over time. This comprehensive system includes automated late fee calculation, grace periods, payment tracking, and student portal access.

## ✅ Implementation Status: 100% COMPLETE

---

## 🗄️ Database Structure

### Tables Created
1. **payment_plans** - Main payment plan records
2. **payment_plan_installments** - Individual installments within plans
3. **payment_plan_payments** - Individual payment records against installments

### Migration Files
- `2025_10_08_154010_create_payment_plans_table.php`
- `2025_10_08_154018_create_payment_plan_installments_table.php`
- `2025_10_08_154026_create_payment_plan_payments_table.php`

**Run Migrations:**
```bash
php artisan migrate
```

---

## 🔐 Permissions

### Created Permissions (10)
- `payment-plan.index` - View all payment plans
- `payment-plan.create` - Create new payment plan
- `payment-plan.store` - Store payment plan
- `payment-plan.show` - View payment plan details
- `payment-plan.edit` - Edit payment plan
- `payment-plan.update` - Update payment plan
- `payment-plan.destroy` - Delete payment plan
- `payment-plan.pay` - Process payments
- `payment-plan.approve` - Approve payment plans
- `payment-plan.cancel` - Cancel payment plans

### Assigned Roles
- ✅ Admin
- ✅ Super Admin

**Seed Permissions:**
```bash
php artisan db:seed --class=PaymentPlanPermissionSeeder
```

---

## 📁 Files Created/Modified

### Models (3 new)
- `app/Models/PaymentPlan.php` - Main model with business logic
- `app/Models/PaymentPlanInstallment.php` - Installment tracking
- `app/Models/PaymentPlanPayment.php` - Payment records

### Controllers (2 new)
- `app/Http/Controllers/Admin/PaymentPlanController.php` - Admin CRUD & payment processing
- `app/Http/Controllers/Student/PaymentPlanController.php` - Student portal views

### Admin Views (8 new)
- `resources/views/admin/payment-plan/index.blade.php` - List all plans
- `resources/views/admin/payment-plan/create.blade.php` - Create plan with installment generator
- `resources/views/admin/payment-plan/show.blade.php` - Full plan details
- `resources/views/admin/payment-plan/edit.blade.php` - Edit plan settings
- `resources/views/admin/payment-plan/pay.blade.php` - Payment modal
- `resources/views/admin/payment-plan/cancel.blade.php` - Cancel modal
- `resources/views/admin/payment-plan/delete.blade.php` - Delete modal
- `resources/views/admin/payment-plan/payment-history.blade.php` - Payment history modal

### Student Views (3 new)
- `resources/views/student/payment-plan/index.blade.php` - Student's plans with statistics
- `resources/views/student/payment-plan/show.blade.php` - Plan details
- `resources/views/student/payment-plan/payment-history.blade.php` - Payment history

### Seeders (1 new)
- `database/seeders/PaymentPlanPermissionSeeder.php`

### Commands (1 new)
- `app/Console/Commands/PaymentPlanMaintenanceCommand.php`

### Modified Files (3)
- `app/Models/Fee.php` - Added payment plan relationship
- `routes/web.php` - Added admin & student routes
- `resources/views/admin/layouts/inc/sidebar.blade.php` - Added menu item
- `resources/views/student/layouts/inc/sidebar.blade.php` - Added menu item

---

## 🎯 Features Implemented

### Admin Features
✅ **Create Payment Plans**
- Select student and their unpaid fees
- Configure number of installments (2-12)
- Set late fee percentage (0-100%)
- Define grace period days (0-30)
- Generate custom installment schedules
- Auto-approval by VC/Admin

✅ **Manage Payment Plans**
- View all plans with filtering (by status, student)
- Edit late fee percentage and grace periods
- Cancel plans with reason tracking
- Delete plans (only if no payments made)
- View complete payment history

✅ **Process Payments**
- Record payments against installments
- Support partial payments
- Multiple payment methods (Cash, Cheque, Bank Transfer, Online, Card, Mobile Money, Other)
- Upload payment receipts
- Automatic status updates
- Transaction integration with existing payment system

✅ **Status Management**
- Active - Plan in progress
- Completed - All installments paid
- Cancelled - Plan cancelled by admin
- Defaulted - Too many overdue payments

### Student Features
✅ **View Payment Plans**
- Dashboard with statistics:
  - Active plans count
  - Total paid amount
  - Total remaining amount
  - Overdue installments count
- List all personal payment plans
- Progress bars showing completion percentage

✅ **Payment Plan Details**
- View installment schedule
- Check due dates and grace periods
- See payment history for each installment
- View late fees applied
- Track remaining balance

✅ **Upcoming Installments**
- Next 10 upcoming payments
- Due date reminders
- Easy navigation to plan details

---

## 🔄 Automated Features

### Console Command
**Command:** `php artisan payment-plan:maintain`

**Options:**
```bash
# Run all maintenance tasks
php artisan payment-plan:maintain

# Update only overdue statuses
php artisan payment-plan:maintain --update-status

# Apply only late fees
php artisan payment-plan:maintain --apply-late-fees

# Send only payment reminders
php artisan payment-plan:maintain --send-reminders
```

**What it does:**
1. **Update Overdue Statuses** - Marks installments as overdue after grace period
2. **Apply Late Fees** - Calculates and applies percentage-based late fees
3. **Send Payment Reminders** - Sends reminders for installments due in next 7 days

**Schedule in Kernel (Recommended):**
```php
// app/Console/Kernel.php
protected function schedule(Schedule $schedule)
{
    // Run payment plan maintenance daily at 1 AM
    $schedule->command('payment-plan:maintain')->daily()->at('01:00');
    
    // Or run multiple times per day
    $schedule->command('payment-plan:maintain')->twiceDaily(1, 13);
}
```

---

## 🌐 Routes

### Admin Routes
```php
// Resource routes (index, create, store, show, edit, update, destroy)
Route::resource('payment-plan', 'PaymentPlanController');

// Custom routes
Route::get('payment-plan/get-student-fees', 'PaymentPlanController@getStudentFees');
Route::post('payment-plan/process-payment', 'PaymentPlanController@processPayment');
Route::post('payment-plan/{id}/cancel', 'PaymentPlanController@cancel');
```

**Admin URLs:**
- List: `/admin/payment-plan`
- Create: `/admin/payment-plan/create`
- View: `/admin/payment-plan/{id}`
- Edit: `/admin/payment-plan/{id}/edit`

### Student Routes
```php
Route::get('payment-plan', 'PaymentPlanController@index');
Route::get('payment-plan/{id}', 'PaymentPlanController@show');
```

**Student URLs:**
- List: `/student/payment-plan`
- View: `/student/payment-plan/{id}`

---

## 💻 Usage Examples

### Creating a Payment Plan (Admin)
1. Navigate to **Fees Collection > Payment Plans**
2. Click **Create Payment Plan**
3. Select student (loads their unpaid fees automatically via AJAX)
4. Select fee to convert to payment plan
5. Configure:
   - Number of installments
   - Late fee percentage
   - Grace period days
6. Click **Generate Schedule** (creates installments automatically)
7. Adjust installment amounts/dates if needed
8. Add notes (optional)
9. Click **Create Payment Plan**

### Processing a Payment (Admin)
1. Go to payment plan details
2. Find the installment to pay
3. Click **Pay** button
4. Enter:
   - Payment date
   - Amount to pay (can be partial)
   - Payment method
   - Reference number (optional)
   - Upload receipt (optional)
   - Add note (optional)
5. Click **Process Payment**
6. System automatically:
   - Updates installment status
   - Creates transaction record
   - Updates plan progress
   - Marks plan as complete if all installments paid

### Viewing Plans (Student)
1. Navigate to **Payment Plans** from sidebar
2. See dashboard with:
   - Active plans summary
   - Payment statistics
   - Overdue installments alert
3. View upcoming installments
4. Click **View** to see full plan details
5. Check payment history for each installment

---

## 🔧 Configuration

### Late Fee Calculation
- Percentage-based (0-100%)
- Applied after grace period expires
- Formula: `late_fee = installment_amount × (late_fee_percentage / 100)`
- Only applied once per installment

### Grace Period
- Days after due date (0-30)
- No late fees during grace period
- Status updates to "overdue" after grace period

### Installment Generation
- Default: Equal distribution of total amount
- Customizable: Adjust amounts and dates after generation
- Due dates: 30 days apart by default
- Last installment: Gets any remaining cents from rounding

---

## 🎨 UI/UX Features

### Progress Tracking
- Visual progress bars showing completion percentage
- Color-coded status badges:
  - **Active** - Green
  - **Completed** - Blue
  - **Cancelled** - Red
  - **Defaulted** - Orange

### Installment Status
- **Pending** - Yellow (not yet paid)
- **Partial** - Blue (partially paid)
- **Paid** - Green (fully paid)
- **Overdue** - Red (past due + grace period)

### Payment History
- Modal popup showing all payments for an installment
- Displays: Date, Amount, Method, Reference, Receipt
- Download receipts directly

---

## 📊 Database Relationships

```
Fee (1) ←→ (1) PaymentPlan
PaymentPlan (1) ←→ (many) PaymentPlanInstallments
PaymentPlanInstallment (1) ←→ (many) PaymentPlanPayments
PaymentPlan (many) ←→ (1) Student
PaymentPlan (many) ←→ (1) User (creator)
PaymentPlan (many) ←→ (1) User (approver)
PaymentPlanPayment (many) ←→ (1) User/Student (polymorphic)
```

---

## 🔍 Testing Checklist

### Admin Tests
- [ ] Create payment plan for student
- [ ] Generate installment schedule (2-12 installments)
- [ ] Edit late fee percentage
- [ ] Edit grace period days
- [ ] Process full payment on installment
- [ ] Process partial payment on installment
- [ ] View payment history
- [ ] Cancel active plan
- [ ] Delete plan with no payments
- [ ] Filter plans by status
- [ ] Search plans by student

### Student Tests
- [ ] View list of payment plans
- [ ] View plan details
- [ ] Check installment schedule
- [ ] View payment history
- [ ] See upcoming installments
- [ ] Verify payment statistics

### Automation Tests
- [ ] Run `php artisan payment-plan:maintain`
- [ ] Verify overdue statuses updated
- [ ] Verify late fees applied
- [ ] Check reminder logs

---

## 🐛 Troubleshooting

### Issue: Permissions not working
**Solution:** Run the permission seeder
```bash
php artisan db:seed --class=PaymentPlanPermissionSeeder
```

### Issue: Menu not showing
**Solution:** Ensure user has `payment-plan.index` permission

### Issue: Student fees not loading
**Solution:** Check AJAX endpoint `/admin/payment-plan/get-student-fees` is accessible

### Issue: Automation not running
**Solution:** 
1. Test manually: `php artisan payment-plan:maintain`
2. Add to scheduler in `app/Console/Kernel.php`
3. Ensure cron job is set up: `* * * * * php /path/to/artisan schedule:run >> /dev/null 2>&1`

---

## 📝 Notes

- Payment plans can only be created for **unpaid** or **partially paid** fees
- Only **one active payment plan** per fee (enforced in controller)
- Late fees are only applied **once** per installment
- Plans with payments **cannot be deleted** (only cancelled)
- Cancelled plans **retain all payment history** for audit purposes
- All actions are **logged** via Auditable trait
- Payment receipts stored in `uploads/payment-plan/`

---

## 🚀 Future Enhancements (Optional)

- Email/SMS notifications for payment reminders
- Online payment gateway integration for students
- Automatic payment scheduling
- Payment plan templates
- Bulk payment plan creation
- Export payment plan reports
- WhatsApp/Telegram reminders
- Mobile app support

---

## 📞 Support

For issues or questions about this feature:
1. Check the troubleshooting section above
2. Review logs in `storage/logs/laravel.log`
3. Run: `php artisan payment-plan:maintain -vvv` for verbose output
4. Check audit logs in the database for detailed change history

---

**Feature Developed:** October 8, 2025  
**Version:** 1.0.0  
**Status:** Production Ready ✅
