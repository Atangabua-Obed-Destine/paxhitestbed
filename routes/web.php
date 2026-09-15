<?php

use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Session;

/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
|
| Here is where you can register web routes for your application. These
| routes are loaded by the RouteServiceProvider within a group which
| contains the "web" middleware group. Now create something great!
|
*/

// Web Routes
Route::middleware(['XSS'])->namespace('Web')->group(function () {
    
    Route::get('/maintenance', function () {
        return view('maintenance');   // resources/views/maintenance.blade.php
    })->name('maintenance');
    
    // Home Route
    Route::get('/', 'HomeController@index')->name('home');
    // About Route
    Route::get('/about', 'HomeController@about')->name('about');
    // Programs Route
    Route::get('/programs', 'HomeController@programs')->name('programs');
    Route::get('/programs/{slug}', 'HomeController@programDetail')->name('program.single');
    // Faculties Route
    Route::get('/faculties', 'HomeController@faculties')->name('faculties');
    Route::get('/faculties/{slug}', 'HomeController@facultyDetail')->name('faculty.single');
    // Admissions Route
    Route::get('/admissions', 'HomeController@admissions')->name('admissions');
    // Projects Routes
    Route::get('/projects', 'HomeController@projects')->name('projects');
    Route::get('/projects/{id}', 'HomeController@projectDetail')->name('project.detail');
    // Campus Life Route
    Route::get('/campus-life', 'HomeController@campusLife')->name('campus-life');
    // Course Route
    Route::get('/course', 'CourseController@index')->name('course');
    Route::get('/course/{slug}', 'CourseController@show')->name('course.single');
    // Event Route
    Route::get('/event', 'EventController@index')->name('event');
    Route::get('/event/{id}/{slug}', 'EventController@show')->name('event.single');
    // Faq Route
    Route::get('/faq', 'FaqController@index')->name('faq');
    // Sitemap Route
    Route::get('/sitemap.xml', 'SitemapController@index')->name('sitemap');
    // Gallery Route
    Route::get('/gallery', 'GalleryController@index')->name('gallery');
    // News Route
    Route::get('/news', 'NewsController@index')->name('news');
    Route::get('/news/{id}/{slug}', 'NewsController@show')->name('news.single');
    // Page Route
    Route::get('/page/{slug}', 'PageController@show')->name('page.single');
    
    // Resources & Downloads Route (Public)
    Route::get('/downloads', 'ResourceController@index')->name('resources');
    Route::get('/download/{resource}', 'ResourceController@download')->name('resource.download');

    // ID Card Verification Route (Public)
    Route::get('/verify-student/{student_id}', 'StudentVerificationController@verify')->name('student.verify');
    
    // Legacy redirect for old QR codes that used /student/verify/ URL
    Route::get('/student/verify/{student_id}', function($student_id) {
        return redirect()->route('student.verify', $student_id);
    });

    // Application Portal Routes
    Route::middleware('guest:applicant')->group(function () {
        // The admissions front door. Every "Apply Online" button on the public
        // site lands here, so it must serve the new applicant first and the
        // returning one second.
        Route::get('application/start', 'ApplicationController@startPage')->name('application.start');

        Route::get('application/login', 'ApplicationController@loginForm')->name('application.login');
        Route::post('application/login', 'ApplicationController@authenticate')->name('application.authenticate');
        Route::get('application/register', 'ApplicationController@registerForm')->name('application.register');
        Route::post('application/register', 'ApplicationController@register')->name('application.register.store');
        
        // Applicant Forgot Password Routes
        Route::get('application/password/forgot', 'ApplicationController@showForgotPasswordForm')->name('application.password.request');
        Route::post('application/password/email', 'ApplicationController@sendResetLinkEmail')->name('application.password.email');
        Route::get('application/password/reset/{token}/{email}', 'ApplicationController@showResetForm')->name('application.password.reset');
        Route::post('application/password/reset', 'ApplicationController@resetPassword')->name('application.password.update');
    });

    // Outside every applicant middleware on purpose: an administrator must be
    // able to leave impersonation even if the account was disabled meanwhile.
    Route::get('application/leave-impersonation', 'ApplicationController@leaveImpersonation')->name('application.leave-impersonation');

    // applicant.active signs out an account that was disabled while signed in,
    // on its very next request — refusing the login alone would not.
    Route::middleware(['auth:applicant', 'applicant.active'])->group(function () {
        // Hub + intake
        Route::get('application/dashboard', 'ApplicationController@dashboard')->name('application.dashboard');
        Route::get('application', 'ApplicationController@index')->name('application.index'); // legacy → dashboard
        Route::get('application/create', 'ApplicationController@create')->name('application.create');
        Route::post('application', 'ApplicationController@store')->name('application.store');
        Route::post('application/logout', 'ApplicationController@logout')->name('application.logout');

        // Per-application (owned)
        Route::get('application/{application}/edit', 'ApplicationController@edit')->name('application.edit');
        Route::post('application/{application}', 'ApplicationController@update')->name('application.update');
        Route::post('application/{application}/save-draft', 'ApplicationController@saveDraft')->name('application.save-draft');
        Route::post('application/{application}/resubmit-documents', 'ApplicationController@resubmitDocuments')->name('application.resubmit-documents');
        Route::get('application/{application}/timeline', 'ApplicationController@timeline')->name('application.timeline');
        Route::get('application/{application}/print', 'ApplicationController@printPreview')->name('application.print');
        Route::get('application/{application}/readiness', 'ApplicationController@readiness')->name('application.readiness');
        Route::post('application/{application}/admission-fee/payment/upload', 'ApplicationController@uploadAdmissionFeeReceipt')->name('application.admission-fee.upload');
    });


    // SetCookie Route
    Route::get('/set-cookie', 'HomeController@setCookie')->name('setCookie');
});


// Ajax Filter Routes
Route::middleware(['XSS'])->group(function () {

    // Filter Routes
    Route::post('filter-district', 'AddressController@filterDistrict')->name('filter-district');
    Route::post('filter-batch', 'FilterController@filterBatch')->name('filter-batch');
    Route::post('filter-program', 'FilterController@filterProgram')->name('filter-program');
    Route::post('filter-academic-department', 'FilterController@filterAcademicDepartment')->name('filter-academic-department');
    Route::post('filter-session', 'FilterController@filterSession')->name('filter-session');
    Route::post('filter-semester', 'FilterController@filterSemester')->name('filter-semester');
    Route::post('filter-section', 'FilterController@filterSection')->name('filter-section');
    Route::post('filter-subject', 'FilterController@filterSubject')->name('filter-subject');
    Route::post('filter-enroll-subject', 'FilterController@filterEnrollSubject')->name('filter-enroll-subject');
    Route::post('filter-student-subject', 'FilterController@filterStudentSubject')->name('filter-student-subject');
    Route::post('filter-techer-subject', 'FilterController@filterTecherSubject')->name('filter-techer-subject');
    Route::post('filter-item', 'InventoryController@filterItem')->name('filter-item');
    Route::post('filter-quantity', 'InventoryController@filterQuantity')->name('filter-quantity');
    Route::post('filter-department', 'InventoryController@filterDepartment')->name('filter-department');
    Route::post('filter-room', 'HostelController@filterRoom')->name('filter-room');
    Route::post('filter-vehicle', 'TransportController@filterVehicle')->name('filter-vehicle');
    
    // AJAX Filter Routes for Exam Schedule Overview
    Route::get('admin/ajax/session-semester-years/{session}', 'FilterController@sessionSemesterYears')->name('ajax.session-semester-years');
    Route::get('admin/ajax/session-year-semesters/{session}/{year}', 'FilterController@sessionYearSemesters')->name('ajax.session-year-semesters');
    Route::get('admin/ajax/faculty-programs/{faculty}', 'FilterController@facultyPrograms')->name('ajax.faculty-programs');

});


// Set Lang Version
Route::get('locale/language/{locale}', function ($locale) {

    Session::put('locale', $locale);

    App::setLocale($locale);

    return redirect()->back();

})->name('version');


// Auth Routes
Route::middleware(['XSS'])->prefix('admin')->group(function () {
    // Auth::routes();
    Auth::routes(['register' => false]);
    
    // 2FA Routes
    Route::get('2fa/verify', 'Auth\LoginController@show2FAVerify')->name('admin.2fa.verify');
    Route::post('2fa/verify', 'Auth\LoginController@verify2FA')->name('admin.2fa.verify.submit');
    Route::post('2fa/resend', 'Auth\LoginController@resend2FACode')->name('admin.2fa.resend');
});


// Verify Purchase
Route::middleware(['XSS'])->group(function () {

    Route::get('verify-purchase', 'PurchaseVerificationController@index')->name('verify');
    Route::post('verify-purchase', 'PurchaseVerificationController@verify')->name('verify-purchase');

});

// Public Receipt Verification (No Authentication Required)
Route::middleware(['XSS'])->group(function () {
    Route::get('verify-receipt/{receipt_number}', 'Admin\FeesStudentController@verify')->name('verify-receipt');
    Route::get('verify-staff/{staff_identifier}', 'Admin\StaffIdCardController@verify')->name('verify-staff');
    Route::get('verify-form-a3/{code}', 'Admin\StudentFormA3Controller@verify')->name('verify-form-a3');
    Route::get('verify-admission-fee/{receipt}', 'Admin\AdmissionFeesReportController@publicVerify')->name('verify-admission-fee');
    Route::get('verify-transcript/{code}', 'Admin\MarksheetController@verify')->name('verify-transcript');
});


// Payment Routes
Route::middleware(['XSS'])->name('payment.')->namespace('Payment')->prefix('payment')->group(function () {

    // Paypal Routes
    // Route::get('paypal', 'PaypalController@index')->name('paypal.index');
    Route::post('paypal/process', 'PaypalController@process')->name('paypal.process');
    Route::get('paypal/success', 'PaypalController@paymentSuccess')->name('paypal.success');
    Route::get('paypal/cancel', 'PaypalController@paymentCancel')->name('paypal.cancel');

    // Stripe Routes
    // Route::get('stripe', 'StripeController@index')->name('stripe.index');
    Route::post('stripe/process', 'StripeController@process')->name('stripe.process');

    // Razorpay Routes
    // Route::get('razorpay', 'RazorpayController@index')->name('razorpay.index');
    Route::post('razorpay/process', 'RazorpayController@process')->name('razorpay.process');

    // Paystack Routes
    // Route::get('paystack', 'PaystackController@index')->name('paystack.index');
    Route::post('paystack/process', 'PaystackController@redirectToGateway')->name('paystack.process');
    Route::get('paystack/callback', 'PaystackController@handleGatewayCallback')->name('paystack.callback');

    // Flutterwave Routes
    // Route::get('flutterwave', 'FlutterwaveController@index')->name('flutterwave.index');
    Route::post('flutterwave/process', 'FlutterwaveController@process')->name('flutterwave.process');
    Route::get('flutterwave/callback', 'FlutterwaveController@callback')->name('flutterwave.callback');

    // Skrill Routes
    // Route::get('skrill', 'SkrillController@index')->name('skrill.index');
    Route::get('skrill/process', 'SkrillController@makePayment')->name('skrill.process');
    Route::get('skrill/completed', 'SkrillController@paymentCompleted')->name('skrill.completed');
    Route::get('skrill/cancelled', 'SkrillController@paymentCancelled')->name('skrill.cancelled');

    // MTN Mobile Money (Cameroon) Routes
    Route::post('momo/mtn/initiate', 'MtnMomoController@initiate')->name('momo.mtn.initiate');
    Route::get('momo/mtn/status/{reference}', 'MtnMomoController@status')->name('momo.mtn.status');
    Route::post('momo/mtn/webhook', 'MtnMomoController@webhook')->name('momo.mtn.webhook')->withoutMiddleware(['XSS']);
    Route::post('momo/mtn/sandbox-mark-paid', 'MtnMomoController@sandboxMarkPaid')->name('momo.mtn.sandbox-mark-paid');

    // Orange Money (Cameroon) Routes
    Route::post('momo/orange/initiate', 'OrangeMomoController@initiate')->name('momo.orange.initiate');
    Route::get('momo/orange/status/{reference}', 'OrangeMomoController@status')->name('momo.orange.status');
    Route::get('momo/orange/return/{reference}', 'OrangeMomoController@return')->name('momo.orange.return');
    Route::post('momo/orange/webhook', 'OrangeMomoController@webhook')->name('momo.orange.webhook')->withoutMiddleware(['XSS']);

});


// Admin Routes
Route::middleware(['auth:web', 'XSS', 'license'])->name('admin.')->namespace('Admin')->prefix('admin')->group(function () {

    // Dashboard Route
    Route::get('/', 'DashboardController@index')->name('dashboard.index');
    Route::get('dashboard', 'DashboardController@index');

    // Editor Image Upload (for TinyMCE paste/drag-drop)
    Route::post('editor/upload-image', 'EditorImageUploadController@upload')->name('editor.upload-image');

    // Student Routes
    // The admissions board report, for the applications matching the list's filters.
    Route::get('admission/application-report/pdf', 'ApplicationReportController@pdf')->name('application.report.pdf');
    Route::get('admission/application-report/excel', 'ApplicationReportController@excel')->name('application.report.excel');
    Route::get('admission/application/{application}/preview', 'ApplicationController@preview')->name('application.preview');
    Route::resource('admission/application', 'ApplicationController');
    Route::post('admission/application/{application}/status-update', 'ApplicationController@storeStatusUpdate')->name('application.status-update');

    // Admission approvals. Each step's own permission is checked in
    // ApplicationApprovalService against the step being decided, not here.
    Route::post('admission/application/{application}/approval/approve', 'ApplicationApprovalController@approve')->name('application.approval.approve');
    Route::post('admission/application/{application}/approval/reject', 'ApplicationApprovalController@reject')->name('application.approval.reject');
    Route::post('admission/application/{application}/approval/return', 'ApplicationApprovalController@returnToStep')->name('application.approval.return');
    Route::get('admission/application/{application}/acceptance-letter/download', 'ApplicationController@downloadAcceptanceLetter')->name('application.acceptance-letter.download');
    Route::post('admission/application/{application}/acceptance-letter/resend', 'ApplicationController@resendAcceptanceLetter')->name('application.acceptance-letter.resend');

    // Applicant accounts — the logins behind applications.
    Route::get('admission/applicant', 'ApplicantController@index')->name('applicant.index');
    Route::put('admission/applicant/{applicant}', 'ApplicantController@update')->name('applicant.update');
    Route::post('admission/applicant/{applicant}/password', 'ApplicantController@passwordChange')->name('applicant.password');
    Route::post('admission/applicant/{applicant}/toggle', 'ApplicantController@toggle')->name('applicant.toggle');
    Route::post('admission/applicant/{applicant}/send-reset-link', 'ApplicantController@sendResetLink')->name('applicant.send-reset-link');
    Route::post('admission/applicant/{applicant}/impersonate', 'ApplicantController@impersonate')->name('applicant.impersonate');
    
    // Admission Fee Configuration
    Route::get('admission/fee-config', 'AdmissionFeeConfigController@index')->name('admission-fee-config.index');
    Route::post('admission/fee-config', 'AdmissionFeeConfigController@update')->name('admission-fee-config.update');
    
    Route::post('admission/student-generate-id', 'StudentController@generateId')->name('student.generate-id');
    Route::resource('admission/student', 'StudentController');
    Route::get('admission/student/{id}/impersonate', 'StudentController@impersonate')->name('student.impersonate');
    Route::get('admission/student-card/{id}', 'StudentController@card')->name('student.card');
    // Route::get('admission/student-status/{id}', 'StudentController@status')->name('student.status');
    Route::post('admission/student-enroll-status/{enrollment}', 'StudentController@toggleEnrollStatus')->name('student.toggle-enroll-status');
    Route::post('admission/student-send-password/{id}', 'StudentController@sendPassword')->name('student.send-password');
    Route::get('admission/student-print-password/{id}', 'StudentController@printPassword')->name('student.print-password');
    Route::post('admission/student-password-change', 'StudentController@passwordChange')->name('student-password-change');
    Route::get('admission/student-import', 'StudentController@import')->name('student.import');
    Route::post('admission/student-import-store', 'StudentController@importStore')->name('student.import.store');
    Route::get('admission/student-password-multiprint', 'StudentController@multiPrintPassword')->name('student.password-multiprint');

    // Student Form A2 (Admin)
    Route::get('admission/student-form-a2', 'StudentFormA2Controller@index')->name('student-form-a2.index');
    Route::get('admission/student-form-a2/{enrollment}/preview', 'StudentFormA2Controller@preview')->name('student-form-a2.preview');
    Route::get('admission/student-form-a2/{enrollment}/download', 'StudentFormA2Controller@download')->name('student-form-a2.download');
    Route::post('admission/student-form-a2/bulk', 'StudentFormA2Controller@bulk')->name('student-form-a2.bulk');

    // Student Form A3 (Admin)
    Route::get('admission/student-form-a3', 'StudentFormA3Controller@index')->name('student-form-a3.index');
    Route::get('admission/student-form-a3/{id}/preview', 'StudentFormA3Controller@preview')->name('student-form-a3.preview');
    Route::get('admission/student-form-a3/{id}/download', 'StudentFormA3Controller@download')->name('student-form-a3.download');
    Route::post('admission/student-form-a3/bulk', 'StudentFormA3Controller@bulk')->name('student-form-a3.bulk');
    Route::post('admission/student-form-a3/{id}/toggle-result-access', 'StudentFormA3Controller@toggleResultAccess')->name('student-form-a3.toggle-result-access');

    // Student Archive Routes
    Route::get('student-archive', 'StudentArchiveController@index')->name('student-archive.index');
    Route::get('student-archive/{studentArchive}', 'StudentArchiveController@show')->name('student-archive.show');
    Route::get('student-archive/{studentArchive}/edit', 'StudentArchiveController@edit')->name('student-archive.edit');
    Route::put('student-archive/{studentArchive}', 'StudentArchiveController@update')->name('student-archive.update');
    Route::post('student-archive-password-change', 'StudentArchiveController@passwordChange')->name('student-archive.password-change');

    // Admission Routes
    Route::resource('admission/student-transfer-out', 'StudentTransferOutController');
    Route::resource('admission/student-transfer-in', 'StudentTransferInController');
    Route::resource('admission/status-type', 'StatusTypeController');
    Route::resource('admission/id-card', 'StudentIdCardController');
    Route::get('admission/id-card-print/{id}', 'StudentIdCardController@print')->name('id-card.print');
    Route::get('admission/id-card-multiprint', 'StudentIdCardController@multiPrint')->name('id-card.multiprint');
    Route::post('admission/id-card-update-photo/{id}', 'StudentIdCardController@updatePhoto')->name('id-card.update-photo');
    Route::get('admission/id-card-download/{id}', 'StudentIdCardController@download')->name('id-card.download');
    Route::post('admission/id-card-download-zip', 'StudentIdCardController@downloadZip')->name('id-card.download-zip');
    Route::resource('admission/id-card-setting', 'StudentIdCardSettingController');



    // Student Attendance Routes
    Route::get('student-attendance/scanner', 'StudentAttendanceController@scanner')->name('student-attendance.scanner');
    Route::post('student-attendance/scan', 'StudentAttendanceController@scan')->name('student-attendance.scan');
    Route::resource('student-attendance', 'StudentAttendanceController');
    Route::get('student-attendance-report', 'StudentAttendanceController@report')->name('student-attendance.report');
    Route::get('student-attendance-import', 'StudentAttendanceController@import')->name('student-attendance.import');
    Route::post('student-attendance-import-store', 'StudentAttendanceController@importStore')->name('student-attendance.import.store');
    Route::get('student-attendance-download-template', 'StudentAttendanceController@downloadClassList')->name('student-attendance.download-template');
    
    // Bulk Attendance Migration Routes
    Route::get('student-attendance-bulk-migration', 'StudentAttendanceController@bulkMigration')->name('student-attendance.bulk-migration');
    Route::post('student-attendance-bulk-migration-store', 'StudentAttendanceController@bulkMigrationStore')->name('student-attendance.bulk-migration.store');

    // Class Session Tracking Routes (Integrated Kiosk + Logbook)
    Route::get('class-session', 'ClassSessionController@index')->name('class-session.index');
    Route::get('class-session/kiosk', 'ClassSessionController@kiosk')->name('class-session.kiosk');
    Route::get('class-session/{id}', 'ClassSessionController@show')->name('class-session.show');
    Route::get('class-session/{id}/details', 'ClassSessionController@getDetails')->name('class-session.details');
    Route::post('class-session/start', 'ClassSessionController@startSession')->name('class-session.start');
    Route::post('class-session/end', 'ClassSessionController@endSession')->name('class-session.end');
    Route::post('class-session/cancel', 'ClassSessionController@cancelSession')->name('class-session.cancel');
    Route::post('class-session/create-extra', 'ClassSessionController@createExtraClass')->name('class-session.create-extra');
    Route::post('class-session/scan', 'ClassSessionController@scan')->name('class-session.scan');
    Route::post('class-session/scan-clock-out', 'ClassSessionController@scanClockOut')->name('class-session.scan-clock-out');
    Route::post('class-session/approve-early-clockout', 'ClassSessionController@approveEarlyClockOut')->name('class-session.approve-early-clockout');
    Route::post('class-session/update-logbook', 'ClassSessionController@updateLogbook')->name('class-session.update-logbook');
    Route::post('class-session/update-class-rep', 'ClassSessionController@updateClassRep')->name('class-session.update-class-rep');
    Route::post('class-session/toggle-delegation', 'ClassSessionController@toggleDelegation')->name('class-session.toggle-delegation');
    Route::get('class-session/{id}/students', 'ClassSessionController@getStudents')->name('class-session.students');
    Route::get('class-session/{id}/stats', 'ClassSessionController@getStats')->name('class-session.stats');
    Route::get('class-session/{id}/activity', 'ClassSessionController@getActivity')->name('class-session.activity');
    Route::post('class-session/send-message', 'ClassSessionController@sendMessage')->name('class-session.send-message');
    Route::post('class-session/answer-question', 'ClassSessionController@answerQuestion')->name('class-session.answer-question');

    // Class Session & Kiosk Attendance Settings
    Route::get('attendance-settings', 'AttendanceSettingController@index')->name('attendance-settings.index');
    Route::post('attendance-settings', 'AttendanceSettingController@update')->name('attendance-settings.update');
    Route::post('attendance-settings/reset', 'AttendanceSettingController@reset')->name('attendance-settings.reset');

    // Class Hub Reports (HOD/Admin)
    Route::get('class-hub-reports', 'ClassHubReportController@index')->name('class-hub-reports.index');
    Route::get('class-hub-reports/session/{classSession}', 'ClassHubReportController@sessionDetail')->name('class-hub-reports.session');
    Route::get('class-hub-reports/export', 'ClassHubReportController@export')->name('class-hub-reports.export');

    // Student Leave Manage
    Route::resource('student-leave-manage', 'StudentLeaveManagementController');
    Route::post('student-leave-manage-status/{id}', 'StudentLeaveManagementController@status')->name('student-leave-manage.status');

    // Student Enroll Routes
    Route::resource('student/student-note', 'StudentNoteController');
    Route::post('student/single-enroll/validate-program-swap', 'StudentSingleEnrollController@validateProgramSwap')->name('single-enroll.validate-swap');
    Route::resource('student/single-enroll', 'StudentSingleEnrollController');
    Route::resource('student/group-enroll', 'StudentGroupEnrollController');
    Route::resource('student/subject-adddrop', 'SubjectAddDropController');
    Route::post('student/subject-adddrop/drop', 'SubjectAddDropController@drop')->name('subject-adddrop.drop');
    Route::resource('student/course-complete', 'CourseCompleteController');
    Route::resource('student/student-alumni', 'StudentAlumniController');
    Route::get('student/max-credit-config', 'MaxCreditConfigController@index')->name('max-credit-config.index');
    Route::post('student/max-credit-config', 'MaxCreditConfigController@store')->name('max-credit-config.store');
    Route::put('student/max-credit-config/{maxCreditConfig}', 'MaxCreditConfigController@update')->name('max-credit-config.update');
    Route::delete('student/max-credit-config/{maxCreditConfig}', 'MaxCreditConfigController@destroy')->name('max-credit-config.destroy');



    // Academic Routes
    Route::get('academic/health-status', 'AcademicHealthController@index')->name('academic-health.index');
    Route::get('academic/health-status/excel', 'AcademicHealthController@export')->name('academic-health.excel');
    Route::resource('academic/faculty', 'FacultyController');
    Route::resource('academic/sector', 'SectorController');
    Route::resource('academic/academic-department', 'AcademicDepartmentController');
    // Institution letterhead, reused by every printable document.
    Route::get('academic/letterhead', 'LetterheadController@index')->name('letterhead.index');
    Route::post('academic/letterhead', 'LetterheadController@update')->name('letterhead.update');
    Route::get('academic/letterhead/preview', 'LetterheadController@preview')->name('letterhead.preview');

    Route::get('academic/degree-type/{degree_type}/form-config', 'DegreeTypeController@formConfig')->name('degree-type.form-config');
    Route::post('academic/degree-type/{degree_type}/form-config', 'DegreeTypeController@saveFormConfig')->name('degree-type.form-config.save');
    Route::get('academic/degree-type/{degree_type}/acceptance-letter/preview', 'DegreeTypeController@previewAcceptanceLetter')->name('degree-type.acceptance-letter.preview');
    Route::get('academic/degree-type/{degree_type}/blank-form/download', 'DegreeTypeController@downloadBlankForm')->name('degree-type.blank-form.download');
    Route::resource('academic/degree-type', 'DegreeTypeController');
    Route::resource('academic/program', 'ProgramController');
    Route::resource('academic/batch', 'BatchController');
    Route::resource('academic/session', 'SessionController');
    Route::get('academic/session-current/{id}', 'SessionController@current')->name('session.current');
    Route::get('academic/session-toggle-applications/{id}', 'SessionController@toggleApplications')->name('session.toggle-applications');
    Route::resource('academic/semester', 'SemesterController');
    Route::resource('academic/section', 'SectionController');
    Route::resource('academic/room', 'ClassRoomController');
    Route::resource('academic/subject', 'SubjectController');
    Route::get('academic/subject-import', 'SubjectController@import')->name('subject.import');
    Route::post('academic/subject-import-store', 'SubjectController@importStore')->name('subject.import.store');
    Route::resource('academic/enroll-subject', 'EnrollSubjectController');



    // Routine Routes
    Route::resource('routine/class-routine', 'ClassRoutineController');
    Route::get('routine/class-routine-teacher', 'ClassRoutineController@teacher')->name('class-routine.teacher');
    Route::get('routine/class-routine-joint', 'ClassRoutineController@jointCreate')->name('class-routine.joint');
    Route::post('routine/class-routine-joint', 'ClassRoutineController@jointStore')->name('class-routine.joint.store');
    Route::post('routine/class-routine/print', 'ClassRoutineController@print')->name('class-routine.print');
    Route::resource('routine/exam-routine', 'ExamRoutineController');
    Route::get('routine/exam-routine-joint', 'ExamRoutineController@jointCreate')->name('exam-routine.joint');
    Route::post('routine/exam-routine-joint', 'ExamRoutineController@jointStore')->name('exam-routine.joint.store');
    Route::post('routine/exam-routine/print', 'ExamRoutineController@print')->name('exam-routine.print');
    Route::get('routine/exam-schedule-overview', 'ExamRoutineController@scheduleOverview')->name('exam-routine.schedule-overview');
    Route::get('routine/routine-setting/class', 'RoutineSettingController@class')->name('routine-setting.class');
    Route::get('routine/routine-setting/exam', 'RoutineSettingController@exam')->name('routine-setting.exam');
    Route::post('routine/routine-setting/store', 'RoutineSettingController@store')->name('routine-setting.store');



    // Exam Routes
    Route::get('exam/exam-attendance/check-status', 'ExamAttendanceController@checkStatus')->name('exam-attendance.check-status');
    Route::get('exam/exam-attendance-print', 'ExamAttendanceController@printSheet')->name('exam-attendance.print');
    Route::resource('exam/exam-attendance', 'ExamAttendanceController');
    Route::post('exam/exam-attendance/unlock', 'ExamAttendanceController@unlock')->name('exam-attendance.unlock');
    Route::post('exam/exam-attendance/bulk-unlock', 'ExamAttendanceController@bulkUnlock')->name('exam-attendance.bulk-unlock');
    Route::get('exam/exam-attendance-import', 'ExamAttendanceController@import')->name('exam-attendance.import');
    Route::post('exam/exam-attendance-import-store', 'ExamAttendanceController@importStore')->name('exam-attendance.import.store');
    Route::get('exam/attendance-eligibility', 'ExamAttendanceSettingController@index')->name('exam-attendance-settings.index');
    Route::post('exam/attendance-eligibility', 'ExamAttendanceSettingController@update')->name('exam-attendance-settings.update');
    Route::get('exam/student-exam-config', 'StudentExamConfigController@index')->name('student-exam-config.index');
    Route::post('exam/student-exam-config', 'StudentExamConfigController@store')->name('student-exam-config.store');
    Route::get('exam/exam-marking/check-status', 'ExamMarkingController@checkStatus')->name('exam-marking.check-status');
    Route::post('exam/exam-marking/attendance-migration', 'ExamMarkingController@attendanceMigration')->name('exam-marking.attendance-migration');
    Route::resource('exam/exam-marking', 'ExamMarkingController');
    Route::post('exam/exam-marking/unlock', 'ExamMarkingController@unlock')->name('exam-marking.unlock');
    Route::post('exam/exam-marking/bulk-unlock', 'ExamMarkingController@bulkUnlock')->name('exam-marking.bulk-unlock');
    Route::get('exam/exam-result', 'ExamMarkingController@result')->name('exam-result');
    Route::post('exam/subject-marking/autosave', 'SubjectMarkingController@autosave')->name('subject-marking.autosave');
    Route::get('exam/subject-marking/{subject_marking}/history', 'SubjectMarkingController@history')->name('subject-marking.history');
    Route::resource('exam/subject-marking', 'SubjectMarkingController');
    Route::post('exam/subject-marking/{subject_marking}/transition', 'SubjectMarkingController@transition')->name('subject-marking.transition');
    Route::post('exam/subject-marking-bulk-transition', 'SubjectMarkingController@bulkTransition')->name('subject-marking.bulk-transition');
    Route::post('exam/subject-marking-bulk-unpublish', 'SubjectMarkingController@bulkUnpublishStudent')->name('subject-marking.bulk-unpublish');
    Route::post('exam/subject-marking/{subject_marking}/unpublish', 'SubjectMarkingController@unpublishStudent')->name('subject-marking.unpublish');
    Route::post('exam/subject-marking/{subject_marking}/republish', 'SubjectMarkingController@republishStudent')->name('subject-marking.republish');
    Route::get('exam/subject-result', 'SubjectMarkingController@result')->name('subject-result');
    
    // Exam Publishing Routes
    Route::get('exam/exam-publishing', 'ExamPublishingController@index')->name('exam-publishing.index');
    Route::get('exam/exam-publishing/download-pdf', 'ExamPublishingController@downloadPdf')->name('exam-publishing.download-pdf');
    Route::get('exam/exam-publishing/download-marks-pdf', 'ExamPublishingController@downloadMarksPdf')->name('exam-publishing.download-marks-pdf');
    Route::post('exam/exam-publishing/export-draft-results', 'ExamPublishingController@exportDraftResultsSummary')->name('exam-publishing.export-draft-results');
    Route::post('exam/exam-publishing/{exam_publishing_state}/transition', 'ExamPublishingController@transition')->name('exam-publishing.transition');
    Route::post('exam/exam-publishing/bulk-transition', 'ExamPublishingController@bulkTransition')->name('exam-publishing.bulk-transition');
    Route::post('exam/exam-publishing/bulk-transition-multi', 'ExamPublishingController@bulkTransitionMulti')->name('exam-publishing.bulk-transition-multi');
    Route::get('exam/exam-publishing/{exam_publishing_state}/history', 'ExamPublishingController@history')->name('exam-publishing.history');
    Route::get('exam/resit-requests', 'ResitRequestController@index')->name('resit-requests.index');
    Route::get('exam/resit-requests/sync-stuck', 'ResitRequestController@syncStuckPreview')->name('resit-requests.sync-stuck');
    Route::post('exam/resit-requests/sync-stuck', 'ResitRequestController@syncStuckExecute')->name('resit-requests.sync-stuck.execute');
    Route::post('exam/resit-requests/{resit_request}/transition', 'ResitRequestController@transition')->name('resit-requests.transition');
    Route::post('exam/resit-requests/{resit_request}/i-grade', 'ResitRequestController@iGrade')->name('resit-requests.i-grade');
    Route::post('exam/resit-requests/{resit_request}/cancel-resit', 'ResitRequestController@cancelResit')->name('resit-requests.cancel-resit');
    Route::get('exam/resit-settings', 'ResitSettingController@edit')->name('resit-settings.edit');
    Route::put('exam/resit-settings', 'ResitSettingController@update')->name('resit-settings.update');
    Route::resource('exam/exam-type', 'ExamTypeController');
    Route::resource('exam/grade', 'GradeController');
    Route::resource('exam/result-contribution', 'ResultContributionController');
    Route::resource('exam/admit-card', 'AdmitCardController');
    Route::get('exam/admit-card-print/{id}', 'AdmitCardController@print')->name('admit-card.print');
    Route::get('exam/admit-card-multiprint', 'AdmitCardController@multiPrint')->name('admit-card.multiprint');
    Route::get('exam/admit-card-download/{id}', 'AdmitCardController@download')->name('admit-card.download');
    Route::resource('exam/admit-setting', 'AdmitCardSettingController');

    // Results Summary Routes
    Route::get('exam/results-summary', 'ResultsSummaryController@index')->name('results-summary.index');
    Route::get('exam/results-summary/department-report', 'ResultsSummaryController@departmentReport')->name('results-summary.department-report');
    Route::get('exam/results-summary/faculty-report', 'ResultsSummaryController@facultyReport')->name('results-summary.faculty-report');
    Route::get('exam/results-summary/course-marksheet', 'ResultsSummaryController@courseMarkSheet')->name('results-summary.course-marksheet');
    Route::get('exam/results-summary/student-results-summary', 'ResultsSummaryController@studentResultsSummary')->name('results-summary.student-results-summary');
    Route::post('exam/results-summary/export-department', 'ResultsSummaryController@exportDepartment')->name('results-summary.export-department');
    Route::post('exam/results-summary/export-faculty', 'ResultsSummaryController@exportFaculty')->name('results-summary.export-faculty');
    Route::post('exam/results-summary/export-course-marksheet', 'ResultsSummaryController@exportCourseMarkSheet')->name('results-summary.export-course-marksheet');
    Route::post('exam/results-summary/export-student-results-summary', 'ResultsSummaryController@exportStudentResultsSummary')->name('results-summary.export-student-results-summary');

    // Senate Deliberation Routes
    Route::get('exam/senate-deliberation', 'SenateDeliberationController@index')->name('senate-deliberation.index');
    Route::get('exam/senate-deliberation/academic-standings', 'SenateDeliberationController@academicStandings')->name('senate-deliberation.academic-standings');
    Route::post('exam/senate-deliberation/classify', 'SenateDeliberationController@classify')->name('senate-deliberation.classify');
    Route::get('exam/senate-deliberation/deliberations', 'SenateDeliberationController@deliberations')->name('senate-deliberation.deliberations');
    Route::get('exam/senate-deliberation/results-preview', 'SenateDeliberationController@resultsPreview')->name('senate-deliberation.results-preview');
    Route::get('exam/senate-deliberation/results-preview/student-matrix', 'SenateDeliberationController@studentMatrix')->name('senate-deliberation.student-matrix');
    Route::get('exam/senate-deliberation/results-preview/export-excel', 'SenateDeliberationController@exportResultsPreview')->name('senate-deliberation.export-results-preview');
    Route::post('exam/senate-deliberation/store', 'SenateDeliberationController@storeDeliberation')->name('senate-deliberation.store');
    Route::get('exam/senate-deliberation/{id}', 'SenateDeliberationController@showDeliberation')->name('senate-deliberation.show');
    Route::put('exam/senate-deliberation/{id}', 'SenateDeliberationController@updateDeliberation')->name('senate-deliberation.update');
    Route::post('exam/senate-deliberation/{deliberation_id}/program-decision', 'SenateDeliberationController@programDecision')->name('senate-deliberation.program-decision');
    Route::post('exam/senate-deliberation/{deliberation_id}/add-signature', 'SenateDeliberationController@addSignature')->name('senate-deliberation.add-signature');
    Route::delete('exam/senate-deliberation/signature/{signature_id}', 'SenateDeliberationController@removeSignature')->name('senate-deliberation.remove-signature');
    Route::post('exam/senate-deliberation/export-pdf', 'SenateDeliberationController@exportPdf')->name('senate-deliberation.export-pdf');
    Route::post('exam/senate-deliberation/export-excel', 'SenateDeliberationController@exportExcel')->name('senate-deliberation.export-excel');

    // Assignment Routes
    Route::resource('download/assignment', 'AssignmentController');
    Route::post('download/assignment-marking', 'AssignmentController@marking')->name('assignment.marking');
    Route::get('download/assignment-export/{id}', 'AssignmentController@export')->name('assignment.export');
    Route::post('download/assignment-import', 'AssignmentController@import')->name('assignment.import');

    // Content Routes
    Route::resource('download/content', 'ContentController');
    Route::resource('download/content-type', 'ContentTypeController');



    // Fees Collection Student
    Route::get('fees-student', 'FeesStudentController@index')->name('fees-student.index');
    Route::post('fees-student-pay', 'FeesStudentController@pay')->name('fees-student.pay');
    Route::post('fees-student-unpay/{id}', 'FeesStudentController@unpay')->name('fees-student.unpay');
    Route::post('fees-student-cancel/{id}', 'FeesStudentController@cancel')->name('fees-student.cancel');
    Route::get('fees-student-report', 'FeesStudentController@report')->name('fees-student.report');
    Route::get('fees-student-print/{id}', 'FeesStudentController@print')->name('fees-student.print');

    // Admission Fees Report (applicant-scoped; separate from student fees report)
    Route::prefix('admission-fees-report')->name('admission-fees-report.')->group(function () {
        // Reading the report, taking money and removing a charge are separate
        // acts and are granted separately. Until these existed the screen had no
        // permission check at all.
        Route::get('/', 'AdmissionFeesReportController@index')
            ->middleware('permission:admission-fees-report-view')->name('index');
        Route::get('receipt/{receipt}', 'AdmissionFeesReportController@receipt')
            ->middleware('permission:admission-fees-report-view')->name('receipt');
        Route::post('{fee}/walk-in', 'AdmissionFeesReportController@recordWalkIn')
            ->middleware('permission:admission-fees-report-walk-in')->name('walk-in');
        Route::post('{fee}/delete', 'AdmissionFeesReportController@destroyFee')
            ->middleware('permission:admission-fees-report-delete')->name('delete');
    });
    Route::get('fees-student-multiprint', 'FeesStudentController@multiPrint')->name('fees-student.multiprint');

    // Quick Collection Student
    Route::get('fees-student-quick-received', 'FeesStudentController@quickReceived')->name('fees-student.quick.received');
    Route::post('fees-student-quick-received', 'FeesStudentController@quickReceivedStore')->name('fees-student.quick.received.store');
    Route::get('fees-student-quick-assign', 'FeesStudentController@quickAssign')->name('fees-student.quick.assign');
    Route::post('fees-student-quick-assign', 'FeesStudentController@quickAssignStore')->name('fees-student.quick.assign.store');

    // Student Credits / Overpayment Management
    Route::get('student-credits', 'StudentCreditController@index')->name('student-credits.index');
    Route::get('student-credits/{id}', 'StudentCreditController@show')->name('student-credits.show');
    Route::post('student-credits/{id}/request-refund', 'StudentCreditController@requestRefund')->name('student-credits.request-refund');
    Route::post('student-credits/{id}/approve-refund', 'StudentCreditController@approveRefund')->name('student-credits.approve-refund');
    Route::post('student-credits/{id}/reject-refund', 'StudentCreditController@rejectRefund')->name('student-credits.reject-refund');
    Route::post('student-credits/{id}/process-refund', 'StudentCreditController@processRefund')->name('student-credits.process-refund');
    Route::post('student-credits/{id}/apply-to-fee', 'StudentCreditController@applyToFee')->name('student-credits.apply-to-fee');

    // Fees Routes
    Route::resource('fees-master', 'FeesMasterController');
    Route::resource('fees-discount', 'FeesDiscountController');
    Route::resource('fees-fine', 'FeesFineController');
    Route::resource('fees-category', 'FeesCategoryController');
    Route::resource('fees-receipt', 'ReceiptSettingController');

    // Universal Fee Assignments History (canonical ledger across all sources).
    Route::get('fee-assignments-history', 'FeeAssignmentsHistoryController@index')->name('fee-assignments-history.index');
    Route::delete('fee-assignments-history/{id}', 'FeeAssignmentsHistoryController@destroy')->name('fee-assignments-history.destroy');
    Route::get('fee-assignments-history/{id}/transfer-targets', 'FeeAssignmentsHistoryController@transferTargets')->name('fee-assignments-history.transfer-targets');
    Route::post('fee-assignments-history/{id}/transfer', 'FeeAssignmentsHistoryController@transfer')->name('fee-assignments-history.transfer');
    
    // Program Semester Fee Configuration Routes
    Route::get('program-semester-fee/get-programs', 'ProgramSemesterFeeController@getPrograms')->name('program-semester-fee.get-programs');
    Route::get('program-semester-fee/get-semester-types', 'ProgramSemesterFeeController@getSemesterTypes')->name('program-semester-fee.get-semester-types');
    Route::get('program-semester-fee/get-semesters', 'ProgramSemesterFeeController@getSemesters')->name('program-semester-fee.get-semesters');
    Route::get('program-semester-fee/get-fee-categories', 'ProgramSemesterFeeController@getFeeCategories')->name('program-semester-fee.get-fee-categories');
    Route::resource('program-semester-fee', 'ProgramSemesterFeeController');

    // Payment Plan Routes (specific routes must come BEFORE resource routes)
    Route::get('payment-plan/get-student-fees', 'PaymentPlanController@getStudentFees')->name('payment-plan.get-student-fees');
    Route::post('payment-plan/process-payment', 'PaymentPlanController@processPayment')->name('payment-plan.process-payment');
    Route::post('payment-plan/{id}/cancel', 'PaymentPlanController@cancel')->name('payment-plan.cancel');
    Route::resource('payment-plan', 'PaymentPlanController');

    // Installment Payment Verification Routes
    Route::get('installment-payment-verification', 'InstallmentPaymentVerificationController@index')->name('installment-payment-verification.index');
    Route::get('installment-payment-verification/{id}', 'InstallmentPaymentVerificationController@show')->name('installment-payment-verification.show');
    Route::post('installment-payment-verification/{id}/approve', 'InstallmentPaymentVerificationController@approve')->name('installment-payment-verification.approve');
    Route::post('installment-payment-verification/{id}/reject', 'InstallmentPaymentVerificationController@reject')->name('installment-payment-verification.reject');

    // The rows of the Income & Expenditure sheet. Declared before budget/{id}
    // for the same reason as budget-sheet below.
    // The Bursar's cash analysis book, generated from money already recorded.
    Route::get('daybook', 'DaybookController@index')->name('daybook.index');
    Route::get('daybook/month/{month}', 'MonthLinkController@daybook')->name('daybook.month');
    Route::get('daybook/summary', 'DaybookController@summary')->name('daybook.summary');
    Route::get('daybook/analysis', 'DaybookController@analysis')->name('daybook.analysis');
    Route::get('daybook/pdf', 'DaybookController@pdf')->name('daybook.pdf');
    Route::get('daybook/excel', 'DaybookController@excel')->name('daybook.excel');

    Route::get('budget-line', 'BudgetLineController@index')->name('budget-line.index');
    Route::post('budget-line/store', 'BudgetLineController@store')->name('budget-line.store');
    Route::post('budget-line/{id}/update', 'BudgetLineController@update')->name('budget-line.update');
    Route::post('budget-line/reorder', 'BudgetLineController@reorder')->name('budget-line.reorder');
    Route::post('budget-line/auto-sort', 'BudgetLineController@autoSort')->name('budget-line.auto-sort');
    Route::post('budget-line/{id}/category', 'BudgetLineController@storeCategory')->name('budget-line.category');
    Route::post('budget-line/{id}/link', 'BudgetLineController@linkCategory')->name('budget-line.link');
    Route::post('budget-line/{id}/toggle', 'BudgetLineController@toggle')->name('budget-line.toggle');
    Route::post('budget-line/{id}/delete', 'BudgetLineController@destroy')->name('budget-line.delete');

    // Income & Expenditure sheet. Declared before the budget routes so
    // "budget-sheet" is never swallowed by budget/{id}.
    Route::get('budget-sheet', 'BudgetSheetController@index')->name('budget-sheet.index');
    Route::post('budget-sheet/store', 'BudgetSheetController@store')->name('budget-sheet.store');
    /*
     * Month-addressable entry points, for links arriving from EdutrustPay.
     *
     * Declared BEFORE budget-sheet/{id} so "month" is never swallowed as an id.
     * Guarded by the same permissions as the screens they land on, so they add
     * no visibility that typing the URL by hand would not.
     */
    Route::get('budget-sheet/month/{month}', 'MonthLinkController@budgetSheet')->name('budget-sheet.month');

    Route::get('budget-sheet/{id}/pdf', 'BudgetSheetController@exportPdf')->name('budget-sheet.pdf');
    Route::get('budget-sheet/{id}/excel', 'BudgetSheetController@exportExcel')->name('budget-sheet.excel');
    Route::get('budget-sheet/{id}', 'BudgetSheetController@show')->name('budget-sheet.show');
    Route::post('budget-sheet/{id}/figures', 'BudgetSheetController@saveFigures')->name('budget-sheet.figures');
    Route::post('budget-sheet/{id}/forecast', 'BudgetSheetController@forecast')->name('budget-sheet.forecast');
    Route::post('budget-sheet/{id}/period', 'BudgetSheetController@updatePeriod')->name('budget-sheet.period');
    Route::post('budget-sheet/{id}/delete', 'BudgetSheetController@destroy')->name('budget-sheet.delete');
    Route::post('budget-sheet/{id}/submit', 'BudgetSheetController@submit')->name('budget-sheet.submit');
    Route::post('budget-sheet/{id}/approve', 'BudgetSheetController@approve')->name('budget-sheet.approve');
    Route::post('budget-sheet/{id}/activate', 'BudgetSheetController@activate')->name('budget-sheet.activate');
    Route::post('budget-sheet/{id}/close', 'BudgetSheetController@close')->name('budget-sheet.close');

    // Budget Routes
    Route::get('budget', 'BudgetController@index')->name('budget.index');
    Route::get('budget/create', 'BudgetController@create')->name('budget.create');
    Route::post('budget/store', 'BudgetController@store')->name('budget.store');
    Route::get('budget/{id}', 'BudgetController@show')->name('budget.show');
    Route::get('budget/{id}/edit', 'BudgetController@edit')->name('budget.edit');
    Route::post('budget/{id}/update', 'BudgetController@update')->name('budget.update');
    Route::post('budget/{id}/delete', 'BudgetController@destroy')->name('budget.delete');
    Route::post('budget/{id}/submit', 'BudgetController@submitForApproval')->name('budget.submit');
    Route::post('budget/{id}/approve', 'BudgetController@approve')->name('budget.approve');
    Route::post('budget/{id}/activate', 'BudgetController@activate')->name('budget.activate');
    Route::post('budget/{id}/close', 'BudgetController@close')->name('budget.close');
    Route::post('budget/{id}/cancel', 'BudgetController@cancel')->name('budget.cancel');
    Route::post('budget/{id}/revise', 'BudgetController@revise')->name('budget.revise');
    Route::get('budget/{id}/allocations-data', 'BudgetController@getAllocationsData')->name('budget.allocations-data');
    
    // Budget Allocation Routes
    Route::get('budget/{budgetId}/allocations', 'BudgetAllocationController@index')->name('budget.allocations');
    Route::post('budget/{budgetId}/allocations/store', 'BudgetAllocationController@store')->name('budget.allocations.store');
    Route::post('budget/{budgetId}/allocations/{id}/update', 'BudgetAllocationController@update')->name('budget.allocations.update');
    Route::post('budget/{budgetId}/allocations/{id}/delete', 'BudgetAllocationController@destroy')->name('budget.allocations.delete');
    
    // Budget Dashboard Routes
    Route::get('budget-dashboard', 'BudgetDashboardController@index')->name('budget.dashboard');
    Route::get('budget-dashboard/{id}/summary', 'BudgetDashboardController@getBudgetSummary')->name('budget.dashboard.summary');
    
    // Budget Report Routes
    Route::get('budget-reports/performance', 'BudgetReportController@performance')->name('budget.reports.performance');
    Route::get('budget-reports/variance', 'BudgetReportController@variance')->name('budget.reports.variance');
    Route::get('budget-reports/department', 'BudgetReportController@department')->name('budget.reports.department');
    Route::get('budget-reports/cashflow', 'BudgetReportController@cashflow')->name('budget.reports.cashflow');

    // Payment Account Routes
    Route::get('payment-account', 'PaymentAccountController@index')->name('payment-account.index');
    Route::get('payment-account/create', 'PaymentAccountController@create')->name('payment-account.create');
    Route::post('payment-account/store', 'PaymentAccountController@store')->name('payment-account.store');
    Route::get('payment-account/{id}/edit', 'PaymentAccountController@edit')->name('payment-account.edit');
    Route::put('payment-account/{id}/update', 'PaymentAccountController@update')->name('payment-account.update');
    Route::delete('payment-account/{id}/delete', 'PaymentAccountController@destroy')->name('payment-account.destroy');
    Route::get('payment-account/{id}/account-book', 'PaymentAccountController@accountBook')->name('payment-account.account-book');
    Route::get('payment-account/{id}/deposit', 'PaymentAccountController@deposit')->name('payment-account.deposit');
    Route::post('payment-account/{id}/deposit', 'PaymentAccountController@deposit')->name('payment-account.deposit.post');
    Route::get('payment-account/{id}/withdraw', 'PaymentAccountController@withdraw')->name('payment-account.withdraw');
    Route::post('payment-account/{id}/withdraw', 'PaymentAccountController@withdraw')->name('payment-account.withdraw.post');
    Route::get('payment-account/{id}/transactions-data', 'PaymentAccountController@getTransactions')->name('payment-account.transactions-data');
    
    // Payment Account Transaction Routes (Edit/Delete individual transactions)
    Route::get('payment-account/transaction/{id}/edit', 'PaymentAccountController@editTransaction')->name('payment-account.transaction.edit');
    Route::put('payment-account/transaction/{id}/update', 'PaymentAccountController@updateTransaction')->name('payment-account.transaction.update');
    Route::delete('payment-account/transaction/{id}/delete', 'PaymentAccountController@deleteTransaction')->name('payment-account.transaction.delete');
    
    // Payment Account Transfer Routes
    Route::get('payment-account-transfer', 'PaymentAccountTransferController@index')->name('payment-account-transfer.index');
    Route::get('payment-account-transfer/create', 'PaymentAccountTransferController@create')->name('payment-account-transfer.create');
    Route::post('payment-account-transfer/store', 'PaymentAccountTransferController@store')->name('payment-account-transfer.store');
    Route::get('payment-account-transfer/{id}/edit', 'PaymentAccountTransferController@edit')->name('payment-account-transfer.edit');
    Route::put('payment-account-transfer/{id}/update', 'PaymentAccountTransferController@update')->name('payment-account-transfer.update');
    Route::delete('payment-account-transfer/{id}/delete', 'PaymentAccountTransferController@destroy')->name('payment-account-transfer.destroy');

    // Payment Account Report Routes
    Route::get('payment-account-report/cashflow', 'PaymentAccountReportController@cashflow')->name('payment-account-report.cashflow');
    Route::get('payment-account-report/statement/{id}', 'PaymentAccountReportController@accountStatement')->name('payment-account-report.statement');
    Route::get('payment-account-report/summary', 'PaymentAccountReportController@summary')->name('payment-account-report.summary');
    Route::get('payment-account-report/unlinked', 'PaymentAccountReportController@unlinkedTransactions')->name('payment-account-report.unlinked');
    Route::post('payment-account-report/link', 'PaymentAccountReportController@linkTransaction')->name('payment-account-report.link');

    // Accounting Module Routes - OHADA System
    // Chart of Accounts
    Route::resource('chart-of-accounts', 'ChartOfAccountController');
    Route::post('chart-of-accounts/{id}/toggle-status', 'ChartOfAccountController@toggleStatus')->name('chart-of-accounts.toggle-status');
    Route::get('chart-of-accounts-by-class', 'ChartOfAccountController@getByClass')->name('chart-of-accounts.by-class');
    
    // Fiscal Years
    Route::resource('fiscal-years', 'FiscalYearController');
    Route::post('fiscal-years/{id}/close', 'FiscalYearController@close')->name('fiscal-years.close');
    Route::post('fiscal-years/{id}/reopen', 'FiscalYearController@reopen')->name('fiscal-years.reopen');
    Route::post('fiscal-years/{id}/set-active', 'FiscalYearController@setActive')->name('fiscal-years.set-active');
    Route::post('fiscal-years/{id}/generate-periods', 'FiscalYearController@generatePeriods')->name('fiscal-years.generate-periods');
    
    // Journal Entries
    Route::resource('journal-entries', 'JournalEntryController');
    Route::get('journal-entries-trash', 'JournalEntryController@trash')->name('journal-entries.trash');
    Route::post('journal-entries/{id}/restore', 'JournalEntryController@restore')->name('journal-entries.restore');
    Route::delete('journal-entries/{id}/force-delete', 'JournalEntryController@forceDelete')->name('journal-entries.force-delete');
    Route::post('journal-entries/{id}/post', 'JournalEntryController@post')->name('journal-entries.post');
    Route::post('journal-entries/{id}/unpost', 'JournalEntryController@unpost')->name('journal-entries.unpost');
    Route::post('journal-entries/{id}/duplicate', 'JournalEntryController@duplicate')->name('journal-entries.duplicate');
    
    // General Ledger
    Route::get('general-ledger', 'GeneralLedgerController@index')->name('general-ledger.index');
    Route::get('general-ledger/account/{id}', 'GeneralLedgerController@account')->name('general-ledger.account');
    Route::get('general-ledger/class/{classNumber}', 'GeneralLedgerController@byClass')->name('general-ledger.by-class');
    Route::get('general-ledger/trial-balance', 'GeneralLedgerController@trialBalance')->name('general-ledger.trial-balance');
    Route::get('general-ledger/balance-sheet', 'GeneralLedgerController@balanceSheet')->name('general-ledger.balance-sheet');
    Route::get('general-ledger/income-statement', 'GeneralLedgerController@incomeStatement')->name('general-ledger.income-statement');
    Route::get('general-ledger/account/{id}/export-pdf', 'GeneralLedgerController@exportAccountPDF')->name('general-ledger.export-pdf');
    Route::get('general-ledger/trial-balance/export-excel', 'GeneralLedgerController@exportTrialBalanceExcel')->name('general-ledger.export-excel');
    Route::get('general-ledger/balance-sheet/export-pdf', 'GeneralLedgerController@exportBalanceSheetPDF')->name('general-ledger.balance-sheet-pdf');
    Route::get('general-ledger/balance-sheet/export-excel', 'GeneralLedgerController@exportBalanceSheetExcel')->name('general-ledger.balance-sheet-excel');
    Route::get('general-ledger/income-statement/export-pdf', 'GeneralLedgerController@exportIncomeStatementPDF')->name('general-ledger.income-statement-pdf');
    Route::get('general-ledger/income-statement/export-excel', 'GeneralLedgerController@exportIncomeStatementExcel')->name('general-ledger.income-statement-excel');

    // Account Mappings
    Route::get('accounting/mappings/settings', 'AccountMappingController@settings')->name('accounting.mappings.settings');
    Route::post('accounting/mappings/save-default', 'AccountMappingController@saveDefaultMappings')->name('accounting.mappings.save-default');
    Route::get('accounting/mappings/transactions', 'AccountMappingController@transactionsList')->name('accounting.mappings.transactions');
    Route::post('accounting/mappings/map-transaction', 'AccountMappingController@mapTransaction')->name('accounting.mappings.map-transaction');
    Route::put('accounting/mappings/{mappingId}', 'AccountMappingController@updateMapping')->name('accounting.mappings.update');
    Route::post('accounting/mappings/auto-map', 'AccountMappingController@autoMap')->name('accounting.mappings.auto-map');
    Route::post('accounting/mappings/bulk-sync', 'AccountMappingController@bulkSync')->name('accounting.mappings.bulk-sync');

    // Fixed Assets & Depreciation
    Route::resource('fixed-assets', 'FixedAssetController');
    Route::post('fixed-assets/{fixedAsset}/dispose', 'FixedAssetController@dispose')->name('fixed-assets.dispose');
    Route::post('fixed-assets/calculate-depreciation', 'FixedAssetController@calculateDepreciation')->name('fixed-assets.calculate-depreciation');
    Route::post('fixed-assets/post-depreciation', 'FixedAssetController@postDepreciation')->name('fixed-assets.post-depreciation');
    Route::get('fixed-assets-register', 'FixedAssetController@assetRegister')->name('fixed-assets.register');
    Route::get('fixed-assets-depreciation-report', 'FixedAssetController@depreciationScheduleReport')->name('fixed-assets.depreciation-report');
    Route::get('fixed-assets-depreciation-summary', 'FixedAssetController@depreciationSummary')->name('fixed-assets.depreciation-summary');

    // Fixed Asset Categories
    Route::resource('fixed-asset-categories', 'FixedAssetCategoryController');

    // Bank Reconciliation
    Route::resource('bank-reconciliation', 'BankReconciliationController');
    Route::post('bank-reconciliation/{bankReconciliation}/complete', 'BankReconciliationController@complete')->name('bank-reconciliation.complete');
    Route::post('bank-reconciliation/{bankReconciliation}/recalculate', 'BankReconciliationController@recalculate')->name('bank-reconciliation.recalculate');
    Route::post('bank-reconciliation/{bankReconciliation}/update-balance', 'BankReconciliationController@updateStatementBalance')->name('bank-reconciliation.update-balance');
    Route::get('bank-reconciliation-summary', 'BankReconciliationController@summary')->name('bank-reconciliation.summary');
    Route::post('bank-reconciliation-item/{item}/clear', 'BankReconciliationController@clearItem')->name('bank-reconciliation.clear-item');
    Route::post('bank-reconciliation-item/{item}/unclear', 'BankReconciliationController@unclearItem')->name('bank-reconciliation.unclear-item');
    Route::post('bank-reconciliation/{bankReconciliation}/add-adjustment', 'BankReconciliationController@addAdjustment')->name('bank-reconciliation.add-adjustment');

    // Recurring Journal Entries
    Route::resource('recurring-entries', 'RecurringEntryController');
    Route::post('recurring-entries/{recurringEntry}/pause', 'RecurringEntryController@pause')->name('recurring-entries.pause');
    Route::post('recurring-entries/{recurringEntry}/resume', 'RecurringEntryController@resume')->name('recurring-entries.resume');
    Route::post('recurring-entries/{recurringEntry}/skip-next', 'RecurringEntryController@skipNext')->name('recurring-entries.skip-next');
    Route::post('recurring-entries/{recurringEntry}/process', 'RecurringEntryController@process')->name('recurring-entries.process');
    Route::post('recurring-entries/{recurringEntry}/duplicate', 'RecurringEntryController@duplicate')->name('recurring-entries.duplicate');
    Route::post('recurring-entries-process-all', 'RecurringEntryController@processAll')->name('recurring-entries.process-all');
    Route::get('recurring-entries-upcoming', 'RecurringEntryController@upcoming')->name('recurring-entries.upcoming');

    // Year-End Closing
    Route::resource('year-end-closing', 'YearEndClosingController');
    Route::post('year-end-closing/{yearEndClosing}/start', 'YearEndClosingController@start')->name('year-end-closing.start');
    Route::post('year-end-closing/{yearEndClosing}/update-checklist', 'YearEndClosingController@updateChecklist')->name('year-end-closing.update-checklist');
    Route::post('year-end-closing/{yearEndClosing}/generate-entries', 'YearEndClosingController@generateClosingEntries')->name('year-end-closing.generate-entries');
    Route::post('year-end-closing/{yearEndClosing}/approve', 'YearEndClosingController@approve')->name('year-end-closing.approve');
    Route::post('year-end-closing/{yearEndClosing}/reverse', 'YearEndClosingController@reverse')->name('year-end-closing.reverse');
    Route::get('year-end-closing/{yearEndClosing}/preview', 'YearEndClosingController@preview')->name('year-end-closing.preview');

    // Accounting Reports
    Route::prefix('accounting-reports')->name('accounting-reports.')->group(function () {
        Route::get('/', 'AccountingReportsController@index')->name('index');
        Route::get('receivables-aging', 'AccountingReportsController@receivablesAging')->name('receivables-aging');
        Route::get('payables-aging', 'AccountingReportsController@payablesAging')->name('payables-aging');
        Route::get('student-fee-aging', 'AccountingReportsController@studentFeeAging')->name('student-fee-aging');
        Route::get('cash-flow-statement', 'AccountingReportsController@cashFlowStatement')->name('cash-flow-statement');
        Route::get('comparative-cash-flow', 'AccountingReportsController@comparativeCashFlow')->name('comparative-cash-flow');
        Route::get('budget-vs-actual', 'AccountingReportsController@budgetVsActual')->name('budget-vs-actual');
        Route::get('export-pdf', 'AccountingReportsController@exportPdf')->name('export-pdf');
        Route::get('export-excel', 'AccountingReportsController@exportExcel')->name('export-excel');
    });

    // Platform Fee Routes
    Route::prefix('platform-fee')->name('platform-fee.')->group(function () {
        // Settings
        Route::get('settings', 'PlatformFeeController@settings')->name('settings');
        Route::post('settings', 'PlatformFeeController@updateSettings')->name('settings.update');
        
        // Payment Verifications
        Route::get('verifications', 'PlatformFeeController@verifications')->name('verifications');
        Route::get('verifications/data', 'PlatformFeeController@getPaymentsData')->name('verifications.data');
        Route::get('verifications/{id}', 'PlatformFeeController@getPayment')->name('verifications.show');
        Route::post('verifications/{id}/approve', 'PlatformFeeController@approvePayment')->name('verifications.approve');
        Route::post('verifications/{id}/reject', 'PlatformFeeController@rejectPayment')->name('verifications.reject');
        
        // Statistics
        Route::get('statistics', 'PlatformFeeController@statistics')->name('statistics');
        
        // Exemptions
        Route::get('exemptions', 'PlatformFeeController@exemptions')->name('exemptions');
        Route::post('exemptions', 'PlatformFeeController@storeExemption')->name('exemptions.store');
        Route::delete('exemptions/{id}', 'PlatformFeeController@deleteExemption')->name('exemptions.delete');
        
        // API for pending count badge
        Route::get('pending-count', 'PlatformFeeController@getPendingCount')->name('pending-count');
    });


    // Staff Routes
    Route::post('staff/user-generate-id', 'UserController@generateStaffId')->name('user.generate-id');
    Route::resource('staff/user','UserController');
    Route::get('staff/user-status/{id}', 'UserController@status')->name('user.status');
    Route::post('staff/user-send-password/{id}', 'UserController@sendPassword')->name('user.send-password');
    // Route::get('staff/user-print-password/{id}', 'UserController@printPassword')->name('user.print-password');
    Route::post('staff/user-password-change', 'UserController@passwordChange')->name('user-password-change');
    Route::get('staff/user-import', 'UserController@import')->name('user.import');
    Route::post('staff/user-import-store', 'UserController@importStore')->name('user.import.store');

    // Staff ID Card Routes
    Route::get('staff/staff-id-card', 'StaffIdCardController@index')->name('staff-id-card.index');
    Route::get('staff/staff-id-card-print/{id}', 'StaffIdCardController@print')->name('staff-id-card.print');
    Route::get('staff/staff-id-card-multiprint', 'StaffIdCardController@multiPrint')->name('staff-id-card.multiprint');
    Route::post('staff/staff-id-card-update-photo/{id}', 'StaffIdCardController@updatePhoto')->name('staff-id-card.update-photo');
    Route::get('staff/staff-id-card-download/{id}', 'StaffIdCardController@download')->name('staff-id-card.download');
    Route::post('staff/staff-id-card-download-zip', 'StaffIdCardController@downloadZip')->name('staff-id-card.download-zip');
    Route::get('staff/staff-id-card-setting', 'StaffIdCardSettingController@index')->name('staff-id-card-setting.index');
    Route::post('staff/staff-id-card-setting', 'StaffIdCardSettingController@store')->name('staff-id-card-setting.store');

    // Payroll Routes
    Route::resource('staff/payroll', 'PayrollController');
    Route::get('staff/payroll-generate/{id}/{month}/{year}', 'PayrollController@generate')->name('payroll.generate');
    Route::post('staff/payroll-pay/{id}', 'PayrollController@pay')->name('payroll.pay');
    Route::post('staff/payroll-unpay/{id}', 'PayrollController@unpay')->name('payroll.unpay');
    Route::get('staff/payroll-report', 'PayrollController@report')->name('payroll.report');
    Route::get('staff/payroll-print/{id}', 'PayrollController@print')->name('payroll.print');
    Route::resource('staff/pay-slip-setting', 'PaySlipSettingController');



    // Human Resource Routes
    Route::resource('staff/designation', 'DesignationController');
    Route::resource('staff/department', 'DepartmentController');
    Route::resource('staff/work-shift-type', 'WorkShiftTypeController');
    Route::resource('staff/staff-note', 'StaffNoteController');
    Route::resource('staff/tax-setting', 'TaxSettingController');
    Route::resource('staff/allowance-type', 'AllowanceTypeController');
    Route::resource('staff/deduction-type', 'DeductionTypeController');
    
    // Tax Groups Routes
    Route::resource('staff/tax-group', 'TaxGroupController');
    Route::post('staff/tax-group/{id}/bracket', 'TaxGroupController@storeBracket')->name('tax-group.bracket.store');
    Route::put('staff/tax-group/{groupId}/bracket/{bracketId}', 'TaxGroupController@updateBracket')->name('tax-group.bracket.update');
    Route::delete('staff/tax-group/{groupId}/bracket/{bracketId}', 'TaxGroupController@destroyBracket')->name('tax-group.bracket.destroy');
    
    // Tax Exemptions Routes
    Route::post('staff/tax-exemptions/store', 'TaxSettingController@storeExemption')->name('tax-exemptions.store');
    Route::delete('staff/tax-exemptions/destroy/{tax_setting_id}/{user_id}', 'TaxSettingController@destroyExemption')->name('tax-exemptions.destroy');

    // Staff Tax Distribution Report
    // Paying withheld tax over to DGI and CNPS. Declared before tax-report so
    // neither shadows the other on the staff/tax-* prefix.
    Route::get('staff/tax-remittance', 'TaxRemittanceController@index')->name('tax-remittance.index');
    Route::post('staff/tax-remittance', 'TaxRemittanceController@store')->name('tax-remittance.store');
    Route::post('staff/tax-remittance/{id}/void', 'TaxRemittanceController@void')->name('tax-remittance.void');

    /*
     * EdutrustPay connection settings.
     *
     * Permissions are applied in the controller's constructor: viewing,
     * changing and testing the credentials are separate, because a wrong value
     * here stops reporting with no visible error at this end — from the body's
     * console this institution simply goes quiet.
     */
    Route::get('edutrustpay', 'EdutrustPayController@index')->name('edutrustpay.index');
    Route::put('edutrustpay', 'EdutrustPayController@update')->name('edutrustpay.update');
    Route::post('edutrustpay/test', 'EdutrustPayController@test')->name('edutrustpay.test');

    Route::get('staff/tax-report', 'StaffTaxReportController@index')->name('staff-tax-report.index');
    Route::get('staff/tax-report/pdf', 'StaffTaxReportController@exportPdf')->name('staff-tax-report.pdf');
    Route::get('staff/tax-report/excel', 'StaffTaxReportController@exportExcel')->name('staff-tax-report.excel');



    // Staff Attendance Routes
    Route::get('attendance/staff-daily-attendance/scanner', 'StaffAttendanceController@scanner')->name('staff-daily-attendance.scanner');
    Route::post('attendance/staff-daily-attendance/scan', 'StaffAttendanceController@scan')->name('staff-daily-attendance.scan');
    Route::get('attendance/staff-daily-attendance/my-attendance', 'StaffAttendanceController@myAttendance')->name('staff-daily-attendance.my-attendance');
    Route::resource('attendance/staff-daily-attendance', 'StaffAttendanceController');
    Route::get('attendance/staff-daily-report', 'StaffAttendanceController@report')->name('staff-daily-attendance.report');
    Route::resource('attendance/staff-hourly-attendance', 'StaffHourlyAttendanceController');
    Route::get('attendance/staff-hourly-report', 'StaffHourlyAttendanceController@report')->name('staff-hourly-attendance.report');
    Route::get('attendance/staff-hourly-report/{id}', 'StaffHourlyAttendanceController@reportDetails')->name('staff-hourly-attendance.report.details');



    // Staff Leave Routes
    Route::resource('leave/staff-leave', 'LeaveController');
    Route::resource('leave/leave-type', 'LeaveTypeController');
    Route::resource('leave/leave-manage', 'LeaveManagementController');
    Route::post('leave/leave-manage-status/{id}', 'LeaveManagementController@status')->name('leave-manage.status');



    // Income Expense Routes
    Route::resource('account/income', 'IncomeController');
    Route::resource('account/income-category', 'IncomeCategoryController');
    Route::resource('account/expense', 'ExpenseController');
    Route::resource('account/expense-category', 'ExpenseCategoryController');
    Route::resource('account/outcome', 'OutcomeCalculationController');



    // Communicate Routes
    Route::resource('communicate/email-notify', 'EmailNotifyController');
    Route::resource('communicate/sms-notify', 'SMSNotifyController');
    Route::resource('communicate/event', 'EventController');
    Route::get('communicate/event-calendar', 'EventController@calendar')->name('event.calendar');
    Route::resource('communicate/notice', 'NoticeController');
    Route::resource('communicate/notice-category', 'NoticeCategoryController');



    // Library Routes
    Route::resource('library/book-list', 'BookController');
    Route::get('library/book-list-token-print/{id}', 'BookController@tokenPrint')->name('book-list.token.print');
    Route::get('library/book-list-multitoken-print', 'BookController@multitokenPrint')->name('book-list.multitoken.print');
    Route::get('library/book-list-import', 'BookController@import')->name('book-list.import');
    Route::post('library/book-list-import-store', 'BookController@importStore')->name('book-list.import.store');
    Route::resource('library/book-request', 'BookRequestController');
    Route::resource('library/book-category', 'BookCategoryController');
    Route::resource('library/issue-return', 'IssueReturnController');
    Route::post('library/issue-return-penalty/{id}', 'IssueReturnController@penalty')->name('issue-return.penalty');

    // Library Member Routes
    Route::resource('member/library-student', 'LibraryStudentController');
    Route::resource('member/library-staff', 'LibraryStaffController');
    Route::resource('member/library-outsider', 'OutSideUserController');
    Route::post('member/library-outsider-status/{id}', 'OutSideUserController@status')->name('library-outsider.status');
    Route::get('member/library-student-card/{id}', 'LibraryStudentController@libraryCard')->name('library-student.card');
    Route::get('member/library-staff-card/{id}', 'LibraryStaffController@libraryCard')->name('library-staff.card');
    Route::get('member/library-outsider-card/{id}', 'OutSideUserController@libraryCard')->name('library-outsider.card');
    Route::resource('library-card-setting', 'LibraryIdCardSettingController');



    // Inventory Routes
    Route::resource('inventory/item-list', 'ItemController');
    Route::resource('inventory/item-issue', 'ItemIssueController');
    Route::post('inventory/item-issue-penalty/{id}', 'ItemIssueController@penalty')->name('item-issue.penalty');
    Route::resource('inventory/item-stock', 'ItemStockController');
    Route::resource('inventory/item-store', 'ItemStoreController');
    Route::resource('inventory/item-supplier', 'ItemSupplierController');
    Route::resource('inventory/item-category', 'ItemCategoryController');



    // Hostel Routes
    Route::resource('hostel/hostel', 'HostelController');
    Route::resource('hostel/hostel-room', 'HostelRoomController');
    Route::resource('hostel/room-type', 'HostelRoomTypeController');
    Route::resource('hostel-student', 'HostelStudentController');
    Route::resource('hostel-staff', 'HostelStaffController');



    // Transport Routes
    Route::resource('transport-route', 'TransportRouteController');
    Route::resource('transport-vehicle', 'TransportVehicleController');
    Route::resource('transport-student', 'TransportStudentController');
    Route::resource('transport-staff', 'TransportStaffController');



    // Visitor Routes
    Route::resource('frontdesk/visitor', 'VisitorController');
    Route::get('frontdesk/visitor-out/{id}', 'VisitorController@outTime')->name('visitor.out');
    Route::get('frontdesk/visitor-token-print/{id}', 'VisitorController@tokenPrint')->name('visitor.token.print');
    Route::resource('frontdesk/visit-purpose', 'VisitPurposeController');
    Route::resource('frontdesk/visitor-token-setting', 'VisitorTokenSettingController');

    // Phone Log Routes
    Route::resource('frontdesk/phone-log', 'PhoneLogController');

    // Enquiry Routes
    Route::resource('frontdesk/enquiry', 'EnquiryController');
    Route::post('frontdesk/enquiry-status/{id}', 'EnquiryController@status')->name('enquiry.status');
    Route::resource('frontdesk/enquiry-source', 'EnquirySourceController');
    Route::resource('frontdesk/enquiry-reference', 'EnquiryReferenceController');

    // Complain Routes
    Route::resource('frontdesk/complain', 'ComplainController');
    Route::post('frontdesk/complain-status/{id}', 'ComplainController@status')->name('complain.status');
    Route::resource('frontdesk/complain-type', 'ComplainTypeController');
    Route::resource('frontdesk/complain-source', 'ComplainSourceController');

    // Postal Exchange Routes
    Route::resource('frontdesk/postal-exchange', 'PostalExchangeController');
    Route::post('frontdesk/postal-exchange-status/{id}', 'PostalExchangeController@status')->name('postal-exchange.status');
    Route::resource('frontdesk/postal-type', 'PostalExchangeTypeController');

    // Postal Exchange Routes
    Route::resource('frontdesk/meeting', 'MeetingScheduleController');
    Route::post('frontdesk/meeting-status/{id}', 'MeetingScheduleController@status')->name('meeting.status');
    Route::resource('frontdesk/meeting-type', 'MeetingTypeController');




    // Marksheet Routes
    Route::resource('transcript/marksheet', 'MarksheetController');
    Route::get('transcript/marksheet-print/{id}', 'MarksheetController@print')->name('marksheet.print');
    Route::get('transcript/marksheet-download/{id}', 'MarksheetController@download')->name('marksheet.download');
    Route::get('transcript/marksheet-semester', 'MarksheetController@semester')->name('marksheet.semester');
    Route::get('transcript/marksheet-semester-print/{id}/{session}', 'MarksheetController@semesterPrint')->name('marksheet.semester.print');
    Route::get('transcript/marksheet-semester-download/{id}/{session}', 'MarksheetController@semesterDownload')->name('marksheet.semester.download');
    Route::get('transcript/marksheet-semester-multiprint', 'MarksheetController@multiPrint')->name('marksheet.semester.multiprint');
    Route::get('transcript/marksheet-bulk', 'MarksheetController@bulk')->name('marksheet.bulk');
    Route::resource('transcript/marksheet-setting', 'MarksheetSettingController');

    // Certificate Routes
    Route::resource('transcript/certificate', 'CertificateController');
    Route::get('transcript/certificate-print/{id}', 'CertificateController@print')->name('certificate.print');
    Route::get('transcript/certificate-download/{id}', 'CertificateController@download')->name('certificate.download');
    Route::get('transcript/certificate-multiprint', 'CertificateController@multiPrint')->name('certificate.multiprint');
    Route::resource('transcript/certificate-template', 'CertificateTemplateController');



    // Report Routes
    Route::get('report/student', 'ReportController@student')->name('report.student');
    Route::get('report/subject', 'ReportController@subject')->name('report.subject');
    Route::get('report/student-attendance', 'ReportController@studentAttendance')->name('report.student-attendance');
    Route::get('report/subject-attendance', 'ReportController@subjectAttendance')->name('report.subject-attendance');
    Route::get('report/fees', 'ReportController@fees')->name('report.fees');
    Route::get('report/student-fees', 'ReportController@studentFees')->name('report.student-fees');
    Route::get('report/payroll', 'ReportController@payroll')->name('report.payroll');
    Route::get('report/leave', 'ReportController@leave')->name('report.leave');
    Route::get('report/income', 'ReportController@income')->name('report.income');
    Route::get('report/expense', 'ReportController@expense')->name('report.expense');
    Route::get('report/library', 'ReportController@library')->name('report.library');
    Route::get('report/book-return', 'ReportController@bookReturn')->name('report.book-return');
    Route::get('report/inventory', 'ReportController@inventory')->name('report.inventory');
    Route::get('report/hostel', 'ReportController@hostel')->name('report.hostel');
    Route::get('report/transport', 'ReportController@transport')->name('report.transport');



    // Setting Routes
    Route::get('setting', 'SettingController@index')->name('setting.index');
    Route::post('setting/siteinfo', 'SettingController@siteInfo')->name('setting.siteinfo');

    // Form A2 Setting Route
    Route::get('setting/form-a2-setting', 'FormA2SettingController@index')->name('form-a2-setting.index');
    Route::post('setting/form-a2-setting', 'FormA2SettingController@update')->name('form-a2-setting.update');

    // Form A3 Setting Route
    Route::get('setting/form-a3-setting', 'FormA3SettingController@index')->name('form-a3-setting.index');
    Route::post('setting/form-a3-setting', 'FormA3SettingController@update')->name('form-a3-setting.update');

    // Form A2 Access Control Route
    Route::get('form-a2-access', 'FormA2AccessController@index')->name('form-a2-access.index');
    Route::post('form-a2-access/{id}/update', 'FormA2AccessController@update')->name('form-a2-access.update');
    Route::post('form-a2-access/bulk-update', 'FormA2AccessController@bulkUpdate')->name('form-a2-access.bulk-update');


    // Address Routes
    Route::resource('setting/province','ProvinceController');
    Route::resource('setting/district','DistrictController');

    // Language Routes
    Route::resource('setting/language', 'LanguageController');
    Route::get('setting/language-default/{id}', 'LanguageController@default')->name('language.default');

    // Translations Routes
    Route::get('translations', 'TranslateController@index')->name('translations.index');
    Route::post('translations/create', 'TranslateController@store')->name('translations.create');
    Route::post('translations/update', 'TranslateController@transUpdate')->name('translation.update.json');
    Route::post('translations/updateKey', 'TranslateController@transUpdateKey')->name('translation.update.json.key');
    Route::delete('translations/destroy/{key}', 'TranslateController@destroy')->name('translations.destroy');

    // Roles And Permission Routes
    Route::resource('setting/role','RoleController');

    // Staff Assignment Routes (Human Resources)
    Route::resource('staff-assignment', 'StaffAssignmentController')->except(['show']);
    Route::post('staff-assignment/get-programs', 'StaffAssignmentController@getPrograms')->name('staff-assignment.get-programs');
    Route::post('staff-assignment/get-courses', 'StaffAssignmentController@getCourses')->name('staff-assignment.get-courses');

    // Env Setting Routes
    Route::resource('setting/mail-setting','MailSettingController');
    Route::resource('setting/sms-setting','SMSSettingController');
    Route::resource('setting/payment-setting','PaymentSettingController');

    // Mobile Money (MTN + Orange) configuration
    Route::get('mobile-money-config',  'MobileMoneyConfigController@index')->name('mobile-money-config.index');
    Route::post('mobile-money-config', 'MobileMoneyConfigController@update')->name('mobile-money-config.update');
    Route::post('mobile-money-config/test/{provider}', 'MobileMoneyConfigController@testConnection')->name('mobile-money-config.test');
    Route::post('mobile-money-config/provision-mtn-sandbox', 'MobileMoneyConfigController@provisionMtnSandbox')->name('mobile-money-config.provision-mtn');

    // Sechedule Setting
    Route::resource('setting/schedule-setting', 'ScheduleSettingController');

    // Application Setting
    Route::resource('setting/application-setting', 'ApplicationSettingController');

    // Context-aware chat assistant
    Route::get('chat/setting', 'ChatSettingController@index')->name('chat-setting.index');
    Route::post('chat/setting', 'ChatSettingController@update')->name('chat-setting.update');

    Route::get('chat/conversation', 'ChatConversationController@index')->name('chat-conversation.index');
    Route::get('chat/conversation/{chatConversation}', 'ChatConversationController@show')->name('chat-conversation.show');
    Route::delete('chat/conversation/{chatConversation}', 'ChatConversationController@destroy')->name('chat-conversation.destroy');

    Route::get('chat/knowledge', 'ChatKnowledgeController@index')->name('chat-knowledge.index');
    Route::post('chat/knowledge', 'ChatKnowledgeController@store')->name('chat-knowledge.store');
    Route::get('chat/knowledge/{chatKnowledge}/edit', 'ChatKnowledgeController@edit')->name('chat-knowledge.edit');
    Route::put('chat/knowledge/{chatKnowledge}', 'ChatKnowledgeController@update')->name('chat-knowledge.update');
    Route::delete('chat/knowledge/{chatKnowledge}', 'ChatKnowledgeController@destroy')->name('chat-knowledge.destroy');

    // Religion Routes
    Route::resource('setting/religion', 'ReligionController');

    // Catholic Students Routes
    Route::get('catholic-student/export', 'CatholicStudentController@export')->name('catholic-student.export');
    Route::get('catholic-student/print', 'CatholicStudentController@print')->name('catholic-student.print');
    Route::resource('catholic-student', 'CatholicStudentController')->except(['create', 'store', 'show', 'edit', 'destroy']);

    // Field Setting Routes
    Route::get('setting/field-user', 'FieldController@user')->name('field.user');
    Route::get('setting/field-student', 'FieldController@student')->name('field.student');
    Route::get('setting/field-application', 'FieldController@application')->name('field.application');
    Route::get('setting/student-panel', 'FieldController@panel')->name('student.panel');
    Route::post('setting/field-store', 'FieldController@store')->name('field.store');



    // Audit Trail Routes
    Route::prefix('audit-log')->group(function () {
        Route::get('/', 'AuditLogController@index')->name('audit-log.index');
        Route::get('/show/{id}', 'AuditLogController@show')->name('audit-log.show');
        Route::get('/export', 'AuditLogController@export')->name('audit-log.export');
        Route::get('/stats', 'AuditLogController@stats')->name('audit-log.stats');
    });

    // Payment Verification Routes
    Route::prefix('payment-verification')->middleware(['permission:payment-receipt-verify'])->group(function () {
        Route::get('/', 'PaymentVerificationController@index')->name('payment-verification.index');
        Route::get('/{type}/{id}', 'PaymentVerificationController@show')->name('payment-verification.show');
        Route::post('/{type}/{id}/approve', 'PaymentVerificationController@approve')->name('payment-verification.approve');
        Route::post('/{type}/{id}/reject', 'PaymentVerificationController@reject')->name('payment-verification.reject');
        Route::post('/{type}/{id}/reverse', 'PaymentVerificationController@reverse')->name('payment-verification.reverse');
    });

    // Partial Payment Report Routes
    Route::prefix('partial-payment-report')->group(function () {
        Route::get('/', 'PartialPaymentReportController@index')->name('partial-payment-report.index');
        Route::get('/export', 'PartialPaymentReportController@export')->name('partial-payment-report.export');
    });


    // Profile Routes
    Route::resource('profile','ProfileController');
    Route::get('profile/account', 'ProfileController@account')->name('profile.account');
    Route::post('profile/changemail', 'ProfileController@changeMail')->name('profile.changemail');
    Route::post('profile/changepass', 'ProfileController@changePass')->name('profile.changepass');



    // Front Web Routes
    Route::prefix('web')->namespace('Web')->group(function () {

        Route::resource('slider', 'SliderController');
        Route::resource('feature', 'FeatureController');
        Route::resource('about-us', 'AboutUsController');
        Route::resource('welcome-message', 'WelcomeMessageController');
        Route::resource('course', 'CourseController');
        Route::resource('web-event', 'WebEventController');
        Route::resource('news', 'NewsController');
    Route::resource('announcement', 'AnnouncementController');
        Route::resource('gallery', 'GalleryController');
        Route::resource('faq', 'FaqController');
        Route::resource('testimonial', 'TestimonialController');
        Route::resource('page', 'PageController');
        Route::resource('call-to-action', 'CallToActionController');
        Route::resource('social-setting', 'SocialSettingController');
        Route::resource('topbar-setting', 'TopbarSettingController');
        
        // New Website Components
        Route::resource('project', 'ProjectController');
        Route::resource('leadership-team', 'LeadershipTeamController');
        Route::resource('accreditation', 'AccreditationController');
        Route::resource('history-timeline', 'HistoryTimelineController');
        Route::resource('support-service', 'SupportServiceController');
        Route::resource('admission-date', 'AdmissionDateController');
        Route::resource('admissions-page', 'AdmissionsPageController');
        Route::resource('campus-life', 'CampusLifeSectionController');
        
        // Resources & Downloads
        Route::get('resource/{resource}/download', 'ResourceController@download')->name('resource.download');
        Route::resource('resource', 'ResourceController');
    });

    // Marketing Routes
    Route::prefix('marketing')->name('marketing.')->group(function () {
        // Dynamic Popup Routes
        Route::get('dynamic-popup', 'DynamicPopupController@index')->name('dynamic-popup.index');
        Route::get('dynamic-popup/create', 'DynamicPopupController@create')->name('dynamic-popup.create');
        Route::post('dynamic-popup', 'DynamicPopupController@store')->name('dynamic-popup.store');
        Route::get('dynamic-popup/{id}', 'DynamicPopupController@show')->name('dynamic-popup.show');
        Route::get('dynamic-popup/{id}/edit', 'DynamicPopupController@edit')->name('dynamic-popup.edit');
        Route::put('dynamic-popup/{id}', 'DynamicPopupController@update')->name('dynamic-popup.update');
        Route::delete('dynamic-popup/{id}', 'DynamicPopupController@destroy')->name('dynamic-popup.destroy');
        Route::post('dynamic-popup/{id}/toggle-status', 'DynamicPopupController@toggleStatus')->name('dynamic-popup.toggle-status');
        Route::post('dynamic-popup/{id}/toggle-pinned', 'DynamicPopupController@togglePinned')->name('dynamic-popup.toggle-pinned');
        Route::post('dynamic-popup/bulk-delete', 'DynamicPopupController@bulkDelete')->name('dynamic-popup.bulk-delete');
    });

    // E-Library Routes
    Route::prefix('e-library')->group(function () {
        Route::get('/', 'ELibraryController@index')->name('e-library.index');
        Route::get('/create', 'ELibraryController@create')->name('e-library.create');
        Route::post('/', 'ELibraryController@store')->name('e-library.store');
        Route::get('/{id}', 'ELibraryController@show')->name('e-library.show');
        Route::get('/{id}/edit', 'ELibraryController@edit')->name('e-library.edit');
        Route::put('/{id}', 'ELibraryController@update')->name('e-library.update');
        Route::delete('/{id}', 'ELibraryController@destroy')->name('e-library.destroy');
        
        // Categories
        Route::get('/categories/manage', 'ELibraryController@categories')->name('e-library.categories');
        Route::post('/categories', 'ELibraryController@storeCategory')->name('e-library.categories.store');
        Route::put('/categories/{id}', 'ELibraryController@updateCategory')->name('e-library.categories.update');
        Route::delete('/categories/{id}', 'ELibraryController@destroyCategory')->name('e-library.categories.destroy');
        
        // Internet Archive API Integration
        Route::get('/internet-archive/search', 'ELibraryController@searchInternetArchive')->name('e-library.internet-archive.search');
        Route::post('/internet-archive/import', 'ELibraryController@importFromInternetArchive')->name('e-library.internet-archive.import');
        
        // Statistics
        Route::get('/statistics/dashboard', 'ELibraryController@statistics')->name('e-library.statistics');
    });
});


// Student Login Routes
Route::prefix('student')->name('student.')->namespace('Student')->group(function(){

    Route::namespace('Auth')->group(function(){

        // Login Routes
        Route::get('/login','LoginController@showLoginForm')->name('login');
        Route::post('/login','LoginController@login')->name('login.store');
        Route::post('/logout','LoginController@logout')->name('logout');
        
        // 2FA Routes
        Route::get('/2fa/verify','LoginController@show2FAVerify')->name('2fa.verify');
        Route::post('/2fa/verify','LoginController@verify2FA')->name('2fa.verify.submit');
        Route::post('/2fa/resend','LoginController@resend2FACode')->name('2fa.resend');

        // Register Routes
        // Route::get('/register','RegisterController@showRegisterForm')->name('register');
        // Route::post('/register','RegisterController@register')->name('register.store');

        // Forgot Password Routes
        Route::get('/password/reset','ForgotPasswordController@showLinkRequestForm')->name('password.request');
        Route::post('/password/email','ForgotPasswordController@sendResetLinkEmail')->name('password.email');

        // Reset Password Routes
        Route::get('/password/reset/{token}/{email}','ResetPasswordController@showResetForm')->name('password.reset');
        Route::post('/password/reset','ResetPasswordController@reset')->name('password.update');
    });

});



// Student Dashboard Routes
Route::middleware(['auth:student', 'XSS'])->prefix('student')->name('student.')->namespace('Student')->group(function () {

    // Platform Fee Payment Routes (EXCLUDED from platform fee middleware)
    Route::get('platform-fee/payment', 'PlatformFeePaymentController@index')->name('platform-fee.payment');
    Route::post('platform-fee/payment/upload', 'PlatformFeePaymentController@uploadReceipt')->name('platform-fee.upload');

    // Program Selection Routes (EXCLUDED from all middlewares - always accessible)
    Route::get('select-program', 'ProgramSelectorController@showSelectProgram')->name('select-program');
    Route::post('switch-program', 'ProgramSelectorController@switchProgram')->name('switch-program');
    Route::get('current-enrollment', 'ProgramSelectorController@getCurrentEnrollment')->name('current-enrollment');

    // Leave Impersonation Route (EXCLUDED from middlewares so admins can always exit)
    Route::get('leave-impersonation', 'DashboardController@leaveImpersonation')->name('leave-impersonation');

    // All other student routes (protected by platform fee middleware AND enrollment selection)
    Route::middleware(['select.enrollment', 'check.platform.fee', 'check.first.installment'])->group(function () {

        // Dashboard Route
        Route::get('/', 'DashboardController@index')->name('dashboard.index');
        Route::get('dashboard', 'DashboardController@index');

        // Form A2 Route
        Route::get('form-a2', 'FormA2Controller@index')->name('form-a2.index');
        Route::get('form-a2/download', 'FormA2Controller@download')->name('form-a2.download');

        // Form A3 Route
        Route::get('form-a3', 'FormA3Controller@index')->name('form-a3.index');
        Route::get('form-a3/download/{id}', 'FormA3Controller@download')->name('form-a3.download');

    // Transcript Routes
    Route::get('transcript', 'TranscriptController@index')->name('transcript.index');

    // Resit Routes
    Route::get('resit', 'ResitController@index')->name('resit.index');
    Route::post('resit/store', 'ResitController@store')->name('resit.store');
    Route::get('resit/history', 'ResitController@history')->name('resit.history');
    Route::post('resit/{id}/cancel', 'ResitController@cancel')->name('resit.cancel');
    Route::post('resit/decline', 'ResitController@decline')->name('resit.decline');
    Route::post('resit/{id}/undo-decline', 'ResitController@undoDecline')->name('resit.undo-decline');

    // Progression Routes
    Route::get('progression/check-eligibility', 'ProgressionController@checkEligibility')->name('progression.check');
    Route::post('progression/proceed', 'ProgressionController@proceed')->name('progression.proceed');

    // Assignment Routes
    Route::get('assignment', 'AssignmentController@index')->name('assignment.index');
    Route::get('assignment/{id}', 'AssignmentController@show')->name('assignment.show');
    Route::post('assignment/{id}/update', 'AssignmentController@update')->name('assignment.update');

    // Class Routine Routes
    Route::get('class-routine', 'ClassRoutineController@index')->name('class-routine.index');

    // Attendance Report
    Route::get('attendance', 'AttendanceController@index')->name('attendance.index');

    // Exam Routine Routes
    Route::get('exam-routine', 'ExamRoutineController@index')->name('exam-routine.index');

    // Exam Result Routes
    Route::get('exam-results', 'ExamResultController@index')->name('exam-results.index');
    Route::get('exam-results/download-pdf', 'ExamResultController@downloadPdf')->name('exam-results.download-pdf');

    // Course Registration Routes
    Route::get('course-registration', 'CourseRegistrationController@index')->name('course-registration.index');
    Route::post('course-registration', 'CourseRegistrationController@update')->name('course-registration.update');
    Route::post('course-registration/drop', 'CourseRegistrationController@drop')->name('course-registration.drop');

    // Fees Routes
    Route::get('fees', 'FeesController@index')->name('fees.index');
    Route::get('fees/pay/{id}', 'FeesController@pay')->name('fees.pay');
    Route::get('fees/print/{id}', 'FeesController@print')->name('fees.print');

    // Payment Plan Routes
    Route::get('payment-plan', 'PaymentPlanController@index')->name('payment-plan.index');
    Route::get('payment-plan/{id}', 'PaymentPlanController@show')->name('payment-plan.show');

    // Installment Payment Routes
    Route::get('installment-payment/create/{installment_id}', 'InstallmentPaymentController@create')->name('installment-payment.create');
    Route::post('installment-payment/store/{installment_id}', 'InstallmentPaymentController@store')->name('installment-payment.store');
    Route::get('installment-payment/{id}', 'InstallmentPaymentController@show')->name('installment-payment.show');

    // Manual Payment Routes
    Route::get('manual-payment', 'ManualPaymentController@index')->name('manual-payment.index');
    Route::get('manual-payment/create/{fee_id}', 'ManualPaymentController@create')->name('manual-payment.create');
    Route::post('manual-payment/store', 'ManualPaymentController@store')->name('manual-payment.store');
    Route::get('manual-payment/{id}', 'ManualPaymentController@show')->name('manual-payment.show');

    // Multi-Payment Routes
    Route::get('multi-payment', 'MultiPaymentController@index')->name('multi-payment.index');
    Route::post('multi-payment/store', 'MultiPaymentController@store')->name('multi-payment.store');
    Route::get('multi-payment/{id}', 'MultiPaymentController@show')->name('multi-payment.show');

    // Library Routes
    Route::get('library', 'LibraryController@index')->name('library.index');

    // E-Library Routes
    Route::prefix('e-library')->group(function () {
        Route::get('/', 'ELibraryController@index')->name('e-library.index');
        Route::get('/browse', 'ELibraryController@browse')->name('e-library.browse');
        Route::get('/book/{id}', 'ELibraryController@show')->name('e-library.show');
        Route::get('/book/{id}/read', 'ELibraryController@read')->name('e-library.read');
        Route::get('/book/{id}/download', 'ELibraryController@download')->name('e-library.download');
        Route::get('/category/{slug}', 'ELibraryController@category')->name('e-library.category');
        
        // User Actions
        Route::post('/book/{id}/favorite', 'ELibraryController@toggleFavorite')->name('e-library.favorite');
        Route::get('/favorites', 'ELibraryController@favorites')->name('e-library.favorites');
        Route::get('/history', 'ELibraryController@history')->name('e-library.history');
        Route::post('/book/{id}/review', 'ELibraryController@submitReview')->name('e-library.review');
        Route::post('/book/{id}/progress', 'ELibraryController@updateProgress')->name('e-library.progress');
        
        // Search
        Route::get('/search', 'ELibraryController@search')->name('e-library.search');
        
        // AI Features
        Route::get('/ai/recommendations', 'ELibraryController@recommendations')->name('e-library.ai.recommendations');
        Route::get('/ai/insights', 'ELibraryController@insights')->name('e-library.ai.insights');
        Route::post('/ai/book/{id}/summary', 'ELibraryController@generateSummary')->name('e-library.ai.summary');
        Route::post('/ai/search', 'ELibraryController@aiSearch')->name('e-library.ai.search');
    });

    // Calendar Routes
    // Route::get('event-calendar', 'EventController@calendar')->name('event.calendar');

    // Notice Routes
    Route::get('notice', 'NoticeController@index')->name('notice.index');
    Route::get('notice/{id}', 'NoticeController@show')->name('notice.show');

    // Leave Routes
    Route::resource('leave', 'LeaveController');

    // Download Routes
    Route::get('download', 'DownloadCenterController@index')->name('download.index');
    Route::get('download/{id}', 'DownloadCenterController@show')->name('download.show');

    // Class Hub Routes (Student Live Class Interaction)
    Route::prefix('class-hub')->name('class-hub.')->group(function () {
        // Main Views
        Route::get('/', 'ClassHubController@index')->name('index');
        Route::get('/live-room/{classSession}', 'ClassHubController@liveRoom')->name('live-room');
        Route::get('/history', 'ClassHubController@history')->name('history');
        Route::get('/my-notes', 'ClassHubController@myNotes')->name('my-notes');
        Route::get('/session/{classSession}', 'ClassHubController@sessionDetails')->name('session-details');
        Route::get('/export-notes', 'ClassHubController@exportNotes')->name('export-notes');
        
        // Real-time Chat
        Route::post('/session/{classSession}/message', 'ClassHubController@sendMessage')->name('send-message');
        Route::get('/session/{classSession}/messages', 'ClassHubController@getMessages')->name('get-messages');
        
        // Notes
        Route::post('/session/{classSession}/note', 'ClassHubController@saveNote')->name('save-note');
        Route::delete('/notes/{note}', 'ClassHubController@deleteNote')->name('delete-note');
        
        // Alerts
        Route::post('/session/{classSession}/alert', 'ClassHubController@createAlert')->name('create-alert');
        Route::post('/alert/{alert}/upvote', 'ClassHubController@upvoteAlert')->name('upvote-alert');
        
        // Questions
        Route::post('/session/{classSession}/question', 'ClassHubController@submitQuestion')->name('submit-question');
        
        // Presence
        Route::get('/session/{classSession}/presence', 'ClassHubController@getPresence')->name('get-presence');
        
        // Logbook (delegated class rep)
        Route::post('/session/{classSession}/logbook', 'ClassHubController@saveLogbook')->name('save-logbook');
        
        // Attendance (delegated class rep)
        Route::post('/session/{classSession}/mark-attendance', 'ClassHubController@markAttendance')->name('mark-attendance');
    });

    }); // End of platform fee middleware group

    // Profile Routes (EXCLUDED from platform fee middleware - students can always access profile)
    Route::resource('profile','ProfileController');
    Route::get('profile/account', 'ProfileController@account')->name('profile.account');
    Route::post('profile/changemail', 'ProfileController@changeMail')->name('profile.changemail');
    Route::post('profile/changepass', 'ProfileController@changePass')->name('profile.changepass');

}); // End of student authenticated route group

// Security Management Routes (Outside platform fee check - always accessible to admins)
Route::middleware(['auth:web', 'XSS', 'license'])->name('admin.')->namespace('Admin')->prefix('admin')->group(function () {
    Route::prefix('security')->name('security.')->group(function () {
        Route::get('dashboard', 'SecurityController@dashboard')->name('dashboard');
        
        // User Management
        Route::get('users', 'SecurityController@users')->name('users');
        Route::post('users/block', 'SecurityController@blockUser')->name('users.block');
        Route::post('users/unblock', 'SecurityController@unblockUser')->name('users.unblock');
        Route::post('users/reset-attempts', 'SecurityController@resetAttempts')->name('users.reset-attempts');
        Route::post('users/reset-orphaned', 'SecurityController@resetOrphanedAttempts')->name('users.reset-orphaned');
        
        // Security Logs
        Route::get('logs', 'SecurityController@logs')->name('logs');
        Route::post('logs/clear', 'SecurityController@clearLogs')->name('logs.clear');
        
        // IP Whitelist Management
        Route::get('whitelist', 'SecurityController@whitelist')->name('whitelist');
        Route::post('whitelist/add', 'SecurityController@addToWhitelist')->name('whitelist.add');
        Route::post('whitelist/remove', 'SecurityController@removeFromWhitelist')->name('whitelist.remove');
        Route::post('whitelist/toggle', 'SecurityController@toggleWhitelist')->name('whitelist.toggle');
        
        // Blocked IPs Management
        Route::get('blocked-ips', 'SecurityController@blockedIps')->name('blocked-ips');
        Route::post('blocked-ips/unblock', 'SecurityController@unblockIp')->name('blocked-ips.unblock');
        Route::post('blocked-ips/block', 'SecurityController@blockIp')->name('blocked-ips.block');
        Route::post('blocked-ips/clear-all', 'SecurityController@clearAllBlocks')->name('blocked-ips.clear-all');
        
        // Security Settings
        Route::get('settings', 'SecurityController@settings')->name('settings');
        Route::post('settings', 'SecurityController@updateSettings')->name('settings.update');
        
        // Two-Factor Authentication
        Route::post('users/toggle-2fa', 'SecurityController@toggle2FA')->name('users.toggle-2fa');
    });

}); // End of admin route group

/*
|--------------------------------------------------------------------------
| Context-aware chat assistant
|--------------------------------------------------------------------------
| One endpoint set for all four surfaces. Identity is resolved server-side by
| ChatContext from the auth guards, so there is no per-portal route to keep in
| sync and no way for a request to declare who it is.
*/
Route::middleware(['web'])->group(function () {
    Route::post('chat/message', 'ChatController@message')->name('chat.message');
    Route::get('chat/history', 'ChatController@history')->name('chat.history');
    Route::post('chat/reset', 'ChatController@reset')->name('chat.reset');
});
