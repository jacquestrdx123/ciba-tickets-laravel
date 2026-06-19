#!/usr/bin/env python3
"""Shared ticket data access for MCP server and CLI scripts."""

from __future__ import annotations

import json
import sqlite3
import urllib.parse
from dataclasses import dataclass
from pathlib import Path
from typing import Any
from urllib.parse import urljoin

import httpx


@dataclass
class Config:
    base_url: str
    password: str
    db_path: Path | None


def load_env_file(path: Path) -> dict[str, str]:
    values: dict[str, str] = {}
    if not path.is_file():
        return values
    for line in path.read_text().splitlines():
        line = line.strip()
        if not line or line.startswith("#") or "=" not in line:
            continue
        key, _, value = line.partition("=")
        values[key.strip()] = value.strip().strip('"').strip("'")
    return values


def load_config(project_root: Path | None = None) -> Config:
    import os

    env: dict[str, str] = {}
    if project_root:
        env = load_env_file(project_root / ".env")

    base_url = (
        os.environ.get("CIBA_TICKETS_BASE_URL")
        or env.get("APP_URL")
        or "http://localhost"
    ).rstrip("/")

    password = os.environ.get("CIBA_TICKETS_PASSWORD") or env.get("AUTH_PASSWORD") or ""

    db_path: Path | None = None
    explicit_db = os.environ.get("CIBA_TICKETS_DB_PATH")
    if explicit_db:
        db_path = Path(explicit_db)
    elif project_root and env.get("DB_CONNECTION") == "sqlite":
        db_path = project_root / "database" / "database.sqlite"

    return Config(base_url=base_url, password=password, db_path=db_path)


class TicketClient:
    def __init__(self, config: Config):
        self.config = config
        self._http: httpx.Client | None = None

    def close(self) -> None:
        if self._http:
            self._http.close()
            self._http = None

    def _http_client(self) -> httpx.Client:
        if self._http is None:
            self._http = httpx.Client(base_url=self.config.base_url, timeout=60.0, follow_redirects=True)
            if self.config.password:
                self._login()
        return self._http

    def _xsrf_token(self, client: httpx.Client) -> str:
        token = client.cookies.get("XSRF-TOKEN", "")
        return urllib.parse.unquote(token) if token else ""

    def _login(self) -> None:
        client = self._http
        assert client is not None
        client.get("/login")
        token = self._xsrf_token(client)
        headers = {"X-XSRF-TOKEN": token} if token else {}
        client.post(
            "/login",
            data={"password": self.config.password, "_token": token},
            headers=headers,
        )

    def _api_get(self, path: str) -> Any:
        response = self._http_client().get(path, headers={"Accept": "application/json"})
        response.raise_for_status()
        return response.json()

    def _api_post(self, path: str) -> Any:
        client = self._http_client()
        token = self._xsrf_token(client)
        headers = {"Accept": "application/json"}
        if token:
            headers["X-XSRF-TOKEN"] = token
        response = client.post(path, headers=headers)
        response.raise_for_status()
        return response.json()

    def _api_patch(self, path: str, json_body: dict[str, Any]) -> Any:
        client = self._http_client()
        token = self._xsrf_token(client)
        headers = {"Accept": "application/json", "Content-Type": "application/json"}
        if token:
            headers["X-XSRF-TOKEN"] = token
        response = client.patch(path, headers=headers, json=json_body)
        response.raise_for_status()
        return response.json()

    def list_categories(self) -> list[dict[str, Any]]:
        data = self._api_get("/api/ticket-categories")
        return data if isinstance(data, list) else []

    def list_uncategorized_tickets(self, *, limit: int = 50) -> list[dict[str, Any]]:
        data = self._api_get("/api/tickets?uncategorized=1")
        tickets = data.get("tickets") or []
        return tickets[:limit]

    def assign_ticket_category(self, vendor_id: int, category_id: int) -> dict[str, Any]:
        return self._api_patch(
            f"/api/tickets/{vendor_id}/category",
            {"category_id": category_id},
        )

    def list_tickets(
        self,
        *,
        status: str | None = None,
        client_name: str | None = None,
        limit: int = 50,
    ) -> list[dict[str, Any]]:
        tickets = self._fetch_all_tickets()
        if status:
            tickets = [t for t in tickets if (t.get("status") or "").lower() == status.lower()]
        if client_name:
            needle = client_name.lower()
            tickets = [t for t in tickets if needle in (t.get("client_name") or "").lower()]
        return tickets[:limit]

    def get_ticket(
        self,
        *,
        vendor_id: int | None = None,
        ticket_number: str | None = None,
    ) -> dict[str, Any] | None:
        if vendor_id is not None:
            try:
                return self._api_get(f"/api/tickets/{vendor_id}")
            except httpx.HTTPError:
                return self._db_get_ticket(vendor_id=vendor_id)

        if ticket_number:
            for ticket in self._fetch_all_tickets():
                if (ticket.get("ticket_number") or "").lower() == ticket_number.lower():
                    vid = ticket.get("vendor_id")
                    if vid is not None:
                        return self.get_ticket(vendor_id=int(vid))
            return self._db_get_ticket(ticket_number=ticket_number)

        return None

    def search_tickets(self, query: str, *, limit: int = 20) -> list[dict[str, Any]]:
        needle = query.lower()
        results: list[dict[str, Any]] = []

        for ticket in self._fetch_all_tickets():
            if len(results) >= limit:
                break

            ticket_number = (ticket.get("ticket_number") or "").lower()
            subject = (ticket.get("subject") or "").lower()
            client = (ticket.get("client_name") or "").lower()
            description = (ticket.get("description") or "").lower()

            if needle in ticket_number or needle in subject or needle in client or needle in description:
                results.append(ticket)
                continue

            vid = ticket.get("vendor_id")
            if vid is not None:
                detail = self.get_ticket(vendor_id=int(vid))
                if detail:
                    for comment in detail.get("comments") or []:
                        body = (comment.get("body") or comment.get("content") or "").lower()
                        if needle in body:
                            results.append(detail)
                            break

        return results[:limit]

    def sync_tickets(self) -> dict[str, Any]:
        result = self._api_post("/api/tickets/sync")
        import time

        for _ in range(120):
            status = self._api_get("/api/tickets/sync/status")
            if status.get("status") in ("completed", "failed", "idle"):
                return status
            time.sleep(2)
        return {"status": "timeout", "message": "Sync did not complete within 4 minutes"}

    def _fetch_all_tickets(self) -> list[dict[str, Any]]:
        try:
            data = self._api_get("/api/tickets")
            return data.get("tickets") or []
        except httpx.HTTPError:
            return self._db_list_tickets()

    def _db_connect(self) -> sqlite3.Connection:
        if not self.config.db_path or not self.config.db_path.is_file():
            raise RuntimeError("Database not available and HTTP API failed")
        conn = sqlite3.connect(self.config.db_path)
        conn.row_factory = sqlite3.Row
        return conn

    def _row_ticket(self, row: sqlite3.Row) -> dict[str, Any]:
        ticket = dict(row)
        if ticket.get("github_branches"):
            try:
                ticket["github_branches"] = json.loads(ticket["github_branches"])
            except (TypeError, json.JSONDecodeError):
                ticket["github_branches"] = []
        return ticket

    def _db_list_tickets(self) -> list[dict[str, Any]]:
        with self._db_connect() as conn:
            rows = conn.execute(
                "SELECT * FROM tickets ORDER BY last_comment_at DESC"
            ).fetchall()
            return [self._row_ticket(r) for r in rows]

    def _db_get_ticket(
        self,
        *,
        vendor_id: int | None = None,
        ticket_number: str | None = None,
    ) -> dict[str, Any] | None:
        with self._db_connect() as conn:
            if vendor_id is not None:
                row = conn.execute(
                    "SELECT * FROM tickets WHERE vendor_id = ?", (vendor_id,)
                ).fetchone()
            elif ticket_number:
                row = conn.execute(
                    "SELECT * FROM tickets WHERE ticket_number = ?", (ticket_number,)
                ).fetchone()
            else:
                return None

            if not row:
                return None

            ticket = self._row_ticket(row)
            comments = conn.execute(
                "SELECT * FROM comments WHERE ticket_id = ? ORDER BY commented_at",
                (ticket["id"],),
            ).fetchall()

            ticket["comments"] = [
                {
                    "id": c["id"],
                    "vendor_id": c["vendor_id"],
                    "author": c["author_name"],
                    "author_name": c["author_name"],
                    "body": c["body"],
                    "content": c["body"],
                    "comment_type": c["comment_type"],
                    "created_at": c["commented_at"],
                }
                for c in comments
            ]
            ticket["vendor_id"] = ticket.get("vendor_id")
            return ticket
