# Laravel Layer — Base para novos projetos

Projeto base Laravel com **Docker**, **autenticação (Passport)**, **domain layer** (services + DTOs) e **testes em Pest**. Use como ponto de partida para novos backends.

### O que já vem

- **Auth**: registro, login e rota `/me` com Laravel Passport
- **Estrutura**: services em `domain/` (ex.: `domain/Auth/Services`), DTOs, tipos de domínio (`domain/Shared`), controllers por contexto
- **Testes**: Pest em `tests/Feature/Auth` (controllers, services, DTOs)
- **Dev**: Docker Compose, Makefile e scripts `.sh` para setup idempotente (incl. `passport:keys --force`)

---

## 🚀 Como usar como base

```bash
# Clone o repositório
git clone <url-do-repo> meu-projeto && cd meu-projeto

# Copie o ambiente e ajuste se precisar (DB, etc.)
cp .env.example .env

# Sobe containers, instala deps, gera keys do Passport, migra e seed
make setup
```

Depois disso, a API estará no ar (rotas em `routes/auth.php`). Para rodar os testes: `docker-compose exec app php artisan test`.

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

