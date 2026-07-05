<?php

namespace App\Services;

use App\Mail\GenericEmail;
use App\Models\Itinerary;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Email-forwarding fallback: turn a forwarded flight confirmation into an
 * itinerary + claims, attributing it to the sender's account (creating a
 * lightweight one + a "set your password" email when needed).
 *
 * Format-agnostic — the webhook controller (SendGrid Inbound Parse) adapts its
 * payload into the plain arguments below.
 *
 * @param array<int, array{name?:string, mime?:string, bytes?:string}> $attachments
 */
class InboundItineraryImporter
{
    public function __construct(private ItineraryParserService $parser)
    {
    }

    public function import(string $fromEmail, ?string $fromName, ?string $subject, ?string $text, ?string $html, array $attachments = []): array
    {
        $email = strtolower(trim($fromEmail));
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            Log::warning('Inbound itinerary: no valid sender address.', ['from' => $fromEmail]);
            return ['new_account' => false, 'created' => 0, 'duplicates' => 0, 'ignored' => 'no-sender'];
        }

        $name = trim((string) $fromName) ?: Str::before($email, '@');
        [$user, $isNew] = $this->resolveUser($email, $name);

        $created = 0;
        $duplicates = 0;
        $failed = 0;
        $parsedItineraries = [];

        foreach ($attachments as $attachment) {
            [$result, $itinerary] = $this->processAttachment($user, $attachment);
            $created    += $result === 'created' ? 1 : 0;
            $duplicates += $result === 'duplicate' ? 1 : 0;
            $failed     += $result === 'failed' ? 1 : 0;
            if ($result === 'created' && $itinerary) {
                $parsedItineraries[] = $itinerary;
            }
        }

        // Fall back to the email body for inline-forwarded itineraries.
        if ($created === 0 && $duplicates === 0 && $failed === 0) {
            $body = (string) ($text ?: strip_tags((string) $html));
            if (trim($body) !== '') {
                [$result, $itinerary] = $this->processBody($user, $subject, $body);
                $created    += $result === 'created' ? 1 : 0;
                $duplicates += $result === 'duplicate' ? 1 : 0;
                if ($result === 'created' && $itinerary) {
                    $parsedItineraries[] = $itinerary;
                }
            }
        }

        // Existing users get a confirmation once the itinerary parses into claims
        // (new users learn about their claim in the set-password email instead).
        if (!$isNew && $created > 0) {
            $this->sendClaimCreatedEmail($user, $parsedItineraries);
        }

        Log::info('Inbound itinerary processed', [
            'email' => $email, 'new_account' => $isNew, 'created' => $created, 'duplicates' => $duplicates,
        ]);

        return ['new_account' => $isNew, 'created' => $created, 'duplicates' => $duplicates];
    }

    /**
     * Find the sender's account, or create a lightweight one and email a
     * "set your password" link. Returns [User, isNew].
     */
    private function resolveUser(string $email, string $name): array
    {
        if ($user = User::where('email', $email)->first()) {
            return [$user, false];
        }

        $user = User::create([
            'name'     => $name,
            'email'    => $email,
            'password' => Hash::make(Str::random(40)), // unusable until they set one
        ]);
        $user->forceFill(['email_verified_at' => now()])->save();
        $user->assignRole('user');

        $this->sendSetPasswordEmail($user);

        return [$user, true];
    }

    private function sendSetPasswordEmail(User $user): void
    {
        try {
            $token = Password::broker()->createToken($user);
            $link  = route('password.reset', ['token' => $token, 'email' => $user->email]);
            $inbound = config('services.inbound.claims_address') ?: 'claims@unjamm.com';

            $subject = 'Set your Unjamm password';
            $body = '<p>Hi ' . e($user->name) . ',</p>'
                . '<p>We received a flight itinerary you forwarded to <strong>' . e($inbound) . '</strong> and created an Unjamm account for you so you can track your claim.</p>'
                . '<p>Set your password to access your claims:</p>'
                . '<p><a href="' . e($link) . '">Set your password</a></p>'
                . "<p>If you didn't forward this, you can safely ignore this email.</p>";

            Mail::to($user->email)->send(new GenericEmail($subject, $body));
        } catch (\Throwable $e) {
            Log::error('Inbound itinerary: failed to send set-password email.', ['email' => $user->email, 'error' => $e->getMessage()]);
        }
    }

    /**
     * Confirmation for existing users: their forwarded itinerary parsed into
     * claim(s) they can review on the dashboard.
     *
     * @param array<int, Itinerary> $itineraries
     */
    private function sendClaimCreatedEmail(User $user, array $itineraries): void
    {
        try {
            $link = route('user.itineraries.index');

            $claims = collect($itineraries)
                ->flatMap(fn (Itinerary $itinerary) => $itinerary->claims()->get());

            $flightRows = $claims->map(function ($claim) {
                $route = e($claim->departure_airport ?: '?') . ' &rarr; ' . e($claim->arrival_airport ?: '?');
                $meta  = collect([$claim->airline, $claim->flight_number, $claim->flight_date?->format('d M Y')])
                    ->filter()->map(fn ($part) => e($part))->implode(' &middot; ');
                return '<tr>'
                    . '<td style="padding: 10px 14px; border-bottom: 1px solid #f1f5f9; font-size: 14px; color: #0f172a; font-weight: 600;">' . $route . '</td>'
                    . '<td style="padding: 10px 14px; border-bottom: 1px solid #f1f5f9; font-size: 13px; color: #64748b;">' . $meta . '</td>'
                    . '</tr>';
            })->implode('');

            $count = max($claims->count(), 1);
            $noun  = $count === 1 ? 'claim' : 'claims';

            $subject = 'Your flight ' . $noun . ' ' . ($count === 1 ? 'has' : 'have') . ' been created';
            $body = '<p style="margin: 0 0 16px 0;">Hi ' . e($user->name) . ',</p>'
                . '<p style="margin: 0 0 16px 0;">Good news &mdash; we received the flight itinerary you forwarded and created '
                . ($count === 1 ? 'a claim' : $count . ' claims') . ' on your account.</p>'
                . ($flightRows !== ''
                    ? '<table width="100%" cellpadding="0" cellspacing="0" role="presentation" style="margin: 0 0 24px 0; border: 1px solid #e2e8f0; border-radius: 10px; border-collapse: separate; overflow: hidden;">' . $flightRows . '</table>'
                    : '')
                . '<table cellpadding="0" cellspacing="0" role="presentation" style="margin: 0 0 24px 0;"><tr>'
                . '<td style="background-color: #0B6B4C; border-radius: 10px;">'
                . '<a href="' . e($link) . '" style="display: inline-block; padding: 12px 28px; font-size: 15px; font-weight: 700; color: #ffffff; text-decoration: none;">View your ' . $noun . '</a>'
                . '</td></tr></table>'
                . '<p style="margin: 0; color: #64748b; font-size: 13px;">We\'ll review your ' . $noun . ' and keep you posted on the next steps.</p>';

            Mail::to($user->email)->send(new GenericEmail($subject, $body));
        } catch (\Throwable $e) {
            Log::error('Inbound itinerary: failed to send claim-created email.', ['email' => $user->email, 'error' => $e->getMessage()]);
        }
    }

    /** @return array{0: ?string, 1: ?Itinerary} */
    private function processAttachment(User $user, array $attachment): array
    {
        $mime  = (string) ($attachment['mime'] ?? '');
        $bytes = $attachment['bytes'] ?? null;

        if (!is_string($bytes) || $bytes === '' || !(str_contains($mime, 'pdf') || str_starts_with($mime, 'image/'))) {
            return [null, null];
        }

        $hash = hash('sha256', $bytes);
        if (Itinerary::where('user_id', $user->id)->where('file_hash', $hash)->exists()) {
            return ['duplicate', null];
        }

        $name = $attachment['name'] ?? 'itinerary';
        $path = 'itineraries/' . $user->id . '/' . Str::uuid() . '-' . $name;
        Storage::disk('local')->put($path, $bytes);

        $itinerary = Itinerary::create([
            'user_id'           => $user->id,
            'original_filename' => $name,
            'file_path'         => $path,
            'file_size'         => strlen($bytes),
            'mime_type'         => $mime ?: 'application/pdf',
            'file_hash'         => $hash,
            'status'            => Itinerary::STATUS_UPLOADED,
        ]);

        // Only counts as created when the AI recognises an actual flight
        // itinerary; the stored file stays available for a manual re-parse.
        $ok = $this->parser->parse($itinerary);

        return [$ok ? 'created' : 'failed', $ok ? $itinerary : null];
    }

    /** @return array{0: ?string, 1: ?Itinerary} */
    private function processBody(User $user, ?string $subject, string $body): array
    {
        $hash = hash('sha256', $body);
        if (Itinerary::where('user_id', $user->id)->where('file_hash', $hash)->exists()) {
            return ['duplicate', null];
        }

        $path = 'itineraries/' . $user->id . '/' . Str::uuid() . '-email.txt';
        Storage::disk('local')->put($path, $body);

        $itinerary = Itinerary::create([
            'user_id'           => $user->id,
            'original_filename' => ($subject ?: 'Forwarded itinerary') . '.txt',
            'file_path'         => $path,
            'file_size'         => strlen($body),
            'mime_type'         => 'text/plain',
            'file_hash'         => $hash,
            'status'            => Itinerary::STATUS_UPLOADED,
        ]);

        $ok = $this->parser->parseFromText($itinerary, $body);

        return [$ok ? 'created' : 'failed', $ok ? $itinerary : null];
    }
}
