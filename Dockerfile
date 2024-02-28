FROM php:8.0-cli

RUN apt-get update && \
    apt-get install -y \
    sqlite3 \
    libsqlite3-dev \
    git \
    unzip \
    && docker-php-ext-install pdo pdo_sqlite sqlite3

RUN curl -sS https://getcomposer.org/installer | php -- --install-dir=/usr/local/bin --filename=composer

COPY . /teste-backend

WORKDIR /teste-backend

EXPOSE 8000

CMD ["php", "-S", "0.0.0.0:8000"]
