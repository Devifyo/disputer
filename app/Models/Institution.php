<?php

namespace App\Models;
use Illuminate\Database\Eloquent\SoftDeletes;

use Illuminate\Database\Eloquent\Model;

class Institution extends Model
{   
    use SoftDeletes;
    
   protected $fillable = [
        'name',
        'institution_category_id', // Updated FK
        'contact_email',
        'is_verified',
        'parent_id',
        'escalation_email',
        'escalation_contact_name'
    ];

    public function category()
    {
        return $this->belongsTo(InstitutionCategory::class, 'institution_category_id');
    }
    public function cases()
    {
        return $this->hasMany(Cases::class);
    }

    public function parent()
    {
        return $this->belongsTo(Institution::class, 'parent_id');
    }

    public function contacts()
    {
        return $this->hasMany(InstitutionContact::class);
    }

    /**
     * Helper to get the correct contact for the app's current dispute stage.
     */
    public function getContactForStage(int $stage)
    {
        return $this->contacts()
            ->where('step_key', $stage)
            ->where('is_primary', true)
            ->get(); 
    }

    /**
     * Resolves the contact for a specific workflow step.
     * @param string $stepKey
     * @param bool $onlyEmails - If true, ignores URLs/Portals
     */
    public function getStepRecipient(string $stepKey, bool $onlyEmails = false): ?array
    {
        // 1. Build the query for specific dynamic routing
        $query = $this->contacts()->where('step_key', $stepKey);

        // If onlyEmails is requested, restrict the channel
        if ($onlyEmails) {
            $query->where('channel', 'email');
        }

        $specificContact = $query->orderBy('is_primary', 'desc')->first();

        if ($specificContact && !empty($specificContact->contact_value)) {
            return [
                'type'  => in_array($specificContact->channel, ['url', 'portal']) ? 'url' : 'email',
                'value' => $specificContact->contact_value,
                'label' => $specificContact->department_name
            ];
        }

        // 2. Fallback Waterfall (These are always emails)
        if (!empty($this->contact_email)) {
            return [
                'type'  => 'email',
                'value' => $this->contact_email,
                'label' => 'Customer Service'
            ];
        }

        if ($this->category && !empty($this->category->fallback_escalation_email)) {
            return [
                'type'  => 'email',
                'value' => $this->category->fallback_escalation_email,
                'label' => 'General Support'
            ];
        }

        return null;
    }
}
