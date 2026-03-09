# Laravel Layer — Base para novos projetos

Projeto base Laravel com **Docker**, **autenticação (Passport)**, **domain layer** (services + DTOs) e **testes em Pest**. Use como ponto de partida para novos backends.

### O que já vem

- **Auth**: registro, login e rota `/me` com Laravel Passport
- **Estrutura**: services em `domain/` (ex.: `domain/Auth/Services`), DTOs, tipos de domínio (`domain/Shared`), controllers por contexto
- **Testes**: Pest em `tests/Feature/Auth` (controllers, services, DTOs)
- **Dev**: Docker Compose, Makefile e scripts `.sh` para setup idempotente (incl. `passport:keys --force`)
- **Filas**: containers **Redis** e **RabbitMQ**; comando `php artisan work {queue}` para processar filas; helper `Domain\Shared\Helpers\Queue` para publicar mensagens (classe + método + argumentos)

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

Depois disso, a API estará no ar (rotas em `routes/auth.php`). Para rodar os testes: `docker-compose exec app php artisan test`.

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

