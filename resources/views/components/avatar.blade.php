@props(['user', 'size' => 'h-8 w-8'])
@if ($user->avatar)
    <img src="{{ $user->avatar }}" alt="" class="{{ $size }} rounded-full object-cover">
@else
    <span class="{{ $size }} inline-flex shrink-0 items-center justify-center rounded-full bg-indigo-100 text-xs font-semibold text-indigo-700">{{ $user->initials() }}</span>
@endif
