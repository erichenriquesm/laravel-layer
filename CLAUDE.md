# laravel-layer

Laravel 10 + Passport starter with pragmatic hexagonal architecture (ports & adapters), `spatie/laravel-data` DTOs and Pest tests. Runs on Docker; the API answers at `http://localhost:81`.

## Mandatory skills

These are not optional. Invoke them BEFORE writing code, not after.

| Situation | Skill |
|---|---|
| Creating or changing anything under `domain/` or `app/` | `domain-architecture` |
| Adding a domain, endpoint, action, DTO, exception or binding | `domain-architecture` |
| Writing, changing or deleting any test | `domain-testing` |
| Claiming a change works | `domain-testing` |

If the task touches application code, at least one applies. When in doubt, read both.

```
"add a /verify-email endpoint"   → domain-architecture, then domain-testing
"why does /oauth/token 500?"     → domain-architecture (known issues)
"the logout test is flaky"       → domain-testing (guard caching trap)
```

## Driver ports yes, driven ports no

A driver port (input) is the interface the outside drives the app through; a driven port (output) is the interface the domain owns to reach an external resource, implemented by an adapter. Every use case is an `Action` implementing a `Contract` (driver port), and callers depend on the contract. But actions use Eloquent, Passport and facades directly — no driven port, no repository, no persistence abstraction. This trades domain isolation for simple queries. Do not propose repositories; if one were ever added, its adapter lives inside the domain (e.g. `domain/<X>/Repositories/`).

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

## Bindings live in the domain

Each domain declares its bindings in its `Providers/` directory (`public array $bindings`); the glob discovers every `*ServiceProvider` there, so a domain may have more than one. Adding a domain never requires editing `app/`.

```php
class AuthDomainServiceProvider extends ServiceProvider {
    public array $bindings = [LoginContract::class => Login::class];
}
```

## The controller only translates HTTP

One method = one action call. No `Auth::user()`, `App\Models\*`, `Laravel\Passport\*`, `instanceof` or business logic in the controller.

```php
public function me(): UserDTO { return $this->getAuthenticatedUserAction->handle(); }
```

## Every error carries an opaque numeric code

Int enum per domain, in ranges (Auth 1100-1199, general 1000-1099). `description()` names the real failure for logs; `publicMessage()` is what the client gets, kept generic so a raw response never spells the failure out (all auth failures answer `Authentication failed`). The number is public contract; a front maps it to its own UX copy.

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

## Tests live in the domain

`domain/<X>/Tests/{Unit,Feature}`, Pest, Given/When/Then, English, grouped by use case, not by layer.

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
app/Console/Commands/Work.php:75   $res = '__delay__' is assignment, not comparison
app/Console/Commands/Work.php:49   (int) $x ?? 0 never triggers the default
/oauth/token                       returns INTERNAL_ERROR 500: Handler ignores $e->render()
PassportSeeder                     seeds layer@gmail.com with a known password (123Mudar!)
.env.example                       real APP_KEY committed; QUEUE_CONNECTION duplicated
CACHE_DRIVER=file                  with >1 replica the rate limit becomes limit × replicas
AUTH_PERSONAL_ACCESS_TOKEN_DAYS    dead config: no createToken() remains in the code
Domain\Shared\Helpers\Queue        the most complex file in the repo, has no test
```
