# Reader analytics: admins see who read, creators see totals (9 November 2026)

Apply on top of the full project from 9 November (after the dashboard SQL fix).

    rsync -av ~/Downloads/acadsyntra-reader-analytics/ ~/Projects/acadsyntra/ --exclude README.md
    php artisan optimize:clear

No migration. Upload `public/build` too (styles rebuilt).

## Admins: Knowledge Admin > "📊 Readers"
- List of published guides, most-read first, with title search.
- Per guide: totals, then every signed-in reader (name, email, university, department, when they read,
  quiz score, passed/failed, why a read didn't count, points, time spent reading, account age),
  searchable by name or email, 50 per page. Plus readers by university and per-question results.

## Creators: Creator dashboard > "See readers" (also "📊 See your readers" on their guide page)
- Total reads (signed in vs visitors), new readers in the last 7 days, verified reads, quiz pass rate
  and average score, a 30-day chart, readers by university and by department, and which questions
  readers get wrong most.
- No names or emails. Universities/departments with fewer than 3 readers are grouped as "Other",
  so one reader can't be identified.
