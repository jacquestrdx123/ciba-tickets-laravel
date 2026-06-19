#!/usr/bin/env python3
"""
Fetch CIBA tickets with comments from the local Laravel app.

Usage:
  python fetch_tickets.py --project-root /path/to/ciba-tickets-laravel
  python fetch_tickets.py --project-root ... --ticket-number T-12345
  python fetch_tickets.py --project-root ... --vendor-id 156 --out tickets.json
"""

from __future__ import annotations

import argparse
import json
import sys
from pathlib import Path

sys.path.insert(0, str(Path(__file__).resolve().parent.parent))

from lib.tickets_client import TicketClient, load_config


def parse_args() -> argparse.Namespace:
    parser = argparse.ArgumentParser(description="Fetch CIBA tickets with comments")
    parser.add_argument("--project-root", type=Path, required=True)
    parser.add_argument("--ticket-number", type=str)
    parser.add_argument("--vendor-id", type=int)
    parser.add_argument("--status", type=str)
    parser.add_argument("--search", type=str)
    parser.add_argument("--limit", type=int, default=100)
    parser.add_argument("--out", type=Path, help="Write JSON to file (default: stdout)")
    parser.add_argument("--sync", action="store_true", help="Sync from vendor before fetching")
    return parser.parse_args()


def main() -> None:
    args = parse_args()
    config = load_config(args.project_root)
    client = TicketClient(config)

    try:
        if args.sync:
            print("Syncing tickets...", file=sys.stderr)
            status = client.sync_tickets()
            print(json.dumps(status, indent=2), file=sys.stderr)

        if args.ticket_number or args.vendor_id is not None:
            ticket = client.get_ticket(
                vendor_id=args.vendor_id,
                ticket_number=args.ticket_number,
            )
            payload = ticket or {"error": "Ticket not found"}
        elif args.search:
            tickets = client.search_tickets(args.search, limit=args.limit)
            payload = {"count": len(tickets), "tickets": tickets}
        else:
            tickets = client.list_tickets(status=args.status, limit=args.limit)
            detailed = []
            for stub in tickets:
                vid = stub.get("vendor_id")
                if vid is None:
                    continue
                detail = client.get_ticket(vendor_id=int(vid))
                if detail:
                    detailed.append(detail)
            payload = {"count": len(detailed), "tickets": detailed}

        output = json.dumps(payload, indent=2, default=str)
        if args.out:
            args.out.write_text(output)
            print(f"Wrote {args.out}", file=sys.stderr)
        else:
            print(output)

    finally:
        client.close()


if __name__ == "__main__":
    main()
