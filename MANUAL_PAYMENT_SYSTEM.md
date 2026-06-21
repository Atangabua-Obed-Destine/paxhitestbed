# Manual Payment with Receipt Upload System

## Overview
This system allows students to pay their fees manually (e.g., bank deposit, mobile money) and upload payment receipts for admin verification. Once approved, the fee status is automatically updated.

## Features

### For Students:
- ✅ View all unpaid fees
- ✅ Upload payment receipt (JPEG, PNG, PDF up to 5MB)
- ✅ Enter payment details (reference number, date, amount, method)
- ✅ Track submission status (Pending/Approved/Rejected)
- ✅ View admin feedback on rejections

### For Admins:
- ✅ View all payment receipts (filterable by status)
- ✅ See uploaded receipt images/PDFs
- ✅ Approve receipts (auto-updates fee status to "Paid")
- ✅ Reject receipts with feedback notes
- ✅ View student and fee details
- ✅ Track who verified each receipt and when

## Database Schema

### `payment_receipts` Table
```sql
id                  - Primary key
fee_id              - Foreign key to fees table
student_id          - Foreign key to students table
receipt_file        - Path to uploaded file
payment_reference   - Transaction/reference number
payment_date        - When student made payment
amount              - Amount paid
payment_method      - Payment method (1-6)
student_note        - Optional note from student
verification_status - enum('pending', 'approved', 'rejected')
verification_note   - Admin's feedback
verified_by         - Foreign key to users table
verified_at         - Timestamp of verification
created_at/updated_at
```

## Workflow

### 1. Student Submits Payment
1. Student pays fee manually (bank, mobile money, etc.)
2. Student logs into portal
3. Navigates to "Manual Payment" section
4. Selects the fee to pay
5. Uploads receipt image/PDF
6. Enters payment details:
   - Payment Reference Number
   - Payment Date
   - Amount Paid
   - Payment Method
   - Optional Note
7. Submits for verification
8. Status: **Pending**

### 2. Admin Verification
1. Admin logs in
2. Sees notification of pending receipts
3. Opens "Payment Verification" section
4. Views receipt details:
   - Student information
   - Fee details
   - Uploaded receipt image
   - Payment information
5. Admin reviews the receipt

### 3a. Approval Flow
1. Admin clicks "Approve"
2. Optionally adds verification note
3. System automatically:
   - Updates receipt status to "Approved"
   - Updates fee status to "Paid"
   - Records payment details in fee
   - Logs audit trail
   - Notifies student (future enhancement)
4. Student can now see "Approved" status

### 3b. Rejection Flow
1. Admin clicks "Reject"
2. **Must** provide rejection reason
3. System:
   - Updates receipt status to "Rejected"
   - Records admin's note
   - Logs audit trail
   - Notifies student (future enhancement)
4. Student can:
   - View rejection reason
   - Re-upload correct receipt

## File Structure

### Models
- `app/Models/PaymentReceipt.php` - Main model with relationships

### Controllers
- `app/Http/Controllers/Student/ManualPaymentController.php` - Student operations
- `app/Http/Controllers/Admin/PaymentVerificationController.php` - Admin verification

### Views
**Student Portal:**
- `resources/views/student/manual-payment/index.blade.php` - List fees & receipts
- `resources/views/student/manual-payment/create.blade.php` - Upload form
- `resources/views/student/manual-payment/show.blade.php` - Receipt status

**Admin Portal:**
- `resources/views/admin/payment-verification/index.blade.php` - Receipts list
- `resources/views/admin/payment-verification/show.blade.php` - Verification page

### Routes
```php
// Student Routes
Route::get('manual-payment', 'ManualPaymentController@index');
Route::get('manual-payment/create/{fee_id}', 'ManualPaymentController@create');
Route::post('manual-payment/store', 'ManualPaymentController@store');
Route::get('manual-payment/{id}', 'ManualPaymentController@show');

// Admin Routes
Route::get('payment-verification', 'PaymentVerificationController@index');
Route::get('payment-verification/{id}', 'PaymentVerificationController@show');
Route::post('payment-verification/{id}/approve', 'PaymentVerificationController@approve');
Route::post('payment-verification/{id}/reject', 'PaymentVerificationController@reject');
```

## Payment Methods
```php
1 => MTN MOBILE MONEY
2 => Cash
3 => ORANGE MONEY
4 => Bank Transfer
5 => Other payment method
6 => Manual Payment (new)
```

## Permissions
- `payment-receipt-verify` - Can view and verify payment receipts (Admin only)

## Security Features
1. ✅ Students can only upload receipts for their own fees
2. ✅ Students cannot modify pending receipts
3. ✅ File upload validation (type, size)
4. ✅ Permission-based access control
5. ✅ Audit trail for all actions
6. ✅ Prevents duplicate submissions for same fee

## Validation Rules

### Receipt Upload
- **File**: Required, must be JPEG/PNG/PDF
- **File Size**: Maximum 5MB
- **Payment Date**: Required, cannot be future date
- **Amount**: Required, must be positive number
- **Payment Method**: Required, must be 1-6
- **Payment Reference**: Optional, max 255 characters
- **Student Note**: Optional, max 1000 characters

### Verification
- **Approval**: Optional verification note (max 1000 chars)
- **Rejection**: Required verification note (max 1000 chars)

## Audit Trail Integration
All actions are automatically logged via the `Auditable` trait:
- Receipt uploaded by student
- Receipt approved by admin
- Receipt rejected by admin
- Fee status updated

## Future Enhancements
- [ ] Email/SMS notifications for status changes
- [ ] Bulk approval for multiple receipts
- [ ] Receipt expiry (auto-reject after X days)
- [ ] OCR for automatic data extraction
- [ ] Receipt templates/guidelines for students
- [ ] Analytics dashboard for payment trends
- [ ] Export receipts to PDF/Excel

## Testing Checklist
- [ ] Student uploads valid receipt
- [ ] Student uploads invalid file (wrong type/size)
- [ ] Student tries to upload for paid fee
- [ ] Student tries to upload duplicate receipt
- [ ] Admin approves receipt
- [ ] Fee status updates to paid
- [ ] Admin rejects receipt with note
- [ ] Student views rejection reason
- [ ] Student re-uploads after rejection
- [ ] Audit trail logs all actions
- [ ] Permissions work correctly
- [ ] File storage works properly

## Storage Requirements
- Receipts stored in: `public/uploads/payment-receipts/`
- Recommended: Set up daily backups
- Monitor disk space usage

## Troubleshooting

### Receipt not uploading
- Check file size (max 5MB)
- Check file type (JPEG/PNG/PDF only)
- Check upload directory permissions

### Fee not updating after approval
- Check fee status before approval
- Verify foreign key constraints
- Check audit logs for errors

### Permission denied
- Ensure user has `payment-receipt-verify` permission
- Check role assignments
- Run: `php artisan permission:cache-reset`

## Support
For issues or questions, check the audit trail logs or contact the system administrator.
