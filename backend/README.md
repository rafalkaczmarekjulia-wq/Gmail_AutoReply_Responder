# Backend setup

```bash
composer install
cp .env.example .env
php artisan key:generate
php artisan migrate
php artisan serve
```

Queue worker:

```bash
php artisan queue:work redis --queue=webhooks,gmail-sync,ai
```

Watch renewal (also scheduled hourly):

```bash
php artisan gmail:renew-watches
```
