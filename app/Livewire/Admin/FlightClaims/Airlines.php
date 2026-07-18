<?php

namespace App\Livewire\Admin\FlightClaims;

use App\Models\Airline;
use App\Models\AirlineContact;
use App\Models\ClaimWorkflow;
use Illuminate\Support\Str;
use Livewire\Component;
use Livewire\WithPagination;

/**
 * Flight Claims -> Airlines: the carrier directory. Each airline carries
 * purpose-based contact emails (claims, legal, escalation...) so lifecycle
 * stages can route outbound communications to the right inbox per carrier.
 */
class Airlines extends Component
{
    use WithPagination;

    public string $search = '';
    public ?int $editingId = null;
    public bool $showForm = false;

    public array $form = [];

    public function updatingSearch(): void
    {
        $this->resetPage();
    }

    private function blankForm(): array
    {
        return [
            'name'              => '',
            'iata_code'         => '',
            'is_active'         => true,
            'claim_workflow_id' => '',
            'notes'             => '',
            'contacts'          => $this->contactRows(),
        ];
    }

    /**
     * One row per contact purpose: the four standard purposes always show;
     * any additional custom purposes the airline has follow, and the admin
     * can add more.
     */
    private function contactRows(array $existing = []): array
    {
        $rows = collect(AirlineContact::PURPOSES)->map(fn ($label, $purpose) => [
            'purpose' => $purpose,
            'custom'  => false,
            'email'   => $existing[$purpose]['email'] ?? '',
            'label'   => $existing[$purpose]['label'] ?? '',
        ])->values();

        foreach ($existing as $purpose => $contact) {
            if (!array_key_exists($purpose, AirlineContact::PURPOSES)) {
                $rows->push(['purpose' => $purpose, 'custom' => true, 'email' => $contact['email'] ?? '', 'label' => $contact['label'] ?? '']);
            }
        }

        return $rows->all();
    }

    /** Append a blank custom contact row (e.g. refunds desk, baggage). */
    public function addContact(): void
    {
        $this->form['contacts'][] = ['purpose' => '', 'custom' => true, 'email' => '', 'label' => ''];
    }

    public function removeContact(int $index): void
    {
        if (($this->form['contacts'][$index]['custom'] ?? false)) {
            unset($this->form['contacts'][$index]);
            $this->form['contacts'] = array_values($this->form['contacts']);
        }
    }

    public function create(): void
    {
        $this->editingId = null;
        $this->form      = $this->blankForm();
        $this->showForm  = true;
    }

    public function edit(int $id): void
    {
        $airline = Airline::with('contacts')->findOrFail($id);

        $this->editingId = $id;
        $this->form      = [
            'name'              => $airline->name,
            'iata_code'         => $airline->iata_code,
            'is_active'         => $airline->is_active,
            'claim_workflow_id' => $airline->claim_workflow_id ?? '',
            'notes'             => $airline->notes,
            'contacts'          => $this->contactRows(
                $airline->contacts->keyBy('purpose')->map(fn ($c) => ['email' => $c->email, 'label' => $c->label])->all()
            ),
        ];
        $this->showForm = true;
    }

    public function save(): void
    {
        $this->validate([
            'form.name'               => 'required|string|max:120',
            'form.iata_code'          => 'nullable|string|min:2|max:3|unique:airlines,iata_code,' . ($this->editingId ?? 'NULL'),
            'form.claim_workflow_id'  => 'nullable|exists:claim_workflows,id',
            'form.notes'              => 'nullable|string|max:500',
            'form.contacts.*.purpose' => 'nullable|string|max:60',
            'form.contacts.*.email'   => 'nullable|email|max:190',
            'form.contacts.*.label'   => 'nullable|string|max:120',
        ], [], ['form.name' => 'airline name', 'form.iata_code' => 'IATA code', 'form.contacts.*.email' => 'contact email']);

        $airline = Airline::updateOrCreate(['id' => $this->editingId], [
            'name'              => trim($this->form['name']),
            'iata_code'         => strtoupper(trim($this->form['iata_code'] ?? '')) ?: null,
            'is_active'         => (bool) $this->form['is_active'],
            'claim_workflow_id' => $this->form['claim_workflow_id'] ?: null,
            'notes'             => $this->form['notes'] ?: null,
        ]);

        // Sync: rows with an address (and, for custom rows, a name) survive;
        // everything else is removed.
        $final = collect($this->form['contacts'])
            ->map(function ($contact) {
                $purpose = ($contact['custom'] ?? false)
                    ? Str::slug(trim($contact['purpose'] ?? ''), '_')
                    : $contact['purpose'];
                $email = trim($contact['email'] ?? '');

                return $purpose && $email !== ''
                    ? ['purpose' => $purpose, 'email' => $email, 'label' => trim($contact['label'] ?? '') ?: null]
                    : null;
            })
            ->filter()
            ->keyBy('purpose');

        $airline->contacts()->whereNotIn('purpose', $final->keys())->delete();
        foreach ($final as $contact) {
            $airline->contacts()->updateOrCreate(['purpose' => $contact['purpose']], $contact);
        }

        $this->showForm = false;
        $this->dispatch('toast', ['type' => 'success', 'message' => "Airline {$airline->name} saved."]);
    }

    public function toggleActive(int $id): void
    {
        $airline = Airline::findOrFail($id);
        $airline->update(['is_active' => !$airline->is_active]);

        $this->dispatch('toast', ['type' => 'success', 'message' => "{$airline->name} " . ($airline->is_active ? 'activated' : 'deactivated') . '.']);
    }

    public function render()
    {
        $airlines = Airline::with('contacts')
            ->when($this->search !== '', function ($q) {
                $s = '%' . trim($this->search) . '%';
                $q->where(fn ($w) => $w->where('name', 'like', $s)
                    ->orWhere('iata_code', 'like', $s)
                    ->orWhereHas('contacts', fn ($c) => $c->where('email', 'like', $s)));
            })
            ->orderBy('name')
            ->paginate(15);

        return view('livewire.admin.flight-claims.airlines', [
                'airlines'  => $airlines->setCollection($airlines->getCollection()->load('workflow')),
                'purposes'  => AirlineContact::PURPOSES,
                'workflows' => ClaimWorkflow::where('is_active', true)->orderByDesc('is_default')->orderBy('name')->get(),
            ])
            ->extends('layouts.admin')
            ->section('content');
    }
}
