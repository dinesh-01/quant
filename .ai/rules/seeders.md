---
paths:
  - 'database/seeders/**'
---

# Seeders

## Demo data lives in DemoSeeder
DatabaseSeeder calls RoleSeeder, firstOrCreates test@example.com as Admin, then DemoSeeder. DemoSeeder is a no-op if a project with prefix CO already exists. All demo accounts use password `password`. Do not put real tracker tokens in the seeder. Re-run with `php artisan migrate:fresh --seed` to rebuild the graph; `db:seed` alone will not duplicate it.
