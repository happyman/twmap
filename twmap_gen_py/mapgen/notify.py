"""WebSocket progress notifications.

Replaces the PHP `Websocat`/`Slog` daemon for real-time progress reporting to
the web frontend. Sends progress messages using the original convention:

- ``ps%NN``   overall progress percentage (0-100)
- ``step:<name>``  a named pipeline stage boundary
- ``err:<msg>``     a fatal error, so the frontend knows the run failed

The Notifier runs a persistent asyncio loop in a background thread. That lets
sync pipeline code (grinder, splitter, export) report progress too, while the
async tile download blocks report directly through the same thread-safe queue.
"""

from __future__ import annotations

import asyncio
import logging
import queue
import threading
from itertools import count

logger = logging.getLogger(__name__)


class Notifier:
    """Best-effort, thread-safe WebSocket progress notifier.

    Never raises out of the caller path: all send failures are logged at debug
    level and swallowed, so progress reporting can never break a map build.
    """

    _ids = count(1)

    def __init__(self, url: str | None = None, channel: str | None = None):
        self.url = url
        self.channel = channel
        self._enabled = bool(url)
        self._running = False
        self._thread: threading.Thread | None = None
        self._loop: asyncio.AbstractEventLoop | None = None
        self._queue: queue.Queue[str] = queue.Queue()
        self._calls = []

    # ------------------------------------------------------------------ #
    # lifecycle
    # ------------------------------------------------------------------ #

    def start(self) -> None:
        """Start the background sender thread (no-op if disabled)."""
        if not self._enabled or self._running:
            return
        self._running = True
        self._thread = threading.Thread(
            target=self._run_loop, name="twmap-notifier", daemon=True
        )
        self._thread.start()
        logger.debug("Notifier started against %s", self.url)

    def stop(self) -> None:
        """Stop the sender thread and close the WebSocket.

        Puts a sentinel and joins the thread, guaranteeing every already-queued
        progress message is flushed to the frontend before this returns (the
        sender keeps draining until it reaches the sentinel).
        """
        if not self._running:
            return
        self._running = False
        self._queue.put(None)  # sentinel: sender stops after draining
        if self._thread is not None:
            self._thread.join(timeout=10.0)
        self._thread = None

    def _run_loop(self) -> None:
        self._loop = asyncio.new_event_loop()
        asyncio.set_event_loop(self._loop)
        self._loop.run_until_complete(self._sender())
        self._loop.close()

    async def _sender(self) -> None:
        import websockets

        kwargs: dict = dict(open_timeout=10, ping_interval=None)
        # Never route frontend connections through a corporate proxy.
        # websockets honors http_proxy/https_proxy env vars by default, which
        # can wrongly 403 a same-host ws:// connection. Bypass for loopback —
        # both literal names and hostnames that resolve to a loopback address
        # (e.g. `twmap` on the same box), and let explicit wss:// hosts go
        # through the normal path.
        if self._is_loopback():
            kwargs["proxy"] = None

        ws = None
        try:
            ws = await websockets.connect(self.url, **kwargs)
        except Exception as exc:  # noqa: BLE001
            logger.debug("WebSocket connect failed (ignored): %s", exc)
            # Connect failed: drop anything already queued and exit.
            self._queue.queue.clear()
            return

        try:
            # Drain the queue until the None sentinel (from stop()); keep
            # sending even after _running goes False so queued messages are
            # not lost at shutdown.
            while True:
                try:
                    msg = self._queue.get(timeout=0.5)
                except queue.Empty:
                    if not self._running:
                        break
                    continue
                if msg is None:
                    break
                try:
                    await ws.send(msg)
                    logger.debug("WS -> %s", msg)
                except Exception as exc:  # noqa: BLE001
                    logger.debug("WebSocket send failed (ignored): %s", exc)
        finally:
            try:
                await ws.close()
            except Exception:  # noqa: BLE001
                pass

    def _host(self) -> str:
        """Best-effort host portion of the configured WebSocket URL."""
        from urllib.parse import urlsplit

        try:
            return (urlsplit(self.url).hostname or "").lower()
        except Exception:  # noqa: BLE001
            return ""

    def _is_loopback(self) -> bool:
        """True if the WebSocket URL targets a loopback address.

        Checks the literal host first (localhost/127.0.0.1/::1) and then
        resolves the hostname, so a name like ``twmap`` that maps to 127.0.0.1
        on this box also bypasses the corporate proxy.
        """
        host = self._host()
        if host in ("localhost", "127.0.0.1", "::1"):
            return True
        if not host:
            return False
        try:
            import socket

            infos = socket.getaddrinfo(host, None)
        except OSError:
            return False
        return any(
            ai[4][0].startswith("127.") or ai[4][0] == "::1" for ai in infos
        )

    # ------------------------------------------------------------------ #
    # reporting
    # ------------------------------------------------------------------ #

    def send(self, message: str) -> None:
        """Queue a raw message. Safe to call from any thread."""
        if not self._enabled:
            return
        try:
            self._queue.put_nowait(message)
        except queue.Full:
            logger.debug("Notifier queue full, dropping %r", message)

    def progress(self, fraction: float) -> None:
        """Queue a ``ps%NN`` progress message (fraction 0..1)."""
        pct = max(0, min(100, int(round(fraction * 100))))
        self.send(f"ps%{pct}")

    def step(self, name: str, fraction: float | None = None) -> None:
        """Queue a named stage boundary, optionally with a progress percent."""
        self.send(f"step:{name}")
        if fraction is not None:
            self.progress(fraction)

    def error(self, message: str) -> None:
        self.send(f"err:{message}")

    @property
    def enabled(self) -> bool:
        return self._enabled


def make_progress_callback(notifier: Notifier | None = None):
    """Return a sync callback reporting ``ps%NN`` into async pipeline blocks.

    No-op if notifier is None or disabled, so it can be threaded into the
    sync stitcher pipeline without changes.
    """

    def cb(fraction: float) -> None:
        if notifier is None or not notifier.enabled:
            return
        notifier.progress(fraction)

    return cb


__all__ = ["Notifier", "make_progress_callback"]
