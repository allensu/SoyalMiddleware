FROM composer AS composer
WORKDIR /code
COPY . .
RUN composer install --ignore-platform-reqs --no-scripts

FROM nginx AS final
WORKDIR /code
COPY --from=composer /code .
