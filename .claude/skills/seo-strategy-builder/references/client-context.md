# Getting client context

Every skill in this set is only as good as what it knows about the client. A
strategy built without their actual keywords, market and constraints is a
generic strategy, and a generic strategy is the thing clients can tell apart
from a real one.

There are three ways that context arrives. Try them in this order, and **never
let the absence of one stop the work** — a thinner input means a narrower,
honestly-framed deliverable, not a refusal.

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

The profile's `questionnaire` block is grouped by section, and every answer
carries its question text, so you can read it without a key map.
`questionnaire_progress` tells you how much is filled in; if large parts are
blank, say so in the deliverable's framing and tell whoever is running this
which sections to chase.

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
