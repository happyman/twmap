"""WebSocket progress notifications.

Replaces the PHP `Websocat`/`Slog` daemon for real-time progress reporting to
the web frontend. Uses the `websockets` client library to push progress
messages like ``ps%50`` on the convention used by the original tool.
"""

from __future__ import annotations

import asyncio
import logging

logger = logging.getLogger(__name__)


class Notifier:
    """A best-effort WebSocket progress notifier that never crashes the run."""

    def __init__(self, url: str | None = None, channel: str | None = None):
        self.url = url
        self.channel = channel
        self._enabled = bool(url)

    async def connect(self) -> None:
        """Open the WebSocket connection (no-op if disabled)."""
        if not self.enabled:
            return

    async def send(self, message: str) -> None:
        """Send a message. Errors are logged and swallowed."""
        if not self._enabled:
            return
        try:
            async with self._session() as ws:
                await ws.send(message)
        except Exception as exc:  # noqa: BLE001
            logger.debug("WebSocket send failed (ignored): %s", exc)

    def _session(self):
        import websockets

        return websockets.connect(self.url)

    async def close(self) -> None:
        pass

    @property
    def enabled(self) -> bool:
        return self._enabled


def make_progress_callback(notifier: Notifier | None = None):
    """Return a sync callback that reports progress via a Notifier.

    The callback is a no-op if notifier is None or disabled, so it can be
    threaded into the sync stitcher pipeline.
    """

    def cb(frac: float) -> None:
        if notifier is None or not notifier.enabled:
            return
        pct = int(round(frac * 100))
        try:
            asyncio.run(notifier.send(f"ps%{pct}"))
        except Exception:  # noqa: BLE001
            pass

    return cb


__all__ = ["Notifier", "make_progress_callback"]
