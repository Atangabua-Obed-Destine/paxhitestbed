# Platform Fee System - Quick Start Guide

## 🚀 Getting Started (5 Minutes)

### Step 1: Enable the Platform Fee System
1. Login as **Super Admin**
2. Navigate to **Platform Fee > Settings**
3. Toggle **Enable Platform Fee** to ON
4. Set **Fee Amount** (e.g., 50.00)
5. Customize **Welcome Message** and **Payment Instructions** (optional)
6. Click **Save Settings**

### Step 2: Test as Student
1. Logout from admin
2. Login as any **Student**
3. You'll be redirected to the **Platform Fee Payment Page**
4. Upload a test receipt (JPG/PNG/PDF, max 2MB)
5. Select payment date
6. Add optional note
7. Click **Submit Payment**
8. You'll see "Payment Pending Verification" message

### Step 3: Verify Payment as Admin
1. Logout and login as **Admin**
2. Navigate to **Platform Fee > Verifications**
3. You'll see the pending payment in the table
4. Click **View** to see receipt and details
5. Click **Approve** and add optional admin note
6. Confirm approval

### Step 4: Verify Student Access
1. Logout and login as the **Student** again
2. Student now has full access to the dashboard!

---

## 📋 Admin Menu Navigation

### Platform Fee Menu (After Fees Section)
```
📁 Platform Fee
  ├── ⚙️ Settings - Configure fee amount and messages
  ├── ✅ Verifications - Approve/reject payment receipts
  ├── 📊 Statistics - View payment statistics and revenue
  └── 🛡️ Exemptions - Manage fee exemptions
```

---

## 🎯 Common Tasks

### How to Configure Fee Amount
1. Go to **Platform Fee > Settings**
2. Change **Fee Amount** field
3. Click **Save Settings**
4. All new students will see the updated amount

### How to Approve a Payment
1. Go to **Platform Fee > Verifications**
2. Filter by **Pending Only** (default)
3. Click **View** on any pending payment
4. Review receipt image/PDF
5. Click **Approve** button
6. Add optional admin note
7. Confirm

### How to Reject a Payment
1. Go to **Platform Fee > Verifications**
2. Click **Reject** on the payment
3. **MUST provide a reason** (student will see this)
4. Confirm rejection
5. Student can now reupload a new receipt

### How to Create an Exemption
1. Go to **Platform Fee > Exemptions**
2. Click **Add Exemption**
3. Choose exemption type:
   - **Student Specific**: Select the student
   - **Entire Session**: Select the session
4. Add optional reason
5. Ensure **Active** is checked
6. Click **Create Exemption**

### How to View Statistics
1. Go to **Platform Fee > Statistics**
2. See overview cards:
   - Total Payments
   - Pending Review
   - Approved
   - Rejected
3. View **Total Revenue Collected**
4. Check **Session-wise Breakdown** table

---

## 🔧 Configuration Options

### Settings Page Fields:
- **Enable Platform Fee** - Master on/off switch
- **Fee Title** - Display name (e.g., "Platform Access Fee")
- **Fee Amount** - Amount to charge (uses system currency)
- **Welcome Message** - Message shown to students
- **Payment Instructions** - Detailed payment instructions

### Exemption Types:
- **Student Specific**: Exempts one student across all sessions
- **Entire Session**: Exempts all students in a specific session

---

## 🎨 Student Experience

### What Students See:
1. **Login** → Automatic redirect to payment page
2. **Payment Page** shows:
   - Welcome message with fee title
   - Fee amount in large display
   - Their student info (name, ID, program, session)
   - Payment instructions
   - Upload form (if no payment yet)
   - Payment status (if payment submitted)

3. **After Upload**:
   - Status badge: "Pending Verification"
   - Message: "Your payment is under review"
   - Can access profile page
   - Cannot access dashboard/other pages

4. **After Approval**:
   - Full access to student portal
   - No more payment page redirect

5. **After Rejection**:
   - Status badge: "Payment Rejected"
   - Admin's rejection reason displayed
   - Upload form reappears
   - Can upload new receipt

---

## 📊 Reports & Statistics

### Statistics Dashboard Shows:
- **Total Payments**: All submitted payments
- **Pending Review**: Awaiting admin verification
- **Approved**: Verified and granted access
- **Rejected**: Rejected payments
- **Total Revenue**: Sum of all approved payments
- **Session Breakdown**: Per-session stats (total, pending, approved, rejected, revenue)

### Verification Page Features:
- **DataTables** with search and sorting
- **Filter by Status**: All, Pending, Approved, Rejected
- **Real-time Updates**: AJAX-based, no page refresh
- **Batch Actions**: Quick approve/reject

---

## 🛡️ Security Features

- ✅ File type validation (JPG, PNG, PDF only)
- ✅ File size limit (2MB maximum)
- ✅ Middleware-based access control
- ✅ Unique payment per student per session
- ✅ Admin authentication required
- ✅ Secure file storage
- ✅ CSRF protection on all forms

---

## 🎯 Best Practices

### For Admins:
1. **Always provide rejection reasons** - Helps students understand what's wrong
2. **Review receipts carefully** - Check payment amount, date, authenticity
3. **Use exemptions wisely** - Document reasons for audit purposes
4. **Monitor statistics regularly** - Track payment collection progress
5. **Update fee amount annually** - Keep in sync with institutional policy

### For Implementation:
1. **Test before production** - Use test student accounts
2. **Communicate with students** - Announce the new system
3. **Provide payment instructions** - Clear bank details, payment methods
4. **Set realistic timelines** - Allow time for admin verification
5. **Train admin staff** - Ensure they know how to verify payments

---

## ❓ Troubleshooting

### Student can't upload receipt
- **Check file size**: Max 2MB allowed
- **Check file type**: Only JPG, PNG, PDF accepted
- **Check browser**: Try different browser
- **Clear cache**: Refresh the page

### Admin can't see pending payments
- **Check filter**: Ensure "Pending Only" is selected
- **Refresh page**: Click refresh button
- **Check database**: Verify payment was actually submitted

### Student still blocked after approval
- **Clear student cache**: Student should logout and login again
- **Check approval status**: Verify status is "approved" in database
- **Check middleware**: Ensure middleware is registered

### Uploads directory not writable
- **Windows**: Right-click folder → Properties → Security → Grant write permissions
- **Linux**: `chmod -R 777 public/uploads/platform-fees`

### Badge not showing pending count
- **Check route**: Ensure `admin.platform-fee.pending-count` route exists
- **Check JavaScript**: Open browser console for errors
- **Check jQuery**: Ensure jQuery is loaded on page

---

## 📞 Support & Maintenance

### Files to Monitor:
- `public/uploads/platform-fees/` - May grow large over time
- Database table `platform_fee_payments` - Monitor size

### Regular Maintenance:
- Archive old receipts annually
- Clean up rejected payment files
- Review and update exemptions
- Audit approval/rejection patterns

### Performance Optimization:
- DataTables handles pagination automatically
- File uploads are limited to 2MB
- AJAX requests are optimized
- Database queries use indexes (via foreign keys)

---

## 🎓 Additional Resources

### Documentation Files:
- `PLATFORM_FEE_SYSTEM_COMPLETE.md` - Complete implementation details
- `PLATFORM_FEE_IMPLEMENTATION_GUIDE.md` - Original implementation guide

### Key Routes:
- Admin Settings: `/admin/platform-fee/settings`
- Admin Verifications: `/admin/platform-fee/verifications`
- Admin Statistics: `/admin/platform-fee/statistics`
- Admin Exemptions: `/admin/platform-fee/exemptions`
- Student Payment: `/student/platform-fee/payment`

### Database Tables:
- `platform_fee_settings` - Global configuration
- `platform_fee_payments` - Student payments
- `platform_fee_exemptions` - Fee exemptions

---

**Last Updated:** November 7, 2025  
**System Version:** 1.0.0  
**Status:** ✅ Production Ready
