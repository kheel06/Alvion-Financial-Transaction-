## Overview
Improve hover behavior for the admin sidebar to match the dark, professional look in your screenshot: subtle pill background on hover/active, clean color transitions, icon brightening, and smooth arrow rotation without jarring movement.

## Current Findings
- Main file: [sidebar_admin.php](file:///c:/Users/kheeel/Herd/hospital-fin-system/includes/sidebars/sidebar_admin.php)
- Active state helpers:
  - [isActive](file:///c:/Users/kheeel/Herd/hospital-fin-system/includes/sidebars/sidebar_admin.php#L12-L17) returns inactive `text-slate-400 hover:bg-white/5 hover:text-white transition-colors` and active `bg-white/10 text-white shadow-sm`.
  - [isActiveDir](file:///c:/Users/kheeel/Herd/hospital-fin-system/includes/sidebars/sidebar_admin.php#L20-L26) mirrors the same behavior.
- Top-level buttons already use `group`, `hover:bg-white/5`, `hover:text-white`, and `transition` classes (e.g., [L45-L48](file:///c:/Users/kheeel/Herd/hospital-fin-system/includes/sidebars/sidebar_admin.php#L45-L48)).
- Submenu JS toggles visibility and uses inline style rotations for arrows ([L277-L312](file:///c:/Users/kheeel/Herd/hospital-fin-system/includes/sidebars/sidebar_admin.php#L277-L312)).

## Proposed Changes
- Normalize hover styles across all items:
  - Inactive: `text-slate-300/400 hover:bg-white/5 hover:text-white transition-colors duration-200`.
  - Active: `bg-white/10 text-white shadow-sm`.
- Ensure icon color changes consistently:
  - Icon span: `text-slate-400 group-hover:text-white transition-colors` plus `text-white` when its parent is active.
- Remove any residual motion/jumpiness:
  - Eliminate `hover:translate-x-*` and heavy shadows; keep subtle `shadow-sm` only for active.
- Arrow rotation with classes instead of inline styles:
  - Add `transition-transform` and toggle `rotate-180` on expand/collapse for smoother animation.
- Submenu items:
  - Use consistent pill hover/active styles on `a` elements; keep left border accent `border-slate-700`.

## Implementation Steps
1. Update helper functions returns:
   - isActive($page): inactive `text-slate-400 hover:bg-white/5 hover:text-white transition-colors duration-200`; active `bg-white/10 text-white shadow-sm`.
   - isActiveDir($dir): same pattern, with `font-medium` for active section buttons.
2. Top-level buttons (Dashboard, General Ledger, etc.):
   - Ensure container has `group px-3 py-2.5 rounded-lg transition-all duration-200`.
   - Ensure inactive/active class sets come from helpers or conditional expressions consistently.
3. Icons inside buttons and submenu links:
   - Apply `text-slate-400 group-hover:text-white transition-colors`.
   - When the parent is active, add `text-white`.
4. Submenu links (`a` elements):
   - Base classes: `flex items-center px-3 py-2 text-sm rounded-md transition-colors`.
   - Append `<?php echo isActive('file.php'); ?>` for consistent inactive/active behavior.
5. Arrow rotation behavior:
   - In markup: add `transition-transform duration-200` to `.submenu-arrow`.
   - In JS: replace inline `style.transform` with toggling `rotate-180` class on expand.
6. Clean-up:
   - Remove any `hover:bg-gray-50` or `dark:hover:bg-gray-700/50` remnants if present.
   - Remove `hover:translate-x-1` and heavy `hover:shadow-*` from any items.

## Verification
- Interactively test hover on all items (Dashboard, GL, AP, AR, etc.).
- Confirm icons brighten, text changes to white, and a subtle pill background appears.
- Verify expanded sections auto-open when active and arrows rotate smoothly.
- Check dark mode contrast and keyboard focus (`focus-visible:ring` optional enhancement).

## Accessibility Enhancements (Optional)
- Add `focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-white/20` to interactive elements for better keyboard navigation.

## Scope
- Changes limited to the admin sidebar. No layout or non-hover behavior changes beyond arrow rotation class-based animation.

If you approve, I’ll implement the class updates and JS tweaks, then validate the interaction end-to-end.