---
paths:
  - 'app/Actions/Users/**, app/Http/Controllers/Users/**, app/Http/Requests/Users/**'
---

# Requests Users

## Invite with a reset link; typed password is the fallback
Creating an account stores a random password and emails Laravel's signed reset link (`Password::sendResetLink`). Until SMTP is set, MAIL_MAILER=log writes that link to the log. An administrator sending a reset link to an existing account needs manage_users and assign_global_roles, and must end sessions the same way SetUserPassword does. Keep the typed-password action as the fallback when mail is unreachable. Public sign-up is gated by config('auth.self_signup') / AUTH_SELF_SIGNUP; do not add Socialite or LDAP without an explicit package approval.
