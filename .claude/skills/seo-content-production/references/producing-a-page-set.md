# Producing a page set

A strategy that stops at a plan is half a deliverable. The agency we are
measured against hands over **72 finished page documents** — 16 service pages,
16 location pages, 40 blog posts, roughly 130,000 words — each one complete
enough that a developer builds the page without asking a question. We have
shipped plans for single pages and called the strategy delivered.

This file is how a page set gets produced: the unit, the order, the file
format, the gate, and how it survives being spread across many sessions.

---

## The unit: one document per page

Not one document containing many pages. One file per page, named after its
slug, so it can be assigned, reviewed, approved and built independently.

```
nr-lawyers/content/
  00-INDEX.md
  service-hub/sex-crimes-domestic-offences.md
  service/sexual-assault-lawyer-toronto.md
  service/domestic-assault-lawyer-toronto.md
  location/criminal-lawyer-casa-loma.md
  blog/bail-hearing-process-ontario.md
```

Each document is: the **SEO meta block**, then the page in full with heading
tags marked, then **Key Takeaways**, then **Schema Recommendations**. The
contract per page type is in `content-templates.md` and it is not optional —
that fixed structure is the only reason seventy pages read as one body of work
rather than seventy separate attempts.

Headings carry their tag in brackets — `[H2] Who Needs a Bail Hearing Lawyer in
Toronto` — because whoever builds the page is not the person who wrote it, and
a markdown `##` does not tell them whether that was meant to be an H2 or an H3
inside a collapsed section.

## The index document

`00-INDEX.md`, maintained as the set is produced. One row per page:

| # | Page | Type | Primary keyword | Vol | Words | Status | Approver | URL |
|---|---|---|---|---|---|---|---|---|
| 1 | Sex Crimes & Domestic Offences | Hub | sex crimes lawyer | 750 | 1,985 | Approved | MA, JN | /sex-crimes-domestic-offences/ |
| 2 | Sexual Assault Lawyer Toronto | Service | sexual assault lawyer toronto | 600 | — | Drafting | — | — |

This is the deliverable the client's marketing contact actually works from, and
it is what makes the set reviewable. Without it, thirty documents in a folder
is not a page set — it is a folder.

---

## The order

Not the order of the keyword list. Dependency order, because pages link to each
other and a page written before its parent has nothing to link up to.

1. **The hub pages first.** A hub defines which child owns which search. Write
   it first and every child page inherits a settled keyword map; write it last
   and the children have already competed with each other.
2. **Then the service pages under each hub**, highest-volume first, so the
   pages that matter get written while attention is freshest.
3. **Then the location pages.** They link up to service pages, which now exist.
4. **Then the blog posts**, in content-calendar order. Each one links to a
   service page that exists, with anchor text matching that page's keyword.
5. **Retitle and rewrite existing pages where the plan says so** — before the
   new pages go live, not after. A new page competing with an un-retitled old
   one splits the searches for months.

Within a batch, write the **highest-volume page in each cluster first** and use
it as the reference for the rest of that cluster. Consistency within a cluster
matters more than consistency across the whole set.

## Batch size and the session problem

A page set is longer than one session. Plan for that from the start rather than
discovering it at page twelve.

- **Produce in batches of five to eight pages**, with the gate run on each page
  as it is finished rather than at the end of the batch. A batch that fails the
  gate as a block has to be re-read as a block.
- **Update `00-INDEX.md` after every page**, not at the end. It is the handover
  state. A session that ends mid-batch should leave the index telling the next
  one exactly where it stopped.
- **Save the client profile and the approved page plan somewhere durable** and
  name them in the index. The next session starts by reading those two files,
  and if they only existed in a previous conversation, the next session starts
  by inventing a voice.
- **Save each approved page to the portal** with `save_client_artifact`, so the
  set is recoverable and the client's dashboard reflects what has shipped.

Where the portal connector is in the session, `list_client_artifacts` is the
fastest way to find out what a previous session already produced — check it
before writing a page that may already exist.

---

## Per-page gate

Every page, before it counts as finished:

```bash
python scripts/check_draft.py <page.md> <profile.md> "Service page" --approver="Name <email>"
```

Three conditions, all three required: score **70 or better**, **no failed
check**, and a **named approver**. The checker also verifies the page type's
section contract — the required headings, in order — the meta block's fields
and character limits, the Key Takeaways and Schema Recommendations sections,
and that no `[VERIFY: …]` marker is left unresolved.

The score cannot tell you the page is true, that it sounds like the client, or
that the case example happened. Those are the failures that damage a client and
no checker will ever catch them, which is what the named approver is for.

## What gets flagged rather than written

Three things, every time:

- **A fact the profile does not have** — a courthouse name for a new city, a
  case outcome, a statistic. `[NEEDS CLIENT INPUT: which court serves Weston]`.
- **A specific number or credential with no source**, under the claims policy's
  standing rule. `[VERIFY: volume-of-results claim]`. The claims policy decides
  how hard the copy pushes; it does not license inventing a figure, because the
  client is the one who gets asked to substantiate it.
- **A local detail that would be wrong on the neighbouring page.** On a
  location page this is the section that justifies the page existing. If it
  cannot be written truthfully, say so and drop the page from the set rather
  than producing a doorway page.

Collect all of these into the index's own section as the set is produced, so
the client answers them in one sitting instead of thirty emails.

---

## How many pages is the right number

From the page plan, not from a round number. The plan assigns one owner to
every search; the page set is exactly those owners that do not yet exist, plus
the rewrites.

Two cases where fewer is correct and should be said plainly in the index:

- **A search that an existing page already owns.** Rewriting and retitling it
  beats building a new URL that competes with it — a new page resets the
  impressions and the history the old one has accumulated.
- **A location that cannot support a truthful page.** Sixteen neighbourhood
  pages that differ only in a place name is not sixteen pages; it is one
  doorway page sixteen times, and it carries a manual-action risk.

Padding a set to a round number produces pages nobody will link to and the
client will not maintain. Say how many the plan supported and why.
