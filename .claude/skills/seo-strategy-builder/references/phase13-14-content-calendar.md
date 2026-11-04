# Phase 13 — Blog Topics, and Phase 14 — Content Calendar

## Phase 13 — Blog Topic List

**What it produces:** a large topic list (the source document's scale is 100 topics — adjust to what the client's keyword research actually supports; padding to hit a round number produces thin, low-value topics), clustered by funnel stage (top: informational/awareness, middle: comparison/consideration, bottom: commercial/ready-to-act) and mapped to intent.

**Required inputs:** the keyword clusters from Phase 7/8, and the page architecture from Phase 9 (so blog topics complement rather than duplicate the planned service/location pages).

**Prompt pattern:**
```
Here is [Client]'s foundation document: [paste].
Here are the clustered keywords from Phase 7/8: [paste].
Here is the page architecture from Phase 9: [paste].

Generate a blog topic list from these keyword clusters — informational
and long-tail terms not already assigned to a service/location page in
the architecture. For each topic: target keyword, funnel stage, search
intent, and a one-line angle. Do not invent topics unrelated to the
keyword data provided; every topic should trace back to a real keyword
or cluster.
```

## Phase 14 — Content Calendar

**What it produces:** a publishing schedule (the source document uses a 12-month horizon) sequencing the blog topics and any remaining architecture pages.

**Required inputs:** the topic list from Phase 13, the page architecture from Phase 9, and a realistic capacity number — how many pieces per month the client or team can actually produce and get reviewed. This last input is a conversation, not something to assume.

**Prompt pattern:**
```
Here is the blog topic list from Phase 13: [paste].
Here is the page architecture from Phase 9: [paste].
The client/team can realistically produce [N] pieces of content per
month, reviewed and published.

Sequence these into a content calendar at that pace. Prioritize by: (1)
pages in the architecture with the highest keyword value, (2) topics that
support pages already planned, (3) seasonal or time-sensitive topics if
any exist, (4) everything else by funnel stage, top-of-funnel first to
build a base of supporting content. Do not compress the timeline below
the stated realistic capacity to make the plan look more impressive.
```

**Where this is easiest to oversell:** a content calendar that assumes a pace the client can't actually sustain is a plan that fails by month three, not a plan that's merely ambitious. Set the pace from an honest capacity conversation, not from what would make the projected timeline in Phase 5 look achievable.
