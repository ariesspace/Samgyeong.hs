# Samgyeong API v1.1 Duplicate Policy

## Goal
Prevent accidental duplicate point records when multiple admins or council members work at the same time.

## Two-layer duplicate protection

### 1. `client_event_id`
Prevents duplicate inserts from the same app event, such as double taps, retries after network errors, or repeated sync of the same local record.

- Same `client_event_id` again: server returns the existing record and does not insert.

### 2. `duplicate_key`
Prevents same-content duplicates from different accounts or different devices.

Hard duplicate criteria:

- same `issued_at`
- same `user_id`
- same `type` (`merit` or `demerit`)
- same `points`
- same normalized `reason`

Default behavior:

- server does not insert
- response includes `duplicate: true`, `inserted: false`, and `existing_record`

Override behavior:

- app shows a warning
- if admin intentionally confirms, app resends with `allow_duplicate: true`
- optional `duplicate_note` explains why the duplicate was allowed

## Recommended app behavior

1. App creates a stable `client_event_id` for every local point event.
2. App calls `/api/admin/points/check-duplicate` before saving, or handles `duplicate: true` from `/api/admin/points`.
3. If hard duplicate exists, show a warning with the existing record.
4. Default action should be cancel.
5. If the admin confirms, resend with `allow_duplicate: true` and `duplicate_note`.

## API additions

### `POST /api/admin/points/check-duplicate`
Checks duplicate candidates without inserting.

### `POST /api/admin/points`
Now accepts:

- `client_event_id`
- `allow_duplicate`
- `duplicate_note`

### `POST /api/admin/points/bulk`
Now returns separate arrays:

- `saved`
- `duplicates`
- `failed`
