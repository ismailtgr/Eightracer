<?php

namespace App\Console\Commands;

use App\Services\Security\ClamAvScanner;
use Illuminate\Console\Command;

class SecurityCheck extends Command
{
    protected $signature = 'security:check';

    protected $description = 'Check production configuration without printing secrets';

    public function handle(): int
    {
        $db = config('database.connections.'.config('database.default'));
        $checks = [
            'APP_ENV production' => app()->isProduction(),
            'Debug disabled' => config('app.debug') === false,
            'Application key present' => filled(config('app.key')),
            'HTTPS application URL' => str_starts_with((string) config('app.url'), 'https://'),
            'HTTPS enforced by API' => app()->isProduction() || config('security.require_https'),
            'Database runtime user restricted (not root)' => ! in_array($db['username'] ?? '', ['', 'root', 'postgres', 'sa'], true),
            'Database password present' => filled($db['password'] ?? null),
            'Encrypted secure HttpOnly sessions' => config('session.encrypt') && config('session.secure') && config('session.http_only'),
            'Google client ID present' => filled(config('prd.google_client_id')),
            'Gemini key present' => filled(config('gemini.api_key')),
            'Explicit HTTPS frontend origins' => count(config('cors.allowed_origins', [])) > 0 && collect(config('cors.allowed_origins', []))->every(fn ($url) => filter_var($url, FILTER_VALIDATE_URL) && str_starts_with($url, 'https://') && ! str_contains($url, '*')),
            'Recovery email delivery enabled (not local capture)' => config('prd.email_otp_enabled') && ! config('security.mail_capture_only') && ! in_array(config('mail.default'), ['log', 'array', null], true),
        ];
        try {
            $probe = tmpfile();
            fwrite($probe, 'Eightracer harmless scanner connectivity check');
            app(ClamAvScanner::class)->assertClean(stream_get_meta_data($probe)['uri']);
            $checks['ClamAV clean scan reachable'] = true;
        } catch (\Throwable) {
            $checks['ClamAV clean scan reachable'] = false;
        } finally {
            if (isset($probe) && is_resource($probe)) {
                fclose($probe);
            }
        }
        foreach ($checks as $name => $passed) {
            $this->line(($passed ? 'PASS ' : 'FAIL ').$name);
        }
        $this->warn('Verify database firewall, actual grants, antivirus signature freshness, HTTPS/proxy setup, backups and FE output encoding on the deployment server separately.');

        return in_array(false, $checks, true) ? self::FAILURE : self::SUCCESS;
    }
}
