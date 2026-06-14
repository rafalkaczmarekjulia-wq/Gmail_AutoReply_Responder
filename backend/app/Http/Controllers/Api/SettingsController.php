<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SettingsController extends Controller
{
    public function show(Request $request): JsonResponse
    {
        $user = $request->user();

        return response()->json([
            'reply_prompt' => $user->reply_prompt ?? User::defaultReplyPrompt(),
            'llm_driver' => config('services.llm.driver'),
            'llm_model' => config('services.llm.openai_model'),
        ]);
    }

    public function updateReplyPrompt(Request $request): JsonResponse
    {
        $data = $request->validate([
            'reply_prompt' => ['required', 'string', 'min:20', 'max:4000'],
        ]);

        $request->user()->update([
            'reply_prompt' => $data['reply_prompt'],
        ]);

        return response()->json([
            'message' => 'Reply prompt saved',
            'reply_prompt' => $request->user()->reply_prompt,
        ]);
    }
}
