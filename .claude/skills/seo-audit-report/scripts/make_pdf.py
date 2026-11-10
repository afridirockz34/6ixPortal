#!/usr/bin/env python3
"""
make_pdf.py — the audit report as a PDF, wrapped in the branded covers.

The HTML renderer produces the body. This prints it to PDF and puts 6ix's own
cover and back pages around it, so what the client receives is one file that
opens the way a report should rather than a web page somebody printed.

    python make_pdf.py report.html -o acme-audit.pdf \\
        --cover assets/cover.pdf --back assets/back.pdf

The covers are optional: without them you get the body alone, which is still a
correctly paginated PDF. That matters because a cover is a brand asset that
changes on its own schedule, and the report has to keep working in between.

Cover text — the client's name, their domain, the date — is drawn onto the
cover page rather than baked into it, so one Canva export serves every client:

    --cover-text "Acme Plumbing|acmeplumbing.ca|10 November 2026"

Needs a headless Chromium to print, and pypdf to merge. Both are checked for
before anything runs, with the install line in the error rather than a stack
trace.
"""

import argparse
import shutil
import subprocess
import sys
import tempfile
from pathlib import Path

CHROME_CANDIDATES = [
    "/opt/pw-browsers/chromium",
    "/usr/bin/chromium", "/usr/bin/chromium-browser", "/usr/bin/google-chrome",
    "/Applications/Google Chrome.app/Contents/MacOS/Google Chrome",
]


def find_chrome(explicit=None):
    if explicit:
        if Path(explicit).exists():
            return explicit
        raise SystemExit(f"No browser at {explicit}")
    for c in CHROME_CANDIDATES:
        if Path(c).exists():
            return c
    for name in ("chromium", "chromium-browser", "google-chrome", "chrome"):
        found = shutil.which(name)
        if found:
            return found
    raise SystemExit(
        "No Chromium or Chrome found to print with. Install one, or pass "
        "--chrome /path/to/chromium.")


def html_to_pdf(html_path, out_path, chrome):
    """Print the body. Margins are generous because this is a document."""
    html_path = Path(html_path).resolve()
    cmd = [
        chrome, "--headless", "--no-sandbox", "--disable-gpu",
        "--no-pdf-header-footer",
        f"--print-to-pdf={Path(out_path).resolve()}",
        html_path.as_uri(),
    ]
    res = subprocess.run(cmd, capture_output=True, text=True, timeout=180)
    if not Path(out_path).exists() or Path(out_path).stat().st_size == 0:
        raise SystemExit("Chromium did not produce a PDF.\n"
                         + (res.stderr or "").strip()[-800:])


def stamp_cover(cover_pdf, out_pdf, lines):
    """
    Draw the client's details onto the branded cover.

    Positioned from the bottom-left in points, which is where a Canva cover
    usually leaves room. Shift it with --cover-xy if the template differs.
    """
    try:
        from pypdf import PdfReader, PdfWriter
        from reportlab.pdfgen import canvas as rl_canvas
        from reportlab.lib.colors import HexColor
    except ImportError:
        raise SystemExit("Stamping the cover needs reportlab and pypdf: "
                         "pip install reportlab pypdf")

    reader = PdfReader(str(cover_pdf))
    page = reader.pages[0]
    w = float(page.mediabox.width)
    h = float(page.mediabox.height)

    x, y = lines["xy"]
    buf = Path(tempfile.mkstemp(suffix=".pdf")[1])
    c = rl_canvas.Canvas(str(buf), pagesize=(w, h))
    c.setFillColor(HexColor(lines["colour"]))
    size = lines["size"]
    for i, text in enumerate(lines["lines"]):
        if not text:
            continue
        c.setFont("Helvetica-Bold" if i == 0 else "Helvetica",
                  size if i == 0 else size * 0.52)
        c.drawString(x, y - i * size * 0.95, text)
    c.save()

    overlay = PdfReader(str(buf)).pages[0]
    page.merge_page(overlay)
    writer = PdfWriter()
    writer.add_page(page)
    with open(out_pdf, "wb") as fh:
        writer.write(fh)
    buf.unlink(missing_ok=True)


def merge(parts, out_path):
    try:
        from pypdf import PdfWriter
    except ImportError:
        raise SystemExit("Merging needs pypdf: pip install pypdf")
    writer = PdfWriter()
    for p in parts:
        writer.append(str(p))
    with open(out_path, "wb") as fh:
        writer.write(fh)


def main():
    ap = argparse.ArgumentParser(description=__doc__,
                                 formatter_class=argparse.RawDescriptionHelpFormatter)
    ap.add_argument("html", help="The rendered report from render_report.py.")
    ap.add_argument("-o", "--out", help="Where to write the PDF.")
    ap.add_argument("--cover", help="A PDF whose first page is the front cover.")
    ap.add_argument("--back", help="A PDF whose first page is the back page.")
    ap.add_argument("--cover-text", default="",
                    help="Pipe-separated lines to draw on the cover, "
                         "e.g. \"Acme Plumbing|acmeplumbing.ca|10 November 2026\".")
    ap.add_argument("--cover-xy", default="72,200",
                    help="Where to start that text, in points from the bottom-left. "
                         "Default 72,200.")
    ap.add_argument("--cover-size", type=float, default=28.0)
    ap.add_argument("--cover-colour", default="#111111")
    ap.add_argument("--chrome", help="Path to a Chromium/Chrome binary.")
    args = ap.parse_args()

    html = Path(args.html)
    if not html.exists():
        raise SystemExit(f"No such file: {html}")
    out = Path(args.out) if args.out else html.with_suffix(".pdf")
    chrome = find_chrome(args.chrome)

    tmp = Path(tempfile.mkdtemp(prefix="audit-pdf-"))
    body = tmp / "body.pdf"
    html_to_pdf(html, body, chrome)

    parts = []
    if args.cover:
        cover = Path(args.cover)
        if not cover.exists():
            raise SystemExit(f"No cover at {cover}")
        if args.cover_text:
            try:
                x, y = (float(v) for v in args.cover_xy.split(","))
            except ValueError:
                raise SystemExit("--cover-xy wants two numbers, e.g. 72,200")
            stamped = tmp / "cover.pdf"
            stamp_cover(cover, stamped, {
                "lines": args.cover_text.split("|"),
                "xy": (x, y), "size": args.cover_size, "colour": args.cover_colour,
            })
            parts.append(stamped)
        else:
            parts.append(cover)
    parts.append(body)
    if args.back:
        back = Path(args.back)
        if not back.exists():
            raise SystemExit(f"No back page at {back}")
        parts.append(back)

    if len(parts) == 1:
        shutil.copy(body, out)
    else:
        merge(parts, out)

    try:
        from pypdf import PdfReader
        pages = len(PdfReader(str(out)).pages)
        print(f"Wrote {out} ({out.stat().st_size:,} bytes, {pages} pages)")
    except Exception:
        print(f"Wrote {out} ({out.stat().st_size:,} bytes)")
    if not args.cover:
        print("No --cover given, so this is the body only.")
    shutil.rmtree(tmp, ignore_errors=True)
    return 0


if __name__ == "__main__":
    raise SystemExit(main())
