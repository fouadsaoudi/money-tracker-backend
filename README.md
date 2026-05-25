# Money Tracker Backend

Laravel API backend for the Money Tracker project.

## Stack
- Laravel 13
- PHP 8.4
- MySQL 8.4
- Docker + Docker Compose

## Prerequisites
- Docker Engine
- Docker Compose v2 (`docker compose`)

## Quick Start (Docker)
1. Clone the repository.
2. Copy environment file:
   ```bash
   cp .env.example .env
   ```
3. Ensure these values exist in `.env`:
   ```env
   APP_URL=http://localhost:8080
   DB_CONNECTION=mysql
   DB_HOST=db
   DB_PORT=3306
   DB_DATABASE=money-tracker
   DB_USERNAME=root
   DB_PASSWORD=@l8i1tUre
   ```
4. Build and start containers:
   ```bash
   docker compose up -d --build
   ```
5. Generate app key and run migrations (inside API container):
   ```bash
   docker compose exec api php artisan key:generate --force
   docker compose exec api php artisan migrate
   ```

API will be available at:
- `http://localhost:8080`

## Services
- `api`
  - Laravel app
  - Port mapping: `8080:8080`
- `db`
  - MySQL 8.4
  - Internal Docker port: `3306`
  - Host port: `3307`

## Common Commands
- Open shell in API container:
  ```bash
  docker compose exec api bash
  ```
- Run artisan command:
  ```bash
  docker compose exec api php artisan <command>
  ```
- View logs:
  ```bash
  docker compose logs -f api
  docker compose logs -f db
  ```
- Stop containers:
  ```bash
  docker compose down
  ```
- Stop and remove volumes (fresh reset):
  ```bash
  docker compose down -v
  ```

## Database Notes
- From **inside container** (`docker compose exec api bash`):
  - Use `DB_HOST=db`, `DB_PORT=3306`
- From **host machine** (running `php artisan` directly without Docker):
  - Use `DB_HOST=127.0.0.1`, `DB_PORT=3307`

Mixing these contexts causes connection errors.

## First-Time Troubleshooting
- `SQLSTATE[HY000] [2002] getaddrinfo for db failed`
  - You are likely running artisan on the host with `DB_HOST=db`.
- `SQLSTATE[HY000] [2002] Connection refused`
  - Check that MySQL container is running:
    ```bash
    docker compose ps
    ```
- `vendor/autoload.php` missing
  - Install dependencies in container:
    ```bash
    docker compose exec api composer install
    ```

## Development Workflow
- Source code is bind-mounted (`./:/var/www/html`) so changes reflect immediately.
- `vendor` is stored in a named Docker volume for dependency stability.

## License
MIT
