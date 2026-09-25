# LinkedIn post (drafts)

Replace `[LIVE_DEMO_URL]`. Post it yourself; nothing here has been published. Pick one.

---

## Option A: the flap-protection angle

A single dropped ping should never wake anyone up at 3 a.m.

I've been building NetWatch, an open-source network uptime and incident platform, and the part I enjoyed most was the flap protection. It's a small state machine: a monitor goes Down only after 3 consecutive failed checks, and comes back only after 2 passes. Counters live per monitor, updated under a row lock so a manual "run now" can't race the scheduler.

To prove it works I wrote a simulator scenario that fails 2 checks out of every 3, forever. Result: 20+ failed checks, zero incidents. Then a second scenario with a real 6-minute outage: incident opens, alert goes out, service recovers, incident resolves itself.

The rest of the loop is there too: provider circuits, maintenance windows, SLA that leaves planned work out, a strict incident state machine with a timeline, Telegram alerts with an Acknowledge button, and an RFO you can export as a PDF.

Laravel, React, Redis, WebSockets. Built clean-room with fictional data.
Demo (read-only): [LIVE_DEMO_URL]
Code: https://github.com/Ali-Imran01/netwatch

#Laravel #React #NetworkMonitoring #NOC #OpenSource

---

## Option B: the SLA angle

"What was our availability last month, excluding the provider's planned maintenance?"

Most teams answer that with a spreadsheet. I built it into NetWatch instead. Every circuit has an SLA target and linked monitors. Maintenance windows are entered as the provider announces them, and the availability figure drops any 5-minute bucket that overlaps a window, so planned work counts neither for nor against the circuit.

A design choice I'd flag: SLA is computed from 5-minute rollups, not raw checks, because raw results are pruned after 14 days but a monthly report has to keep working. The trade-off is precision: a window edge inside a bucket excludes the whole bucket. I documented it rather than hiding it.

It's part of a bigger project: a self-hosted network monitoring and incident platform (Laravel, React, Redis, WebSockets) with Telegram acknowledge-from-your-phone and PDF RFO export.

Demo (read-only): [LIVE_DEMO_URL]
Code: https://github.com/Ali-Imran01/netwatch

#NetworkOperations #SLA #Laravel #OpenSource

---

## First comment (either option)

Two bugs the testing caught that I liked: checks were running every 60 s instead of 30 s (claiming each monitor at a slightly different moment pushed some past the next scheduler tick), and 565 live-update broadcasts failed because the workers were pointed at `localhost` inside Docker. Both are fixed and covered by the write-up in the repo.
