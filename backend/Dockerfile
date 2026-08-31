FROM php:8.2-cli

RUN docker-php-ext-install mysqli pdo_mysql

WORKDIR /app

COPY . .

RUN chmod +x start.sh

EXPOSE 8080

CMD ["sh", "start.sh"]