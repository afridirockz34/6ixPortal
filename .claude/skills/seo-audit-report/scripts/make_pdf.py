#!/usr/bin/env python3
"""
make_pdf.py — the audit report as a PDF, in 6ix's covers.

The HTML renderer produces the body. This prints it and wraps it in the
branded cover and back pages, so what the client receives is one file that
opens the way a report should rather than a web page somebody printed.

    python make_pdf.py report.html -o acme-audit.pdf \\
        --client "Acme Plumbing" --date "10 November 2026"

The covers are drawn here, from the artwork in assets/brand — the polygon
background, the logo and League Gothic — rather than taken from a PDF with
the client's name baked in. That is deliberate: the Canva export carries
[CLIENT COMPANY NAME] and [DATE] as real text in subset fonts, so those
placeholders cannot be edited to another name (the subset holds only the
letters already on the page). Rebuilding from the same artwork sets any
client's name in the right typeface at the right place, and keeps working
when the next client is called something else.

If the design changes, re-export it and pass the new pages with --cover and
--back to use them as they are, or replace the files in assets/brand.
--no-covers gives the body alone.

Needs a headless Chromium to print, pypdf to merge and reportlab to draw.
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


BRAND = Path(__file__).resolve().parent.parent / "assets" / "brand"

# Where each thing sits, as a fraction of the page, measured from 6ix's own
# Canva cover. Fractions rather than points so the layout survives a different
# page size.
LAYOUT = {
    "kicker_y": 0.082,       # "SEO AUDIT"
    "brand_y": 0.114,        # "6IX DEVELOPERS"
    "date_y": 0.192,
    "right_x": 0.895,        # right edge of that right-aligned stack
    "title_x": 0.102,
    "title_size": 0.1422,     # of page height; matched to the Canva cover
    "title_baseline": 0.4857,  # first line
    "title_leading": 0.107,
    "logo_y": 0.828,
    "logo_w": 0.235,
    "tagline_y": 0.934,
}
TAGLINE = "WE COUNT LEADS, NOT CLICKS"
BACK_TITLE = "THANK YOU!"
BACK_ADDRESS = ["6IX DEVELOPERS", "1550 S, GATEWAY RD.",
                "MISSISSAUGA, ON,L4W 5G6", "WWW.6IXDEVELOPERS.COM"]


def _fonts():
    """League Gothic for the title; Helvetica-Bold for the tracked small caps."""
    from reportlab.pdfbase import pdfmetrics
    from reportlab.pdfbase.ttfonts import TTFont
    title_font = "Helvetica-Bold"
    ttf = BRAND / "LeagueGothic.ttf"
    if ttf.exists():
        try:
            pdfmetrics.registerFont(TTFont("LeagueGothic", str(ttf)))
            title_font = "LeagueGothic"
        except Exception:
            pass
    return title_font, "Helvetica-Bold"


def _tracked(c, text, x, y, font, size, tracking, colour, align="left"):
    """
    Draw letter-spaced small caps.

    The brand sets these lines with wide tracking, and reportlab has no
    letter-spacing, so the string is drawn with spaces between characters and
    measured that way for right-alignment.
    """
    from reportlab.pdfbase import pdfmetrics
    spaced = (" " * 0).join(text)
    c.setFont(font, size)
    c.setFillColor(colour)
    total = pdfmetrics.stringWidth(spaced, font, size) + tracking * max(0, len(spaced) - 1)
    if align == "right":
        x -= total
    elif align == "center":
        x -= total / 2
    for ch in spaced:
        c.drawString(x, y, ch)
        x += pdfmetrics.stringWidth(ch, font, size) + tracking
    return total


def _wrap(text, font, size, max_w):
    from reportlab.pdfbase import pdfmetrics
    words, lines, cur = text.split(), [], ""
    for w in words:
        trial = (cur + " " + w).strip()
        if pdfmetrics.stringWidth(trial, font, size) <= max_w or not cur:
            cur = trial
        else:
            lines.append(cur)
            cur = w
    if cur:
        lines.append(cur)
    return lines


def build_cover(out_pdf, client, domain, date, size=(612, 792), title_size=None):
    """6ix's cover, with this client's name and date set into it."""
    from reportlab.pdfgen import canvas as rl_canvas
    from reportlab.lib.colors import HexColor, white
    from reportlab.lib.utils import ImageReader

    w, h = size
    L = LAYOUT
    title_f, small_f = _fonts()
    c = rl_canvas.Canvas(str(out_pdf), pagesize=size)

    bg = BRAND / "cover-background.png"
    if bg.exists():
        c.drawImage(ImageReader(str(bg)), 0, 0, width=w, height=h,
                    preserveAspectRatio=False, mask="auto")
    else:
        c.setFillColor(HexColor("#1a1a1a"))
        c.rect(0, 0, w, h, fill=1, stroke=0)

    rx = w * L["right_x"]
    _tracked(c, "SEO AUDIT", rx, h * (1 - L["kicker_y"]), small_f, 10.5, 3.4, white, "right")
    _tracked(c, "6IX DEVELOPERS", rx, h * (1 - L["brand_y"]), small_f, 13.5, 3.2, white, "right")
    if date:
        _tracked(c, date.upper(), rx, h * (1 - L["date_y"]), small_f, 10.5, 3.4, white, "right")

    # The client's name, as large as it can be without running off the page.
    tx = w * L["title_x"]
    max_w = w - tx * 2
    sz = title_size or h * L["title_size"]
    name = (client or "").upper()
    lines = _wrap(name, title_f, sz, max_w)
    while len(lines) > 3 and sz > 40:
        sz *= 0.9
        lines = _wrap(name, title_f, sz, max_w)
    c.setFillColor(white)
    y = h * (1 - L["title_baseline"])
    for line in lines:
        c.setFont(title_f, sz)
        c.drawString(tx, y, line)
        y -= h * L["title_leading"] * (sz / (h * L["title_size"]))

    logo = BRAND / "logo.png"
    if logo.exists():
        lw = w * L["logo_w"]
        img = ImageReader(str(logo))
        iw, ih = img.getSize()
        lh = lw * ih / iw
        c.drawImage(img, (w - lw) / 2, h * (1 - L["logo_y"]) - lh / 2,
                    width=lw, height=lh, mask="auto")
    _tracked(c, TAGLINE, w / 2, h * (1 - L["tagline_y"]), small_f, 11, 3.2, white, "center")

    c.showPage()
    c.save()


def build_back(out_pdf, size=(612, 792), address=None):
    """The closing page. No client detail on it, so it never goes stale."""
    from reportlab.pdfgen import canvas as rl_canvas
    from reportlab.lib.colors import HexColor, white
    from reportlab.lib.utils import ImageReader

    w, h = size
    title_f, small_f = _fonts()
    c = rl_canvas.Canvas(str(out_pdf), pagesize=size)
    bg = BRAND / "cover-background.png"
    if bg.exists():
        c.drawImage(ImageReader(str(bg)), 0, 0, width=w, height=h,
                    preserveAspectRatio=False, mask="auto")
    else:
        c.setFillColor(HexColor("#1a1a1a"))
        c.rect(0, 0, w, h, fill=1, stroke=0)

    c.setFillColor(white)
    sz = h * 0.1409
    c.setFont(title_f, sz)
    c.drawCentredString(w / 2, h * 0.5382, BACK_TITLE)

    logo = BRAND / "logo.png"
    if logo.exists():
        lw = w * 0.235
        img = ImageReader(str(logo))
        iw, ih = img.getSize()
        lh = lw * ih / iw
        c.drawImage(img, (w - lw) / 2, h * 0.255, width=lw, height=lh, mask="auto")

    y = h * 0.225
    for line in (address or BACK_ADDRESS):
        _tracked(c, line, w / 2, y, small_f, 10.5, 2.6, HexColor("#e8734a"), "center")
        y -= h * 0.0205
    c.showPage()
    c.save()


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
    ap.add_argument("--client", default="", help="Client name for the cover.")
    ap.add_argument("--domain", default="", help="Their domain, if the cover shows one.")
    ap.add_argument("--date", default="", help="The date on the cover, e.g. '10 November 2026'.")
    ap.add_argument("--no-covers", action="store_true",
                    help="Body only, no cover or back page.")
    ap.add_argument("--cover", help="Use this PDF's first page as the cover, as-is.")
    ap.add_argument("--back", help="Use this PDF's first page as the back page, as-is.")
    ap.add_argument("--title-size", type=float,
                    help="Override the cover title size in points.")
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

    # The body's own page size, so the covers match it rather than assuming
    # Letter and leaving a visible step in the middle of the document.
    size = (612, 792)
    try:
        from pypdf import PdfReader
        mb = PdfReader(str(body)).pages[0].mediabox
        size = (float(mb.width), float(mb.height))
    except Exception:
        pass

    parts = []
    if not args.no_covers:
        if args.cover:
            parts.append(Path(args.cover))
        else:
            if not args.client:
                print("No --client given, so the cover will carry no name.",
                      file=sys.stderr)
            cov = tmp / "cover.pdf"
            build_cover(cov, args.client, args.domain, args.date, size=size,
                        title_size=args.title_size)
            parts.append(cov)
    parts.append(body)
    if not args.no_covers:
        if args.back:
            parts.append(Path(args.back))
        else:
            bk = tmp / "back.pdf"
            build_back(bk, size=size)
            parts.append(bk)

    for q in parts:
        if not Path(q).exists():
            raise SystemExit(f"Missing page: {q}")

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
    shutil.rmtree(tmp, ignore_errors=True)
    return 0


if __name__ == "__main__":
    raise SystemExit(main())
