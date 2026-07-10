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
├── DTOs/                ← entrada e saída (LoginDTO, AccessTokenDTO, UserDTO…)
├── Exceptions/          ← exceções de domínio (InvalidCredentialsException)
├── Providers/           ← liga contract → action
└── Tests/{Unit,Feature}
```

O `AuthController` depende de `LoginContract`, nunca de `Login`. O adapter primário é o próprio controller: ele traduz HTTP em DTO, chama a porta e devolve um DTO.

```php
public function login(LoginDTO $input): AccessTokenDTO
{
    return $this->loginAction->handle($input);
}
```

O ganho concreto disso aparece no teste: dá para substituir a implementação sem tocar no controller.

```php
$this->mock(LoginContract::class, function (MockInterface $mock) {
    $mock->shouldReceive('handle')->once()->andReturn(new AccessTokenDTO('fake'));
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
| `POST /login` | `200` + `{token}` | `401` `{message}` / `422` |
| `GET /me` | `200` + `{id, name, email}` | `401` |

A validação vive nos atributos do DTO de entrada, não em form requests. O `Handler` força JSON em qualquer resposta de erro, já que a aplicação só expõe API.

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

