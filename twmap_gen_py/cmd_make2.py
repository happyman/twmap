"""Legacy-compatible top-level entry point.

Mirrors `cmd_make2.php`: ``python cmd_make2.py -r x:y:w:h:datum -O ./out/ -v 2016``
forwards to the `mapgen` CLI.
"""

from __future__ import annotations

import sys

from mapgen.cli import main

if __name__ == "__main__":
    sys.exit(main())
