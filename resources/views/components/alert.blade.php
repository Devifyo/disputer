@props(['type' => 'info', 'title' => null, 'dismissible' => true])

@php
    $colors = match($type) {
        'success' => 'bg-emerald-50 border-emerald-200 text-emerald-800',
        'error' => 'bg-red-50 border-red-200 text-red-800',
        'warning' => 'bg-amber-50 border-amber-200 text-amber-800',
        'info' => 'bg-blue-50 border-blue-200 text-blue-800',
        default => 'bg-slate-50 border-slate-200 text-slate-800',
    };

    $iconColor = match($type) {
        'success' => 'text-emerald-500',
        'error' => 'text-red-500',
        'warning' => 'text-amber-500',
        'info' => 'text-blue-500',
        default => 'text-slate-500',
    };

    $icon = match($type) {
        'success' => 'check-circle',
        'error' => 'alert-circle',
        'warning' => 'alert-triangle',
        'info' => 'info',
        default => 'info',
    };
@endphp

<div x-data="{ show: true }" 
     x-show="show" 
     x-transition.opacity.duration.300ms
     {{ $attributes->merge(['class' => "relative rounded-lg border p-4 $colors"]) }}>
    
    <div class="flex items-start gap-3">
        <div class="shrink-0">
            <i data-lucide="{{ $icon }}" class="w-5 h-5 {{ $iconColor }}"></i>
        </div>

        <div class="flex-1 min-w-0">
            @if($title)
                <h3 class="text-sm font-bold mb-1 leading-none">{{ $title }}</h3>
            @endif
            <div class="text-sm opacity-90 leading-relaxed">
                {{ $slot }}
            </div>
        </div>

        @if($dismissible)
            <button @click="show = false" class="shrink-0 -mt-1 -mr-1 p-1.5 rounded-md hover:bg-black/5 transition-colors opacity-60 hover:opacity-100">
                <i data-lucide="x" class="w-4 h-4"></i>
            </button>
        @endif
    </div>
</div>