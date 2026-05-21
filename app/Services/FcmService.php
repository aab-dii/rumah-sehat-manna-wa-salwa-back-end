<?php

namespace App\Services;

use App\Models\Notification;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use App\Events\NotificationCountUpdated;
use Google\Auth\Credentials\ServiceAccountCredentials;

class FcmService
{
    /**
     * Send an FCM push notification and save it to the notifications table.
     *
     * @param string      $to     FCM device token of the recipient
     * @param string      $title  Notification title
     * @param string      $body   Notification body
     * @param array       $data   Extra payload (must be string values for FCM)
     * @param string|null $type   Notification type for DB (e.g. 'booking_status', 'payment')
     * @param int|null    $userId The USER ID of the intended recipient (explicit, NOT looked up from token)
     * @param string|null $role   The ROLE of the intended recipient (e.g. 'admin', 'pasien', 'terapis')
     */
    public static function send(
        $to,
        $title,
        $body,
        $data   = [],
        $type   = null,
        $userId = null,
        $role   = null
    ) {
        // 1. Load Service Account JSON Path
        $credentialsPath = config('services.firebase.credentials', env('FIREBASE_CREDENTIALS'));
        $fullPath        = base_path($credentialsPath);

        // 2. Save notification to DB (BEFORE FCM send)
        //    Only save when we know the explicit target user.
        try {
            if ($userId) {
                Notification::create([
                    'user_id'  => $userId,
                    'for_role' => $role,
                    'title'    => $title,
                    'body'     => $body,
                    'type'     => $type,
                    'data'     => !empty($data) ? $data : null,
                    'is_read'  => false,
                ]);
                Log::info("Notification saved to DB for user_id={$userId} role={$role}: {$title}");

                // Broadcast unread count to Pusher
                $unreadCount = Notification::where('user_id', $userId)
                    ->where('is_read', false)
                    ->count();
                event(new NotificationCountUpdated($userId, $unreadCount));
                Log::info("Broadcasted UnreadCount={$unreadCount} to user_id={$userId}");
            } else {
                Log::warning("FCM: Skipped DB save — userId not provided for notification '{$title}'");
            }
        } catch (\Exception $dbEx) {
            Log::warning("FCM DB Save Failed: " . $dbEx->getMessage());
        }

        // 3. Validate FCM credentials
        if (!$credentialsPath || !file_exists($fullPath)) {
            Log::error("FCM Error: File json tidak ditemukan di: " . $fullPath);
            return false;
        }

        if (!$to) {
            Log::warning("FCM Warning: Token tujuan kosong, skip FCM send.");
            return false;
        }

        try {
            // 4. Get Access Token (OAuth2)
            $credentials = new ServiceAccountCredentials(
                'https://www.googleapis.com/auth/cloud-platform',
                $fullPath
            );

            $client  = new \GuzzleHttp\Client(['verify' => false]);
            $handler = function ($request, $options = []) use ($client) {
                return $client->send($request, $options);
            };

            $tokenArray  = $credentials->fetchAuthToken($handler);
            $accessToken = $tokenArray['access_token'];

            // 5. Get Project ID dari JSON
            $json      = json_decode(file_get_contents($fullPath), true);
            $projectId = $json['project_id'];

            // 6. Construct FCM Payload
            $payload = [
                'message' => [
                    'token' => $to,
                    'notification' => [
                        'title' => $title,
                        'body'  => $body,
                    ],
                    'data'    => count($data) > 0 ? array_map('strval', $data) : null,
                    'android' => [
                        'priority'     => 'high',
                        'notification' => [
                            'sound'      => 'default',
                            'channel_id' => 'rumah_sehat_channel',
                        ],
                    ],
                ],
            ];

            // 7. Send to Google FCM API V1
            $response = Http::withoutVerifying()->withHeaders([
                'Authorization' => 'Bearer ' . $accessToken,
                'Content-Type'  => 'application/json',
            ])->post("https://fcm.googleapis.com/v1/projects/{$projectId}/messages:send", $payload);

            if ($response->successful()) {
                Log::info("FCM V1 Berhasil: " . $response->body());
                return true;
            } else {
                Log::error("FCM V1 Gagal: " . $response->body());
                return false;
            }

        } catch (\Exception $e) {
            Log::error("FCM Exception: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Kirim notifikasi FCM ke semua admin & super admin aktif.
     * Dipakai saat ada booking baru masuk.
     */
    public static function sendToAdmins(
        string $title,
        string $body,
        array $data = []
    ): void {
        try {
            $admins = \App\Models\User::whereIn('role', ['admin', 'super_admin'])
                ->where('is_active', true)
                ->get();

            foreach ($admins as $admin) {
                $type = $data['type'] ?? 'new_booking';
                self::send(
                    $admin->fcm_token,
                    $title,
                    $body,
                    $data,
                    $type,
                    $admin->id,
                    $admin->role
                );
            }
        } catch (\Exception $e) {
            Log::error('FcmService::sendToAdmins failed: ' . $e->getMessage());
        }
    }
}