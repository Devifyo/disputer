<?php

namespace App\Livewire\Admin\FlightClaims;

use App\Models\Airline;
use App\Models\AirlineEmailTemplate;
use App\Models\Claim;
use App\Services\Audit\AdminActivity;
use App\Services\Claims\TemplateRenderer;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Livewire\Component;
use Livewire\WithPagination;

/**
 * Flight Claims -> Claim Templates: the letters an airline gets, per type.
 * Admins send one verbatim from the claim composer, or let the AI use it as
 * its base so the airline's own wording survives - the AI stays the default
 * route, these are the manual one.
 */
class ClaimTemplates extends Component
{
    use WithPagination;

    public string $search = '';
    public string $airlineFilter = 'all';
    public string $typeFilter = 'all';

    // Editor.
    public bool $showEditor = false;
    public ?int $editingId = null;
    public array $form = [];

    // Preview.
    public ?int $previewId = null;

    public function mount(): void
    {
        abort_unless(auth()->user()->can('claim_templates.manage'), 403);
    }

    public function updatingSearch(): void
    {
        $this->resetPage();
    }

    public function updatingAirlineFilter(): void
    {
        $this->resetPage();
    }

    public function updatingTypeFilter(): void
    {
        $this->resetPage();
    }

    // ── Editor ──────────────────────────────────────────────

    public function create(): void
    {
        $this->editingId = null;
        $this->form = [
            'airline_id' => $this->airlineFilter !== 'all' ? (int) $this->airlineFilter : null,
            'name'       => '',
            'type'       => AirlineEmailTemplate::TYPE_INITIAL,
            'subject'    => 'Compensation claim - {{flight_number}} on {{scheduled_departure}} [{{claim_reference}}]',
            'body'       => $this->starterBody(),
            'is_default' => false,
            'is_active'  => true,
        ];
        $this->showEditor = true;
        $this->resetErrorBag();
    }

    public function edit(int $id): void
    {
        $template = AirlineEmailTemplate::findOrFail($id);

        $this->editingId  = $template->id;
        $this->form       = $template->only(['airline_id', 'name', 'type', 'subject', 'body', 'is_default', 'is_active']);
        $this->showEditor = true;
        $this->resetErrorBag();
    }

    public function save(AdminActivity $activity): void
    {
        $data = $this->validate([
            'form.airline_id' => ['required', 'integer', 'exists:airlines,id'],
            'form.name'       => ['required', 'string', 'max:120'],
            'form.type'       => ['required', Rule::in(array_keys(AirlineEmailTemplate::TYPES))],
            'form.subject'    => ['required', 'string', 'max:190'],
            'form.body'       => ['required', 'string', 'min:40'],
            'form.is_default' => ['boolean'],
            'form.is_active'  => ['boolean'],
        ], [
            'form.body.min' => 'The letter looks too short to send to an airline.',
        ], [
            'form.airline_id' => 'airline', 'form.name' => 'template name', 'form.type' => 'template type',
            'form.subject' => 'subject', 'form.body' => 'body',
        ])['form'];

        $template = $this->editingId ? AirlineEmailTemplate::findOrFail($this->editingId) : new AirlineEmailTemplate();
        $old      = $this->editingId ? $template->only(['name', 'type', 'subject', 'body', 'is_default', 'is_active']) : null;

        DB::transaction(function () use ($template, $data, $activity, $old) {
            $template->fill($data);
            $template->updated_by = auth()->id();
            $template->created_by ??= auth()->id();
            $template->save();

            if ($data['is_default']) {
                $this->promoteDefault($template);
            }

            $activity->log(
                $template,
                $old ? AdminActivity::TEMPLATE_UPDATED : AdminActivity::TEMPLATE_CREATED,
                $old,
                $template->only(['name', 'type', 'subject', 'body', 'is_default', 'is_active']),
                $template->airline?->name,
            );
        });

        $this->showEditor = false;
        $this->dispatch('toast', ['type' => 'success', 'message' => $old ? 'Template updated.' : 'Template created.']);
    }

    public function duplicate(int $id, AdminActivity $activity): void
    {
        $source = AirlineEmailTemplate::findOrFail($id);

        $copy = $source->replicate(['created_by', 'updated_by']);
        $copy->name       = Str::limit($source->name, 105, '') . ' (copy)';
        $copy->is_default = false;
        $copy->is_active  = false;   // a copy is a draft until the admin says otherwise
        $copy->created_by = auth()->id();
        $copy->updated_by = auth()->id();
        $copy->save();

        $activity->log($copy, AdminActivity::TEMPLATE_DUPLICATED, null, ['copied_from' => $source->id], $source->name);

        $this->edit($copy->id);
        $this->dispatch('toast', ['type' => 'success', 'message' => 'Copy created - edit and activate it when ready.']);
    }

    public function setDefault(int $id, AdminActivity $activity): void
    {
        $template = AirlineEmailTemplate::findOrFail($id);

        DB::transaction(function () use ($template, $activity) {
            $this->promoteDefault($template);
            $activity->log($template, AdminActivity::TEMPLATE_DEFAULTED, null, null, $template->airline?->name);
        });

        $this->dispatch('toast', ['type' => 'success', 'message' => "\"{$template->name}\" is now the default {$template->typeLabel()} letter."]);
    }

    public function toggleActive(int $id, AdminActivity $activity): void
    {
        $template = AirlineEmailTemplate::findOrFail($id);

        $template->forceFill(['is_active' => !$template->is_active, 'updated_by' => auth()->id()])->save();
        $activity->log($template, AdminActivity::TEMPLATE_TOGGLED, null, ['is_active' => $template->is_active]);

        $this->dispatch('toast', ['type' => 'success', 'message' => $template->is_active ? 'Template enabled.' : 'Template disabled.']);
    }

    public function delete(int $id, AdminActivity $activity): void
    {
        abort_unless(auth()->user()->can('claim_templates.delete'), 403);

        $template = AirlineEmailTemplate::findOrFail($id);
        $activity->log($template, AdminActivity::TEMPLATE_DELETED, $template->only(['name', 'type', 'subject', 'body']), null, $template->airline?->name);

        // Sent emails keep their history: the FK nulls, the record stays.
        $template->delete();

        $this->dispatch('toast', ['type' => 'success', 'message' => 'Template deleted.']);
    }

    /** Exactly one default per airline and type. */
    private function promoteDefault(AirlineEmailTemplate $template): void
    {
        AirlineEmailTemplate::where('airline_id', $template->airline_id)
            ->where('type', $template->type)
            ->whereKeyNot($template->id)
            ->update(['is_default' => null]);

        $template->forceFill(['is_default' => 1])->save();
    }

    private function starterBody(): string
    {
        return <<<'TEXT'
        Dear {{airline_name}} Claims Team,

        I am writing on behalf of {{passenger_name}} regarding flight {{flight_number}} from {{departure_airport}} to {{arrival_airport}}, scheduled to depart on {{scheduled_departure}}.

        Booking reference: {{booking_reference}}
        Our claim reference: {{claim_reference}}

        The flight was disrupted and our passenger is entitled to compensation of {{compensation_amount}} under {{regulation}}, {{article}}.

        Please confirm receipt of this claim and advise on payment within 30 days.

        Yours faithfully,
        Unjamm Claims Team
        {{today_date}}
        TEXT;
    }

    public function render(TemplateRenderer $renderer)
    {
        $templates = AirlineEmailTemplate::with(['airline', 'author'])
            ->when($this->search !== '', function ($q) {
                $term = '%' . trim($this->search) . '%';
                $q->where(fn ($w) => $w->where('name', 'like', $term)
                    ->orWhere('subject', 'like', $term)
                    ->orWhereHas('airline', fn ($a) => $a->where('name', 'like', $term)->orWhere('iata_code', 'like', $term)));
            })
            ->when($this->airlineFilter !== 'all', fn ($q) => $q->where('airline_id', (int) $this->airlineFilter))
            ->when($this->typeFilter !== 'all', fn ($q) => $q->where('type', $this->typeFilter))
            ->orderBy('airline_id')
            ->orderByDesc('is_default')
            ->orderBy('type')
            ->paginate(15);

        // Preview against a real claim for that airline when there is one -
        // an admin should see the letter as the airline will read it.
        $preview = null;
        if ($this->previewId && $template = AirlineEmailTemplate::with('airline')->find($this->previewId)) {
            $claim = Claim::where('airline', 'like', '%' . $template->airline?->name . '%')->latest()->first()
                ?: Claim::latest()->first();

            $preview = [
                'template' => $template,
                'claim'    => $claim,
                'rendered' => $claim ? $renderer->renderTemplate($template, $claim) : null,
                'unknown'  => $renderer->unknownVariables($template->subject . ' ' . $template->body),
            ];
        }

        return view('livewire.admin.flight-claims.claim-templates', [
                'templates' => $templates,
                'airlines'  => Airline::orderBy('name')->get(),
                'types'     => AirlineEmailTemplate::TYPES,
                'variables' => TemplateRenderer::VARIABLES,
                'preview'   => $preview,
                'canDelete' => auth()->user()->can('claim_templates.delete'),
            ])
            ->extends('layouts.admin')
            ->section('content');
    }
}
