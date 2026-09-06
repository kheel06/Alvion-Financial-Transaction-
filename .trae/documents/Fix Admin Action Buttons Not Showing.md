## What’s Causing It
- Most admin “Action” buttons are icon-only and use `<svg data-lucide="...">`.
- Lucide is loaded in [footer.php](file:///c:/Users/kheeel/Herd/hospital-fin-system/includes/footer.php#L52-L61) but there is no global `lucide.createIcons()` call, so icons remain blank and the buttons look like they’re not showing.
- A few pages also call `lucide.createIcons()` without guarding for `window.lucide`, which can throw if the CDN fails—breaking other JS and making UI feel non-functional.
- Some action buttons are intentionally hidden by status/permission checks (e.g., Approve only when status is Pending). If icons are blank, it looks like “no buttons”.

## Changes To Make
1. Add a global Lucide initialization
   - Update [footer.php](file:///c:/Users/kheeel/Herd/hospital-fin-system/includes/footer.php) to run `window.lucide?.createIcons?.()` on `DOMContentLoaded`.
   - Also run it immediately (after the script loads) to cover pages that already finished loading.

2. Make Lucide calls safe everywhere
   - Search and update any direct `lucide.createIcons()` calls to be guarded (`if (window.lucide && typeof window.lucide.createIcons === 'function') ...`).

3. Ensure admin action areas render consistently
   - Audit the main admin modules’ “Actions” columns to confirm:
     - Buttons are always present when they should be (View always visible).
     - Approve/Reject/Submit rules match status/permission logic.
     - Links/forms have correct `href`, `name`, and `type` attributes.

## Verification
- Open these pages and confirm icons render and clicks work:
  - Accounts Payable, Accounts Receivable, Budget Management, Disbursement, Suppliers, Chart of Accounts.
- Validate that status-gated actions appear correctly (Pending vs non-Pending records).
- Confirm no JS errors in the browser console after page load.

## Deliverable
- Admin action buttons will visibly render (icons show), and existing actions will remain functional (links/forms/handlers), without breaking when Lucide is unavailable.