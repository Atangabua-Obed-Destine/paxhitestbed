<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Applicant;
use App\Models\Application;
use App\Services\ApplicantPasswordReset;
use Flasher\Laravel\Facade\Flasher;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * Applicant accounts — the logins people create on the application portal.
 *
 * An applicant account is not an application. One account can hold several
 * applications, and it exists before the first one is started; it is what the
 * person signs in with. This screen is for the account: finding it, correcting
 * it, getting a locked-out applicant back in, and switching an account off.
 */
class ApplicantController extends Controller
{
    protected $title = 'Applicants';
    protected $route = 'admin.applicant';
    protected $view = 'admin.applicant';
    protected $access = 'applicant';

    public function __construct()
    {
        $this->middleware('permission:applicant-view', ['only' => ['index']]);
        $this->middleware('permission:applicant-edit', ['only' => ['update', 'toggle', 'sendResetLink']]);
        $this->middleware('permission:applicant-password-change', ['only' => ['passwordChange']]);
        $this->middleware('permission:applicant-impersonate', ['only' => ['impersonate']]);
    }

    /**
     * Sign in as this applicant, to see the portal exactly as they see it.
     *
     * Started by POST, with a token. The equivalent for students is a plain
     * link, which means any page an administrator visits could begin a session
     * as somebody else on their behalf; a form cannot be triggered that way.
     *
     * The administrator stays signed in as themselves: the two guards share a
     * session but not an identity. Leaving is therefore just signing the
     * applicant guard out again.
     */
    public function impersonate(Request $request, Applicant $applicant)
    {
        if ($applicant->disabled_at) {
            Flasher::addError(__('This account is disabled. Enable it before signing in as them.'), __('msg_error'));

            return redirect()->back();
        }

        $request->session()->put('impersonate_applicant_admin_id', Auth::guard('web')->id());
        Auth::guard('applicant')->loginUsingId($applicant->id);

        $applicant->customAuditLog(
            'impersonated',
            sprintf('An administrator signed in as applicant %s', $applicant->email)
        );

        return redirect()->route('application.dashboard');
    }

    public function index(Request $request)
    {
        $data['title'] = $this->title;
        $data['route'] = $this->route;
        $data['view'] = $this->view;
        $data['access'] = $this->access;

        $search = trim((string) $request->get('q', ''));
        $status = $request->get('status', 'all');

        $query = Applicant::query()
            ->withCount('applications')
            ->with(['applications' => fn ($q) => $q->with('program')->orderByDesc('id')])
            ->when($search !== '', function ($q) use ($search) {
                $like = '%' . $search . '%';
                $q->where(function ($w) use ($like) {
                    $w->where('first_name', 'like', $like)
                      ->orWhere('last_name', 'like', $like)
                      ->orWhere(DB::raw("CONCAT(COALESCE(first_name,''), ' ', COALESCE(last_name,''))"), 'like', $like)
                      ->orWhere('email', 'like', $like)
                      ->orWhere('phone', 'like', $like);
                });
            })
            ->when($status === 'active', fn ($q) => $q->whereNull('disabled_at'))
            ->when($status === 'disabled', fn ($q) => $q->whereNotNull('disabled_at'))
            ->when($status === 'no_application', fn ($q) => $q->doesntHave('applications'))
            ->orderByDesc('id');

        $data['rows'] = $query->paginate(25)->withQueryString();
        $data['search'] = $search;
        $data['status'] = $status;

        $data['stats'] = [
            'total' => Applicant::count(),
            'disabled' => Applicant::whereNotNull('disabled_at')->count(),
            'no_application' => Applicant::doesntHave('applications')->count(),
            'never_logged_in' => Applicant::whereNull('portal_last_login_at')->count(),
        ];

        return view($this->view . '.index', $data);
    }

    /**
     * Correct the name, email or phone on an account.
     *
     * The email is the login, so it has to stay unique among applicants. Each
     * application also carries a copy of it; copies that still matched the old
     * address are moved to the new one, so the application and its account keep
     * agreeing. A copy that had already been changed on purpose is left alone.
     */
    public function update(Request $request, Applicant $applicant)
    {
        $validated = $request->validate([
            'first_name' => ['nullable', 'string', 'max:191'],
            'last_name' => ['nullable', 'string', 'max:191'],
            'email' => ['required', 'email', 'max:191', 'unique:applicants,email,' . $applicant->id],
            'phone' => ['nullable', 'string', 'max:191'],
        ]);

        DB::transaction(function () use ($applicant, $validated) {
            $oldEmail = $applicant->email;

            $applicant->fill($validated);
            $applicant->save();

            if ($oldEmail !== $applicant->email) {
                Application::where('applicant_id', $applicant->id)
                    ->where('email', $oldEmail)
                    ->update(['email' => $applicant->email]);

                // A reset link sent to the old address must not still work.
                DB::table('password_resets')->where('email', $oldEmail)->delete();
            }
        });

        Flasher::addSuccess(__('msg_updated_successfully'), __('msg_success'));

        return redirect()->back();
    }

    /**
     * Set a new password for an applicant who cannot get in.
     *
     * The "remember me" token is replaced too, so a device still signed in with
     * the old password is signed out rather than left open.
     *
     * The audit trail deliberately never stores a password, and on its own
     * records nothing at all when only the password changes. So the change is
     * logged here explicitly — who changed whose password, and when — with no
     * password in it.
     */
    public function passwordChange(Request $request, Applicant $applicant)
    {
        $request->validate([
            'password' => ['required', 'confirmed', 'min:8'],
        ]);

        $applicant->password = Hash::make($request->password);
        $applicant->setRememberToken(Str::random(60));
        $applicant->save();

        DB::table('password_resets')->where('email', $applicant->email)->delete();

        $applicant->customAuditLog(
            'password_changed',
            sprintf('Password for applicant %s changed by an administrator', $applicant->email)
        );

        Flasher::addSuccess(__('The password has been changed.'), __('msg_success'));

        return redirect()->back();
    }

    /** Disable an account, or enable it again. Its applications are untouched either way. */
    public function toggle(Request $request, Applicant $applicant)
    {
        if ($applicant->disabled_at) {
            $applicant->disabled_at = null;
            $applicant->disabled_by = null;
            $applicant->disabled_reason = null;
            $applicant->save();

            Flasher::addSuccess(__('The account can sign in again.'), __('msg_success'));

            return redirect()->back();
        }

        $request->validate([
            'disabled_reason' => ['nullable', 'string', 'max:500'],
        ]);

        $applicant->disabled_at = now();
        $applicant->disabled_by = Auth::guard('web')->id();
        $applicant->disabled_reason = $request->disabled_reason;
        // Any device signed in with "remember me" loses that, too.
        $applicant->setRememberToken(Str::random(60));
        $applicant->save();

        Flasher::addSuccess(__('The account has been disabled. Its applications are unchanged.'), __('msg_success'));

        return redirect()->back();
    }

    /** Email the standard reset link, so the applicant chooses the password. */
    public function sendResetLink(Applicant $applicant, ApplicantPasswordReset $reset)
    {
        if ($applicant->disabled_at) {
            Flasher::addError(__('This account is disabled. Enable it before sending a reset link.'), __('msg_error'));

            return redirect()->back();
        }

        $result = $reset->send($applicant);

        if ($result === ApplicantPasswordReset::SENT) {
            Flasher::addSuccess(__('A reset link has been sent to :email.', ['email' => $applicant->email]), __('msg_success'));
        } elseif ($result === ApplicantPasswordReset::MAIL_NOT_CONFIGURED) {
            Flasher::addError(__('Email is not set up on this server, so no link could be sent. Change the password instead.'), __('msg_error'));
        } else {
            Flasher::addError(__('The email could not be sent. The error has been logged.'), __('msg_error'));
        }

        return redirect()->back();
    }
}
