<?php

namespace App\Http\Controllers\Student\Auth;

use Illuminate\Foundation\Auth\ThrottlesLogins;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Auth;
use Illuminate\Http\Request;
use App\Models\AuditLog;
use App\Models\TwoFactorCode;
use App\Models\SecuritySetting;

class LoginController extends Controller
{
	/**
     * This trait has all the login throttling functionality.
     */
    use ThrottlesLogins;

    //Your other code here...
    /**
	 * Max login attempts allowed.
	 */
	public $maxAttempts = 5;

	/**
	 * Number of minutes to lock the login.
	 */
	public $decayMinutes = 3;

    /**
     * Username used in ThrottlesLogins trait
     *
     * @return string
     */
    public function username()
	{
        return 'email';
    }

	/**
	 * Only guests for "student" guard are allowed except
	 * for logout.
	 *
	 * @return void
	 */
	public function __construct()
	{
	    $this->middleware('guest:student')->except('logout');
	}

    /**
     * Show the login form.
     *
     * @return \Illuminate\Http\Response
     */
    public function showLoginForm()
    {
        return view('web.student.login',[
            'loginRoute' => 'student.login',
            'forgotPasswordRoute' => 'student.password.request',
        ]);
    }

    /**
     * Login the student.
     *
     * @param \Illuminate\Http\Request $request
     * @return \Illuminate\Http\RedirectResponse
     */
    public function login(Request $request)
	{
	    $this->validator($request);

	    //check if the user has too many login attempts.
	    if ($this->hasTooManyLoginAttempts($request)){
	        //Fire the lockout event.
	        $this->fireLockoutEvent($request);

	        //redirect the user back after lockout.
	        return $this->sendLockoutResponse($request);
	    }

	    //attempt login.
	    if(Auth::guard('student')->attempt($request->only('email','password'),$request->filled('remember'))){
	        //Authenticated
	        $student = Auth::guard('student')->user();
	        
	        // Check if 2FA is enabled globally for students
	        $twoFactorEnabled = SecuritySetting::getValue('enable_2fa_student', false);
	        $twoFactorMandatory = SecuritySetting::getValue('2fa_mandatory_student', false);
	        
	        // Check if student has 2FA enabled (individual setting)
	        if ($twoFactorEnabled && ($student->two_factor_enabled || $twoFactorMandatory)) {
	            // Logout the student temporarily
	            Auth::guard('student')->logout();
	            
	            // Generate and send 2FA code
	            $studentName = $student->first_name . ' ' . $student->last_name;
	            TwoFactorCode::generateAndSend($student->email, 'student', $studentName);
	            
	            // Store student credentials in session for verification
	            session([
	                '2fa_student_id' => $student->id,
	                '2fa_student_type' => 'student',
	                '2fa_student_remember' => $request->filled('remember'),
	            ]);
	            
	            return redirect()->route('student.2fa.verify')
	                ->with('info', __('A verification code has been sent to your email.'));
	        }
	        
	        // Log the successful login
	        AuditLog::create([
	            'user_id' => $student->id,
	            'user_type' => get_class($student),
	            'event' => 'logged_in',
	            'auditable_type' => get_class($student),
	            'auditable_id' => $student->id,
	            'old_values' => null,
	            'new_values' => json_encode([
	                'login_time' => now()->toDateTimeString(),
	                'ip_address' => $request->ip(),
	                'user_agent' => $request->userAgent(),
	            ]),
	            'ip_address' => $request->ip(),
	            'user_agent' => $request->userAgent(),
	            'url' => $request->fullUrl(),
	            'description' => 'Student logged in successfully',
	        ]);
	        
	        return redirect()
	            ->intended(route('student.dashboard.index'))
	            ->with('success', __('auth_logged_in'));
	    }

	    //keep track of login attempts from the user.
	    $this->incrementLoginAttempts($request);
	    
	    // Log failed login attempt
	    AuditLog::create([
	        'user_id' => null,
	        'user_type' => null,
	        'event' => 'failed_login',
	        'auditable_type' => 'App\Models\Student',
	        'auditable_id' => null,
	        'old_values' => null,
	        'new_values' => json_encode([
	            'email' => $request->email,
	            'attempt_time' => now()->toDateTimeString(),
	            'ip_address' => $request->ip(),
	            'user_agent' => $request->userAgent(),
	        ]),
	        'ip_address' => $request->ip(),
	        'user_agent' => $request->userAgent(),
	        'url' => $request->fullUrl(),
	        'description' => 'Failed student login attempt for: ' . $request->email,
	    ]);

	    //Authentication failed
	    return $this->loginFailed();
	}

    /**
     * Logout the student.
     *
     * @return \Illuminate\Http\RedirectResponse
     */
    public function logout(Request $request)
	{
	    $student = Auth::guard('student')->user();
	    
	    if ($student) {
	        AuditLog::create([
	            'user_id' => $student->id,
	            'user_type' => get_class($student),
	            'event' => 'logged_out',
	            'auditable_type' => get_class($student),
	            'auditable_id' => $student->id,
	            'old_values' => null,
	            'new_values' => json_encode([
	                'logout_time' => now()->toDateTimeString(),
	                'ip_address' => $request->ip(),
	            ]),
	            'ip_address' => $request->ip(),
	            'user_agent' => $request->userAgent(),
	            'url' => $request->fullUrl(),
	            'description' => 'Student logged out',
	        ]);
	    }
	    
	    Auth::guard('student')->logout();

	    return redirect()
	        ->route('student.login')
	        ->with('success', __('auth_logged_out'));
	}

    /**
     * Validate the form data.
     *
     * @param \Illuminate\Http\Request $request
     * @return
     */
    private function validator(Request $request)
	{
	    //validation rules.
	    $rules = [
	        'email' => 'required|string|email|exists:students,email|max:191',
	        'password' => 'required|string|min:6|max:255',
	    ];

	    //custom validation error messages.
	    $messages = [
	        'email.exists' => __('auth_credentials_not_match'),
	        'email.required' => 'Email address is required.',
	        'email.email' => 'Please enter a valid email address.',
	    ];

	    //validate the request.
	    $request->validate($rules,$messages);
	}

    /**
     * Show 2FA verification form
     */
    public function show2FAVerify()
    {
        if (!session()->has('2fa_student_id')) {
            return redirect()->route('student.login')->with('error', __('Session expired. Please login again.'));
        }
        
        return view('web.student.2fa-verify');
    }
    
    /**
     * Verify 2FA code
     */
    public function verify2FA(Request $request)
    {
        $request->validate([
            'code' => 'required|string|size:6',
        ]);
        
        if (!session()->has('2fa_student_id')) {
            return redirect()->route('student.login')->with('error', __('Session expired. Please login again.'));
        }
        
        $studentId = session('2fa_student_id');
        $remember = session('2fa_student_remember', false);
        
        // Get student
        $student = \App\Models\Student::find($studentId);
        
        if (!$student) {
            return redirect()->route('student.login')->with('error', __('Student not found.'));
        }
        
        // Verify the code
        $verified = TwoFactorCode::verify($student->email, $request->code, 'student', $request->ip());
        
        if ($verified) {
            // Clear 2FA session
            session()->forget(['2fa_student_id', '2fa_student_type', '2fa_student_remember']);
            
            // Log the student in
            Auth::guard('student')->login($student, $remember);
            
            // Log successful login
            AuditLog::create([
                'user_id' => $student->id,
                'user_type' => get_class($student),
                'event' => 'logged_in',
                'auditable_type' => get_class($student),
                'auditable_id' => $student->id,
                'old_values' => null,
                'new_values' => json_encode([
                    'login_time' => now()->toDateTimeString(),
                    'ip_address' => $request->ip(),
                    'user_agent' => $request->userAgent(),
                    '2fa_verified' => true,
                ]),
                'ip_address' => $request->ip(),
                'user_agent' => $request->userAgent(),
                'url' => $request->fullUrl(),
                'description' => 'Student logged in successfully with 2FA',
            ]);
            
            return redirect()->intended(route('student.dashboard.index'))
                ->with('success', __('Logged in successfully.'));
        }
        
        return redirect()->back()
            ->with('error', __('Invalid or expired verification code.'))
            ->withInput();
    }
    
    /**
     * Resend 2FA code
     */
    public function resend2FACode(Request $request)
    {
        if (!session()->has('2fa_student_id')) {
            return redirect()->route('student.login')->with('error', __('Session expired. Please login again.'));
        }
        
        $studentId = session('2fa_student_id');
        $student = \App\Models\Student::find($studentId);
        
        if (!$student) {
            return redirect()->route('student.login')->with('error', __('Student not found.'));
        }
        
        // Generate and send new code
        $studentName = $student->first_name . ' ' . $student->last_name;
        TwoFactorCode::generateAndSend($student->email, 'student', $studentName);
        
        return redirect()->back()
            ->with('success', __('A new verification code has been sent to your email.'));
    }

    /**
     * Redirect back after a failed login.
     *
     * @return \Illuminate\Http\RedirectResponse
     */
    private function loginFailed()
	{
	    return redirect()
	        ->back()
	        ->withInput()
	        ->with('error', __('auth_login_failed'));
	}
}
