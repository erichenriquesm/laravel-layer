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
Caso esteja rodando pela primeira vez, lembre-se de configurar as variáveis no `.env` antes de rodar os comandos.  
Exemplo de variáveis essenciais:

```
DB_DATABASE=nome_do_banco
DB_USERNAME=root
DB_PASSWORD=root
```

Agora é só rodar o `setup` e começar a desenvolver! 🚀🔥

