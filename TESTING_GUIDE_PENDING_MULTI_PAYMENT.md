# Testing Guide: Pending Multi-Payment Protection System

## Pre-Testing Checklist

✅ All caches cleared (`php artisan view:clear` and `php artisan cache:clear`)
✅ All model methods implemented
✅ All controller validations added
✅ All view files updated
✅ Database migrations completed

---

## Test Scenario 1: Basic Multi-Payment Pending Protection

### Steps:
1. **Login as Student**
   - Navigate to: Student Dashboard → Fees

2. **Create Multi-Payment**
   - Select 2-3 fees using checkboxes
   - Click "Proceed to Payment" (shopping cart button)
   - Review amounts and click "Submit Multi-Payment"
   - Multi-payment status should be: **pending**

3. **Verify Visual Indicators**
   - Return to Fees page
   - ✅ Fees included in multi-payment should show orange badge: "Multi-Pay Pending (amount)"
   - ✅ Badge should display correct pending amount
   - ✅ Checkbox for those fees should be disabled (grayed out)

4. **Test Checkbox Prevention**
   - Try to check checkbox for fee with pending multi-payment
   - ✅ Should be disabled and not selectable
   - ✅ Cannot add to cart again

5. **Test Manual Receipt Upload Button**
   - Look at "Action" column for fee with pending multi-payment
   - ✅ Instead of "Upload Receipt" button, should see disabled "Payment Pending" button
   - ✅ Button should be gray and not clickable

6. **Test Direct Access Prevention**
   - Try to access upload form directly by navigating to: `/student/manual-payment/create/{fee_id}`
   - ✅ Should be redirected back to fees page
   - ✅ Should see warning message: "This fee has a pending multi-payment verification. Please wait for approval."

---

## Test Scenario 2: Payment Plan Installment Protection

### Steps:
1. **Setup Payment Plan**
   - Ensure student has an active payment plan with unpaid installments

2. **Create Multi-Payment with Installment**
   - Select fees that include payment plan installment distribution
   - Submit multi-payment (status: pending)

3. **Navigate to Payment Plan**
   - Go to: Student Dashboard → Payment Plans → View specific plan

4. **Verify Installment Protection**
   - Find installment included in pending multi-payment
   - ✅ Should show disabled button: "Multi-Pay Pending"
   - ✅ "Pay Now" button should NOT be visible
   - ✅ Button should be gray/secondary color

5. **Test Direct Installment Payment**
   - Try to access: `/student/installment-payment/create/{installment_id}`
   - ✅ Should be redirected back to payment plan
   - ✅ Should see warning message about pending multi-payment

---

## Test Scenario 3: Manual Payment Index View

### Steps:
1. **Navigate to Manual Payment Index**
   - Go to: Student Dashboard → Manual Payment (if available)

2. **Check Fee List**
   - Fees with pending multi-payment should show:
   - ✅ Disabled button: "Multi-Pay Pending"
   - ✅ NOT show "Pay Now" button
   - ✅ NOT show "Upload Receipt" button

3. **Verify Two Locations**
   - Scroll through both sections of the page
   - Both should consistently show pending status

---

## Test Scenario 4: Admin Report Visibility

### Steps:
1. **Login as Admin**
   - Navigate to: Admin Dashboard → Fees → Reports

2. **View Fees Student Report**
   - Go to: Fees → Student Fees Report

3. **Check Status Column**
   - Find fees with pending multi-payment
   - ✅ Should see orange badge: "Multi-Pay Pending (amount)" below status badge
   - ✅ Amount should be displayed correctly
   - ✅ Badge should have clock icon

4. **Test Enhanced Report**
   - Navigate to enhanced report version (if available)
   - ✅ Same badge should appear

---

## Test Scenario 5: Admin Approval Flow

### Steps:
1. **Login as Admin**
   - Navigate to: Payment Verification → Multi-Payments

2. **Find Pending Multi-Payment**
   - Locate the multi-payment created in Test Scenario 1
   - Status should be: **pending**

3. **Approve Multi-Payment**
   - Click "Approve" button
   - Confirm approval

4. **Verify Status Clear (Student Side)**
   - Login as student
   - Go to Fees page
   - ✅ "Multi-Pay Pending" badge should be GONE
   - ✅ Checkboxes should be enabled again (if balance > 0)
   - ✅ Action buttons should be normal (Upload Receipt or Pay Now)

5. **Check Fee Balance**
   - ✅ Fee balance should be updated (reduced by multi-payment amount)
   - ✅ If fully paid, status should be "Paid"

---

## Test Scenario 6: Admin Rejection Flow

### Steps:
1. **Create Another Multi-Payment**
   - Login as student
   - Create new multi-payment (status: pending)

2. **Reject Multi-Payment (Admin)**
   - Login as admin
   - Navigate to pending multi-payment
   - Click "Reject" button
   - Optionally add rejection reason

3. **Verify Status Clear (Student Side)**
   - Login as student
   - Go to Fees page
   - ✅ "Multi-Pay Pending" badge should be GONE
   - ✅ Student can now make new payment
   - ✅ Checkboxes enabled, buttons active

4. **Check Fee Balance**
   - ✅ Fee balance should be UNCHANGED (no payment applied)
   - ✅ Student can try payment again

---

## Test Scenario 7: Multiple Fees with Different Pending Amounts

### Steps:
1. **Create Multi-Payment with Partial Amounts**
   - Select 3 fees
   - Distribute payment amounts (e.g., Fee1: 1000, Fee2: 500, Fee3: 0)
   - Submit multi-payment

2. **Verify Different Badge Amounts**
   - ✅ Fee1 badge: "Multi-Pay Pending (1000.00)"
   - ✅ Fee2 badge: "Multi-Pay Pending (500.00)"
   - ✅ Fee3: NO badge (amount = 0, not included)

3. **Verify Fee3 Not Protected**
   - ✅ Fee3 checkbox should NOT be disabled
   - ✅ Fee3 can still be paid via other methods

---

## Test Scenario 8: Edge Case - Direct URL Manipulation

### Steps:
1. **Create Multi-Payment** (status: pending)

2. **Try URL Hacking (Student)**
   - Manually type URL: `/student/manual-payment/create/{fee_id_with_pending}`
   - ✅ Should redirect with warning
   - Try URL: `/student/installment-payment/create/{installment_id_with_pending}`
   - ✅ Should redirect with warning

3. **Try Form Submission (Using Browser DevTools)**
   - Open browser DevTools
   - Enable disabled checkbox manually
   - Try to submit form
   - ✅ Should be caught by controller validation
   - ✅ Should redirect with warning

---

## Test Scenario 9: Performance Test (Multiple Students)

### Steps:
1. **Create Multiple Multi-Payments**
   - Have 5-10 students create multi-payments simultaneously

2. **Check Admin Report**
   - Navigate to fees report
   - ✅ Page should load without errors
   - ✅ All pending badges should display correctly
   - ✅ No database performance issues (check query time)

3. **Check Student Fees Page**
   - Each student loads their fees page
   - ✅ Page loads quickly (< 2 seconds)
   - ✅ All pending statuses accurate

---

## Expected Results Summary

### Visual Indicators:
- ✅ Orange badge with clock icon
- ✅ "Multi-Pay Pending (amount)" text
- ✅ Correct amount displayed
- ✅ Consistent across all views

### UI Prevention:
- ✅ Checkboxes disabled
- ✅ Buttons replaced with disabled state
- ✅ Clear messaging to user

### Backend Protection:
- ✅ Controller redirects with warning
- ✅ Form submission blocked
- ✅ Direct URL access prevented

### Admin Visibility:
- ✅ Pending status visible in reports
- ✅ Amount displayed correctly
- ✅ Can approve/reject normally

### Approval Flow:
- ✅ On approval: status clears, payment applied
- ✅ On rejection: status clears, no payment applied
- ✅ Student can make new payment after rejection

---

## Common Issues and Solutions

### Issue: Badge not showing
**Solution**: Clear view cache (`php artisan view:clear`)

### Issue: Checkbox still enabled
**Solution**: Check if hasPendingMultiPayment() method is correct, verify distribution records exist

### Issue: Direct URL access works
**Solution**: Check controller validation, ensure methods are called in correct order

### Issue: Amount showing as 0
**Solution**: Verify amount_applied > 0 in distribution records, check getPendingMultiPaymentAmount() method

### Issue: Badge shows after approval
**Solution**: Verify multi_payment status changed to 'approved' (not 'pending'), clear cache

---

## Reporting Test Results

### For Each Test:
- [ ] Test completed successfully
- [ ] Issues found (describe)
- [ ] Screenshots taken (if visual issue)

### Overall Status:
- [ ] All tests passed
- [ ] Some tests failed (requires fix)
- [ ] Ready for production

---

## Quick Test Commands

```powershell
# Clear all caches
php artisan view:clear
php artisan cache:clear
php artisan config:clear

# Run verification script
php verify_pending_multi_payment_protection.php

# Check for errors
php artisan route:list | findstr "multi-payment"
```

---

**Tester Name**: _________________
**Test Date**: _________________
**Environment**: Development / Staging / Production
**Browser**: Chrome / Firefox / Edge / Safari
**Status**: ✅ Pass / ❌ Fail / ⚠️ Issues Found
