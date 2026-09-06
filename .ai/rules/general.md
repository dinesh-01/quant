---
paths:
  - vite.config.ts
---

# General

## Vite origin needs CORS for APP_URL
server.origin pins asset URLs to http://localhost:5173 so Laravel does not emit http://:::5173. That same origin becomes Vite's CORS allow-list, which blocks scripts from APP_URL (http://localhost:8001) and leaves the SSR page unhydrated. Always set server.cors.origin to include the Laravel origin as well.
