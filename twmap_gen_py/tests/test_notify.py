"""Tests for WebSocket progress notifications (notify.py) and the progress
wiring in ``cmd_make``.
"""

import threading
import time
from pathlib import Path

import numpy as np
import websockets

from mapgen import cli
from mapgen.notify import Notifier

_GPX = """<?xml version="1.0" encoding="UTF-8"?>
<gpx version="1.1" creator="opencode" xmlns="http://www.topografix.com/GPX/1/1">
  <trk><name>路線</name>
    <trkseg>
      <trkpt lat="24.79" lon="121.004"><ele>500</ele></trkpt>
      <trkpt lat="24.795" lon="121.009"><ele>600</ele></trkpt>
    </trkseg>
  </trk>
</gpx>
"""


def _wait_for(predicate, timeout=6.0):
    """Poll until predicate() is truthy or timeout elapses (bounded wait)."""
    deadline = time.time() + timeout
    while time.time() < deadline:
        if predicate():
            return True
        time.sleep(0.05)
    return False


class WsEchoServer:
    """Minimal in-process WebSocket server that records received messages."""

    def __init__(self, port: int):
        self.port = port
        self.received: list[str] = []
        self._stop = threading.Event()
        self._thread: threading.Thread | None = None

    async def _handler(self, ws):
        async for msg in ws:
            self.received.append(msg)

    def _run(self):
        import asyncio

        loop = asyncio.new_event_loop()
        asyncio.set_event_loop(loop)
        async def main():
            async with websockets.serve(self._handler, "127.0.0.1", self.port):
                await asyncio.sleep(0.1)
                while not self._stop.is_set():
                    await asyncio.sleep(0.1)
        loop.run_until_complete(main())

    def start(self):
        self._thread = threading.Thread(target=self._run, daemon=True)
        self._thread.start()
        time.sleep(0.6)

    def stop(self):
        self._stop.set()
        if self._thread is not None:
            self._thread.join(timeout=2.0)


def test_notifier_sends_progress():
    server = WsEchoServer(22111)
    server.start()
    try:
        n = Notifier(url=f"ws://127.0.0.1:{server.port}/twmap_test")
        n.start()
        n.step("start")
        n.progress(0.1)
        n.step("download", 0.4)
        n.progress(1.0)
        assert _wait_for(lambda: "ps%100" in server.received), (
            f"ps%100 not received, got {server.received}"
        )
        n.stop()

        msgs = server.received
        assert msgs, "no progress messages received by frontend"
        pcts = [int(m[3:]) for m in msgs if m.startswith("ps%")]
        assert pcts == sorted(pcts), "progress not monotonic"
        assert any(m.startswith("step:") for m in msgs)
    finally:
        server.stop()


def test_notifier_disabled_is_noop():
    n = Notifier(url=None)
    n.start()
    n.progress(0.5)
    n.step("x", 0.5)
    assert n.enabled is False
    n.stop()
    # No thread started, nothing to assert beyond no error.


def test_make_progress_callback_noop_without_notifier():
    from mapgen.notify import make_progress_callback

    cb = make_progress_callback(None)
    # Should not raise.
    cb(0.5)


async def _fake_base_image(region, source, workdir, *a, **k):
    return np.full((150, 150, 3), 128, np.uint8)


def test_cmd_make_emits_progress_steps(tmp_path, monkeypatch):
    import logging

    import mapgen.stitcher as stitcher

    monkeypatch.setattr(stitcher, "build_base_image", _fake_base_image)
    logging.disable(logging.CRITICAL)

    server = WsEchoServer(22112)
    server.start()
    try:
        gpx_path = Path(tmp_path) / "track.gpx"
        gpx_path.write_text(_GPX, encoding="utf-8")
        out = tmp_path / "out2"
        args = cli.build_parser().parse_args(
            [
                "make",
                "--region",
                "250000,2743650,1,1,TWD67",
                "--output",
                str(out),
                "--map-type",
                "2016",
                "--gpx",
                f"{gpx_path}:0:0",
                "--ws-url",
                f"ws://127.0.0.1:{server.port}/twmap_cli",
                "--tmpdir",
                str(out),
            ]
        )
        cli.cmd_make(args)

        assert _wait_for(lambda: "ps%100" in server.received), (
            f"cmd_make didn't reach 100%, got {server.received}"
        )
        msgs = server.received
        assert msgs, "cmd_make sent no progress"
        steps = [m for m in msgs if m.startswith("step:")]
        pcts = [int(m[3:]) for m in msgs if m.startswith("ps%")]
        assert "step:start" in steps
        assert any(s.startswith("step:download") for s in steps)
        assert "step:export" in steps
        assert pcts and pcts[-1] == 100, f"expected end at 100, got {pcts}"
        assert pcts == sorted(pcts), f"progress not monotonic: {pcts}"
    finally:
        server.stop()
        logging.disable(logging.NOTSET)
