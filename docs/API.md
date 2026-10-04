# Nexora API v1

Base URL: `/api/v1/`

All protected requests use `Authorization: Bearer <token>` and `Accept: application/json`.

| Method | Endpoint | Purpose |
|---|---|---|
| POST | `auth/login` | Create technician token |
| GET | `auth/me` | Current user profile |
| POST | `auth/logout` | Revoke current token |
| GET | `premises` | Assigned active premises |
| GET | `attendance` | Paginated attendance history |
| GET | `attendance/today` | Today's attendance state |
| POST | `attendance/check-in` | Selfie and geofenced check-in |
| POST | `attendance/check-out` | GPS checkout |
| GET | `tasks` | Assigned and observed tasks |
| GET | `tasks/{id}` | Task detail and timeline |
| POST | `tasks/{id}/action` | Close or reassign task |

`attendance/check-in` is multipart form data with `premises_id`, `latitude`, `longitude`, `selfie`, and optional `remark`. A remark becomes mandatory outside the configured premises radius.
