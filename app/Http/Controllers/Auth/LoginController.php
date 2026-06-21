<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Auth;
use Illuminate\Foundation\Auth\AuthenticatesUsers;
use Illuminate\Foundation\Auth\ThrottlesLogins;
use App\Models\AuditLog;
use App\Models\TwoFactorCode;
use App\Models\SecuritySetting;
use Illuminate\Http\Request;

class LoginController extends Controller
{
    /*
    |--------------------------------------------------------------------------
    | Login Controller
    |--------------------------------------------------------------------------
    |
    | This controller handles authenticating users for the application and
    | redirecting them to your home screen. The controller uses a trait
    | to conveniently provide its functionality to your applications.
    |
    */

    use AuthenticatesUsers, ThrottlesLogins;

    /**
     * Max login attempts allowed.
     */
    public $maxAttempts = 5;

    /**
     * Number of minutes to lock the login.
     */
    public $decayMinutes = 5;

    /**
     * Where to redirect users after login.
     *
     * @var string
     */
    protected $redirectTo = '/admin/dashboard';

    /**
     * Create a new controller instance.
     *
     * @return void
     */
    public function __construct()
    {
        $this->middleware('guest:web')->except('logout');
    }

    /**
     * Override the authenticated method to handle 2FA
     */
    protected function authenticated(Request $request, $user)
    {
        // Check if 2FA is enabled globally for admins
        $twoFactorEnabled = SecuritySetting::getValue('enable_2fa_admin', false);
        $twoFactorMandatory = SecuritySetting::getValue('2fa_mandatory_admin', false);
        
        // Check if user has 2FA enabled (individual setting)
        if ($twoFactorEnabled && ($user->two_factor_enabled || $twoFactorMandatory)) {
            // Logout the user temporarily
            Auth::guard('web')->logout();
            
            // Generate and send 2FA code
            $userName = $user->first_name . ' ' . $user->last_name;
            TwoFactorCode::generateAndSend($user->email, 'user', $userName);
            
            // Store user credentials in session for verification
            session([
                '2fa_user_id' => $user->id,
                '2fa_user_type' => 'user',
                '2fa_remember' => $request->filled('remember'),
            ]);
            
            return redirect()->route('admin.2fa.verify')
                ->with('info', __('A verification code has been sent to your email.'));
        }
        
        // Log successful login
        AuditLog::create([
            'user_id' => $user->id,
            'user_type' => get_class($user),
            'event' => 'logged_in',
            'auditable_type' => get_class($user),
            'auditable_id' => $user->id,
            'old_values' => null,
            'new_values' => json_encode([
                'login_time' => now()->toDateTimeString(),
                'ip_address' => $request->ip(),
                'user_agent' => $request->userAgent(),
            ]),
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
            'url' => $request->fullUrl(),
            'description' => 'Admin user logged in successfully',
        ]);
    }

    /**
     * Override to log failed login attempts
     */
    protected function sendFailedLoginResponse(Request $request)
    {
        AuditLog::create([
            'user_id' => null,
            'user_type' => null,
            'event' => 'failed_login',
            'auditable_type' => 'App\Models\User',
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
            'description' => 'Failed login attempt for: ' . $request->email,
        ]);

        throw \Illuminate\Validation\ValidationException::withMessages([
            $this->username() => [trans('auth.failed')],
        ]);
    }

    /**
     * Show 2FA verification form
     */
    public function show2FAVerify()
    {
        if (!session()->has('2fa_user_id')) {
            return redirect()->route('login')->with('error', __('Session expired. Please login again.'));
        }
        
        return view('auth.2fa-verify');
    }
    
    /**
     * Verify 2FA code
     */
    public function verify2FA(Request $request)
    {
        $request->validate([
            'code' => 'required|string|size:6',
        ]);
        
        if (!session()->has('2fa_user_id')) {
            return redirect()->route('login')->with('error', __('Session expired. Please login again.'));
        }
        
        $userId = session('2fa_user_id');
        $userType = session('2fa_user_type');
        $remember = session('2fa_remember', false);
        
        // Get user
        $user = \App\User::find($userId);
        
        if (!$user) {
            return redirect()->route('login')->with('error', __('User not found.'));
        }
        
        // Verify the code
        $verified = TwoFactorCode::verify($user->email, $request->code, $userType, $request->ip());
        
        if ($verified) {
            // Clear 2FA session
            session()->forget(['2fa_user_id', '2fa_user_type', '2fa_remember']);
            
            // Log the user in
            Auth::guard('web')->login($user, $remember);
            
            // Log successful login
            AuditLog::create([
                'user_id' => $user->id,
                'user_type' => get_class($user),
                'event' => 'logged_in',
                'auditable_type' => get_class($user),
                'auditable_id' => $user->id,
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
                'description' => 'Admin user logged in successfully with 2FA',
            ]);
            
            return redirect()->intended($this->redirectTo)
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
        if (!session()->has('2fa_user_id')) {
            return redirect()->route('login')->with('error', __('Session expired. Please login again.'));
        }
        
        $userId = session('2fa_user_id');
        $user = \App\User::find($userId);
        
        if (!$user) {
            return redirect()->route('login')->with('error', __('User not found.'));
        }
        
        // Generate and send new code
        $userName = $user->first_name . ' ' . $user->last_name;
        TwoFactorCode::generateAndSend($user->email, 'user', $userName);
        
        return redirect()->back()
            ->with('success', __('A new verification code has been sent to your email.'));
    }

    /**
     * Application's logout action.
     *
     * @return \Illuminate\Http\Response
     */
    public function logout(Request $request){
        
        $user = Auth::guard('web')->user();
        
        if ($user) {
            AuditLog::create([
                'user_id' => $user->id,
                'user_type' => get_class($user),
                'event' => 'logged_out',
                'auditable_type' => get_class($user),
                'auditable_id' => $user->id,
                'old_values' => null,
                'new_values' => json_encode([
                    'logout_time' => now()->toDateTimeString(),
                    'ip_address' => $request->ip(),
                ]),
                'ip_address' => $request->ip(),
                'user_agent' => $request->userAgent(),
                'url' => $request->fullUrl(),
                'description' => 'Admin user logged out',
            ]);
        }

        Auth::guard('web')->logout();

        return redirect()->route('login')->with('success', __('auth_logged_out'));
    }
}
