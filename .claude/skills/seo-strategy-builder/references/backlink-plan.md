# The backlink plan

`seo-strategy-builder/references/off-page.md` is the judgement: which links are
worth having, why a disavow is rarely the first move, what an anchor profile is
telling you. Read it first.

This file is the **deliverable**. Off-page work keeps falling out of the
strategy because it is the one part with no page to point at — there is no URL
to put in a table, so it survives as a paragraph saying links matter, and then
nobody builds any. A client who is not confident about backlinks cannot act on
a paragraph.

So the off-page section ships as five named artifacts, the same way the content
section ships as pages. Every one of them is a table with rows somebody can
work through.

---

## What ships

| # | Artifact | What it is | Where it goes |
|---|---|---|---|
| 1 | **Current profile** | What is pointing at the site today, and what that is saying about it | Strategy document |
| 2 | **Disavow shortlist** | The referring domains to disown, each with a reason | Strategy document, plus a plain-text disavow file in the zip |
| 3 | **Prospect list** | Named, reachable link targets in tiers, with the contact route for each | Its own sheet in the zip |
| 4 | **Anchor text plan** | For every destination page, the anchor variations to use, and the one never to repeat | Strategy document |
| 5 | **The twelve-month schedule** | Which tier is worked in which month, and how many links that is | Strategy document + the task board |

Artifact 3 is the one that makes the difference. A tier is a category; a
prospect list is a hundred rows with names, URLs and a next action, and it is
what turns "build authority" into work somebody can start on a Tuesday.

---

## 1. Current profile

Four numbers and one sentence each. Every one is an Ahrefs estimate of what it
has crawled — say so, in the table, not in a footnote.

| Reading | Example | What it means for us |
|---|---|---|
| Referring domains | 612 (Ahrefs, 9 Nov 2026) | How many separate sites link here at all |
| Dofollow share | 18% | The share that passes authority. Below about a third is the finding, not a detail |
| Top anchor | `nrlawyers.com` — 41% of all anchors | The site has told Google its name and almost nothing about what it does |
| Descriptive anchors | 4% contain a service word | This is the gap the plan closes |

Then one paragraph, in plain words, saying what those four lines add up to. For
most clients it is some version of: *there are plenty of links, they mostly do
not pass authority, and almost none of them say what the business does.* That
sentence is worth more to the client than the table above it.

**Compare it to someone.** A number on its own is not a finding. Put the same
four readings for the two closest competitors beside it, from the same pull on
the same day, so the client can see whether 612 is a lot.

---

## 2. Disavow shortlist

One row per domain, and **a reason written in words, not a score**:

| Domain | Links | Why it is on this list |
|---|---|---|
| `pirateddirectory.org` | 14 | Paid directory, no editorial review, submits any site |
| `gerolpercasd.blogspot.com` | 1 | Scraped blog, 14,000 outbound links, unrelated topics |

Rules that do not bend:

- **Read every row by hand before it goes in a document.** A tool sorting by
  domain rating will file a hijacked domain under "your best links". That
  mistake is visible to anyone on the client's side who knows the field.
- **A shortlist is not a disavow.** Google ignores most junk without being
  asked. Disavow on a pattern — a cluster from one previous campaign, or a
  manual action — not on one ugly row. Say in the document which it is.
- **Record the date and keep the file.** If rankings move afterwards, the
  disavow is the first thing to check, and that is impossible without a dated
  record of exactly what went in.

---

## 3. Prospect list

The sheet, one row per prospect:

| Tier | Source | URL | Why they would list us | Route in | Status | Month |
|---|---|---|---|---|---|---|
| 1 | Law Society of Ontario — specialist directory | lso.ca/… | Already a certified specialist; the listing exists and is incomplete | Member portal, update profile | Not started | 1 |
| 1 | Ontario Bar Association | oba.org/… | Firm is a member | Membership admin | Not started | 1 |
| 2 | Canadian Lawyer Top 10 Criminal Boutiques | … | Won in 2024; badge page not claimed | Editorial contact | Not started | 2 |
| 4 | Toronto Star — legal commentary | … | Partner is quotable on bail reform | Pitch via newsroom tips | Not started | 5 |

Five tiers, ordered by how reliably a link can actually be had — the table in
`off-page.md` §3 defines them. Two things about how this list is built:

- **Tiers 1 to 3 are finite.** Regulator, professional bodies, specialist
  directories, awards the client already holds, chambers and local business
  listings. Work through the list once and it is done. Plan them as a
  checklist with an end, not as an ongoing programme, and say so.
- **Tiers 4 and 5 are slow and they are the real work.** Expert commentary and
  something worth citing. Three real links in a month is a good month. Any
  plan promising thirty is describing a different activity.

**Where the names come from.** Competitors' referring domains are the richest
source — pull them for the two or three closest rivals and read what links to
them that does not link to the client. A gap analysis is just that list,
filtered to the sources a real business could plausibly approach.

---

## 4. Anchor text plan

Per destination page, not per link. The page is what the anchor is supposed to
describe, so the table is organised the way the work is done:

| Destination page | Anchor variations (rotate; never the same twice running) | Never |
|---|---|---|
| `/sexual-assault-lawyer-toronto/` | sexual assault lawyer in Toronto · Toronto sexual assault defence · defence lawyer for sexual assault charges · the firm's name | the exact phrase on every link |
| `/bail-hearing-lawyer-toronto/` | bail hearing lawyer Toronto · bail lawyer in Toronto · help with a bail hearing · the firm's name | — |

The shape to aim for, stated as a shape because anybody quoting an exact ratio
is guessing: branded and bare-URL anchors stay the largest share, because that
is what links a business did not ask for look like; a real minority becomes
descriptive; generic anchors (`click here`, `website`) are a small tail.

Order of safety, and it is also the order of effort:

1. **Internal links first.** They are anchors, they are entirely ours, and on
   most sites they are worse than the external ones.
   `phase9-10-architecture-linking.md` has the rules.
2. **Ask when you are already placing a link.** On a directory listing or a
   professional profile the anchor is a form field nobody thought about.
3. **Earn links to deep pages.** A link to a service page tends to use that
   service's words; a link to a homepage tends to use the domain.

Never buy anchors, and never request the same phrase repeatedly. A uniform
anchor profile is one of the clearest manipulation signals there is, and it is
worse than the bland profile you started with.

---

## 5. The twelve-month schedule

Rows go straight to the task board, so each one names a deliverable and a
month:

| Month | Off-page work | What exists at the end of it |
|---|---|---|
| 1 | Disavow audit on the top 50 referring domains; claim and complete every Tier 1 professional listing | A dated disavow record; 6–8 Tier 1 listings live |
| 2 | Awards and recognition already held; chamber and local business listings | Tier 2 and 3 checklist closed |
| 3 | Anchor repair on internal links; micro-site decision | Internal anchors describe their destinations |
| 4–6 | First commentary pitches; one citable resource published | 2–4 earned links |
| 7–12 | Continued commentary; the second citable asset; quarterly anchor review | 2–3 earned links per quarter, and a profile whose descriptive share has moved |

**Micro-sites.** Where a client owns exact-match domains from older work
(`domestic-assault-lawyer.com` and the like), decide in month 3 and write the
decision down: 301 them into the matching service page, or leave them alone.
A second site competing with the main one for the same phrase is the worst of
the three options, and it is the state most of them are in.

---

## What goes in the client's document, and what does not

In: the current profile with its competitor comparison, the disavow shortlist
with reasons, the anchor plan, the schedule, and a plain sentence on what links
are for — *other sites vouching for this one, which is most of what decides
whether a page can rank for a competitive phrase at all.*

Out: domain rating or domain authority quoted as progress. It is a third-party
score, it moves for reasons that have nothing to do with the client, and
reporting it monthly trains them to watch the wrong number. Report referring
domains, the dofollow share, the descriptive-anchor share, and the links
actually earned — named, individually, because three real ones is a good month
and a count of thirty is a count of something else.
