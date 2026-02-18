@component('mail::message')
# New Issue Submitted

A new issue has been submitted to Ableton Cookbook.

**Issue ID:** #{{ $issueId }}  
**Type:** {{ $issueType }}  
**Title:** {{ $title }}

@component('mail::button', ['url' => route('admin.issues.show', $issueId)])
Review in Admin Dashboard
@endcomponent

Thanks,<br>
{{ config('app.name') }} System
@endcomponent
