<?php

namespace App\Http\Middleware;

use App\Models\AttendanceSetting;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Whether unpaid students may reach the class hub.
 *
 * The hub sits behind the platform fee and the first instalment, so a student
 * who has not paid is redirected to the fees page and cannot open the live
 * room at all. That is deliberate - the portal is for students in good
 * standing - but it is a policy, not a law, and some schools will want a
 * student who is sitting in the lecture room to be able to follow the class
 * whatever their balance.
 *
 * So it becomes a setting. The default is the behaviour that shipped, so
 * nothing changes until somebody decides otherwise.
 *
 * What this never does is affect ATTENDANCE. Attendance is taken by the
 * lecturer at the kiosk, from the student's ID card, and no fee check stands
 * between a student who is present and being marked present. The kiosk shows
 * the lecturer which students are fee-blocked from the portal, so an absence
 * is never a mystery.
 */
class ClassHubFeeGate
{
    public const SETTING = 'class_hub_requires_fees';

    public function handle(Request $request, Closure $next): Response
    {
        if (!static::enabled()) {
            return $next($request);
        }

        // Defer to the two middlewares that own these rules, so there is one
        // definition of "has paid" rather than a second copy here.
        return app(CheckPlatformFeePayment::class)->handle($request, function ($request) use ($next) {
            return app(CheckFirstInstallment::class)->handle($request, $next);
        });
    }

    /** Is the fee gate switched on? */
    public static function enabled(): bool
    {
        return (bool) AttendanceSetting::getValue(static::SETTING, true);
    }
}
