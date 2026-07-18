<?php

namespace App\Livewire\Admin\FlightClaims;

use App\Models\AirlineContact;
use App\Models\ClaimLifecycleStage;
use App\Models\ClaimWorkflow;
use Illuminate\Validation\Rule;
use Livewire\Component;

/**
 * Flight Claims -> Lifecycle Management: the configurable claim workflow.
 * Admins create/edit/reorder stages, wire transitions, timers, visibility
 * and automation - new stages slot into the flow without code changes.
 * System stages stay protected: keys locked, cannot be deactivated/deleted.
 */
class Lifecycle extends Component
{
    public int $workflowId = 0;
    public ?int $editingId = null;
    public bool $showForm = false;
    public bool $showPreview = false;
    public bool $showWorkflowForm = false;

    public array $form = [];
    public array $workflowForm = ['name' => '', 'description' => ''];

    public function mount(): void
    {
        $this->workflowId = ClaimWorkflow::defaultId();
    }

    public function switchWorkflow(int $id): void
    {
        abort_unless(ClaimWorkflow::whereKey($id)->exists(), 404);
        $this->workflowId = $id;
        $this->showForm   = false;
    }

    /** New workflow = a copy of the default's stages, ready to customise. */
    public function createWorkflow(): void
    {
        $this->validate(
            ['workflowForm.name' => 'required|string|max:80', 'workflowForm.description' => 'nullable|string|max:300'],
            [], ['workflowForm.name' => 'workflow name']
        );

        $copy = ClaimWorkflow::find(ClaimWorkflow::defaultId())
            ->duplicateAs(trim($this->workflowForm['name']), $this->workflowForm['description'] ?: null);

        $this->workflowForm     = ['name' => '', 'description' => ''];
        $this->showWorkflowForm = false;
        $this->workflowId       = $copy->id;
        $this->dispatch('toast', ['type' => 'success', 'message' => "Workflow \"{$copy->name}\" created from the default lifecycle."]);
    }

    public function setDefaultWorkflow(int $id): void
    {
        ClaimWorkflow::query()->update(['is_default' => false]);
        ClaimWorkflow::whereKey($id)->update(['is_default' => true]);
        ClaimWorkflow::first()?->touch(); // flush the cached default id

        $this->dispatch('toast', ['type' => 'success', 'message' => 'Default workflow updated.']);
    }

    public function deleteWorkflow(int $id): void
    {
        $workflow = ClaimWorkflow::withCount('airlines')->findOrFail($id);

        if ($workflow->is_default) {
            $this->dispatch('toast', ['type' => 'error', 'message' => 'The default workflow cannot be deleted.']);

            return;
        }

        $workflow->delete(); // attached airlines fall back to the default (FK null)
        $this->workflowId = ClaimWorkflow::defaultId();
        $this->dispatch('toast', ['type' => 'success', 'message' => "Workflow deleted{$this->airlinesFallbackNote($workflow->airlines_count)}."]);
    }

    private function airlinesFallbackNote(int $count): string
    {
        return $count > 0 ? " - {$count} airline(s) now follow the default workflow" : '';
    }

    private function blankForm(): array
    {
        return [
            'key' => '', 'name' => '', 'description' => '', 'color' => 'slate', 'icon' => 'circle',
            'is_active' => true, 'is_initial' => false, 'is_final' => false,
            'customer_visible' => true, 'customer_label' => '', 'admin_visible' => true,
            'allow_manual' => true, 'allow_auto' => false,
            'auto_delay_days' => null, 'auto_next_stage' => '',
            'notify_admin' => false, 'notify_customer' => false,
            'ai_action' => '', 'permissions' => '', 'airline_contact_purpose' => '',
            'next_stages' => [], 'notes' => '',
        ];
    }

    public function create(): void
    {
        $this->editingId = null;
        $this->form      = $this->blankForm();
        $this->showForm  = true;
    }

    public function edit(int $id): void
    {
        $stage = ClaimLifecycleStage::findOrFail($id);

        $this->editingId = $id;
        $this->form      = array_merge($this->blankForm(), $stage->only(array_keys($this->blankForm())));
        $this->form['auto_next_stage'] = $stage->auto_next_stage ?? '';
        $this->form['ai_action']       = $stage->ai_action ?? '';
        $this->form['permissions']     = implode(', ', $stage->permissions ?? []);
        $this->form['airline_contact_purpose'] = $stage->airline_contact_purpose ?? '';
        $this->showForm  = true;
    }

    public function save(): void
    {
        $stage = $this->editingId ? ClaimLifecycleStage::findOrFail($this->editingId) : null;

        $this->validate([
            'form.name'            => 'required|string|max:60',
            'form.key'             => ['required', 'string', 'max:60', 'regex:/^[a-z0-9_]+$/',
                Rule::unique('claim_lifecycle_stages', 'key')
                    ->where('claim_workflow_id', $this->workflowId)
                    ->ignore($this->editingId)],
            'form.description'     => 'nullable|string|max:500',
            'form.color'           => 'required|in:' . implode(',', array_keys(ClaimLifecycleStage::COLORS)),
            'form.icon'            => 'required|string|max:40',
            'form.customer_label'  => 'nullable|string|max:120',
            'form.auto_delay_days' => 'nullable|integer|min:0|max:365',
            'form.auto_next_stage' => 'nullable|string',
            'form.ai_action'       => 'nullable|in:,airline_claim,follow_up,regulator_complaint',
            'form.airline_contact_purpose' => 'nullable|in:,' . implode(',', array_keys(AirlineContact::PURPOSES)),
            'form.permissions'     => 'nullable|string|max:190',
            'form.next_stages'     => 'array',
            'form.notes'           => 'nullable|string|max:500',
        ], [], ['form.key' => 'internal name', 'form.name' => 'display name']);

        $data = $this->form;
        $data['auto_next_stage'] = $data['auto_next_stage'] ?: null;
        $data['auto_delay_days'] = $data['auto_delay_days'] !== null && $data['auto_delay_days'] !== '' ? (int) $data['auto_delay_days'] : null;
        $data['customer_label']  = $data['customer_label'] ?: null;
        $data['ai_action']       = $data['ai_action'] ?: null;
        $data['airline_contact_purpose'] = $data['airline_contact_purpose'] ?: null;
        $data['permissions']     = array_values(array_filter(array_map('trim', explode(',', (string) $data['permissions'])))) ?: null;
        $data['next_stages']     = array_values(array_filter($data['next_stages']));

        // System stages keep their identity and can never be switched off.
        if ($stage?->is_system) {
            $data['key']       = $stage->key;
            $data['is_active'] = true;
        }

        // A single initial stage per workflow.
        if (!empty($data['is_initial'])) {
            ClaimLifecycleStage::where('claim_workflow_id', $this->workflowId)
                ->where('id', '!=', $this->editingId ?? 0)->update(['is_initial' => false]);
        }

        if ($stage) {
            $stage->update($data);
        } else {
            $data['claim_workflow_id'] = $this->workflowId;
            $data['sort']      = (int) ClaimLifecycleStage::where('claim_workflow_id', $this->workflowId)->max('sort') + 10;
            $data['is_system'] = false;
            ClaimLifecycleStage::create($data);
        }

        $this->showForm = false;
        $this->dispatch('toast', ['type' => 'success', 'message' => 'Lifecycle stage saved.']);
    }

    public function toggleActive(int $id): void
    {
        $stage = ClaimLifecycleStage::findOrFail($id);

        if ($stage->is_system) {
            $this->dispatch('toast', ['type' => 'error', 'message' => 'System stages cannot be deactivated - the workflow depends on them.']);

            return;
        }

        $stage->update(['is_active' => !$stage->is_active]);
        $this->dispatch('toast', ['type' => 'success', 'message' => "Stage {$stage->name} " . ($stage->is_active ? 'activated' : 'deactivated') . '.']);
    }

    public function delete(int $id): void
    {
        $stage = ClaimLifecycleStage::findOrFail($id);

        if ($stage->is_system) {
            $this->dispatch('toast', ['type' => 'error', 'message' => 'System stages cannot be deleted.']);

            return;
        }

        // Detach it from every transition list before removing.
        foreach (ClaimLifecycleStage::all_cached($this->workflowId) as $other) {
            if (in_array($stage->key, $other->next_stages ?? [], true)) {
                $other->update(['next_stages' => array_values(array_diff($other->next_stages, [$stage->key]))]);
            }
        }

        $stage->delete();
        $this->dispatch('toast', ['type' => 'success', 'message' => 'Stage deleted.']);
    }

    /** Drag-and-drop reorder: receives the stage ids in their new order. */
    public function reorder(array $orderedIds): void
    {
        foreach (array_values($orderedIds) as $index => $id) {
            ClaimLifecycleStage::where('id', (int) $id)->update(['sort' => ($index + 1) * 10]);
        }

        $this->dispatch('toast', ['type' => 'success', 'message' => 'Stage order updated.']);
    }

    public function render()
    {
        return view('livewire.admin.flight-claims.lifecycle', [
                'stages'    => ClaimLifecycleStage::where('claim_workflow_id', $this->workflowId)->orderBy('sort')->get(),
                'colors'    => ClaimLifecycleStage::COLORS,
                'workflows' => ClaimWorkflow::withCount('airlines')->orderByDesc('is_default')->orderBy('name')->get(),
                'workflow'  => ClaimWorkflow::find($this->workflowId),
            ])
            ->extends('layouts.admin')
            ->section('content');
    }
}
