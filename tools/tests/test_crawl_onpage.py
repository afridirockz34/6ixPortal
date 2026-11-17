#!/usr/bin/env python3
"""
The on-page crawler, against a site built to contain every fault it looks for.

Why a real server rather than parsing fixture strings: the findings that
matter most — broken links, redirect chains, orphans — are not properties of
a page, they are properties of how pages point at each other. A parser test
would pass with the link graph completely wrong.

So this stands up a throwaway HTTP server on localhost, serves a site with
one known instance of each fault, crawls it, and checks that each one is
found exactly once and that the clean pages are not reported.

Two failure directions, and the second is the one that gets a tool switched
off: a check that misses a real fault, and a check that invents one. Both are
asserted. `alt=""` on a decorative image is correct markup and must not be
reported; a self-referencing canonical must not read as pointing elsewhere;
a single redirect is not a chain.

    python3 tools/tests/test_crawl_onpage.py
"""

import http.server
import importlib.util
import socket
import sys
import threading
from pathlib import Path

ROOT = Path(__file__).resolve().parents[2]
SCRIPT = ROOT / ".claude/skills/seo-audit-report/scripts/crawl_onpage.py"

spec = importlib.util.spec_from_file_location("crawl_onpage", SCRIPT)
crawl_onpage = importlib.util.module_from_spec(spec)
spec.loader.exec_module(crawl_onpage)

passed = failed = 0


def check(name, ok, detail=""):
    global passed, failed
    if ok:
        passed += 1
        print(f"PASS  {name}")
    else:
        failed += 1
        print(f"FAIL  {name}" + (f"\n        {detail}" if detail else ""))


# ---------------------------------------------------------------------------
# The fixture site. One known instance of each fault, and clean pages that
# must stay out of every list.

def page(title=None, meta=None, h1s=(), body="", canonical=None, robots=None,
         extra_head=""):
    head = []
    if title is not None:
        head.append(f"<title>{title}</title>")
    if meta is GOOD_META:
        meta = auto_meta(title)
    if meta is not None:
        head.append(f'<meta name="description" content="{meta}">')
    if robots is not None:
        head.append(f'<meta name="robots" content="{robots}">')
    if canonical is not None:
        head.append(f'<link rel="canonical" href="{canonical}">')
    head.append(extra_head)
    headings = "".join(f"<h1>{h}</h1>" for h in h1s)
    filler = " ".join(["word"] * 400)
    return ("<!doctype html><html><head>" + "".join(head) +
            f"</head><body>{headings}{body}<p>{filler}</p></body></html>").encode()


# A sentinel meaning "give this page its own sensible meta description".
#
# They started out as one shared string, which made all seventeen pages
# duplicates of each other and buried the one deliberate duplicate pair under
# sixteen accidental ones. A fixture has to be as clean as the site it is
# pretending to be, or the assertions end up testing the fixture.
GOOD_META = object()


def auto_meta(title):
    """Distinct per page, and inside the 70-155 window on every title here."""
    base = (title or "this page").split("|")[0].strip()[:40]
    return (f"{base} - what this means for your case in Ontario, what the court "
            f"looks at, and what to do next.")


PAGES = {
    "/": page(
        title="Criminal Defence Lawyer Toronto | Example Firm",
        meta=GOOD_META,
        h1s=["Criminal Defence Lawyer in Toronto"],
        canonical="http://HOST/",
        body="""
            <a href="/long-title/">long title</a>
            <a href="/short/">short</a>
            <a href="/same-as-h1/">same as h1</a>
            <a href="/no-meta/">no meta</a>
            <a href="/meta-long/">meta long</a>
            <a href="/dupe-a/">dupe a</a>
            <a href="/dupe-b/">dupe b</a>
            <a href="/two-h1/">two h1</a>
            <a href="/no-h1/">no h1</a>
            <a href="/noindex/">noindex</a>
            <a href="/alt/">alt</a>
            <a href="/canon-elsewhere/">canonical elsewhere</a>
            <a href="/thin/">thin</a>
            <a href="/chain-start/">chain</a>
            <a href="/one-hop/">one hop</a>
            <a href="/gone/">gone</a>
            <a href="/depth/1/">deep</a>
            <a href="/clean/">clean</a>
        """,
    ),
    # Clean control page. It must appear in no list at all.
    "/clean/": page(
        title="Bail Hearing Lawyer in Toronto | Example Firm",
        meta=GOOD_META,
        h1s=["How a Bail Hearing Works in Ontario"],
        canonical="http://HOST/clean/",
        body='<img src="/photo.png" alt="A courtroom">'
             '<img src="/divider.png" alt="">',
    ),
    "/long-title/": page(
        title="An Extremely Long Page Title That Runs Well Past Sixty Characters | Example Firm",
        meta=GOOD_META, h1s=["Long title page"], canonical="http://HOST/long-title/"),
    "/short/": page(
        title="Bail", meta=GOOD_META, h1s=["Short title page"],
        canonical="http://HOST/short/"),
    "/same-as-h1/": page(
        title="Assault Causing Bodily Harm in Ontario",
        meta=GOOD_META, h1s=["Assault Causing Bodily Harm in Ontario"],
        canonical="http://HOST/same-as-h1/"),
    "/no-meta/": page(
        title="A Page With No Meta Description At All Here",
        h1s=["No meta"], canonical="http://HOST/no-meta/"),
    "/meta-long/": page(
        title="A Page Whose Meta Description Runs Long Indeed",
        meta=("This meta description is deliberately written to run past the hundred "
              "and fifty-five character ceiling so that the length check has something "
              "real to catch when it reads the page."),
        h1s=["Meta long"], canonical="http://HOST/meta-long/"),
    "/dupe-a/": page(
        title="Duplicated Title Across Two Separate Pages",
        meta="A duplicated meta description that is used on two pages at once and is long enough to pass the floor.",
        h1s=["Duplicated Heading Across Two Pages"], canonical="http://HOST/dupe-a/"),
    "/dupe-b/": page(
        title="Duplicated Title Across Two Separate Pages",
        meta="A duplicated meta description that is used on two pages at once and is long enough to pass the floor.",
        h1s=["Duplicated Heading Across Two Pages"], canonical="http://HOST/dupe-b/"),
    "/two-h1/": page(
        title="A Page Carrying Two Separate H1 Headings",
        meta=GOOD_META, h1s=["First Heading Here", "Second Heading Here"],
        canonical="http://HOST/two-h1/"),
    "/no-h1/": page(
        title="A Page With No H1 Heading On It Anywhere",
        meta=GOOD_META, canonical="http://HOST/no-h1/"),
    "/noindex/": page(
        title="A Page Deliberately Kept Out Of The Index",
        meta=GOOD_META, h1s=["Noindex page"], robots="noindex,follow",
        canonical="http://HOST/noindex/"),
    "/alt/": page(
        title="A Page Whose Images Have No Alt Attribute",
        meta=GOOD_META, h1s=["Images without alt"],
        canonical="http://HOST/alt/",
        body='<img src="/a.png"><img src="/b.png"><img src="/ok.png" alt="described">'),
    "/canon-elsewhere/": page(
        title="A Page Whose Canonical Points Somewhere Else",
        meta=GOOD_META, h1s=["Canonical elsewhere"],
        canonical="http://HOST/clean/"),
    "/thin/": ("<!doctype html><html><head>"
               "<title>A Very Thin Page With Almost No Words On It</title>"
               f'<meta name="description" content="{auto_meta(chr(65))}">'
               '<link rel="canonical" href="http://HOST/thin/">'
               "</head><body><h1>Thin page</h1><p>Only a few words here.</p>"
               "</body></html>").encode(),
    "/depth/1/": page(title="Depth One Of A Four Level Deep Branch",
                      meta=GOOD_META, h1s=["Depth one"],
                      canonical="http://HOST/depth/1/",
                      body='<a href="/depth/2/">next</a>'),
    "/depth/2/": page(title="Depth Two Of A Four Level Deep Branch",
                      meta=GOOD_META, h1s=["Depth two"],
                      canonical="http://HOST/depth/2/",
                      body='<a href="/depth/3/">next</a>'),
    "/depth/3/": page(title="Depth Three Of A Four Level Deep Branch",
                      meta=GOOD_META, h1s=["Depth three"],
                      canonical="http://HOST/depth/3/",
                      body='<a href="/depth/4/">next</a>'),
    "/depth/4/": page(title="Depth Four Of A Four Level Deep Branch",
                      meta=GOOD_META, h1s=["Depth four"],
                      canonical="http://HOST/depth/4/"),
    # Reached by nothing, but listed in the sitemap.
    "/orphan/": page(title="An Orphan Page Nothing On The Site Links To",
                     meta=GOOD_META, h1s=["Orphan"],
                     canonical="http://HOST/orphan/"),
}

REDIRECTS = {
    "/chain-start/": "/chain-middle/",     # two hops — a chain
    "/chain-middle/": "/clean/",
    "/one-hop/": "/clean/",                # one hop — not a chain
}

SITEMAP = """<?xml version="1.0" encoding="UTF-8"?>
<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">
  <url><loc>http://HOST/</loc></url>
  <url><loc>http://HOST/clean/</loc></url>
  <url><loc>http://HOST/orphan/</loc></url>
</urlset>"""

ROBOTS = "User-agent: *\nDisallow: /private/\n"


class Handler(http.server.BaseHTTPRequestHandler):
    host = ""

    def log_message(self, *a):
        pass

    def _send(self, code, body=b"", ctype="text/html; charset=utf-8", headers=None):
        self.send_response(code)
        self.send_header("Content-Type", ctype)
        self.send_header("Content-Length", str(len(body)))
        for k, v in (headers or {}).items():
            self.send_header(k, v)
        self.end_headers()
        if self.command != "HEAD":
            self.wfile.write(body)

    def do_HEAD(self):
        self.do_GET()

    def do_GET(self):
        path = self.path
        if path == "/robots.txt":
            return self._send(200, ROBOTS.encode(), "text/plain")
        if path == "/sitemap.xml":
            return self._send(200, SITEMAP.replace("HOST", self.host).encode(),
                              "application/xml")
        if path in REDIRECTS:
            return self._send(301, b"", headers={"Location": REDIRECTS[path]})
        if path in PAGES:
            return self._send(200, PAGES[path].replace(b"HOST", self.host.encode()))
        if path.endswith(".png"):
            return self._send(200, b"\x89PNG", "image/png")
        return self._send(404, b"<html><body>Not found</body></html>")


def free_port():
    s = socket.socket()
    s.bind(("127.0.0.1", 0))
    port = s.getsockname()[1]
    s.close()
    return port


PORT = free_port()
HOST = f"127.0.0.1:{PORT}"
Handler.host = HOST
server = http.server.ThreadingHTTPServer(("127.0.0.1", PORT), Handler)
threading.Thread(target=server.serve_forever, daemon=True).start()

BASE = f"http://{HOST}"
data = crawl_onpage.crawl(BASE, max_pages=100, delay=0.0)
f = crawl_onpage.findings(data)
server.shutdown()


def urls(key):
    return {r.get("url") for r in f.get(key, [])}


def names(key):
    return sorted(u.replace(BASE, "") or "/" for u in urls(key))


# ── The crawl itself ─────────────────────────────────────────────────────
read = sum(1 for p in data["pages"].values() if p.get("fetched"))
check("every linked page is read", read >= len(PAGES) - 1, f"read {read}")
check("the crawl did not hit its limit", not data["crawl_limit_hit"])
check("robots.txt was read", data["robots_note"] == "", data["robots_note"])

# ── Titles ───────────────────────────────────────────────────────────────
check("an over-long title is found", names("title_too_long") == ["/long-title/"],
      names("title_too_long"))
check("and a suggestion is offered that fits",
      0 < len(f["title_too_long"][0]["suggestion"]) <= crawl_onpage.TITLE_MAX,
      repr(f["title_too_long"][0]["suggestion"]))
check("the suggestion keeps the page's own words rather than inventing any",
      f["title_too_long"][0]["suggestion"] in f["title_too_long"][0]["title"])
check("a too-short title is found", names("title_too_short") == ["/short/"],
      names("title_too_short"))
check("a title identical to the H1 is found",
      names("title_same_as_h1") == ["/same-as-h1/"], names("title_same_as_h1"))
check("duplicate titles are grouped, not listed twice",
      len(f.get("title_duplicate", [])) == 1
      and f["title_duplicate"][0]["count"] == 2, f.get("title_duplicate"))
check("no page is reported as missing a title", "title_missing" not in f,
      f.get("title_missing"))

# ── Meta descriptions ────────────────────────────────────────────────────
check("a missing meta description is found", names("meta_missing") == ["/no-meta/"],
      names("meta_missing"))
check("an over-long meta description is found", names("meta_too_long") == ["/meta-long/"],
      names("meta_too_long"))
check("duplicate meta descriptions are grouped",
      len(f.get("meta_duplicate", [])) == 1 and f["meta_duplicate"][0]["count"] == 2,
      f.get("meta_duplicate"))
check("a meta description of a sensible length is not flagged either way",
      f"{BASE}/clean/" not in urls("meta_too_long") | urls("meta_too_short"))

# ── H1s ──────────────────────────────────────────────────────────────────
check("a missing H1 is found", names("h1_missing") == ["/no-h1/"], names("h1_missing"))
check("two H1s on one page is found", names("h1_multiple") == ["/two-h1/"],
      names("h1_multiple"))
check("and it says how many there were", f["h1_multiple"][0]["count"] == 2)
check("duplicate H1s across pages are grouped",
      len(f.get("h1_duplicate", [])) == 1, f.get("h1_duplicate"))

# ── Links ────────────────────────────────────────────────────────────────
broken = {r["url"].replace(BASE, "") for r in f.get("broken_links", [])}
check("a 404 is found", "/gone/" in broken, sorted(broken))
check("and it names the page that links to it",
      any(r["url"].endswith("/gone/") and r["linked_from"] for r in f["broken_links"]))
check("no working link is called broken", broken == {"/gone/"}, sorted(broken))

chains = {r["url"].replace(BASE, "") for r in f.get("redirect_chains", [])}
check("a two-hop redirect is reported as a chain", "/chain-start/" in chains,
      sorted(chains))
check("a single redirect is not", "/one-hop/" not in chains, sorted(chains))

# ── Canonicals, indexability, images ─────────────────────────────────────
check("a canonical pointing elsewhere is found",
      names("canonical_elsewhere") == ["/canon-elsewhere/"],
      names("canonical_elsewhere"))
check("a self-referencing canonical is not", "canonical_missing" not in f
      or f"{BASE}/clean/" not in urls("canonical_missing"))
check("a noindex page is found", names("noindex") == ["/noindex/"], names("noindex"))

check("images with no alt attribute are found", names("images_missing_alt") == ["/alt/"],
      names("images_missing_alt"))
check("and counted per page", f["images_missing_alt"][0]["count"] == 2,
      f["images_missing_alt"][0])
check('alt="" on a decorative image is correct markup and is not reported',
      f"{BASE}/clean/" not in urls("images_missing_alt"),
      'the clean page has one described image and one alt=""')

# ── Structure ────────────────────────────────────────────────────────────
check("a page four clicks from home is reported as too deep",
      names("too_deep") == ["/depth/4/"], names("too_deep"))
check("a thin page is found", names("thin") == ["/thin/"], names("thin"))
check("an orphan in the sitemap is found", names("orphans") == ["/orphan/"],
      names("orphans"))
check("a page that is linked to is never called an orphan",
      f"{BASE}/clean/" not in urls("orphans"))

# ── The clean page appears nowhere ───────────────────────────────────────
appearances = [k for k, rows in f.items()
               if any(r.get("url") == f"{BASE}/clean/" for r in rows)
               or any(f"{BASE}/clean/" in (r.get("urls") or []) for r in rows)]
check("a page with nothing wrong with it appears in no finding", not appearances,
      f"it appears under: {appearances}")

# ── Orphan detection is suppressed on a truncated crawl ──────────────────
# A crawl that stopped early has pages whose links were never read, so
# anything not yet linked-to would be reported as an orphan. That would be
# the worst kind of wrong: a long, confident, entirely invented list.
truncated = dict(data, crawl_limit_hit=True)
check("a truncated crawl reports no orphans at all",
      "orphans" not in crawl_onpage.findings(truncated),
      "unread pages would be reported as orphans")

# ── The markdown ─────────────────────────────────────────────────────────
md = crawl_onpage.to_markdown(data, f, limit=5)
check("the markdown leads with a count per issue", "| Issue | Pages affected |" in md)
check("every finding group gets a section",
      all(crawl_onpage.GROUP_TITLES[k][0] in md for k in f),
      [k for k in f if crawl_onpage.GROUP_TITLES[k][0] not in md])
check("each section says why the issue matters",
      "wins the click" in md and "screen reader" in md)
check("a long table is truncated with a count, not silently cut",
      "and 3 more" in md or "more. The full list" in md or
      all(len(rows) <= 5 for rows in f.values()))
check("a pipe in a title cannot break the table",
      "\\|" in md, "the homepage title contains a pipe")

# ── Not inventing anything ───────────────────────────────────────────────
check("a page that could not be read is reported, not treated as faultless",
      "unfetched" not in f or all(r.get("reason") or r.get("status")
                                  for r in f["unfetched"]))
check("the thresholds the findings are written against are stated",
      crawl_onpage.TITLE_MAX == 60 and crawl_onpage.META_MAX == 155)

# ── Politeness is not configurable downwards ─────────────────────────────
src = SCRIPT.read_text(encoding="utf-8")
check("the User-Agent names the tool and carries no client details",
      "6ixDevelopers-SEO-Audit" in src and "@" not in crawl_onpage.UA)
check("robots.txt is honoured by default",
      "--ignore-robots" in src and "respect_robots=not args.ignore_robots" in src)
check("and overriding it is documented as needing the owner's agreement",
      "written agreement" in src)
check("requests are serialized with a delay", "self._wait()" in src)
check("the delay has a floor", "max(0.2, args.delay)" in src)

print()
print(f"Passed: {passed}  Failed: {failed}")
sys.exit(1 if failed else 0)
