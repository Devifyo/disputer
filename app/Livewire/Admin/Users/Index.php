<?php

namespace App\Livewire\Admin\Users;

use Livewire\Component;
use Livewire\WithPagination;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;
use App\Livewire\Admin\Users\Traits\ManagesEmailConfig;

class Index extends Component
{
    use WithPagination;
    use ManagesEmailConfig;

    public $showModal = false;
    public $activeTab = 'basic'; 
    public $isEditMode = false;

    // --- NEW: Filter Properties ---
    public $search = '';
    public $filterConfig = ''; // Options: '', 'configured', 'unconfigured'

    // User Properties
    public $user_id, $name, $email, $password, $role = 'user';

    // Reset pagination when searching or filtering
    public function updatingSearch() { $this->resetPage(); }
    public function updatingFilterConfig() { $this->resetPage(); }

    protected function rules()
    {
        $basicRules = [
            'name' => 'required|min:3|max:255',
            'email' => ['required', 'email', Rule::unique('users')->ignore($this->user_id)],
            'password' => $this->isEditMode ? 'nullable|min:6' : 'required|min:6',
            'role' => 'required|in:admin,user',
        ];

        return array_merge($basicRules, $this->getEmailConfigRules());
    }

    protected function messages()
    {
        $basicMessages = [
            'name.required' => 'Please enter the user\'s full name.',
            'email.required' => 'An email address is required.',
            'email.unique' => 'This email is already registered.',
            'password.required' => 'A password is required for new users.',
        ];

        return array_merge($basicMessages, $this->getEmailConfigMessages());
    }

    private function validateAndSwitchTab()
    {
        try {
            $this->validate();
        } catch (ValidationException $e) {
            $errors = array_keys($e->validator->errors()->toArray());
            
            $mailFields = ['from_name', 'from_email', 'smtp_host', 'smtp_port', 'smtp_username', 'smtp_password', 'imap_host', 'imap_port', 'imap_username', 'imap_password'];
            
            $hasBasicErrors = count(array_intersect($errors, ['name', 'email', 'password', 'role'])) > 0;
            $hasMailErrors = count(array_intersect($errors, $mailFields)) > 0;

            if ($hasBasicErrors) {
                $this->activeTab = 'basic';
            } elseif ($hasMailErrors) {
                $this->activeTab = 'mail';
            }

            throw $e; 
        }
    }

    public function setTab($tab) { $this->activeTab = $tab; }

    public function create()
    {
        $this->reset(['user_id', 'name', 'email', 'password', 'role']);
        $this->resetEmailConfig(); 
        $this->activeTab = 'basic';
        $this->isEditMode = false;
        $this->showModal = true;
    }

    public function edit($id)
    {
        $this->reset(['user_id', 'name', 'email', 'password', 'role']);
        $this->user_id = $id;
        $this->isEditMode = true;
        
        $user = User::with('emailConfig')->findOrFail($id);
        $this->name = $user->name;
        $this->email = $user->email;
        $this->role = $user->roles->first()?->name ?? 'user';

        $this->loadEmailConfig($user);

        $this->activeTab = 'basic';
        $this->showModal = true;
    }

    public function store()
    {
        $this->validateAndSwitchTab();

        try {
            DB::transaction(function () {
                $user = User::create([
                    'name' => $this->name,
                    'email' => $this->email,
                    'password' => Hash::make($this->password),
                    'is_active' => true, // Assuming new users default to active
                ]);
                $user->assignRole($this->role);
                $this->saveEmailConfig($user); 
            });

            $this->showModal = false;
            $this->dispatch('toast', ['type' => 'success', 'message' => 'User created successfully!']);
        } catch (\Exception $e) {
            Log::error($e->getMessage());
            $this->dispatch('toast', ['type' => 'error', 'message' => 'Database Error.']);
        }
    }

    public function update()
    {
        $this->validateAndSwitchTab();

        try {
            DB::transaction(function () {
                $user = User::findOrFail($this->user_id);
                $data = ['name' => $this->name, 'email' => $this->email];
                if ($this->password) $data['password'] = Hash::make($this->password);
                
                $user->update($data);
                $user->syncRoles([$this->role]);
                $this->saveEmailConfig($user); 
            });

            $this->showModal = false;
            $this->dispatch('toast', ['type' => 'success', 'message' => 'User updated successfully!']);
        } catch (\Exception $e) {
            Log::error($e->getMessage());
            $this->dispatch('toast', ['type' => 'error', 'message' => 'Update Failed.']);
        }
    }

    public function delete($id)
    {
        if($id == auth()->id()) {
            $this->dispatch('toast', ['type' => 'error', 'message' => 'You cannot delete yourself.']);
            return;
        }
        User::findOrFail($id)->delete();
        $this->dispatch('toast', ['type' => 'warning', 'message' => 'User deleted.']);
    }

    // --- NEW: Toggle Status Method ---
    public function toggleStatus($id)
    {
        if ($id == auth()->id()) {
            $this->dispatch('toast', ['type' => 'error', 'message' => 'You cannot change your own status.']);
            return;
        }
        
        $user = User::findOrFail($id);
        $user->is_active = !$user->is_active; // Change this if your column name is different
        $user->save();

        $status = $user->is_active ? 'Activated' : 'Deactivated';
        $this->dispatch('toast', ['type' => 'success', 'message' => "User {$status}."]);
    }

    public function impersonate($userId)
    {
        $user = User::findOrFail($userId);

        // Prevent admin from impersonating themselves
        if ($user->id === auth()->id()) {
            $this->dispatch('toast', ['type' => 'error', 'message' => 'You are already logged in as this user.']);
            return;
        }

        // Store the original admin's ID in the session so they can switch back later
        session()->put('impersonated_by', auth()->id());

        // Log in as the target user
        auth()->login($user);

        // Redirect to the user's dashboard or home page
        // (Change 'dashboard' to whatever your main user route is named)
        return redirect()->route('dashboard'); 
    }

    public function render()
    {
        // --- NEW: Filtered Query ---
        $query = User::with('roles', 'emailConfig')
            ->whereHas('roles', function($q) {
                $q->where('name', 'user');
            })
            ->when($this->search, function($q) {
                $q->where(function($subQuery) {
                    $subQuery->where('name', 'like', '%' . $this->search . '%')
                             ->orWhere('email', 'like', '%' . $this->search . '%');
                });
            })
            ->when($this->filterConfig === 'configured', function($q) {
                $q->has('emailConfig');
            })
            ->when($this->filterConfig === 'unconfigured', function($q) {
                $q->doesntHave('emailConfig');
            })
            ->latest();

        return view('livewire.admin.users.index', [
            'users' => $query->paginate(10)
        ])->extends('layouts.admin')->section('content');
    }
}