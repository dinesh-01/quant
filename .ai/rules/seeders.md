---
paths:
  - 'database/seeders/**'
---

# Seeders

## Demo data lives in DemoSeeder
DatabaseSeeder calls RoleSeeder, firstOrCreates test@example.com as Admin, then DemoSeeder. DemoSeeder is a no-op if a project with prefix CO already exists. All demo accounts use password `password`. Do not put real tracker tokens in the seeder. Re-run with `php artisan migrate:fresh --seed` to rebuild the graph; `db:seed` alone will not duplicate it.

DemoSeeder builds that graph through Eloquent factories. Cloud (and any `--no-dev` install) omits require-dev, so `fakerphp/faker` stays in `require`. Do not move it back to require-dev or the Cloud seed dies on `$this->faker->unique()` / `fake()`.
