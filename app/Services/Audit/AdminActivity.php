<?php

namespace App\Services\Audit;

use App\Models\AdminActivityLog;
use Illuminate\Database\Eloquent\Model;

/**
 * The one way admin actions get written to the activity trail. Callers say
 * what happened; this decides what gets stored (actor, IP, before/after).
 */
class AdminActivity
{
    public const TEMPLATE_CREATED    = 'template_created';
    public const TEMPLATE_UPDATED    = 'template_updated';
    public const TEMPLATE_DELETED    = 'template_deleted';
    public const TEMPLATE_DUPLICATED = 'template_duplicated';
    public const TEMPLATE_DEFAULTED  = 'template_set_default';
    public const TEMPLATE_TOGGLED    = 'template_toggled';
    public const AIRLINE_CREATED     = 'airline_created';
    public const AIRLINE_UPDATED     = 'airline_updated';
    public const AIRLINE_DELETED     = 'airline_deleted';

    public function log(
        Model $subject,
        string $action,
        ?array $old = null,
        ?array $new = null,
        ?string $notes = null,
    ): AdminActivityLog {
        return AdminActivityLog::create([
            'subject_type' => $subject->getMorphClass(),
            'subject_id'   => $subject->getKey(),
            'action'       => $action,
            'actor_id'     => auth()->id(),
            'ip'           => request()?->ip(),
            'old_values'   => $old,
            'new_values'   => $new,
            'notes'        => $notes,
        ]);
    }
}
