---
name: domain-architecture
description: Architecture rules for laravel-layer — pragmatic ports & adapters, actions, spatie/laravel-data DTOs, per-domain providers and error codes. ALWAYS read before creating or changing anything under domain/ or app/, and before adding an endpoint, a domain, a DTO, an action or an exception.
---

# laravel-layer architecture

Read this before touching `domain/` or `app/`. Every rule below has a consequence that has already been paid for in this project.

## Mental model: driver ports yes, driven ports no

Hexagonal architecture applied only as far as it pays for itself. A **driver port** (input) is the interface the outside drives the app through — a use case; the app implements it. A **driven port** (output) is the interface the domain *owns and calls* to reach an external resource; an adapter implements it. Here every use case is an `Action` implementing a `Contract` (driver port), and callers depend on the contract.

Persistence stays Eloquent-direct — no repository, no driven port; do not propose one. The **one** driven port is the message queue: `Domain\Shared\Contracts\MessageQueue`, implemented by the `RabbitMqMessageQueue` adapter in `domain/Shared/Queue/`. Abstracting "enqueue work" is worth it (producers never name a broker); abstracting CRUD is not. A driven port's adapter lives **inside the domain**, next to the port — the queue adapter sits in `domain/Shared/Queue/`, not outside `domain/`.

```php
// Caller depends on the contract, never on the concrete class.
public function __construct(private readonly LoginContract $loginAction) {}

// The action uses Eloquent / Passport directly — this is the accepted trade-off.
class Login implements LoginContract
{
    public function handle(LoginDTO $input): TokenPairDTO { /* ... */ }
}
```

## Domain layout

Each domain is self-contained. Nothing outside `domain/` needs editing to add one.

```
domain/<Domain>/
├── Contracts/   driver ports: interfaces with handle()
├── Actions/     implementations: class X implements XContract
├── DTOs/        input (validates) and output (serializes), extends Data
├── Exceptions/  domain exceptions + the <Domain>ErrorCode enum
├── Providers/   one or more *ServiceProvider with the domain's public array $bindings
├── Support/     internal collaborators, no port (e.g. PassportTokenIssuer)
├── Queue/       the one driven-port adapter: RabbitMqMessageQueue + DeferredCall/dispatcher (Shared only)
└── Tests/{Unit,Feature}/
```

## The controller only translates HTTP

A controller method has exactly one useful line: call the action. No `Auth::user()`, no `App\Models\*`, no `Laravel\Passport\*`, no `instanceof`, no business `if`, no building a DTO from a model. This has been violated twice here — do not repeat it, and never move a domain decision to the edge just to silence Intelephense.

```php
public function me(): UserDTO
{
    return $this->getAuthenticatedUserAction->handle();
}
```

## Actions use handle(), never exec()

A single-method object, injected and discarded, is an action. The method is `handle`. There is no `Services/` layer in this project.

```php
class RefreshToken implements RefreshTokenContract
{
    public function handle(RefreshTokenDTO $input): TokenPairDTO { /* ... */ }
}
```

## DTOs do validation and serialization

Input DTOs validate through `laravel-data` attributes — no FormRequest, no `Validator::make` in the controller. Output DTOs are returned straight from the controller — no API Resource, no response envelope. Note: `laravel-data` returns 201 for every POST; override `calculateResponseStatus()` when the endpoint creates nothing (e.g. login).

```php
final class LoginDTO extends Data
{
    public function __construct(
        #[Required, EmailRule] public readonly string $email,
        #[Required, StringType] public readonly string $password,
    ) {}
}
```

## Bindings live in the domain

Each domain declares its own bindings in its `Providers/` directory; the glob (`domain/*/Providers/*ServiceProvider.php`) discovers every `*ServiceProvider` there, so a domain may have more than one. If you find yourself opening `AppServiceProvider` to register a bind, you stopped in the wrong place.

```php
class AuthDomainServiceProvider extends ServiceProvider
{
    public array $bindings = [
        LoginContract::class => Login::class,
    ];
}
```

## Every error carries an opaque numeric code

Every error response has the same shape; only 422 adds `errors`. Domain codes are an `int` enum in `domain/<Domain>/Exceptions/<Domain>ErrorCode.php`, in a reserved range (Auth 1100-1199); general codes live in `GeneralErrorCode` (1000-1099). Each case has `description()` — the real meaning, for logs and developers — and `publicMessage()` — the only text the client sees, kept generic so a raw response reveals nothing (every auth failure answers the same `Authentication failed`). An exception joins the contract by implementing `Domain\Shared\Contracts\HasErrorCode` (`errorCode(): int`, `httpStatus(): int`). The number is public contract; a front maps it to its own copy.

```php
enum AuthErrorCode: int
{
    case InvalidCredentials = 1101;

    public function description(): string    // internal: logs, tests
    {
        return match ($this) {
            self::InvalidCredentials => 'The email or the password is wrong',
        };
    }

    public function publicMessage(): string  // on the wire: says nothing specific
    {
        return 'Authentication failed';
    }
}
// wire: { "code": 1101, "message": "Authentication failed" }
```

## Error-handling invariants (do not undo)

The client only ever sees `publicMessage()`; the exception's own message never reaches the wire, so a wrong password and an unknown email are indistinguishable (no enumeration), and a 500 never echoes a query or an API key. The `Handler` forwards `HttpExceptionInterface` headers (the 429 carries `Retry-After` on itself). Passport's `OAuthServerException` extends `HttpResponseException`, which the router renders natively before the `Handler` runs, so `/oauth/*` keep the RFC OAuth error shape without any special case here.

```php
// Handler: classify the exception, then build the {code, message} envelope.
[$code, $status] = $this->classify($e);
$headers = $e instanceof HttpExceptionInterface ? $e->getHeaders() : [];
```

## Authentication uses the password grant

`/login` uses the password grant (enabled in `AuthServiceProvider`; Passport 11+ disables it by default), returning a short access token plus a rotating single-use refresh token. TTLs live in `config/tokens.php`, never hardcoded. Personal access tokens have no refresh token — do not go back to `createToken()`. The grant is called in-process, never via a nested HTTP request (that would re-enter the kernel and burn a second rate-limit hit).

```php
// AuthServiceProvider::boot()
Passport::enablePasswordGrant();
Passport::tokensExpireIn(CarbonInterval::minutes(config('tokens.access_token_minutes')));
```

## Rate limiting

`throttle:global` sits in the Kernel's global stack so it also covers Passport's routes; sensitive routes stack their own limiter. Never put the same limiter in both the `api` group and the global stack (double counting). The login limiter uses two keys at once — IP (spraying) and normalized email (credential stuffing from an IP pool).

```php
RateLimiter::for('login', function (Request $request) {
    $email = Str::lower(trim((string) $request->input('email')));
    $limits = [Limit::perMinute(5)->by($request->ip())];
    if ($email !== '') {
        $limits[] = Limit::perMinute(10)->by('login-account:'.$email);
    }
    return $limits;
});
```

## All generated code is in English

Everything you write is English: class, method, variable, property and enum names; database columns; comments; log messages; and any hardcoded string. This is not negotiable and applies even when the prompt, the conversation or the surrounding notes are in Portuguese. Translate the intent, do not transliterate the request. The only Portuguese allowed is inside user-facing strings that a product decision explicitly requires — and there are none in this codebase today.

```php
// right
class RegisterUser implements RegisterUserContract {
    public function handle(RegisterUserDTO $input): UserDTO { /* ... */ }
}

// wrong — never do this, regardless of the prompt's language
class CadastrarUsuario implements CadastrarUsuarioContract {
    public function executar(DadosUsuarioDTO $dados): UsuarioDTO { /* ... */ }
}
```

## Comments only when relevant

A comment exists only to say what the code cannot: a positional flag, a magic number, a non-obvious constraint. Never narrate the code. English, like everything else, log messages included.

```php
# delivery_mode 2 persists the message to disk, so it survives a broker restart.
'delivery_mode' => 2,
```

## Checklist: new domain

Create the six pieces, then dump the autoloader. Do not edit `app/`.

```
domain/Billing/
├── Contracts/BillingContract.php               handle(BillingDTO): SomeDTO
├── Actions/Billing.php                         implements BillingContract
├── DTOs/BillingDTO.php                          extends Data
├── Exceptions/BillingErrorCode.php              enum: BILLING_*
├── Providers/BillingDomainServiceProvider.php   public array $bindings
└── Tests/{Unit,Feature}/
# composer dump-autoload
```

## Checklist: new endpoint

Route → thin controller method → action; input and output are DTOs; errors implement `HasErrorCode`; if the HTTP contract changes, update the README table and verify each row against the running API.

```php
Route::post('subscribe', [BillingController::class, 'subscribe'])->middleware('throttle:...');

public function subscribe(SubscribeDTO $input): SubscriptionDTO
{
    return $this->subscribeAction->handle($input);
}
```

## Verification is mandatory

Never conclude something works without exercising the behavior. `php -l` on a generator says nothing about the generated code; a green suite does not prove a test detects the bug (see the `domain-testing` skill).

```bash
docker compose exec -T app php artisan test
docker compose exec -T app sh -lc 'composer dump-autoload -q && php -l <file>'
curl -s -i -X POST http://localhost:81/login -H 'Accept: application/json' \
  -H 'Content-Type: application/json' -d '{"email":"...","password":"..."}'
```

## Known issues (do not "fix" in passing without saying so)

These predate or are tracked separately; touching them silently hides them.

```
(none outstanding)

caveat: RabbitMqMessageQueueTest is an integration test against a real broker; it SKIPS when
        RabbitMQ is unreachable, so a CI without a broker reports those as skipped, not passed.
```
