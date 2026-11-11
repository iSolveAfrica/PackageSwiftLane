# Readability adjustment

Increased explicit small UI text sizes (9–14px) by 2 CSS pixels across the shared
shell, page styles, inline page labels and dynamically rendered support metadata.
For example, 12px captions become 14px and 13.5px supporting copy becomes 15.5px.
The default body size is now 16px. Font weights and larger headings are unchanged.
Mobile fields retain their 16px minimum. Dedicated print layouts retain their sizing.

Adjusted phone wrapping for action buttons, email subjects, header subtitles and
chart month labels. Narrow charts show fewer month labels while retaining the
underlying data. Compose-message actions wrap rather than overflowing.

Validation: 129 rendered page states at 1440, 1024, 768, 390 and 360px; four narrow
layout issues were identified and corrected. A focused six-state recheck and
final compose-message recheck cover those corrections. Shared runtime, mobile
experience, route and page-shell checks pass. Changed PHP files pass syntax
checks; changed JavaScript parses and the diff passes whitespace checks.
Compared font-weight declarations against the previous commit: none changed.
