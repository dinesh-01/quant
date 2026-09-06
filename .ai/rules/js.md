---
paths:
    - 'resources/js/**'
---

# Js

## Regenerate Wayfinder with --with-form

`php artisan wayfinder:generate` on its own silently deletes every `.form()` helper, because `vite.config.ts` configures the plugin with `formVariants: true` and the bare artisan command does not read that. The damage is app-wide, not limited to what you were working on: every existing page using `Controller.method.form()` stops type checking at once, which reads like you broke unrelated files.

Always run `php artisan wayfinder:generate --with-form`. Better still, let `npm run dev` or `npm run build` regenerate them, since the plugin passes the flag itself.

If `npm run types:check` suddenly reports `Property 'form' does not exist` across `pages/auth/**` and `pages/settings/**`, this is the cause. Regenerating with the flag fixes all of it.

## Inputs default to autocomplete off

`components/ui/input` defaults to `autoComplete="off"`. Auth and settings pages that want the browser to fill a field must pass an explicit token (`email`, `current-password`, `name`). Do not rely on the field `name` alone — Chrome treats `name="name"` as a contact field and offers values saved from other sites.
