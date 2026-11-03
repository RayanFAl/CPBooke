<?php

namespace App\Modules\Settings\Services;

use App\Models\SystemSetting;

/**
 * Applies SMTP / mailer settings from system_settings over .env defaults.
 */
class MailSettingsConfigurator
{
    public function usesDatabaseMailTransport(SystemSetting $settings): bool
    {
        if (filled($settings->mail_mailer)) {
            return true;
        }

        return filled($settings->mail_host);
    }

    public function isDeliveryConfigured(SystemSetting $settings): bool
    {
        $mailer = $this->resolvedMailer($settings);

        if (in_array($mailer, ['log', 'array'], true)) {
            return true;
        }

        if ($mailer !== 'smtp') {
            return filled(config('mail.mailers.'.$mailer.'.transport'));
        }

        $host = $this->resolvedSmtpHost($settings);

        return filled($host);
    }

    public function apply(SystemSetting $settings): void
    {
        if (! $this->usesDatabaseMailTransport($settings)) {
            return;
        }

        $mailer = $this->resolvedMailer($settings);

        config(['mail.default' => $mailer]);

        if ($mailer !== 'smtp') {
            return;
        }

        $encryption = $this->resolvedEncryption($settings);

        config([
            'mail.mailers.smtp.host' => $this->resolvedSmtpHost($settings),
            'mail.mailers.smtp.port' => $this->resolvedSmtpPort($settings),
            'mail.mailers.smtp.username' => $this->resolvedSmtpUsername($settings),
            'mail.mailers.smtp.password' => $this->resolvedSmtpPassword($settings),
            'mail.mailers.smtp.scheme' => $encryption === 'ssl' ? 'smtps' : null,
        ]);
    }

    public function resolvedMailer(SystemSetting $settings): string
    {
        $mailer = strtolower(trim((string) ($settings->mail_mailer ?? '')));

        if ($mailer !== '') {
            return $mailer;
        }

        if (filled($settings->mail_host)) {
            return 'smtp';
        }

        return (string) config('mail.default', 'log');
    }

    private function resolvedSmtpHost(SystemSetting $settings): string
    {
        $host = trim((string) ($settings->mail_host ?? ''));

        return $host !== '' ? $host : (string) config('mail.mailers.smtp.host', '');
    }

    private function resolvedSmtpPort(SystemSetting $settings): int
    {
        if ($settings->mail_port !== null && (int) $settings->mail_port > 0) {
            return (int) $settings->mail_port;
        }

        return (int) config('mail.mailers.smtp.port', 587);
    }

    private function resolvedSmtpUsername(SystemSetting $settings): ?string
    {
        $username = trim((string) ($settings->mail_username ?? ''));

        if ($username !== '') {
            return $username;
        }

        $fromEnv = config('mail.mailers.smtp.username');

        return is_string($fromEnv) && $fromEnv !== '' ? $fromEnv : null;
    }

    private function resolvedSmtpPassword(SystemSetting $settings): ?string
    {
        $password = $settings->mail_password;

        if (is_string($password) && $password !== '') {
            return $password;
        }

        $fromEnv = config('mail.mailers.smtp.password');

        return is_string($fromEnv) && $fromEnv !== '' ? $fromEnv : null;
    }

    private function resolvedEncryption(SystemSetting $settings): ?string
    {
        $value = strtolower(trim((string) ($settings->mail_encryption ?? '')));

        if ($value === '' || $value === 'none') {
            return null;
        }

        if (in_array($value, ['tls', 'ssl'], true)) {
            return $value;
        }

        return null;
    }
}
