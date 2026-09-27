---
paths:
  - 'database/seeders/**'
---

# Seeders

## Demo data lives in DemoSeeder
DatabaseSeeder calls RoleSeeder, firstOrCreates test@example.com as Admin, then DemoSeeder. DemoSeeder is a no-op if a project with prefix CO already exists. All demo accounts use password `password`. Do not put real tracker tokens in the seeder. Re-run with `php artisan migrate:fresh --seed` to rebuild the graph; `db:seed` alone will not duplicate it.

DemoSeeder builds that graph through Eloquent factories. Cloud (and any `--no-dev` install) omits require-dev, so `fakerphp/faker` stays in `require`. Do not move it back to require-dev or the Cloud seed dies on `$this->faker->unique()` / `fake()`.

## Checkout demo volume is 482 cases
VolumeSeeder::TARGET_CASES is 482 because that is what the mockups show. OverviewMirrorSeeder calls VolumeSeeder, then shapes Checkout to the Overview mockup exactly: it trims surplus cases, rebuilds the four active plans with fixed plan numbers (12/14/15/16), and writes the named Needs attention runs and activity records. It rewrites demo rows, so it only ever touches the CO project. Neither seeder belongs in DatabaseSeeder — run them by hand with `db:seed --class=`.

## OverviewMirrorSeeder also shapes Test Suites
OverviewMirrorSeeder::shapePromotions() writes the Promotions suite the Test Suites mockup shows: 46 cases, 5 frozen (CO-TC-243 plus four unnamed), featured rows 240-246 with their names/keywords/versions, and CO-TC-240's three SAVE20 steps, Devon as author, promo_percent_spec.ts, a Passed last run, and updated_at two days ago. VolumeSeeder freezes every tenth case, so Promotions must be reopened first or Frozen reads 8. Change the seeder, never the page, when a number or name is wrong. Still run by hand with db:seed --class=; do not add it to DatabaseSeeder.
