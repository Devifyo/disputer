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

        foreach ($attachments as $attachment) {
            $result = $this->processAttachment($user, $attachment);
            $created    += $result === 'created' ? 1 : 0;
            $duplicates += $result === 'duplicate' ? 1 : 0;
        }

        // Fall back to the email body for inline-forwarded itineraries.
        if ($created === 0 && $duplicates === 0) {
            $body = (string) ($text ?: strip_tags((string) $html));
            if (trim($body) !== '') {
                $result = $this->processBody($user, $subject, $body);
                $created    += $result === 'created' ? 1 : 0;
                $duplicates += $result === 'duplicate' ? 1 : 0;
            }
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

    private function processAttachment(User $user, array $attachment): ?string
    {
        $mime  = (string) ($attachment['mime'] ?? '');
        $bytes = $attachment['bytes'] ?? null;

        if (!is_string($bytes) || $bytes === '' || !(str_contains($mime, 'pdf') || str_starts_with($mime, 'image/'))) {
            return null;
        }

        $hash = hash('sha256', $bytes);
        if (Itinerary::where('user_id', $user->id)->where('file_hash', $hash)->exists()) {
            return 'duplicate';
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

        $this->parser->parse($itinerary);

        return 'created';
    }

    private function processBody(User $user, ?string $subject, string $body): ?string
    {
        $hash = hash('sha256', $body);
        if (Itinerary::where('user_id', $user->id)->where('file_hash', $hash)->exists()) {
            return 'duplicate';
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

        $this->parser->parseFromText($itinerary, $body);

        return 'created';
    }
}
