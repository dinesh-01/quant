---
paths:
  - 'app/Http/Controllers/Api/**, app/Http/Resources/Api/**, app/Http/Requests/Api/**, routes/api.php, openapi/**'
---

# Api

## Fresh Sanctum API, not a TestLink shim
Public automation is `/api/v1` (bootstrap `apiPrefix`) with Sanctum personal access tokens. Tokens inherit the user's Role/Ability gates — do not add a second ability list on the token. There is no XML-RPC, `/lib/api/rest/v3`, or `devKey` compatibility layer.

List endpoints use `cursorPaginate` ordered by `id`, cap `limit` at 100 (default 50), and return `meta.next_cursor` plus `meta.has_more`. Do not add offset `page=`.

Authorize in the existing domain actions. Uploads stay one route per parent type, same as the web attachments. Write the committed `openapi/v1.yaml` when routes change; do not add Scramble or l5-swagger without asking.
