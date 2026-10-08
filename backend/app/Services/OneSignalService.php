<?php

namespace App\Services;

use App\Models\Setting;
use App\Models\User;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Http;
use Throwable;

class OneSignalService
{
    /** @param array<string, scalar|null> $data */
    public function sendToUser(User $user, string $title, string $message, array $data = []): bool
    {
        $appId = Setting::valueFor('onesignal_app_id');
        $apiKey = $this->apiKey();
        if (blank($appId) || blank($apiKey)) {
            return false;
        }

        try {
            $response = Http::asJson()
                ->acceptJson()
                ->withToken($apiKey, 'Key')
                ->timeout(8)
                ->retry(2, 250)
                ->post('https://api.onesignal.com/notifications', [
                    'app_id' => $appId,
                    'target_channel' => 'push',
                    'include_aliases' => ['external_id' => [(string) $user->id]],
                    'headings' => ['en' => $title],
                    'contents' => ['en' => $message],
                    'data' => $data,
                ]);

            if ($response->failed()) {
                report(new \RuntimeException('OneSignal push failed: '.$response->body()));

                return false;
            }

            return filled($response->json('id'));
        } catch (Throwable $exception) {
            report($exception);

            return false;
        }
    }

    private function apiKey(): ?string
    {
        $encrypted = Setting::valueFor('onesignal_rest_api_key');
        if (blank($encrypted)) {
            return null;
        }

        try {
            return Crypt::decryptString($encrypted);
        } catch (Throwable) {
            return null;
        }
    }
}
