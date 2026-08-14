<?php

namespace Database\Seeders;

use Spatie\Permission\Models\Permission;
use Illuminate\Support\Facades\DB;
use Illuminate\Database\Seeder;

class PermissionSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        $permissions = [
            //*** Application Modules ***//
            ['name' => 'application-view', 'group' => 'Application', 'title' => 'View'],
            ['name' => 'application-create', 'group' => 'Application', 'title' => 'Admission'],
            ['name' => 'application-edit', 'group' => 'Application', 'title' => 'Action'],
            ['name' => 'application-delete', 'group' => 'Application', 'title' => 'Delete'],
            //*** Application Modules ***//



            //*** Student Modules ***//
            ['name' => 'student-view', 'group' => 'Student', 'title' => 'View'],
            ['name' => 'student-create', 'group' => 'Student', 'title' => 'Create'],
            ['name' => 'student-edit', 'group' => 'Student', 'title' => 'Edit'],
            ['name' => 'student-delete', 'group' => 'Student', 'title' => 'Delete'],
            ['name' => 'student-import', 'group' => 'Student', 'title' => 'Import'],
            ['name' => 'student-password-print', 'group' => 'Student', 'title' => 'Password Print'],
            ['name' => 'student-password-change', 'group' => 'Student', 'title' => 'Password Change'],
            ['name' => 'student-card', 'group' => 'Student', 'title' => 'ID Card'],

            ['name' => 'id-card-setting-view', 'group' => 'ID Card', 'title' => 'Setting'],
            
            ['name' => 'student-archive-view', 'group' => 'Student', 'title' => 'View Archives'],
            ['name' => 'student-form-a2-view', 'group' => 'Student', 'title' => 'View Student Form A2'],
            ['name' => 'student-form-a3-view', 'group' => 'Student', 'title' => 'View Student Form A3'],
            ['name' => 'form-a2-setting-view', 'group' => 'Student', 'title' => 'View Form A2 Settings'],
            ['name' => 'form-a2-setting-edit', 'group' => 'Student', 'title' => 'Edit Form A2 Settings'],
            ['name' => 'form-a3-setting-view', 'group' => 'Student', 'title' => 'View Form A3 Settings'],
            ['name' => 'form-a3-setting-edit', 'group' => 'Student', 'title' => 'Edit Form A3 Settings'],
            ['name' => 'form-a2-access-view', 'group' => 'Student', 'title' => 'View Form A2 Access'],
            ['name' => 'form-a2-access-edit', 'group' => 'Student', 'title' => 'Edit Form A2 Access'],
            //*** Student Modules ***//



            //*** Student Transfer Modules ***//
            ['name' => 'student-transfer-in-view', 'group' => 'Transfer In', 'title' => 'View'],
            ['name' => 'student-transfer-in-create', 'group' => 'Transfer In', 'title' => 'Transfer'],
            ['name' => 'student-transfer-in-edit', 'group' => 'Transfer In', 'title' => 'Edit'],

            ['name' => 'student-transfer-out-view', 'group' => 'Transfer Out', 'title' => 'View'],
            ['name' => 'student-transfer-out-create', 'group' => 'Transfer Out', 'title' => 'Transfer'],
            ['name' => 'student-transfer-out-edit', 'group' => 'Transfer Out', 'title' => 'Edit'],
            //*** Student Transfer Modules ***//



            //*** Academic Modules ***//
            ['name' => 'status-type-view', 'group' => 'Status Type', 'title' => 'View'],
            ['name' => 'status-type-create', 'group' => 'Status Type', 'title' => 'Create'],
            ['name' => 'status-type-edit', 'group' => 'Status Type', 'title' => 'Edit'],
            ['name' => 'status-type-delete', 'group' => 'Status Type', 'title' => 'Delete'],
            //*** Academic Modules ***//



            //*** Student Attendance Modules ***//
            ['name' => 'student-attendance-action', 'group' => 'Student Attendance', 'title' => 'Manage'],
            ['name' => 'student-attendance-report', 'group' => 'Student Attendance', 'title' => 'Report'],
            ['name' => 'student-attendance-import', 'group' => 'Student Attendance', 'title' => 'Import'],
            //*** Student Attendance Modules ***//



            //*** Class Session & Kiosk Modules ***//
            ['name' => 'class-session-view', 'group' => 'Attendance', 'title' => 'View Class Sessions'],
            ['name' => 'class-session-create', 'group' => 'Attendance', 'title' => 'Create Class Sessions'],
            ['name' => 'class-session-edit', 'group' => 'Attendance', 'title' => 'Edit Class Sessions'],
            ['name' => 'class-session-delete', 'group' => 'Attendance', 'title' => 'Delete Class Sessions'],
            ['name' => 'attendance-setting-view', 'group' => 'Attendance', 'title' => 'View Kiosk Settings'],
            ['name' => 'attendance-setting-edit', 'group' => 'Attendance', 'title' => 'Edit Kiosk Settings'],
            ['name' => 'class-hub-reports-view', 'group' => 'Attendance', 'title' => 'View Class Hub Reports'],
            ['name' => 'class-hub-reports-export', 'group' => 'Attendance', 'title' => 'Export Class Hub Reports'],
            //*** Class Session & Kiosk Modules ***//



            //*** Student Leave Modules ***//
            ['name' => 'student-leave-manage-view', 'group' => 'Student Leave', 'title' => 'View'],
            ['name' => 'student-leave-manage-edit', 'group' => 'Student Leave', 'title' => 'Action'],
            ['name' => 'student-leave-manage-delete', 'group' => 'Student Leave', 'title' => 'Delete'],
            //*** Student Leave Modules ***//



            //*** Student Note Modules ***//
            ['name' => 'student-note-view', 'group' => 'Student Note', 'title' => 'View'],
            ['name' => 'student-note-create', 'group' => 'Student Note', 'title' => 'Create'],
            ['name' => 'student-note-edit', 'group' => 'Student Note', 'title' => 'Edit'],
            ['name' => 'student-note-delete', 'group' => 'Student Note', 'title' => 'Delete'],
            //*** Student Note Modules ***//



            //*** Student Enroll Modules ***//
            ['name' => 'student-enroll-single', 'group' => 'Enrollments', 'title' => 'Single Enroll'],
            ['name' => 'student-enroll-group', 'group' => 'Enrollments', 'title' => 'Group Enroll'],
            ['name' => 'student-enroll-adddrop', 'group' => 'Enrollments', 'title' => 'Course Add Drop'],
            ['name' => 'student-enroll-complete', 'group' => 'Enrollments', 'title' => 'Course Graduation'],
            ['name' => 'student-enroll-alumni', 'group' => 'Enrollments', 'title' => 'Alumni List'],
            //*** Student Enroll Modules ***//



            //*** Academic Modules ***//
            ['name' => 'faculty-view', 'group' => 'Faculty', 'title' => 'View'],
            ['name' => 'faculty-create', 'group' => 'Faculty', 'title' => 'Create'],
            ['name' => 'faculty-edit', 'group' => 'Faculty', 'title' => 'Edit'],
            ['name' => 'faculty-delete', 'group' => 'Faculty', 'title' => 'Delete'],

            ['name' => 'degree-type-view', 'group' => 'Degree Type', 'title' => 'View'],
            ['name' => 'degree-type-create', 'group' => 'Degree Type', 'title' => 'Create'],
            ['name' => 'degree-type-edit', 'group' => 'Degree Type', 'title' => 'Edit'],
            ['name' => 'degree-type-delete', 'group' => 'Degree Type', 'title' => 'Delete'],

            ['name' => 'program-view', 'group' => 'Program', 'title' => 'View'],
            ['name' => 'program-create', 'group' => 'Program', 'title' => 'Create'],
            ['name' => 'program-edit', 'group' => 'Program', 'title' => 'Edit'],
            ['name' => 'program-delete', 'group' => 'Program', 'title' => 'Delete'],

            ['name' => 'batch-view', 'group' => 'Batch', 'title' => 'View'],
            ['name' => 'batch-create', 'group' => 'Batch', 'title' => 'Create'],
            ['name' => 'batch-edit', 'group' => 'Batch', 'title' => 'Edit'],
            ['name' => 'batch-delete', 'group' => 'Batch', 'title' => 'Delete'],

            ['name' => 'session-view', 'group' => 'Session', 'title' => 'View'],
            ['name' => 'session-create', 'group' => 'Session', 'title' => 'Create'],
            ['name' => 'session-edit', 'group' => 'Session', 'title' => 'Edit'],
            ['name' => 'session-delete', 'group' => 'Session', 'title' => 'Delete'],

            ['name' => 'semester-view', 'group' => 'Semester', 'title' => 'View'],
            ['name' => 'semester-create', 'group' => 'Semester', 'title' => 'Create'],
            ['name' => 'semester-edit', 'group' => 'Semester', 'title' => 'Edit'],
            ['name' => 'semester-delete', 'group' => 'Semester', 'title' => 'Delete'],

            ['name' => 'section-view', 'group' => 'Section', 'title' => 'View'],
            ['name' => 'section-create', 'group' => 'Section', 'title' => 'Create'],
            ['name' => 'section-edit', 'group' => 'Section', 'title' => 'Edit'],
            ['name' => 'section-delete', 'group' => 'Section', 'title' => 'Delete'],

            ['name' => 'class-room-view', 'group' => 'Class Room', 'title' => 'View'],
            ['name' => 'class-room-create', 'group' => 'Class Room', 'title' => 'Create'],
            ['name' => 'class-room-edit', 'group' => 'Class Room', 'title' => 'Edit'],
            ['name' => 'class-room-delete', 'group' => 'Class Room', 'title' => 'Delete'],
            //*** Academic Modules ***//



            //*** Subject Modules ***//
            ['name' => 'subject-view', 'group' => 'Course', 'title' => 'View'],
            ['name' => 'subject-create', 'group' => 'Course', 'title' => 'Create'],
            ['name' => 'subject-edit', 'group' => 'Course', 'title' => 'Edit'],
            ['name' => 'subject-delete', 'group' => 'Course', 'title' => 'Delete'],
            ['name' => 'subject-import', 'group' => 'Course', 'title' => 'Import'],

            ['name' => 'enroll-subject-view', 'group' => 'Enroll Course', 'title' => 'View'],
            ['name' => 'enroll-subject-create', 'group' => 'Enroll Course', 'title' => 'Create'],
            ['name' => 'enroll-subject-edit', 'group' => 'Enroll Course', 'title' => 'Edit'],
            ['name' => 'enroll-subject-delete', 'group' => 'Enroll Course', 'title' => 'Delete'],
            //*** Subject Modules ***//



            //*** Routine Modules ***//
            ['name' => 'class-routine-view', 'group' => 'Class Routine', 'title' => 'View'],
            ['name' => 'class-routine-create', 'group' => 'Class Routine', 'title' => 'Manage'],
            ['name' => 'class-routine-print', 'group' => 'Class Routine', 'title' => 'Print'],
            ['name' => 'class-routine-teacher', 'group' => 'Class Routine', 'title' => 'Teacher'],

            ['name' => 'exam-routine-view', 'group' => 'Exam Routine', 'title' => 'View'],
            ['name' => 'exam-routine-create', 'group' => 'Exam Routine', 'title' => 'Manage'],
            ['name' => 'exam-routine-edit', 'group' => 'Exam Routine', 'title' => 'Edit'],
            ['name' => 'exam-routine-delete', 'group' => 'Exam Routine', 'title' => 'Delete'],
            ['name' => 'exam-routine-print', 'group' => 'Exam Routine', 'title' => 'Print'],

            ['name' => 'routine-setting-class', 'group' => 'Routine Setting', 'title' => 'Class Routine'],
            ['name' => 'routine-setting-exam', 'group' => 'Routine Setting', 'title' => 'Exam Routine'],
            //*** Routine Modules ***//



            //*** Exam Manage Modules ***//
            ['name' => 'exam-attendance', 'group' => 'Exam', 'title' => 'Attendance'],
            ['name' => 'exam-attendance-bypass', 'group' => 'Exam', 'title' => 'Attendance Bypass'],
            ['name' => 'exam-marking', 'group' => 'Exam', 'title' => 'Mark Ledger'],
            ['name' => 'exam-result', 'group' => 'Exam', 'title' => 'Result'],
            ['name' => 'exam-import', 'group' => 'Exam', 'title' => 'Import'],

            ['name' => 'subject-marking', 'group' => 'Course Final', 'title' => 'Mark Ledger'],
            ['name' => 'subject-marking-submit', 'group' => 'Course Final', 'title' => 'Submit For Check'],
            ['name' => 'subject-marking-check', 'group' => 'Course Final', 'title' => 'Check Marks'],
            ['name' => 'subject-marking-approve', 'group' => 'Course Final', 'title' => 'Approve Marks'],
            ['name' => 'subject-marking-publish', 'group' => 'Course Final', 'title' => 'Publish Marks'],
            ['name' => 'subject-marking-unpublish', 'group' => 'Course Final', 'title' => 'Unpublish Individual Results'],
            ['name' => 'subject-result', 'group' => 'Course Final', 'title' => 'Result'],
            ['name' => 'resit-request-view', 'group' => 'Resit', 'title' => 'View Requests'],
            ['name' => 'resit-request-transition', 'group' => 'Resit', 'title' => 'Manage Workflow'],
            ['name' => 'resit-request-finance', 'group' => 'Resit', 'title' => 'Finance Review'],
            ['name' => 'resit-request-approve', 'group' => 'Resit', 'title' => 'Academic Approval'],
            ['name' => 'resit-request-schedule', 'group' => 'Resit', 'title' => 'Schedule'],
            ['name' => 'resit-request-reject', 'group' => 'Resit', 'title' => 'Reject'],
            //*** Exam Manage Modules ***//



            //*** Exam Modules ***//
            ['name' => 'grade-view', 'group' => 'Grading System', 'title' => 'View'],
            ['name' => 'grade-create', 'group' => 'Grading System', 'title' => 'Create'],
            ['name' => 'grade-edit', 'group' => 'Grading System', 'title' => 'Edit'],
            ['name' => 'grade-delete', 'group' => 'Grading System', 'title' => 'Delete'],

            ['name' => 'exam-type-view', 'group' => 'Exam Type', 'title' => 'View'],
            ['name' => 'exam-type-create', 'group' => 'Exam Type', 'title' => 'Create'],
            ['name' => 'exam-type-edit', 'group' => 'Exam Type', 'title' => 'Edit'],
            ['name' => 'exam-type-delete', 'group' => 'Exam Type', 'title' => 'Delete'],

            ['name' => 'admit-card-view', 'group' => 'Admit Card', 'title' => 'View'],
            ['name' => 'admit-card-print', 'group' => 'Admit Card', 'title' => 'Print'],
            ['name' => 'admit-card-download', 'group' => 'Admit Card', 'title' => 'Download'],

            ['name' => 'admit-setting-view', 'group' => 'Admit Card', 'title' => 'Setting'],
            ['name' => 'result-contribution-view', 'group' => 'Mark Distribution', 'title' => 'Setting'],
            //*** Exam Modules ***//



            //*** Download Center Modules ***//
            ['name' => 'assignment-view', 'group' => 'Assignment', 'title' => 'View'],
            ['name' => 'assignment-create', 'group' => 'Assignment', 'title' => 'Create'],
            ['name' => 'assignment-edit', 'group' => 'Assignment', 'title' => 'Edit'],
            ['name' => 'assignment-delete', 'group' => 'Assignment', 'title' => 'Delete'],
            ['name' => 'assignment-marking', 'group' => 'Assignment', 'title' => 'Mark Ledger'],

            ['name' => 'content-view', 'group' => 'Content', 'title' => 'View'],
            ['name' => 'content-create', 'group' => 'Content', 'title' => 'Create'],
            ['name' => 'content-edit', 'group' => 'Content', 'title' => 'Edit'],
            ['name' => 'content-delete', 'group' => 'Content', 'title' => 'Delete'],

            ['name' => 'content-type-view', 'group' => 'Content Type', 'title' => 'View'],
            ['name' => 'content-type-create', 'group' => 'Content Type', 'title' => 'Create'],
            ['name' => 'content-type-edit', 'group' => 'Content Type', 'title' => 'Edit'],
            ['name' => 'content-type-delete', 'group' => 'Content Type', 'title' => 'Delete'],
            //*** Download Center Modules ***//



            //*** Fees Collection Modules ***//
            ['name' => 'fees-student-due', 'group' => 'Student Fees', 'title' => 'Fees Due'],
            ['name' => 'fees-student-quick-assign', 'group' => 'Student Fees', 'title' => 'Quick Assign'],
            ['name' => 'fees-student-quick-received', 'group' => 'Student Fees', 'title' => 'Quick Received'],
            ['name' => 'fees-student-report', 'group' => 'Student Fees', 'title' => 'Report'],
            ['name' => 'fees-student-action', 'group' => 'Student Fees', 'title' => 'Action'],
            ['name' => 'fees-student-print', 'group' => 'Student Fees', 'title' => 'Print'],

            ['name' => 'fees-receipt-view', 'group' => 'Fees Receipt', 'title' => 'Setting'],
            //*** Fees Collection Modules ***//



            //*** Fees Master Modules ***//
            ['name' => 'fees-master-create', 'group' => 'Assign Fees', 'title' => 'Manage'],
            ['name' => 'fees-master-view', 'group' => 'Assign Fees', 'title' => 'Report'],

            ['name' => 'fees-category-view', 'group' => 'Fees Type', 'title' => 'View'],
            ['name' => 'fees-category-create', 'group' => 'Fees Type', 'title' => 'Create'],
            ['name' => 'fees-category-edit', 'group' => 'Fees Type', 'title' => 'Edit'],
            ['name' => 'fees-category-delete', 'group' => 'Fees Type', 'title' => 'Delete'],
            //*** Fees Master Modules ***//



            //*** Fees Management Modules ***//
            ['name' => 'fees-discount-view', 'group' => 'Fees Discount', 'title' => 'View'],
            ['name' => 'fees-discount-create', 'group' => 'Fees Discount', 'title' => 'Create'],
            ['name' => 'fees-discount-edit', 'group' => 'Fees Discount', 'title' => 'Edit'],
            ['name' => 'fees-discount-delete', 'group' => 'Fees Discount', 'title' => 'Delete'],

            ['name' => 'fees-fine-view', 'group' => 'Fees Fine', 'title' => 'View'],
            ['name' => 'fees-fine-create', 'group' => 'Fees Fine', 'title' => 'Create'],
            ['name' => 'fees-fine-edit', 'group' => 'Fees Fine', 'title' => 'Edit'],
            ['name' => 'fees-fine-delete', 'group' => 'Fees Fine', 'title' => 'Delete'],
            //*** Fees Management Modules ***//



            //*** Staff Modules ***//
            ['name' => 'user-view', 'group' => 'Staff List', 'title' => 'View'],
            ['name' => 'user-create', 'group' => 'Staff List', 'title' => 'Create'],
            ['name' => 'user-edit', 'group' => 'Staff List', 'title' => 'Edit'],
            ['name' => 'user-delete', 'group' => 'Staff List', 'title' => 'Delete'],
            ['name' => 'user-import', 'group' => 'Staff List', 'title' => 'Import'],
            // ['name' => 'user-password-print', 'group' => 'Staff List', 'title' => 'Password Print'],
            ['name' => 'user-password-change', 'group' => 'Staff List', 'title' => 'Password Change'],
            //*** Staff Modules ***//



            //*** Staff Note Modules ***//
            ['name' => 'staff-note-view', 'group' => 'Staff Note', 'title' => 'View'],
            ['name' => 'staff-note-create', 'group' => 'Staff Note', 'title' => 'Create'],
            ['name' => 'staff-note-edit', 'group' => 'Staff Note', 'title' => 'Edit'],
            ['name' => 'staff-note-delete', 'group' => 'Staff Note', 'title' => 'Delete'],
            //*** Staff Note Modules ***//



            //*** Payroll Modules ***//
            ['name' => 'payroll-view', 'group' => 'Payroll', 'title' => 'View'],
            ['name' => 'payroll-action', 'group' => 'Payroll', 'title' => 'Action'],
            ['name' => 'payroll-report', 'group' => 'Payroll', 'title' => 'Report'],
            ['name' => 'payroll-print', 'group' => 'Payroll', 'title' => 'Print'],

            ['name' => 'pay-slip-setting-view', 'group' => 'Pay Slip', 'title' => 'Setting'],
            //*** Payroll Modules ***//



            //*** HR Modules ***//
            ['name' => 'work-shift-type-view', 'group' => 'Work Shift Type', 'title' => 'View'],
            ['name' => 'work-shift-type-create', 'group' => 'Work Shift Type', 'title' => 'Create'],
            ['name' => 'work-shift-type-edit', 'group' => 'Work Shift Type', 'title' => 'Edit'],
            ['name' => 'work-shift-type-delete', 'group' => 'Work Shift Type', 'title' => 'Delete'],

            ['name' => 'tax-setting-view', 'group' => 'Tax Setting', 'title' => 'View'],
            ['name' => 'tax-setting-create', 'group' => 'Tax Setting', 'title' => 'Create'],
            ['name' => 'tax-setting-edit', 'group' => 'Tax Setting', 'title' => 'Edit'],
            ['name' => 'tax-setting-delete', 'group' => 'Tax Setting', 'title' => 'Delete'],
            //*** HR Modules ***//



            //*** Department Modules ***//
            ['name' => 'designation-view', 'group' => 'Designation', 'title' => 'View'],
            ['name' => 'designation-create', 'group' => 'Designation', 'title' => 'Create'],
            ['name' => 'designation-edit', 'group' => 'Designation', 'title' => 'Edit'],
            ['name' => 'designation-delete', 'group' => 'Designation', 'title' => 'Delete'],

            ['name' => 'department-view', 'group' => 'Department', 'title' => 'View'],
            ['name' => 'department-create', 'group' => 'Department', 'title' => 'Create'],
            ['name' => 'department-edit', 'group' => 'Department', 'title' => 'Edit'],
            ['name' => 'department-delete', 'group' => 'Department', 'title' => 'Delete'],

            ['name' => 'allowance-type-view', 'group' => 'Allowance Type', 'title' => 'View'],
            ['name' => 'allowance-type-create', 'group' => 'Allowance Type', 'title' => 'Create'],
            ['name' => 'allowance-type-edit', 'group' => 'Allowance Type', 'title' => 'Edit'],
            ['name' => 'allowance-type-delete', 'group' => 'Allowance Type', 'title' => 'Delete'],

            ['name' => 'deduction-type-view', 'group' => 'Deduction Type', 'title' => 'View'],
            ['name' => 'deduction-type-create', 'group' => 'Deduction Type', 'title' => 'Create'],
            ['name' => 'deduction-type-edit', 'group' => 'Deduction Type', 'title' => 'Edit'],
            ['name' => 'deduction-type-delete', 'group' => 'Deduction Type', 'title' => 'Delete'],
            //*** Department Modules ***//



            //*** Staff Attendance Modules ***//
            ['name' => 'staff-daily-attendance-action', 'group' => 'Staff Daily Attendance', 'title' => 'Manage'],
            ['name' => 'staff-daily-attendance-report', 'group' => 'Staff Daily Attendance', 'title' => 'Report'],

            ['name' => 'staff-hourly-attendance-action', 'group' => 'Staff Hourly Attendance', 'title' => 'Manage'],
            ['name' => 'staff-hourly-attendance-report', 'group' => 'Staff Hourly Attendance', 'title' => 'Report'],
            //*** Staff Attendance Modules ***//



            //*** Staff Leave Modules ***//
            ['name' => 'staff-leave-view', 'group' => 'Staff Apply Leave', 'title' => 'View'],
            ['name' => 'staff-leave-create', 'group' => 'Staff Apply Leave', 'title' => 'Apply'],
            ['name' => 'staff-leave-delete', 'group' => 'Staff Apply Leave', 'title' => 'Delete'],

            ['name' => 'leave-type-view', 'group' => 'Leave Type', 'title' => 'View'],
            ['name' => 'leave-type-create', 'group' => 'Leave Type', 'title' => 'Create'],
            ['name' => 'leave-type-edit', 'group' => 'Leave Type', 'title' => 'Edit'],
            ['name' => 'leave-type-delete', 'group' => 'Leave Type', 'title' => 'Delete'],

            ['name' => 'staff-leave-manage-view', 'group' => 'Staff Leave Manage', 'title' => 'View'],
            ['name' => 'staff-leave-manage-edit', 'group' => 'Staff Leave Manage', 'title' => 'Action'],
            ['name' => 'staff-leave-manage-delete', 'group' => 'Staff Leave Manage', 'title' => 'Delete'],
            //*** Staff Leave Modules ***//



            //*** Income Modules ***//
            ['name' => 'income-view', 'group' => 'Income', 'title' => 'View'],
            ['name' => 'income-create', 'group' => 'Income', 'title' => 'Create'],
            ['name' => 'income-edit', 'group' => 'Income', 'title' => 'Edit'],
            ['name' => 'income-delete', 'group' => 'Income', 'title' => 'Delete'],

            ['name' => 'income-category-view', 'group' => 'Income Category', 'title' => 'View'],
            ['name' => 'income-category-create', 'group' => 'Income Category', 'title' => 'Create'],
            ['name' => 'income-category-edit', 'group' => 'Income Category', 'title' => 'Edit'],
            ['name' => 'income-category-delete', 'group' => 'Income Category', 'title' => 'Delete'],
            //*** Income Modules ***//



            //*** Expense Modules ***//
            ['name' => 'expense-view', 'group' => 'Expense', 'title' => 'View'],
            ['name' => 'expense-create', 'group' => 'Expense', 'title' => 'Create'],
            ['name' => 'expense-edit', 'group' => 'Expense', 'title' => 'Edit'],
            ['name' => 'expense-delete', 'group' => 'Expense', 'title' => 'Delete'],

            ['name' => 'expense-category-view', 'group' => 'Expense Category', 'title' => 'View'],
            ['name' => 'expense-category-create', 'group' => 'Expense Category', 'title' => 'Create'],
            ['name' => 'expense-category-edit', 'group' => 'Expense Category', 'title' => 'Edit'],
            ['name' => 'expense-category-delete', 'group' => 'Expense Category', 'title' => 'Delete'],

            ['name' => 'outcome-view', 'group' => 'Outcome Overview', 'title' => 'View'],
            //*** Expense Modules ***//



            //*** Notify Modules ***//
            ['name' => 'email-notify-view', 'group' => 'Send Email', 'title' => 'View'],
            ['name' => 'email-notify-create', 'group' => 'Send Email', 'title' => 'Send'],
            ['name' => 'email-notify-delete', 'group' => 'Send Email', 'title' => 'Delete'],

            ['name' => 'sms-notify-view', 'group' => 'Send SMS', 'title' => 'View'],
            ['name' => 'sms-notify-create', 'group' => 'Send SMS', 'title' => 'Send'],
            ['name' => 'sms-notify-delete', 'group' => 'Send SMS', 'title' => 'Delete'],
            //*** Notify Modules ***//



            //*** Event Modules ***//
            ['name' => 'event-view', 'group' => 'Event', 'title' => 'View'],
            ['name' => 'event-create', 'group' => 'Event', 'title' => 'Create'],
            ['name' => 'event-edit', 'group' => 'Event', 'title' => 'Edit'],
            ['name' => 'event-delete', 'group' => 'Event', 'title' => 'Delete'],

            ['name' => 'event-calendar', 'group' => 'Event', 'title' => 'Calendar'],
            //*** Event Modules ***//



            //*** Notice Modules ***//
            ['name' => 'notice-view', 'group' => 'Notice List', 'title' => 'View'],
            ['name' => 'notice-create', 'group' => 'Notice List', 'title' => 'Create'],
            ['name' => 'notice-edit', 'group' => 'Notice List', 'title' => 'Edit'],
            ['name' => 'notice-delete', 'group' => 'Notice List', 'title' => 'Delete'],

            ['name' => 'notice-category-view', 'group' => 'Notice Category', 'title' => 'View'],
            ['name' => 'notice-category-create', 'group' => 'Notice Category', 'title' => 'Create'],
            ['name' => 'notice-category-edit', 'group' => 'Notice Category', 'title' => 'Edit'],
            ['name' => 'notice-category-delete', 'group' => 'Notice Category', 'title' => 'Delete'],
            //*** Notice Modules ***//



            //*** Issue Return Modules ***//
            ['name' => 'book-issue-view', 'group' => 'Issue Book', 'title' => 'View'],
            ['name' => 'book-issue-action', 'group' => 'Issue Book', 'title' => 'Action'],
            ['name' => 'book-issue-delete', 'group' => 'Issue Book', 'title' => 'Delete'],
            //*** Issue Return Modules ***//



            //*** Library Member Modules ***//
            ['name' => 'library-member-view', 'group' => 'Library Member', 'title' => 'View'],
            ['name' => 'library-member-create', 'group' => 'Library Member', 'title' => 'Create'],
            ['name' => 'library-member-edit', 'group' => 'Library Member', 'title' => 'Edit'],
            ['name' => 'library-member-delete', 'group' => 'Library Member', 'title' => 'Delete'],
            ['name' => 'library-member-card', 'group' => 'Library Member', 'title' => 'Card Print'],

            ['name' => 'library-card-setting-view', 'group' => 'Library Card', 'title' => 'Setting'],
            //*** Library Member Modules ***//



            //*** Book Modules ***//
            ['name' => 'book-view', 'group' => 'Book List', 'title' => 'View'],
            ['name' => 'book-create', 'group' => 'Book List', 'title' => 'Create'],
            ['name' => 'book-edit', 'group' => 'Book List', 'title' => 'Edit'],
            ['name' => 'book-delete', 'group' => 'Book List', 'title' => 'Delete'],
            ['name' => 'book-import', 'group' => 'Book List', 'title' => 'Import'],
            ['name' => 'book-print', 'group' => 'Book List', 'title' => 'Token Print'],

            ['name' => 'book-request-view', 'group' => 'Book Request', 'title' => 'View'],
            ['name' => 'book-request-create', 'group' => 'Book Request', 'title' => 'Create'],
            ['name' => 'book-request-edit', 'group' => 'Book Request', 'title' => 'Edit'],
            ['name' => 'book-request-delete', 'group' => 'Book Request', 'title' => 'Delete'],

            ['name' => 'book-category-view', 'group' => 'Book Category', 'title' => 'View'],
            ['name' => 'book-category-create', 'group' => 'Book Category', 'title' => 'Create'],
            ['name' => 'book-category-edit', 'group' => 'Book Category', 'title' => 'Edit'],
            ['name' => 'book-category-delete', 'group' => 'Book Category', 'title' => 'Delete'],
            //*** Book Modules ***//



            //*** Inventory Modules ***//
            ['name' => 'item-issue-view', 'group' => 'Issue Item', 'title' => 'View'],
            ['name' => 'item-issue-action', 'group' => 'Issue Item', 'title' => 'Action'],
            ['name' => 'item-issue-delete', 'group' => 'Issue Item', 'title' => 'Delete'],

            ['name' => 'item-stock-view', 'group' => 'Item Stock', 'title' => 'View'],
            ['name' => 'item-stock-create', 'group' => 'Item Stock', 'title' => 'Create'],
            ['name' => 'item-stock-edit', 'group' => 'Item Stock', 'title' => 'Edit'],
            ['name' => 'item-stock-delete', 'group' => 'Item Stock', 'title' => 'Delete'],

            ['name' => 'item-view', 'group' => 'Item List', 'title' => 'View'],
            ['name' => 'item-create', 'group' => 'Item List', 'title' => 'Create'],
            ['name' => 'item-edit', 'group' => 'Item List', 'title' => 'Edit'],
            ['name' => 'item-delete', 'group' => 'Item List', 'title' => 'Delete'],

            ['name' => 'item-store-view', 'group' => 'Store', 'title' => 'View'],
            ['name' => 'item-store-create', 'group' => 'Store', 'title' => 'Create'],
            ['name' => 'item-store-edit', 'group' => 'Store', 'title' => 'Edit'],
            ['name' => 'item-store-delete', 'group' => 'Store', 'title' => 'Delete'],

            ['name' => 'item-supplier-view', 'group' => 'Supplier', 'title' => 'View'],
            ['name' => 'item-supplier-create', 'group' => 'Supplier', 'title' => 'Create'],
            ['name' => 'item-supplier-edit', 'group' => 'Supplier', 'title' => 'Edit'],
            ['name' => 'item-supplier-delete', 'group' => 'Supplier', 'title' => 'Delete'],

            ['name' => 'item-category-view', 'group' => 'Item Category', 'title' => 'View'],
            ['name' => 'item-category-create', 'group' => 'Item Category', 'title' => 'Create'],
            ['name' => 'item-category-edit', 'group' => 'Item Category', 'title' => 'Edit'],
            ['name' => 'item-category-delete', 'group' => 'Item Category', 'title' => 'Delete'],
            //*** Inventory Modules ***//



            //*** Hostel Modules ***//
            ['name' => 'hostel-member-view', 'group' => 'Hostel Member', 'title' => 'View'],
            ['name' => 'hostel-member-create', 'group' => 'Hostel Member', 'title' => 'Manage'],

            ['name' => 'hostel-room-view', 'group' => 'Hostel Room', 'title' => 'View'],
            ['name' => 'hostel-room-create', 'group' => 'Hostel Room', 'title' => 'Create'],
            ['name' => 'hostel-room-edit', 'group' => 'Hostel Room', 'title' => 'Edit'],
            ['name' => 'hostel-room-delete', 'group' => 'Hostel Room', 'title' => 'Delete'],

            ['name' => 'hostel-view', 'group' => 'Hostel', 'title' => 'View'],
            ['name' => 'hostel-create', 'group' => 'Hostel', 'title' => 'Create'],
            ['name' => 'hostel-edit', 'group' => 'Hostel', 'title' => 'Edit'],
            ['name' => 'hostel-delete', 'group' => 'Hostel', 'title' => 'Delete'],

            ['name' => 'room-type-view', 'group' => 'Room Type', 'title' => 'View'],
            ['name' => 'room-type-create', 'group' => 'Room Type', 'title' => 'Create'],
            ['name' => 'room-type-edit', 'group' => 'Room Type', 'title' => 'Edit'],
            ['name' => 'room-type-delete', 'group' => 'Room Type', 'title' => 'Delete'],
            //*** Hostel Modules ***//



            //*** Transport Modules ***//
            ['name' => 'transport-member-view', 'group' => 'Transport Member', 'title' => 'View'],
            ['name' => 'transport-member-create', 'group' => 'Transport Member', 'title' => 'Manage'],

            ['name' => 'transport-vehicle-view', 'group' => 'Transport Vehicle', 'title' => 'View'],
            ['name' => 'transport-vehicle-create', 'group' => 'Transport Vehicle', 'title' => 'Create'],
            ['name' => 'transport-vehicle-edit', 'group' => 'Transport Vehicle', 'title' => 'Edit'],
            ['name' => 'transport-vehicle-delete', 'group' => 'Transport Vehicle', 'title' => 'Delete'],

            ['name' => 'transport-route-view', 'group' => 'Transport Route', 'title' => 'View'],
            ['name' => 'transport-route-create', 'group' => 'Transport Route', 'title' => 'Create'],
            ['name' => 'transport-route-edit', 'group' => 'Transport Route', 'title' => 'Edit'],
            ['name' => 'transport-route-delete', 'group' => 'Transport Route', 'title' => 'Delete'],
            //*** Transport Modules ***//



            //*** Visitor Modules ***//
            ['name' => 'visitor-view', 'group' => 'Visitor', 'title' => 'View'],
            ['name' => 'visitor-create', 'group' => 'Visitor', 'title' => 'Create'],
            ['name' => 'visitor-edit', 'group' => 'Visitor', 'title' => 'Edit'],
            ['name' => 'visitor-delete', 'group' => 'Visitor', 'title' => 'Delete'],
            ['name' => 'visitor-print', 'group' => 'Visitor', 'title' => 'Token Print'],

            ['name' => 'visit-purpose-view', 'group' => 'Visit Purpose', 'title' => 'View'],
            ['name' => 'visit-purpose-create', 'group' => 'Visit Purpose', 'title' => 'Create'],
            ['name' => 'visit-purpose-edit', 'group' => 'Visit Purpose', 'title' => 'Edit'],
            ['name' => 'visit-purpose-delete', 'group' => 'Visit Purpose', 'title' => 'Delete'],

            ['name' => 'visitor-token-setting-view', 'group' => 'Visitor Token', 'title' => 'Setting'],
            //*** Visitor Modules ***//



            //*** Phone Log Modules ***//
            ['name' => 'phone-log-view', 'group' => 'Phone Log', 'title' => 'View'],
            ['name' => 'phone-log-create', 'group' => 'Phone Log', 'title' => 'Create'],
            ['name' => 'phone-log-edit', 'group' => 'Phone Log', 'title' => 'Edit'],
            ['name' => 'phone-log-delete', 'group' => 'Phone Log', 'title' => 'Delete'],
            //*** Phone Log Modules ***//



            //*** Enquiry Modules ***//
            ['name' => 'enquiry-view', 'group' => 'Enquiry', 'title' => 'View'],
            ['name' => 'enquiry-create', 'group' => 'Enquiry', 'title' => 'Create'],
            ['name' => 'enquiry-edit', 'group' => 'Enquiry', 'title' => 'Edit'],
            ['name' => 'enquiry-delete', 'group' => 'Enquiry', 'title' => 'Delete'],

            ['name' => 'enquiry-reference-view', 'group' => 'Enquiry Reference', 'title' => 'View'],
            ['name' => 'enquiry-reference-create', 'group' => 'Enquiry Reference', 'title' => 'Create'],
            ['name' => 'enquiry-reference-edit', 'group' => 'Enquiry Reference', 'title' => 'Edit'],
            ['name' => 'enquiry-reference-delete', 'group' => 'Enquiry Reference', 'title' => 'Delete'],

            ['name' => 'enquiry-source-view', 'group' => 'Enquiry Source', 'title' => 'View'],
            ['name' => 'enquiry-source-create', 'group' => 'Enquiry Source', 'title' => 'Create'],
            ['name' => 'enquiry-source-edit', 'group' => 'Enquiry Source', 'title' => 'Edit'],
            ['name' => 'enquiry-source-delete', 'group' => 'Enquiry Source', 'title' => 'Delete'],
            //*** Enquiry Modules ***//



            //*** Complain Modules ***//
            ['name' => 'complain-view', 'group' => 'Complain', 'title' => 'View'],
            ['name' => 'complain-create', 'group' => 'Complain', 'title' => 'Create'],
            ['name' => 'complain-edit', 'group' => 'Complain', 'title' => 'Edit'],
            ['name' => 'complain-delete', 'group' => 'Complain', 'title' => 'Delete'],

            ['name' => 'complain-type-view', 'group' => 'Complain Type', 'title' => 'View'],
            ['name' => 'complain-type-create', 'group' => 'Complain Type', 'title' => 'Create'],
            ['name' => 'complain-type-edit', 'group' => 'Complain Type', 'title' => 'Edit'],
            ['name' => 'complain-type-delete', 'group' => 'Complain Type', 'title' => 'Delete'],

            ['name' => 'complain-source-view', 'group' => 'Complain Source', 'title' => 'View'],
            ['name' => 'complain-source-create', 'group' => 'Complain Source', 'title' => 'Create'],
            ['name' => 'complain-source-edit', 'group' => 'Complain Source', 'title' => 'Edit'],
            ['name' => 'complain-source-delete', 'group' => 'Complain Source', 'title' => 'Delete'],
            //*** Complain Modules ***//



            //*** Postal Exchange Modules ***//
            ['name' => 'postal-exchange-view', 'group' => 'Postal Exchange', 'title' => 'View'],
            ['name' => 'postal-exchange-create', 'group' => 'Postal Exchange', 'title' => 'Create'],
            ['name' => 'postal-exchange-edit', 'group' => 'Postal Exchange', 'title' => 'Edit'],
            ['name' => 'postal-exchange-delete', 'group' => 'Postal Exchange', 'title' => 'Delete'],

            ['name' => 'postal-type-view', 'group' => 'Postal Type', 'title' => 'View'],
            ['name' => 'postal-type-create', 'group' => 'Postal Type', 'title' => 'Create'],
            ['name' => 'postal-type-edit', 'group' => 'Postal Type', 'title' => 'Edit'],
            ['name' => 'postal-type-delete', 'group' => 'Postal Type', 'title' => 'Delete'],
            //*** Postal Exchange Modules ***//



            //*** Meeting Modules ***//
            ['name' => 'meeting-view', 'group' => 'Meeting Schedule', 'title' => 'View'],
            ['name' => 'meeting-create', 'group' => 'Meeting Schedule', 'title' => 'Create'],
            ['name' => 'meeting-edit', 'group' => 'Meeting Schedule', 'title' => 'Edit'],
            ['name' => 'meeting-delete', 'group' => 'Meeting Schedule', 'title' => 'Delete'],

            ['name' => 'meeting-type-view', 'group' => 'Meeting Type', 'title' => 'View'],
            ['name' => 'meeting-type-create', 'group' => 'Meeting Type', 'title' => 'Create'],
            ['name' => 'meeting-type-edit', 'group' => 'Meeting Type', 'title' => 'Edit'],
            ['name' => 'meeting-type-delete', 'group' => 'Meeting Type', 'title' => 'Delete'],
            //*** Meeting Modules ***//



            //*** Marksheet Modules ***//
            ['name' => 'marksheet-view', 'group' => 'Marksheet', 'title' => 'View'],
            ['name' => 'marksheet-print', 'group' => 'Marksheet', 'title' => 'Print'],
            ['name' => 'marksheet-download', 'group' => 'Marksheet', 'title' => 'Download'],

            ['name' => 'marksheet-setting-view', 'group' => 'Marksheet', 'title' => 'Setting'],
            //*** Marksheet Modules ***//



            //*** Certificate Modules ***//
            ['name' => 'certificate-view', 'group' => 'Certificate', 'title' => 'View'],
            ['name' => 'certificate-create', 'group' => 'Certificate', 'title' => 'Genarate'],
            ['name' => 'certificate-edit', 'group' => 'Certificate', 'title' => 'Edit'],
            ['name' => 'certificate-print', 'group' => 'Certificate', 'title' => 'Print'],
            ['name' => 'certificate-download', 'group' => 'Certificate', 'title' => 'Download'],

            ['name' => 'certificate-template-view', 'group' => 'Certificate Template', 'title' => 'View'],
            ['name' => 'certificate-template-create', 'group' => 'Certificate Template', 'title' => 'Create'],
            ['name' => 'certificate-template-edit', 'group' => 'Certificate Template', 'title' => 'Edit'],
            ['name' => 'certificate-template-delete', 'group' => 'Certificate Template', 'title' => 'Delete'],
            //*** Certificate Modules ***//



            //*** Report Modules ***//
            ['name' => 'report-student-progress', 'group' => 'Reports', 'title' => 'Student Progress'],
            ['name' => 'report-subject-students', 'group' => 'Reports', 'title' => 'Course Students'],
            ['name' => 'report-student-attendance', 'group' => 'Reports', 'title' => 'Student Attendance'],
            ['name' => 'report-subject-attendance', 'group' => 'Reports', 'title' => 'Subject Attendance'],
            ['name' => 'report-collected-fees', 'group' => 'Reports', 'title' => 'Collected Fees'],
            ['name' => 'report-student-fees', 'group' => 'Reports', 'title' => 'Student Fees'],
            ['name' => 'report-salary-paid', 'group' => 'Reports', 'title' => 'Salary Paid'],
            ['name' => 'report-staff-leaves', 'group' => 'Reports', 'title' => 'Staff Leaves'],
            ['name' => 'report-income', 'group' => 'Reports', 'title' => 'Total Income'],
            ['name' => 'report-expense', 'group' => 'Reports', 'title' => 'Total Expense'],
            ['name' => 'report-library', 'group' => 'Reports', 'title' => 'Library History'],
            ['name' => 'report-book-return', 'group' => 'Reports', 'title' => 'Book Return Due'],
            ['name' => 'report-inventory', 'group' => 'Reports', 'title' => 'Inventory History'],
            ['name' => 'report-hostel', 'group' => 'Reports', 'title' => 'Hostel Members'],
            ['name' => 'report-transport', 'group' => 'Reports', 'title' => 'Transport Members'],
            //*** Report Modules ***//



            //*** Website Modules ***//
            ['name' => 'topbar-setting-view', 'group' => 'Contact Setting', 'title' => 'Manage'],

            ['name' => 'social-setting-view', 'group' => 'Social Setting', 'title' => 'Manage'],

            ['name' => 'slider-view', 'group' => 'Slider', 'title' => 'View'],
            ['name' => 'slider-create', 'group' => 'Slider', 'title' => 'Create'],
            ['name' => 'slider-edit', 'group' => 'Slider', 'title' => 'Edit'],
            ['name' => 'slider-delete', 'group' => 'Slider', 'title' => 'Delete'],

            ['name' => 'about-us-view', 'group' => 'About Us', 'title' => 'Manage'],

            ['name' => 'feature-view', 'group' => 'Feature', 'title' => 'View'],
            ['name' => 'feature-create', 'group' => 'Feature', 'title' => 'Create'],
            ['name' => 'feature-edit', 'group' => 'Feature', 'title' => 'Edit'],
            ['name' => 'feature-delete', 'group' => 'Feature', 'title' => 'Delete'],

            ['name' => 'course-view', 'group' => 'Course', 'title' => 'View'],
            ['name' => 'course-create', 'group' => 'Course', 'title' => 'Create'],
            ['name' => 'course-edit', 'group' => 'Course', 'title' => 'Edit'],
            ['name' => 'course-delete', 'group' => 'Course', 'title' => 'Delete'],

            ['name' => 'web-event-view', 'group' => 'Web Event', 'title' => 'View'],
            ['name' => 'web-event-create', 'group' => 'Web Event', 'title' => 'Create'],
            ['name' => 'web-event-edit', 'group' => 'Web Event', 'title' => 'Edit'],
            ['name' => 'web-event-delete', 'group' => 'Web Event', 'title' => 'Delete'],

            ['name' => 'news-view', 'group' => 'News', 'title' => 'View'],
            ['name' => 'news-create', 'group' => 'News', 'title' => 'Create'],
            ['name' => 'news-edit', 'group' => 'News', 'title' => 'Edit'],
            ['name' => 'news-delete', 'group' => 'News', 'title' => 'Delete'],

            ['name' => 'gallery-view', 'group' => 'Gallery', 'title' => 'View'],
            ['name' => 'gallery-create', 'group' => 'Gallery', 'title' => 'Create'],
            ['name' => 'gallery-edit', 'group' => 'Gallery', 'title' => 'Edit'],
            ['name' => 'gallery-delete', 'group' => 'Gallery', 'title' => 'Delete'],

            ['name' => 'faq-view', 'group' => 'Faq', 'title' => 'View'],
            ['name' => 'faq-create', 'group' => 'Faq', 'title' => 'Create'],
            ['name' => 'faq-edit', 'group' => 'Faq', 'title' => 'Edit'],
            ['name' => 'faq-delete', 'group' => 'Faq', 'title' => 'Delete'],

            ['name' => 'testimonial-view', 'group' => 'Testimonial', 'title' => 'View'],
            ['name' => 'testimonial-create', 'group' => 'Testimonial', 'title' => 'Create'],
            ['name' => 'testimonial-edit', 'group' => 'Testimonial', 'title' => 'Edit'],
            ['name' => 'testimonial-delete', 'group' => 'Testimonial', 'title' => 'Delete'],

            ['name' => 'page-view', 'group' => 'Footer Page', 'title' => 'View'],
            ['name' => 'page-create', 'group' => 'Footer Page', 'title' => 'Create'],
            ['name' => 'page-edit', 'group' => 'Footer Page', 'title' => 'Edit'],
            ['name' => 'page-delete', 'group' => 'Footer Page', 'title' => 'Delete'],

            ['name' => 'call-to-action-view', 'group' => 'Call To Action', 'title' => 'Manage'],

            ['name' => 'project-view', 'group' => 'Project', 'title' => 'View'],
            ['name' => 'project-create', 'group' => 'Project', 'title' => 'Create'],
            ['name' => 'project-edit', 'group' => 'Project', 'title' => 'Edit'],
            ['name' => 'project-delete', 'group' => 'Project', 'title' => 'Delete'],

            ['name' => 'leadership-team-view', 'group' => 'Leadership Team', 'title' => 'View'],
            ['name' => 'leadership-team-create', 'group' => 'Leadership Team', 'title' => 'Create'],
            ['name' => 'leadership-team-edit', 'group' => 'Leadership Team', 'title' => 'Edit'],
            ['name' => 'leadership-team-delete', 'group' => 'Leadership Team', 'title' => 'Delete'],

            ['name' => 'accreditation-view', 'group' => 'Accreditation', 'title' => 'View'],
            ['name' => 'accreditation-create', 'group' => 'Accreditation', 'title' => 'Create'],
            ['name' => 'accreditation-edit', 'group' => 'Accreditation', 'title' => 'Edit'],
            ['name' => 'accreditation-delete', 'group' => 'Accreditation', 'title' => 'Delete'],

            ['name' => 'history-timeline-view', 'group' => 'History Timeline', 'title' => 'View'],
            ['name' => 'history-timeline-create', 'group' => 'History Timeline', 'title' => 'Create'],
            ['name' => 'history-timeline-edit', 'group' => 'History Timeline', 'title' => 'Edit'],
            ['name' => 'history-timeline-delete', 'group' => 'History Timeline', 'title' => 'Delete'],

            ['name' => 'support-service-view', 'group' => 'Support Service', 'title' => 'View'],
            ['name' => 'support-service-create', 'group' => 'Support Service', 'title' => 'Create'],
            ['name' => 'support-service-edit', 'group' => 'Support Service', 'title' => 'Edit'],
            ['name' => 'support-service-delete', 'group' => 'Support Service', 'title' => 'Delete'],

            ['name' => 'admission-date-view', 'group' => 'Admission Date', 'title' => 'View'],
            ['name' => 'admission-date-create', 'group' => 'Admission Date', 'title' => 'Create'],
            ['name' => 'admission-date-edit', 'group' => 'Admission Date', 'title' => 'Edit'],
            ['name' => 'admission-date-delete', 'group' => 'Admission Date', 'title' => 'Delete'],
            //*** Website Modules ***//



            //*** Address Modules ***//
            ['name' => 'province-view', 'group' => 'State/Province', 'title' => 'View'],
            ['name' => 'province-create', 'group' => 'State/Province', 'title' => 'Create'],
            ['name' => 'province-edit', 'group' => 'State/Province', 'title' => 'Edit'],
            ['name' => 'province-delete', 'group' => 'State/Province', 'title' => 'Delete'],

            ['name' => 'district-view', 'group' => 'District/City', 'title' => 'View'],
            ['name' => 'district-create', 'group' => 'District/City', 'title' => 'Create'],
            ['name' => 'district-edit', 'group' => 'District/City', 'title' => 'Edit'],
            ['name' => 'district-delete', 'group' => 'District/City', 'title' => 'Delete'],
            //*** Address Modules ***//



            //*** Accounting Modules ***//
            ['name' => 'chart-of-accounts-view', 'group' => 'Accounting', 'title' => 'View Chart of Accounts'],
            ['name' => 'chart-of-accounts-create', 'group' => 'Accounting', 'title' => 'Create Chart of Accounts'],
            ['name' => 'chart-of-accounts-edit', 'group' => 'Accounting', 'title' => 'Edit Chart of Accounts'],
            ['name' => 'chart-of-accounts-delete', 'group' => 'Accounting', 'title' => 'Delete Chart of Accounts'],
            ['name' => 'journal-entry-view', 'group' => 'Accounting', 'title' => 'View Journal Entries'],
            ['name' => 'journal-entry-create', 'group' => 'Accounting', 'title' => 'Create Journal Entries'],
            ['name' => 'journal-entry-edit', 'group' => 'Accounting', 'title' => 'Edit Journal Entries'],
            ['name' => 'journal-entry-delete', 'group' => 'Accounting', 'title' => 'Delete Journal Entries'],
            ['name' => 'general-ledger-view', 'group' => 'Accounting', 'title' => 'View General Ledger'],
            ['name' => 'trial-balance-view', 'group' => 'Accounting', 'title' => 'View Trial Balance'],
            ['name' => 'accounting-period-view', 'group' => 'Accounting', 'title' => 'View Accounting Periods'],
            ['name' => 'accounting-period-create', 'group' => 'Accounting', 'title' => 'Create Accounting Periods'],
            ['name' => 'accounting-period-edit', 'group' => 'Accounting', 'title' => 'Edit Accounting Periods'],
            ['name' => 'accounting-report-view', 'group' => 'Accounting', 'title' => 'View Accounting Reports'],
            ['name' => 'budget-view', 'group' => 'Accounting', 'title' => 'View Budget'],
            ['name' => 'budget-create', 'group' => 'Accounting', 'title' => 'Create Budget'],
            ['name' => 'budget-edit', 'group' => 'Accounting', 'title' => 'Edit Budget'],
            ['name' => 'budget-delete', 'group' => 'Accounting', 'title' => 'Delete Budget'],
            ['name' => 'bank-reconciliation-list', 'group' => 'Accounting', 'title' => 'View Bank Reconciliation'],
            ['name' => 'bank-reconciliation-create', 'group' => 'Accounting', 'title' => 'Create Bank Reconciliation'],
            ['name' => 'fixed-asset-list', 'group' => 'Accounting', 'title' => 'View Fixed Assets'],
            ['name' => 'fixed-asset-create', 'group' => 'Accounting', 'title' => 'Create Fixed Assets'],
            ['name' => 'fixed-asset-edit', 'group' => 'Accounting', 'title' => 'Edit Fixed Assets'],
            ['name' => 'fixed-asset-delete', 'group' => 'Accounting', 'title' => 'Delete Fixed Assets'],
            ['name' => 'fixed-asset-category-list', 'group' => 'Accounting', 'title' => 'View Fixed Asset Categories'],
            ['name' => 'fixed-asset-category-create', 'group' => 'Accounting', 'title' => 'Create Fixed Asset Categories'],
            ['name' => 'fixed-asset-category-edit', 'group' => 'Accounting', 'title' => 'Edit Fixed Asset Categories'],
            ['name' => 'fixed-asset-category-delete', 'group' => 'Accounting', 'title' => 'Delete Fixed Asset Categories'],
            ['name' => 'recurring-entry-list', 'group' => 'Accounting', 'title' => 'View Recurring Entries'],
            ['name' => 'recurring-entry-create', 'group' => 'Accounting', 'title' => 'Create Recurring Entries'],
            ['name' => 'recurring-entry-edit', 'group' => 'Accounting', 'title' => 'Edit Recurring Entries'],
            ['name' => 'recurring-entry-delete', 'group' => 'Accounting', 'title' => 'Delete Recurring Entries'],
            ['name' => 'year-end-closing-list', 'group' => 'Accounting', 'title' => 'View Year End Closing'],
            ['name' => 'year-end-closing-create', 'group' => 'Accounting', 'title' => 'Create Year End Closing'],
            ['name' => 'transaction-mapping-view', 'group' => 'Accounting', 'title' => 'View Transaction Mapping'],
            ['name' => 'transaction-mapping-settings', 'group' => 'Accounting', 'title' => 'Manage Transaction Mapping Settings'],
            //*** Accounting Modules ***//



            //*** Language Modules ***//
            ['name' => 'language-view', 'group' => 'Language', 'title' => 'View'],
            ['name' => 'language-create', 'group' => 'Language', 'title' => 'Create'],
            ['name' => 'language-edit', 'group' => 'Language', 'title' => 'Edit'],
            ['name' => 'language-delete', 'group' => 'Language', 'title' => 'Delete'],

            ['name' => 'translations-view', 'group' => 'Translation', 'title' => 'View'],
            ['name' => 'translations-create', 'group' => 'Translation', 'title' => 'Create'],
            ['name' => 'translations-delete', 'group' => 'Translation', 'title' => 'Delete'],
            //*** Language Modules ***//



            //*** Platform Fee Modules ***//
            ['name' => 'platform-fee-view', 'group' => 'Platform Fee', 'title' => 'View'],
            ['name' => 'platform-fee-action', 'group' => 'Platform Fee', 'title' => 'Action'],
            ['name' => 'platform-fee-delete', 'group' => 'Platform Fee', 'title' => 'Delete'],
            //*** Platform Fee Modules ***//



            //*** E-Library Modules ***//
            ['name' => 'e-library-view', 'group' => 'E-Library', 'title' => 'View'],
            ['name' => 'e-library-create', 'group' => 'E-Library', 'title' => 'Create'],
            ['name' => 'e-library-edit', 'group' => 'E-Library', 'title' => 'Edit'],
            ['name' => 'e-library-delete', 'group' => 'E-Library', 'title' => 'Delete'],
            //*** E-Library Modules ***//



            //*** Setting Modules ***//
            ['name' => 'setting-view', 'group' => 'Setting', 'title' => 'General'],
            ['name' => 'setting-mail', 'group' => 'Setting', 'title' => 'Mail Setting'],
            ['name' => 'setting-sms', 'group' => 'Setting', 'title' => 'SMS Getaways'],
            ['name' => 'setting-payment', 'group' => 'Setting', 'title' => 'Payment Getaways'],

            ['name' => 'application-setting-view', 'group' => 'Application Setting', 'title' => 'Manage'],
            // ['name' => 'schedule-setting-view', 'group' => 'Fees Reminder', 'title' => 'Setting'],

            ['name' => 'religion-view', 'group' => 'Religion', 'title' => 'View'],
            ['name' => 'religion-create', 'group' => 'Religion', 'title' => 'Create'],
            ['name' => 'religion-edit', 'group' => 'Religion', 'title' => 'Edit'],
            ['name' => 'religion-delete', 'group' => 'Religion', 'title' => 'Delete'],

            ['name' => 'catholic-student-view', 'group' => 'Catholic Students', 'title' => 'View'],
            ['name' => 'catholic-student-edit', 'group' => 'Catholic Students', 'title' => 'Edit'],

            ['name' => 'role-view', 'group' => 'Role and Permissions', 'title' => 'View'],
            ['name' => 'role-create', 'group' => 'Role and Permissions', 'title' => 'Create'],
            ['name' => 'role-edit', 'group' => 'Role and Permissions', 'title' => 'Edit'],
            ['name' => 'role-delete', 'group' => 'Role and Permissions', 'title' => 'Delete'],

            ['name' => 'field-staff', 'group' => 'Field Setting', 'title' => 'Staff'],
            ['name' => 'field-student', 'group' => 'Field Setting', 'title' => 'Student'],
            ['name' => 'field-application', 'group' => 'Field Setting', 'title' => 'Application'],

            ['name' => 'student-panel-view', 'group' => 'Student Panel', 'title' => 'Manage'],

            ['name' => 'profile-view', 'group' => 'My Profile', 'title' => 'View'],
            ['name' => 'profile-edit', 'group' => 'My Profile', 'title' => 'Edit'],
            ['name' => 'profile-account', 'group' => 'My Profile', 'title' => 'Account'],
            //*** Setting Modules ***//

            //*** Audit Trail Modules ***//
            ['name' => 'audit-log-view', 'group' => 'Audit Trail', 'title' => 'View'],
            ['name' => 'audit-log-export', 'group' => 'Audit Trail', 'title' => 'Export'],
            //*** Audit Trail Modules ***//

            //*** Security Modules ***//
            ['name' => 'security-dashboard-view', 'group' => 'Security', 'title' => 'View Security Dashboard'],
            ['name' => 'security-users-view', 'group' => 'Security', 'title' => 'View Security Users'],
            ['name' => 'security-users-manage', 'group' => 'Security', 'title' => 'Manage Security Users'],
            ['name' => 'security-logs-view', 'group' => 'Security', 'title' => 'View Security Logs'],
            ['name' => 'security-logs-export', 'group' => 'Security', 'title' => 'Export Security Logs'],
            ['name' => 'security-blocked-ips-view', 'group' => 'Security', 'title' => 'View Blocked IPs'],
            ['name' => 'security-blocked-ips-manage', 'group' => 'Security', 'title' => 'Manage Blocked IPs'],
            ['name' => 'security-whitelist-view', 'group' => 'Security', 'title' => 'View IP Whitelist'],
            ['name' => 'security-whitelist-manage', 'group' => 'Security', 'title' => 'Manage IP Whitelist'],
            ['name' => 'security-settings-view', 'group' => 'Security', 'title' => 'View Security Settings'],
            ['name' => 'security-settings-edit', 'group' => 'Security', 'title' => 'Edit Security Settings'],
            //*** Security Modules ***//

            //*** Payment Verification Modules ***//
            ['name' => 'payment-receipt-verify', 'group' => 'Payment Verification', 'title' => 'Verify'],
            //*** Payment Verification Modules ***//
        ];
        foreach ($permissions as $permission) {
            $permission['guard_name'] = $permission['guard_name'] ?? 'web';
            Permission::updateOrCreate(
                ['name' => $permission['name'], 'guard_name' => $permission['guard_name']],
                $permission
            );
        }
    }
}
