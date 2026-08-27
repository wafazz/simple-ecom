<?php

namespace App\Support;

use App\Models\Setting;

/**
 * The mail transport, assembled from what the admin saved.
 *
 * Mailgun over SMTP. The HTTP API driver would need symfony/mailgun-mailer,
 * and adding it drags the Symfony tree to a release requiring PHP 8.4 while
 * this project pins the platform to 8.3 to match the server. SMTP is the same
 * Mailgun account and needs no package.
 *
 * apply() is called when the mailer is first RESOLVED, not on every request —
 * see AppServiceProvider. secure_settings is deliberately never cached, so a
 * request that sends no mail must not pay for reading it.
 */
final class MailSettings
{
    /** Ports Mailgun accepts. 2525 exists because hosts block 587. */
    public const PORTS = [
        '587' => '587 — standard (STARTTLS)',
        '2525' => '2525 — alternative, when 587 is blocked',
        '465' => '465 — implicit TLS',
    ];

    private function __construct() {}

    public static function username(): ?string
    {
        return IntegrationConfig::get('mailgun.smtp_username');
    }

    public static function host(): string
    {
        return (string) (Setting::get('mailgun_smtp_host') ?: config('services.mailgun.smtp_host'));
    }

    public static function port(): int
    {
        $port = (string) (Setting::get('mailgun_smtp_port') ?: config('services.mailgun.smtp_port'));

        return array_key_exists($port, self::PORTS) ? (int) $port : 587;
    }

    public static function fromAddress(): ?string
    {
        return Setting::get('mail_from_address') ?: config('mail.from.address');
    }

    public static function fromName(): ?string
    {
        return Setting::get('mail_from_name') ?: Setting::get('store_name') ?: config('mail.from.name');
    }

    /** Enough to actually send: a username, a password and a sender. */
    public static function isConfigured(): bool
    {
        return filled(self::username())
            && filled(IntegrationConfig::get('mailgun.smtp_password'))
            && filled(self::fromAddress());
    }

    /**
     * Point Laravel's SMTP mailer at Mailgun.
     *
     * Does nothing while the credentials are incomplete, so an unconfigured
     * shop keeps whatever MAIL_MAILER says — `log` by default, which writes to
     * storage/logs instead of failing.
     */
    public static function apply(): void
    {
        if (! self::isConfigured()) {
            return;
        }

        $port = self::port();

        config([
            'mail.default' => 'smtp',
            'mail.mailers.smtp.host' => self::host(),
            'mail.mailers.smtp.port' => $port,
            'mail.mailers.smtp.username' => self::username(),
            'mail.mailers.smtp.password' => IntegrationConfig::get('mailgun.smtp_password'),
            // 465 speaks TLS from the first byte; 587 and 2525 upgrade with
            // STARTTLS. Naming the wrong one hangs until the timeout.
            'mail.mailers.smtp.scheme' => $port === 465 ? 'smtps' : 'smtp',
            'mail.from.address' => self::fromAddress(),
            'mail.from.name' => self::fromName(),
        ]);
    }
}
