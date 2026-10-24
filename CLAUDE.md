# laravel-layer

Laravel 12 + Passport 13 starter with pragmatic hexagonal architecture (ports & adapters), `spatie/laravel-data` DTOs and Pest tests. Runs on Docker; the API answers at `http://localhost:81`.

## Mandatory skills

These are not optional. Invoke them BEFORE writing code, not after.

| Situation | Skill |
|---|---|
| Creating or changing anything under `modules/` or `app/` | `module-architecture` |
| Adding a module, endpoint, action, DTO, exception or binding | `module-architecture` |
| Writing, changing or deleting any test | `module-testing` |
| Claiming a change works | `module-testing` |

If the task touches application code, at least one applies. When in doubt, read both.

```
"add a /verify-email endpoint"   → module-architecture, then module-testing
"why is this error not {code}?"  → module-architecture (error handling)
"the logout test is flaky"       → module-testing (guard caching trap)
```

## Driver ports yes, driven ports no

A driver port (input) is the interface the outside drives the app through; a driven port (output) is the interface the domain owns to reach an external resource, implemented by an adapter. Every use case is an `Action` implementing a `Contract` (driver port), and callers depend on the contract. Persistence uses Eloquent, Passport and facades directly, and background work is deferred by raising a domain event that a queued listener handles — the action calls the injected `Illuminate\Contracts\Events\Dispatcher`, never a Job or a queue, so the rule announces a fact and stays ignorant of the mechanism. No repository, no driven port; do not propose one. `Dispatcher` is a framework contract resolved by DI, not a domain-owned output port.

```php
public function __construct(private readonly LoginContract $loginAction) {}   // depends on the port
class Login implements LoginContract { public function handle(LoginDTO $i): TokenPairDTO {} }
```

## Actions use handle()

A single-method object, injected and discarded. No `Services/` layer, no `exec()`.

```php
class Logout implements LogoutContract { public function handle(): void {} }
```

## DTOs validate and serialize

Input validates via `laravel-data` attributes (no FormRequest). Output is returned straight from the controller (no API Resource, no envelope).

```php
final class RegisterUserDTO extends Data {
    public function __construct(#[Required, StringType] public readonly string $name, /* ... */) {}
}
```

## Modules own everything they need

Each module in `modules/` is a vertical slice: its controllers (`Http/Controllers`), middleware (`Http/Middleware`), routes (`Routes/`), models (`Models/`), bindings, rate limiters and event wiring all live inside it, registered by its `Providers/*ServiceProvider` (`public array $bindings` + `boot()`). The glob discovers every `*ServiceProvider` there, so a module may have more than one. Adding a module never requires editing `app/`, which keeps only framework glue (kernels, exception handler, base controller, generic middleware, the global rate limit).

```php
class AuthModuleServiceProvider extends ServiceProvider {
    public array $bindings = [LoginContract::class => Login::class];
}
```

## The controller only translates HTTP

One method = one action call. No `Auth::user()`, `Modules\*\Models\*`, `Laravel\Passport\*`, `instanceof` or business logic in the controller.

```php
public function me(): UserDTO { return $this->getAuthenticatedUserAction->handle(); }
```

## Every error carries an opaque numeric code

Int enum per module, in ranges (Auth 1100-1199, general 1000-1099). `description()` names the real failure for logs; `publicMessage()` is what the client gets, kept generic so a raw response never spells the failure out (all auth failures answer `Authentication failed`). The number is public contract; a front maps it to its own UX copy.

```php
// { "code": 1101, "message": "Authentication failed" }   // 1101 = AuthErrorCode::InvalidCredentials
```

## Auth uses the password grant

Short access token + rotating single-use refresh token. TTLs in `config/tokens.php`, never hardcoded. Personal access tokens have no refresh token — do not go back to them.

```php
Passport::tokensExpireIn(CarbonInterval::minutes(config('tokens.access_token_minutes')));
```

## All generated code is in English

Every identifier, comment, log message and hardcoded string is English — even when the prompt or this conversation is in Portuguese. Translate the intent, never transliterate the request. Comments exist only to say what the code cannot; never narrate the code.

```php
class RegisterUser implements RegisterUserContract {}   // right
class CadastrarUsuario implements CadastrarUsuarioContract {}   // wrong, whatever the prompt's language
```

## Tests live in the module

`modules/<X>/Tests/{Unit,Feature}`, Pest, Given/When/Then, English, grouped by use case, not by layer.

```php
it('logs in through the route and responds 200 with the token pair', function () { /* ... */ });
```

## Verification is the house standard

Never claim something works without exercising the behavior.

```bash
docker compose exec -T app php artisan test
curl -s -i http://localhost:81/me -H 'Accept: application/json'
```

Three rules learned the hard way here: a green suite does not prove a test detects the bug (delete the guard, watch it fail, restore); `php -l` on a generator says nothing about the generated code; changed the HTTP contract → hit the running endpoint and read status, body and headers.

```bash
# prove a test detects: remove the guard line, then
docker compose exec -T app php artisan test --filter="the guard's test"   # must be ⨯
```

## Known issues (do not "fix" in passing without saying so)

```
(none outstanding)
```
