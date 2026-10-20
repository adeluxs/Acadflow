// The launch video storyboard. The video is a pure function of this file plus
// the captured frames, so re-running after a site update regenerates the film.
//
// Each scene references a framed screenshot by its manifest path
// (`frames/<runId>/<section>/<name>__<viewport>__<fold>.png`).
//
// `move` drives the camera; `callout` places a feature label; `text` is the
// on-screen caption / lower third.

export const brand = {
  name: 'AcadSyntra',
  url: 'acadflow.app',
  tagline: 'Academic work, finally under control',
  primary: '#4f46e5',
  ink: '#0f172a',
};

export const storyboard = [
  {
    id: 'hook',
    dur: 4,
    kind: 'title',
    text: 'Coursework, deadlines, feedback — in one place',
    bg: 'brand',
  },
  {
    id: 'dashboard',
    dur: 8,
    kind: 'shot',
    text: 'Every course, at a glance',
    frame: 'student/dashboard__desktop__fold.png',
    move: { type: 'zoom-in', to: 1.12, focus: { x: 0.5, y: 0.4 } },
    callout: { x: 0.72, y: 0.24, label: 'Due soon & overdue' },
  },
  {
    id: 'assignments',
    dur: 8,
    kind: 'shot',
    text: 'Deadlines that explain themselves',
    frame: 'student/assignments-list__desktop__full.png',
    move: { type: 'pan-down', from: 0, to: 0.6 },
    callout: { x: 0.3, y: 0.5, label: 'Extensions shown accurately' },
  },
  {
    id: 'submission-text',
    dur: 9,
    kind: 'shot',
    text: 'Write in a real editor — no upload required',
    frame: 'student/submission-create-text__desktop__full.png',
    move: { type: 'zoom-in', to: 1.15, focus: { x: 0.5, y: 0.3 } },
    callout: { x: 0.7, y: 0.3, label: 'Rich text submission' },
  },
  {
    id: 'submission-file',
    dur: 7,
    kind: 'shot',
    text: '…or upload with clear constraints',
    frame: 'student/submission-create-file__desktop__full.png',
    move: { type: 'pan-down', from: 0.1, to: 0.7 },
    callout: { x: 0.32, y: 0.55, label: 'Formats, size and count' },
  },
  {
    id: 'ai',
    dur: 8,
    kind: 'shot',
    text: 'An assistant that reads your course context',
    frame: 'student/ai-assistant__desktop__fold.png',
    move: { type: 'zoom-in', to: 1.1, focus: { x: 0.5, y: 0.45 } },
    callout: { x: 0.75, y: 0.35, label: 'Role-aware answers' },
  },
  {
    id: 'study-tools',
    dur: 7,
    kind: 'shot',
    text: 'Study tools that build a habit',
    frame: 'student/flashcards__desktop__full.png',
    move: { type: 'zoom-in', to: 1.08, focus: { x: 0.5, y: 0.35 } },
  },
  {
    id: 'lecturer',
    dur: 9,
    kind: 'shot',
    text: 'Grade with rubrics — and AI suggestions',
    frame: 'lecturer/submission-review__desktop__full.png',
    move: { type: 'pan-down', from: 0, to: 0.7 },
    callout: { x: 0.68, y: 0.4, label: 'Rubric + written answer' },
  },
  {
    id: 'admin',
    dur: 6,
    kind: 'shot',
    text: 'Institution controls that admins actually own',
    frame: 'admin/settings__desktop__full.png',
    move: { type: 'zoom-in', to: 1.1, focus: { x: 0.5, y: 0.3 } },
  },
  {
    id: 'cta',
    dur: 6,
    kind: 'cta',
    text: 'Bring your courses onto AcadSyntra',
    url: brand.url,
    bg: 'brand',
  },
];

export const totalDuration = storyboard.reduce((sum, scene) => sum + scene.dur, 0);
