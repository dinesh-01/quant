---
paths:
    - 'tests/**'
---

# Tests

## MySQL reorders JSON object keys

MySQL normalises the key order inside a JSON column's objects (by key length, then value), so a stored ['from' => ..., 'to' => ...] reads back with 'to' first. assertSame on the whole array fails for that reason alone.

Assert key by key, or use assertEquals, which compares arrays regardless of key order.

Also: when asserting a value is absent from a result (that no secret was recorded, say), assert the row exists FIRST. Otherwise the absence checks pass vacuously on an empty result — which is precisely when the request broke and the test mattered.

## Resolve actions from the container, never with new

Use app(SomeAction::class)(...), not (new SomeAction)(...) or new SomeAction(new Dependency).

Every domain action now takes at least AuditLogger, and several take more. Building them by hand has already broken a batch of tests twice when a constructor gained a parameter, and the failure is an ArgumentCountError that masks whatever the test was actually asserting — including expected-exception tests, which then fail with a confusing 'exception did not match' message.
