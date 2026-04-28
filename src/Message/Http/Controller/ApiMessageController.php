<?php

namespace Module\Message\Http\Controller;

use App\Http\Controllers\Controller;
use App\Providers\GenericHelperServiceProvider;
use App\Providers\MessengerProvider;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Tymon\JWTAuth\Facades\JWTAuth;

class ApiMessageController extends Controller
{
    /**
     * Send a new direct message to a user.
     *
     * POST /api/messages/send
     * Body: { receiver_id, message }
     */
    public function send(Request $request): JsonResponse
    {
        $request->validate([
            'receiver_id' => 'required|integer|exists:users,id',
            'message' => 'required|string|max:800',
        ]);

        $sender = JWTAuth::user();
        $receiverId = (int) $request->input('receiver_id');

        // Cannot message yourself
        if ($sender->id === $receiverId) {
            return response()->json([
                'success' => false,
                'message' => __('You cannot send a message to yourself.'),
            ], 400);
        }

        // Check block list — both directions
        if (GenericHelperServiceProvider::hasUserBlocked($receiverId, $sender->id)) {
            return response()->json([
                'success' => false,
                'message' => __('This user has blocked you.'),
            ], 403);
        }

        if (GenericHelperServiceProvider::hasUserBlocked($sender->id, $receiverId)) {
            return response()->json([
                'success' => false,
                'message' => __('You have blocked this user.'),
            ], 403);
        }

        try {
            $provider = new MessengerProvider($sender);
            $provider->sendUserMessage([
                'senderID' => $sender->id,
                'receiverID' => $receiverId,
                'messageValue' => $request->input('message'),
                'messagePrice' => 0,
                'attachments' => [],
            ]);

            return response()->json([
                'success' => true,
                'message' => __('Message sent.'),
            ]);
        } catch (\Exception $e) {
            Log::error('Send message error: ' . $e->getMessage());

            return response()->json([
                'success' => false,
                'message' => __('Failed to send message.'),
            ], 500);
        }
    }
}
