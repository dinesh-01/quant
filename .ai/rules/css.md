---
paths:
  - 'resources/css/**'
---

# Css

## Swap visual themes from active.css
Color, radius, font stack and sidebar chrome live in resources/css/themes/*.css. Each file must define the same :root / .dark variables as starter.css. Switch the look by changing the single @import in themes/active.css. Do not put page or component rules in a theme file. Components must use semantic tokens (bg-primary, bg-success, bg-sidebar), never a hardcoded palette, so a new theme can drop in tomorrow.
