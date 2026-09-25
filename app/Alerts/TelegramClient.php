<?php

namespace App\Alerts;

use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Http;
use RuntimeException;

/** Minimal Telegram Bot API client: send a message (optionally with buttons) and answer button presses. */
class TelegramClient
{
    public function configured(): bool
    {
        return filled(config('services.telegram.token'));
    }

    /** @param  list<list<array{text: string, callback_data: string}>>|null  $keyboard */
    public function sendMessage(string $chatId, string $text, ?array $keyboard = null): void
    {
        $this->call('sendMessage', array_filter([
            'chat_id' => $chatId,
            'text' => $text,
            'reply_markup' => $keyboard ? ['inline_keyboard' => $keyboard] : null,
        ]));
    }

    public function answerCallback(string $callbackId, string $text): void
    {
        $this->call('answerCallbackQuery', ['callback_query_id' => $callbackId, 'text' => $text]);
    }

    public function removeButtons(string $chatId, int $messageId): void
    {
        $this->call('editMessageReplyMarkup', ['chat_id' => $chatId, 'message_id' => $messageId, 'reply_markup' => ['inline_keyboard' => []]]);
    }

    /** @param  array<string, mixed>  $params */
    private function call(string $method, array $params): void
    {
        $token = (string) config('services.telegram.token');
        if ($token === '') {
            throw new RuntimeException('TELEGRAM_BOT_TOKEN is not set.');
        }

        try {
            Http::timeout(10)->post("https://api.telegram.org/bot{$token}/{$method}", $params)->throw();
        } catch (RequestException $e) {
            // Guzzle puts the request URL, and so the bot token, in its message. Never let that reach logs or API responses.
            throw new RuntimeException(str_replace($token, '***', $e->getMessage()));
        }
    }
}
