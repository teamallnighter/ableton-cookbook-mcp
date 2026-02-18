<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\URL;

class UnsubscribeController extends Controller
{
    /**
     * Show unsubscribe confirmation page
     */
    public function show(Request $request)
    {
        if (!$request->hasValidSignature()) {
            abort(401, 'Invalid unsubscribe link');
        }

        $user = User::findOrFail($request->user_id);
        
        return view('emails.unsubscribe', compact('user'));
    }

    /**
     * Process unsubscribe request
     */
    public function unsubscribe(Request $request)
    {
        if (!$request->hasValidSignature()) {
            abort(401, 'Invalid unsubscribe link');
        }

        $user = User::findOrFail($request->user_id);
        $user->update([
            'email_consent' => false,
            'email_notifications_enabled' => false,
        ]);

        return view('emails.unsubscribed', compact('user'));
    }

    /**
     * Generate unsubscribe URL for a user
     */
    public static function generateUnsubscribeUrl(User $user): string
    {
        return URL::temporarySignedRoute(
            'unsubscribe.show',
            now()->addDays(30),
            ['user_id' => $user->id]
        );
    }
}
