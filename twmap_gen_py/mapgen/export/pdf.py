"""PDF export: convert page PNGs to a single multi-page PDF.

Replaces the PHP pipeline of `img2pdf` (single pages) + `gs` (merge).
This uses the `img2pdf` library for lossless PNG->PDF, then `pypdf` to
merge the single-page PDFs and (optionally) add bookmarks.
"""

from __future__ import annotations

import logging
from pathlib import Path
from typing import Sequence

import img2pdf
from pypdf import PdfReader, PdfWriter

logger = logging.getLogger(__name__)


def pages_to_pdf(
    page_paths: Sequence[str | Path],
    out_path: str | Path,
    title: str = "我的地圖",
    author: str = "twmap-gen",
    subject: str = "",
    keywords: str = "",
) -> Path:
    """Merge a set of page image files into a single PDF.

    Uses img2pdf to convert each PNG page to a single-page PDF (lossless,
    fitting A4, shrink-only), then pypdf to concatenate them.
    """
    page_paths = [Path(p) for p in page_paths]
    out = Path(out_path)

    # Convert each page to a single-page PDF
    single_pdfs: list[Path] = []
    pdf_layout = img2pdf.get_layout_fun(
        pagesize=(595.28, 841.89),  # A4 in points
        fit=img2pdf.FitMode.shrink,
        auto_orient=True,
    )
    for i, p in enumerate(page_paths):
        sp = out.with_name(f"{out.stem}_page{i}.pdf")
        with open(p, "rb") as f, open(sp, "wb") as g:
            g.write(img2pdf.convert(f, layout_fun=pdf_layout))
        single_pdfs.append(sp)

    # Merge
    writer = PdfWriter()
    for sp in single_pdfs:
        reader = PdfReader(sp)
        for page in reader.pages:
            writer.add_page(page)
    writer.add_metadata(
        {
            "/Title": title,
            "/Author": author,
            "/Subject": subject,
            "/Keywords": keywords,
        }
    )
    with open(out, "wb") as f:
        writer.write(f)

    # Clean up single page PDFs
    for sp in single_pdfs:
        try:
            sp.unlink()
        except OSError:
            pass

    logger.info("Wrote %s (%d pages)", out, len(page_paths))
    return out


__all__ = ["pages_to_pdf"]
