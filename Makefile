.PHONY: setup up down wait-db migrate seed

setup: up wait-db install-composer migrate seed
	@echo "✅ Setup concluído!"

restart: down up
	@echo "✅ Restart concluído!"

up:
	@echo "🚀 Subindo os containers..."
	@docker-compose up -d

down:
	@echo "🛑 Derrubando os containers..."
	@docker-compose down

wait-db:
	@echo "⏳ Aguardando o MySQL ficar pronto..."
	@until docker-compose exec db mysqladmin ping -h"localhost" --silent; do \
		sleep 2; \
	done

install-composer:
    @docker-compose exec app composer install

migrate:
	@echo "📌 Rodando as migrações..."
	@docker-compose exec app php artisan migrate --force

seed:
	@echo "📌 Configurando o Passport..."
	@docker-compose exec app php artisan db:seed --class=PassportSeeder
