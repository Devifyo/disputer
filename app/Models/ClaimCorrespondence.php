<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ClaimCorrespondence extends Model
{
    public const DIRECTION_OUTBOUND = 'outbound';
    public const DIRECTION_INBOUND  = 'inbound';

    public const STATUS_SENT      = 'sent';
    public const STATUS_SCHEDULED = 'scheduled';
    public const STATUS_FAILED    = 'failed';

    protected $table = 'claim_correspondence';

    protected $fillable = [
        'claim_id', 'direction', 'from_email', 'from_name', 'to_email', 'cc', 'bcc',
        'subject', 'body', 'attachments', 'matched_by', 'sent_by',
        'template_id', 'ai_generated', 'status', 'scheduled_at',
    ];

    protected $casts = [
        'attachments'  => 'array',
        'cc'           => 'array',
        'bcc'          => 'array',
        'ai_generated' => 'boolean',
        'scheduled_at' => 'datetime',
    ];

    public function template(): BelongsTo
    {
        return $this->belongsTo(AirlineEmailTemplate::class, 'template_id');
    }

    /** How this letter was produced, for the history list. */
    public function originLabel(): string
    {
        return match (true) {
            $this->direction === self::DIRECTION_INBOUND => 'Received',
            $this->ai_generated                          => 'AI draft',
            (bool) $this->template_id                    => 'Template',
            default                                      => 'Written by hand',
        };
    }

    public function claim(): BelongsTo
    {
        return $this->belongsTo(Claim::class);
    }

    /** The reply's own text - quoted history starts at the first quote marker. */
    public function newBody(): string
    {
        $new = trim(substr((string) $this->body, 0, $this->quoteOffset()));

        return $new !== '' ? $new : trim((string) $this->body);
    }

    /** The quoted chain below the reply, when present ("On ... wrote:", "> ..."). */
    public function quotedBody(): ?string
    {
        $quoted = trim(substr((string) $this->body, $this->quoteOffset()));

        return $quoted !== '' && $quoted !== $this->newBody() ? $quoted : null;
    }

    private function quoteOffset(): int
    {
        $body    = (string) $this->body;
        $offsets = [];
        foreach (['/^On .{5,200}wrote:\s*$/mu', '/^-{2,}\s*Original Message/mi', '/^>/m'] as $pattern) {
            if (preg_match($pattern, $body, $m, PREG_OFFSET_CAPTURE)) {
                $offsets[] = $m[0][1];
            }
        }

        return $offsets ? min($offsets) : strlen($body);
    }

    public function sender(): BelongsTo
    {
        return $this->belongsTo(User::class, 'sent_by');
    }
}
