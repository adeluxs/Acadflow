process.env.MARKETING_INTERACTIVE='1';
await import('./render.mjs');
const {writeInteractivePreview}=await import('./interactive-preview.mjs');
await writeInteractivePreview(process.env.MARKETING_INTERACTIVE_OUTPUT || 'marketing/video/launch/2026-10-21-interactive');
