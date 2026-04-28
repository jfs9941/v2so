<?php

namespace Module\FreeTrialLink\Service;

use App\Model\FreeTrialLink;
use App\Model\FreeTrialRegister;
use App\Model\Subscription;
use App\Model\Transaction;
use App\Providers\NotificationServiceProvider;
use App\User;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;

/**
 * FreeTrialLink Service
 *
 * Handles free trial link validation, redemption, and subscription creation
 */
class FreeTrialLinkService
{
    /**
     * Validate a free trial link by hash/token
     *
     * @param string $token The free trial link hash
     * @return FreeTrialLink|null
     */
    public function validateToken(string $token): ?FreeTrialLink
    {
        $token = str_starts_with($token, 'free_') ? str_replace('free_', '', $token) : $token;
        return FreeTrialLink::where('hash', $token)
            ->where('expired_at', '>', now())
            ->first();
    }

    /**
     * Check if a free trial link is valid and active
     *
     * @param FreeTrialLink $link
     * @return bool
     */
    public function isLinkActive(FreeTrialLink $link): bool
    {
        return $link->isActive();
    }

    /**
     * Check if the link can be redeemed by the user
     *
     * @param FreeTrialLink $link
     * @param int $userId
     * @return array{valid: bool, reason: string|null}
     */
    public function canRedeem(FreeTrialLink $link, int $userId): array
    {
        // Check if link is active
        if (!$this->isLinkActive($link)) {
            return [
                'valid' => false,
                'reason' => 'free_trial_link_inactive',
            ];
        }

        // Check if creator exists
        $creator = $link->user;
        if (!$creator) {
            return [
                'valid' => false,
                'reason' => 'creator_not_found',
            ];
        }

        // User cannot redeem their own link
        if ($creator->id === $userId) {
            return [
                'valid' => false,
                'reason' => 'self_redemption',
            ];
        }

        // Check if user already has an active subscription to this creator
        $existingSubscription = Subscription::where('sender_user_id', $userId)
            ->where('recipient_user_id', $creator->id)
            ->where('status', Subscription::ACTIVE_STATUS)
            ->where('expires_at', '>', now())
            ->first();

        if ($existingSubscription) {
            return [
                'valid' => false,
                'reason' => 'already_subscribed',
            ];
        }

        return ['valid' => true, 'reason' => null];
    }

    /**
     * Redeem a free trial link for a newly registered user
     * Creates a subscription with the trial duration from the link
     *
     * @param string $token The free trial token from session
     * @param int $userId The newly registered user ID
     * @return array{success: bool, subscription: Subscription|null, message: string, creator?: User}
     */
    public function redeemAfterRegistration(string $token, int $userId): array
    {
        // Find the free trial link
        $freeTrialLink = $this->validateToken($token);

        if (!$freeTrialLink) {
            Log::warning('Free trial link not found during redemption', ['token' => $token]);
            return [
                'success' => false,
                'subscription' => null,
                'message' => 'Free trial link not found or expired.',
            ];
        }

        // Check if creator exists
        $creator = $freeTrialLink->user;
        if (!$creator) {
            Log::warning('Creator not found for free trial link', [
                'token' => $token,
                'creator_id' => $freeTrialLink->user_id,
            ]);
            return [
                'success' => false,
                'subscription' => null,
                'message' => 'Creator not found.',
            ];
        }

        // Check if user can redeem
        $canRedeem = $this->canRedeem($freeTrialLink, $userId);
        if (!$canRedeem['valid']) {
            Log::info('User cannot redeem free trial link', [
                'token' => $token,
                'user_id' => $userId,
                'reason' => $canRedeem['reason'],
            ]);
            return [
                'success' => false,
                'subscription' => null,
                'creator' => $creator,
                'message' => 'Cannot redeem free trial: ' . $canRedeem['reason'],
            ];
        }

        // Calculate expiration date
        $expiresAt = Carbon::now()->addDays($freeTrialLink->time);

        // Create the free trial subscription
        $subscription = Subscription::create([
            'sender_user_id' => $userId,
            'recipient_user_id' => $creator->id,
            'status' => Subscription::ACTIVE_STATUS,
            'type' => Transaction::FREE_TRIAL,
            'provider' => 'system',
            'expires_at' => $expiresAt,
            'amount' => 0,
        ]);

        Log::info('Free trial subscription created', [
            'subscription_id' => $subscription->id,
            'user_id' => $userId,
            'creator_id' => $creator->id,
            'free_trial_link_id' => $freeTrialLink->id,
            'trial_days' => $freeTrialLink->time,
            'expires_at' => $expiresAt,
        ]);

        // Send notification to the subscriber
        NotificationServiceProvider::createNewSubscriptionNotification($subscription);

        // Optional: Create a transaction record for audit trail
        $this->createTransactionRecord($subscription, $freeTrialLink);

        return [
            'success' => true,
            'subscription' => $subscription,
            'message' => 'Free trial redeemed successfully.',
            'creator' => $creator,
        ];
    }

    /**
     * Create a transaction record for the free trial subscription (for audit purposes)
     *
     * @param Subscription $subscription
     * @param FreeTrialLink $freeTrialLink
     * @return Transaction|null
     */
    protected function createTransactionRecord(Subscription $subscription, FreeTrialLink $freeTrialLink): ?Transaction
    {
        try {
            $transaction = Transaction::create([
                'sender_user_id' => $subscription->sender_user_id,
                'recipient_user_id' => $subscription->recipient_user_id,
                'subscription_id' => $subscription->id,
                'type' => Transaction::FREE_TRIAL,
                'status' => Transaction::APPROVED_STATUS,
                'amount' => 0,
                'payment_provider' => 'system',
                'currency' => config('app.site.currency_code', 'USD'),
            ]);

            Log::info('Free trial transaction created', [
                'transaction_id' => $transaction->id,
                'subscription_id' => $subscription->id,
                'free_trial_link_id' => $freeTrialLink->id,
            ]);

            return $transaction;
        } catch (\Exception $e) {
            Log::error('Failed to create free trial transaction', [
                'error' => $e->getMessage(),
                'subscription_id' => $subscription->id,
            ]);
            return null;
        }
    }

    /**
     * Get the free trial token from session
     *
     * @param \Illuminate\Http\Request $request
     * @return string|null
     */
    public function getTokenFromSession($request): ?string
    {
        return $request->session()->get('free_trial_token');
    }

    /**
     * Clear the free trial token from session
     *
     * @param \Illuminate\Http\Request $request
     * @return void
     */
    public function clearTokenFromSession($request): void
    {
        $request->session()->forget('free_trial_token');
    }

    /**
     * Store the free trial token in session
     *
     * @param \Illuminate\Http\Request $request
     * @param string $token
     * @return void
     */
    public function storeTokenInSession($request, string $token): void
    {
        $request->session()->put('free_trial_token', $token);
    }

    /**
     * Find pending free trial registration for a user
     *
     * @param int $userId
     * @return FreeTrialRegister|null
     */
    public function findPendingRegistration(int $userId): ?FreeTrialRegister
    {
        return FreeTrialRegister::where('fan_id', $userId)
            ->where('status', 'pending')
            ->first();
    }

    /**
     * Find pending free trial registration by email
     *
     * @param string $email
     * @return FreeTrialRegister|null
     */
    public function findPendingRegistrationByEmail(string $email): ?FreeTrialRegister
    {
        return FreeTrialRegister::where('email', $email)
            ->where('status', 'pending')
            ->first();
    }
}
