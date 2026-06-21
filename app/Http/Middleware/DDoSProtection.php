<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use App\Models\FailedLoginAttempt;
use App\Models\IpWhitelist;

class DDoSProtection
{
    /**
     * Handle an incoming request.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Closure  $next
     * @return mixed
     */
    public function handle(Request $request, Closure $next)
    {
        $ip = $request->ip();
        $now = now();
        
        // Check if IP is whitelisted - bypass all security checks
        if (IpWhitelist::isWhitelisted($ip)) {
            return $next($request);
        }
        
        // Skip protection for localhost in development
        if (in_array($ip, ['127.0.0.1', '::1', 'localhost']) && config('app.env') !== 'production') {
            return $next($request);
        }
        
        // Check if DDoS protection is enabled
        if (!config('security.ddos.enabled', true)) {
            return $next($request);
        }
        
        // Rate limiting - use config value
        $key = 'ddos_protection:' . $ip;
        $requests = Cache::get($key, 0);
        $maxRequests = config('security.ddos.max_requests_per_minute', 500);
        
        if ($requests > $maxRequests) {
            // Log the attack attempt
            Log::warning('Possible DDoS attack detected', [
                'ip' => $ip,
                'url' => $request->fullUrl(),
                'user_agent' => $request->userAgent(),
                'requests' => $requests,
                'threshold' => $maxRequests,
            ]);
            
            // Block using config duration
            $blockKey = 'ddos_blocked:' . $ip;
            $blockDuration = config('security.ddos.block_duration_minutes', 10);
            
            if (!Cache::has($blockKey)) {
                Cache::put($blockKey, true, now()->addMinutes($blockDuration));
                
                // Create failed login attempt record for tracking
                FailedLoginAttempt::updateOrCreate(
                    ['ip_address' => $ip, 'email' => 'ddos_attack', 'user_type' => 'unknown'],
                    [
                        'attempts' => 999,
                        'last_attempt_at' => $now,
                        'blocked_until' => $now->addMinutes($blockDuration),
                        'user_agent' => $request->userAgent(),
                    ]
                );
            }
            
            $retryAfter = $blockDuration * 60; // Convert minutes to seconds
            return response()->json([
                'error' => 'Too many requests. Your IP has been temporarily blocked.',
                'retry_after' => $retryAfter,
                'message' => "Please try again in {$blockDuration} minutes.",
            ], 429);
        }
        
        // Check if IP is currently blocked
        if (Cache::has('ddos_blocked:' . $ip)) {
            return response()->json([
                'error' => 'Your IP is temporarily blocked due to suspicious activity.',
                'message' => 'Please try again later or contact support.',
            ], 403);
        }
        
        // Increment request counter
        Cache::put($key, $requests + 1, now()->addMinute());
        
        // Detect suspicious patterns
        $this->detectSuspiciousActivity($request);
        
        return $next($request);
    }
    
    /**
     * Detect suspicious activity patterns
     */
    private function detectSuspiciousActivity(Request $request)
    {
        $ip = $request->ip();
        $userAgent = $request->userAgent();
        $url = $request->fullUrl();
        $path = $request->path();
        
        // Check if suspicious pattern detection is enabled
        if (!config('security.ddos.suspicious_pattern_enabled', true)) {
            // Still check bots
            $this->detectBotActivity($request);
            return;
        }
        
        // Check if current path is excluded from checks
        $excludePaths = config('security.ddos.exclude_paths', []);
        foreach ($excludePaths as $excludePattern) {
            if (fnmatch($excludePattern, $path)) {
                $this->detectBotActivity($request);
                return;
            }
        }
        
        // More targeted suspicious patterns (less aggressive)
        $suspiciousPatterns = [
            // SQL Injection attempts (combined patterns only)
            '/(union\s+select|union\s+all\s+select)/i',
            '/(\'\s*or\s*\'\s*=\s*\'|\'\s*or\s*1\s*=\s*1)/i',
            '/(exec\s*\(|execute\s*\(|drop\s+table|drop\s+database)/i',
            
            // XSS attempts (actual script tags)
            '/(<script[^>]*>|<iframe[^>]*>|javascript\s*:)/i',
            
            // Path traversal (multiple levels)
            '/(\.\.\/\.\.\/|\.\.\\\\\.\.\\\\)/i',
            
            // Command injection (combined patterns)
            '/(;\s*(rm|del|format)\s|&&\s*(rm|del))/i',
        ];
        
        $matched = false;
        foreach ($suspiciousPatterns as $pattern) {
            if (preg_match($pattern, $url) || 
                preg_match($pattern, json_encode($request->all()))) {
                $matched = true;
                
                Log::warning('Suspicious pattern detected', [
                    'type' => 'suspicious_pattern',
                    'ip' => $ip,
                    'url' => $url,
                    'user_agent' => $userAgent,
                    'pattern' => $pattern,
                ]);
                
                break;
            }
        }
        
        if ($matched) {
            // Implement multi-strike system before blocking
            $strikeKey = 'suspicious_strikes:' . $ip;
            $strikes = Cache::get($strikeKey, 0) + 1;
            $strikesWindow = config('security.ddos.suspicious_strikes_window_minutes', 15);
            $maxStrikes = config('security.ddos.suspicious_strikes_before_block', 3);
            
            Cache::put($strikeKey, $strikes, now()->addMinutes($strikesWindow));
            
            if ($strikes >= $maxStrikes) {
                // Block after multiple violations
                $blockHours = config('security.ddos.suspicious_block_hours', 2);
                Cache::put('ddos_blocked:' . $ip, true, now()->addHours($blockHours));
                
                Log::critical('IP blocked after multiple suspicious activities', [
                    'ip' => $ip,
                    'strikes' => $strikes,
                    'block_duration_hours' => $blockHours,
                ]);
                
                // Record the block
                FailedLoginAttempt::updateOrCreate(
                    ['ip_address' => $ip, 'email' => 'suspicious_activity', 'user_type' => 'unknown'],
                    [
                        'attempts' => $strikes,
                        'last_attempt_at' => now(),
                        'blocked_until' => now()->addHours($blockHours),
                        'user_agent' => $userAgent,
                    ]
                );
            } else {
                Log::info('Suspicious activity strike recorded', [
                    'ip' => $ip,
                    'strikes' => $strikes,
                    'max_strikes' => $maxStrikes,
                    'remaining' => $maxStrikes - $strikes,
                ]);
            }
        }
        
        // Check for bot activity
        $this->detectBotActivity($request);
    }
    
    /**
     * Detect and limit bot activity
     */
    private function detectBotActivity(Request $request)
    {
        if (!config('security.ddos.bot_detection_enabled', true)) {
            return;
        }
        
        $ip = $request->ip();
        $userAgent = $request->userAgent();
        
        // Bot detection patterns (excluding legitimate browsers)
        $botPatterns = [
            '/bot|crawler|spider|scraper/i',
            '/(curl|wget|python-requests|java\/|perl\/)/i',
        ];
        
        $isBot = false;
        foreach ($botPatterns as $pattern) {
            if (preg_match($pattern, $userAgent)) {
                $isBot = true;
                break;
            }
        }
        
        if ($isBot) {
            $botKey = 'bot_requests:' . $ip;
            $botRequests = Cache::get($botKey, 0);
            $botLimit = config('security.ddos.bot_request_limit', 150);
            
            if ($botRequests > $botLimit) {
                $blockHours = config('security.ddos.bot_block_duration_hours', 1);
                Cache::put('ddos_blocked:' . $ip, true, now()->addHours($blockHours));
                
                Log::info('Bot blocked after exceeding request limit', [
                    'ip' => $ip,
                    'user_agent' => $userAgent,
                    'requests' => $botRequests,
                    'limit' => $botLimit,
                ]);
            }
            
            Cache::put($botKey, $botRequests + 1, now()->addMinutes(10));
        }
    }
}
