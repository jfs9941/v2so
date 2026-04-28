<?php

namespace Module\FreeTrialLink\Controller;

use App\Http\Controllers\Controller;
use App\Model\FreeTrialLink;
use App\Model\Post;
use App\User;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Module\FreeTrialLink\Service\FreeTrialLinkService;

/**
 * FreeTrialLink Controller
 *
 * Handles free trial link generation and landing page display
 */
class FreeTrialLinkController extends Controller
{
    /**
     * Display the free trial landing page
     *
     * @param string $token The free trial link hash/token
     */
    public function landing(Request $request, $token)
    {
        // Find the free trial link by hash
        $freeTrialLink = FreeTrialLink::where('hash', $token)
            ->first();
        // Validate token
        if (!$freeTrialLink) {
            return Inertia::render('FreeTrial', [
                'creator' => null,           // No creator = no CTA button
                'posts' => [],
                'trialDuration' => 0,
                'token' => '',
                'status' => 'invalid',
            ]);
        }
        $creator = User::find($freeTrialLink->user_id);
        if (!$creator) {
            return Inertia::render('FreeTrial', [
                'creator' => null,           // No creator = no CTA button
                'posts' => [],
                'trialDuration' => 0,
                'token' => '',
                'status' => 'invalid',
            ]);
        }

        if ($freeTrialLink->expired_at < now()) {
            // Expired token, when user login, route to the creator's profile page
            session(['url.intended' => route('profile', ['username' => $creator->username])]);

            return Inertia::render('FreeTrial', [
                'creator' => $creator,
                'posts' => [],
                'trialDuration' => 0,
                'token' => $freeTrialLink->hash,
                'status' => 'invalid',
            ]);
        }

        // Redirect logged-in users (they already have an account)
        if (auth()->check()) {
            // If same user, redirect to settings
            if (auth()->id() === $creator->id) {
                return redirect()->route('home')
                    ->with('message', __('You cannot use your own free trial link.'));
            }

            app(FreeTrialLinkService::class)->redeemAfterRegistration($token, auth()->id());
            app(FreeTrialLinkService::class)->clearTokenFromSession($request);

            return redirect()->route('profile', ['username' => $creator->username])
                ->with('info', __('You already have an account. Subscribe to access exclusive content.'));
        }

        // Fetch creator's public posts for preview
        $posts = Post::with(['attachments'])
            ->where('user_id', $creator->id)
            ->where('status', Post::APPROVED_STATUS)
            ->where('is_public', 1)
            ->notExpiredAndReleased()
            ->orderBy('created_at', 'desc')
            ->limit(12)
            ->get();

        // Render the free trial landing page
        return Inertia::render('FreeTrial', [
            'creator' => [
                'id' => $creator->id,
                'name' => $creator->name,
                'username' => $creator->username,
                'avatar' => $creator->avatar ?? 'https://i.pravatar.cc/150?img=' . $creator->id,
                'cover' => $creator->cover ?? null,
            ],
            'posts' => $posts->map(function ($post) {
                return [
                    'id' => $post->id,
                    'text' => $post->text,
                    'price' => $post->price,
                    'created_at' => $post->created_at->toDateTimeString(),
                    'attachments' => $post->attachments->map(function ($attachment) {
                        return [
                            'id' => $attachment->id,
                            'type' => $attachment->type,
                            'attachment_type' => $attachment->attachmentType,
                            'thumbnail' => $attachment->thumbnail,
                            'path' => $attachment->path,
                            'player_url' => $attachment->player_url,
                            'orientation' => $attachment->orientation,
                            'resolution' => $attachment->resolution,
                        ];
                    })->toArray(),
                ];
            })->toArray(),
            'trialDuration' => $freeTrialLink->time, // days
            'token' => $token,
        ]);
    }

    /**
     * Handle free trial redemption during registration
     * This is called when user completes registration with a valid token
     *
     * @param \Illuminate\Http\Request $request
     * @return \Illuminate\Http\RedirectResponse
     */
    public function redeem(Request $request)
    {
        $request->validate([
            'token' => 'required|string',
        ]);

        $freeTrialLink = FreeTrialLink::where('hash', $request->token)
            ->where('expired_at', '>', now())
            ->first();

        if (!$freeTrialLink) {
            return redirect()->route('home')
                ->with('error', __('Invalid or expired free trial link.'));
        }

        // The actual subscription creation will be handled by the registration flow
        // Store the token in session for use after registration
        session(['free_trial_token' => $request->token]);

        return redirect()->route('register');
    }
}
