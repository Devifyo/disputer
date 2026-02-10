@props(['dismissible' => true])

@php
    $messages = [
        'success' => ['type' => 'success', 'title' => 'Success'],
        'error' => ['type' => 'error', 'title' => 'Error'],
        'warning' => ['type' => 'warning', 'title' => 'Warning'],
        'info' => ['type' => 'info', 'title' => 'Info'],
        'status' => ['type' => 'info', 'title' => 'Status'], // Standard Laravel status
    ];
@endphp

<div class="fixed top-4 right-4 z-50 w-full max-w-sm space-y-4 pointer-events-none">
    @foreach($messages as $key => $config)
        @if(session()->has($key))
            <div x-data="{ show: true }" 
                 x-show="show" 
                 x-init="setTimeout(() => show = false, 5000)" 
                 x-transition:enter="transform ease-out duration-300 transition"
                 x-transition:enter-start="translate-y-2 opacity-0 sm:translate-y-0 sm:translate-x-2"
                 x-transition:enter-end="translate-y-0 opacity-100 sm:translate-x-0"
                 x-transition:leave="transition ease-in duration-100"
                 x-transition:leave-start="opacity-100"
                 x-transition:leave-end="opacity-0"
                 class="pointer-events-auto shadow-lg rounded-lg overflow-hidden border-l-4 
                 {{ match($config['type']) {
                     'success' => 'bg-white border-emerald-500',
                     'error' => 'bg-white border-red-500',
                     'warning' => 'bg-white border-amber-500',
                     default => 'bg-white border-blue-500',
                 } }}">
                
                <div class="p-4 flex items-start">
                    <div class="shrink-0">
                        @if($config['type'] === 'success')
                            <i data-lucide="check-circle" class="h-5 w-5 text-emerald-500"></i>
                        @elseif($config['type'] === 'error')
                            <i data-lucide="x-circle" class="h-5 w-5 text-red-500"></i>
                        @elseif($config['type'] === 'warning')
                            <i data-lucide="alert-triangle" class="h-5 w-5 text-amber-500"></i>
                        @else
                            <i data-lucide="info" class="h-5 w-5 text-blue-500"></i>
                        @endif
                    </div>
                    <div class="ml-3 w-0 flex-1 pt-0.5">
                        <p class="text-sm font-medium text-gray-900">{{ $config['title'] }}</p>
                        <p class="mt-1 text-sm text-gray-500">{{ session($key) }}</p>
                        
                        {{-- Handle Special "Action" Buttons (Like your SMTP Missing) --}}
                        @if($key === 'error' && session('smtp_missing'))
                             <div class="mt-3">
                                <a href="{{ route('profile.show') }}#email-settings" 
                                   class="text-xs font-semibold text-amber-600 hover:text-amber-500 whitespace-nowrap">
                                    Configure SMTP Now &rarr;
                                </a>
                            </div>
                        @endif
                    </div>
                    <div class="ml-4 shrink-0 flex">
                        <button @click="show = false" class="bg-white rounded-md inline-flex text-gray-400 hover:text-gray-500 focus:outline-none">
                            <span class="sr-only">Close</span>
                            <i data-lucide="x" class="h-5 w-5"></i>
                        </button>
                    </div>
                </div>
            </div>
        @endif
    @endforeach
</div>