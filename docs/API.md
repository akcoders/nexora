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
| GET | `library/filters` | Library categories (with guide counts), device types, manufacturers and content types |
| GET | `library` | Paginated library guides; `q`, `category`, `device_type`, `manufacturer`, `content`, `page`, `per_page` |
| GET | `library/{id}` | One guide: written steps, videos, documents (signed links), web links and related guides |
| GET | `library/files/{resource}` | Signed document download (no token; use the `url` from the guide detail) |
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

## Knowledge library

How-to guides written by the office: written steps (rich text), YouTube videos, documents and web links, grouped by category and tagged by device type and manufacturer. All three token endpoints need the `library.read` permission (Admin, Manager, Technician and Viewer roles have it); without it they return `403 {"message": "You do not have access to the library."}`. Users who can create, update or delete guides also see drafts; for everyone else a draft or deleted guide is `404`.

### `GET library/filters`

Only active categories and active device type / manufacturer masters, in their admin order. `count` is the number of guides the user can see in that category.

```json
{
  "categories": [{"id": 2, "name": "Preventive maintenance", "slug": "preventive-maintenance", "icon": "shield-check", "color": "success", "description": "PM and AMC checklists, cleaning and periodic service.", "count": 1}],
  "device_types": [{"code": "split_ac", "label": "Split AC"}],
  "manufacturers": [{"code": "daikin", "label": "Daikin"}],
  "content_types": [{"key": "article", "label": "Written guide", "icon": "notebook-text"}, {"key": "video", "label": "Video", "icon": "youtube"}, {"key": "document", "label": "Document", "icon": "file-text"}, {"key": "link", "label": "Web link", "icon": "link"}]
}
```

`icon` values are Lucide icon names; `color` is a tone key (`primary`, `info`, `success`, `warning`, `danger`, `purple`, `teal`, `secondary`).

### `GET library`

| Query | Meaning |
|---|---|
| `q` | Search words; every word must appear in the title, summary, written steps, tags, model numbers, category name or a resource title / file name. Title matches come first. |
| `category` | Category id or slug |
| `device_type`, `manufacturer` | Master `code` from `library/filters` |
| `content` | `article` (has written steps), `video`, `document` or `link`; anything else is `422` |
| `page`, `per_page` | Default 20 per page; `per_page` above 50 is capped at 50 |

Without a search, featured guides come first, then the most recently published.

```json
{
  "data": [{
    "id": 9001, "title": "How to clean split AC indoor filters",
    "summary": "Monthly filter wash for wall-mounted split units: isolate, remove, wash, dry and refit.",
    "category": {"id": 2, "name": "Preventive maintenance", "slug": "preventive-maintenance", "icon": "shield-check", "color": "success"},
    "device_type": {"code": "split_ac", "label": "Split AC"},
    "manufacturer": {"code": "daikin", "label": "Daikin"},
    "model_numbers": "FTKF50, FTKF60", "tags": ["filters", "cleaning", "pm"],
    "status": "published", "is_featured": true, "has_steps": true,
    "counts": {"video": 1, "document": 1, "link": 1},
    "cover_url": "https://i.ytimg.com/vi/dQw4w9WgXcQ/hqdefault.jpg",
    "views_count": 1, "published_at": "2026-11-03T14:52:11+05:30", "updated_at": "2026-11-03T14:52:11+05:30"
  }],
  "meta": {"current_page": 1, "last_page": 1, "per_page": 20, "total": 3}
}
```

`summary` is a short excerpt (the author's summary, or the start of the written steps). `device_type` and `manufacturer` are `null` when not set, `cover_url` is the first video's thumbnail or `null`, `published_at` is `null` for drafts. Dates are ISO 8601 with offset.

### `GET library/{id}`

Every card field above plus the content below. Opening a guide counts one view per user every 6 hours, on the web and in the app together (`views_count` in the response already includes it). Views do not change `updated_at`.

```json
{
  "data": {
    "id": 9001, "title": "How to clean split AC indoor filters", "...": "card fields",
    "full_summary": "Monthly filter wash for wall-mounted split units: isolate, remove, wash, dry and refit.",
    "body_html": "<h2>Before you start</h2><p>Switch the unit <strong>OFF</strong> at the isolator.</p><ol><li>Open the front panel.</li></ol><p><img src=\"https://example.com/storage/library/images/filter-demo.png\" alt=\"Filter\"></p>",
    "videos": [{"id": 9001, "title": "Filter cleaning walkthrough", "video_id": "dQw4w9WgXcQ", "url": "https://www.youtube.com/watch?v=dQw4w9WgXcQ", "embed_url": "https://www.youtube-nocookie.com/embed/dQw4w9WgXcQ?rel=0", "thumbnail_url": "https://i.ytimg.com/vi/dQw4w9WgXcQ/hqdefault.jpg"}],
    "documents": [{"id": 9002, "title": "FTKF service manual", "file_name": "daikin-ftkf-service-manual.pdf", "extension": "pdf", "size_label": "45 B", "mime_type": "application/pdf", "url": "https://example.com/api/v1/library/files/9002?expires=1793701357&user=12&signature=…"}],
    "links": [{"id": 9003, "title": "Daikin India support", "url": "https://www.daikinindia.com/support", "host": "daikinindia.com"}],
    "related": [{"id": 9002, "title": "E4 error code on Voltas inverter split", "...": "card fields"}],
    "author": {"name": "Nexora Admin"},
    "updated_by": {"name": "Nexora Admin"}
  }
}
```

- `full_summary` is the author's summary as written (`null` when there is none); `body_html` is `null` when the guide has no written steps.
- `body_html` is sanitised HTML: headings, paragraphs, bold/italic/underline/strike, `ol`/`ul` lists (checklists are `<ul class="nx-checklist"><li data-checked="true|false">`), blockquotes, `pre`/`code`, tables, links, images (site-relative image URLs are made absolute) and YouTube embeds as `<iframe class="ql-video" src="https://www.youtube-nocookie.com/embed/ID?rel=0">`. Text and background colour survive as inline `style` (`color`, `background-color`); alignment comes as `class="ql-align-center|right|justify"` (or an inline `text-align`).
- Resource titles fall back to the file name, the link host or the kind when the author left them empty.
- `related` holds up to 5 other guides the user can see from the same category or for the same device type, guides matching both first.

### `GET library/files/{resource}`

Open a document with the `url` from the guide detail. The link is signed and valid for 60 minutes, so it needs no `Authorization` header and can be handed to a PDF viewer, browser or download manager; fetch the guide again for a fresh link. PDFs, images and text files are sent `inline`, other documents (Word, Excel, PowerPoint, CSV) as `attachment`, with the standard `Content-Type` for the extension. The link carries the reader's user id and works only while that user is active and can still see the guide; if the guide is unpublished or deleted, or the user loses library access, it returns `404`. A changed, missing or expired signature is `403`; a resource that is not a document or a missing file is `404`.
