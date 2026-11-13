# Phase 13 — Blog Topics, and Phase 14 — Content Calendar

## Phase 13 — Blog Topic List

**What it produces:** about 100 blog topics in the exact shape
`references/keyword-strategy.md` specifies — ten named clusters by **subject**,
columns `# | Blog Title | Target Keyword | Funnel Stage | Angle | Priority`.
**Read `references/keyword-strategy.md` before starting;** it holds the
controlled `Angle` vocabulary and the rule that stops one angle taking over a
cluster.

Two things that distinguish this from the topic list we used to produce:

- **Blog Title is the real headline, written out.** Not "post about bail
  hearings" — the title the published page will carry. If it isn't good enough
  to publish, it isn't good enough to put in the plan, and a list of subject
  labels is a list somebody still has to do the work on.
- **Angle comes from the controlled vocabulary.** This is the column that stops
  100 topics becoming 100 restatements of the same article, which is the normal
  failure and the reason long topic lists read as filler. Clustering by subject
  rather than by funnel stage is what makes the set read as coverage of an area;
  the Funnel Stage column still carries the stage for every row.

**Required inputs:** the keyword clusters from Phase 7/8, and the page architecture from Phase 9 (so blog topics complement rather than duplicate the planned service/location pages).

**Prompt pattern:**
```
Here is [Client]'s foundation document: [paste].
Here are the clustered keywords from Phase 7/8: [paste].
Here is the page architecture from Phase 9: [paste].

Produce the blog topic table exactly as keyword-strategy.md specifies:
about 100 topics in ten named clusters by subject, columns
# | Blog Title | Target Keyword | Funnel Stage | Angle | Priority.

Write each Blog Title as a real, publishable headline. Take every Angle
from keyword-strategy.md's controlled vocabulary, and don't let one
angle dominate a cluster unless the cluster is named for that angle.
Every Target Keyword comes from the Phase 7/8 research — informational
and long-tail terms not already assigned to a service or location page
in the architecture. A topic that traces to no keyword is a topic
somebody made up; leave it out. If the research supports fewer than
100, ship fewer and say how many it supported.
```

```bash
python scripts/check_keyword_set.py topics.csv --kind topics
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
pages and topics with the highest Keyword Opportunity Score (see
keyword-scoring.md) among the architecture and topic list, (2) topics
that support pages already planned, (3) seasonal or time-sensitive
topics if any exist, (4) everything else by funnel stage, top-of-funnel
first to build a base of supporting content. Do not compress the
timeline below the stated realistic capacity to make the plan look more
impressive.
```

**Where this is easiest to oversell:** a content calendar that assumes a pace the client can't actually sustain is a plan that fails by month three, not a plan that's merely ambitious. Set the pace from an honest capacity conversation, not from what would make the projected timeline in Phase 5 look achievable.

**This calendar gets checked, not just executed.** Once pieces from it are actually published, Phase 20's recurring retrospective compares how the scored priorities performed against what the score predicted — and feeds a revised sequence back into the next period's calendar. A calendar built here isn't the final word on sequencing; it's the first draft that the retrospective is meant to correct.
