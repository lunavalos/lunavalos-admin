@props(['url'])
<tr>
<td class="header">
<a href="{{ $url }}" style="display: inline-block;">
@php
    $logoPath = null;
    try {
        if (\Illuminate\Support\Facades\Schema::hasTable('settings')) {
            $logoPath = \App\Models\Setting::where('key', 'company_logo')->value('value');
        }
    } catch (\Exception $e) {}
@endphp
@if ($logoPath)
<img src="{{ asset('storage/' . $logoPath) }}" class="logo" alt="{!! strip_tags($slot) !!}" style="max-height: 50px; width: auto; max-width: 100%;">
@else
@if (trim($slot) === 'Laravel')
<img src="https://laravel.com/img/notification-logo-v2.1.png" class="logo" alt="Laravel Logo">
@else
{!! $slot !!}
@endif
@endif
</a>
</td>
</tr>
