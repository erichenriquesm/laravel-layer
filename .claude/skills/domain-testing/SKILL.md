---
name: domain-testing
description: Testing rules for laravel-layer — Pest in Given/When/Then, per-domain Tests/{Unit,Feature}, the port() helper, and the requirement to prove by mutation that each test detects the bug it claims to. ALWAYS read before writing, changing or deleting any test, and before claiming a change works.
---

# laravel-layer testing

## Tests live inside the domain

Not in `tests/`. `Unit` never touches the DB or HTTP; `Feature` does. `tests/` holds only infrastructure (`Pest.php`, `TestCase.php`, `CreatesApplication.php`), and `phpunit.xml` finds tests by glob.

```
domain/<Domain>/Tests/Unit/      DTOs, enums
domain/<Domain>/Tests/Feature/   actions, routes
```

## Group by use case, not by layer

`LoginTest.php` (Feature) holds both the action test and the route test. There is no `Controllers/`, `Actions/` or `DTOs/` test folder. Actions go in `Feature` because they use Eloquent directly and need a DB.

```
domain/Auth/Tests/Feature/LoginTest.php     # action + route, together
domain/Auth/Tests/Feature/LogoutTest.php
domain/Auth/Tests/Unit/LoginDTOTest.php
```

## Shape: Pest, English, explicit Given/When/Then

Name and comments in English, fixtures in English. The "When" isolates the action; for exceptions use `$act = fn () => ...` with `expect()->toThrow()`, not try/catch.

```php
it('throws InvalidCredentialsException when the password is wrong', function () {
    // Given
    User::create(['name' => 'Jane Doe', 'email' => 'jane@example.com', 'password' => Hash::make('secret123')]);
    $dto = new LoginDTO(email: 'jane@example.com', password: 'wrong-password');

    // When
    $act = fn () => port(LoginContract::class)->handle($dto);

    // Then
    expect($act)->toThrow(InvalidCredentialsException::class);
});
```

## Resolve contracts with port(), never app()

`app()` is typed as `Application`, which implements `HttpKernelInterface` and already has a `handle(Symfony\Request)`, so Intelephense matches that and reports P1006. `port()` (in `tests/Pest.php`) preserves the type via `@template`.

```php
port(LoginContract::class)->handle($dto);   // right
app(LoginContract::class)->handle($dto);    // wrong — P1006
```

## Swap the implementation under the contract

This is what driver ports exist for. Swap before the request; each test rebuilds the app, so it never leaks.

```php
$this->mock(LoginContract::class, function (MockInterface $mock) {
    $mock->shouldReceive('handle')->once()->andReturn(new TokenPairDTO('a', 'r', 900, 'Bearer'));
});
$this->postJson('/login', [...])->assertStatus(200);
```

## The rule that matters most: prove the test detects

A passing test proves nothing. Before claiming a guarantee is covered, delete the line that implements it and confirm the matching test fails — and only it. Then restore. This has caught empty tests here more than once.

```bash
cp app/Providers/RouteServiceProvider.php /tmp/good
# remove the guard, then:
docker compose exec -T app php artisan test --filter="the guard's test"   # must show ⨯
cp /tmp/good app/Providers/RouteServiceProvider.php
docker compose exec -T app php artisan test                                # green again
```

## Changed the HTTP contract? Hit the real API

A green suite does not prove the wire contract. Curl the running endpoint and read status, body and headers.

```bash
curl -s -i -X POST http://localhost:81/login -H 'Accept: application/json' \
  -H 'Content-Type: application/json' -d '{"email":"...","password":"..."}'
# check status, body, and Retry-After / X-RateLimit-* headers
```

## Integration tests skip when their service is down, and must not hang

An integration test against a real service (RabbitMqMessageQueueTest → the broker) tries to connect in `beforeEach` and `markTestSkipped` when it can't — so a CI without the service reports skipped, not failed. And it must never block forever: a blocking consume takes a `waitTimeout`, a `basic_get` polls with a deadline. A broken publish then fails in seconds, it does not hang the suite. (Learned the hard way — a mutation with no timeout hung the run.)

```php
try {
    $this->connection = new AMQPStreamConnection($host, $port, $user, $pass, read_write_timeout: 5);
} catch (\Throwable $e) {
    $this->markTestSkipped('RabbitMQ is not reachable');
}
$adapter->consume($queue, $handler, waitTimeout: 5);   // bounded, never blocks forever
```

## Trap: the guard caches the resolved user

A test reuses one application across requests; production gets a fresh process per request. After revoking a token, call `forgetGuards()` before re-checking `/me`. Confirm in the DB before blaming the action.

```php
$this->withHeader('Authorization', 'Bearer '.$token)->postJson('/logout')->assertStatus(204);
app('auth')->forgetGuards();
$this->withHeader('Authorization', 'Bearer '.$token)->getJson('/me')->assertStatus(401);
```

## Trap: shared DB state and rate limits

`TestCase` opens a transaction and rolls it back in `tearDown` — if a test only passes alone, suspect a fixture leak and run the suite twice. Use deterministic emails and trust the rollback. Rate limits count during tests, so give each test its own IP bucket.

```php
$this->withServerVariables(['REMOTE_ADDR' => '10.0.0.1']);   // own throttle bucket
// deterministic email + rollback, not faker->unique()
```

## Trap: actingAs() issues no Passport token

`actingAs()` authenticates without a bearer token, so `token()` is not a `Token`. Endpoints that revoke tokens must handle this — the test asserts 401, not a crash.

```php
$this->actingAs($user);                       // no real access token
$this->postJson('/logout')->assertStatus(401)->assertJson(['code' => 1100]);
```

## Never let a test lie

If behavior changes, rewrite the assertion or delete the test — never tweak it to stay green while asserting something false. If a gap is accepted on purpose, write a test that documents it with a comment.

```php
it('does not throttle a single account when the attacker rotates ip addresses', function () {
    // accepted gap: credential stuffing from a proxy pool is not stopped by the IP-only key
    // ...
});
```

## Commands

```bash
docker compose exec -T app php artisan test                     # all
docker compose exec -T app php artisan test --testsuite=Unit    # fast, no DB or HTTP
docker compose exec -T app php artisan test --filter=LoginTest
docker compose exec -T app php artisan test && docker compose exec -T app php artisan test   # idempotency
```
