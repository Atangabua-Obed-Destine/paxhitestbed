<?php

namespace App\Http\Middleware;

use Illuminate\Foundation\Http\Middleware\VerifyCsrfToken as Middleware;

class VerifyCsrfToken extends Middleware
{
    /**
     * Indicates whether the XSRF-TOKEN cookie should be set on the response.
     *
     * @var bool
     */
    protected $addHttpCookie = true;

    /**
     * The URIs that should be excluded from CSRF verification.
     *
     * @var array
     */
    protected $except = [
        //
        'student/login',
        'student/logout',
        'payment/momo/mtn/webhook',
        'payment/momo/orange/webhook',
        // Fapshi posts this from its own servers, so there is no session and no
        // token to carry. It authenticates on the x-wh-secret header instead,
        // and the state it reports is then re-fetched rather than believed.
        'payment/fapshi/webhook',
    ];
}
