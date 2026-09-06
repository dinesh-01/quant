---
paths:
  - resources/js/components/ui/input.tsx
---

# Ui

## Inputs default to autocomplete off
The shared Input defaults to autoComplete="off". Login, register, and settings must pass an explicit token (email, current-password, name) when the browser should fill the field. Chrome treats name="name" as a contact field and shows values saved from other sites.
