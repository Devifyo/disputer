{{-- SMTP Missing Alert (Actionable) --}}
@if(session('smtp_missing'))
    <div class="mb-6">
        <x-alert type="warning" title="Email Configuration Required">
            <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
                <p class="text-sm text-amber-800">
                    You cannot send emails yet because your SMTP/IMAP settings are missing. 
                    Please configure your email provider to start communicating directly from the dashboard.
                </p>
                <a href="{{ route('profile.edit').'#email-settings' }}" 
                   class="shrink-0 px-4 py-2 bg-amber-600 hover:bg-amber-700 text-white text-xs font-bold rounded-lg shadow-sm transition-colors flex items-center gap-2">
                    <i data-lucide="settings" class="w-3.5 h-3.5"></i>
                    Configure Now
                </a>
            </div>
        </x-alert>
    </div>
@endif

{{-- Standard Success Alert --}}
@if(session('success'))
    <div class="mb-6">
        <x-alert type="success" title="Success">
            {{ session('success') }}
        </x-alert>
    </div>
@endif

{{-- Standard Error Alert (Excluding SMTP Missing) --}}
@if(session('error') && !session('smtp_missing'))
    <div class="mb-6">
        <x-alert type="error" title="Error">
            {{ session('error') }}
        </x-alert>
    </div>
@endif

{{-- Validation Errors (Laravel Default) --}}
@if($errors->any())
    <div class="mb-6">
        <x-alert type="error" title="There were problems with your input">
            <ul class="list-disc list-inside text-sm">
                @foreach($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </x-alert>
    </div>
@endif