# Working preferences

- Include performance considerations in every change. Check for repeated database queries, unbounded result sets, unnecessary payloads, and work on frequently used request paths.
- Reuse the existing architecture and prefer measured, simple optimizations. Preserve authorization, tenant isolation, data ownership, and billing correctness; do not cache mutable permissions or financial balances across requests.
- Verify affected behavior and meaningful performance regressions with appropriate checks. Do not run a frontend build unless the change requires it or the user requests it.
- Treat speed as a standing requirement throughout the application, including shared layouts, dashboards, APIs, search, research, academic modules, billing, notifications, uploads, and background jobs. Measure affected paths before and after substantial changes; report evidence and remaining infrastructure limits without claiming unmeasured improvements.
