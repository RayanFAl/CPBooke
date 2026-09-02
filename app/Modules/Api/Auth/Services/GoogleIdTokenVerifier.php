<?php

namespace App\Modules\Api\Auth\Services;

use App\Modules\Api\Auth\Contracts\GoogleIdTokenVerifierInterface;
use Google\Auth\AccessToken;
use Google\Auth\HttpHandler\HttpHandlerFactory;
use GuzzleHttp\Client as GuzzleClient;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpKernel\Exception\HttpException;

class GoogleIdTokenVerifier implements GoogleIdTokenVerifierInterface
{
    public function __construct(
        private readonly AccessToken $accessToken,
    ) {
    }

    public static function make(): self
    {
        self::ensureSslCertificates();

        $cacert = self::cacertPath();
        $httpHandler = HttpHandlerFactory::build(new GuzzleClient([
            'verify' => $cacert ?? true,
            'timeout' => 15,
        ]));

        return new self(new AccessToken($httpHandler));
    }

    /**
     * {@inheritdoc}
     */
    public function verify(string $idToken): array
    {
        if (! class_exists(\phpseclib3\Crypt\RSA::class)) {
            throw new HttpException(
                500,
                'Google sign-in requires phpseclib/phpseclib. Run: composer require phpseclib/phpseclib:^3.0',
            );
        }

        $clientId = (string) config('services.google.client_id');

        if ($clientId === '') {
            throw new HttpException(500, 'Google sign-in is not configured.');
        }

        try {
            $payload = $this->accessToken->verify($idToken, [
                'audience' => $clientId,
                'throwException' => true,
            ]);
        } catch (\Throwable $exception) {
            $this->logVerificationFailure($exception, $clientId, $idToken);

            throw new HttpException(401, 'The Google ID token is invalid or has expired.');
        }

        if (! is_array($payload)) {
            Log::warning('Google ID token verification failed.', [
                'reason' => 'AccessToken::verify() returned a non-array payload.',
                'configured_audience' => $clientId,
                'token_claims' => $this->decodeTokenClaimsForLog($idToken),
            ]);

            throw new HttpException(401, 'The Google ID token is invalid or has expired.');
        }

        $email = isset($payload['email']) ? trim((string) $payload['email']) : '';
        $sub = isset($payload['sub']) ? trim((string) $payload['sub']) : '';

        if ($email === '' || $sub === '') {
            throw new HttpException(422, 'The Google account is missing required profile information.');
        }

        if (! filter_var($email, FILTER_VALIDATE_EMAIL)) {
            throw new HttpException(422, 'The Google account email is invalid.');
        }

        $emailVerified = $payload['email_verified'] ?? false;

        if (! $this->isTruthy($emailVerified)) {
            throw new HttpException(422, 'The Google account email is not verified.');
        }

        return [
            'sub' => $sub,
            'email' => strtolower($email),
            'name' => isset($payload['name']) ? trim((string) $payload['name']) : '',
            'picture' => isset($payload['picture']) ? trim((string) $payload['picture']) : '',
            'email_verified' => $emailVerified,
        ];
    }

    private function isTruthy(mixed $value): bool
    {
        if (is_bool($value)) {
            return $value;
        }

        if (is_string($value)) {
            return in_array(strtolower($value), ['1', 'true', 'yes'], true);
        }

        return (bool) $value;
    }

    private function logVerificationFailure(\Throwable $exception, string $clientId, string $idToken): void
    {
        Log::warning('Google ID token verification failed.', [
            'exception' => $exception::class,
            'message' => $exception->getMessage(),
            'configured_audience' => $clientId,
            'token_claims' => $this->decodeTokenClaimsForLog($idToken),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function decodeTokenClaimsForLog(string $idToken): array
    {
        $parts = explode('.', $idToken);

        if (count($parts) < 2) {
            return [];
        }

        $payload = json_decode(base64_decode(strtr($parts[1], '-_', '+/'), true), true);

        if (! is_array($payload)) {
            return [];
        }

        return array_intersect_key($payload, array_flip([
            'iss',
            'aud',
            'azp',
            'sub',
            'email',
            'exp',
            'iat',
            'email_verified',
        ]));
    }

    private static function cacertPath(): ?string
    {
        $configured = config('services.google.ca_bundle');

        if (is_string($configured) && $configured !== '' && is_file($configured)) {
            return $configured;
        }

        $default = storage_path('certs/cacert.pem');

        return is_file($default) ? $default : null;
    }

    /**
     * Ensure PHP cURL/OpenSSL can verify Google TLS certificates on Windows/local PHP.
     */
    private static function ensureSslCertificates(): void
    {
        $cacert = self::cacertPath();

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
