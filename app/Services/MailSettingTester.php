<?php

namespace App\Services;

use Illuminate\Support\Str;
use Symfony\Component\Mailer\Mailer;
use Symfony\Component\Mailer\Transport\Dsn;
use Symfony\Component\Mailer\Transport\SendmailTransport;
use Symfony\Component\Mailer\Transport\Smtp\EsmtpTransport;
use Symfony\Component\Mailer\Transport\Smtp\EsmtpTransportFactory;
use Symfony\Component\Mailer\Transport\Smtp\Stream\SocketStream;
use Symfony\Component\Mailer\Transport\TransportInterface;
use Symfony\Component\Mime\Email;

/**
 * Checks that a set of mail settings can actually send mail.
 *
 * Mail is the one setting whose failure is silent. A wrong password here does
 * not break a page — it quietly stops acceptance letters reaching applicants,
 * and nobody finds out until someone asks why they never got their offer. So
 * the school needs a way to ask the question directly.
 *
 * Deliberately three separate stages, reported separately. "Mail is not
 * working" is not a fix; "the server refused the password" is. The stages fail
 * for entirely different reasons and are repaired by different people, so
 * collapsing them into one yes/no would throw away the part that helps.
 *
 * The transport is built exactly the way Illuminate\Mail\MailManager builds it
 * — same factory, same scheme rules — so a passing test means the application's
 * own mail will go out, not merely that some connection somewhere succeeded.
 *
 * Nothing is written. The settings tested are whatever was passed in, which
 * lets an administrator correct a field and try it before saving a value that
 * does not work.
 */
class MailSettingTester
{
    /**
     * Seconds to wait on the socket.
     *
     * A wrong host does not refuse the connection, it hangs — and a button that
     * spins forever reads as a broken page rather than a failed test.
     */
    public const TIMEOUT = 12;

    /**
     * Shortest password worth blanking out of an error message.
     *
     * Below this a password is indistinguishable from ordinary text and
     * redacting it mangles the message. No real SMTP password is this short.
     */
    public const MIN_REDACTABLE_PASSWORD = 5;

    public const STAGE_CONFIGURATION = 'configuration';
    public const STAGE_CONNECTION = 'connection';
    public const STAGE_DELIVERY = 'delivery';

    /**
     * Try the settings, and say how far they got.
     *
     * @param  array  $config  driver, host, port, username, password, encryption, sender_email, sender_name
     * @return array{ok: bool, stage: string, headline: string, detail: ?string, steps: array<int, array{key: string, label: string, state: string, note: ?string}>}
     */
    public function run(array $config, string $recipient): array
    {
        $driver = strtolower(trim((string) ($config['driver'] ?? 'smtp')));
        $isSmtp = $driver === 'smtp';

        $steps = [
            ['key' => self::STAGE_CONFIGURATION, 'label' => __('Settings are complete'), 'state' => 'pending', 'note' => null],
            ['key' => self::STAGE_CONNECTION, 'label' => $isSmtp
                ? __('Mail server accepts the connection and sign-in')
                : __('Local mail program is available'), 'state' => 'pending', 'note' => null],
            ['key' => self::STAGE_DELIVERY, 'label' => __('Test message accepted for delivery'), 'state' => 'pending', 'note' => null],
        ];

        // --- Stage 1: is there enough here to try? ---------------------------
        $missing = $this->missingFields($config, $isSmtp);

        if ($missing !== []) {
            $steps[0]['state'] = 'failed';
            $steps[0]['note'] = implode(', ', $missing);
            $steps[1]['state'] = $steps[2]['state'] = 'skipped';

            return $this->result(false, self::STAGE_CONFIGURATION,
                __('Some settings are still missing.'),
                __('Fill in: :fields', ['fields' => implode(', ', $missing)]),
                $steps);
        }

        $steps[0]['state'] = 'passed';

        // --- Stage 2: will the server talk to us? ----------------------------
        try {
            $transport = $this->transport($config, $isSmtp);
        } catch (\Throwable $e) {
            $steps[1]['state'] = $steps[2]['state'] = 'failed';

            return $this->result(false, self::STAGE_CONNECTION,
                __('These settings could not be used to build a mail connection.'),
                $this->clean($e->getMessage(), $config), $steps);
        }

        if ($isSmtp && $transport instanceof EsmtpTransport) {
            try {
                // start() is the whole handshake: connect, EHLO, upgrade to TLS
                // where the server offers it, then authenticate. Almost every
                // misconfiguration fails here rather than at send time.
                $transport->start();
            } catch (\Throwable $e) {
                $steps[1]['state'] = 'failed';
                $steps[1]['note'] = $this->clean($e->getMessage(), $config);
                $steps[2]['state'] = 'skipped';

                return $this->result(false, self::STAGE_CONNECTION,
                    $this->explain($e->getMessage()),
                    $this->clean($e->getMessage(), $config), $steps);
            }
        }

        $steps[1]['state'] = 'passed';

        // --- Stage 3: will it take a real message? ---------------------------
        try {
            $this->send($transport, $config, $recipient);
        } catch (\Throwable $e) {
            $steps[2]['state'] = 'failed';
            $steps[2]['note'] = $this->clean($e->getMessage(), $config);

            return $this->result(false, self::STAGE_DELIVERY,
                $this->explain($e->getMessage()),
                $this->clean($e->getMessage(), $config), $steps);
        }

        $steps[2]['state'] = 'passed';

        return $this->result(true, self::STAGE_DELIVERY,
            __('Test message sent to :email.', ['email' => $recipient]),
            // Said plainly, because "sent" and "arrived" are not the same thing
            // and an administrator who assumes they are will stop looking too
            // early when mail is being filed as spam.
            __('The mail server accepted it. Check that inbox — including its spam folder — to confirm it arrived.'),
            $steps);
    }

    /** Which required settings are still blank. */
    private function missingFields(array $config, bool $isSmtp): array
    {
        $required = $isSmtp
            ? ['host' => __('Host'), 'port' => __('Port'), 'username' => __('Username'),
               'password' => __('Password'), 'sender_email' => __('Sender email')]
            : ['sender_email' => __('Sender email')];

        $missing = [];

        foreach ($required as $key => $label) {
            if (trim((string) ($config[$key] ?? '')) === '') {
                $missing[] = $label;
            }
        }

        return $missing;
    }

    /** The transport Laravel itself would build from these settings. */
    private function transport(array $config, bool $isSmtp): TransportInterface
    {
        if (!$isSmtp) {
            return new SendmailTransport(config('mail.sendmail'));
        }

        // Mirrors Illuminate\Mail\MailManager::createSmtpTransport: only 'tls'
        // picks a scheme, and 'ssl' is left to the factory, which infers an
        // encrypted connection from port 465.
        $scheme = !empty($config['encryption']) && $config['encryption'] === 'tls'
            ? (((int) $config['port']) === 465 ? 'smtps' : 'smtp')
            : '';

        $transport = (new EsmtpTransportFactory)->create(new Dsn(
            $scheme,
            $config['host'],
            $config['username'] ?: null,
            $config['password'] ?: null,
            (int) $config['port'] ?: null,
            []
        ));

        $stream = $transport->getStream();

        if ($stream instanceof SocketStream) {
            $stream->setTimeout(self::TIMEOUT);
        }

        return $transport;
    }

    private function send(TransportInterface $transport, array $config, string $recipient): void
    {
        $institution = trim((string) ($config['sender_name'] ?? '')) ?: config('app.name');

        $email = (new Email)
            ->from(new \Symfony\Component\Mime\Address($config['sender_email'], $institution))
            ->to($recipient)
            ->subject(__('Mail settings test — :name', ['name' => $institution]))
            ->text(implode("\n", [
                __('This is a test message from the :name portal.', ['name' => $institution]),
                '',
                __('If you are reading it, the mail settings are working and the portal can send acceptance letters, notices and receipts.'),
                '',
                __('Sent :time', ['time' => now()->format('d/m/Y H:i')]),
            ]));

        if (!empty($config['reply_email'])) {
            $email->replyTo($config['reply_email']);
        }

        (new Mailer($transport))->send($email);
    }

    /**
     * A plain-language headline for the usual failures.
     *
     * The raw SMTP text is still shown underneath. This is only so the person
     * reading it knows which field to go and change.
     */
    private function explain(string $error): string
    {
        $haystack = strtolower($error);

        $rules = [
            // "Failed to authenticate on SMTP server with username ..." is
            // Symfony's own wrapper and the only part guaranteed to be there;
            // the server's 535 may or may not survive into the message.
            ['needles' => ['failed to authenticate', 'authentication failed', 'auth failed', '535', '534',
                           'username and password', 'invalid login', 'authentication credentials'],
             'says' => __('The mail server refused the username or password.')],
            ['needles' => ['getaddrinfo', 'name or service not known', 'could not resolve', 'no such host', 'php_network_getaddresses'],
             'says' => __('The host name could not be found. Check the Host field for a typo.')],
            ['needles' => ['connection refused', 'actively refused'],
             'says' => __('The server refused the connection. The port is probably wrong, or a firewall is blocking it.')],
            ['needles' => ['timed out', 'timeout', 'connection could not be established'],
             'says' => __('The server did not answer in time. Check the host and port, and whether this server is allowed to reach it.')],
            ['needles' => ['ssl', 'tls', 'certificate', 'wrong version number'],
             'says' => __('The secure connection failed. The Encryption setting usually does not match the port — SSL goes with 465, TLS with 587.')],
            ['needles' => ['relay', 'not permitted', 'sender address', '553', '550 5.7', 'not allowed'],
             'says' => __('The server would not send from this address. The Sender email normally has to match the mailbox you signed in as.')],
            ['needles' => ['recipient', '550 5.1', 'unknown user', 'does not exist'],
             'says' => __('The server rejected the address you are sending to.')],
        ];

        foreach ($rules as $rule) {
            foreach ($rule['needles'] as $needle) {
                if (str_contains($haystack, $needle)) {
                    return $rule['says'];
                }
            }
        }

        return __('The mail server rejected the attempt.');
    }

    /**
     * The server's own words, with the password taken out.
     *
     * SMTP errors quote back what was sent, which can include the credentials,
     * and this text is going to a web page an administrator may screenshot.
     */
    private function clean(string $message, array $config): string
    {
        $password = (string) ($config['password'] ?? '');

        // Only a password long enough to be distinctive. A two-character one
        // matches ordinary letters all through the message and would redact the
        // diagnostic itself — "host smtp.x" became "host smt********.x" — which
        // destroys the one thing this text exists to convey.
        if (mb_strlen($password) >= self::MIN_REDACTABLE_PASSWORD) {
            $message = str_replace($password, '********', $message);
            $message = str_replace(base64_encode($password), '********', $message);
        }

        $message = preg_replace('/\s+/', ' ', trim($message));

        return Str::limit($message, 400);
    }

    private function result(bool $ok, string $stage, string $headline, ?string $detail, array $steps): array
    {
        return [
            'ok' => $ok,
            'stage' => $stage,
            'headline' => $headline,
            'detail' => $detail,
            'steps' => $steps,
        ];
    }
}
