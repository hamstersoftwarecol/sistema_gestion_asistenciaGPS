@if (session('status') && ! in_array(session('status'), ['profile-updated', 'password-updated', 'verification-link-sent'], true))
    <div x-data="{ show: true }" x-show="show" class="mb-4 flex items-start gap-3 rounded-lg border border-emerald-200 bg-emerald-50 p-3 text-sm text-emerald-800">
        <x-icon name="check-circle" class="mt-0.5 h-5 w-5 shrink-0" />
        <div class="flex-1">{{ session('status') }}</div>
        <button type="button" @click="show = false" class="text-emerald-600 hover:text-emerald-900"><x-icon name="x" class="h-4 w-4" /></button>
    </div>
@endif
@if (session('error'))
    <div class="mb-4 flex items-start gap-3 rounded-lg border border-red-200 bg-red-50 p-3 text-sm text-red-800">
        <x-icon name="warning" class="mt-0.5 h-5 w-5 shrink-0" />
        <div>{{ session('error') }}</div>
    </div>
@endif
@if ($errors->any())
    <div class="mb-4 rounded-lg border border-red-200 bg-red-50 p-3 text-sm text-red-800">
        <div class="font-semibold">Revisa los datos ingresados:</div>
        <ul class="mt-1 list-inside list-disc">
            @foreach ($errors->all() as $error)
                <li>{{ $error }}</li>
            @endforeach
        </ul>
    </div>
@endif
