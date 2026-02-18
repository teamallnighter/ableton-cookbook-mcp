@component('mail::message')
# Issue Submitted Successfully

Thank you for submitting your issue to Ableton Cookbook!

**Issue ID:** #{{ $issueId }}

We have received your submission and will review it shortly. You will receive email updates when the status changes.

@component('mail::button', ['url' => route('issues.show', $issueId)])
View Your Issue
@endcomponent

Thanks,<br>
{{ config('app.name') }} Team
@endcomponent
