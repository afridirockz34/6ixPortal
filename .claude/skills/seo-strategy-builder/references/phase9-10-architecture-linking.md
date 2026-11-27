# Phase 9 — Page Architecture, and Phase 10 — Internal Linking

Both are derived phases — strong Claude territory once the keyword clusters exist, because the inputs are already structured data rather than raw research.

## Phase 9 — Page Architecture

**What it produces:** the URL map — which pages get built, what they're titled, what URL slug they use, and which keyword cluster each one targets.

**Required inputs:** the *scored and intent-classified* keyword clusters from Phase 7/8 (not a raw list — see `references/keyword-scoring.md`), and the foundation document's service and service-area lists (so the architecture matches how the business actually thinks about itself).

**Prompt pattern:**
```
Here is [Client]'s foundation document: [paste].
Here are the scored, intent-classified keyword clusters from Phase 7/8,
with each keyword's Keyword Opportunity Score: [paste].

Propose a page architecture: for each proposed page, its type (service
page, location page, audience page, blog post, pillar page — matched to
the classified intent of its primary keyword(s) per keyword-scoring.md's
intent-to-page-type table), a working title, a URL slug, and which
keyword cluster(s) it targets. Order the page list by the highest
Keyword Opportunity Score among each page's targeted keywords, so it's
visible which pages should get built first if architecture is phased.
Group service and location combinations sensibly rather than creating a
separate page for every possible combination.
```

**The failure mode to watch for:** AI-generated architecture proposals tend to over-fragment — a separate thin page for every city × service combination, most of which would never earn enough search volume to justify a dedicated page. A human should sanity-check the proposed page count against realistic content production capacity and realistic per-page search volume (a page targeting a keyword with near-zero monthly volume usually shouldn't exist as a standalone page — fold it into a broader page instead). This is exactly the kind of judgment call the model shouldn't make unsupervised.

---

## Hub and sub-page: how many pages a city actually supports

This is the part of the architecture a client reads most closely, and the
reasoning behind it is the single thing in a recent strategy the client
singled out as worth keeping. It is reproduced here so it survives.

**Every city gets a hub page. Sub-pages only exist where a search exists.**

Work it out per city, from the keyword set, before proposing anything:

| City | Searches in the set | Pages it supports |
|---|---|---|
| Brampton | dui 600, sexual assault 300, impaired driving 200, domestic assault 200 | Hub **plus three** sub-pages |
| Belleville | criminal lawyer belleville 100 (the hub itself), dui lawyer belleville 100 | Hub **plus one** |
| Owen Sound | criminal lawyer owen sound 50 (the hub itself) | **Hub only** |

Owen Sound is the instructive one. There is nothing left for a sub-page to
target, so any we built would be a page with no search behind it — which is
the thin-content pattern we are usually removing from the client's site in
month two. Building it anyway while removing theirs is indefensible.

### Say it in the document, before the client finds it

A hub page with 50 searches a month behind it is still worth building: the
firm wants work in that city, the page should exist so there is something to
rank when somebody there searches, and 50 a month in a town that size is a
reasonable share of it. What it will not do is move the traffic number.

**Say that in the strategy rather than letting it surface as an
underperforming page at the month-six review.** One sentence:

> Owen Sound has one search behind it at around 50 a month, which the hub
> page covers on its own. We are building it because the firm wants work
> there and it should rank when somebody in Owen Sound searches — it is not
> going to move the traffic number, and we would rather say that now.

A strategy that explains why a small page exists reads as judgement. The
same page with no explanation reads as padding, and in month six it reads
as a failure.

---

## Low-hanging fruit: 10–15% of the plan, never the driver

A strategy built entirely on the biggest keywords shows the client nothing
for six months. A strategy built on easy wins makes us look good and never
grows the business. The client's own framing, which is the rule now:

> It's not all about searches. It's also about pleasing the client and
> showing them results and something that matters. … Keep in mind we need to
> bring quality visitors and leads to the customer but at the same time we
> need to go for low hanging fruits that can get us a win and make us look
> good on papers. These low hanging fruits should however not be the driving
> factor in the strategy.

So, concretely:

- **Two to five winnable targets**, no more, carrying **10–15% of the
  plan's weight**. A smaller city where the firm can realistically take the
  top three inside a couple of quarters is the shape of this.
- **They go early**, because their job is to produce something to show while
  the competitive work is still ranking.
- **The rest of the plan is driven by value**, not by ease: the services the
  client said are most profitable, in the cities they said they want work
  from.
- **Never dress an easy win as a major outcome.** Belleville reaching the
  top three is a real win and a small number. Say both.

### Set the expectation on the big keywords in the same breath

A new or thin site will not take *criminal lawyer toronto* quickly, and a
strategy that implies otherwise fails at the first review. State the
asymmetry once, plainly, where the objectives are introduced:

> The smaller markets should show movement first. The competitive Toronto
> searches are where most of the value is, and on a site this age they take
> longer — we expect those to move through the second half of the year
> rather than the first.

---

## Look past the list the client gave you

The same client asked for this explicitly: *"I like that it is doing a
comprehensive search and bringing more growth opportunities around by
suggesting some other city pages other than was included in the plan.
Enhance this even more."*

So the architecture is not limited to the cities on the questionnaire. Run
the service × city expansion across the surrounding region, score what comes
back, and bring the findings into the document as a short section of their
own:

- Cities the client **did not name** that carry real volume for their
  services — with the number, so they can judge it.
- Services the client **does offer** but did not list as a priority, where
  the search volume says otherwise.
- Anything with meaningful volume and low difficulty that the plan would
  otherwise miss.

Present them as recommendations with the evidence attached, not as decisions
already taken. The client decides whether their firm wants work in a city;
we decide what the search data says about it.

## Phase 10 — Internal Linking

**What it produces:** the site's linking structure — which page types link to which others (the "silo" structure), anchor text rules, and a minimum-links-per-page-type table.

**Required inputs:** the finished page architecture from Phase 9.

**Prompt pattern:**
```
Here is the page architecture from Phase 9, including each page's
Keyword Opportunity Score: [paste].

Produce an internal linking plan: which page types should link to which
others (a silo/hub structure), anchor text guidelines (descriptive,
varied, never the exact same anchor text repeated across many pages),
and a minimum internal links per page type table. Weight the minimums
toward the highest-scored pages — they should end up with more inbound
internal links than a low-scored page, not an equal share. Flag any page
in the architecture that would end up with no inbound internal links
under this plan — an orphaned page is a real problem, not a minor one.
```

This is genuinely one of the more mechanical phases — once the architecture exists, the linking plan mostly follows from it directly. The one piece of judgment is making sure link equity actually flows toward the pages the Keyword Opportunity Score says matter most, rather than distributing links evenly out of convenience.
