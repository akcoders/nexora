# Nexora API v1

Base URL: `/api/v1/`

All protected requests use `Authorization: Bearer <token>` and `Accept: application/json`.

| Method | Endpoint | Purpose |
|---|---|---|
| POST | `auth/login` | Create technician token |
| GET | `auth/me` | Current user profile |
| POST | `auth/logout` | Revoke current token |
| GET | `notifications` | Paginated task and service-job alerts |
| POST | `notifications/{id}/read` | Mark one alert read |
| POST | `notifications/read-all` | Mark every alert read |
| GET | `premises` | Assigned active premises |
| GET | `attendance` | Paginated attendance history |
| GET | `attendance/today` | Today's attendance state |
| POST | `attendance/check-in` | Selfie and geofenced check-in |
| POST | `attendance/check-out` | GPS checkout |
| GET | `tasks` | Assigned and observed tasks |
| GET | `tasks/{id}` | Task detail and timeline |
| POST | `tasks/{id}/action` | Close or reassign task |
| GET | `complaint-visits` | Complaint visits the user is on as technician or assistant; `scope=open` (default) or `completed` (latest 50) |
| GET | `complaint-visits/{id}` | Visit and complaint detail, previous visits, report form option lists and `can_report` |
| POST | `complaints/{complaint}/visits/{visit}/report` | Save visit progress or submit the outcome (JSON or multipart with `attachments[]`) |
| GET | `service-jobs` | Assigned service jobs; supports `scope=today` and `status` |
| GET | `service-jobs/{id}` | Job detail, current primary action, masters and lifecycle data |
| POST | `service-jobs/{id}/contact` | Confirm, reschedule, no-answer, decline or cancel outcome |
| POST | `service-jobs/{id}/journey` | Start technician journey |
| POST | `service-jobs/{id}/arrival` | Record GPS or manual arrival |
| POST | `service-jobs/{id}/inspection` | Start pre-service inspection |
| PUT | `service-jobs/{id}/inspection-items/{item}` | Save checklist condition and remark |
| POST | `service-jobs/{id}/inspection/complete` | Complete pre-service checklist |
| POST | `service-jobs/{id}/estimates` | Create server-calculated estimate |
| POST | `service-jobs/{id}/estimates/{estimate}/decision` | Customer approval, change request or decline |
| POST | `service-jobs/{id}/service/start` | Begin service execution |
| POST | `service-jobs/{id}/services` | Add work performed from service catalog |
| POST | `service-jobs/{id}/materials` | Add inventory or other material |
| POST | `service-jobs/{id}/photos` | Upload before, during or after photo |
| POST | `service-jobs/{id}/post-inspection` | Start post-service checklist |
| POST | `service-jobs/{id}/post-inspection/complete` | Complete post-service checklist |
| POST | `service-jobs/{id}/signatures` | Capture completion or technician signature |
| POST | `service-jobs/{id}/service/complete` | Validate and complete field work |
| POST | `service-jobs/{id}/payments` | Record payment, partial payment or pay-later status |
| POST | `service-jobs/{id}/complete` | Close service and create feedback link |

`attendance/check-in` is multipart form data with `premises_id`, `latitude`, `longitude`, `selfie`, and optional `remark`. A remark becomes mandatory outside the configured premises radius.

Photo, approval-signature, completion-signature and payment-proof requests use multipart form data. Estimate totals, catalog prices and taxes are always recalculated by the backend.

The complaint visit report accepts partial updates: any field left out keeps the value saved on the visit (from the app or the web), so sending only `{"accepted_at": "2026-11-02T10:15:00"}` just stamps Accepted; a field sent as `null` is cleared. Times without an offset are read as app time (IST); times with an offset such as `Z` are converted. Fault category, root cause and pending reason / sub reason are sent as the master label (the `value` in the form lists).
