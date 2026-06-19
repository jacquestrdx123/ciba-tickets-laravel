# CIBA Tickets API Reference

Base URL: `{APP_URL}` (from project `.env`)

## Authentication

**API clients (agents, curl, scripts)** — Bearer token:

```
Authorization: Bearer {API_TOKEN}
```

Set `API_TOKEN` in `.env`. No login or CSRF required for `/api/*`.

**Web UI** — session login at `/login` using `AUTH_PASSWORD` (browser dashboard).

Either auth method works on `/api/*` routes.

## Endpoints

### List tickets

```
GET /api/tickets
GET /api/tickets?uncategorized=1
```

With `uncategorized=1`, returns only tickets where `category_id` is null and includes `description` in each ticket (for agent categorization).

Response:
```json
{
  "tickets": [
    {
      "id": 1,
      "vendor_id": 156,
      "ticket_number": "T-12345",
      "subject": "...",
      "description": "...",
      "client_name": "...",
      "status": "open",
      "github_branches": [{"name": "feature/T-12345", "is_default": false, "sha": "abc"}],
      "last_comment_at": "2026-06-19 10:00:00",
      "synced_at": "2026-06-19T05:24:48.000000Z",
      "category": {"id": 5, "name": "CPD", "color": "#534AB7"},
      "created_at": "...",
      "updated_at": "..."
    }
  ]
}
```

Note: list endpoint does **not** include comments — use `GET /api/tickets/{vendor_id}` for comments.

### Get ticket with comments

```
GET /api/tickets/{vendor_id}
```

Response:
```json
{
  "id": 156,
  "vendor_id": 156,
  "ticket_number": "T-12345",
  "subject": "...",
  "status": "...",
  "client_name": "...",
  "description": "...",
  "github_branches": [],
  "last_comment_at": "...",
  "created_at": "...",
  "synced_at": "...",
  "comments": [
    {
      "id": 1,
      "vendor_id": 999,
      "author": "Jane",
      "author_name": "Jane",
      "body": "Comment text",
      "content": "Comment text",
      "comment_type": null,
      "created_at": "2026-06-18 14:30:00"
    }
  ]
}
```

### Sync tickets

```
POST /api/tickets/sync        → 202 { "queued": true, "status": "queued" }
GET  /api/tickets/sync/status → { "status": "completed|running|failed", "synced": 69, ... }
```

### GitHub branches (stored on ticket)

```
GET /api/github/branch?ticket_number=T-12345
POST /api/github/branches/sync
GET  /api/github/branches/sync/status
```

## Database schema (direct read fallback)

**tickets**: `vendor_id`, `ticket_number`, `subject`, `description`, `client_name`, `status`, `github_branches` (JSON), `last_comment_at`, `synced_at`

**comments**: `ticket_id` → tickets.id, `vendor_id`, `author_name`, `body`, `comment_type`, `commented_at`

Comments are ordered by `commented_at` ascending.

### Ticket categories

```
GET  /api/ticket-categories
PATCH /api/tickets/{vendor_id}/category
```

List categories:
```json
[
  {"id": 1, "name": "Bug / broken functionality", "color": "#185FA5"},
  {"id": 5, "name": "CPD", "color": "#534AB7"}
]
```

Assign category (agent writes classification result):
```
PATCH /api/tickets/156/category
Content-Type: application/json

{"category_id": 5}
```

Response: `{"category_id": 5}`

Returns **409** if the ticket already has a different category. Idempotent when assigning the same category. Pass `"category_id": null` to uncategorize.

### MCP categorization tools

| Tool | HTTP equivalent |
|------|-----------------|
| `list_ticket_categories` | `GET /api/ticket-categories` |
| `list_uncategorized_tickets` | `GET /api/tickets?uncategorized=1` |
| `assign_ticket_category` | `PATCH /api/tickets/{vendor_id}/category` |
