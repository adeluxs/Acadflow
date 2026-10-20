# AcadSyntra screenshot → launch video pipeline

Automated, repeatable capture of every curated AcadSyntra page, cleaned into
consistent branded frames, driven by a data storyboard that can be rendered into
a marketing video.

```
routes ─▶ demo seed ─▶ Playwright capture ─▶ sharp cleanup ─▶ storyboard ─▶ video render
```

## What is implemented

| Stage             | Tool                                      | Status in this repo                                                                           |
| ----------------- | ----------------------------------------- | --------------------------------------------------------------------------------------------- |
| Page discovery    | `php artisan route:list --json`           | ✅ documented, use for inventory                                                              |
| Demo data         | `MarketingDemoSeeder` + `prepare-demo.sh` | ✅ implemented + seeded                                                                       |
| Auth (roles)      | Playwright `storageState`                 | ✅ implemented (`auth` step)                                                                  |
| Capture           | Playwright (system Chrome channel)        | ✅ **ran: 35 screenshots**                                                                    |
| Cleanup / framing | `sharp`                                   | ✅ **ran: 35 frames + contact sheet**                                                         |
| Storyboard        | `storyboard.mjs`                          | ✅ implemented                                                                                |
| Video render      | Remotion / FFmpeg                         | ⚠️ scaffold + docs (this machine cannot install Remotion and has no FFmpeg — see Limitations) |

## Quick start

```bash
cd acadflow-web

# 1. Build + seed a dedicated demo database (never touches dev data)
npm run video:demo

# 2. Run the demo app in another terminal
DB_DATABASE="$(pwd)/database/marketing-demo.sqlite" DB_CONNECTION=sqlite \
  APP_URL=http://127.0.0.1:8123 php artisan serve --host=127.0.0.1 --port=8123

# 3. Capture + clean
npm run video            # capture then clean
# or individually:
npm run video:capture    # → marketing/video/artifacts/<runId>/raw/**.png + manifest.json
npm run video:clean      # → marketing/video/frames/<runId>/**.png + contact-sheet.png
```

Environment overrides:

| Variable                    | Default                 | Purpose                                    |
| --------------------------- | ----------------------- | ------------------------------------------ |
| `MARKETING_BASE_URL`        | `http://127.0.0.1:8123` | target app                                 |
| `MARKETING_RUN_ID`          | ISO timestamp           | run folder name                            |
| `MARKETING_BROWSER_CHANNEL` | `chrome`                | Playwright channel (uses installed Chrome) |

## Layout

```
marketing/
  video/
    prepare-demo.sh     # demo db + seed
    lib.mjs             # config, launch, auth helpers, freeze CSS, masks
    capture.mjs         # the curated manifest + Playwright capture
    clean.mjs           # sharp: rounded, shadowed, branded frames + contact sheet
    storyboard.mjs      # scenes, camera moves, callouts, copy
    artifacts/<runId>/  # raw screenshots + manifest.json  (git-ignored)
    frames/<runId>/     # cleaned frames + contact-sheet.png (git-ignored)
    .auth/              # per-role storageState JSON        (git-ignored)
database/
  seeders/MarketingDemoSeeder.php
  marketing-demo.sqlite # demo db                          (git-ignored)
storage/app/marketing-demo.json  # demo entity ids for dynamic URLs
```

Demo accounts (password `password`):
`demo.student@acadsyntra.test`, `demo.lecturer@acadsyntra.test`, `demo.admin@acadsyntra.test`.

## How capture works

1. **Auth** — logs in each demo role in an isolated context and writes
   `storageState` (student / lecturer / admin). Rebuilt every run so cookies
   never go stale.
2. **Manifest** — [`capture.mjs`](video/capture.mjs) lists curated pages with
   role, viewports (desktop/tablet/mobile), fold vs full page, a `waitFor`
   selector, optional `actions`, and mask selectors. Dynamic URLs are resolved
   from `storage/app/marketing-demo.json` (written by the seeder).
3. **Determinism** — before each shot the browser injects CSS that freezes
   animations/transitions and injects a service-worker kill shim, waits for
   fonts and network idle, masks volatile elements (`[data-marketing-mask]`,
   `.js-last-login`, `.js-notification-time`), and screenshots at 1×/2× DPI.
4. **Storage & naming** — `raw/<section>/<name>__<viewport>__<fold>.png`, plus
   `manifest.json` with size + SHA-256 per file (change detection on re-runs).

## Cleanup (clean.mjs)

Each raw shot is resized to a consistent width, rounded, given a soft drop
shadow and placed on the AcadSyntra brand gradient; the run also produces
`contact-sheet.png` (a 4-column grid of thumbnails) for a fast visual review.

## Storyboard → video

[`storyboard.mjs`](video/storyboard.mjs) is the single source of truth for the
film: scene list with durations, caption text, `move` (zoom/pan), and `callout`
labels, referencing framed images. Re-running after a site update regenerates
the video.

**To render** (on a machine where these are available):

- **Remotion (recommended, fully programmable):**
    ```bash
    npm i -D remotion @remotion/cli react react-dom
    npx remotion render rendering/entry.js AcadFlowLaunch out/acadflow-16x9.mp4
    ```
    Build scenes as React components; use `interpolate`/`spring` for the zooms,
    pans and callouts defined in the storyboard.
- **FFmpeg (scriptable, no Node renderer):**
    ```bash
    # per-scene Ken Burns clip, then concat + crossfade
    ffmpeg -loop 1 -i frame.png -vf "zoompan=z='min(zoom+0.0008,1.15)':d=150,setsar=1,fade=in:0:15,fade=out:135:15" -t 5 -r 30 scene.mp4
    ffmpeg -f concat -safe 0 -i scenes.txt -c copy video.mp4
    ```

Aspect ratios to export from the same storyboard: **16:9** (YouTube/web),
**1:1** and **4:5** (feed), **9:16** (Reels/TikTok/Shorts). Resolutions
1080p/1440p/4K; H.264 MP4 master + VP9/WebM for the web + a short looping
MP4/GIF for docs.

## Audio (when rendering)

Licensed music (Artlist/Epidemic/Uppbeat), human voiceover preferred (auto-TTS
only as a fallback), burnt-in captions + sidecar `.srt`, loudness ≈ −14 LUFS.

## Limitations (encountered / to plan for)

- **This machine cannot install Remotion** (its native install step references a
  macOS symbol absent on Big Sur) and **has no FFmpeg**, and **Playwright cannot
  download its bundled Chromium on macOS 11** — so capture uses the
  **installed Google Chrome channel**, which works. Render steps therefore must
  run on a newer macOS/Linux/CI box (or a machine with FFmpeg).
- Screenshots can't capture canvas/WebGL, embedded PDFs and streaming states
  faithfully; record those live.
- Auth/2FA/onboarding and service workers can corrupt state — the pipeline
  seeds ready users and disables the service worker per page.
- Real PII must be masked; the demo dataset uses fake names only.
- Screenshot-only footage looks static — pair it with 2–3 live-recorded hero
  interactions and a voiceover for a premium launch film.

## Repeatability

Rerun `npm run video:demo && npm run video` after a release. Because
`manifest.json` stores a SHA-256 per screenshot, changed scenes are easy to
spot; wire the same commands into CI to regenerate artifacts automatically.
