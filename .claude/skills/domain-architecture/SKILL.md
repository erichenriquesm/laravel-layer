---
name: domain-architecture
description: Architecture rules for laravel-layer — pragmatic ports & adapters, actions, spatie/laravel-data DTOs, per-domain providers and error codes. ALWAYS read before creating or changing anything under domain/ or app/, and before adding an endpoint, a domain, a DTO, an action or an exception.
---

# laravel-layer architecture

Read this before touching `domain/` or `app/`. Every rule below has a consequence that has already been paid for in this project.

## Mental model: driver ports yes, driven ports no

Hexagonal architecture applied only as far as it pays for itself. Every use case is an `Action` implementing a `Contract`, and callers depend on the contract. But actions talk to Eloquent, Passport and facades directly — there is no repository and no persistence abstraction. Do not propose repositories or driven ports.

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
├── Providers/   <Domain>DomainServiceProvider with public array $bindings
├── Support/     internal collaborators, no port (e.g. PassportTokenIssuer)
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

Each domain declares its own bindings in its provider, discovered by glob (`domain/*/Providers/*ServiceProvider.php`). If you find yourself opening `AppServiceProvider` to register a bind, you stopped in the wrong place.

```php
class AuthDomainServiceProvider extends ServiceProvider
{
    public array $bindings = [
        LoginContract::class => Login::class,
    ];
}
```

## Every error carries a stable code

Every error response has the same shape; only 422 adds `errors`. Domain codes are a string enum in `domain/<Domain>/Exceptions/<Domain>ErrorCode.php`, all prefixed (`AUTH_`); general codes live in `GeneralErrorCode`. An exception joins the contract by implementing `Domain\Shared\Contracts\HasErrorCode`. The `code` is public contract; the `message` is human and may change.

```php
enum AuthErrorCode: string
{
    case InvalidCredentials = 'AUTH_INVALID_CREDENTIALS';

    public function description(): string
    {
        return match ($this) {
            self::InvalidCredentials => 'The email or the password is wrong',
        };
    }
}
// wire: { "code": "AUTH_INVALID_CREDENTIALS", "message": "Verify your credentials" }
```

## Error-handling invariants (do not undo)

`AUTH_INVALID_CREDENTIALS` never distinguishes an unknown email from a wrong password (distinguishing enables account enumeration). A 500 never echoes the exception message (it may hold a query or an API key). The `Handler` must forward `HttpExceptionInterface` headers (the 429 carries `Retry-After` on itself) and must delegate to `$e->render($request)` when present, or Passport's `/oauth/*` errors become `INTERNAL_ERROR 500`.

```php
// Handler: delegate first, classify second.
if (method_exists($e, 'render')) {
    return $e->render($request);
}
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
app/Console/Commands/Work.php:75   $res = '__delay__' is assignment, not comparison
app/Console/Commands/Work.php:49   (int) $x ?? 0 never triggers the default
/oauth/token                       returns INTERNAL_ERROR 500: Handler ignores $e->render()
PassportSeeder                     seeds layer@gmail.com with a known password (123Mudar!)
.env.example                       real APP_KEY committed; QUEUE_CONNECTION duplicated
CACHE_DRIVER=file                  with >1 replica the rate limit becomes limit × replicas
AUTH_PERSONAL_ACCESS_TOKEN_DAYS    dead config: no createToken() remains in the code
```
