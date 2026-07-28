<?php

namespace App\Services\Claims;

use App\Mail\AirlineClaimMail;
use App\Models\Claim;
use App\Models\ClaimCorrespondence;
use App\Services\Notifications\AdminNotifier;
use App\Support\Alerts\AdminAlert;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Outbound claim emails to airlines and the inbound replies they send back.
 *
 * Everything goes out from the one public claims address; threading works
 * because each email carries a per-claim reply-to token
 * (claims+clm-xxxxxxxx@<inbound-parse-host>) and the claim reference in the
 * subject. Replies to either land on the claims inbound webhook, which asks
 * matchInbound() whose claim they belong to before falling back to the
 * customer itinerary flow.
 */
class ClaimCorrespondenceService
{
    /** Send the composed email to the airline and record it on the claim. */
    public function send(Claim $claim, string $to, string $subject, string $body, array $attachmentKeys, ?int $adminId): ClaimCorrespondence
    {
        $subject = $this->taggedSubject($claim, $subject);
        $files   = $this->files($claim, $attachmentKeys);

        Mail::to($to)->send(new AirlineClaimMail($claim, $subject, $body, $files));

        $record = $claim->correspondence()->create([
            'direction'   => ClaimCorrespondence::DIRECTION_OUTBOUND,
            'from_email'  => config('services.inbound.claims_display'),
            'from_name'   => 'Unjamm Claims',
            'to_email'    => $to,
            'subject'     => $subject,
            'body'        => $body,
            'attachments' => collect($files)->map(fn ($f) => ['name' => $f['name'], 'key' => $f['key']])->all(),
            'sent_by'     => $adminId,
        ]);

        app(ClaimWorkflowService::class)->audit(
            $claim, "Claim email sent to {$to}", 'admin', $adminId,
            $subject . ' (' . count($files) . ' attachment' . (count($files) === 1 ? '' : 's') . ')'
        );

        return $record;
    }

    /**
     * Which claim does an inbound email belong to? The reply-to token wins;
     * the claim reference anywhere in the subject is the fallback (airlines
     * that reply to the visible from-address instead of the reply-to).
     *
     * @return array{0: ?Claim, 1: ?string} [claim, matched_by]
     */
    public function matchInbound(string $recipients, ?string $subject): array
    {
        if (preg_match('/claims\+(clm-[a-z0-9]+)@/i', $recipients, $m)) {
            $claim = Claim::where('reference', strtoupper($m[1]))->first();
            if ($claim) {
                return [$claim, 'reply_token'];
            }
        }

        if ($subject && preg_match('/\b(CLM-[A-Z0-9]{8})\b/i', $subject, $m)) {
            $claim = Claim::where('reference', strtoupper($m[1]))->first();
            if ($claim) {
                return [$claim, 'subject_reference'];
            }
        }

        return [null, null];
    }

    /**
     * Store an airline reply on its claim: files to disk, an immutable
     * correspondence record, an audit entry, and an admin alert. Customers
     * never see airline correspondence.
     *
     * @param array<int, array{name: string, mime: string, bytes: string}> $attachments
     */
    public function recordInbound(Claim $claim, string $matchedBy, string $fromEmail, ?string $fromName, ?string $subject, ?string $text, ?string $html, array $attachments = []): ClaimCorrespondence
    {
        $stored = [];
        foreach ($attachments as $file) {
            if ($file['bytes'] === '') {
                continue;
            }
            $path = "claims/{$claim->user_id}/inbound/" . Str::random(20) . '-' . Str::slug(pathinfo($file['name'], PATHINFO_FILENAME));
            $ext  = pathinfo($file['name'], PATHINFO_EXTENSION);
            $path .= $ext ? ".{$ext}" : '';
            Storage::disk('local')->put($path, $file['bytes']);
            $stored[] = ['name' => $file['name'], 'path' => $path, 'mime' => $file['mime']];
        }

        $body = trim((string) $text) !== '' ? trim((string) $text) : trim(strip_tags((string) $html));

        $record = $claim->correspondence()->create([
            'direction'   => ClaimCorrespondence::DIRECTION_INBOUND,
            'from_email'  => $fromEmail,
            'from_name'   => $fromName,
            'to_email'    => $claim->replyAddress(),
            'subject'     => $subject,
            'body'        => $body,
            'attachments' => $stored,
            'matched_by'  => $matchedBy,
        ]);

        app(ClaimWorkflowService::class)->audit(
            $claim, "Airline email received from {$fromEmail}", 'airline', null,
            (string) $subject
        );

        $this->alertAdmins($claim, $record);

        return $record;
    }

    /** The claim reference makes every subject threadable - append it once. */
    private function taggedSubject(Claim $claim, string $subject): string
    {
        if (Str::contains(Str::upper($subject), Str::upper($claim->reference))) {
            return $subject;
        }

        return trim($subject) . " [Ref: {$claim->reference}]";
    }

    /** @return array<int, array{key: string, name: string, path: string}> */
    private function files(Claim $claim, array $keys): array
    {
        $files = [];

        foreach (array_values(array_unique($keys)) as $key) {
            $path = $claim->documentPath($key);
            if (!$path || !Storage::disk('local')->exists($path)) {
                continue;
            }
            $files[] = ['key' => $key, 'name' => $this->fileName($claim, $key, $path), 'path' => $path];
        }

        return $files;
    }

    private function fileName(Claim $claim, string $key, string $path): string
    {
        $ext = pathinfo($path, PATHINFO_EXTENSION) ?: 'pdf';

        return match (true) {
            $key === 'assignment'           => "Assignment-of-Claims-{$claim->reference}.{$ext}",
            $key === 'itinerary'            => $claim->itinerary?->original_filename ?: "Booking-{$claim->reference}.{$ext}",
            str_starts_with($key, 'poa-')   => $this->poaName($claim, (int) substr($key, 4), $ext),
            // Receipts arrive named IMG_1234.jpg - label them for the airline.
            str_starts_with($key, 'expense-') => $this->receiptName($claim, (int) substr($key, 8), $ext),
            str_starts_with($key, 'doc-')   => $claim->documents[(int) substr($key, 4)]['name'] ?? basename($path),
            str_starts_with($key, 'extra-') => $claim->airline_letter['extra'][(int) substr($key, 6)]['name'] ?? basename($path),
            default                         => basename($path),
        };
    }

    /** Names the passenger the authorisation covers, falling back to the signer. */
    private function poaName(Claim $claim, int $signerId, string $ext): string
    {
        $signer = $claim->signers()->find($signerId);
        $who    = $signer?->signs_for ?: $signer?->name;

        return 'Power-of-Attorney-' . Str::slug($who ?: 'passenger') . ".{$ext}";
    }

    /**
     * "Receipt-hotel-accommodation-CAD180.00.pdf" - self-explanatory to the
     * airline. The amount is NEVER slugged: Str::slug would strip the
     * decimal point and turn CAD 180.00 into "cad-18000".
     */
    private function receiptName(Claim $claim, int $expenseId, string $ext): string
    {
        $expense = $claim->expenses()->find($expenseId);

        if (!$expense) {
            return "Receipt.{$ext}";
        }

        $amount = $expense->amount === null
            ? ''
            : '-' . ($expense->currency ?: '') . number_format((float) $expense->amount, 2);

        return 'Receipt-' . Str::slug($expense->categoryLabel()) . $amount . ".{$ext}";
    }

    private function alertAdmins(Claim $claim, ClaimCorrespondence $record): void
    {
        $from = trim(($record->from_name ?: '') . ' <' . $record->from_email . '>');
        $url  = route('admin.flight-claims.claims.show', $claim);

        app(AdminNotifier::class)->send(new AdminAlert(
            type: AdminAlertRecipients::TYPE_AIRLINE_REPLY,
            title: sprintf('New airline reply on claim #%s', $claim->number),
            description: sprintf('%s - %s', $from, Str::limit((string) ($record->subject ?: $record->body), 120)),
            url: $url,
            template: 'claim-airline-reply-alert',
            replacements: [
                '[CLAIM]'     => '#' . $claim->number,
                '[FROM]'      => $from,
                '[FLIGHT]'    => trim(($claim->airline ?? '') . ' ' . ($claim->flight_number ?? '')),
                '[ROUTE]'     => "{$claim->departure_airport} - {$claim->arrival_airport}",
                '[SUBJECT]'   => (string) $record->subject,
                '[PREVIEW]'   => Str::limit((string) $record->body, 400),
                '[CLAIM_URL]' => $url,
            ],
        ));
    }
}
