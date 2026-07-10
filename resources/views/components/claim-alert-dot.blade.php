{{-- Blinking red dot: a trip is eligible for compensation, claim not filed yet --}}
{{-- Positioning (relative/absolute + offsets) is the caller's responsibility --}}
<span {{ $attributes->merge(['class' => 'flex w-2 h-2', 'title' => 'A trip is eligible for compensation']) }}>
    <span class="absolute inline-flex w-full h-full rounded-full bg-rose-400 opacity-75 animate-ping"></span>
    <span class="relative inline-flex w-2 h-2 rounded-full bg-rose-500"></span>
</span>
