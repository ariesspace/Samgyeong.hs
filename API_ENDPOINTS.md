# Samgyeong Admin App API v1

Base URL: `https://www.samgyeong.site`

## Authentication

### POST `/api/admin/login`
Request JSON:
```json
{"username":"admin","password":"..."}
```
Response:
```json
{"ok":true,"token":"...","expires_at":"YYYY-MM-DD HH:MM:SS","user":{...}}
```
Allowed roles: `admin`, `council`.

For protected endpoints, send either:

```http
X-Api-Token: <token>
```

or:

```http
Authorization: Bearer <token>
```

### POST `/api/admin/logout`
Revokes the current token.

## Read endpoints

### GET `/api/admin/me`
Returns the authenticated admin/council user.

### GET `/api/admin/students`
Returns students/council accounts usable as point targets.

### GET `/api/admin/points/recent?limit=50`
Returns recent point records.

### GET `/api/admin/points/summary`
Returns per-student totals:
- `merit_total`
- `demerit_total`
- `wish_coupons`

### GET `/api/admin/points/text`
Returns copy-ready text for BAND/notice use.

## Write endpoints

### POST `/api/admin/points`
Request JSON:
```json
{
  "user_id": 14,
  "type": "merit",
  "points": 1,
  "reason": "API 쓰기 테스트",
  "issued_at": "2026-07-04"
}
```

`type` must be `merit` or `demerit`.

### POST `/api/admin/points/bulk`
Request JSON:
```json
{
  "records": [
    {"user_id":14,"type":"merit","points":1,"reason":"회의 참여","issued_at":"2026-07-04"}
  ]
}
```

Returns `saved_count`, `failed_count`, `saved`, and `failed`.

## Current server patch notes

- API entrypoint: `/opt/samgyeong/app/src/Api.php`
- Router hook: `/opt/samgyeong/app/public/index.php`, before web CSRF check
- DB token table: `api_tokens`
- Existing web functions remain unchanged.
- Existing web form CSRF remains enabled for non-API POST requests.
