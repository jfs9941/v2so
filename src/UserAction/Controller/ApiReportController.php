<?php

namespace Module\UserAction\Controller;

use App\Http\Controllers\Controller;
use App\Model\UserReport;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class ApiReportController extends Controller
{
    /**
     * Report a post or user.
     *
     * POST /api/report
     */
    public function store(Request $request)
    {
        try {
            UserReport::create([
                'from_user_id' => Auth::id(),
                'post_id' => $request->get('post_id'),
                'user_id' => $request->get('user_id'),
                'type' => $request->get('type'),
                'details' => $request->get('details'),
                'status' => UserReport::$statusMap[0],
            ]);

            return response()->json(['success' => true, 'message' => 'Report sent.']);
        } catch (\Exception $exception) {
            return response()->json([
                'success' => false,
                'message' => 'An internal error has occurred.',
            ], 500);
        }
    }
}
