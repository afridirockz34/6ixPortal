# Off-page: links, anchors, and the ones to disown

Most of what gets called link building is either unbuyable or worthless. This
file is about the part in between: the links a real business can actually earn,
the words those links use, and the ones already pointing at the site that are
doing harm.

Nothing here invents a number. Backlink and anchor data come from Ahrefs and
are estimates of what it has crawled; Search Console's link report is the
client's own and partial. Say which you used.

---

## 1. Start with a disavow audit, not an outreach plan

Before building anything, look at what is already there. Pull the referring
domains sorted by traffic and by rating, and read the top 50 by hand.

What you are looking for:

| Signal | What it usually is |
|---|---|
| Directory with no editorial standard — "submit your site" | A link farm. Common in old agency work. |
| Hundreds of outbound links, unrelated industries | A PBN or a hacked site |
| Article sites with spun text (`articleted`, `sooperarticles`) | Paid placement at scale |
| Anything adult, gambling or crypto on a professional-services site | Either a hack or a negative-SEO attempt |
| Foreign-language sites with no plausible reason to link | Scraped content |

**A spam link is not a link to brag about.** This is worth stating because the
failure mode is specific and embarrassing: an automated tool will happily list
a client's junk backlinks under a heading like "your highest value links",
because it sorts by domain rating and a hijacked domain can have a high one.
Never put a raw backlink list in a client document without reading it. If a
report would show `bitcoinmix.biz` as an asset, the report is wrong and the
client's own SEO-literate contact will notice.

**Disavowing is not the first move.** Google ignores most low-quality links
without being asked. Disavow when there is a pattern — a cluster of clearly
paid or automated links, usually from previous work — and especially where
there has been a manual action. One ugly link is not a reason.

Record what was disavowed and when. It belongs in the implementation log, and
it is the first thing to check if rankings move oddly afterwards.

---

## 2. Anchor text is the signal you can actually shape

Anchor text is what the linking page says the link is *about*. Most sites have
a distribution that looks like this before anyone touches it:

- the bare domain, `example.ca` — the majority
- the brand name
- `click here`, `website`, `read more`
- almost nothing describing the service

That last line is the finding. A site can have 600 referring domains and still
have told Google nothing about what it does, because none of the anchors
contain the words it wants to rank for.

**What a healthy distribution looks like.** There is no magic ratio and anyone
quoting one precisely is guessing, but the shape is: branded and bare-URL
anchors as the largest share (they are what natural links look like), a real
minority that is descriptive — `criminal defence lawyer in Toronto`, not
`lawyer` — and generic anchors as a small tail.

**How to move it, in order of how safe it is:**

1. **Fix your own internal links first.** They are anchors too, they are
   entirely under your control, and on most sites they are worse than the
   external ones. See `phase9-10-architecture-linking.md`.
2. **Ask, when you are already placing a link.** A directory listing, a
   professional-body profile, a guest piece — the anchor is often a field in a
   form, and nobody has thought about it.
3. **Earn links to deep pages.** A link to a service page tends to use that
   service's words. A link to the homepage tends to use the domain.

**What never happens:** buying anchors, or asking for the exact same phrase
repeatedly. An unnaturally uniform anchor profile is one of the clearest
manipulation signals there is, and it is far worse than the bland profile you
started with.

---

## 3. Where links actually come from for a local professional service

Ranked by how reliably they can be had, not by domain rating:

| Tier | Source | Why it works |
|---|---|---|
| 1 | The profession's own bodies — regulator, bar or trade association, specialist directories | They already list the business or will on request. Highest authority per unit of effort. |
| 2 | Awards and recognition the client already holds | Unclaimed badges and listings are common; this is admin, not outreach. |
| 3 | Local business and chamber listings | Dull, cheap, and they double as citations. |
| 4 | Expert commentary — local press, trade publications, podcasts | Slow and genuinely earned. Needs a named person with real credentials. |
| 5 | Content worth citing — original data, a guide nobody else wrote | Slowest, compounds the longest. |

Tiers 1–3 are a finite list: work through it once and it is done. Treat them as
a checklist with an end, not an ongoing programme, and be honest in the plan
that the ongoing part is tiers 4 and 5 and it is slow.

**Not on the list, on purpose:** paid guest posts, link exchanges, anything
with a price per link. They are against Google's guidelines, they are the first
thing a competent competitor will report, and a client who inherits them from a
previous agency has usually inherited section 1 of this file.

---

## 4. What to report

- Referring domains and their trend, named as an Ahrefs estimate.
- The anchor distribution, as a table, with the descriptive share called out.
- Links earned this period, listed individually. Three real links is a good
  month; a count of thirty is a count of something else.
- Anything disavowed, with the reason.

Do not report "domain authority" or "domain rating" as if it were a Google
metric. It is a third-party score, it moves for reasons unrelated to the
client, and quoting it as progress trains the client to watch the wrong number.
