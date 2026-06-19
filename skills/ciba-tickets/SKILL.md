---
name: ciba-tickets
description: >
  Query and analyse CIBA support tickets with their full comment threads.
  Use when the user asks about tickets, ticket numbers, client issues,
  support queue status, ticket comments, ticket descriptions, GitHub branches
  linked to tickets, or needs to search/summarise/review support cases — even
  casual requests like "show me ticket 12345", "what did the client say on
  ticket X", or "list open tickets for Acme". Prefer the bundled MCP tools
  when available; otherwise use the fetch script or HTTP API directly.
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
| `AUTH_PASSWORD` | App login password for HTTP API access |
| `DB_CONNECTION` + sqlite path | Used for direct DB reads when HTTP fails |

Environment overrides (optional):
- `CIBA_TICKETS_BASE_URL`
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

## Error handling

| Error | Action |
|-------|--------|
| 401 Unauthenticated | Check `AUTH_PASSWORD` / login flow |
| 404 ticket not found | Try `search_tickets` or `list_tickets`; ticket may not be synced |
| Empty results | Run `sync_tickets` first |
| MCP not connected | Fall back to CLI script |

For full endpoint and schema details, see [reference.md](reference.md).
