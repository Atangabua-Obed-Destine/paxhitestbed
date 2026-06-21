<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use App\Models\FailedLoginAttempt;
use App\Models\SecuritySetting;
use App\Models\Student;
use App\Models\IpWhitelist;
use App\User;

class TrackLoginAttempts
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $ipAddress = $request->ip();
        
        // Check if IP is whitelisted - bypass all security checks
        if (IpWhitelist::isWhitelisted($ipAddress)) {
            return $next($request);
        }
        
        // Only process login attempts
        if ($request->is('admin/login') || $request->is('student/login') || $request->is('application/login')) {
            $email = $request->input('email');

            // Check if user is blocked
            if ($email) {
                $userType = $this->getUserType($request->path());
                $user = $this->findUser($email, $userType);

                if ($user && $user->blocked_at) {
                    return response()->json([
                        'error' => 'Your account has been blocked. Reason: ' . $user->block_reason
                    ], 403);
                }

                // Check failed login attempts
                $attempt = FailedLoginAttempt::where('email', $email)
                    ->where('ip_address', $ipAddress)
                    ->first();

                if ($attempt && $attempt->isBlocked()) {
                    $remainingTime = $attempt->getRemainingBlockTime();
                    return response()->json([
                        'error' => "Too many failed login attempts. Please try again in {$remainingTime} minutes."
                    ], 429);
                }
            }
        }

        $response = $next($request);

        // Track failed login after response
        if ($request->isMethod('post') && 
            ($request->is('admin/login') || $request->is('student/login') || $request->is('application/login'))) {
            
            // Check if login failed (401 or redirect with errors)
            if ($response->getStatusCode() === 401 || 
                ($response->isRedirect() && session()->has('errors'))) {
                
                $this->recordFailedAttempt($request);
            }
        }

        return $response;
    }

    /**
     * Record a failed login attempt
     */
    protected function recordFailedAttempt(Request $request)
    {
        $email = $request->input('email');
        $ipAddress = $request->ip();
        $userAgent = $request->userAgent();
        $userType = $this->getUserType($request->path());

        if (!$email) {
            return;
        }

        // Get security settings
        $maxAttempts = SecuritySetting::getValue('max_login_attempts', 5);
        $lockoutDuration = SecuritySetting::getValue('lockout_duration', 5);
        $autoBlockThreshold = SecuritySetting::getValue('auto_block_threshold', 10);

        // Find or create failed attempt record
        $attempt = FailedLoginAttempt::firstOrNew([
            'email' => $email,
            'ip_address' => $ipAddress,
        ]);

        $attempt->user_agent = $userAgent;
        $attempt->user_type = $userType;
        $attempt->attempts = ($attempt->attempts ?? 0) + 1;
        $attempt->last_attempt_at = now();

        // Block temporarily if max attempts reached
        if ($attempt->attempts >= $maxAttempts) {
            $attempt->blocked_until = now()->addMinutes($lockoutDuration);
        }

        $attempt->save();

        // Update user's failed_login_attempts counter
        $user = $this->findUser($email, $userType);
        if ($user) {
            $user->increment('failed_login_attempts');

            // Auto-block if threshold reached
            if ($user->failed_login_attempts >= $autoBlockThreshold && !$user->blocked_at) {
                $user->update([
                    'blocked_at' => now(),
                    'block_reason' => 'Automatically blocked after ' . $autoBlockThreshold . ' failed login attempts.',
                    'blocked_by' => null,
                ]);
            }
        }
    }

    /**
     * Get user type from URL path
     */
    protected function getUserType(string $path): string
    {
        if (str_contains($path, 'admin')) {
            return 'admin';
        } elseif (str_contains($path, 'student')) {
            return 'student';
        } elseif (str_contains($path, 'application')) {
            return 'applicant';
        }
        return 'unknown';
    }

    /**
     * Find user by email and type
     */
    protected function findUser(string $email, string $userType)
    {
        if ($userType === 'admin') {
            return User::where('email', $email)->first();
        } elseif ($userType === 'student') {
            return Student::where('email', $email)->first();
        }
        return null;
    }
}
