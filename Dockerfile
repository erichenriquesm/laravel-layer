# Use a imagem oficial do PHP com a versão FPM
FROM php:8.2.5-fpm

# Instale as dependências necessárias
RUN apt-get update && apt-get install -y \
    libpng-dev \
    libjpeg-dev \
    libfreetype6-dev \
    zip \
    unzip \
    inotify-tools \
    librabbitmq-dev \
    && docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install gd pdo pdo_mysql mysqli \
    && docker-php-ext-install sockets

# Instalar a extensão PhpRedis e PHP AMQP
RUN pecl install redis amqp \
    && docker-php-ext-enable redis amqp

# Instale o Composer globalmente
RUN curl -sS https://getcomposer.org/installer | php -- --install-dir=/usr/local/bin --filename=composer


# Configure o diretório de trabalho no container
WORKDIR /var/www

# Copie o código do projeto para o container
COPY nginx/www.conf /usr/local/etc/php-fpm.d/www.conf
COPY . /var/www

# Instale as dependências do Composer
RUN composer install --no-dev --optimize-autoloader

# Exponha a porta usada pelo PHP-FPM
EXPOSE 9000

# Comando para iniciar o PHP-FPM
CMD ["php-fpm"]
