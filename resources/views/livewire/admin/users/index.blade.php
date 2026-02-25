<div class="p-6">
    <x-flash />

    {{-- Top Header --}}
    <div class="flex items-center justify-between mb-6">
        <div>
            <h1 class="text-2xl font-bold text-slate-900 tracking-tight">User Management</h1>
            <p class="text-sm text-slate-500">Add and configure system users and their mail servers.</p>
        </div>
        {{-- Updated Add User Button --}}
        <button wire:click="create" class="bg-slate-900 hover:bg-slate-800 text-white px-3 sm:px-4 py-2 rounded-lg text-xs font-bold shadow-lg shadow-slate-900/20 transition-all flex items-center gap-2 hover:scale-105 active:scale-95">
            <i data-lucide="plus" class="w-4 h-4"></i> Add User
        </button>
    </div>

    {{-- Filters Section --}}
    <div class="flex flex-col md:flex-row gap-4 mb-6">
        {{-- Search Input --}}
        <div class="relative flex-1 max-w-md">
            <i data-lucide="search" class="w-4 h-4 absolute left-3.5 top-1/2 -translate-y-1/2 text-slate-400"></i>
            <input type="text" wire:model.live.debounce.300ms="search" placeholder="Search by name or email..." class="w-full pl-10 pr-4 py-2.5 bg-white border border-slate-200 rounded-xl text-sm focus:border-primary-600 focus:ring-2 focus:ring-primary-600/20 outline-none transition-all shadow-sm">
        </div>
        
        {{-- Config Filter Dropdown --}}
        <div class="w-full md:w-56">
            <select wire:model.live="filterConfig" class="w-full px-4 py-2.5 bg-white border border-slate-200 rounded-xl text-sm font-medium text-slate-600 focus:border-primary-600 focus:ring-2 focus:ring-primary-600/20 outline-none transition-all shadow-sm appearance-none cursor-pointer">
                <option value="">All Users</option>
                <option value="configured">Has Mail Config</option>
                <option value="unconfigured">No Mail Config</option>
            </select>
        </div>
    </div>

    {{-- Table --}}
    <div class="bg-white rounded-2xl border border-slate-200 shadow-sm overflow-hidden">
        <table class="w-full text-left text-sm">
            <thead class="bg-slate-50 border-b border-slate-200 text-slate-500 font-bold uppercase text-[10px] tracking-widest">
                <tr>
                    <th class="px-6 py-4">User Details</th>
                    <th class="px-6 py-4">Role</th>
                    <th class="px-6 py-4">Status</th>
                    <th class="px-6 py-4">Mail Config</th>
                    <th class="px-6 py-4 text-right">Actions</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100">
                @forelse($users as $user)
                    <tr class="hover:bg-slate-50/50 transition-colors">
                        <td class="px-6 py-4">
                            <div class="flex items-center gap-3">
                                <div class="w-10 h-10 rounded-full bg-primary-600/10 text-primary-600 flex items-center justify-center font-bold text-xs">
                                    {{ substr($user->name, 0, 1) }}
                                </div>
                                <div>
                                    <div class="font-bold text-slate-900 leading-none mb-1">{{ $user->name }}</div>
                                    <div class="text-xs text-slate-400">{{ $user->email }}</div>
                                </div>
                            </div>
                        </td>
                        <td class="px-6 py-4">
                            <span class="px-2.5 py-1 rounded-lg text-[10px] font-bold border {{ $user->hasRole('admin') ? 'bg-slate-900 text-white border-slate-900' : 'bg-slate-50 text-slate-600 border-slate-200' }}">
                                {{ strtoupper($user->roles->first()?->name ?? 'User') }}
                            </span>
                        </td>
                        <td class="px-6 py-4">
                            <button wire:click="toggleStatus({{ $user->id }})" 
                                    class="relative inline-flex h-5 w-9 items-center rounded-full transition-colors focus:outline-none {{ $user->is_active ? 'bg-emerald-500' : 'bg-slate-300' }}"
                                    title="{{ $user->is_active ? 'Deactivate User' : 'Activate User' }}">
                                <span class="inline-block h-3.5 w-3.5 transform rounded-full bg-white transition-transform {{ $user->is_active ? 'translate-x-4' : 'translate-x-1' }}"></span>
                            </button>
                        </td>
                        <td class="px-6 py-4">
                            @if($user->emailConfig)
                                <span class="text-emerald-600 text-xs font-bold flex items-center gap-1.5">
                                    <div class="w-1.5 h-1.5 rounded-full bg-emerald-500"></div> Connected
                                </span>
                            @else
                                <span class="text-slate-300 text-xs italic">Not Set</span>
                            @endif
                        </td>
                        <td class="px-6 py-4 text-right">
                            <div class="flex justify-end gap-1">
                                @if($user->id !== auth()->id() && auth()->user()->canImpersonate() && $user->canBeImpersonated())
                                    <a href="{{ route('impersonate', $user->id) }}" 
                                    class="inline-block p-2 text-slate-400 hover:text-primary-600 hover:bg-primary-600/10 rounded-lg transition-all" 
                                    title="Impersonate User">
                                        <i data-lucide="user-check" class="w-4 h-4"></i>
                                    </a>
                                @endif
                                <button wire:click="edit({{ $user->id }})" class="p-2 text-slate-400 hover:text-primary-600 hover:bg-primary-600/10 rounded-lg transition-all">
                                    <i data-lucide="edit-3" class="w-4 h-4"></i>
                                </button>
                                @if(auth()->id() !== $user->id)
                                    <button wire:click="delete({{ $user->id }})" wire:confirm="Delete this user?" class="p-2 text-slate-400 hover:text-rose-600 hover:bg-rose-50 rounded-lg transition-all">
                                        <i data-lucide="trash-2" class="w-4 h-4"></i>
                                    </button>
                                @endif
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="5" class="px-6 py-12 text-center text-slate-500">
                            <div class="flex flex-col items-center justify-center gap-2">
                                <i data-lucide="users" class="w-8 h-8 text-slate-300"></i>
                                <p class="text-sm font-medium text-slate-600">No users found</p>
                                <p class="text-xs text-slate-400">Try adjusting your search or filters.</p>
                            </div>
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
        @if($users->hasPages())
            <div class="p-4 border-t border-slate-50">{{ $users->links() }}</div>
        @endif
    </div>

    {{-- MODAL --}}
    @if($showModal)
        <div class="fixed inset-0 z-[100] flex items-center justify-center p-4">
            <div class="absolute inset-0 bg-slate-900/60 backdrop-blur-sm animate-in fade-in" wire:click="$set('showModal', false)"></div>
            <div class="relative bg-white rounded-[2rem] shadow-2xl w-full max-w-2xl flex flex-col overflow-hidden animate-in zoom-in-95">
                
                {{-- Tabs Header --}}
                <div class="px-8 py-6 border-b flex items-center justify-between bg-white">
                    <h2 class="text-xl font-bold text-slate-900">{{ $isEditMode ? 'Update User' : 'New User' }}</h2>
                    <div class="flex bg-slate-100 p-1 rounded-xl">
                        {{-- Tab Button 1 (Always Visible) --}}
                        <button wire:click="setTab('basic')" 
                                class="relative px-4 py-1.5 rounded-lg text-xs font-bold transition-all {{ $activeTab == 'basic' ? 'bg-white text-primary-600 shadow-sm' : 'text-slate-500 hover:text-slate-700' }}">
                            Profile
                            @if($errors->hasAny(['name', 'email', 'password', 'role']))
                                <span class="absolute -top-1 -right-1 w-2.5 h-2.5 bg-rose-500 rounded-full border-2 border-white"></span>
                            @endif
                        </button>
                        
                        {{-- Tab Button 2 (Only visible if the user wants to add Mail Config) --}}
                        @if($has_mail_config)
                            <button wire:click="setTab('mail')" 
                                    class="relative px-4 py-1.5 rounded-lg text-xs font-bold transition-all {{ $activeTab == 'mail' ? 'bg-white text-primary-600 shadow-sm' : 'text-slate-500 hover:text-slate-700' }}">
                                Mail Config
                                @if($errors->hasAny(['from_name', 'from_email', 'smtp_host', 'smtp_port', 'smtp_username', 'smtp_password', 'imap_host', 'imap_port', 'imap_username', 'imap_password']))
                                    <span class="absolute -top-1 -right-1 w-2.5 h-2.5 bg-rose-500 rounded-full border-2 border-white"></span>
                                @endif
                            </button>
                        @endif
                    </div>
                </div>

                <div class="p-8 overflow-y-auto max-h-[70vh] bg-white">
                    @if($activeTab == 'basic')
                        <div class="space-y-6">
                            {{-- Basic Profile Info --}}
                            <div class="grid grid-cols-2 gap-6">
                                <div>
                                    <label class="block text-[11px] font-bold text-slate-400 uppercase mb-2">Name</label>
                                    <input type="text" wire:model.blur="name" class="w-full px-4 py-2.5 bg-slate-50 border {{ $errors->has('name') ? 'border-rose-400 bg-rose-50/30' : 'border-slate-200' }} rounded-xl text-sm outline-none focus:bg-white focus:border-primary-600 transition-all">
                                    @error('name') <p class="text-rose-500 text-[10px] mt-1 font-bold">{{ $message }}</p> @enderror
                                </div>
                                <div>
                                    <label class="block text-[11px] font-bold text-slate-400 uppercase mb-2">Email</label>
                                    <input type="email" wire:model.blur="email" class="w-full px-4 py-2.5 bg-slate-50 border {{ $errors->has('email') ? 'border-rose-400 bg-rose-50/30' : 'border-slate-200' }} rounded-xl text-sm outline-none focus:bg-white focus:border-primary-600 transition-all">
                                    @error('email') <p class="text-rose-500 text-[10px] mt-1 font-bold">{{ $message }}</p> @enderror
                                </div>
                            </div>
                            <div class="grid grid-cols-2 gap-6">
                                <div>
                                    <label class="block text-[11px] font-bold text-slate-400 uppercase mb-2">Password</label>
                                    <input type="password" wire:model.blur="password" class="w-full px-4 py-2.5 bg-slate-50 border {{ $errors->has('password') ? 'border-rose-400 bg-rose-50/30' : 'border-slate-200' }} rounded-xl text-sm outline-none focus:bg-white focus:border-primary-600 transition-all">
                                    @error('password') <p class="text-rose-500 text-[10px] mt-1 font-bold">{{ $message }}</p> @enderror
                                </div>
                                <div>
                                    <label class="block text-[11px] font-bold text-slate-400 uppercase mb-2">Role</label>
                                    <select wire:model="role" class="w-full px-4 py-2.5 bg-slate-50 border border-slate-200 rounded-xl text-sm font-bold outline-none focus:bg-white focus:border-primary-600 transition-all">
                                        <option value="user">User</option>
                                        <option value="admin">Admin</option>
                                    </select>
                                    @error('role') <p class="text-rose-500 text-[10px] mt-1 font-bold">{{ $message }}</p> @enderror
                                </div>
                            </div>

                            {{-- Checkbox to Toggle Mail Config --}}
                            <div class="pt-6 mt-6 border-t border-slate-100">
                                <label class="flex items-center gap-3 cursor-pointer group w-max">
                                    <div class="relative flex items-center justify-center">
                                        <input type="checkbox" wire:model.live="has_mail_config" class="peer appearance-none w-5 h-5 border-2 border-slate-300 rounded focus:ring-2 focus:ring-primary-600/20 checked:bg-primary-600 checked:border-primary-600 transition-all cursor-pointer">
                                        <i data-lucide="check" class="w-3.5 h-3.5 text-white absolute opacity-0 peer-checked:opacity-100 pointer-events-none transition-opacity"></i>
                                    </div>
                                    <div class="flex flex-col">
                                        <span class="text-sm font-bold text-slate-700 group-hover:text-slate-900 transition-colors">Setup Custom Mail Server</span>
                                        <span class="text-xs text-slate-400 font-normal">Add SMTP and IMAP details for this user</span>
                                    </div>
                                </label>
                            </div>
                        </div>
                    @else
                        <div class="space-y-6">
                            {{-- Sender Identity --}}
                            <div class="bg-slate-50 p-6 rounded-2xl border border-slate-100">
                                <h3 class="text-[10px] font-black text-slate-400 uppercase tracking-widest mb-4">Sender Details</h3>
                                <div class="grid grid-cols-2 gap-4">
                                    <div>
                                        <input type="text" wire:model="from_name" placeholder="From Name" class="w-full px-3.5 py-2 rounded-lg border {{ $errors->has('from_name') ? 'border-rose-400 bg-rose-50/30' : 'border-slate-200' }} text-xs focus:border-primary-600 outline-none">
                                        @error('from_name') <p class="text-rose-500 text-[9px] mt-1 font-bold">{{ $message }}</p> @enderror
                                    </div>
                                    <div>
                                        <input type="email" wire:model="from_email" placeholder="From Email" class="w-full px-3.5 py-2 rounded-lg border {{ $errors->has('from_email') ? 'border-rose-400 bg-rose-50/30' : 'border-slate-200' }} text-xs focus:border-primary-600 outline-none">
                                        @error('from_email') <p class="text-rose-500 text-[9px] mt-1 font-bold">{{ $message }}</p> @enderror
                                    </div>
                                </div>
                            </div>

                            {{-- SMTP --}}
                            <div class="bg-slate-50 p-6 rounded-2xl border border-slate-100">
                                <h3 class="text-[10px] font-black text-primary-600 uppercase tracking-widest mb-4">SMTP (Outgoing)</h3>
                                <div class="grid grid-cols-12 gap-4">
                                    <div class="col-span-8">
                                        <input type="text" wire:model.blur="smtp_host" placeholder="Host (e.g. smtp.gmail.com)" class="w-full px-3.5 py-2 rounded-lg border {{ $errors->has('smtp_host') ? 'border-rose-400 bg-rose-50/30' : 'border-slate-200' }} text-xs focus:border-primary-600 outline-none">
                                        @error('smtp_host') <p class="text-rose-500 text-[9px] mt-1 font-bold">{{ $message }}</p> @enderror
                                    </div>
                                    <div class="col-span-4">
                                        <input type="number" wire:model.blur="smtp_port" placeholder="Port" class="w-full px-3.5 py-2 rounded-lg border {{ $errors->has('smtp_port') ? 'border-rose-400 bg-rose-50/30' : 'border-slate-200' }} text-xs focus:border-primary-600 outline-none">
                                        @error('smtp_port') <p class="text-rose-500 text-[9px] mt-1 font-bold">{{ $message }}</p> @enderror
                                    </div>
                                    <div class="col-span-6">
                                        <input type="text" wire:model.blur="smtp_username" placeholder="Username" class="w-full px-3.5 py-2 rounded-lg border {{ $errors->has('smtp_username') ? 'border-rose-400 bg-rose-50/30' : 'border-slate-200' }} text-xs focus:border-primary-600 outline-none">
                                        @error('smtp_username') <p class="text-rose-500 text-[9px] mt-1 font-bold">{{ $message }}</p> @enderror
                                    </div>
                                    <div class="col-span-6">
                                        <input type="password" wire:model.blur="smtp_password" placeholder="Password" class="w-full px-3.5 py-2 rounded-lg border {{ $errors->has('smtp_password') ? 'border-rose-400 bg-rose-50/30' : 'border-slate-200' }} text-xs focus:border-primary-600 outline-none">
                                        @error('smtp_password') <p class="text-rose-500 text-[9px] mt-1 font-bold">{{ $message }}</p> @enderror
                                    </div>
                                </div>
                            </div>

                            {{-- IMAP --}}
                            <div class="bg-slate-50 p-6 rounded-2xl border border-slate-100">
                                <h3 class="text-[10px] font-black text-slate-700 uppercase tracking-widest mb-4">IMAP (Incoming)</h3>
                                <div class="grid grid-cols-12 gap-4">
                                    <div class="col-span-8">
                                        <input type="text" wire:model.blur="imap_host" placeholder="IMAP Host" class="w-full px-3.5 py-2 rounded-lg border {{ $errors->has('imap_host') ? 'border-rose-400 bg-rose-50/30' : 'border-slate-200' }} text-xs focus:border-primary-600 outline-none">
                                        @error('imap_host') <p class="text-rose-500 text-[9px] mt-1 font-bold">{{ $message }}</p> @enderror
                                    </div>
                                    <div class="col-span-4">
                                        <input type="number" wire:model.blur="imap_port" placeholder="Port" class="w-full px-3.5 py-2 rounded-lg border {{ $errors->has('imap_port') ? 'border-rose-400 bg-rose-50/30' : 'border-slate-200' }} text-xs focus:border-primary-600 outline-none">
                                        @error('imap_port') <p class="text-rose-500 text-[9px] mt-1 font-bold">{{ $message }}</p> @enderror
                                    </div>
                                    <div class="col-span-6">
                                        <input type="text" wire:model.blur="imap_username" placeholder="Username" class="w-full px-3.5 py-2 rounded-lg border {{ $errors->has('imap_username') ? 'border-rose-400 bg-rose-50/30' : 'border-slate-200' }} text-xs focus:border-primary-600 outline-none">
                                        @error('imap_username') <p class="text-rose-500 text-[9px] mt-1 font-bold">{{ $message }}</p> @enderror
                                    </div>
                                    <div class="col-span-6">
                                        <input type="password" wire:model.blur="imap_password" placeholder="Password" class="w-full px-3.5 py-2 rounded-lg border {{ $errors->has('imap_password') ? 'border-rose-400 bg-rose-50/30' : 'border-slate-200' }} text-xs focus:border-primary-600 outline-none">
                                        @error('imap_password') <p class="text-rose-500 text-[9px] mt-1 font-bold">{{ $message }}</p> @enderror
                                    </div>
                                </div>
                            </div>
                        </div>
                    @endif
                </div>

                <div class="px-8 py-6 bg-slate-50 border-t flex justify-end gap-3">
                    <button wire:click="$set('showModal', false)" class="text-sm font-bold text-slate-400 hover:text-slate-600">Cancel</button>
                    <button wire:click="{{ $isEditMode ? 'update' : 'store' }}" class="px-8 py-2.5 bg-slate-900 text-white rounded-xl text-sm font-bold shadow-xl active:scale-95 transition-all">
                        {{ $isEditMode ? 'Save Changes' : 'Create User' }}
                    </button>
                </div>
            </div>
        </div>
    @endif

    {{-- Persistent Icon Fix --}}
    <script>
        document.addEventListener('livewire:init', () => {
           Livewire.hook('morph.updated', ({ el, component }) => {
                if (typeof lucide !== 'undefined') {
                    lucide.createIcons();
                }
           });
        });
    </script>
</div>