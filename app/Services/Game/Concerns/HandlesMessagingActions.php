<?php

namespace App\Services\Game\Concerns;

use App\Enums\GameActionType;
use App\Exceptions\GameActionException;
use App\Models\GameCharacter;
use App\Models\GameMessage;
use App\Models\User;
use Illuminate\Support\Facades\RateLimiter;

/**
 * Chat and whisper actions plus their spam protection.
 */
trait HandlesMessagingActions
{
    /**
     * Global chat is intentionally very small here: persist first, decorate later in the UI.
     *
     * @param  array<string, mixed>  $payload
     * @return array{message: string, cooldown_seconds: int, rewards: array<string, int>, target_location_id: int}
     */
    private function sendGlobalChat(GameCharacter $character, array $payload): array
    {
        GameMessage::query()->create([
            'sender_character_id' => $character->id,
            'recipient_character_id' => null,
            'channel' => 'global',
            'body' => trim((string) $payload['message']),
        ]);

        return [
            'message' => __('Your global message was posted to the realm chat.'),
            'cooldown_seconds' => 0,
            'rewards' => [],
            'target_location_id' => $character->location_id,
        ];
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array{message: string, cooldown_seconds: int, rewards: array<string, int>, target_location_id: int}
     */
    private function sendWhisper(GameCharacter $character, array $payload): array
    {
        $recipientName = trim((string) $payload['recipient_name']);
        $message = trim((string) $payload['message']);

        $recipient = GameCharacter::query()
            ->whereRaw('lower(name) = ?', [mb_strtolower($recipientName)])
            ->first();

        if ($recipient === null) {
            throw new GameActionException(__('That character could not be found for whisper delivery.'));
        }

        if ($recipient->is($character)) {
            throw new GameActionException(__('You cannot whisper to your own character.'));
        }

        GameMessage::query()->create([
            'sender_character_id' => $character->id,
            'recipient_character_id' => $recipient->id,
            'channel' => 'whisper',
            'body' => $message,
        ]);

        return [
            'message' => __('You whisper to :name.', ['name' => $recipient->name]),
            'cooldown_seconds' => 0,
            'rewards' => [],
            'target_location_id' => $character->location_id,
        ];
    }

    /**
     * Chat spam rules live next to chat writes so rate-limit changes stay easy to find.
     */
    private function guardChatRateLimit(User $user, GameActionType $action): void
    {
        $key = 'chat:'.$action->value.':'.$user->id;
        $maxAttempts = $action === GameActionType::GlobalChat ? 12 : 20;

        if (RateLimiter::tooManyAttempts($key, $maxAttempts)) {
            throw new GameActionException(
                __('Chat is moving too fast. Wait :seconds seconds before sending another message.', [
                    'seconds' => RateLimiter::availableIn($key),
                ]),
            );
        }

        RateLimiter::hit($key, 60);
    }
}
