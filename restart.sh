echo "🛑 Derrubando os containers..."
docker-compose down

echo "🚀 Subindo os containers..."
docker-compose up -d
