<?php

namespace Module\Message\Http\Controller;

use App\Http\Controllers\Controller;
use App\Model\Attachment;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Module\Message\Core\AutomateScenarioEnum;
use Module\Message\Core\MessageAutomateSetting;
use Module\Message\Core\MessageTemplate;
use Module\Message\Http\Request\ToggleSetting;

class MessageAutomateController extends Controller
{
    public function storeSetting(Request $request): JsonResponse
    {
        $attachments = $request->input('attachments', []);
        $message = $request->input('message', '');
        $scenario = $request->input('scenario', 1);
        $enabled = $request->input('enabled', false);
        $price = $request->input('price', 0);
        if (!empty($attachments)) {
            // update ownership of attachments
            Attachment::whereIn('id', $attachments)->update(['user_id' => auth()->user()->id]);
        } else {
            $price = 0;
        }

        $automateSettingsService = new MessageAutomateSetting();
        $automateSettingsService->setAutomateTemplate(
            auth()->user(),
            new MessageTemplate($enabled, AutomateScenarioEnum::from($scenario),
                $message ?? '', $attachments ?? [], $price ?? 0)
        );

        return response()->json([
            'status' => 'success',
            'data' => [
                'attachments' => $attachments,
                'message' => $message,
                'enabled' => $enabled,
            ],
        ]);
    }

    public function toggleSetting(ToggleSetting $request): JsonResponse
    {
        $scenario = $request->get('scenario', 1);
        $enabled = $request->get('enabled', false);

        $automateSettingsService = new MessageAutomateSetting();
        $template = $automateSettingsService->getAutomateTemplate(auth()->user(), AutomateScenarioEnum::from($scenario));
        $template->enabled = $enabled;
        $template->price = $request->input('price', 0);
        $automateSettingsService->setAutomateTemplate(
            auth()->user(),
            $template
        );

        return response()->json([
            'status' => 'success'
        ]);
    }
}