#!/usr/bin/env python3
"""
crawl_onpage.py — the on-page findings, read from the live site.

The on-page section of an audit kept being written from whatever Ahrefs Site
Audit happened to have crawled, which for a prospect is usually nothing: Site
Audit needs a project and a crawl that nobody has set up yet. So the section
arrived as a sentence about best practice, or as a count with no URLs behind
it. A prospect can check their own title tags in thirty seconds, which makes
this the most verifiable section in the report and the worst one to assert.

This reads the site itself, politely, and reports per URL:

  broken links            every internal and external link that 404s or errors,
                          with the pages that link to it
  redirect chains         a link that goes through two or more hops
  page titles             missing, duplicated across pages, over 60 or under 30
                          characters, or identical to the page's own H1
  meta descriptions       missing, duplicated, over 155 or under 70 characters
  H1s                     missing, more than one, duplicated across pages, over
                          70 characters
  canonicals              missing, or pointing somewhere else
  indexability            noindex, and whether that looks deliberate
  images                  missing alt text, and the page each one is on
  depth                   pages more than three clicks from the homepage
  orphans                 pages in the sitemap that nothing links to

Usage:
    python crawl_onpage.py https://example.com -o onpage.json
    python crawl_onpage.py https://example.com --max-pages 500 --markdown out.md
    python crawl_onpage.py https://example.com --into audit.json

Politeness is not optional and not configurable downwards: robots.txt is
honoured, requests are serialized with a delay between them, and the crawl
stops at --max-pages. The User-Agent names the tool and carries no personal
or client details — it goes to somebody else's server.

Nothing here invents a value. A page that could not be fetched is reported as
unfetched with the reason, never as a page with no title.
"""

import argparse
import json
import re
import sys
import time
import urllib.error
import urllib.parse
import urllib.request
import urllib.robotparser
from collections import deque
from html.parser import HTMLParser
from pathlib import Path

UA = "6ixDevelopers-SEO-Audit/1.0 (+site crawl for an agreed SEO audit)"
TIMEOUT = 20
DEFAULT_DELAY = 0.5
DEFAULT_MAX_PAGES = 300
MAX_REDIRECTS = 10

# The thresholds the findings are written against. They are conventions, not
# laws from Google — the report says so — but they are the conventions every
# tool the client might check against uses, so disagreeing with them silently
# is worse than following them.
TITLE_MAX = 60
TITLE_MIN = 30
META_MAX = 155
META_MIN = 70
H1_MAX = 70
MAX_DEPTH = 3

SKIP_EXTENSIONS = (
    ".pdf", ".jpg", ".jpeg", ".png", ".gif", ".webp", ".svg", ".ico",
    ".zip", ".gz", ".mp4", ".mp3", ".avi", ".mov", ".css", ".js",
    ".doc", ".docx", ".xls", ".xlsx", ".ppt", ".pptx",
)


# ---------------------------------------------------------------------------
# Parsing


class PageParser(HTMLParser):
    """Everything the on-page checks need, in one pass over the HTML."""

    def __init__(self):
        super().__init__(convert_charrefs=True)
        self.title = None
        self.meta_description = None
        self.meta_robots = ""
        self.canonical = None
        self.h1s = []
        self.links = []          # (href, rel, is_nofollow)
        self.images = []         # {"src":…, "alt":…}
        self._in_title = False
        self._in_h1 = False
        self._h1_buf = []
        self._title_buf = []
        self._text_buf = []
        self._skip_text = 0      # inside script/style/noscript

    def handle_starttag(self, tag, attrs):
        a = dict(attrs)
        if tag == "title":
            self._in_title = True
        elif tag == "h1":
            self._in_h1 = True
            self._h1_buf = []
        elif tag in ("script", "style", "noscript", "template"):
            self._skip_text += 1
        elif tag == "meta":
            name = (a.get("name") or "").lower()
            if name == "description" and self.meta_description is None:
                self.meta_description = (a.get("content") or "").strip()
            elif name == "robots":
                self.meta_robots = (a.get("content") or "").lower()
        elif tag == "link":
            rels = (a.get("rel") or "").lower().split()
            if "canonical" in rels and self.canonical is None:
                self.canonical = (a.get("href") or "").strip()
        elif tag == "a":
            href = (a.get("href") or "").strip()
            if href:
                rel = (a.get("rel") or "").lower()
                self.links.append((href, rel, "nofollow" in rel.split()))
        elif tag == "img":
            # A missing alt attribute and alt="" are different things: the
            # second is the correct markup for a decorative image, and
            # reporting it as a fault is how an audit loses its credibility
            # with anyone who knows the spec.
            self.images.append({
                "src": (a.get("src") or "").strip(),
                "alt": a.get("alt"),
            })

    def handle_startendtag(self, tag, attrs):
        self.handle_starttag(tag, attrs)

    def handle_endtag(self, tag):
        if tag == "title":
            self._in_title = False
        elif tag == "h1":
            self._in_h1 = False
            text = collapse(" ".join(self._h1_buf))
            if text:
                self.h1s.append(text)
        elif tag in ("script", "style", "noscript", "template"):
            self._skip_text = max(0, self._skip_text - 1)

    def handle_data(self, data):
        if self._in_title:
            self._title_buf.append(data)
        if self._in_h1:
            self._h1_buf.append(data)
        if not self._skip_text:
            self._text_buf.append(data)

    def finish(self):
        t = collapse("".join(self._title_buf))
        self.title = t if t else None
        self.word_count = len(collapse(" ".join(self._text_buf)).split())
        return self


def collapse(s: str) -> str:
    return re.sub(r"\s+", " ", s or "").strip()


# ---------------------------------------------------------------------------
# Fetching


class Fetcher:
    """One request at a time, with a delay, and every redirect recorded."""

    def __init__(self, delay=DEFAULT_DELAY, timeout=TIMEOUT):
        self.delay = delay
        self.timeout = timeout
        self._last = 0.0

    def _wait(self):
        gap = time.monotonic() - self._last
        if gap < self.delay:
            time.sleep(self.delay - gap)
        self._last = time.monotonic()

    def fetch(self, url, method="GET"):
        """
        Returns {status, final_url, chain, body, content_type, error}.

        `chain` is every hop taken, so a redirect chain is a fact we observed
        rather than something inferred from a final URL that differs.
        """
        chain = []
        current = url
        for _ in range(MAX_REDIRECTS):
            self._wait()
            req = urllib.request.Request(current, method=method,
                                         headers={"User-Agent": UA,
                                                  "Accept": "text/html,*/*"})
            opener = urllib.request.build_opener(NoRedirect)
            try:
                with opener.open(req, timeout=self.timeout) as resp:
                    status = resp.status
                    location = resp.headers.get("Location")
                    ctype = (resp.headers.get("Content-Type") or "").lower()
                    if status in (301, 302, 303, 307, 308) and location:
                        nxt = urllib.parse.urljoin(current, location)
                        chain.append({"from": current, "status": status, "to": nxt})
                        current = nxt
                        continue
                    body = b""
                    if method == "GET" and "html" in ctype:
                        body = resp.read(3_000_000)
                    return {"status": status, "final_url": current, "chain": chain,
                            "body": body, "content_type": ctype, "error": None}
            except urllib.error.HTTPError as e:
                return {"status": e.code, "final_url": current, "chain": chain,
                        "body": b"", "content_type": "", "error": None}
            except Exception as e:                       # network, DNS, TLS, timeout
                return {"status": None, "final_url": current, "chain": chain,
                        "body": b"", "content_type": "",
                        "error": f"{type(e).__name__}: {e}"}
        return {"status": None, "final_url": current, "chain": chain, "body": b"",
                "content_type": "", "error": f"more than {MAX_REDIRECTS} redirects"}


class NoRedirect(urllib.request.HTTPRedirectHandler):
    """Redirects are the finding, so they are not followed automatically."""

    def redirect_request(self, req, fp, code, msg, headers, newurl):
        return None

    def http_error_301(self, req, fp, code, msg, headers):
        return fp

    http_error_302 = http_error_303 = http_error_307 = http_error_308 = http_error_301


# ---------------------------------------------------------------------------
# Crawling


def normalize(url: str) -> str:
    """Drop the fragment and a trailing empty query; keep everything else."""
    parts = urllib.parse.urlsplit(url)
    path = parts.path or "/"
    return urllib.parse.urlunsplit((parts.scheme.lower(), parts.netloc.lower(),
                                    path, parts.query, ""))


def same_site(url: str, host: str) -> bool:
    netloc = urllib.parse.urlsplit(url).netloc.lower()
    return netloc == host or netloc == "www." + host or "www." + netloc == host


def crawlable(url: str) -> bool:
    parts = urllib.parse.urlsplit(url)
    if parts.scheme not in ("http", "https"):
        return False
    return not parts.path.lower().endswith(SKIP_EXTENSIONS)


def read_sitemap(fetcher, root) -> list:
    """URLs from the sitemap, so orphans can be found at all."""
    found, seen = [], set()
    queue = deque([urllib.parse.urljoin(root, "/sitemap.xml"),
                   urllib.parse.urljoin(root, "/sitemap_index.xml")])
    while queue and len(found) < 5000:
        sm = queue.popleft()
        if sm in seen:
            continue
        seen.add(sm)
        r = fetcher.fetch(sm)
        if r["status"] != 200:
            continue
        body = r["body"].decode("utf-8", "replace") if r["body"] else ""
        if not body:
            # XML is not text/html, so the body was not read above. Re-read it.
            try:
                req = urllib.request.Request(sm, headers={"User-Agent": UA})
                with urllib.request.urlopen(req, timeout=TIMEOUT) as resp:
                    body = resp.read(5_000_000).decode("utf-8", "replace")
            except Exception:
                continue
        locs = re.findall(r"<loc>\s*([^<\s]+)\s*</loc>", body, re.IGNORECASE)
        if "<sitemapindex" in body.lower():
            queue.extend(locs[:50])
        else:
            found.extend(locs)
    return [normalize(u) for u in found]


def crawl(root_url, max_pages=DEFAULT_MAX_PAGES, delay=DEFAULT_DELAY,
          respect_robots=True, verbose=False):
    root_url = root_url.rstrip("/") + "/"
    host = urllib.parse.urlsplit(root_url).netloc.lower()
    fetcher = Fetcher(delay=delay)

    robots = urllib.robotparser.RobotFileParser()
    robots_note = ""
    if respect_robots:
        robots.set_url(urllib.parse.urljoin(root_url, "/robots.txt"))
        try:
            robots.read()
        except Exception as e:
            robots_note = f"robots.txt could not be read ({type(e).__name__}); crawling as if it allows everything"

    def allowed(u):
        if not respect_robots or robots_note:
            return True
        try:
            return robots.can_fetch(UA, u)
        except Exception:
            return True

    pages = {}            # url -> page record
    link_sources = {}     # target url -> [pages that link to it]
    checked = {}          # url -> fetch result for non-HTML / external targets
    queue = deque([(normalize(root_url), 0)])
    seen = {normalize(root_url)}
    blocked = []

    while queue and len(pages) < max_pages:
        url, depth = queue.popleft()
        if not allowed(url):
            blocked.append(url)
            continue
        if verbose:
            print(f"  [{len(pages) + 1}/{max_pages}] {url}", file=sys.stderr)

        r = fetcher.fetch(url)
        record = {
            "url": url,
            "depth": depth,
            "status": r["status"],
            "redirect_chain": r["chain"],
            "error": r["error"],
            "fetched": r["status"] == 200 and bool(r["body"]),
        }

        if not record["fetched"]:
            pages[url] = record
            continue

        # A URL that redirects is not a page — it is a redirect, and the
        # content belongs to where it landed. Filing it under the requested
        # address made every redirect a second copy of its destination, which
        # then reported itself as a duplicate title, a duplicate meta, a
        # duplicate H1 and a canonical pointing elsewhere. Four confident
        # findings, none of them real, about a page that is fine.
        landed = normalize(r["final_url"])
        if r["chain"]:
            pages[url] = dict(record, fetched=False, redirected_to=landed,
                              status=r["chain"][0]["status"])
            if landed in pages or not same_site(landed, host):
                continue
            seen.add(landed)
            record = dict(record, url=landed, status=200, redirect_chain=[])
            url = landed

        p = PageParser()
        try:
            p.feed(r["body"].decode("utf-8", "replace"))
        except Exception as e:
            record["error"] = f"could not parse: {type(e).__name__}"
            pages[url] = record
            continue
        p.finish()

        record.update({
            "title": p.title,
            "title_length": len(p.title) if p.title else 0,
            "meta_description": p.meta_description or None,
            "meta_description_length": len(p.meta_description) if p.meta_description else 0,
            "meta_robots": p.meta_robots,
            "noindex": "noindex" in p.meta_robots,
            "canonical": urllib.parse.urljoin(url, p.canonical) if p.canonical else None,
            "h1s": p.h1s,
            "word_count": p.word_count,
            "images_missing_alt": [i["src"] for i in p.images if i["alt"] is None],
            "images_total": len(p.images),
        })
        pages[url] = record

        for href, _rel, _nofollow in p.links:
            target = normalize(urllib.parse.urljoin(url, href))
            if not target.startswith(("http://", "https://")):
                continue
            link_sources.setdefault(target, [])
            if url not in link_sources[target]:
                link_sources[target].append(url)
            if same_site(target, host) and crawlable(target) and target not in seen:
                seen.add(target)
                queue.append((target, depth + 1))

    # Every link target that was not crawled as a page still has to be checked
    # once — that is where the broken ones are. HEAD first; some servers will
    # not answer HEAD, so a 405 falls back to GET.
    for target in list(link_sources):
        if target in pages:
            continue
        r = fetcher.fetch(target, method="HEAD")
        if r["status"] in (405, 501) or (r["status"] is None and not r["error"]):
            r = fetcher.fetch(target, method="GET")
        checked[target] = {"status": r["status"], "chain": r["chain"], "error": r["error"]}

    return {
        "root": root_url,
        "host": host,
        "pages": pages,
        "link_sources": link_sources,
        "checked_links": checked,
        "blocked_by_robots": blocked,
        "robots_note": robots_note,
        "sitemap": read_sitemap(fetcher, root_url),
        "crawl_limit_hit": len(pages) >= max_pages and bool(queue),
    }


# ---------------------------------------------------------------------------
# Findings
#
# Every finding is one row with the URL, what is there now, and a count the
# client can verify. A suggested replacement is written where one can be
# written mechanically and honestly — a title that is 8 characters too long
# has an obvious shortening; a missing meta description does not, so the
# suggestion says what it has to contain rather than inventing a sentence in
# a voice we have not been given yet.


def suggest_title(title, brand=None):
    """Shorten an over-long title without inventing words."""
    if not title:
        return ""
    # Most over-length titles are "Long Descriptive Phrase | Brand Name".
    # Dropping the brand is usually enough and never changes the claim.
    for sep in (" | ", " – ", " - ", " — "):
        if sep in title:
            head, _, tail = title.rpartition(sep)
            if len(head) <= TITLE_MAX and len(head) >= TITLE_MIN:
                return head
    cut = title[:TITLE_MAX].rsplit(" ", 1)[0]
    return cut if len(cut) >= TITLE_MIN else ""


def findings(data):
    pages = data["pages"]
    host = data["host"]
    out = {}

    live = {u: p for u, p in pages.items() if p.get("fetched")}

    def group(key, rows):
        if rows:
            out[key] = rows

    # ── Broken links ─────────────────────────────────────────────────────
    broken = []
    for target, result in data["checked_links"].items():
        status = result["status"]
        if status is None or status >= 400:
            broken.append({
                "url": target,
                "status": status if status is not None else result["error"],
                "internal": same_site(target, host),
                "linked_from": data["link_sources"].get(target, [])[:10],
                "link_count": len(data["link_sources"].get(target, [])),
            })
    for url, p in pages.items():
        if p.get("status") and p["status"] >= 400:
            broken.append({
                "url": url, "status": p["status"], "internal": True,
                "linked_from": data["link_sources"].get(url, [])[:10],
                "link_count": len(data["link_sources"].get(url, [])),
            })
    group("broken_links", sorted(broken, key=lambda r: -r["link_count"]))

    # ── Redirect chains ──────────────────────────────────────────────────
    chains = []
    for url, p in pages.items():
        if len(p.get("redirect_chain") or []) > 1:
            chains.append({"url": url, "hops": len(p["redirect_chain"]),
                           "chain": [h["to"] for h in p["redirect_chain"]]})
    for target, result in data["checked_links"].items():
        if len(result.get("chain") or []) > 1:
            chains.append({"url": target, "hops": len(result["chain"]),
                           "chain": [h["to"] for h in result["chain"]]})
    group("redirect_chains", chains)

    # ── Titles ───────────────────────────────────────────────────────────
    by_title = {}
    for url, p in live.items():
        if p.get("title"):
            by_title.setdefault(p["title"].lower(), []).append(url)

    group("title_missing", [{"url": u} for u, p in live.items() if not p.get("title")])
    group("title_duplicate", [
        {"title": pages[urls[0]]["title"], "count": len(urls), "urls": urls}
        for urls in by_title.values() if len(urls) > 1
    ])
    group("title_too_long", [
        {"url": u, "title": p["title"], "length": p["title_length"],
         "suggestion": suggest_title(p["title"])}
        for u, p in live.items() if p.get("title") and p["title_length"] > TITLE_MAX
    ])
    group("title_too_short", [
        {"url": u, "title": p["title"], "length": p["title_length"]}
        for u, p in live.items() if p.get("title") and p["title_length"] < TITLE_MIN
    ])
    # A title identical to the H1 wastes the one line that appears in the
    # search result: the H1 speaks to somebody already on the page, the title
    # has to win the click from a list of ten.
    group("title_same_as_h1", [
        {"url": u, "title": p["title"], "length": p["title_length"]}
        for u, p in live.items()
        if p.get("title") and p.get("h1s")
        and p["title"].strip().lower() == p["h1s"][0].strip().lower()
    ])

    # ── Meta descriptions ────────────────────────────────────────────────
    by_meta = {}
    for url, p in live.items():
        if p.get("meta_description"):
            by_meta.setdefault(p["meta_description"].lower(), []).append(url)

    group("meta_missing", [
        {"url": u, "title": p.get("title")}
        for u, p in live.items() if not p.get("meta_description")
    ])
    group("meta_duplicate", [
        {"meta": pages[urls[0]]["meta_description"], "count": len(urls), "urls": urls}
        for urls in by_meta.values() if len(urls) > 1
    ])
    group("meta_too_long", [
        {"url": u, "meta": p["meta_description"], "length": p["meta_description_length"]}
        for u, p in live.items()
        if p.get("meta_description") and p["meta_description_length"] > META_MAX
    ])
    group("meta_too_short", [
        {"url": u, "meta": p["meta_description"], "length": p["meta_description_length"]}
        for u, p in live.items()
        if p.get("meta_description") and 0 < p["meta_description_length"] < META_MIN
    ])

    # ── H1s ──────────────────────────────────────────────────────────────
    by_h1 = {}
    for url, p in live.items():
        if p.get("h1s"):
            by_h1.setdefault(p["h1s"][0].lower(), []).append(url)

    group("h1_missing", [{"url": u, "title": p.get("title")}
                         for u, p in live.items() if not p.get("h1s")])
    group("h1_multiple", [
        {"url": u, "count": len(p["h1s"]), "h1s": p["h1s"][:4]}
        for u, p in live.items() if len(p.get("h1s") or []) > 1
    ])
    group("h1_duplicate", [
        {"h1": pages[urls[0]]["h1s"][0], "count": len(urls), "urls": urls}
        for urls in by_h1.values() if len(urls) > 1
    ])
    group("h1_too_long", [
        {"url": u, "h1": p["h1s"][0], "length": len(p["h1s"][0])}
        for u, p in live.items() if p.get("h1s") and len(p["h1s"][0]) > H1_MAX
    ])

    # ── Canonicals and indexability ──────────────────────────────────────
    group("canonical_missing", [{"url": u} for u, p in live.items() if not p.get("canonical")])
    group("canonical_elsewhere", [
        {"url": u, "canonical": p["canonical"]}
        for u, p in live.items()
        if p.get("canonical") and normalize(p["canonical"]) != u
    ])
    group("noindex", [{"url": u, "directive": p["meta_robots"]}
                      for u, p in live.items() if p.get("noindex")])

    # ── Images ───────────────────────────────────────────────────────────
    group("images_missing_alt", [
        {"url": u, "count": len(p["images_missing_alt"]),
         "images": p["images_missing_alt"][:10]}
        for u, p in live.items() if p.get("images_missing_alt")
    ])

    # ── Structure ────────────────────────────────────────────────────────
    group("too_deep", [
        {"url": u, "clicks_from_home": p["depth"]}
        for u, p in live.items() if p["depth"] > MAX_DEPTH
    ])
    group("thin", [
        {"url": u, "words": p["word_count"]}
        for u, p in live.items() if p.get("word_count", 0) < 300
    ])

    # Orphans: in the sitemap, so the site means them to be found, and nothing
    # on the site links to them. Only meaningful where the crawl finished —
    # a crawl that hit its limit has unvisited pages whose links were never
    # read, and calling those orphans would be wrong.
    if data["sitemap"] and not data["crawl_limit_hit"]:
        linked = set(data["link_sources"])
        group("orphans", [{"url": u} for u in data["sitemap"]
                          if u not in linked and u != normalize(data["root"])])

    group("unfetched", [
        {"url": u, "status": p.get("status"), "reason": p.get("error")}
        for u, p in pages.items()
        if not p.get("fetched") and not (p.get("status") and p["status"] >= 400)
    ])

    return out


# ---------------------------------------------------------------------------
# Reporting


GROUP_TITLES = {
    "broken_links": ("Broken links", "Every link that returns an error. An internal one wastes a visitor; an external one dates the page."),
    "redirect_chains": ("Redirect chains", "A link that goes through two or more hops before it arrives. Each hop costs time and loses a little authority."),
    "title_missing": ("Pages with no title tag", "The line Google shows in the search result. Without one it writes its own."),
    "title_duplicate": ("Duplicate page titles", "Two pages claiming the same search result. Google picks one, and it may not be the one that should win."),
    "title_too_long": (f"Page titles over {TITLE_MAX} characters", "Cut off in the result, usually mid-phrase."),
    "title_too_short": (f"Page titles under {TITLE_MIN} characters", "Room left unused in the one line that wins the click."),
    "title_same_as_h1": ("Page titles identical to the page heading", "The heading speaks to somebody already reading; the title has to win the click from a list of ten. Using one text for both wastes the second job."),
    "meta_missing": ("Pages with no meta description", "Google writes its own from the page, and what it picks is rarely the sentence that would have persuaded anybody."),
    "meta_duplicate": ("Duplicate meta descriptions", "The same sentence under several results."),
    "meta_too_long": (f"Meta descriptions over {META_MAX} characters", "Truncated in the result."),
    "meta_too_short": (f"Meta descriptions under {META_MIN} characters", "Space left unused."),
    "h1_missing": ("Pages with no H1", "No heading saying what the page is."),
    "h1_multiple": ("Pages with more than one H1", "Two headings each claiming to be the page's subject."),
    "h1_duplicate": ("Duplicate H1s", "Several pages with the same heading."),
    "h1_too_long": (f"H1s over {H1_MAX} characters", "A heading that reads as a paragraph."),
    "canonical_missing": ("Pages with no canonical tag", "Nothing telling Google which address is the real one for this page."),
    "canonical_elsewhere": ("Pages whose canonical points elsewhere", "The page is telling Google to index a different address. Sometimes deliberate — worth confirming each one."),
    "noindex": ("Pages marked noindex", "Deliberately kept out of Google. Worth confirming that every one of these is meant to be."),
    "images_missing_alt": ("Images with no alt attribute", "Invisible to a screen reader and to image search. An empty alt is correct for a decorative image and is not counted here."),
    "too_deep": (f"Pages more than {MAX_DEPTH} clicks from the homepage", "Harder for Google to reach and for a visitor to find."),
    "thin": ("Pages under 300 words", "Too little to answer a search with."),
    "orphans": ("Pages in the sitemap that nothing links to", "The site says these matter and then gives no way to reach them."),
    "unfetched": ("Pages that could not be read", "Reported rather than skipped, because a page we could not read is not a page with no problems."),
}

ROW_FIELDS = {
    "broken_links": [("url", "Address"), ("status", "Status"), ("link_count", "Links to it"), ("linked_from", "Linked from")],
    "redirect_chains": [("url", "Address"), ("hops", "Hops"), ("chain", "Goes through")],
    "title_missing": [("url", "Address")],
    "title_duplicate": [("title", "Title"), ("count", "Pages"), ("urls", "Addresses")],
    "title_too_long": [("url", "Address"), ("title", "Existing title"), ("length", "Length"), ("suggestion", "Suggestion")],
    "title_too_short": [("url", "Address"), ("title", "Existing title"), ("length", "Length")],
    "title_same_as_h1": [("url", "Address"), ("title", "Existing title"), ("length", "Length")],
    "meta_missing": [("url", "Address"), ("title", "Page title")],
    "meta_duplicate": [("meta", "Meta description"), ("count", "Pages"), ("urls", "Addresses")],
    "meta_too_long": [("url", "Address"), ("meta", "Existing meta description"), ("length", "Length")],
    "meta_too_short": [("url", "Address"), ("meta", "Existing meta description"), ("length", "Length")],
    "h1_missing": [("url", "Address"), ("title", "Page title")],
    "h1_multiple": [("url", "Address"), ("count", "H1s"), ("h1s", "They are")],
    "h1_duplicate": [("h1", "H1"), ("count", "Pages"), ("urls", "Addresses")],
    "h1_too_long": [("url", "Address"), ("h1", "Existing H1"), ("length", "Length")],
    "canonical_missing": [("url", "Address")],
    "canonical_elsewhere": [("url", "Address"), ("canonical", "Points to")],
    "noindex": [("url", "Address"), ("directive", "Directive")],
    "images_missing_alt": [("url", "Address"), ("count", "Images"), ("images", "Which")],
    "too_deep": [("url", "Address"), ("clicks_from_home", "Clicks from home")],
    "thin": [("url", "Address"), ("words", "Words")],
    "orphans": [("url", "Address")],
    "unfetched": [("url", "Address"), ("status", "Status"), ("reason", "Reason")],
}


def cell(value):
    if isinstance(value, list):
        if not value:
            return "—"
        shown = " · ".join(str(v) for v in value[:3])
        return shown + (f" … and {len(value) - 3} more" if len(value) > 3 else "")
    if value is None or value == "":
        return "—"
    return str(value).replace("|", "\\|")


def to_markdown(data, result, limit=25):
    host = data["host"]
    crawled = sum(1 for p in data["pages"].values() if p.get("fetched"))
    lines = [f"# On-page findings — {host}", ""]
    lines.append(f"{crawled} pages read"
                 + (f", stopped at the crawl limit of {len(data['pages'])}" if data["crawl_limit_hit"] else "")
                 + f", {len(data['checked_links'])} further link targets checked.")
    if data["robots_note"]:
        lines.append("")
        lines.append(f"**Note:** {data['robots_note']}")
    if data["blocked_by_robots"]:
        lines.append("")
        lines.append(f"{len(data['blocked_by_robots'])} URLs were not read because robots.txt disallows them.")
    lines.append("")

    lines.append("| Issue | Pages affected |")
    lines.append("|---|---|")
    for key, rows in result.items():
        title = GROUP_TITLES.get(key, (key, ""))[0]
        lines.append(f"| {title} | {len(rows)} |")
    if not result:
        lines.append("| Nothing found in any checked category | 0 |")
    lines.append("")

    for key, rows in result.items():
        title, why = GROUP_TITLES.get(key, (key, ""))
        lines.append(f"## {title} — {len(rows)}")
        lines.append("")
        if why:
            lines.append(why)
            lines.append("")
        fields = ROW_FIELDS.get(key) or [(k, k) for k in rows[0]]
        lines.append("| " + " | ".join(label for _, label in fields) + " |")
        lines.append("|" + "---|" * len(fields))
        for row in rows[:limit]:
            lines.append("| " + " | ".join(cell(row.get(f)) for f, _ in fields) + " |")
        if len(rows) > limit:
            lines.append("")
            lines.append(f"*… and {len(rows) - limit} more. The full list is in the JSON output.*")
        lines.append("")
    return "\n".join(lines)


def main():
    ap = argparse.ArgumentParser(description=__doc__,
                                 formatter_class=argparse.RawDescriptionHelpFormatter)
    ap.add_argument("url", help="The site to read, e.g. https://example.com")
    ap.add_argument("-o", "--out", help="Write the findings as JSON here")
    ap.add_argument("--markdown", help="Write the grouped tables as markdown here")
    ap.add_argument("--into", help="Write the findings into an audit JSON file's `onpage` block")
    ap.add_argument("--max-pages", type=int, default=DEFAULT_MAX_PAGES)
    ap.add_argument("--delay", type=float, default=DEFAULT_DELAY,
                    help="Seconds between requests. The floor is 0.2; this goes to somebody else's server.")
    ap.add_argument("--rows", type=int, default=25, help="Rows per table in the markdown")
    ap.add_argument("--ignore-robots", action="store_true",
                    help="Only with the site owner's written agreement, for a site whose robots.txt blocks everything")
    ap.add_argument("-v", "--verbose", action="store_true")
    args = ap.parse_args()

    delay = max(0.2, args.delay)
    print(f"Reading {args.url} — up to {args.max_pages} pages, {delay}s apart.", file=sys.stderr)
    data = crawl(args.url, max_pages=args.max_pages, delay=delay,
                 respect_robots=not args.ignore_robots, verbose=args.verbose)
    result = findings(data)

    crawled = sum(1 for p in data["pages"].values() if p.get("fetched"))
    print(f"\n{crawled} pages read. Findings:", file=sys.stderr)
    for key, rows in result.items():
        print(f"  {GROUP_TITLES.get(key, (key, ''))[0]}: {len(rows)}", file=sys.stderr)
    if not result:
        print("  nothing in any checked category", file=sys.stderr)

    payload = {
        "root": data["root"],
        "host": data["host"],
        "pages_read": crawled,
        "crawl_limit_hit": data["crawl_limit_hit"],
        "robots_note": data["robots_note"],
        "blocked_by_robots": len(data["blocked_by_robots"]),
        "links_checked": len(data["checked_links"]),
        "thresholds": {"title_max": TITLE_MAX, "title_min": TITLE_MIN,
                       "meta_max": META_MAX, "meta_min": META_MIN,
                       "h1_max": H1_MAX, "max_depth": MAX_DEPTH},
        "findings": result,
    }

    if args.out:
        Path(args.out).write_text(json.dumps(payload, indent=2), encoding="utf-8")
        print(f"\nWrote {args.out}", file=sys.stderr)
    if args.markdown:
        Path(args.markdown).write_text(to_markdown(data, result, limit=args.rows),
                                       encoding="utf-8")
        print(f"Wrote {args.markdown}", file=sys.stderr)
    if args.into:
        p = Path(args.into)
        audit = json.loads(p.read_text(encoding="utf-8")) if p.exists() else {}
        audit["onpage"] = payload
        p.write_text(json.dumps(audit, indent=2), encoding="utf-8")
        print(f"Wrote the onpage block into {args.into}", file=sys.stderr)
    if not (args.out or args.markdown or args.into):
        print(json.dumps(payload, indent=2))
    return 0


if __name__ == "__main__":
    sys.exit(main())
