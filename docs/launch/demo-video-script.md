# 2-minute demo video: script and shot list

You record this; it needs the deployed demo (or the dev stack seeded with `DemoSeeder`). Aim for ~2:00. Screen recording, voice-over, no music needed. Do a dry run first: the story below depends on where the simulated outage is in its 30-minute cycle, so check the Status board a few minutes before you record and start right before a circuit goes red (or record a longer take and cut).

**Prep**
- Two browser windows: one signed in as the **read-only demo account** (`demo@netwatch.example`), one signed in as an **engineer/admin** you created with `php artisan netwatch:user` (the demo account cannot acknowledge or edit).
- Telegram open on your phone/desktop with the alert channel configured, if you want the acknowledge moment (needs the bot set up per `docs/DEPLOY.md`).
- Browser zoom ~110%, hide bookmarks bar, close other tabs.

| Time | On screen | Say |
|---|---|---|
| 0:00–0:10 | Status board, all green, counts on top, "Live" indicator | "This is NetWatch: a self-hosted platform for tracking network assets, watching uptime, and running incidents from alert to written report." |
| 0:10–0:25 | Inventory → Sites / Devices, then Carriers → Circuits (SLA column) | "Inventory of sites, devices and subnets, plus provider circuits, each with an SLA target and a 30-day availability figure." |
| 0:25–0:45 | Back to Status; point at the flapping monitor staying green, then the latency-spike monitor's chart (Monitors → open it → 1 hour / 24 hours) | "Checks run every 30 seconds through a queue. One link is dropping two packets in three, but nothing opens: a monitor only goes Down after three failures in a row. Here's a latency spike on a circuit." |
| 0:45–1:05 | A circuit tile turns red **live**; counts change; open banner "1 open incident" | "This circuit just failed its third check, so it flipped to Down over the WebSocket without a refresh, and an incident opened by itself." |
| 1:05–1:25 | (Phone) Telegram alert with the **Acknowledge** button → tap it; (laptop) incident timeline shows "Acknowledged via Telegram" | "The alert reaches Telegram with an Acknowledge button. Tapping it moves the incident to Acknowledged, and the timeline records who and when." |
| 1:25–1:40 | Incident detail: allowed next states only; (admin window) move to Investigating → Escalated, add a provider ticket number; show an illegal move being impossible (button not offered) | "The state machine only offers legal moves. When the circuit recovers by itself, an unescalated incident resolves automatically." |
| 1:40–1:55 | Resolved incident → fill RFO → close → **Download PDF** → open the PDF | "Closing requires an RFO. One click gives a PDF with the timeline, the root cause and the corrective action." |
| 1:55–2:00 | Carriers → Maintenance page, then repo URL on screen | "Planned provider maintenance is excluded from SLA and silences incidents. It's open source: Laravel, React, Redis, WebSockets. Link below." |

**If the outage isn't due:** record the Status board and charts first, then use an admin window to open **Monitors → Run now** on a monitor whose target you've temporarily pointed at a closed port, or seed a fresh instance right before recording so the cycle starts near the beginning.

**After recording:** upload, add the demo and repo links to the description, and paste the video link into the case study and LinkedIn post.
