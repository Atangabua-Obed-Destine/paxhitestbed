<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\FailedLoginAttempt;
use App\Models\IpWhitelist;
use App\Models\SecuritySetting;
use App\User;
use App\Models\Student;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class SecurityController extends Controller
{
    /**
     * Display security monitoring dashboard
     */
    public function dashboard()
    {
        // Get statistics for dashboard
        $stats = [
            'failed_attempts_today' => FailedLoginAttempt::whereDate('last_attempt_at', today())->sum('attempts'),
            'blocked_users' => User::whereNotNull('blocked_at')->count() + Student::whereNotNull('blocked_at')->count(),
            'active_whitelists' => IpWhitelist::where('is_active', true)->count(),
            'total_failed_attempts' => FailedLoginAttempt::sum('attempts'),
            'blocked_ips' => FailedLoginAttempt::where('blocked_until', '>', now())->count(),
        ];

        // Get recent failed login attempts (last 7 days)
        $recentAttempts = FailedLoginAttempt::where('last_attempt_at', '>=', now()->subDays(7))
            ->orderBy('last_attempt_at', 'desc')
            ->limit(10)
            ->get();

        // Get failed attempts trend (last 30 days)
        $trendData = FailedLoginAttempt::select(
            DB::raw('DATE(last_attempt_at) as date'),
            DB::raw('SUM(attempts) as total_attempts')
        )
            ->where('last_attempt_at', '>=', now()->subDays(30))
            ->groupBy('date')
            ->orderBy('date', 'asc')
            ->get();

        // Get top attacking IPs
        $topAttackingIps = FailedLoginAttempt::select('ip_address', DB::raw('SUM(attempts) as total'))
            ->where('last_attempt_at', '>=', now()->subDays(7))
            ->groupBy('ip_address')
            ->orderBy('total', 'desc')
            ->limit(5)
            ->get();

        // Get security settings
        $settings = SecuritySetting::getAllSettings();

        return view('admin.security.dashboard', compact('stats', 'recentAttempts', 'trendData', 'topAttackingIps', 'settings'));
    }

    /**
     * Display user management page
     */
    public function users(Request $request)
    {
        $userType = $request->get('type', 'users');
        
        if ($userType === 'students') {
            $users = Student::orderBy('created_at', 'desc')
                ->paginate(20);
            
            // Sync failed attempts from logs table to students table
            foreach ($users as $user) {
                $logAttempt = FailedLoginAttempt::where('email', $user->email)
                    ->where('user_type', 'student')
                    ->first();
                    
                if ($logAttempt && $logAttempt->attempts != $user->failed_login_attempts) {
                    $user->update(['failed_login_attempts' => $logAttempt->attempts]);
                    $user->refresh();
                }
            }
            
            // Get failed attempts for emails not in students table
            $orphanedAttempts = FailedLoginAttempt::where('user_type', 'student')
                ->where('attempts', '>', 0)
                ->whereNotIn('email', Student::pluck('email'))
                ->orderBy('attempts', 'desc')
                ->get();
        } else {
            $users = User::orderBy('created_at', 'desc')
                ->paginate(20);
            
            // Sync failed attempts from logs table to users table
            foreach ($users as $user) {
                $logAttempt = FailedLoginAttempt::where('email', $user->email)
                    ->where('user_type', 'user')
                    ->first();
                    
                if ($logAttempt && $logAttempt->attempts != $user->failed_login_attempts) {
                    $user->update(['failed_login_attempts' => $logAttempt->attempts]);
                    $user->refresh();
                }
            }
            
            // Get failed attempts for emails not in users table
            $orphanedAttempts = FailedLoginAttempt::where('user_type', 'user')
                ->where('attempts', '>', 0)
                ->whereNotIn('email', User::pluck('email'))
                ->orderBy('attempts', 'desc')
                ->get();
        }

        return view('admin.security.users', compact('users', 'userType', 'orphanedAttempts'));
    }

    /**
     * Block a user
     */
    public function blockUser(Request $request)
    {
        $request->validate([
            'user_type' => 'required|in:user,student',
            'user_id' => 'required|integer',
            'block_reason' => 'required|string|max:500',
        ]);

        $model = $request->user_type === 'student' ? Student::class : User::class;
        $user = $model::findOrFail($request->user_id);

        $user->update([
            'blocked_at' => now(),
            'block_reason' => $request->block_reason,
            'blocked_by' => auth()->id(),
        ]);

        return response()->json([
            'success' => true,
            'message' => ucfirst($request->user_type) . ' blocked successfully.',
        ]);
    }

    /**
     * Unblock a user
     */
    public function unblockUser(Request $request)
    {
        $request->validate([
            'user_type' => 'required|in:user,student',
            'user_id' => 'required|integer',
        ]);

        $model = $request->user_type === 'student' ? Student::class : User::class;
        $user = $model::findOrFail($request->user_id);

        $user->update([
            'blocked_at' => null,
            'block_reason' => null,
            'blocked_by' => null,
            'failed_login_attempts' => 0,
        ]);

        return response()->json([
            'success' => true,
            'message' => ucfirst($request->user_type) . ' unblocked successfully.',
        ]);
    }

    /**
     * Reset failed login attempts
     */
    public function resetAttempts(Request $request)
    {
        $request->validate([
            'user_type' => 'required|in:user,student',
            'user_id' => 'required|integer',
        ]);

        $model = $request->user_type === 'student' ? Student::class : User::class;
        $user = $model::findOrFail($request->user_id);

        $user->update(['failed_login_attempts' => 0]);

        // Also clear from failed_login_attempts table
        if ($user->email) {
            FailedLoginAttempt::where('email', $user->email)->delete();
        }

        return response()->json([
            'success' => true,
            'message' => 'Failed login attempts reset successfully.',
        ]);
    }

    /**
     * Reset failed login attempts for emails without accounts
     */
    public function resetOrphanedAttempts(Request $request)
    {
        $request->validate([
            'email' => 'required|email',
            'user_type' => 'required|in:user,student',
        ]);

        FailedLoginAttempt::where('email', $request->email)
            ->where('user_type', $request->user_type)
            ->delete();

        return response()->json([
            'success' => true,
            'message' => 'Failed login attempts reset successfully for ' . $request->email,
        ]);
    }

    /**
     * Display security logs
     */
    public function logs(Request $request)
    {
        $query = FailedLoginAttempt::query();

        // Apply filters
        if ($request->filled('email')) {
            $query->where('email', 'like', '%' . $request->email . '%');
        }

        if ($request->filled('ip_address')) {
            $query->where('ip_address', 'like', '%' . $request->ip_address . '%');
        }

        if ($request->filled('user_type')) {
            $query->where('user_type', $request->user_type);
        }

        if ($request->filled('date_from')) {
            $query->whereDate('last_attempt_at', '>=', $request->date_from);
        }

        if ($request->filled('date_to')) {
            $query->whereDate('last_attempt_at', '<=', $request->date_to);
        }

        $logs = $query->orderBy('last_attempt_at', 'desc')->paginate(50);

        return view('admin.security.logs', compact('logs'));
    }

    /**
     * Clear old security logs
     */
    public function clearLogs(Request $request)
    {
        $request->validate([
            'days' => 'required|integer|min:1|max:365',
        ]);

        $deleted = FailedLoginAttempt::where('last_attempt_at', '<', now()->subDays($request->days))->delete();

        return response()->json([
            'success' => true,
            'message' => "Deleted {$deleted} log entries older than {$request->days} days.",
        ]);
    }

    /**
     * Display IP whitelist management
     */
    public function ipWhitelist()
    {
        $whitelists = IpWhitelist::with('creator')->orderBy('created_at', 'desc')->get();
        return view('admin.security.ip-whitelist', compact('whitelists'));
    }

    /**
     * Store new IP whitelist entry
     */
    public function storeIpWhitelist(Request $request)
    {
        $request->validate([
            'ip_address' => 'required|ip|unique:ip_whitelists,ip_address',
            'description' => 'nullable|string|max:255',
        ]);

        IpWhitelist::create([
            'ip_address' => $request->ip_address,
            'description' => $request->description,
            'is_active' => true,
            'created_by' => auth()->id(),
        ]);

        return response()->json([
            'success' => true,
            'message' => 'IP address added to whitelist successfully.',
        ]);
    }

    /**
     * Toggle IP whitelist status
     */
    public function toggleIpWhitelist($id)
    {
        $whitelist = IpWhitelist::findOrFail($id);
        $whitelist->is_active = !$whitelist->is_active;
        $whitelist->save();

        return response()->json([
            'success' => true,
            'message' => 'IP whitelist status updated.',
        ]);
    }

    /**
     * Delete IP whitelist entry
     */
    public function deleteIpWhitelist($id)
    {
        IpWhitelist::findOrFail($id)->delete();

        return response()->json([
            'success' => true,
            'message' => 'IP address removed from whitelist.',
        ]);
    }

    /**
     * Display security settings
     */
    public function settings()
    {
        $settings = SecuritySetting::orderBy('key')->get();
        return view('admin.security.settings', compact('settings'));
    }

    /**
     * Update security settings
     */
    public function updateSettings(Request $request)
    {
        $request->validate([
            'settings' => 'required|array',
        ]);

        foreach ($request->settings as $key => $value) {
            SecuritySetting::setValue($key, $value);
        }

        // Auto-enable 2FA for all users when system-wide 2FA is enabled or mandatory
        if (isset($request->settings['enable_2fa_admin']) && $request->settings['enable_2fa_admin']) {
            // Enable 2FA for all admin users
            User::where('two_factor_enabled', 0)->update([
                'two_factor_enabled' => true,
                'two_factor_enabled_at' => now(),
            ]);
        }

        if (isset($request->settings['enable_2fa_student']) && $request->settings['enable_2fa_student']) {
            // Enable 2FA for all students
            Student::where('two_factor_enabled', 0)->update([
                'two_factor_enabled' => true,
                'two_factor_enabled_at' => now(),
            ]);
        }

        // If mandatory is enabled, force enable for all users
        if (isset($request->settings['2fa_mandatory_admin']) && $request->settings['2fa_mandatory_admin']) {
            User::where('two_factor_enabled', 0)->update([
                'two_factor_enabled' => true,
                'two_factor_enabled_at' => now(),
            ]);
        }

        if (isset($request->settings['2fa_mandatory_student']) && $request->settings['2fa_mandatory_student']) {
            Student::where('two_factor_enabled', 0)->update([
                'two_factor_enabled' => true,
                'two_factor_enabled_at' => now(),
            ]);
        }

        return response()->json([
            'success' => true,
            'message' => 'Security settings updated successfully. 2FA has been automatically enabled for affected users.',
        ]);
    }

    /**
     * Toggle 2FA for a user
     */
    public function toggle2FA(Request $request)
    {
        $request->validate([
            'user_type' => 'required|in:user,student',
            'user_id' => 'required|integer',
            'enabled' => 'required|boolean',
        ]);

        $model = $request->user_type === 'student' ? Student::class : User::class;
        $user = $model::findOrFail($request->user_id);

        $user->update([
            'two_factor_enabled' => $request->enabled,
            'two_factor_enabled_at' => $request->enabled ? now() : null,
        ]);

        $status = $request->enabled ? 'enabled' : 'disabled';
        
        return response()->json([
            'success' => true,
            'message' => "Two-Factor Authentication {$status} successfully.",
        ]);
    }

    /**
     * Display blocked IPs management page
     */
    public function blockedIps()
    {
        // Get all failed login attempts (show everything including historical data)
        // This includes currently blocked, previously blocked, and failed attempts
        $failedAttempts = FailedLoginAttempt::orderBy('last_attempt_at', 'desc')
            ->paginate(25);
        
        // Add cache status to each attempt
        foreach ($failedAttempts as $attempt) {
            $attempt->is_cache_blocked = \Illuminate\Support\Facades\Cache::has('ddos_blocked:' . $attempt->ip_address);
            $attempt->cache_ttl = null;
            
            if ($attempt->is_cache_blocked) {
                // Try to get the remaining time from cache
                $cacheKey = 'ddos_blocked:' . $attempt->ip_address;
                // Note: Cache TTL retrieval depends on cache driver
            }
        }
        
        return view('admin.security.blocked-ips', compact('failedAttempts'));
    }

    /**
     * Unblock a specific IP
     */
    public function unblockIp(Request $request)
    {
        $request->validate([
            'ip_address' => 'required|ip',
        ]);

        $ip = $request->ip_address;

        // Remove from cache
        \Illuminate\Support\Facades\Cache::forget('ddos_blocked:' . $ip);
        \Illuminate\Support\Facades\Cache::forget('ddos_protection:' . $ip);
        \Illuminate\Support\Facades\Cache::forget('bot_requests:' . $ip);

        // Reset failed login attempts
        FailedLoginAttempt::where('ip_address', $ip)->update([
            'attempts' => 0,
            'blocked_until' => null,
        ]);

        return response()->json([
            'success' => true,
            'message' => "IP address {$ip} has been unblocked successfully.",
        ]);
    }

    /**
     * Block a specific IP manually
     */
    public function blockIp(Request $request)
    {
        $request->validate([
            'ip_address' => 'required|ip',
            'reason' => 'nullable|string|max:500',
            'duration' => 'required|integer|min:1|max:43200', // Max 30 days in minutes
        ]);

        $ip = $request->ip_address;
        $duration = $request->duration;

        // Add to cache
        \Illuminate\Support\Facades\Cache::put('ddos_blocked:' . $ip, true, now()->addMinutes($duration));

        // Create or update failed login attempt record
        FailedLoginAttempt::updateOrCreate(
            ['ip_address' => $ip, 'email' => 'manual_block', 'user_type' => 'unknown'],
            [
                'attempts' => 999,
                'last_attempt_at' => now(),
                'blocked_until' => now()->addMinutes($duration),
                'user_agent' => $request->reason ?? 'Manually blocked by admin',
            ]
        );

        return response()->json([
            'success' => true,
            'message' => "IP address {$ip} has been blocked for {$duration} minutes.",
        ]);
    }

    /**
     * Clear all DDoS blocks
     */
    public function clearAllBlocks(Request $request)
    {
        // Reset all failed login attempts
        $count = FailedLoginAttempt::where('attempts', '>', 0)->update([
            'attempts' => 0,
            'blocked_until' => null,
        ]);

        // Clear cache (note: this clears all cache, in production you'd want to be more specific)
        \Illuminate\Support\Facades\Cache::flush();

        return response()->json([
            'success' => true,
            'message' => "All IP blocks have been cleared. Reset {$count} records.",
        ]);
    }

    /**
     * Display IP whitelist management page
     */
    public function whitelist()
    {
        $whitelistedIps = IpWhitelist::with('creator')
            ->orderBy('created_at', 'desc')
            ->get();

        return view('admin.security.whitelist', compact('whitelistedIps'));
    }

    /**
     * Add IP to whitelist
     */
    public function addToWhitelist(Request $request)
    {
        $request->validate([
            'ip_address' => 'required|ip|unique:ip_whitelists,ip_address',
            'description' => 'nullable|string|max:500',
        ]);

        $whitelist = IpWhitelist::create([
            'ip_address' => $request->ip_address,
            'description' => $request->description ?? 'Added by admin',
            'is_active' => true,
            'created_by' => auth()->id(),
        ]);

        // Also unblock this IP if it's currently blocked
        \Illuminate\Support\Facades\Cache::forget('ddos_blocked:' . $request->ip_address);
        \Illuminate\Support\Facades\Cache::forget('ddos_protection:' . $request->ip_address);
        \Illuminate\Support\Facades\Cache::forget('bot_requests:' . $request->ip_address);

        FailedLoginAttempt::where('ip_address', $request->ip_address)->update([
            'attempts' => 0,
            'blocked_until' => null,
        ]);

        return response()->json([
            'success' => true,
            'message' => "IP address {$request->ip_address} has been added to whitelist and unblocked.",
            'data' => $whitelist,
        ]);
    }

    /**
     * Remove IP from whitelist
     */
    public function removeFromWhitelist(Request $request)
    {
        $request->validate([
            'id' => 'required|exists:ip_whitelists,id',
        ]);

        $whitelist = IpWhitelist::findOrFail($request->id);
        $ip = $whitelist->ip_address;
        $whitelist->delete();

        return response()->json([
            'success' => true,
            'message' => "IP address {$ip} has been removed from whitelist.",
        ]);
    }

    /**
     * Toggle whitelist status
     */
    public function toggleWhitelist(Request $request)
    {
        $request->validate([
            'id' => 'required|exists:ip_whitelists,id',
        ]);

        $whitelist = IpWhitelist::findOrFail($request->id);
        $whitelist->is_active = !$whitelist->is_active;
        $whitelist->save();

        $status = $whitelist->is_active ? 'activated' : 'deactivated';

        return response()->json([
            'success' => true,
            'message' => "IP address {$whitelist->ip_address} has been {$status}.",
            'is_active' => $whitelist->is_active,
        ]);
    }
}
