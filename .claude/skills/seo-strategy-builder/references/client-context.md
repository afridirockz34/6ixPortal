# Getting client context

Every skill in this set is only as good as what it knows about the client. A
strategy built without their actual keywords, market and constraints is a
generic strategy, and a generic strategy is the thing clients can tell apart
from a real one.

There are three ways that context arrives. Try them in this order, and **never
let the absence of one stop the work** — a thinner input means a narrower,
honestly-framed deliverable, not a refusal.

---

## 0. First, what did they actually buy?

Before deciding what to assess, read the client's **active services** — the
profile returns them, and they are the scope of the work. The three cases are
different deliverables, not different emphases:

| Active services | What the deliverable covers |
|---|---|
| **SEO only** | Organic. Local is worth raising as an opportunity where they are plainly a local business, but flagged as something not currently in scope rather than quietly assessed. |
| **Google Business Profile only** | Local pack and map visibility, the profile itself, reviews, citations. Their organic rankings are context. Do not hand them a content strategy they are not paying for. |
| **Both** | Both, and **the local half is not optional.** |

That last row is the one that goes wrong. A client paying for SEO and GMB,
handed a report about keywords and backlinks, has been assessed on half of
what they bought — and on the half they can least easily check themselves.
`references/local-and-gmb.md` covers what a local assessment actually
consists of and which connector measures it.

A client with no active services at all is a prospect, which is normal: the
audit runs as an external scan, and `seo-audit-report` says so in its framing.

---

## 1. The portal profile (best, when it's there)

The 6ix Developers portal is the system of record for everything a client or
lead has told us: their onboarding answers plus the full SEO questionnaire —
brand story, ideal client, target keywords and market, named competitors, team
credentials, content capacity, and any regulatory constraints.

Look for the portal connector in this session's tool list — tools named
`get_client_profile`, `list_clients`, `save_client_artifact` (search for them if
they're deferred). If it's there:

1. **`list_clients`** with `search` — turn a business or person's name into a
   `client_id`. You'll be handed "build the strategy for Acme Plumbing", not an
   id. Searches business name, person's name and email.
2. **`get_client_profile`** with that id — the whole profile in one call.
3. **`list_client_artifacts`** — check what's already been produced for them
   (a recent audit, a keyword analysis) before regenerating it.
4. **`check_performance`** — for an existing client, where they stand against
   where they started and against the targets a previous strategy set. Worth a
   call before any deliverable that will characterise how they're doing.
   `references/performance-tracking.md` covers reading it.

The profile's `questionnaire` block holds two sets — `audit` (the short intake
answered at signup) and `strategy` (the long one) — each grouped into
`sections`, and every answer carries its question text, so you can read it
without a key map. Unanswered questions are left out rather than returned
blank. Each set's `progress` tells you how much is filled in and names what's
outstanding; if large parts are blank, say so in the deliverable's framing and
tell whoever is running this which sections to chase. The profile's `stage`
is the quick version of the same thing.

`questionnaire.strategy` also carries `issued`/`issued_at` and
`submitted`/`submitted_at`. Those are three separate facts and they move
independently: issued is us asking, `progress.percent` is how much is filled
in, and submitted is the client saying they are finished. A questionnaire can
read 100% without ever having been submitted — they are most likely still
working through it, so treat the last answers as drafts and re-read the
profile before anything is finalised.

**If the connector isn't in this session, or the client isn't in the portal
yet:** that's normal — a cold prospect won't be in there, and not every session
has the connector loaded. Move to option 2.

---

## 2. A questionnaire they filled in as a document

Plenty of clients would rather fill in a Word document over email than log into
a portal. That's fine, and the answers are exactly as good.

Accept whatever they send: `.docx`, `.pdf`, Google Doc export, a pasted email
body, a call transcript, or an older brand document that covers the same
ground. Read it and pull the same facts you'd have read from the profile —
there's no requirement that it match the official questionnaire's wording or
section order.

Two things to do with it:

- **Use it immediately** for whatever you're building. Don't block on getting it
  into the portal first.
- **Offer to file it.** If the portal connector is available and the client
  exists there, offer to write their answers in with `update_client_profile` so
  the next person doesn't have to hunt through an inbox for them.
  `references/questionnaire-fields.md` maps every question to the key it's
  stored under — match on the question text, write using the key. Only write
  what the document actually answers; leave the rest alone.

  Two cautions. Fields marked *from signup* already hold what the client told us
  during onboarding — only overwrite one if their document is genuinely more
  current. And don't write the contact section; names, emails and addresses are
  account data, not questionnaire answers.

  Writing needs a write-scoped token. If `update_client_profile` (or
  `save_client_artifact`, used elsewhere for filing a finished deliverable)
  fails or the connector is read-only, say so plainly rather than silently
  skipping the write — the person running this should know their answers
  didn't actually get filed, not discover it the next time someone looks for
  them and finds nothing there.

If their document only covers part of the ground, that's still worth having.
Note which sections are missing rather than treating a partial return as
unusable.

---

## 3. Neither — work from what's publicly measurable

If there's no profile and no document, the work still goes ahead, built on
whatever external data the task has: Ahrefs, the live site, their Google
Business listing, competitor pages.

What changes is the framing. Say plainly in the deliverable that it reflects
what's publicly measurable and that a full version would also account for the
business specifics you don't have — something like:

> This reflects what's measurable from the outside. A full strategy would also
> account for [their margins, their regulatory constraints, which services they
> actually want to grow] — we'd pick that up in the questionnaire.

Then name the two or three questions that would change the conclusions most.
That's a far better close than pretending the gap isn't there.

**What never happens:** filling the gap with a plausible-sounding assumption.
Don't invent a target market, a competitor set, a client's margins, or a
compliance position. An invented fact in a foundation document or client
profile propagates into everything built on top of it afterwards, and it is
very hard to spot later.

---

## Compliance is a special case

Whatever route the context arrives by, the regulated-industry answers are not
background colour — they are a hard constraint on what the deliverable may say:

- The regulator, and whether the profession is regulated at all
- Claims they're not permitted to make (guaranteed results, protected terms
  like "specialist")
- Rules about testimonials, reviews, and soliciting them
- Required disclaimers
- Whether content must clear a compliance officer before publishing

If the client is in a regulated field (law, medicine, finance and similar) and
you don't have these answers, **ask for them before writing marketing claims**,
rather than writing something that may be unusable. This is the one gap worth
pausing on.

---

## One thing we never do with this data

`seo_lifetime_value` and `seo_repeat_business` tell you which keywords are
worth competing for. They are **not** inputs to a revenue or ROI projection.
6ix Developers does not promise returns, and any deliverable implying a
guaranteed financial outcome is one we can't stand behind. Use them to rank
opportunity; never to multiply out a number.
