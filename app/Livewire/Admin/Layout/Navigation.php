<?php

namespace App\Livewire\Admin\Layout;

use Livewire\Component;
use Illuminate\Support\Facades\Auth;

class Navigation extends Component
{
    // This replaces x-data="{ sidebarOpen: false }"
    public $mobileSidebarOpen = false;

    public function toggleSidebar()
    {
        $this->mobileSidebarOpen = ! $this->mobileSidebarOpen;
    }

    public function closeSidebar()
    {
        $this->mobileSidebarOpen = false;
    }

    public function logout()
    {
        Auth::guard('web')->logout();
        session()->invalidate();
        session()->regenerateToken();
        return redirect('/');
    }

    public function render()
    {
        return view('livewire.admin.layout.navigation');
    }
}