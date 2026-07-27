# Vendored Claude Code skills

These skills are copied ("vendored") into the repo so they load in **every**
Claude Code session on this repo — including Claude Code on the web (the remote
sandbox), which does not auto-install external plugin marketplaces. Committing
them under `.claude/` is what makes them available in the web workflow.

`.claude/settings.json` also declares the upstream marketplaces so local
sessions can install/update the real plugins via `/plugin`.

## Sources

| Skill(s) | Upstream | License | Vendored at |
|----------|----------|---------|-------------|
| `skills/impeccable` (+ `agents/impeccable-*`) | [pbakaus/impeccable](https://github.com/pbakaus/impeccable) | Apache-2.0 | commit `d272b9b` |
| `skills/brainstorming`, `executing-plans`, `writing-plans`, `systematic-debugging`, `test-driven-development`, `subagent-driven-development`, `dispatching-parallel-agents`, `requesting-code-review`, `receiving-code-review`, `verification-before-completion`, `finishing-a-development-branch`, `using-git-worktrees`, `writing-skills`, `using-superpowers` | [obra/superpowers](https://github.com/obra/superpowers) | MIT | commit `3dcbd5c` |

Full license texts are in `.claude/vendor-licenses/`.

## Updating

These are snapshots, not live installs. To refresh:

```
git clone --depth 1 https://github.com/obra/superpowers.git
git clone --depth 1 https://github.com/pbakaus/impeccable.git
cp -R superpowers/skills/*                     .claude/skills/
cp -R impeccable/plugin/skills/impeccable      .claude/skills/impeccable
cp -R impeccable/plugin/agents/*               .claude/agents/
```

Then update the commit SHAs in the table above.

## Notes

- **superpowers** — pure guidance skills + small helper scripts; run well in the
  web sandbox. Commands like `/brainstorm`, `/write-plan`, `/execute-plan`.
- **impeccable** — front-end/design skill, invoked as `/impeccable [mode] [target]`
  (e.g. `craft`, `audit`, `critique`, `polish`, `layout`, `typeset`). Its design
  knowledge (`SKILL.md` + `reference/`) works immediately. Its script-driven
  modes (`scripts/context.mjs`, the detector, and `live` browser iteration) may
  need the npm package on a local machine — run `npx impeccable install` there —
  and `live` mode additionally needs a browser, so it's a local-only feature.
