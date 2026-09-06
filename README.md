# Quanta

Quanta is a test management application for QA teams. It keeps the whole cycle in one place: write cases, cover requirements, plan a run, execute it, and report the result.

It was built as a fresh Laravel app for that workflow — not a compatibility layer over an older test-management product. The UI is an Inertia + React SPA. Automation talks to a versioned REST API.

## What you can do

- **Projects and members** — isolate work by product, then assign people with project-scoped roles
- **Specification** — suites, cases, steps, versions, freeze/unfreeze, copy/move, keywords, attachments
- **Requirements** — specs, versions, coverage links to cases, and watchers for changes
- **Planning** — plans, builds, milestones, platforms, and tester assignments
- **Execution** — run cases against a build, record step results, attach evidence, and link or create issues
- **Reports** — plan status, baselines, CSV and XLSX export
- **Trackers** — GitHub/GitLab-style code trackers for automation scripts, plus Jira for defects
- **Administration** — users, roles and abilities, custom fields, and an event log
- **API** — Sanctum personal access tokens for CI (`/api/v1`)

Public sign-up can be turned off with `AUTH_SELF_SIGNUP=false` so only administrators create accounts.

## Stack

| Layer | Choice |
| --- | --- |
| App | Laravel 13, PHP 8.3+ (developed on 8.5) |
| Auth | Fortify, optional passkeys, Sanctum tokens |
| UI | Inertia.js 3, React 19, Tailwind CSS 4 |
| Database | MySQL |
| Spreadsheets | PhpSpreadsheet |

## Local setup

You need PHP 8.3+, Composer, Node.js 22+, npm, and MySQL.

```bash
git clone https://github.com/dinesh-01/quant.git
cd quant
composer run setup
```

That copies `.env`, generates `APP_KEY`, installs PHP and JS dependencies, migrates, and builds the frontend.

Then point `.env` at your database and set `APP_URL` to the host you will use (for example `http://localhost:8000` or `http://localhost:8001`).

Load the demo dataset:

```bash
php artisan migrate --seed
```

On Laravel Cloud, deploy installs `--no-dev`. `fakerphp/faker` is a production dependency because the demo is built through factories. After a deploy that includes that change, seed with `php artisan db:seed --force` (do not use `migrate:fresh` on Cloud).

`DemoSeeder` is a no-op if a project with prefix `CO` already exists. To rebuild the demo from scratch locally:

```bash
php artisan migrate:fresh --seed
```

Run the app:

```bash
composer run dev
```

Or serve PHP and Vite separately (`php artisan serve` and `npm run dev`). If the UI looks stale after a frontend change, run `npm run build` or keep `npm run dev` running.

### Demo accounts

All seeded accounts use the password `password`.

| Email | Role |
| --- | --- |
| `test@example.com` | Admin |
| `maya.leader@example.com` | Leader |
| `sam.senior@example.com` | Senior Tester |
| `rina.tester@example.com` | Tester (assigned cases only) |
| `devon.designer@example.com` | Test Designer |
| `guest.viewer@example.com` | Guest |
| `inactive.user@example.com` | Tester, deactivated |

The walkable demo project is **Checkout** (`CO`).

## REST API

Create a token in **Settings → Tokens**, then call `/api/v1` with `Authorization: Bearer <token>`. The token inherits that user's roles and abilities — there is no second permission list.

The contract lives in [`openapi/v1.yaml`](openapi/v1.yaml). Lists are cursor-paginated (`meta.next_cursor`, `meta.has_more`). Typical uses: list projects and cases, create a case, post an execution from CI, upload an attachment.

## Housekeeping

A daily scheduler prunes the event log, stale execution drafts, and expired Sanctum tokens. Windows are `RETENTION_*` in `.env` (see `.env.example`). The event-log floor is 30 days. Completed runs are never pruned.

In production, run `php artisan schedule:run` from cron and a queue worker (`QUEUE_CONNECTION=database` by default).

## Tests

```bash
php artisan test --compact
```

PHPUnit feature tests cover the domain. `composer test` also runs Pint and PHPStan.

After changing routes that the frontend calls, regenerate Wayfinder with:

```bash
php artisan wayfinder:generate --with-form
```

Omitting `--with-form` breaks form helpers in the UI.

## Out of scope

These are deliberate non-goals, not missing tickets:

- Legacy XML import/export
- Inventory, requirement-management-system hosts, DocBook, and a plugin marketplace
- OAuth / LDAP / SSO (needs a later package decision)
- Cloud deploy, SMTP, and backups (parked until a host is chosen)

## License

MIT
