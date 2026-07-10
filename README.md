# Laravel Layer — Base para novos projetos

Projeto base Laravel com **Docker**, **autenticação (Passport)**, **arquitetura hexagonal pragmática** (ports & adapters) e **testes em Pest**. Use como ponto de partida para novos backends.

### O que já vem

- **Auth**: registro, login e rota `/me` com Laravel Passport
- **Estrutura**: um diretório por domínio em `domain/`, com actions, contracts (ports), DTOs, provider e testes próprios
- **DTOs**: `spatie/laravel-data` fazendo validação de entrada (no lugar de form requests) e serialização de saída (no lugar de resources)
- **Testes**: Pest em `domain/<Domínio>/Tests/{Unit,Feature}`, escritos em Given/When/Then
- **Dev**: Docker Compose, Makefile e scripts `.sh` para setup idempotente (incl. `passport:keys --force`)
- **Filas**: containers **Redis** e **RabbitMQ**; comando `php artisan work {queue}` para processar filas; helper `Domain\Shared\Helpers\Queue` para publicar mensagens (classe + método + argumentos)

---

## 🧩 Arquitetura: ports & adapters, de forma pragmática

A ideia central da arquitetura hexagonal é que a regra de negócio não conheça o mundo externo. Quem chama de fora entra por um **driver port** (porta de entrada); quem é chamado de fora sai por um **driven port** (porta de saída). Aqui essa ideia é aplicada **até onde ela paga o próprio custo** — e o README é explícito sobre onde ela para.

### O que existe: driver ports

Cada caso de uso é uma **action** que implementa um **contract**. O contract é o driver port: é ele, e não a classe concreta, que o mundo externo enxerga.

```
domain/Auth/
├── Contracts/           ← driver ports (LoginContract, RegisterUserContract)
├── Actions/             ← implementações (Login, RegisterUser)
├── DTOs/                ← entrada e saída (LoginDTO, TokenPairDTO, UserDTO…)
├── Exceptions/          ← exceções de domínio (InvalidCredentialsException)
├── Providers/           ← liga contract → action
└── Tests/{Unit,Feature}
```

O `AuthController` depende de `LoginContract`, nunca de `Login`. O adapter primário é o próprio controller: ele traduz HTTP em DTO, chama a porta e devolve um DTO.

```php
public function login(LoginDTO $input): TokenPairDTO
{
    return $this->loginAction->handle($input);
}
```

O ganho concreto disso aparece no teste: dá para substituir a implementação sem tocar no controller.

```php
$this->mock(LoginContract::class, function (MockInterface $mock) {
    $mock->shouldReceive('handle')->once()->andReturn(new TokenPairDTO('access', 'refresh', 900, 'Bearer'));
});
```

### O que não existe: driven ports

**As actions usam Eloquent, `Auth::attempt()` e o Passport diretamente.** Não há `UserRepositoryContract`, nem repositórios, nem abstração de persistência.

Isso é uma escolha, não um esquecimento. O preço de um driven port de banco em CRUD é alto — some a expressividade do query builder, aparecem DTOs de credencial, hasher e emissor de token — e o retorno (trocar o banco, testar sem ele) raramente se realiza. O trade-off aceito é: **queries simples, domínio que precisa de banco para ser testado.** Por isso os testes de action ficam em `Tests/Feature`, não em `Tests/Unit`.

Se um dia a persistência precisar ser abstraída, o caminho é criar os contracts de saída em `domain/<Domínio>/Contracts/` e os adapters fora de `domain/`.

### Bindings: um provider por domínio

Não existe arquivo central de bindings. Cada domínio declara os seus no próprio provider, usando o array `$bindings` nativo do Laravel:

```php
class AuthDomainServiceProvider extends ServiceProvider
{
    public array $bindings = [
        LoginContract::class        => Login::class,
        RegisterUserContract::class => RegisterUser::class,
    ];
}
```

O `App\Providers\DomainServiceProvider` descobre esses providers por convenção (`domain/*/Providers/*ServiceProvider.php`) e os registra. **Adicionar um domínio não exige editar nada dentro de `app/`.**

### Criando um novo domínio

Copie `domain/Auth` como molde:

```
domain/Billing/
├── Contracts/BillingContract.php               ← interface com handle(BillingDTO): SomeDTO
├── Actions/Billing.php                         ← implements BillingContract
├── DTOs/BillingDTO.php                         ← extends Spatie\LaravelData\Data
├── Providers/BillingDomainServiceProvider.php  ← public array $bindings = [...]
└── Tests/{Unit,Feature}/
```

Rode `composer dump-autoload` e pronto — o provider é descoberto sozinho.

### Contrato HTTP

As respostas são os próprios DTOs de saída, sem envelope. Os erros seguem o formato padrão do Laravel.

| Rota | Sucesso | Erro |
|---|---|---|
| `POST /register` | `201` + `{id, name, email}` | `422` `{message, errors}` |
| `POST /login` | `200` + `{access_token, refresh_token, expires_in, token_type}` | `401` `{message}` / `422` |
| `POST /refresh` | `200` + mesmo par, rotacionado | `401` `{message}` / `422` |
| `POST /logout` | `204` | `401` |
| `GET /me` | `200` + `{id, name, email}` | `401` |

A validação vive nos atributos do DTO de entrada, não em form requests. O `Handler` força JSON em qualquer resposta de erro, já que a aplicação só expõe API.

---

## 🔑 Tokens: expiração, rotação e logout

O `/login` usa o **password grant** do Passport (habilitado em `AuthServiceProvider`, já que o Passport 11+ o desliga por padrão). O `client_id` e o `client_secret` do password grant client ficam no servidor — o cliente da API nunca os vê.

Há dois tokens, com propósitos diferentes. O **access token** é curto e viaja em toda requisição. O **refresh token** é longo, fica guardado e só é usado para obter um access token novo. A ideia é que o token exposto o tempo todo valha pouco tempo.

### Como o front deve buscar um token novo

1. `POST /login` devolve `access_token`, `refresh_token`, `expires_in` (segundos) e `token_type`.
2. O cliente guarda os dois e envia `Authorization: Bearer <access_token>` em cada requisição.
3. Quando o access token expira, a API responde **`401`**.
4. Ao ver esse `401`, o cliente chama `POST /refresh` com `{"refresh_token": "..."}` no corpo e recebe **um par novo**, com um novo refresh token junto.
5. O cliente **substitui os dois tokens** e repete a requisição original.
6. Se o `/refresh` também responder `401`, não há como recuperar: o refresh token expirou, foi revogado ou já foi usado. O cliente descarta tudo e manda o usuário para a tela de login.

Quatro detalhes que costumam morder quem implementa o cliente:

**O refresh token é de uso único.** A rotação revoga o antigo. Se você guardar só o access token novo e continuar mandando o refresh token velho, a próxima chamada ao `/refresh` devolve `401` mesmo dentro do prazo de 14 dias.

**A rotação também mata o access token antigo, na hora.** Ele não fica valendo até o `expires_in` acabar: o `/refresh` o revoga junto. Requisições em voo que ainda carregam o token velho vão tomar `401`. É outra razão para serializar o refresh, e para trocar os dois tokens de uma vez.

**Não dispare vários `/refresh` em paralelo.** Se cinco requisições tomarem `401` ao mesmo tempo e cada uma chamar `/refresh`, a primeira rotaciona e invalida o token que as outras quatro estão usando — elas tomam `401` e derrubam a sessão. O cliente precisa serializar: o primeiro `401` dispara o refresh, os demais esperam o resultado dele.

**`/refresh` não usa `Authorization`.** Ele é a saída de emergência para quando o access token não vale mais. Mandar o access token expirado no header não atrapalha, mas não serve para nada.

O `POST /logout` (esse sim autenticado) revoga o access token **e** o refresh token daquela sessão. As outras sessões do mesmo usuário continuam válidas.

### Onde o cliente guarda o refresh token

Ele vale 14 dias e permite emitir access tokens novos, então é o segredo mais valioso que o cliente carrega.

Em **app nativo ou mobile**, use o armazenamento seguro da plataforma (Keychain, Keystore).

Em **SPA no navegador**, saiba o que está aceitando: um refresh token em `localStorage` é legível por qualquer XSS na sua página, e quem o roubar renova o acesso por duas semanas. Se esse risco não for aceitável, o caminho é servir o refresh token num cookie `httpOnly; Secure; SameSite` e ler dele no `/refresh` — o que exige proteção CSRF e sai do padrão da RFC. Este projeto usa o corpo da requisição, como manda a [RFC 6749 §6](https://datatracker.ietf.org/doc/html/rfc6749#section-6).

### Ajustando os tempos de expiração

Os tempos vivem em [`config/tokens.php`](config/tokens.php) e são lidos do `.env`. Não há valor hardcoded no código.

```
AUTH_ACCESS_TOKEN_MINUTES=15   # vida do access token
AUTH_REFRESH_TOKEN_DAYS=14     # por quanto tempo o usuário fica logado sem digitar a senha
AUTH_PERSONAL_ACCESS_TOKEN_DAYS=1
```

Depois de mudar, rode `php artisan config:clear` (ou reinicie o container). Os valores são aplicados em `App\Providers\AuthServiceProvider`, e **tokens já emitidos mantêm a validade que tinham** — a mudança só afeta os próximos.

Ao escolher os números, o trade-off é este: encurtar o access token reduz a janela em que um token vazado é útil, ao custo de mais chamadas ao `/refresh`. Encurtar o refresh token força o usuário a digitar a senha com mais frequência.

Sem essa configuração o Passport expira **tudo em um ano** — foi o default herdado até este projeto passar a definir os três valores. Um token vazado seria utilizável por 12 meses.

### Rate limit dos endpoints de autenticação

| Rota | Limite |
|---|---|
| `POST /login` | 5/min por IP **e** 10/min por email |
| `POST /register` | 3/min por IP |
| `POST /refresh` | 10/min por IP |
| Todas as rotas | 30/min por usuário ou IP |

---

## 🚀 Como usar como base

```bash
# Clone o repositório
git clone https://github.com/erichenriquesm/laravel-layer.git meu-projeto && cd meu-projeto

# Copie o ambiente e ajuste se precisar (DB, etc.)
cp .env.example .env

# Sobe containers, instala deps, gera keys do Passport, migra e seed
make setup
```

Depois disso, a API estará no ar (rotas em `routes/auth.php`).

```bash
# Todos os testes
docker compose exec app php artisan test

# Só os testes rápidos (DTOs e value objects): sem query nem request HTTP
docker compose exec app php artisan test --testsuite=Unit
```

---

## 📬 Filas: Redis + RabbitMQ

O ambiente já inclui os containers **Redis** (porta 6379) e **RabbitMQ** (AMQP 5672, management 15672). As filas são processadas via RabbitMQ usando o helper `Domain\Shared\Helpers\Queue` e o comando `work`.

### Publicar mensagens

Use `Queue::publish()` passando o nome da fila, a classe que será executada no consumer, o método e os argumentos:

```php
use Domain\Shared\Helpers\Queue;

// Publica na fila "emails" para executar MailService::send($userId, $templateId)
Queue::publish('emails', \App\Services\MailService::class, 'send', $userId, $templateId);

// Exemplo: processar pedido em background (o consumer chama ProcessOrder::run($orderId))
Queue::publish('orders', \App\Services\ProcessOrder::class, 'run', $orderId);
```

A mensagem será consumida pelo comando `work`, que deserializa e chama `$class::$method(...$args)` (ou instancia a classe e chama o método, se não for estático).

### Processar filas

Dentro do container (ou no host com PHP/Redis/RabbitMQ configurados):

```bash
# Consumir a fila "emails" (roda até encerrar)
php artisan work emails

# Opções úteis: prefetch, delay, requeue em erro, etc.
php artisan work emails --prefetch=5 --delay=10 --onerror=requeue
```

### Gerenciadores de processos (PM2, systemd, etc.)

Para manter o worker rodando em produção, use um gerenciador de processos. Exemplo com **PM2**:

```bash
# Instalar PM2: npm i -g pm2

# Processar a fila "emails" com 2 instâncias e reinício automático
pm2 start "php artisan work emails" --name worker-emails -i 2

# Ou com caminho absoluto ao PHP/projeto
pm2 start "docker-compose exec -T app php artisan work emails" --name worker-emails
```

Com **systemd**, crie um unit que execute `php artisan work <queue>` e use `Restart=always`. Em todos os casos, o worker consome mensagens da fila RabbitMQ configurada no `.env` (`RABBITMQ_HOST`, `RABBITMQ_PORT`, etc.).

---

# 🛠️ Setup do Projeto com Docker

Este projeto utiliza **Docker** para configurar e gerenciar o ambiente de desenvolvimento.  
Você pode rodá-lo tanto em **Linux/macOS** (usando `make`) quanto no **Windows** (executando os arquivos `.sh`).  

---

## 📌 Comandos para Linux/macOS (`Makefile`)

Para rodar os comandos no **Linux/macOS**, basta executar:

```bash
make <comando>
```

### 🎯 **Comandos disponíveis**
| Comando          | Descrição |
|-----------------|-----------|
| `make up`       | Sobe os containers Docker |
| `make down`     | Para e remove os containers |
| `make restart`  | Reinicia os containers |
| `make setup`    | Realiza o setup completo (sobe os containers, roda as migrações e seeds) |

---

## 📌 Comandos para Windows (`.sh` scripts)

No **Windows**, use os scripts `.sh` para executar os mesmos comandos.  
Certifique-se de ter **Git Bash** ou **WSL** instalado para rodar os scripts.

### 🎯 **Scripts disponíveis**
| Script         | Descrição |
|---------------|-----------|
| `up.sh`       | Sobe os containers Docker |
| `down.sh`     | Para e remove os containers |
| `restart.sh`  | Reinicia os containers |
| `setup.sh`    | Realiza o setup completo (sobe os containers, roda as migrações e seeds) |

---

## 🚀 **Como rodar no Windows?**
Abra o terminal **Git Bash** ou **WSL** e execute:

```bash
./setup.sh
```

Isso iniciará o projeto automaticamente.

Se precisar apenas subir os containers:

```bash
./up.sh
```

Para derrubar:

```bash
./down.sh
```

---

## 🏠️ **Configuração do Docker**
Caso esteja rodando pela primeira vez, copie o exemplo e ajuste as variáveis: `cp .env.example .env`.  
Variáveis essenciais (exemplo):

```
DB_DATABASE=nome_do_banco
DB_USERNAME=layer
DB_PASSWORD=layer
```

Agora é só rodar o `setup` e começar a desenvolver! 🚀🔥

