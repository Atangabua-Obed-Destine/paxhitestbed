# Quick Reference: Per-Student Publish Control

## 🎯 What It Does
Allows unpublishing/republishing individual student results independently from the class.

---

## 🔑 Who Can Use It?
- ✅ Super Admin
- ✅ HOD (Head of Department)
- ✅ Exam Officer
- ✅ Academic Dean
- ✅ Anyone with `subject-marking-unpublish` permission

---

## 📍 Where to Find It
**URL**: `http://localhost/paxhitest/admin/exam/subject-marking`

**Steps**:
1. Select Faculty, Program, Session, Semester, Section, Subject
2. Click "Filter"
3. Find student row
4. Look at **"Publish Control"** column (rightmost)

---

## 🔒 How to Unpublish a Result

### When to Use
- Grade appeal pending
- Academic misconduct investigation
- Missing coursework needs processing
- Data entry error correction

### Steps
1. Ensure section is **Published** (bulk transition first if needed)
2. Find student row
3. Click yellow **"Unpublish"** button in "Publish Control" column
4. Modal opens
5. Enter reason (minimum 10 characters, maximum 500)
   - Example: *"Grade appeal pending review - disputed final exam calculation"*
6. Click **"Unpublish Result"**
7. ✅ Success message appears
8. Badge changes to 🔒 **"Unpublished"** (red)
9. Student **cannot** see this result anymore

---

## 🔓 How to Republish a Result

### When to Use
- Appeal resolved
- Investigation complete
- Correction finished
- Issue addressed

### Steps
1. Find student with 🔒 **"Unpublished"** badge
2. Click green **"Republish"** button
3. Modal opens
4. Enter optional reason
   - Example: *"Appeal resolved - original grade upheld"*
5. Click **"Republish Result"**
6. ✅ Success message appears
7. Badge changes to 🔓 **"Published"** (green)
8. Student **can** see this result again

---

## 📊 Status Badges

| Badge | Meaning | Student Sees? |
|-------|---------|---------------|
| 🔓 **Published** (green) | Following workflow, result visible | ✅ Yes |
| 🔒 **Unpublished** (red) | Manually hidden | ❌ No |
| 🔓 **Force Published** (green) | Manually visible | ✅ Yes |

---

## ⚠️ Important Notes

### Cannot Unpublish If:
- ❌ Section not published yet → **Publish section first**
- ❌ Already unpublished → **Already hidden from student**

### Cannot Republish If:
- ❌ Not currently unpublished → **Already visible to student**

### Validation Rules:
- **Unpublish reason**: Required, 10-500 characters
- **Republish reason**: Optional, max 500 characters

---

## 🔍 What Students See

### When Result Unpublished:
- ❌ Result **disappears** from transcript
- ❌ Result **excluded** from dashboard "Recent Results"
- ❌ **Not counted** in CGPA calculation
- ❌ No notification (just absent from portal)

### When Result Republished:
- ✅ Result **reappears** in transcript
- ✅ Result **shows** in dashboard
- ✅ **Included** in CGPA calculation
- ✅ Seamless - as if never unpublished

---

## 📝 Examples

### Example 1: Grade Appeal
```
Reason: "Grade appeal pending - student disputes final exam score"
Action: Unpublish → Review → Republish
Timeline: 3-5 days
```

### Example 2: Plagiarism Investigation
```
Reason: "Academic misconduct investigation in progress"
Action: Unpublish → Investigate → Decision
Timeline: 1-2 weeks
```

### Example 3: Data Entry Error
```
Reason: "Data entry error - entered wrong student's marks"
Action: Unpublish → Correct → Republish
Timeline: Same day
```

---

## 🚨 Troubleshooting

| Problem | Solution |
|---------|----------|
| Can't see "Unpublish" button | Section not published - do bulk transition first |
| "Permission denied" error | Contact admin to grant permission |
| Student still sees result | Clear cache: `php artisan cache:clear` |
| Reason rejected | Must be 10-500 characters |

---

## 📞 Support
Contact: System Administrator
Permission Issues: Admin → Roles & Permissions

---

## ✅ Quick Checklist

Before Unpublishing:
- [ ] Section is published
- [ ] Have clear reason (min 10 chars)
- [ ] Notified relevant parties (HOD, etc.)

After Unpublishing:
- [ ] Verify student can't see result
- [ ] Document in student file
- [ ] Set reminder to resolve issue

After Republishing:
- [ ] Verify student can see result
- [ ] Update student file
- [ ] Close issue ticket

---

**Remember**: Every action is logged with your name, timestamp, and reason!
