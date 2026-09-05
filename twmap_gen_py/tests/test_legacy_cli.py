"""Tests for the legacy queue-mode CLI additions.

Covers the args the beanstalkd job payload passes (from backend_make.php +
worker): ``-i``, ``-a``, ``--agent``, ``--logurl_prefix``, ``--logfile``, the
``-l`` channel-vs-URL ws resolution, MIME-encoded titles, the made.php
callback, and the Penghu ``p`` filename marker.
"""

import http.server
import threading
import time

import pytest

from mapgen import cli

# --------------------------------------------------------------------------
# MIME title decoding
# --------------------------------------------------------------------------


def test_decode_mime_title_utf8_base64():
    # b64 of "我的地圖"
    import base64

    b64 = base64.b64encode("我的地圖".encode("utf-8")).decode()
    decoded = cli._decode_mime_title(f"=?UTF-8?B?{b64}?=")
    assert decoded == "我的地圖"


def test_decode_mime_title_plain_passthrough():
    assert cli._decode_mime_title("我的地圖") == "我的地圖"
    assert cli._decode_mime_title("") == ""


def test_decode_mime_title_folded():
    # PHP _mb_mime_encode splits long titles into multiple =?...?= chunks
    # joined by CRLF.
    import base64

    part1 = "這裡有一段滿長的臺灣地圖標題內容"
    part2 = "第二段"
    b64 = lambda s: base64.b64encode(s.encode("utf-8")).decode()  # noqa: E731
    s = f"=?UTF-8?B?{b64(part1)}?=\r\n=?UTF-8?B?{b64(part2)}?=a"
    assert cli._decode_mime_title(s) == part1 + part2 + "a"


# --------------------------------------------------------------------------
# ws URL resolution (channel + logurl_prefix vs full URL)
# --------------------------------------------------------------------------


class _Args:
    def __init__(self, ws_url=None, logurl_prefix=None):
        self.ws_url = ws_url
        self.logurl_prefix = logurl_prefix


def test_effective_ws_url_full_url():
    assert cli._effective_ws_url(_Args("ws://twmap:9002/twmap_abc")) == (
        "ws://twmap:9002/twmap_abc"
    )


def test_effective_ws_url_channel_plus_prefix():
    assert cli._effective_ws_url(
        _Args("abc", "ws://twmap:9002/twmap_")
    ) == "ws://twmap:9002/twmap_abc"


def test_effective_ws_url_channel_no_prefix_is_none():
    assert cli._effective_ws_url(_Args("abc")) is None


def test_effective_ws_url_none():
    assert cli._effective_ws_url(_Args()) is None


def test_extract_channel():
    assert cli._extract_channel(_Args("abc")) == "abc"
    assert (
        cli._extract_channel(_Args("ws://twmap:9002/twmap_abc")) == "twmap_abc"
    )


# --------------------------------------------------------------------------
# output prefix (Penghu marker)
# --------------------------------------------------------------------------


def _region():
    from mapgen.proj import Region

    return Region(x0=307000, y0=2677000, x1=319000, y1=2671000, datum="TWD67")


def test_output_prefix_penghu_marker():
    a1 = _Args()
    a1.map_type = "2016"
    a1.penghu = 0
    assert cli._output_prefix(_region(), a1) == "307000x2677000-12x6-v2016_TWD67"
    a2 = _Args()
    a2.map_type = "2016"
    a2.penghu = 1
    assert cli._output_prefix(_region(), a2) == "307000x2677000-12x6-v2016p_TWD67"


# --------------------------------------------------------------------------
# legacy payload parsing
# --------------------------------------------------------------------------


def test_parse_legacy_queue_payload():
    args = cli.build_parser().parse_args(
        [
            "make",
            "-r",
            "307:2677:12:6:TWD67",
            "-O",
            "/tmp/out",
            "-v",
            "2016",
            "-t",
            "=?UTF-8?B?5aaC5qC3?=",
            "-i",
            "1.2.3.4",
            "-p",
            "0",
            "-m",
            "/dev/shm",
            "-l",
            "chan01",
            "-a",
            "http://twmap/makemap/api/made.php",
            "-D",
            "5x7",
            "-e",
            "--agent",
            "local",
            "--logurl_prefix",
            "ws://twmap:9002/twmap_",
            "--logfile",
            "/tmp/x.log",
        ]
    )
    assert args.region == "307:2677:12:6:TWD67"
    assert args.output == "/tmp/out"
    assert args.map_type == "2016"
    assert args.remote_ip == "1.2.3.4"
    assert args.penghu == 0
    assert args.ws_url == "chan01"
    assert args.callback == "http://twmap/makemap/api/made.php"
    assert args.dims == ["5x7"]
    assert args.grid_100m is True
    assert args.agent == "local"
    assert args.logurl_prefix == "ws://twmap:9002/twmap_"
    assert args.logfile == "/tmp/x.log"


def test_legacy_region_is_kilometers():
    parsed = cli._parse_region("307:2677:12:6:TWD67")
    assert parsed["x0"] == 307000
    assert parsed["y0"] == 2677000
    assert parsed["shiftx"] == 12
    assert parsed["shifty"] == 6
    assert parsed["datum"] == "TWD67"


def test_modern_region_is_meters():
    parsed = cli._parse_region("307000,2677000,12,6,TWD67")
    assert parsed["x0"] == 307000
    assert parsed["y0"] == 2677000


# --------------------------------------------------------------------------
# made.php callback
# --------------------------------------------------------------------------


class _Recorder(http.server.BaseHTTPRequestHandler):
    requests = []
    code = None

    def do_GET(self):  # noqa: N802
        type(self).requests.append(self.path)
        if getattr(type(self), "code", None) is not None:
            status = type(self).code
        else:
            status = 200 if getattr(type(self), "ok", True) else 500
        self.send_response(status)
        self.end_headers()
        self.wfile.write(b"no such channel")

    def log_message(self, *a):
        pass


def _callback_http_server(ok=True):
    _Recorder.ok = ok
    _Recorder.requests = []
    srv = http.server.ThreadingHTTPServer(("127.0.0.1", 0), _Recorder)
    thread = threading.Thread(target=srv.serve_forever, daemon=True)
    thread.start()
    return srv


def _callback_args(url, channel="chan01"):
    return cli.build_parser().parse_args(
        ["make", "--callback", url, "-l", channel, "--agent", "tester"]
    )


def test_handle_callback_posts(monkeypatch):
    for env in ("HTTPS_PROXY", "http_proxy", "HTTP_PROXY"):
        monkeypatch.delenv(env, raising=False)
    srv = _callback_http_server(ok=True)
    try:
        url = f"http://127.0.0.1:{srv.server_port}/twmap/api/made.php"
        args = _callback_args(url)
        cli._handle_callback(args)
        assert len(_Recorder.requests) == 1
        req = _Recorder.requests[0]
        assert "ch=chan01" in req
        assert "status=ok" in req
        assert "agent=tester" in req
        assert "params=" in req
    finally:
        srv.shutdown()


def test_handle_callback_no_callback_is_noop():
    args = cli.build_parser().parse_args(["make", "--region", "1,2,3,4"])
    cli._handle_callback(args)  # must not raise


def test_handle_callback_fails_on_persistent_error(monkeypatch):
    for env in ("HTTPS_PROXY", "http_proxy", "HTTP_PROXY"):
        monkeypatch.delenv(env, raising=False)
    srv = _callback_http_server(ok=False)
    try:
        url = f"http://127.0.0.1:{srv.server_port}/twmap/api/made.php"
        args = _callback_args(url)
        with pytest.raises(RuntimeError, match="callback failed"):
            cli._handle_callback(args)
    finally:
        srv.shutdown()


def test_handle_callback_4xx_is_final_no_retry(monkeypatch):
    """A 4xx (e.g. made.php "no such channel") must not be retried or raise.

    Retrying a dead channel can never succeed; treating it as final avoids
    releasing the job so the map is not regenerated (and the err spammed).
    """
    for env in ("HTTPS_PROXY", "http_proxy", "HTTP_PROXY"):
        monkeypatch.delenv(env, raising=False)
    _Recorder.code = 400
    _Recorder.requests = []
    srv = _callback_http_server()
    try:
        url = f"http://127.0.0.1:{srv.server_port}/twmap/api/made.php"
        args = _callback_args(url)
        cli._handle_callback(args)  # must not raise
        assert len(_Recorder.requests) == 1  # exactly one attempt, no retry
    finally:
        _Recorder.code = None
        srv.shutdown()


class _SlowRecorder(http.server.BaseHTTPRequestHandler):
    """made.php that is still busy (finish_task sleep+DB+migrate) when the
    old 2s read timeout would have fired. Must receive exactly one GET."""

    calls = 0
    delay = 3.0

    def do_GET(self):  # noqa: N802
        time.sleep(type(self).delay)
        type(self).calls += 1
        self.send_response(200)
        self.end_headers()
        self.wfile.write(b"<h1>done</h1>")

    def log_message(self, *a):
        pass


def _slow_callback_http_server():
    _SlowRecorder.calls = 0
    srv = http.server.ThreadingHTTPServer(("127.0.0.1", 0), _SlowRecorder)
    thread = threading.Thread(target=srv.serve_forever, daemon=True)
    thread.start()
    return srv


def test_handle_callback_slow_success_is_not_resent(monkeypatch):
    """A made.php taking longer than 2s (the old per-attempt timeout) must be
    allowed to finish instead of being abandoned and re-sent.

    The old ``urlopen(url, timeout=2)`` timed out during finish_task, retried,
    and hit made.php after it had already deleted the channel key -> the
    ``no such channel: ok`` error. The retry must not happen: the request is
    given the full 30s (PHP ``--connect-timeout 2 --max-time 30`` parity).
    """
    for env in ("HTTPS_PROXY", "http_proxy", "HTTP_PROXY"):
        monkeypatch.delenv(env, raising=False)
    srv = _slow_callback_http_server()
    try:
        url = f"http://127.0.0.1:{srv.server_port}/twmap/api/made.php"
        args = _callback_args(url)
        cli._handle_callback(args)  # must not raise
        assert _SlowRecorder.calls == 1  # exactly once, no duplicate callback
    finally:
        srv.shutdown()


# --------------------------------------------------------------------------
# notifier loopback hostname detection
# --------------------------------------------------------------------------


def test_notifier_is_loopback_resolves_hostname(monkeypatch):
    import socket

    from mapgen.notify import Notifier

    def fake_getaddrinfo(host, port):
        return [(2, 1, 6, "", ("127.0.0.1", 0))]

    n = Notifier(url="ws://twmap:9002/twmap_abc")
    monkeypatch.setattr(socket, "getaddrinfo", fake_getaddrinfo)
    assert n._is_loopback() is True


def test_notifier_is_loopback_nonloopback(monkeypatch):
    import socket

    from mapgen.notify import Notifier

    def fake_getaddrinfo(host, port):
        return [(2, 1, 6, "", ("144.6.70.107", 0))]

    n = Notifier(url="ws://external:9002/twmap_abc")
    monkeypatch.setattr(socket, "getaddrinfo", fake_getaddrinfo)
    assert n._is_loopback() is False


def test_notifier_is_loopback_literal():
    from mapgen.notify import Notifier

    assert Notifier(url="ws://localhost:9002/x")._is_loopback()
    assert Notifier(url="ws://127.0.0.1:9002/x")._is_loopback()
