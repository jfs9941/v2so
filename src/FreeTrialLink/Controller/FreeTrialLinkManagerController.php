<?php

namespace Module\FreeTrialLink\Controller;

use App\Http\Controllers\Controller;
use App\Model\FreeTrialLink;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;

class FreeTrialLinkManagerController extends Controller
{
    /**
     * Display the free trial links page.
     */
    public function index()
    {
        $user = Auth::user();
        $freeTrialLinks = FreeTrialLink::where('user_id', $user->id)
            ->withCount(['registers as claims_count' => function ($query) {
                $query->where('status', 'redeemed');
            }])
            ->orderBy('created_at', 'desc')
            ->get();

        return view('pages.free-trial-links', [
            'freeTrialLinks' => $freeTrialLinks,
        ]);
    }

    /**
     * Store a new free trial link.
     */
    public function store(Request $request)
    {
        $user = Auth::user();
        $request->validate([
            'name' => 'required|string|max:255',
            'time' => 'required|integer|in:1,7,30',
            'offer_limit' => 'required|integer|min:1|max:1000',
            'expired_at' => 'required|date|after:today',
        ]);

        try {
            // Generate unique hash
            $hash = $this->generateUniqueHash();

            FreeTrialLink::create([
                'user_id' => $user->id,
                'name' => $request->name,
                'hash' => $hash,
                'time' => $request->time,
                'offer_limit' => $request->offer_limit,
                'expired_at' => $request->expired_at
            ]);
        } catch (\Exception $e) {
            return redirect()->back()->with('error', __('An error occurred, please try again'));
        }

        return redirect()->back()->with('message', __('Free trial link created successfully!'));
    }

    /**
     * Update a free trial link.
     */
    public function update(Request $request, $id)
    {
        $user = Auth::user();
        $request->validate([
            'name' => 'required|string|max:255',
            'time' => 'required|integer|in:1,7,30',
            'offer_limit' => 'required|integer|min:1|max:1000',
            'expired_at' => 'required|date|after:today',
        ]);

        $freeTrialLink = FreeTrialLink::where('id', $id)
            ->where('user_id', $user->id)
            ->firstOrFail();

        try {
            $freeTrialLink->update([
                'name' => $request->name,
                'time' => $request->time,
                'offer_limit' => $request->offer_limit,
                'expired_at' => $request->expired_at,
            ]);
        } catch (\Exception $e) {
            return redirect()->back()->with('error', __('An error occurred, please try again'));
        }

        return redirect()->back()->with('message', __('Free trial link updated successfully!'));
    }

    /**
     * Delete a free trial link.
     */
    public function destroy($id)
    {
        $freeTrialLink = FreeTrialLink::where('id', $id)
            ->where('user_id', Auth::id())
            ->firstOrFail();

        $freeTrialLink->delete();

        return redirect()->back()->with('message', __('Link removed successfully!'));
    }

    /**
     * Generate a unique hash for the free trial link.
     */
    private function generateUniqueHash(): string
    {
        do {
            $hash = Str::random(10);
        } while (FreeTrialLink::where('hash', $hash)->exists());

        return $hash;
    }

    /**
     * API: Generate free trial signup link URL.
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function generateSignupLink(Request $request): JsonResponse
    {
        $request->validate([
            'type' => 'required|in:google,x,email',
            'token' => 'required|string',
        ]);

        $type = $request->input('type');
        $token = $request->input('token');
        $ref = 'free_' . $token;

        $url = match ($type) {
            'email' => url('/register/step1/fan?ref=' . $ref),
            'google' => url('/auth/google?ref=' . $ref),
            'x' => url('/auth/twitter?ref=' . $ref),
        };

        return response()->json([
            'success' => true,
            'data' => [
                'url' => $url,
                'type' => $type,
                'ref' => $ref,
            ],
        ]);
    }
}
