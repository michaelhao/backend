---
name: grill-me
description: Interview the user relentlessly about a plan or design until reaching shared understanding, resolving each branch of the decision tree. Use when user wants to stress-test a plan, get grilled on their design, or mentions "grill me". Reads and updates OpenSpec change artifacts (proposal/design/tasks) when relevant.
---

Interview the user relentlessly about every aspect of this plan until you reach a shared understanding. Walk down each branch of the design tree, resolving dependencies between decisions one-by-one. For each question, provide your recommended answer.

Ask questions one at a time, waiting for the user's response before continuing.

If a question can be answered by exploring the codebase, explore the codebase instead of asking.

---

## OpenSpec Awareness

You may be grilling a plan that already lives as an OpenSpec change. Use that context when it exists; don't force it when it doesn't.

### Check for context

When the user mentions a change name, or the grill topic clearly maps to spec-driven work, run:

```bash
openspec list --json
```

This tells you which changes are active and what the user might be working on.

### When grilling against a specific change

If a change is in scope, read the artifacts before asking questions:

- `openspec/changes/<name>/proposal.md`
- `openspec/changes/<name>/design.md`
- `openspec/changes/<name>/tasks.md`
- `openspec/specs/<capability>/spec.md` (when the change touches an existing spec)

Use those artifacts as the source of grilling material — quote them, challenge them, look for gaps and contradictions.

### Capturing decisions inline

When grilling crystallises a decision, offer to update the right artifact:

| Insight                    | Where to capture             |
|----------------------------|------------------------------|
| New requirement discovered | `specs/<capability>/spec.md` |
| Requirement changed        | `specs/<capability>/spec.md` |
| Design decision made       | `design.md`                  |
| Scope changed              | `proposal.md`                |
| New work identified        | `tasks.md`                   |
| Assumption invalidated     | the relevant artifact        |

Always offer; never auto-capture. Examples:
- "That's a design decision. Capture it in design.md?"
- "This changes scope. Update the proposal?"

The user decides. Move on if they decline.

---

## Guardrails

- **One question at a time.** Wait for the answer before the next question.
- **Always include your recommended answer.** Never throw a blank question at the user.
- **Don't implement.** This is grilling, not building. If the user asks for code mid-grill, remind them to exit grill mode first.
- **Don't auto-capture.** Offer to update artifacts; let the user say yes.
- **Explore the codebase when it answers a question.** Don't ask the user things they'd answer with a grep.
