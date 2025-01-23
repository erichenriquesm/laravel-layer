# Use a imagem oficial do PHP com a última versão
FROM php:8.2.5-fpm

# Instale as dependências necessárias
RUN apt-get update && apt-get install -y \
    libpng-dev \
    libjpeg-dev \
    libfreetype6-dev \
    zip \
    unzip \
    && docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install gd pdo pdo_mysql


# Instale extensões PHP necessárias
RUN docker-php-ext-install pdo pdo_mysql mysqli

# Instale o inotify-tools
RUN apt-get update && apt-get install -y inotify-tools

# Instalar a extensão PhpRedis
RUN pecl install redis \
    && docker-php-ext-enable redis

# Exponha a porta 80 para ac

# Instale o Composer globalmente
RUN curl -sS https://getcomposer.org/installer | php -- --install-dir=/usr/local/bin --filename=composer

# Configure o diretório de trabalho no container
WORKDIR /var/www

# Copie o código do projeto para o container
COPY . /var/www

# Instale as dependências do Composer
RUN composer install

# Exponha a porta 8000 para acesso externo
EXPOSE 8000

# Comando para iniciar o servidor
CMD ["php", "-S", "0.0.0.0:8000", "-t", "public"]
