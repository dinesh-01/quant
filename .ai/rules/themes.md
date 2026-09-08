---
paths:
  - 'resources/css/themes/**'
---

# Themes

## Tabler theme is tokens only
The product look is Tabler 1.5 mapped onto the starter.css token contract in tabler.css. Switch themes only by changing the @import in active.css. Do not add layout or component rules to a theme file, and do not import @tabler/core SCSS or Bootstrap JS into the Inertia React app — port visuals through semantic tokens (bg-primary, bg-sidebar) and React components.
