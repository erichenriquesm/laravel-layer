<?php

declare(strict_types=1);

namespace Domain\Auth\Support;

use Domain\Auth\DTOs\TokenPairDTO;
use Laravel\Passport\Client;
use Laravel\Passport\Exceptions\OAuthServerException;
use Laravel\Passport\Http\Controllers\AccessTokenController;
use Psr\Http\Message\ServerRequestInterface;
use RuntimeException;

final class PassportTokenIssuer
{
    public function __construct(
        private readonly AccessTokenController $accessTokens,
    ) {}

    /**
     * @throws OAuthServerException when the grant rejects the credentials or the refresh token.
     */
    public function issue(array $grantParameters): TokenPairDTO
    {
        $client = $this->passwordClient();

        # Calling the controller in process instead of re-entering the HTTP kernel: a nested
        # request would run the global middleware again and burn a second rate limit hit.
        $request = app(ServerRequestInterface::class)->withParsedBody(array_merge([
            'client_id'     => (string) $client->id,
            'client_secret' => $client->secret,
            'scope'         => '',
        ], $grantParameters));

        $response = $this->accessTokens->issueToken($request);

        /** @var array{access_token: string, refresh_token: string, expires_in: int, token_type: string} $payload */
        $payload = json_decode($response->getContent(), true, 512, JSON_THROW_ON_ERROR);

        return new TokenPairDTO(
            accessToken: $payload['access_token'],
            refreshToken: $payload['refresh_token'],
            expiresIn: $payload['expires_in'],
            tokenType: $payload['token_type'],
        );
    }

    private function passwordClient(): Client
    {
        $client = Client::query()
            ->where('password_client', true)
            ->where('revoked', false)
            ->first();

        if ($client === null) {
            throw new RuntimeException('No password grant client found. Run: php artisan db:seed --class=PassportSeeder');
        }

        return $client;
    }
}
