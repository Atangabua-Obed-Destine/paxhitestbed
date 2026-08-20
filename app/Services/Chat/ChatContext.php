<?php

namespace App\Services\Chat;

use App\Models\Applicant;
use App\Models\ChatConversation;
use App\Models\Student;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;

/**
 * The ONLY source of identity for the chat module.
 *
 * Every data tool takes its subject from this object. Nothing the language
 * model produces is ever used to select whose records are read — a tool has no
 * "student_id" parameter to fill in, so a prompt-injected "show me student 42's
 * fees" has nowhere to put the 42.
 *
 * Actor is resolved from the auth guards, in priority order:
 *   web (staff) -> student -> applicant -> guest
 *
 * Note the deliberate distinction between ACTOR and SURFACE. The actor decides
 * what data may be read; the surface only decides whether the widget is shown
 * and which greeting is used. A signed-in student browsing the public website
 * is still a student actor.
 */
class ChatContext
{
    protected string $actorType;
    protected ?int $actorId;
    protected string $surface;
    protected ?int $actingUserId;
    protected string $sessionKey;
    protected ?string $currentPath;

    /** Lazily resolved subject model. */
    protected $subject = null;
    protected bool $subjectLoaded = false;

    public function __construct(
        string $actorType,
        ?int $actorId,
        string $surface,
        string $sessionKey,
        ?int $actingUserId = null,
        ?string $currentPath = null
    ) {
        $this->actorType = $actorType;
        $this->actorId = $actorId;
        $this->surface = $surface;
        $this->sessionKey = $sessionKey;
        $this->actingUserId = $actingUserId;
        $this->currentPath = $currentPath;
    }

    /**
     * Build the context for the current request. Guards are consulted directly;
     * no request input contributes to identity.
     */
    public static function fromRequest(Request $request, ?string $surface = null): self
    {
        $actorType = ChatConversation::ACTOR_GUEST;
        $actorId = null;

        if (Auth::guard('web')->check()) {
            $actorType = ChatConversation::ACTOR_USER;
            $actorId = (int) Auth::guard('web')->id();
        } elseif (Auth::guard('student')->check()) {
            $actorType = ChatConversation::ACTOR_STUDENT;
            $actorId = (int) Auth::guard('student')->id();
        } elseif (Auth::guard('applicant')->check()) {
            $actorType = ChatConversation::ACTOR_APPLICANT;
            $actorId = (int) Auth::guard('applicant')->id();
        }

        // When staff impersonate a student the student guard is active, so the
        // answer is correctly scoped to that student — but the audit trail must
        // still record who actually asked.
        $actingUserId = $request->session()->get('impersonate_admin_id');

        // A stable per-visitor key so guests keep one thread.
        $sessionKey = $request->session()->get('chat_session_key');
        if (!$sessionKey) {
            $sessionKey = (string) Str::uuid();
            $request->session()->put('chat_session_key', $sessionKey);
        }

        return new self(
            $actorType,
            $actorId,
            $surface ?: static::resolveSurface($request, $actorType),
            $sessionKey,
            $actingUserId ? (int) $actingUserId : null,
            $request->path()
        );
    }

    /** Which portal a page request came from, used only for display decisions. */
    public static function detectSurface(Request $request): string
    {
        $path = $request->path();

        return match (true) {
            Str::startsWith($path, 'admin') => 'admin',
            Str::startsWith($path, 'student') => 'student',
            Str::startsWith($path, 'application') => 'application',
            default => 'web',
        };
    }

    /**
     * The chat endpoints live at /chat/* for every portal, so the path cannot
     * reveal which surface the widget is embedded in. The widget therefore
     * reports it — but the claim is checked against the actor rather than
     * trusted, because the surface selects which knowledge-base entries apply
     * and a student must not be able to request the staff-only ones.
     */
    public static function resolveSurface(Request $request, string $actorType): string
    {
        $allowed = static::allowedSurfaces($actorType);
        $claimed = (string) $request->input('surface', '');

        if (in_array($claimed, $allowed, true)) {
            return $claimed;
        }

        // The /chat/* endpoints are shared by every portal, so the path says
        // nothing about where the widget is embedded. Only trust detection for
        // ordinary page requests.
        if (!Str::startsWith($request->path(), 'chat')) {
            $detected = static::detectSurface($request);
            if (in_array($detected, $allowed, true)) {
                return $detected;
            }
        }

        // Fall back to the actor's own portal, never to a wider one.
        return $allowed[0];
    }

    /**
     * Surfaces each actor can legitimately be browsing. 'web' is always
     * possible because every actor can visit the public site.
     *
     * @return array<string>
     */
    public static function allowedSurfaces(string $actorType): array
    {
        return match ($actorType) {
            ChatConversation::ACTOR_STUDENT => ['student', 'web'],
            ChatConversation::ACTOR_APPLICANT => ['application', 'web'],
            ChatConversation::ACTOR_USER => ['admin', 'web'],
            default => ['web'],
        };
    }

    public function actorType(): string
    {
        return $this->actorType;
    }

    public function actorId(): ?int
    {
        return $this->actorId;
    }

    public function surface(): string
    {
        return $this->surface;
    }

    public function sessionKey(): string
    {
        return $this->sessionKey;
    }

    public function actingUserId(): ?int
    {
        return $this->actingUserId;
    }

    public function currentPath(): ?string
    {
        return $this->currentPath;
    }

    public function isGuest(): bool
    {
        return $this->actorType === ChatConversation::ACTOR_GUEST;
    }

    public function isStudent(): bool
    {
        return $this->actorType === ChatConversation::ACTOR_STUDENT;
    }

    public function isApplicant(): bool
    {
        return $this->actorType === ChatConversation::ACTOR_APPLICANT;
    }

    public function isStaff(): bool
    {
        return $this->actorType === ChatConversation::ACTOR_USER;
    }

    /**
     * The record the tools may read. Never derived from model output.
     * Returns null for guests.
     */
    public function subject()
    {
        if ($this->subjectLoaded) {
            return $this->subject;
        }

        $this->subjectLoaded = true;
        $this->subject = match ($this->actorType) {
            ChatConversation::ACTOR_STUDENT => Student::find($this->actorId),
            ChatConversation::ACTOR_APPLICANT => Applicant::find($this->actorId),
            ChatConversation::ACTOR_USER => \App\User::find($this->actorId),
            default => null,
        };

        return $this->subject;
    }

    /**
     * Permission check for staff tools, delegating to the Spatie roles already
     * on App\User. Always false for non-staff actors, so a student can never
     * satisfy a staff tool's requirement.
     */
    public function can(string $permission): bool
    {
        if (!$this->isStaff()) {
            return false;
        }

        $user = $this->subject();

        return $user && method_exists($user, 'can') ? (bool) $user->can($permission) : false;
    }

    /** Human-readable actor label for prompts and the audit screen. */
    public function displayName(): string
    {
        $subject = $this->subject();

        if (!$subject) {
            return __('Guest');
        }

        $name = trim(($subject->first_name ?? '') . ' ' . ($subject->last_name ?? ''));

        return $name !== '' ? $name : ($subject->email ?? __('User'));
    }

    /**
     * A short description of who is asking, injected into the system prompt so
     * the model knows what it may and may not offer to do.
     */
    public function describeActor(): string
    {
        return match ($this->actorType) {
            ChatConversation::ACTOR_STUDENT => 'a signed-in student asking about their own records',
            ChatConversation::ACTOR_APPLICANT => 'a signed-in applicant asking about their own application',
            ChatConversation::ACTOR_USER => 'a signed-in staff member of the university',
            default => 'an anonymous visitor to the public website',
        };
    }
}
