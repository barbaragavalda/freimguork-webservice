# API reference

Every request needs an `Authorization` header: either the consuming app's own shared secret
(`shared` below) or the caller's own user token (`user`). Paths are relative to wherever the
consuming app mounts this package (usually `/api`).

| Method | Path | Auth | What it does |
|---|---|---|---|
| POST | `/register` | shared | Create an account (`email`, `password`, `username`) |
| POST | `/login` | shared | Log in (`email`, `password`), returns a `token` |
| POST | `/logout` | user | Revoke the current device's token |
| POST | `/password/forgot` | shared | Email a 6-digit reset code (`email`) - same response either way |
| POST | `/password/reset` | shared | Reset with the code (`email`, `code`, `password`) - also revokes every device token |
| DELETE | `/account` | user | Revoke every token and delete the account |

Email change, username/password change, and language are **not** owned by this package - a
consuming app implements those itself against `Webservice\Model\User`/`EmailChange` directly (see
`tv-tracker-local`'s `Api\Controller\Account\*` for the reference implementation).
