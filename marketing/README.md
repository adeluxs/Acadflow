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
| Video render      | Chrome native H.264/AAC MP4 encoder         | ✅ `npm run video:render` exports landscape and portrait launch films with original music |

## Finished launch video

`launch-capture.mjs` combines previously verified marketing demo screens with fresh
Research Studio captures from the **isolated local browser-fixture server**. It
creates a demonstration project and an interview-guide resource in that fixture
database. Start the isolated server described in
[PERFORMANCE_OPERATIONS.md](../docs/PERFORMANCE_OPERATIONS.md#isolated-browser-fixtures-and-profiling)
at port 8013 before running capture; do not point it at your normal app database.

```sh
npm run video:launch:capture
npm run video:render
```

Output defaults to `marketing/video/launch/2026-10-21/`:

- `acadsyntra-launch-landscape.mp4` — 1920×1080, 52 seconds.
- `acadsyntra-launch-portrait.mp4` — 1080×1920, 52 seconds.
- `acadsyntra-launch.srt` — scene captions.
- `landscape-storyboard.png` and `portrait-storyboard.png` — scene previews.
- `exports.json`, `sources.json`, and `CREDITS.txt` — export checks and sources.

The music is an original synthesized instrumental; no third-party recordings
are used. The film uses real demo screens, motion, on-screen copy, and music.
It contains no voiceover. Every MP4 is checked for duration, dimensions and
successful playback seeking before the renderer reports success.
The active launch scene list, layouts and music live in `video/launch.html`;
`video/render.mjs` records and validates both formats. The older
`video/storyboard.mjs` remains a separate reference for the original screenshot
pipeline and does not control these exports.

Use `MARKETING_OUTPUT` to choose a different output folder for both commands,
`MARKETING_DEMO_URL` to change the isolated capture server, and
`MARKETING_SOURCE_RUN` to choose an existing verified screenshot run. Set
`MARKETING_LAUNCH_DATE` to change the displayed date. A website is displayed
only when `MARKETING_LAUNCH_URL` is explicitly supplied; an unconfirmed address
is never substituted. For example:

```sh
MARKETING_LAUNCH_DATE="21 OCTOBER 2026" npm run video:render
```

The installed Chrome must support MP4 MediaRecorder (H.264/AAC). Override
`PLAYWRIGHT_CHROMIUM_EXECUTABLE_PATH` when Chrome lives elsewhere. The new
renderer does not require FFmpeg, Remotion, a browser download, or network access.

## Quick start

### Live interaction launch walkthrough

The more interactive version records actual clicks, chapter changes, focus mode,
typing and autosave, table insertion/removal, library editing and the Studio guide.
Both desktop and mobile recordings include timestamped cursor and click highlights.
Use the same isolated fixture server at port 8013 described above, then run:

```sh
npm run video:interactive:capture
npm run video:interactive:render
```

Outputs are in `marketing/video/launch/2026-10-21-interactive/`:

- `acadsyntra-interactive-launch-landscape.mp4` and `acadsyntra-interactive-launch-portrait.mp4` — 52-second H.264/AAC films.
- `preview.html` — open in a browser for clickable chapters and a format selector.
- `interactions.json` and `clips/` — captured frame timestamps and actual pointer events.
- Scene previews, captions, export metadata and credits.

`MARKETING_INTERACTIVE_OUTPUT` overrides the capture and render output folder.
The capture step also uses the first launch run's brand/screens folder. It creates
only demonstration projects in the fixture database. Native app captures run at
approximately 8 fps; the composition, cursor animation and music render at 30 fps.
`interactive.html` supplies the composition, while `interactive-preview.html`
supplies the chapter player. No application build is needed for video rendering.

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

- The older Remotion/FFmpeg path required tools unavailable on this Mac. The
  implemented `video:render` path instead uses the installed Chrome's native
  MP4 encoder and works locally; the optional render approaches above remain
  alternatives for further editing.
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
