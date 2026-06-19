#!/usr/bin/env python3
"""MCP server exposing CIBA ticket tools with comments."""

from __future__ import annotations

import argparse
import json
import sys
from pathlib import Path

sys.path.insert(0, str(Path(__file__).resolve().parent.parent))

from lib.tickets_client import TicketClient, load_config

try:
    from mcp.server import Server
    from mcp.server.stdio import stdio_server
    from mcp.types import TextContent, Tool
except ImportError:
    print("Missing dependencies. Run: pip install mcp httpx", file=sys.stderr)
    sys.exit(1)


def parse_args() -> argparse.Namespace:
    parser = argparse.ArgumentParser(description="CIBA Tickets MCP server")
    parser.add_argument(
        "--project-root",
        type=Path,
        help="Path to ciba-tickets-laravel project (reads .env)",
    )
    return parser.parse_args()


def main() -> None:
    args = parse_args()
    config = load_config(args.project_root)
    client = TicketClient(config)
    server = Server("ciba-tickets")

    @server.list_tools()
    async def list_tools() -> list[Tool]:
        return [
            Tool(
                name="list_tickets",
                description="List synced support tickets. Does not include comment bodies — use get_ticket for full threads.",
                inputSchema={
                    "type": "object",
                    "properties": {
                        "status": {"type": "string", "description": "Filter by status (e.g. open, closed)"},
                        "client_name": {"type": "string", "description": "Filter by client name (partial match)"},
                        "limit": {"type": "integer", "description": "Max tickets to return", "default": 50},
                    },
                },
            ),
            Tool(
                name="get_ticket",
                description="Get a single ticket with full comment thread, description, and GitHub branches.",
                inputSchema={
                    "type": "object",
                    "properties": {
                        "vendor_id": {"type": "integer", "description": "Vendor/API ticket ID"},
                        "ticket_number": {"type": "string", "description": "Human-readable ticket number"},
                    },
                },
            ),
            Tool(
                name="search_tickets",
                description="Search tickets by subject, client, ticket number, description, or comment body text.",
                inputSchema={
                    "type": "object",
                    "properties": {
                        "query": {"type": "string", "description": "Search text"},
                        "limit": {"type": "integer", "default": 20},
                    },
                    "required": ["query"],
                },
            ),
            Tool(
                name="sync_tickets",
                description="Queue a full ticket sync from the vendor API and wait for completion.",
                inputSchema={"type": "object", "properties": {}},
            ),
        ]

    @server.call_tool()
    async def call_tool(name: str, arguments: dict) -> list[TextContent]:
        try:
            if name == "list_tickets":
                tickets = client.list_tickets(
                    status=arguments.get("status"),
                    client_name=arguments.get("client_name"),
                    limit=int(arguments.get("limit", 50)),
                )
                payload = {"count": len(tickets), "tickets": tickets}

            elif name == "get_ticket":
                vendor_id = arguments.get("vendor_id")
                ticket_number = arguments.get("ticket_number")
                if vendor_id is None and not ticket_number:
                    raise ValueError("Provide vendor_id or ticket_number")
                ticket = client.get_ticket(
                    vendor_id=int(vendor_id) if vendor_id is not None else None,
                    ticket_number=ticket_number,
                )
                if not ticket:
                    payload = {"error": "Ticket not found"}
                else:
                    payload = ticket

            elif name == "search_tickets":
                query = arguments.get("query", "").strip()
                if not query:
                    raise ValueError("query is required")
                results = client.search_tickets(query, limit=int(arguments.get("limit", 20)))
                payload = {"count": len(results), "tickets": results}

            elif name == "sync_tickets":
                payload = client.sync_tickets()

            else:
                raise ValueError(f"Unknown tool: {name}")

            return [TextContent(type="text", text=json.dumps(payload, indent=2, default=str))]

        except Exception as exc:
            return [TextContent(type="text", text=json.dumps({"error": str(exc)}))]

    async def run() -> None:
        async with stdio_server() as (read_stream, write_stream):
            await server.run(read_stream, write_stream, server.create_initialization_options())

    import asyncio
    try:
        asyncio.run(run())
    finally:
        client.close()


if __name__ == "__main__":
    main()
