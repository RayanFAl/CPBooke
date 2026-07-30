<?php

namespace App\Modules\Notifications\Services;

use Google\Auth\Credentials\ServiceAccountCredentials;
use Google\Auth\HttpHandler\HttpHandlerFactory;
use GuzzleHttp\Client as GuzzleClient;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use RuntimeException;

class FcmHttpV1Client
{
    /**
     * Send one FCM HTTP v1 message to a device token.
     *
     * @param  array<string, string>  $data
     * @return array<string, mixed>
     */
    public function sendToToken(string $deviceToken, string $title, string $body, array $data = []): array
    {
        $credentialsPath = $this->credentialsPath();

        if ($credentialsPath === null) {
            return [
                'delivered' => false,
                'reason' => 'missing_credentials',
            ];
        }

        $projectId = $this->projectId($credentialsPath);
        $accessToken = $this->accessToken($credentialsPath);

        $payload = [
            'message' => [
                'token' => $deviceToken,
                'notification' => [
                    'title' => $title,
                    'body' => $body,
                ],
                'data' => $data,
                'android' => [
                    'priority' => 'HIGH',
                    'notification' => [
                        'channel_id' => 'cpbooke_default',
                        'sound' => 'default',
                        'default_vibrate_timings' => true,
                        'notification_priority' => 'PRIORITY_HIGH',
                    ],
                ],
                'apns' => [
                    'payload' => [
                        'aps' => [
                            'alert' => [
                                'title' => $title,
                                'body' => $body,
                            ],
                            'sound' => 'default',
                            'badge' => 1,
                        ],
                    ],
                ],
            ],
        ];

        $response = Http::withToken($accessToken)
            ->withOptions(['verify' => is_file(storage_path('certs/cacert.pem')) ? storage_path('certs/cacert.pem') : true])
            ->acceptJson()
            ->post("https://fcm.googleapis.com/v1/projects/{$projectId}/messages:send", $payload);

        if (! $response->successful()) {
            Log::warning('FCM HTTP v1 push failed', [
                'status' => $response->status(),
                'body' => $response->json() ?? $response->body(),
                'project_id' => $projectId,
            ]);

            return [
                'delivered' => false,
                'reason' => 'fcm_http_error',
                'status' => $response->status(),
                'response' => $response->json() ?? $response->body(),
            ];
        }

        return [
            'delivered' => true,
            'response' => $response->json() ?? [],
        ];
    }

    public function isConfigured(): bool
    {
        return $this->credentialsPath() !== null;
    }

    public function credentialsPath(): ?string
    {
        $configured = trim((string) config('services.notifications.firebase_credentials'));

        if ($configured !== '') {
            $absolute = $this->absolutePath($configured);

            return is_file($absolute) ? $absolute : null;
        }

        $default = storage_path('app/firebase/firebase_credentials.json');

        return is_file($default) ? $default : null;
    }

    private function absolutePath(string $path): string
    {
        if ($this->isAbsolutePath($path)) {
            return $path;
        }

        return base_path($path);
    }

    private function isAbsolutePath(string $path): bool
    {
        return str_starts_with($path, '/')
            || (bool) preg_match('/^[A-Za-z]:[\\\\\\/]/', $path);
    }

    private function projectId(string $credentialsPath): string
    {
        /** @var array<string, mixed> $json */
        $json = json_decode((string) file_get_contents($credentialsPath), true) ?: [];
        $projectId = trim((string) ($json['project_id'] ?? ''));

        if ($projectId === '') {
            throw new RuntimeException('Firebase credentials JSON is missing project_id.');
        }

        return $projectId;
    }

    private function accessToken(string $credentialsPath): string
    {
        $this->ensureSslCertificates();

        /** @var array<string, mixed> $json */
        $json = json_decode((string) file_get_contents($credentialsPath), true) ?: [];

        $credentials = new ServiceAccountCredentials(
            'https://www.googleapis.com/auth/firebase.messaging',
            $json,
        );

        $httpHandler = HttpHandlerFactory::build(new GuzzleClient([
            'verify' => $this->cacertPath() ?? true,
            'timeout' => 20,
        ]));

        $token = $credentials->fetchAuthToken($httpHandler);
        $accessToken = trim((string) ($token['access_token'] ?? ''));

        if ($accessToken === '') {
            throw new RuntimeException('Unable to obtain Firebase access token.');
        }

        return $accessToken;
    }

    private function cacertPath(): ?string
    {
        $cacert = storage_path('certs/cacert.pem');

        return is_file($cacert) ? $cacert : null;
    }

    /**
     * Ensure PHP cURL/OpenSSL can verify Google TLS certificates on Windows/WAMP.
     */
    private function ensureSslCertificates(): void
    {
        $cacert = $this->cacertPath();

        if ($cacert === null) {
            return;
        }

        if (! ini_get('curl.cainfo')) {
            ini_set('curl.cainfo', $cacert);
        }

        if (! ini_get('openssl.cafile')) {
            ini_set('openssl.cafile', $cacert);
        }

        putenv('SSL_CERT_FILE='.$cacert);
        putenv('CURL_CA_BUNDLE='.$cacert);
    }
}
