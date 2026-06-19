---
name: ciba-tickets
description: >
  Query and analyse CIBA support tickets with their full comment threads.
  Use when the user asks about tickets, ticket numbers, client issues,
  support queue status, ticket comments, ticket descriptions, GitHub branches
  linked to tickets, ticket categorization, assigning categories to tickets,
  or needs to search/summarise/review support cases — even casual requests
  like "show me ticket 12345", "what did the client say on ticket X",
  "list open tickets for Acme", or "categorize uncategorized tickets".
  Prefer the bundled MCP tools when available; otherwise use the fetch script
  or HTTP API directly.
---

# CIBA Tickets Skill

Fetch locally-synced CIBA support tickets (with comments) from the
**ciba-tickets-laravel** app. Data is stored in the app database after a
vendor sync — always use local endpoints, not the vendor API directly.

---

## Step 1 — Locate the skill directory

This skill lives in the project at:

```
skills/ciba-tickets/
```

Set `SKILL_DIR` to that path (relative to the repo root).

| Asset | Path |
|-------|------|
| MCP server | `skills/ciba-tickets/mcp/server.py` |
| CLI fetch script | `skills/ciba-tickets/scripts/fetch_tickets.py` |
| API reference | `skills/ciba-tickets/reference.md` |

---

## Step 2 — Choose access method

| Method | When to use |
|--------|-------------|
| **MCP tools** (preferred) | MCP server is connected — use `list_tickets`, `get_ticket`, `search_tickets` |
| **CLI script** | Batch export, offline analysis, or MCP unavailable |
| **HTTP API** | One-off curl when script/MCP not set up |

### MCP tools (if connected)

| Tool | Purpose |
|------|---------|
| `list_tickets` | Paginated list; optional `status`, `client_name`, `limit` |
| `get_ticket` | Full ticket + comments by `vendor_id` or `ticket_number` |
| `search_tickets` | Search subject, client, ticket number, description, comment bodies |
| `sync_tickets` | Queue a vendor sync and poll until complete |
| `list_ticket_categories` | All valid categories (`id`, `name`, `color`) |
| `list_uncategorized_tickets` | Tickets without a category; includes `description` |
| `assign_ticket_category` | Set category on one ticket (`vendor_id` + `category_id`) |

### CLI script

```bash
python "$SKILL_DIR/scripts/fetch_tickets.py" \
  --project-root "/path/to/ciba-tickets-laravel" \
  [--ticket-number TICKET-123] \
  [--vendor-id 156] \
  [--status open] \
  [--search "invoice"] \
  [--out /tmp/tickets.json]
```

Omit filters to export all tickets with comments.

---

## Step 3 — Configuration

The MCP server and scripts read the project `.env` when given `--project-root`.

| Variable | Purpose |
|----------|---------|
| `APP_URL` | Base URL (e.g. `http://ciba-tickets-laravel.test`) |
| `API_TOKEN` | Bearer token for `/api/*` (preferred for agents and scripts) |
| `AUTH_PASSWORD` | Web UI login password (session auth) |
| `DB_CONNECTION` + sqlite path | Used for direct DB reads when HTTP fails |

Environment overrides (optional):
- `CIBA_TICKETS_BASE_URL`
- `CIBA_TICKETS_API_TOKEN`
- `CIBA_TICKETS_PASSWORD`
- `CIBA_TICKETS_DB_PATH` — absolute path to `database.sqlite`

---

## Step 4 — Enable MCP (one-time setup)

The project root already includes `.mcp.json`:

```json
{
  "ciba-tickets": {
    "command": "skills/ciba-tickets/.venv/bin/python",
    "args": [
      "skills/ciba-tickets/mcp/server.py",
      "--project-root",
      "."
    ]
  }
}
```

Install dependencies once (if `.venv` is missing):

```bash
python3 -m venv skills/ciba-tickets/.venv
skills/ciba-tickets/.venv/bin/pip install -r skills/ciba-tickets/requirements.txt
```

Restart Claude Code after adding the MCP server.

---

## Step 5 — Working with ticket data

### Ticket fields

| Field | Notes |
|-------|-------|
| `vendor_id` | Primary key for API lookups (`GET /api/tickets/{vendor_id}`) |
| `ticket_number` | Human-readable ID shown in UI |
| `subject`, `description`, `status`, `client_name` | Core metadata |
| `github_branches` | `[{name, is_default, sha}]` — synced from GitHub |
| `last_comment_at` | Most recent activity timestamp |
| `comments` | Array ordered by `commented_at` |

### Comment fields

| Field | Notes |
|-------|-------|
| `author` / `author_name` | Who wrote the comment |
| `body` / `content` | Comment text |
| `comment_type` | Optional type label |
| `created_at` | When posted |

### Response guidelines

When summarising tickets for the user:
1. Lead with **ticket number**, **subject**, **status**, **client**
2. Quote relevant comment excerpts with author and date
3. Note linked **GitHub branches** when relevant to dev work
4. Flag tickets with no comments or stale `last_comment_at`
5. If data looks outdated, call `sync_tickets` then re-fetch

### Sync before analysis

If the user needs fresh data or `synced_at` is old:

```bash
# MCP: call sync_tickets tool
# CLI:
curl -X POST "$APP_URL/api/tickets/sync" -b cookies.txt
# Poll GET /api/tickets/sync/status until status=completed
```

---

## Categorize tickets

Use when the user asks to categorize, classify, or label uncategorized tickets.
The **local agent** picks categories; the app stores them via the API.

### Workflow

1. Optionally run `sync_tickets` if data is stale
2. Call `list_ticket_categories` — **must** pick from this list only
3. Call `list_uncategorized_tickets` (use `limit`; process in batches if many)
4. For each ticket, classify using `subject` and `description`; call `get_ticket` when comments would disambiguate
5. Call `assign_ticket_category` with the chosen `category_id`
6. Skip tickets that already have a category; do not retry after a 409 response
7. Summarize: processed, assigned, skipped, failures

### Valid categories (examples)

| Category | Typical signals |
|----------|-----------------|
| Bug / broken functionality | Errors, crashes, wrong behaviour, regressions |
| Feature / enhancement request | New capability, UI improvement, "can we add…" |
| Data / reporting | Exports, reports, incorrect figures, missing fields in lists |
| Document / file management | Uploads, downloads, templates, PDFs, letters |
| CPD | Continuing professional development hours, certificates |
| Tax / licence | Tax practitioner licence, SARS, regulatory compliance |
| Membership / member profile | Member details, standing, profile updates |
| ECMS / applications | Application workflow, ECMS forms and status |
| Payments / billing | Invoices, payments, balances, refunds |
| Email / notifications | Emails not sent/received, notification issues |

### Rules

- Never overwrite tickets that already have a category (API returns 409)
- Only assign `category_id` values from `list_ticket_categories`
- Prefer the most specific category; use `get_ticket` when unsure

---

## Error handling

| Error | Action |
|-------|--------|
| 401 Unauthenticated | Check `API_TOKEN` Bearer header, or `AUTH_PASSWORD` session login |
| 404 ticket not found | Try `search_tickets` or `list_tickets`; ticket may not be synced |
| 409 category conflict | Ticket already categorized — skip and continue |
| Empty results | Run `sync_tickets` first |
| MCP not connected | Fall back to CLI script |

For full endpoint and schema details, see [reference.md](reference.md).
