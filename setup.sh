#!/bin/bash

echo "🚀 Subindo os containers..."
docker-compose up -d

echo "⏳ Aguardando o MySQL ficar pronto..."
until docker-compose exec db mysqladmin ping -h"localhost" --silent; do
    sleep 2
done

echo "📌 Rodando as migrações..."
docker-compose exec app php artisan migrate --force

echo "📌 Configurando o Passport..."
docker-compose exec app php artisan db:seed --class=PassportSeeder

echo "✅ Setup concluído!"
