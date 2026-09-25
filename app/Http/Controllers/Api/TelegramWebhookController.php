<?php

namespace App\Http\Controllers\Api;

use App\Alerts\TelegramClient;
use App\Enums\IncidentState;
use App\Http\Controllers\Controller;
use App\Models\AlertChannel;
use App\Models\Incident;
use App\Services\IncidentService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Throwable;

/**
 * Receives Telegram button presses (register with setWebhook and a secret_token). Anyone can message a public bot,
 * so a press only counts if it comes from a chat configured as an enabled alert channel.
 */
class TelegramWebhookController extends Controller
{
    public function __invoke(Request $request, TelegramClient $telegram, IncidentService $incidents): JsonResponse
    {
        $secret = (string) config('services.telegram.webhook_secret');
        // Fail closed: with no secret configured nothing is accepted.
        if ($secret === '' || ! hash_equals($secret, (string) $request->header('X-Telegram-Bot-Api-Secret-Token', ''))) {
            abort(403);
        }

        $callback = $request->input('callback_query');
        if (! is_array($callback)) {
            return response()->json(['ok' => true]);
        }

        // From here on always answer 200: a non-2xx makes Telegram retry the same update.
        try {
            $this->handle($callback, $telegram, $incidents);
        } catch (Throwable $e) {
            report($e);
        }

        return response()->json(['ok' => true]);
    }

    private function handle(array $callback, TelegramClient $telegram, IncidentService $incidents): void
    {
        $chatId = (string) data_get($callback, 'message.chat.id');
        $callbackId = (string) data_get($callback, 'id');

        $known = AlertChannel::where('type', 'telegram')->where('enabled', true)->where('target', $chatId)->exists();
        if (! $known) {
            $telegram->answerCallback($callbackId, 'This chat is not authorised.');

            return;
        }
        if (! preg_match('/^ack:(\d+)$/', (string) data_get($callback, 'data'), $m)) {
            $telegram->answerCallback($callbackId, 'Unknown action.');

            return;
        }

        $incident = Incident::find((int) $m[1]);
        if (! $incident) {
            $telegram->answerCallback($callbackId, 'Incident not found.');

            return;
        }

        $who = data_get($callback, 'from.username') ? '@'.data_get($callback, 'from.username') : (data_get($callback, 'from.first_name') ?? 'Telegram user');

        try {
            $incidents->transition($incident, IncidentState::Acknowledged, null, "Acknowledged via Telegram by {$who}.");
            $telegram->answerCallback($callbackId, 'Acknowledged.');
        } catch (ValidationException) {
            // Already acknowledged, resolved, or otherwise past this step: say so instead of failing.
            $telegram->answerCallback($callbackId, "Incident is already {$incident->fresh()->state->value}.");
        }

        if ($messageId = data_get($callback, 'message.message_id')) {
            $telegram->removeButtons($chatId, (int) $messageId);
        }
    }
}
