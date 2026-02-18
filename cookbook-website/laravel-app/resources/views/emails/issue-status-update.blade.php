@component('mail::message')
# Issue Status Update

Your issue **#{{ $issueId }}** status has been updated from **{{ ucfirst(str_replace('_', ' ', $oldStatus)) }}** to **{{ ucfirst(str_replace('_', ' ', $newStatus)) }}**.

@if($comment)
## Admin Comment:
{{ $comment }}
@endif

@component('mail::button', ['url' => route('issues.show', $issueId)])
View Issue
@endcomponent

Thanks,<br>
{{ config('app.name') }} Team
@endcomponent
