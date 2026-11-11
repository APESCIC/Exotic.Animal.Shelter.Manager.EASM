---
name: plan-work
description: Investigate a task, issue or whole version and write a plan in the EASM layout (summary, decisions, numbered tasks with done-when checks, files, risks, glossary), post it to the issue when asked, wait for approval, then name the agent. Use when the user asks for a plan, to plan an issue or version, or before any change that touches more than a few files.
---

# Plan work

Read-only until the plan is approved. Terms: `docs/glossary.md`.

1. **Investigate, changing nothing.**
   - The issue and its comments: `gh issue view <n> --comments`.
   - Its parent, sub-issues and milestone.
   - Related open PRs.
   - The code it touches.
   - `docs/shipping.md`, if the plan touches how work ships.
2. **For a whole version:**
   - List the milestone's issues: `gh issue list --milestone "<milestone title>" --state open --json number,title,body --limit 100`.
   - Put them in a sensible order and note which depend on which.
   - Flag any issue that is too big (more than one easy-to-review PR, or several unrelated areas) and suggest how to split it.
3. **Write the plan** in plain English, short sentences, with these sections:
   1. **Summary:** what you found, in five bullets or fewer.
   2. **Decisions needed:** a short table of each choice, its options and your recommendation.
   3. **Tasks:** numbered and in order. For each: what, why (one line), files, a "done when" check, and size (small, medium or large). Group them into PRs that are easy to review.
   4. **Files:** to add, rewrite, delete or keep.
   5. **Risks:** and how you'll avoid each.
   6. **Glossary:** any term a novice wouldn't know, in one plain sentence each, or a link to `docs/glossary.md`.
4. **Post it when asked.** `gh issue comment <n> --body-file <file>`, with the agent name (step 6) as its heading.
5. **Stop and wait** for Bambie's approval. Make no edits, installs or commits before it. Ask any decisions with AskQuestion, recommended option first.
6. **Name the agent** once the plan is approved:
   - For a version, use the milestone title plus " – EASM", for example "v0.6.2 Repo standards – EASM agent docs".
   - For work with no milestone, use "EASM – <short task name>".
   - Keep it under 60 characters, with the version number and never the milestone number.
   - If you can't rename the chat, make the first line of your reply "Suggested agent name: …".
7. **Carry out** the plan with the `work-on-issue` skill, one issue at a time.
