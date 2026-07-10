<?php

declare(strict_types=1);

namespace Domain\Auth\Support;

use Domain\Auth\DTOs\TokenPairDTO;
use Laravel\Passport\Client;
use Laravel\Passport\Exceptions\OAuthServerException;
use Laravel\Passport\Http\Controllers\AccessTokenController;
use Psr\Http\Message\ResponseInterface;
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
        # Passport 13 hashes the stored secret, so it cannot be read back: the plain text comes
        # from config, and the client row is looked up only for its id.
        $request = app(ServerRequestInterface::class)->withParsedBody(array_merge([
            'client_id'     => (string) $client->getKey(),
            'client_secret' => (string) config('tokens.password_client_secret'),
            'scope'         => '',
        ], $grantParameters));

        # Passport 13's issueToken requires the PSR-7 response to be passed in; 12 created it itself.
        $response = $this->accessTokens->issueToken($request, app(ResponseInterface::class));

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
        # Passport 13 replaced the password_client boolean with the grant_types column.
        $client = Client::query()
            ->whereJsonContains('grant_types', 'password')
            ->where('revoked', false)
            ->first();

        if ($client === null) {
            throw new RuntimeException('No password grant client found. Run: php artisan db:seed --class=PassportSeeder');
        }

        return $client;
    }
}
