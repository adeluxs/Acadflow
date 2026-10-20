# Chapter Two literature insertion fix — 2026-09-16

## Problem
The Research Studio displayed **Insert citation**, **Insert evidence**, **Methodology**, and **Research gap** actions beside Chapter Two, and the button briefly changed to "Inserted", but the selected text did not appear in the visible editor.

## Root cause
AcadSyntra dispatches literature insertions to the hidden `textarea[data-rich-editor]`. The fallback rich-text editor listened for `acadflow:editor-insert`, but the normal Quill editor path did not. Because Quill is normally available, the visible editor never received the insertion event.

The rich-editor module is also dynamically imported, so a very fast click could occur before either editor finished initialization and the event would be lost.

## Fix
- Added a shared rich-editor registry used by both Quill and the fallback editor.
- Added one centralized `acadflow:editor-insert` listener for every registered editor.
- Added `window.AcadFlowRichEditor.insert(...)` for explicit insertion into a specific editor.
- Added a pre-initialization queue on the target textarea. If the user clicks before Quill finishes loading, the text is inserted as soon as the editor registers.
- Quill now immediately synchronizes the visible editor content back into the hidden textarea after an insertion, so **Save version** persists exactly what the researcher sees.
- Removed duplicate fallback insertion listeners that could have produced double text.
- Kept insertion user-controlled: text is inserted into the editor, but nothing is saved until the researcher presses **Save version**.

## Regression protection
`scripts/check-chapter-two-literature-insertion.php` verifies that:
- both Quill and fallback editors register with the same insertion contract;
- the insertion listener exists exactly once;
- the global insertion API and pre-init queue exist;
- Chapter Two uses that API;
- Quill uses its real `insertText` path.
