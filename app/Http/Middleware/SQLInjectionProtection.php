<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use App\Models\IpWhitelist;

class SQLInjectionProtection
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
        // Check if IP is whitelisted - bypass all security checks
        if (IpWhitelist::isWhitelisted($request->ip())) {
            return $next($request);
        }
        
        // SQL Injection patterns to detect
        $sqlPatterns = [
            '/(\bUNION\b.*\bSELECT\b)/i',
            '/(\bSELECT\b.*\bFROM\b.*\bWHERE\b)/i',
            '/(\bINSERT\b.*\bINTO\b.*\bVALUES\b)/i',
            '/(\bUPDATE\b.*\bSET\b)/i',
            '/(\bDELETE\b.*\bFROM\b)/i',
            '/(\bDROP\b.*\b(TABLE|DATABASE)\b)/i',
            '/(\bEXEC\b|\bEXECUTE\b)/i',
            '/(;.*\b(DROP|DELETE|UPDATE|INSERT)\b)/i',
            '/(\bOR\b.*=.*\bOR\b)/i',
            '/(\'\s*OR\s*\'1\'\s*=\s*\'1)/i',
            '/(\'\s*OR\s*1\s*=\s*1)/i',
            '/(--\s+)/i', // SQL comment: -- followed by space
            '/(\/\*.*\*\/)/i', // SQL block comment: /* ... */
            '/(\bxp_cmdshell\b)/i',
            '/(\bBENCHMARK\b|\bSLEEP\b)/i', // Time-based attacks
        ];
        
        // Fields to skip (tokens, method spoofing - these are framework fields)
        $skipFields = ['_token', '_method'];
        
        // Patterns that are safe (hex colors like #007bff)
        $safePatterns = [
            '/^#[0-9a-fA-F]{3,8}$/', // Valid hex color codes
        ];
        
        // Check all input data
        $inputs = $request->all();
        
        foreach ($inputs as $key => $value) {
            // Skip framework fields
            if (in_array($key, $skipFields)) {
                continue;
            }
            
            if (is_string($value)) {
                // Check if value matches a safe pattern (e.g., hex color)
                $isSafe = false;
                foreach ($safePatterns as $safePattern) {
                    if (preg_match($safePattern, $value)) {
                        $isSafe = true;
                        break;
                    }
                }
                if ($isSafe) {
                    continue;
                }
                foreach ($sqlPatterns as $pattern) {
                    if (preg_match($pattern, $value)) {
                        Log::critical('SQL Injection attempt detected', [
                            'ip' => $request->ip(),
                            'url' => $request->fullUrl(),
                            'field' => $key,
                            'value' => $value,
                            'user_agent' => $request->userAgent(),
                        ]);
                        
                        // Block this request
                        return response()->json([
                            'error' => 'Invalid input detected. Request blocked for security reasons.',
                            'message' => 'If you believe this is an error, please contact support.',
                        ], 400);
                    }
                }
            }
        }
        
        return $next($request);
    }
}
