---
user-invocable: true
name: bear-cache-log
description: Retired. Replaced by bear-observe, which ships with BEAR.EventSourcing. Use only when the user invokes bear-cache-log by name, and point them to bear-observe.
---

# bear-cache-log (retired)

This skill has moved. Use `bear-observe`, which ships with
[BEAR.EventSourcing](https://github.com/bearsunday/BEAR.EventSourcing):

```bash
composer require --dev bear/event-sourcing
mkdir -p .claude/skills && cp -r vendor/bear/event-sourcing/skills/bear-observe .claude/skills/
```

`bear-observe` installs the observation context, proves the wiring, runs a request and reads the
tree: the cache decisions this skill used to cover, nested inside the resource requests that
caused them.
