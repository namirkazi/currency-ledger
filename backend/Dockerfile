FROM php:8.2-cli

# Install required PHP extensions
RUN docker-php-ext-install \
    mysqli \
    pdo_mysql \
    bcmath

WORKDIR /app

COPY . .

RUN chmod +x start.sh

EXPOSE 8080

CMD ["sh", "start.sh"]