# Screenshots checklist

Capture these from the demo (signed in as the demo viewer; use an admin window only where noted). 1600×900 or larger, browser chrome hidden or cropped, light theme. Save into `docs/screenshots/` with the names below, then uncomment the image line in the README and reference the rest from the case study.

| File | What to show | Notes |
|---|---|---|
| `status-board.png` | Status page: counts row, mixed tiles including one Down and one Maintenance, "Live" | Wait for the simulated outage or the maintenance window so it is not all green |
| `monitor-chart.png` | A monitor detail page, 24 hours range, average + p95 lines | Needs ≥ 1 hour of data (rollups appear after a 5-minute bucket closes) |
| `circuits-sla.png` | Carriers → Circuits with the SLA (30d) column | |
| `maintenance.png` | Carriers → Maintenance with a scheduled and an active window | |
| `incident-timeline.png` | Incident detail with the timeline and the legal next-state buttons | Admin window |
| `rfo-pdf.png` | The exported RFO PDF, first page | Close an incident first (admin) |
| `telegram-alert.png` | The Telegram message with the Acknowledge button | Crop out the chat's name/avatar |
| `inventory-import.png` | CSV import panel showing row-level errors | Use a deliberately broken file |

Do not capture: the sign-in page with any password typed, `.env` values, or any real hostnames or IPs (the demo only has fictional data, but check).
