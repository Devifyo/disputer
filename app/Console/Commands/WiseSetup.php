<?php

namespace App\Console\Commands;

use App\Services\Payments\WisePayoutService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;

/**
 * One-shot Wise environment check: verifies the token for the ACTIVE
 * environment (sandbox or live per WISE_SANDBOX), shows the resolved
 * profile, and registers the transfer-status webhook if it is missing.
 */
class WiseSetup extends Command
{
    protected $signature   = 'wise:setup';
    protected $description = 'Verify the active Wise environment and register the payout webhook';

    public function handle(WisePayoutService $wise): int
    {
        $env = $wise->sandbox() ? 'SANDBOX' : 'LIVE';
        $this->info("Active Wise environment: {$env} ({$this->baseUrl()})");

        if (!config('services.wise.token')) {
            $this->error($wise->sandbox()
                ? 'No sandbox token. Create an account at https://wise-sandbox.com, generate an API token (Settings -> API tokens), and set WISE_SANDBOX_API_TOKEN.'
                : 'No live token. Set WISE_API_TOKEN.');

            return self::FAILURE;
        }

        // Re-resolve the profile fresh each run - a business profile added
        // after the first run must win over the cached personal one.
        \Illuminate\Support\Facades\Cache::forget('wise.profile.' . substr(sha1((string) config('services.wise.token')), 0, 12));

        $profiles = $this->api('get', '/v1/profiles');

        // Wise runs two sandbox API hosts (the newer wise-sandbox.com and the
        // classic sandbox.transferwise.tech) - if the token is rejected on
        // the configured one, try its sibling and say which to configure.
        if ($profiles === null && $wise->sandbox()) {
            foreach (['https://api.wise-sandbox.com', 'https://api.sandbox.transferwise.tech'] as $candidate) {
                if ($candidate === $this->baseUrl()) {
                    continue;
                }
                config(['services.wise.base_url' => $candidate]);
                $profiles = $this->api('get', '/v1/profiles');
                if ($profiles !== null) {
                    $this->warn("Token works on {$candidate} - set WISE_SANDBOX_BASE_URL={$candidate} in .env to make this stick.");
                    break;
                }
            }
        }

        if ($profiles === null) {
            $this->error('Token rejected by ' . $this->baseUrl() . ' - check it belongs to this environment.');

            return self::FAILURE;
        }

        foreach ($profiles as $profile) {
            $name = $profile['details']['name'] ?? trim(($profile['details']['firstName'] ?? '') . ' ' . ($profile['details']['lastName'] ?? ''));
            $this->line(sprintf('  profile %s  %-9s %s', $profile['id'], $profile['type'], $name));
        }

        $active = $wise->profileId();
        $activeType = collect($profiles)->firstWhere('id', (int) $active)['type'] ?? '?';
        $this->info("Paying out from profile: {$active} ({$activeType})" . (config('services.wise.profile_id') ? ' (from env)' : ' (auto-resolved, business preferred)'));

        if ($activeType === 'personal') {
            $this->warn('PERSONAL profile: Wise no longer allows API funding on personal accounts (PSD2).');
            $this->warn('Transfers will be created as drafts - fund them from the Wise website/app, or use a business profile.');
        }

        // Webhook: register transfers#state-change if this environment lacks it.
        $url           = url('/api/webhooks/wise');
        $subscriptions = $this->api('get', "/v3/profiles/{$active}/subscriptions") ?? [];
        $existing      = collect($subscriptions)->first(fn ($s) => ($s['delivery']['url'] ?? '') === $url);

        if ($existing) {
            $this->info("Webhook already registered ({$existing['id']}).");
        } else {
            $created = $this->api('post', "/v3/profiles/{$active}/subscriptions", [
                'name'       => 'Unjamm payout status',
                'trigger_on' => 'transfers#state-change',
                'delivery'   => ['version' => '2.0.0', 'url' => $url],
            ]);

            $created
                ? $this->info("Webhook registered ({$created['id']}) -> {$url}")
                : $this->warn('Could not register the webhook - create it manually in Wise settings for ' . $url);
        }

        $this->info('Wise is ready. ' . ($wise->sandbox() ? 'Transfers here are FAKE - flip WISE_SANDBOX=false for real money.' : 'Transfers here move REAL money.'));

        return self::SUCCESS;
    }

    private function baseUrl(): string
    {
        return rtrim(config('services.wise.base_url'), '/');
    }

    private function api(string $method, string $path, array $payload = []): ?array
    {
        $response = Http::withToken(config('services.wise.token'))
            ->acceptJson()->timeout(20)->{$method}($this->baseUrl() . $path, $payload);

        return $response->successful() ? ($response->json() ?? []) : null;
    }
}
