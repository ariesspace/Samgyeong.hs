<?php

declare(strict_types=1);

date_default_timezone_set('Asia/Seoul');

function samgyeong_api_ensure_schema(PDO $db): void
{
    $db->exec("\n        CREATE TABLE IF NOT EXISTS api_tokens (\n            id INTEGER PRIMARY KEY AUTOINCREMENT,\n            user_id INTEGER NOT NULL,\n            token_hash TEXT NOT NULL UNIQUE,\n            created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,\n            expires_at TEXT NOT NULL,\n            revoked_at TEXT,\n            last_used_at TEXT,\n            FOREIGN KEY(user_id) REFERENCES users(id)\n        );\n\n        CREATE INDEX IF NOT EXISTS idx_api_tokens_hash ON api_tokens(token_hash);\n        CREATE INDEX IF NOT EXISTS idx_api_tokens_user ON api_tokens(user_id);\n    ");

    // POINT_DUPLICATE_SCHEMA_V11_START
    $db->exec('PRAGMA busy_timeout = 5000');

    $pointColumns = [];
    foreach ($db->query('PRAGMA table_info(point_records)') as $column) {
        $pointColumns[(string) $column['name']] = true;
    }

    if (!isset($pointColumns['client_event_id'])) {
        $db->exec('ALTER TABLE point_records ADD COLUMN client_event_id TEXT');
    }
    if (!isset($pointColumns['duplicate_key'])) {
        $db->exec('ALTER TABLE point_records ADD COLUMN duplicate_key TEXT');
    }
    if (!isset($pointColumns['duplicate_override_of'])) {
        $db->exec('ALTER TABLE point_records ADD COLUMN duplicate_override_of INTEGER');
    }
    if (!isset($pointColumns['duplicate_note'])) {
        $db->exec('ALTER TABLE point_records ADD COLUMN duplicate_note TEXT');
    }

    $rowsNeedingDuplicateKey = $db->query("\n        SELECT id, user_id, type, points, reason, issued_at\n        FROM point_records\n        WHERE duplicate_key IS NULL OR duplicate_key = ''\n        LIMIT 1000\n    ")->fetchAll();
    if ($rowsNeedingDuplicateKey) {
        $updateDuplicateKey = $db->prepare('UPDATE point_records SET duplicate_key = ? WHERE id = ?');
        foreach ($rowsNeedingDuplicateKey as $row) {
            $reasonNorm = samgyeong_api_normalize_reason((string) ($row['reason'] ?? ''));
            $duplicateKey = samgyeong_api_make_duplicate_key(
                (string) $row['issued_at'],
                (int) $row['user_id'],
                (string) $row['type'],
                (int) $row['points'],
                $reasonNorm
            );
            $updateDuplicateKey->execute([$duplicateKey, (int) $row['id']]);
        }
    }

    $db->exec("\n        CREATE UNIQUE INDEX IF NOT EXISTS idx_point_records_client_event_id\n        ON point_records(client_event_id)\n        WHERE client_event_id IS NOT NULL AND client_event_id != ''\n    ");
    try {
        $db->exec("\n            CREATE UNIQUE INDEX IF NOT EXISTS idx_point_records_duplicate_key_active\n            ON point_records(duplicate_key)\n            WHERE duplicate_key IS NOT NULL\n              AND duplicate_key != ''\n              AND duplicate_override_of IS NULL\n              AND canceled_at IS NULL\n              AND cancellation_of_id IS NULL\n        ");
    } catch (Throwable $e) {
        $db->exec("\n            CREATE INDEX IF NOT EXISTS idx_point_records_duplicate_key\n            ON point_records(duplicate_key)\n        ");
    }
    $db->exec('CREATE INDEX IF NOT EXISTS idx_point_records_issued_user ON point_records(issued_at, user_id, type, points)');
    // POINT_DUPLICATE_SCHEMA_V11_END

    $db->exec("DELETE FROM api_tokens WHERE expires_at < datetime('now')");
}

function samgyeong_api_json(array $payload, int $status = 200): never
{
    http_response_code($status);
    header('Content-Type: application/json; charset=utf-8');
    header('Cache-Control: no-store');
    echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

function samgyeong_api_body(): array
{
    $raw = file_get_contents('php://input') ?: '';
    $json = json_decode($raw, true);
    if (is_array($json)) {
        return $json;
    }

    if (!empty($_POST)) {
        return $_POST;
    }

    return [];
}

function samgyeong_api_token_from_request(): string
{
    $token = trim((string) ($_SERVER['HTTP_X_API_TOKEN'] ?? ''));
    if ($token !== '') {
        return $token;
    }

    $auth = (string) ($_SERVER['HTTP_AUTHORIZATION'] ?? $_SERVER['REDIRECT_HTTP_AUTHORIZATION'] ?? '');
    if ($auth === '' && function_exists('getallheaders')) {
        $headers = getallheaders();
        $auth = (string) ($headers['Authorization'] ?? $headers['authorization'] ?? '');
    }

    if (preg_match('/Bearer\s+(.+)/i', $auth, $m)) {
        return trim($m[1]);
    }

    return '';
}

function samgyeong_api_current_user(PDO $db, array $roles = ['admin', 'council']): array
{
    samgyeong_api_ensure_schema($db);

    $token = samgyeong_api_token_from_request();
    if ($token === '') {
        samgyeong_api_json(['ok' => false, 'error' => 'missing_token'], 401);
    }

    $hash = hash('sha256', $token);
    $stmt = $db->prepare("\n        SELECT users.id, users.username, users.role, users.display_name, users.hall_key, users.year\n        FROM api_tokens\n        JOIN users ON users.id = api_tokens.user_id\n        WHERE api_tokens.token_hash = ?\n          AND api_tokens.revoked_at IS NULL\n          AND api_tokens.expires_at >= datetime('now')\n        LIMIT 1\n    ");
    $stmt->execute([$hash]);
    $user = $stmt->fetch();

    if (!$user) {
        samgyeong_api_json(['ok' => false, 'error' => 'invalid_token'], 401);
    }
    // SGMANAGER_V041_FIX3_GUARDS_CURRENT_USER_START
    $activeStmt = $db->prepare('SELECT COALESCE(is_active, 1) FROM users WHERE id = ? LIMIT 1');
    $activeStmt->execute([(int) $user['id']]);
    if ((int) $activeStmt->fetchColumn() !== 1) {
        samgyeong_api_json(['ok' => false, 'error' => 'account_inactive'], 403);
    }
    // SGMANAGER_V041_FIX3_GUARDS_CURRENT_USER_END


    if (!in_array($user['role'], $roles, true)) {
        samgyeong_api_json(['ok' => false, 'error' => 'forbidden'], 403);
    }

    $stmt = $db->prepare('UPDATE api_tokens SET last_used_at = CURRENT_TIMESTAMP WHERE token_hash = ?');
    $stmt->execute([$hash]);

    $user['id'] = (int) $user['id'];
    $user['year'] = (int) ($user['year'] ?? 0);

    return $user;
}

// POINT_DUPLICATE_V11_START
function samgyeong_api_normalize_reason(string $reason): string
{
    $reason = trim($reason);
    $reason = preg_replace('/\s+/u', ' ', $reason) ?? $reason;
    if (function_exists('mb_strtolower')) {
        $reason = mb_strtolower($reason, 'UTF-8');
    } else {
        $reason = strtolower($reason);
    }
    return $reason;
}

function samgyeong_api_make_duplicate_key(string $issuedAt, int $userId, string $type, int $points, string $reasonNorm): string
{
    return hash('sha256', $issuedAt . '|' . $userId . '|' . $type . '|' . $points . '|' . $reasonNorm);
}

function samgyeong_api_trim_text(string $value, int $limit): string
{
    $value = trim($value);
    if (function_exists('mb_substr')) {
        return mb_substr($value, 0, $limit, 'UTF-8');
    }
    return substr($value, 0, $limit);
}

function samgyeong_api_public_point_record(?array $row): ?array
{
    if (!$row) {
        return null;
    }

    foreach (['id', 'user_id', 'points', 'issuer_id', 'canceled_by', 'cancellation_of_id', 'duplicate_override_of'] as $key) {
        if (array_key_exists($key, $row) && $row[$key] !== null) {
            $row[$key] = (int) $row[$key];
        }
    }

    return $row;
}

function samgyeong_api_fetch_point_record_by_id(PDO $db, int $id): ?array
{
    $stmt = $db->prepare("\n        SELECT point_records.*,\n               target.display_name AS target_name,\n               target.username AS target_username,\n               issuer.display_name AS issuer_name,\n               issuer.username AS issuer_username\n        FROM point_records\n        JOIN users AS target ON target.id = point_records.user_id\n        JOIN users AS issuer ON issuer.id = point_records.issuer_id\n        WHERE point_records.id = ?\n        LIMIT 1\n    ");
    $stmt->execute([$id]);
    $row = $stmt->fetch();

    return samgyeong_api_public_point_record($row ?: null);
}

function samgyeong_api_find_by_client_event_id(PDO $db, string $clientEventId): ?array
{
    if ($clientEventId === '') {
        return null;
    }

    $stmt = $db->prepare('SELECT id FROM point_records WHERE client_event_id = ? LIMIT 1');
    $stmt->execute([$clientEventId]);
    $id = $stmt->fetchColumn();

    return $id ? samgyeong_api_fetch_point_record_by_id($db, (int) $id) : null;
}

function samgyeong_api_find_hard_duplicate(PDO $db, string $duplicateKey): ?array
{
    $stmt = $db->prepare("\n        SELECT id\n        FROM point_records\n        WHERE duplicate_key = ?\n          AND duplicate_override_of IS NULL\n          AND canceled_at IS NULL\n          AND cancellation_of_id IS NULL\n        ORDER BY id DESC\n        LIMIT 1\n    ");
    $stmt->execute([$duplicateKey]);
    $id = $stmt->fetchColumn();

    return $id ? samgyeong_api_fetch_point_record_by_id($db, (int) $id) : null;
}

function samgyeong_api_find_soft_duplicates(PDO $db, array $candidate, int $limit = 5): array
{
    $stmt = $db->prepare("\n        SELECT id\n        FROM point_records\n        WHERE issued_at = ?\n          AND user_id = ?\n          AND type = ?\n          AND points = ?\n          AND canceled_at IS NULL\n          AND cancellation_of_id IS NULL\n          AND (duplicate_key IS NULL OR duplicate_key != ?)\n        ORDER BY id DESC\n        LIMIT ?\n    ");
    $stmt->bindValue(1, $candidate['issued_at']);
    $stmt->bindValue(2, $candidate['user_id'], PDO::PARAM_INT);
    $stmt->bindValue(3, $candidate['type']);
    $stmt->bindValue(4, $candidate['points'], PDO::PARAM_INT);
    $stmt->bindValue(5, $candidate['duplicate_key']);
    $stmt->bindValue(6, max(1, min(20, $limit)), PDO::PARAM_INT);
    $stmt->execute();

    $rows = [];
    foreach ($stmt->fetchAll() as $row) {
        $record = samgyeong_api_fetch_point_record_by_id($db, (int) $row['id']);
        if ($record) {
            $rows[] = $record;
        }
    }

    return $rows;
}

function samgyeong_api_prepare_point_candidate(PDO $db, array $item): array
{
    $userId = (int) ($item['user_id'] ?? 0);
    $type = (string) ($item['type'] ?? '');
    $points = max(0, min(100, (int) ($item['points'] ?? 0)));
    $reason = samgyeong_api_trim_text((string) ($item['reason'] ?? ''), 160);
    $issuedAt = (string) ($item['issued_at'] ?? date('Y-m-d'));
    $clientEventId = samgyeong_api_trim_text((string) ($item['client_event_id'] ?? ''), 120);
    $allowDuplicate = filter_var($item['allow_duplicate'] ?? false, FILTER_VALIDATE_BOOLEAN);
    $duplicateNote = samgyeong_api_trim_text((string) ($item['duplicate_note'] ?? ''), 160);

    if ($userId <= 0) {
        return ['ok' => false, 'error' => 'invalid_user_id'];
    }
    if (!in_array($type, ['merit', 'demerit'], true)) {
        return ['ok' => false, 'error' => 'invalid_type'];
    }
    if ($points <= 0) {
        return ['ok' => false, 'error' => 'invalid_points'];
    }
    if ($reason === '') {
        return ['ok' => false, 'error' => 'empty_reason'];
    }
    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $issuedAt)) {
        return ['ok' => false, 'error' => 'invalid_issued_at'];
    }

    $stmt = $db->prepare("SELECT id FROM users WHERE id = ? AND role IN ('student', 'council') AND COALESCE(is_active, 1) = 1");
    $stmt->execute([$userId]);
    if (!$stmt->fetchColumn()) {
        return ['ok' => false, 'error' => 'target_not_found'];
    }

    $reasonNorm = samgyeong_api_normalize_reason($reason);
    $duplicateKey = samgyeong_api_make_duplicate_key($issuedAt, $userId, $type, $points, $reasonNorm);

    return [
        'ok' => true,
        'user_id' => $userId,
        'type' => $type,
        'points' => $points,
        'reason' => $reason,
        'issued_at' => $issuedAt,
        'client_event_id' => $clientEventId,
        'allow_duplicate' => $allowDuplicate,
        'duplicate_note' => $duplicateNote,
        'reason_norm' => $reasonNorm,
        'duplicate_key' => $duplicateKey,
    ];
}

function samgyeong_api_check_point_duplicate(PDO $db, array $item): array
{
    $candidate = samgyeong_api_prepare_point_candidate($db, $item);
    if (!$candidate['ok']) {
        return $candidate;
    }

    $clientDuplicate = samgyeong_api_find_by_client_event_id($db, $candidate['client_event_id']);
    $hardDuplicate = samgyeong_api_find_hard_duplicate($db, $candidate['duplicate_key']);
    $softDuplicates = samgyeong_api_find_soft_duplicates($db, $candidate);

    return [
        'ok' => true,
        'candidate' => [
            'user_id' => $candidate['user_id'],
            'type' => $candidate['type'],
            'points' => $candidate['points'],
            'reason' => $candidate['reason'],
            'issued_at' => $candidate['issued_at'],
            'duplicate_key' => $candidate['duplicate_key'],
        ],
        'client_event_duplicate' => $clientDuplicate !== null,
        'hard_duplicate' => $hardDuplicate !== null,
        'soft_duplicate' => count($softDuplicates) > 0,
        'client_event_record' => $clientDuplicate,
        'hard_duplicate_record' => $hardDuplicate,
        'soft_duplicate_records' => $softDuplicates,
    ];
}

function samgyeong_api_insert_point(PDO $db, array $issuer, array $item): array
{
    $candidate = samgyeong_api_prepare_point_candidate($db, $item);
    if (!$candidate['ok']) {
        return $candidate;
    }

    $clientDuplicate = samgyeong_api_find_by_client_event_id($db, $candidate['client_event_id']);
    if ($clientDuplicate) {
        return [
            'ok' => true,
            'inserted' => false,
            'duplicate' => true,
            'duplicate_type' => 'client_event_id',
            'id' => (int) $clientDuplicate['id'],
            'existing_record' => $clientDuplicate,
        ];
    }

    $hardDuplicate = samgyeong_api_find_hard_duplicate($db, $candidate['duplicate_key']);
    if ($hardDuplicate && !$candidate['allow_duplicate']) {
        return [
            'ok' => true,
            'inserted' => false,
            'duplicate' => true,
            'duplicate_type' => 'content',
            'id' => (int) $hardDuplicate['id'],
            'existing_record' => $hardDuplicate,
            'message' => '이미 같은 상벌점 기록이 있습니다.',
        ];
    }

    $duplicateOverrideOf = $hardDuplicate && $candidate['allow_duplicate'] ? (int) $hardDuplicate['id'] : null;
    $duplicateNote = $candidate['duplicate_note'] !== '' ? $candidate['duplicate_note'] : null;
    $clientEventId = $candidate['client_event_id'] !== '' ? $candidate['client_event_id'] : null;

    $stmt = $db->prepare("\n        INSERT INTO point_records (\n            user_id, type, points, reason, issuer_id, issued_at,\n            client_event_id, duplicate_key, duplicate_override_of, duplicate_note\n        )\n        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)\n    ");

    try {
        $stmt->execute([
            $candidate['user_id'],
            $candidate['type'],
            $candidate['points'],
            $candidate['reason'],
            (int) $issuer['id'],
            $candidate['issued_at'],
            $clientEventId,
            $candidate['duplicate_key'],
            $duplicateOverrideOf,
            $duplicateNote,
        ]);
    } catch (PDOException $e) {
        $clientDuplicate = samgyeong_api_find_by_client_event_id($db, $candidate['client_event_id']);
        if ($clientDuplicate) {
            return [
                'ok' => true,
                'inserted' => false,
                'duplicate' => true,
                'duplicate_type' => 'client_event_id',
                'id' => (int) $clientDuplicate['id'],
                'existing_record' => $clientDuplicate,
            ];
        }

        $hardDuplicate = samgyeong_api_find_hard_duplicate($db, $candidate['duplicate_key']);
        if ($hardDuplicate && !$candidate['allow_duplicate']) {
            return [
                'ok' => true,
                'inserted' => false,
                'duplicate' => true,
                'duplicate_type' => 'content',
                'id' => (int) $hardDuplicate['id'],
                'existing_record' => $hardDuplicate,
                'message' => '이미 같은 상벌점 기록이 있습니다.',
            ];
        }

        throw $e;
    }

    $newId = (int) $db->lastInsertId();
    return [
        'ok' => true,
        'inserted' => true,
        'duplicate' => false,
        'id' => $newId,
        'duplicate_override_of' => $duplicateOverrideOf,
    ];
}
// POINT_DUPLICATE_V11_END


function samgyeong_api_point_summary(PDO $db): array
{
    $resetAt = function_exists('current_point_reset_at') ? current_point_reset_at($db) : null;
    $resetJoinCondition = $resetAt ? ' AND point_records.created_at > :reset_at' : '';
    $stmt = $db->prepare("
        SELECT
            users.id,
            users.username,
            users.display_name,
            users.hall_key,
            users.year,
            COALESCE(SUM(CASE
                WHEN point_records.type = 'merit'
                 AND point_records.canceled_at IS NULL
                 AND point_records.cancellation_of_id IS NULL
                THEN point_records.points ELSE 0 END), 0) AS merit_total,
            COALESCE(SUM(CASE
                WHEN point_records.type = 'demerit'
                 AND point_records.canceled_at IS NULL
                 AND point_records.cancellation_of_id IS NULL
                THEN point_records.points ELSE 0 END), 0) AS demerit_total
        FROM users
        LEFT JOIN point_records ON point_records.user_id = users.id
            {$resetJoinCondition}
        WHERE users.role IN ('student', 'council')
          AND COALESCE(users.is_active, 1) = 1
        GROUP BY users.id
        -- SGMANAGER_V041_FIX3_GUARDS_SUMMARY
        ORDER BY users.hall_key, users.year DESC, users.display_name, users.username
    ");
    if ($resetAt) {
        $stmt->bindValue(':reset_at', $resetAt);
    }
    $stmt->execute();

    $rows = $stmt->fetchAll();
    foreach ($rows as &$row) {
        $row['id'] = (int) $row['id'];
        $row['year'] = (int) $row['year'];
        $row['merit_total'] = (int) $row['merit_total'];
        $row['demerit_total'] = (int) $row['demerit_total'];
        $row['wish_coupons'] = intdiv($row['merit_total'], 10);
    }

    return $rows;
}


// SGMANAGER_V041_STUDENT_MANAGEMENT_API_HELPERS_FIX2_START
function samgyeong_api_v041_ensure_student_schema(PDO $db): void
{
    $userColumns = [];
    foreach ($db->query('PRAGMA table_info(users)') as $column) {
        $userColumns[(string) $column['name']] = true;
    }

    if (!isset($userColumns['is_active'])) {
        $db->exec('ALTER TABLE users ADD COLUMN is_active INTEGER NOT NULL DEFAULT 1');
    }

    $db->exec('CREATE INDEX IF NOT EXISTS idx_users_active_role ON users(is_active, role)');
}

function samgyeong_api_v041_is_student_role(string $role): bool
{
    return in_array($role, ['student', 'council'], true);
}

function samgyeong_api_v041_is_hall_key(string $hallKey): bool
{
    return in_array($hallKey, ['gyeongcheon', 'gyeongin', 'gyeongmul'], true);
}

function samgyeong_api_v041_hall_meta(string $hallKey): ?array
{
    $map = [
        'gyeongcheon' => ['name' => '경천관', 'meaning' => '敬天', 'color' => 'blue'],
        'gyeongin' => ['name' => '경인관', 'meaning' => '敬人', 'color' => 'gold'],
        'gyeongmul' => ['name' => '경물관', 'meaning' => '敬物', 'color' => 'green'],
    ];

    return $map[$hallKey] ?? null;
}

function samgyeong_api_v041_public_student(?array $row): ?array
{
    if (!$row) {
        return null;
    }

    $row['id'] = (int) $row['id'];
    $row['year'] = (int) ($row['year'] ?? 0);
    $row['is_active'] = (int) ($row['is_active'] ?? 1);

    unset($row['password_hash']);

    return $row;
}

function samgyeong_api_v041_fetch_student(PDO $db, int $id): ?array
{
    samgyeong_api_v041_ensure_student_schema($db);

    $stmt = $db->prepare("
        SELECT id, username, role, display_name, hall_key, year, photo_path,
               COALESCE(is_active, 1) AS is_active, created_at
        FROM users
        WHERE id = ?
          AND role IN ('student', 'council')
        LIMIT 1
    ");
    $stmt->execute([$id]);

    return samgyeong_api_v041_public_student($stmt->fetch() ?: null);
}

function samgyeong_api_v041_username_exists(PDO $db, string $username, ?int $exceptId = null): bool
{
    if ($exceptId !== null) {
        $stmt = $db->prepare('SELECT id FROM users WHERE username = ? AND id != ? LIMIT 1');
        $stmt->execute([$username, $exceptId]);
    } else {
        $stmt = $db->prepare('SELECT id FROM users WHERE username = ? LIMIT 1');
        $stmt->execute([$username]);
    }

    return (bool) $stmt->fetchColumn();
}

function samgyeong_api_v041_sync_hall_member(PDO $db, int $userId): void
{
    samgyeong_api_v041_ensure_student_schema($db);

    $stmt = $db->prepare("
        SELECT id, role, display_name, hall_key, year, photo_path, COALESCE(is_active, 1) AS is_active
        FROM users
        WHERE id = ?
        LIMIT 1
    ");
    $stmt->execute([$userId]);
    $user = $stmt->fetch();

    if (!$user) {
        return;
    }

    $delete = function () use ($db, $userId): void {
        $stmt = $db->prepare('DELETE FROM hall_members WHERE user_id = ?');
        $stmt->execute([$userId]);
    };

    if (!samgyeong_api_v041_is_student_role((string) $user['role']) || (int) ($user['is_active'] ?? 1) !== 1) {
        $delete();
        return;
    }

    $hallKey = (string) ($user['hall_key'] ?? '');
    $meta = samgyeong_api_v041_hall_meta($hallKey);
    $year = (int) ($user['year'] ?? 0);
    $displayName = trim((string) ($user['display_name'] ?? ''));

    if (!$meta || $year < 1 || $year > 3 || $displayName === '') {
        $delete();
        return;
    }

    $roleLabel = ((string) $user['role'] === 'council') ? '학생회' : '학생';
    $photoPath = $user['photo_path'] ?? null;

    $stmt = $db->prepare('SELECT id FROM hall_members WHERE user_id = ? LIMIT 1');
    $stmt->execute([$userId]);
    $hallMemberId = $stmt->fetchColumn();

    if ($hallMemberId) {
        $stmt = $db->prepare("
            UPDATE hall_members
            SET hall_key = ?, hall_name = ?, hall_meaning = ?, hall_color = ?,
                student_name = ?, year = ?, role_label = ?, photo_path = ?
            WHERE user_id = ?
        ");
        $stmt->execute([
            $hallKey,
            $meta['name'],
            $meta['meaning'],
            $meta['color'],
            $displayName,
            $year,
            $roleLabel,
            $photoPath,
            $userId,
        ]);
        return;
    }

    $stmt = $db->prepare("
        INSERT INTO hall_members (
            hall_key, hall_name, hall_meaning, hall_color,
            student_name, year, role_label, sort_order, photo_path, user_id
        )
        VALUES (?, ?, ?, ?, ?, ?, ?, 0, ?, ?)
    ");
    $stmt->execute([
        $hallKey,
        $meta['name'],
        $meta['meaning'],
        $meta['color'],
        $displayName,
        $year,
        $roleLabel,
        $photoPath,
        $userId,
    ]);
}

function samgyeong_api_v041_clean_student_input(array $body, bool $isCreate): array
{
    $username = trim((string) ($body['username'] ?? ''));
    $displayName = samgyeong_api_trim_text((string) ($body['display_name'] ?? $body['displayName'] ?? ''), 80);
    $role = trim((string) ($body['role'] ?? 'student'));
    $hallKey = trim((string) ($body['hall_key'] ?? $body['hallKey'] ?? ''));
    $year = (int) ($body['year'] ?? 0);
    $password = (string) ($body['password'] ?? '');
    $photoPath = array_key_exists('photo_path', $body) ? samgyeong_api_trim_text((string) ($body['photo_path'] ?? ''), 200) : null;
    $isActive = array_key_exists('is_active', $body) ? (int) filter_var($body['is_active'], FILTER_VALIDATE_BOOLEAN) : 1;

    if ($username === '') {
        return ['ok' => false, 'error' => 'empty_username'];
    }
    if (!preg_match('/^[A-Za-z0-9_.-]{3,40}$/', $username)) {
        return ['ok' => false, 'error' => 'invalid_username'];
    }
    if ($displayName === '') {
        return ['ok' => false, 'error' => 'empty_display_name'];
    }
    if (!samgyeong_api_v041_is_student_role($role)) {
        return ['ok' => false, 'error' => 'invalid_role'];
    }
    if (!samgyeong_api_v041_is_hall_key($hallKey)) {
        return ['ok' => false, 'error' => 'invalid_hall_key'];
    }
    if ($year < 1 || $year > 3) {
        return ['ok' => false, 'error' => 'invalid_year'];
    }
    if ($isCreate && strlen($password) < 4) {
        return ['ok' => false, 'error' => 'password_too_short'];
    }
    if ($password !== '' && strlen($password) < 4) {
        return ['ok' => false, 'error' => 'password_too_short'];
    }

    return [
        'ok' => true,
        'username' => $username,
        'display_name' => $displayName,
        'role' => $role,
        'hall_key' => $hallKey,
        'year' => $year,
        'password' => $password,
        'photo_path' => $photoPath === '' ? null : $photoPath,
        'is_active' => $isActive === 1 ? 1 : 0,
    ];
}
// SGMANAGER_V041_STUDENT_MANAGEMENT_API_HELPERS_FIX2_END

// SGMANAGER_V0431_CODE_MANAGEMENT_HELPERS_START
function samgyeong_api_v0431_ensure_schema(PDO $db): void
{
    $db->exec("\n        CREATE TABLE IF NOT EXISTS api_app_access_codes (\n            id INTEGER PRIMARY KEY AUTOINCREMENT,\n            label TEXT NOT NULL DEFAULT '',\n            code_hash TEXT NOT NULL,\n            code_preview TEXT NOT NULL DEFAULT '',\n            created_by INTEGER NOT NULL,\n            created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,\n            revoked_at TEXT,\n            revoked_by INTEGER,\n            FOREIGN KEY(created_by) REFERENCES users(id),\n            FOREIGN KEY(revoked_by) REFERENCES users(id)\n        );\n\n        CREATE TABLE IF NOT EXISTS api_app_access_code_uses (\n            id INTEGER PRIMARY KEY AUTOINCREMENT,\n            code_id INTEGER NOT NULL,\n            user_id INTEGER NOT NULL,\n            used_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,\n            ip_address TEXT,\n            user_agent TEXT,\n            FOREIGN KEY(code_id) REFERENCES api_app_access_codes(id),\n            FOREIGN KEY(user_id) REFERENCES users(id)\n        );\n\n        CREATE TABLE IF NOT EXISTS api_app_settings (\n            key TEXT PRIMARY KEY,\n            value TEXT NOT NULL,\n            updated_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP\n        );\n    ");

    $tokenColumns = [];
    foreach ($db->query('PRAGMA table_info(api_tokens)') as $column) {
        $tokenColumns[(string) $column['name']] = true;
    }
    if (!isset($tokenColumns['app_access_code_id'])) {
        $db->exec('ALTER TABLE api_tokens ADD COLUMN app_access_code_id INTEGER');
    }

    $db->exec('CREATE INDEX IF NOT EXISTS idx_api_app_access_codes_active ON api_app_access_codes(revoked_at, created_at)');
    $db->exec('CREATE INDEX IF NOT EXISTS idx_api_app_access_code_uses_code ON api_app_access_code_uses(code_id, used_at)');
    $db->exec('CREATE INDEX IF NOT EXISTS idx_api_tokens_app_access_code ON api_tokens(app_access_code_id)');
}

function samgyeong_api_v0431_get_setting(PDO $db, string $key): ?string
{
    samgyeong_api_v0431_ensure_schema($db);
    $stmt = $db->prepare('SELECT value FROM api_app_settings WHERE key = ? LIMIT 1');
    $stmt->execute([$key]);
    $value = $stmt->fetchColumn();
    return $value === false ? null : (string) $value;
}

function samgyeong_api_v0431_set_setting(PDO $db, string $key, string $value): void
{
    samgyeong_api_v0431_ensure_schema($db);
    $stmt = $db->prepare("\n        INSERT INTO api_app_settings (key, value, updated_at)\n        VALUES (?, ?, CURRENT_TIMESTAMP)\n        ON CONFLICT(key) DO UPDATE SET value = excluded.value, updated_at = CURRENT_TIMESTAMP\n    ");
    $stmt->execute([$key, $value]);
}

function samgyeong_api_v0431_active_code_count(PDO $db): int
{
    samgyeong_api_v0431_ensure_schema($db);
    return (int) $db->query('SELECT COUNT(*) FROM api_app_access_codes WHERE revoked_at IS NULL')->fetchColumn();
}

function samgyeong_api_v0431_policy(PDO $db): array
{
    samgyeong_api_v0431_ensure_schema($db);
    return [
        'required' => samgyeong_api_v0431_get_setting($db, 'app_access_required') === '1',
        'active_code_count' => samgyeong_api_v0431_active_code_count($db),
        'updated_at' => samgyeong_api_v0431_get_setting($db, 'app_access_updated_at'),
    ];
}

function samgyeong_api_v0431_generate_code(): string
{
    $alphabet = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789';
    $parts = [];
    for ($p = 0; $p < 2; $p++) {
        $s = '';
        for ($i = 0; $i < 4; $i++) {
            $s .= $alphabet[random_int(0, strlen($alphabet) - 1)];
        }
        $parts[] = $s;
    }
    return 'SG-' . implode('-', $parts);
}

function samgyeong_api_v0431_code_preview(string $code): string
{
    $clean = preg_replace('/\s+/u', '', trim($code)) ?? trim($code);
    $last = function_exists('mb_substr') ? mb_substr($clean, -4, null, 'UTF-8') : substr($clean, -4);
    return '••••' . $last;
}

function samgyeong_api_v0431_find_code(PDO $db, string $code): ?array
{
    // SGMANAGER_FIX11_ALL_CODES_REUSABLE_FIND_START
    samgyeong_api_v0431_ensure_schema($db);
    if (function_exists('samgyeong_api_fix9_ensure_code_guard_schema')) {
        samgyeong_api_fix9_ensure_code_guard_schema($db);
    } elseif (function_exists('samgyeong_api_fix7_ensure_code_guard_schema')) {
        samgyeong_api_fix7_ensure_code_guard_schema($db);
    }

    $normalized = function_exists('samgyeong_api_fix7_normalized_code')
        ? samgyeong_api_fix7_normalized_code($code)
        : strtoupper(preg_replace('/\s+/u', '', trim($code)) ?? trim($code));
    $fingerprint = hash('sha256', $normalized);

    $stmt = $db->prepare("\n        SELECT c.*, COUNT(uses.id) AS use_count, MAX(uses.used_at) AS last_used_at\n        FROM api_app_access_codes AS c\n        LEFT JOIN api_app_access_code_uses AS uses ON uses.code_id = c.id\n        WHERE c.revoked_at IS NULL\n          AND (c.code_fingerprint IS NULL OR c.code_fingerprint = :fingerprint)\n        GROUP BY c.id\n        ORDER BY c.id DESC\n    ");
    $stmt->execute([':fingerprint' => $fingerprint]);

    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
        if (password_verify($code, (string)$row['code_hash'])) {
            return $row; // 모든 활성 학생회 코드는 폐기 전까지 고정/반복 사용 가능.
        }
    }

    return null;
    // SGMANAGER_FIX11_ALL_CODES_REUSABLE_FIND_END
}

function samgyeong_api_v0431_enforce_login_code(PDO $db, array $body): void
{
    samgyeong_api_v0431_ensure_schema($db);
    $policy = samgyeong_api_v0431_policy($db);
    $code = trim((string) ($body['app_access_code'] ?? $body['access_code'] ?? $body['student_council_code'] ?? ''));

    // 코드 정책이 아직 켜지지 않았으면 최초 관리자 로그인을 허용한다.
    if (!$policy['required']) {
        if ($code !== '') {
            $matched = samgyeong_api_v0431_find_code($db, $code);
            if ($matched) {
                $GLOBALS['samgyeong_v0431_app_access_code_id'] = (int) $matched['id'];
            }
        }
        return;
    }

    if ($code === '') {
        samgyeong_api_json(['ok' => false, 'error' => 'missing_app_access_code', 'message' => '학생회 고유 코드를 입력해 주세요.'], 403);
    }

    $matched = samgyeong_api_v0431_find_code($db, $code);
    if (!$matched) {
        samgyeong_api_json(['ok' => false, 'error' => 'invalid_app_access_code', 'message' => '학생회 고유 코드가 올바르지 않습니다.'], 403);
    }

    $GLOBALS['samgyeong_v0431_app_access_code_id'] = (int) $matched['id'];
}

function samgyeong_api_v0431_after_login(PDO $db, array $user, string $tokenHash): void
{
    // SGMANAGER_FIX11_ALL_CODES_REUSABLE_AFTER_LOGIN_START
    samgyeong_api_v0431_ensure_schema($db);
    if (function_exists('samgyeong_api_fix9_ensure_code_guard_schema')) {
        samgyeong_api_fix9_ensure_code_guard_schema($db);
    } elseif (function_exists('samgyeong_api_fix7_ensure_code_guard_schema')) {
        samgyeong_api_fix7_ensure_code_guard_schema($db);
    }

    $codeId = isset($GLOBALS['samgyeong_v0431_app_access_code_id']) ? (int)$GLOBALS['samgyeong_v0431_app_access_code_id'] : 0;
    if ($codeId <= 0) {
        return;
    }

    $isAdminCode = function_exists('samgyeong_api_fix9_is_admin_code')
        ? samgyeong_api_fix9_is_admin_code($db, $codeId)
        : (function_exists('samgyeong_api_fix7_is_admin_code') ? samgyeong_api_fix7_is_admin_code($db, $codeId) : false);

    if ($isAdminCode) {
        $admin = null;
        if (function_exists('samgyeong_api_fix9_admin_user')) {
            $admin = samgyeong_api_fix9_admin_user($db);
        } elseif (function_exists('samgyeong_api_fix7_admin_user')) {
            $admin = samgyeong_api_fix7_admin_user($db);
        } else {
            $stmt = $db->query("SELECT * FROM users WHERE username = 'admin' ORDER BY id LIMIT 1");
            $admin = $stmt ? $stmt->fetch(PDO::FETCH_ASSOC) : false;
        }

        $adminId = $admin ? (int)($admin['id'] ?? 0) : 0;
        $isAdminUser = ((int)($user['id'] ?? 0) === $adminId)
            || ((string)($user['username'] ?? '') === 'admin')
            || ((string)($user['role'] ?? '') === 'admin');

        if (!$isAdminUser) {
            $stmt = $db->prepare('UPDATE api_tokens SET revoked_at = CURRENT_TIMESTAMP WHERE token_hash = ? AND revoked_at IS NULL');
            $stmt->execute([$tokenHash]);
            samgyeong_api_json([
                'ok' => false,
                'error' => 'admin_code_admin_only',
                'message' => '관리자 코드는 admin 계정에서만 사용할 수 있습니다.',
            ], 403);
        }
    }

    $stmt = $db->prepare('UPDATE api_tokens SET app_access_code_id = ? WHERE token_hash = ?');
    $stmt->execute([$codeId, $tokenHash]);

    // 사용 이력은 남기지만, 이제 사용 이력 때문에 코드가 만료되지는 않는다.
    $stmt = $db->prepare('INSERT INTO api_app_access_code_uses (code_id, user_id, ip_address, user_agent) VALUES (?, ?, ?, ?)');
    $stmt->execute([
        $codeId,
        (int)$user['id'],
        $_SERVER['REMOTE_ADDR'] ?? null,
        $_SERVER['HTTP_USER_AGENT'] ?? null,
    ]);
    // SGMANAGER_FIX11_ALL_CODES_REUSABLE_AFTER_LOGIN_END
}

function samgyeong_api_v0431_public_code(PDO $db, array $row): array
{
    $codeId = (int) $row['id'];
    $stmt = $db->prepare("\n        SELECT u.id AS user_id, u.username, u.display_name, MAX(uses.used_at) AS used_at\n        FROM api_app_access_code_uses AS uses\n        JOIN users AS u ON u.id = uses.user_id\n        WHERE uses.code_id = ?\n        GROUP BY u.id\n        ORDER BY used_at DESC\n        LIMIT 20\n    ");
    $stmt->execute([$codeId]);
    $usedBy = [];
    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $u) {
        $usedBy[] = [
            'user_id' => (int) $u['user_id'],
            'username' => $u['username'],
            'display_name' => $u['display_name'],
            'used_at' => $u['used_at'],
        ];
    }

    return [
        'id' => $codeId,
        'label' => $row['label'] ?? '',
        'code_preview' => $row['code_preview'] ?? '',
        'is_active' => empty($row['revoked_at']),
        'created_by' => isset($row['created_by']) ? (int) $row['created_by'] : null,
        'created_by_name' => $row['created_by_name'] ?? null,
        'created_at' => $row['created_at'] ?? null,
        'revoked_by' => isset($row['revoked_by']) ? (int) $row['revoked_by'] : null,
        'revoked_by_name' => $row['revoked_by_name'] ?? null,
        'revoked_at' => $row['revoked_at'] ?? null,
        'use_count' => isset($row['use_count']) ? (int) $row['use_count'] : 0,
        'last_used_at' => $row['last_used_at'] ?? null,
        'used_by' => $usedBy,
    ];
}

function samgyeong_api_v0431_fetch_code(PDO $db, int $id): ?array
{
    $stmt = $db->prepare("\n        SELECT c.*,\n               creator.display_name AS created_by_name,\n               revoker.display_name AS revoked_by_name,\n               COUNT(uses.id) AS use_count,\n               MAX(uses.used_at) AS last_used_at\n        FROM api_app_access_codes AS c\n        LEFT JOIN users AS creator ON creator.id = c.created_by\n        LEFT JOIN users AS revoker ON revoker.id = c.revoked_by\n        LEFT JOIN api_app_access_code_uses AS uses ON uses.code_id = c.id\n        WHERE c.id = ?\n        GROUP BY c.id\n        LIMIT 1\n    ");
    $stmt->execute([$id]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    return $row ? samgyeong_api_v0431_public_code($db, $row) : null;
}
// SGMANAGER_V0431_CODE_MANAGEMENT_HELPERS_END

// SGMANAGER_V046_HOMEPAGE_DISCIPLINE_HELPERS_START
function samgyeong_api_v046_strip_text(string $text): string
{
    $text = html_entity_decode(strip_tags($text), ENT_QUOTES | ENT_HTML5, 'UTF-8');
    $text = preg_replace('/\s+/u', ' ', $text) ?? $text;
    return trim($text);
}

function samgyeong_api_v046_public_derived_rule(array $rule): array
{
    $metric = (string) ($rule['metric'] ?? 'demerit_total');
    $comparator = (string) ($rule['comparator'] ?? 'gte');

    return [
        'id' => (int) ($rule['id'] ?? 0),
        'title' => (string) ($rule['title'] ?? '홈페이지 징계 기준'),
        'metric' => $metric,
        'metric_label' => match ($metric) {
            'net_score' => '통합 점수',
            'merit_total' => '상점 합계',
            default => '벌점 합계',
        },
        'comparator' => $comparator,
        'comparator_label' => $comparator === 'lte' ? '이하' : '이상',
        'threshold' => (int) ($rule['threshold'] ?? 0),
        'action_label' => (string) ($rule['action_label'] ?? '징계 검토'),
        'description' => (string) ($rule['description'] ?? ''),
        'is_active' => 1,
        'created_at' => null,
        'updated_at' => null,
        'source' => 'homepage',
    ];
}

function samgyeong_api_v046_rule_from_text(string $rawText, int $id): ?array
{
    $text = samgyeong_api_v046_strip_text($rawText);
    if ($text === '') {
        return null;
    }

    $isDisciplineLike = preg_match('/징계|생활지도|상담|위원회|경고|제재|처분|대상/u', $text);
    $hasPoint = preg_match('/벌점|상벌점|점수|점/u', $text);
    if (!$isDisciplineLike || !$hasPoint) {
        return null;
    }

    $threshold = null;
    $comparator = 'gte';

    $patterns = [
        '/벌점\s*(?:누적|합계)?\s*(\d+)\s*점?\s*(이상|초과|이하|미만)?/u',
        '/(?:누적|합계)?\s*(\d+)\s*점\s*(이상|초과|이하|미만).*?(?:징계|상담|생활지도|위원회|경고|제재|처분|대상)/u',
        '/(?:징계|상담|생활지도|위원회|경고|제재|처분|대상).*?(\d+)\s*점\s*(이상|초과|이하|미만)?/u',
    ];

    foreach ($patterns as $pattern) {
        if (preg_match($pattern, $text, $m)) {
            $threshold = (int) $m[1];
            $operator = $m[2] ?? '';
            if (in_array($operator, ['이하', '미만'], true)) {
                $comparator = 'lte';
            }
            break;
        }
    }

    if ($threshold === null) {
        return null;
    }

    $metric = 'demerit_total';
    if (preg_match('/통합|합산|총합/u', $text) && !preg_match('/벌점/u', $text)) {
        $metric = 'net_score';
    }
    if (preg_match('/상점/u', $text) && !preg_match('/벌점/u', $text)) {
        $metric = 'merit_total';
    }

    $title = $text;
    if (function_exists('mb_substr')) {
        $title = mb_substr($title, 0, 80, 'UTF-8');
    } else {
        $title = substr($title, 0, 80);
    }

    return [
        'id' => $id,
        'title' => $title,
        'metric' => $metric,
        'comparator' => $comparator,
        'threshold' => $threshold,
        'action_label' => $title,
        'description' => $text,
    ];
}

function samgyeong_api_v046_extract_homepage_discipline_rules(PDO $db): array
{
    $rules = [];
    $seen = [];
    $id = 1;

    $tableNames = [];
    foreach ($db->query("SELECT name FROM sqlite_master WHERE type='table'") as $row) {
        $tableNames[(string) $row['name']] = true;
    }

    foreach (['point_list_rules', 'point_rules'] as $table) {
        if (!isset($tableNames[$table])) {
            continue;
        }

        try {
            $rows = $db->query('SELECT rowid AS _rowid_, * FROM ' . $table)->fetchAll(PDO::FETCH_ASSOC);
        } catch (Throwable $e) {
            continue;
        }

        foreach ($rows as $row) {
            $parts = [];
            foreach ($row as $key => $value) {
                if ($value === null) {
                    continue;
                }
                $keyLower = strtolower((string) $key);
                if (in_array($keyLower, ['id', '_rowid_', 'created_at', 'updated_at', 'sort_order'], true)) {
                    continue;
                }
                if (is_scalar($value)) {
                    $parts[] = (string) $value;
                }
            }

            $rule = samgyeong_api_v046_rule_from_text(implode(' ', $parts), $id);
            if (!$rule) {
                continue;
            }

            $key = $rule['metric'] . '|' . $rule['comparator'] . '|' . $rule['threshold'] . '|' . $rule['title'];
            if (isset($seen[$key])) {
                continue;
            }

            $seen[$key] = true;
            $rules[] = $rule;
            $id++;
        }
    }

    // If the criteria are written directly in PHP view files instead of DB rows,
    // parse the public rule/discipline views as a fallback.
    $viewFiles = [
        '/var/www/html/views/point-rules.php',
        '/var/www/html/views/discipline-awards.php',
        '/var/www/html/views/student-life-rules.php',
        '/var/www/html/views/school-rules.php',
    ];

    foreach ($viewFiles as $file) {
        if (!is_file($file)) {
            continue;
        }

        $content = file_get_contents($file);
        if ($content === false) {
            continue;
        }

        $chunks = preg_split('/[\r\n。.!?]+/u', $content) ?: [];
        foreach ($chunks as $chunk) {
            $rule = samgyeong_api_v046_rule_from_text($chunk, $id);
            if (!$rule) {
                continue;
            }

            $key = $rule['metric'] . '|' . $rule['comparator'] . '|' . $rule['threshold'] . '|' . $rule['title'];
            if (isset($seen[$key])) {
                continue;
            }

            $seen[$key] = true;
            $rules[] = $rule;
            $id++;
        }
    }

    // Last fallback: if admins already saved v0.4.4 rules, use them,
    // but homepage-derived rules always take priority.
    if (!$rules && isset($tableNames['api_discipline_rules'])) {
        try {
            $rows = $db->query("SELECT * FROM api_discipline_rules WHERE is_active = 1 ORDER BY threshold ASC, id DESC")->fetchAll(PDO::FETCH_ASSOC);
            foreach ($rows as $row) {
                $rules[] = [
                    'id' => $id++,
                    'title' => (string) ($row['title'] ?? '징계 기준'),
                    'metric' => (string) ($row['metric'] ?? 'demerit_total'),
                    'comparator' => (string) ($row['comparator'] ?? 'gte'),
                    'threshold' => (int) ($row['threshold'] ?? 0),
                    'action_label' => (string) ($row['action_label'] ?? '징계 검토'),
                    'description' => (string) ($row['description'] ?? ''),
                ];
            }
        } catch (Throwable $e) {
            // Ignore fallback errors.
        }
    }

    return array_map('samgyeong_api_v046_public_derived_rule', $rules);
}

function samgyeong_api_v046_rule_matches(array $rule, array $studentSummary): bool
{
    $merit = (int) ($studentSummary['merit_total'] ?? 0);
    $demerit = (int) ($studentSummary['demerit_total'] ?? 0);
    $net = $merit - $demerit;

    $metric = (string) ($rule['metric'] ?? 'demerit_total');
    $value = match ($metric) {
        'net_score' => $net,
        'merit_total' => $merit,
        default => $demerit,
    };

    $threshold = (int) ($rule['threshold'] ?? 0);
    return (string) ($rule['comparator'] ?? 'gte') === 'lte'
        ? $value <= $threshold
        : $value >= $threshold;
}
// SGMANAGER_V046_HOMEPAGE_DISCIPLINE_HELPERS_END

// SGMANAGER_V047_DISCIPLINE_SOURCE_HELPERS_START
function samgyeong_api_v047_text_clean(string $text): string
{
    $text = preg_replace('/<script\b[^>]*>.*?<\/script>/is', ' ', $text) ?? $text;
    $text = preg_replace('/<style\b[^>]*>.*?<\/style>/is', ' ', $text) ?? $text;
    $text = html_entity_decode(strip_tags($text), ENT_QUOTES | ENT_HTML5, 'UTF-8');
    $text = str_replace(["\r\n", "\r"], "\n", $text);
    $text = preg_replace('/[ \t\x{00A0}]+/u', ' ', $text) ?? $text;
    $text = preg_replace('/\n\s*\n+/u', "\n", $text) ?? $text;
    return trim($text);
}

function samgyeong_api_v047_short_text(string $text, int $limit = 120): string
{
    $text = trim($text);
    if (function_exists('mb_strlen') && function_exists('mb_substr')) {
        return mb_strlen($text, 'UTF-8') > $limit ? mb_substr($text, 0, $limit, 'UTF-8') . '…' : $text;
    }
    return strlen($text) > $limit ? substr($text, 0, $limit) . '…' : $text;
}

function samgyeong_api_v047_public_rule(array $rule): array
{
    $metric = (string) ($rule['metric'] ?? 'demerit_total');
    $comparator = (string) ($rule['comparator'] ?? 'gte');

    return [
        'id' => (int) ($rule['id'] ?? 0),
        'title' => (string) ($rule['title'] ?? '홈페이지 징계 기준'),
        'metric' => $metric,
        'metric_label' => match ($metric) {
            'net_score' => '통합 점수',
            'merit_total' => '상점 합계',
            default => '벌점 합계',
        },
        'comparator' => $comparator,
        'comparator_label' => $comparator === 'lte' ? '이하' : '이상',
        'threshold' => (int) ($rule['threshold'] ?? 0),
        'action_label' => (string) ($rule['action_label'] ?? '징계 검토'),
        'description' => (string) ($rule['description'] ?? ''),
        'is_active' => 1,
        'created_at' => null,
        'updated_at' => null,
        'source' => (string) ($rule['source'] ?? 'rules/discipline'),
    ];
}

function samgyeong_api_v047_extract_section(string $text, string $sectionTitle): string
{
    $titles = ['징계 기준', '포상 기준', '적용 원칙', '징계 및 포상 기준', '징계 및 포상'];
    $pattern = '/(' . implode('|', array_map(fn($t) => preg_quote($t, '/'), $titles)) . ')/u';

    $matches = [];
    if (!preg_match_all($pattern, $text, $matches, PREG_OFFSET_CAPTURE)) {
        return $text;
    }

    $positions = [];
    foreach ($matches[1] as $m) {
        $positions[] = ['title' => $m[0], 'pos' => $m[1]];
    }
    usort($positions, fn($a, $b) => $a['pos'] <=> $b['pos']);

    $start = null;
    $end = strlen($text);

    foreach ($positions as $idx => $item) {
        if ($item['title'] === $sectionTitle || ($sectionTitle === '징계 기준' && $item['title'] === '징계 및 포상 기준')) {
            $start = $item['pos'];
            if (isset($positions[$idx + 1])) {
                $end = $positions[$idx + 1]['pos'];
            }
            break;
        }
    }

    if ($start === null) {
        return $text;
    }

    return trim(substr($text, $start, max(0, $end - $start)));
}

function samgyeong_api_v047_make_rule_from_sentence(string $sentence, int $id): ?array
{
    $sentence = samgyeong_api_v047_text_clean($sentence);
    if ($sentence === '') {
        return null;
    }

    if (!preg_match('/징계|생활지도|상담|위원회|경고|처분|제재|퇴학|정학|봉사|대상/u', $sentence)) {
        return null;
    }

    $metric = 'demerit_total';
    $comparator = 'gte';
    $threshold = null;

    $patterns = [
        '/벌점\s*(?:누적|합계|총점|총합)?\s*(\-?\d+)\s*점?\s*(이상|초과|이하|미만)?/u',
        '/(?:누적|합계|총점|총합)?\s*벌점\s*(\-?\d+)\s*점?\s*(이상|초과|이하|미만)?/u',
        '/(?:누적|합계|총점|총합)?\s*(\-?\d+)\s*점\s*(이상|초과|이하|미만).*?(?:징계|생활지도|상담|위원회|경고|처분|제재|퇴학|정학|봉사|대상)/u',
        '/(?:징계|생활지도|상담|위원회|경고|처분|제재|퇴학|정학|봉사|대상).*?(\-?\d+)\s*점\s*(이상|초과|이하|미만)?/u',
    ];

    foreach ($patterns as $pattern) {
        if (preg_match($pattern, $sentence, $m)) {
            $threshold = (int) $m[1];
            $op = $m[2] ?? '';
            if (in_array($op, ['이하', '미만'], true)) {
                $comparator = 'lte';
            }
            break;
        }
    }

    if ($threshold === null) {
        return null;
    }

    if (preg_match('/통합|합산|상벌점/u', $sentence) && !preg_match('/벌점/u', $sentence)) {
        $metric = 'net_score';
    }
    if (preg_match('/상점|포상/u', $sentence) && !preg_match('/벌점|징계/u', $sentence)) {
        $metric = 'merit_total';
    }

    // This app currently applies discipline alerting only.
    // If the sentence is clearly an award/merit-only rule, do not make a discipline target rule.
    if ($metric === 'merit_total' && preg_match('/포상|상점|시상|장학/u', $sentence) && !preg_match('/징계|생활지도|상담|위원회|경고|처분/u', $sentence)) {
        return null;
    }

    $title = samgyeong_api_v047_short_text($sentence, 90);
    $actionLabel = $title;
    if (preg_match('/(상담|생활지도|경고|징계위원회|징계 위원회|정학|퇴학|봉사|처분|제재)[^,.。\n]*/u', $sentence, $am)) {
        $actionLabel = samgyeong_api_v047_short_text($am[0], 80);
    }

    return [
        'id' => $id,
        'title' => $title,
        'metric' => $metric,
        'comparator' => $comparator,
        'threshold' => abs($threshold),
        'action_label' => $actionLabel,
        'description' => $sentence,
        'source' => 'rules/discipline',
    ];
}

function samgyeong_api_v047_rules_from_text(string $text): array
{
    $clean = samgyeong_api_v047_text_clean($text);
    $disciplineSection = samgyeong_api_v047_extract_section($clean, '징계 기준');
    $principles = samgyeong_api_v047_extract_section($clean, '적용 원칙');

    $candidates = [];
    $lines = preg_split('/[\n。.!?]+/u', $disciplineSection) ?: [];
    foreach ($lines as $line) {
        $line = trim($line);
        if ($line !== '') {
            $candidates[] = $line;
        }
    }

    // Also parse compact table/card text where rows became one long line.
    if (count($candidates) < 2) {
        $split = preg_split('/(?=(?:벌점|누적|합계|총점|총합)?\s*\d+\s*점\s*(?:이상|이하|초과|미만))/u', $disciplineSection) ?: [];
        foreach ($split as $part) {
            $part = trim($part);
            if ($part !== '') {
                $candidates[] = $part;
            }
        }
    }

    $rules = [];
    $seen = [];
    $id = 1;

    foreach ($candidates as $candidate) {
        $rule = samgyeong_api_v047_make_rule_from_sentence($candidate, $id);
        if (!$rule) {
            continue;
        }

        $key = $rule['metric'] . '|' . $rule['comparator'] . '|' . $rule['threshold'] . '|' . $rule['action_label'];
        if (isset($seen[$key])) {
            continue;
        }

        $seen[$key] = true;
        $rules[] = samgyeong_api_v047_public_rule($rule);
        $id++;
    }

    // Keep application principles in the description of every rule so the app/server have the source context.
    if ($principles !== '' && $principles !== $clean) {
        foreach ($rules as &$rule) {
            $rule['description'] = trim($rule['description'] . "\n\n적용 원칙: " . samgyeong_api_v047_short_text($principles, 300));
        }
        unset($rule);
    }

    return $rules;
}

function samgyeong_api_v047_collect_rule_source_text(PDO $db): array
{
    $texts = [];
    $debug = [
        'db_matches' => [],
        'file_matches' => [],
    ];

    // 1) Database scan: find the actual content behind /rules/discipline even if table names changed.
    $tables = [];
    foreach ($db->query("SELECT name FROM sqlite_master WHERE type='table'") as $row) {
        $name = (string) $row['name'];
        if (preg_match('/token|session|password|point_records|api_tokens/i', $name)) {
            continue;
        }
        $tables[] = $name;
    }

    foreach ($tables as $table) {
        try {
            $cols = $db->query('PRAGMA table_info(' . $db->quote($table) . ')')->fetchAll(PDO::FETCH_ASSOC);
        } catch (Throwable $e) {
            continue;
        }

        $textCols = [];
        foreach ($cols as $col) {
            $type = strtoupper((string) ($col['type'] ?? ''));
            $name = (string) ($col['name'] ?? '');
            if ($name === '' || preg_match('/password|hash|token/i', $name)) {
                continue;
            }
            if ($type === '' || str_contains($type, 'TEXT') || str_contains($type, 'CHAR') || str_contains($type, 'CLOB') || str_contains($type, 'VARCHAR')) {
                $textCols[] = $name;
            }
        }

        if (!$textCols) {
            continue;
        }

        $selectParts = array_map(fn($c) => '"' . str_replace('"', '""', $c) . '"', $textCols);
        try {
            $rows = $db->query('SELECT ' . implode(', ', $selectParts) . ' FROM "' . str_replace('"', '""', $table) . '" LIMIT 500')->fetchAll(PDO::FETCH_ASSOC);
        } catch (Throwable $e) {
            continue;
        }

        foreach ($rows as $row) {
            $joined = implode("\n", array_map('strval', array_filter($row, fn($v) => $v !== null && $v !== '')));
            if ($joined === '') {
                continue;
            }
            if (preg_match('/rules\/discipline|징계\s*및\s*포상|징계\s*기준|포상\s*기준|적용\s*원칙|생활지도|징계위원회/u', $joined)) {
                $texts[] = $joined;
                $debug['db_matches'][] = $table;
            }
        }
    }

    // 2) File scan: route/controller/view files often contain the banner content directly.
    $roots = ['/var/www/html'];
    $extensions = ['php', 'html', 'phtml', 'blade.php', 'twig', 'json', 'md', 'txt'];
    foreach ($roots as $root) {
        if (!is_dir($root)) {
            continue;
        }

        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS),
            RecursiveIteratorIterator::SELF_FIRST
        );

        foreach ($iterator as $file) {
            if (!$file instanceof SplFileInfo || !$file->isFile()) {
                continue;
            }

            $path = $file->getPathname();
            if (preg_match('#/(vendor|storage/data|node_modules|\.git)/#', $path)) {
                continue;
            }

            if ($file->getSize() > 800000) {
                continue;
            }

            $lower = strtolower($path);
            $okExt = false;
            foreach ($extensions as $ext) {
                if (str_ends_with($lower, '.' . $ext)) {
                    $okExt = true;
                    break;
                }
            }
            if (!$okExt && !preg_match('/discipline|rules|point|award|생활|징계|포상/u', $path)) {
                continue;
            }

            $content = @file_get_contents($path);
            if ($content === false || $content === '') {
                continue;
            }

            if (preg_match('/rules\/discipline|징계\s*및\s*포상|징계\s*기준|포상\s*기준|적용\s*원칙|생활지도|징계위원회/u', $content)) {
                $texts[] = $content;
                $debug['file_matches'][] = str_replace('/var/www/html/', '', $path);
            }
        }
    }

    $debug['db_matches'] = array_values(array_unique($debug['db_matches']));
    $debug['file_matches'] = array_values(array_unique($debug['file_matches']));

    return ['texts' => $texts, 'debug' => $debug];
}

function samgyeong_api_v047_extract_homepage_rules(PDO $db): array
{
    $collected = samgyeong_api_v047_collect_rule_source_text($db);
    $rules = [];
    $seen = [];

    foreach ($collected['texts'] as $text) {
        foreach (samgyeong_api_v047_rules_from_text($text) as $rule) {
            $key = $rule['metric'] . '|' . $rule['comparator'] . '|' . $rule['threshold'] . '|' . $rule['action_label'];
            if (isset($seen[$key])) {
                continue;
            }

            $seen[$key] = true;
            $rule['id'] = count($rules) + 1;
            $rules[] = $rule;
        }
    }

    return ['rules' => $rules, 'debug' => $collected['debug']];
}

function samgyeong_api_v047_rule_matches(array $rule, array $studentSummary): bool
{
    $merit = (int) ($studentSummary['merit_total'] ?? 0);
    $demerit = (int) ($studentSummary['demerit_total'] ?? 0);
    $net = $merit - $demerit;

    $metric = (string) ($rule['metric'] ?? 'demerit_total');
    $value = match ($metric) {
        'net_score' => $net,
        'merit_total' => $merit,
        default => $demerit,
    };

    $threshold = (int) ($rule['threshold'] ?? 0);
    return (string) ($rule['comparator'] ?? 'gte') === 'lte'
        ? $value <= $threshold
        : $value >= $threshold;
}
// SGMANAGER_V047_DISCIPLINE_SOURCE_HELPERS_END

// SGMANAGER_V049_POINT_RULES_DIRECT_HELPERS_START
function samgyeong_api_v049_table_columns(PDO $db, string $table): array
{
    $cols = [];
    try {
        foreach ($db->query('PRAGMA table_info("' . str_replace('"', '""', $table) . '")') as $col) {
            $cols[(string) $col['name']] = true;
        }
    } catch (Throwable $e) {
        return [];
    }
    return $cols;
}

function samgyeong_api_v049_clean_text(string $text): string
{
    $text = html_entity_decode(strip_tags($text), ENT_QUOTES | ENT_HTML5, 'UTF-8');
    $text = preg_replace('/\s+/u', ' ', $text) ?? $text;
    return trim($text);
}

function samgyeong_api_v049_short_text(string $text, int $limit = 100): string
{
    $text = samgyeong_api_v049_clean_text($text);
    if (function_exists('mb_strlen') && function_exists('mb_substr')) {
        return mb_strlen($text, 'UTF-8') > $limit ? mb_substr($text, 0, $limit, 'UTF-8') . '…' : $text;
    }
    return strlen($text) > $limit ? substr($text, 0, $limit) . '…' : $text;
}

function samgyeong_api_v049_row_text(array $row): string
{
    $parts = [];
    foreach ($row as $key => $value) {
        if ($value === null || $value === '') {
            continue;
        }

        $keyLower = strtolower((string) $key);
        if (in_array($keyLower, ['id', 'sort_order', 'created_at', 'updated_at', 'created_by'], true)) {
            continue;
        }

        if (is_scalar($value)) {
            $parts[] = (string) $value;
        }
    }
    return samgyeong_api_v049_clean_text(implode(' ', $parts));
}

function samgyeong_api_v049_cell(array $row, array $names): string
{
    foreach ($names as $name) {
        if (array_key_exists($name, $row) && $row[$name] !== null && $row[$name] !== '') {
            return samgyeong_api_v049_clean_text((string) $row[$name]);
        }
    }
    return '';
}

function samgyeong_api_v049_public_rule(array $rule): array
{
    $metric = (string) ($rule['metric'] ?? 'demerit_total');
    $comparator = (string) ($rule['comparator'] ?? 'gte');

    return [
        'id' => (int) ($rule['id'] ?? 0),
        'title' => (string) ($rule['title'] ?? '징계 기준'),
        'metric' => $metric,
        'metric_label' => match ($metric) {
            'net_score' => '통합 점수',
            'merit_total' => '상점 합계',
            default => '벌점 합계',
        },
        'comparator' => $comparator,
        'comparator_label' => $comparator === 'lte' ? '이하' : '이상',
        'threshold' => (int) ($rule['threshold'] ?? 0),
        'action_label' => (string) ($rule['action_label'] ?? '징계 검토'),
        'description' => (string) ($rule['description'] ?? ''),
        'is_active' => 1,
        'created_at' => null,
        'updated_at' => null,
        'source' => 'point_rules',
    ];
}

function samgyeong_api_v049_is_discipline_row(array $row, string $text): bool
{
    $category = samgyeong_api_v049_cell($row, ['category', 'section', 'group_key', 'rule_type', 'type', 'kind', 'tab']);
    $categoryLower = strtolower($category);

    if (preg_match('/reward|award|merit|상점|포상|시상|혜택/u', $categoryLower) && !preg_match('/discipline|demerit|징계|벌점/u', $categoryLower)) {
        return false;
    }

    if (preg_match('/discipline|demerit/u', $categoryLower) || preg_match('/징계|벌점|생활지도|상담|위원회|경고|처분|제재|퇴학|정학|봉사/u', $category . ' ' . $text)) {
        return true;
    }

    return false;
}

function samgyeong_api_v049_extract_threshold(array $row, string $text): ?int
{
    foreach (['threshold', 'min_points', 'required_points', 'point_threshold', 'points_required', 'score_threshold'] as $key) {
        if (array_key_exists($key, $row) && is_numeric($row[$key])) {
            return abs((int) $row[$key]);
        }
    }

    $scoreText = samgyeong_api_v049_cell($row, ['score_label', 'score', 'points', 'point', 'range_label', 'criteria', 'condition']);
    $candidateText = trim($scoreText . ' ' . $text);

    $patterns = [
        '/벌점\s*(?:누적|합계|총점|총합)?\s*(\-?\d+)\s*점?\s*(?:이상|초과|이하|미만)?/u',
        '/(?:누적|합계|총점|총합)?\s*벌점\s*(\-?\d+)\s*점?\s*(?:이상|초과|이하|미만)?/u',
        '/(\-?\d+)\s*[~\-]\s*\d+\s*점/u',
        '/(\-?\d+)\s*점\s*(?:이상|초과|이하|미만)?/u',
        '/(\-?\d+)/u',
    ];

    foreach ($patterns as $pattern) {
        if (preg_match($pattern, $candidateText, $m)) {
            return abs((int) $m[1]);
        }
    }

    return null;
}

function samgyeong_api_v049_extract_comparator(array $row, string $text): string
{
    $candidate = samgyeong_api_v049_cell($row, ['comparator', 'operator', 'condition']) . ' ' . $text;
    if (preg_match('/lte|less|이하|미만/u', $candidate)) {
        return 'lte';
    }
    return 'gte';
}

function samgyeong_api_v049_rule_from_point_rule_row(array $row, int $id, string $principleText): ?array
{
    $text = samgyeong_api_v049_row_text($row);
    if (!samgyeong_api_v049_is_discipline_row($row, $text)) {
        return null;
    }

    $threshold = samgyeong_api_v049_extract_threshold($row, $text);
    if ($threshold === null) {
        return null;
    }

    $metric = 'demerit_total';
    $metricText = samgyeong_api_v049_cell($row, ['metric', 'score_type', 'target_metric']) . ' ' . $text;
    if (preg_match('/net|통합|합산/u', $metricText) && !preg_match('/벌점|demerit/u', $metricText)) {
        $metric = 'net_score';
    }

    $title = samgyeong_api_v049_cell($row, ['title', 'name', 'label', 'rule_name']);
    $action = samgyeong_api_v049_cell($row, ['action_label', 'action', 'result', 'reward', 'penalty', 'description', 'body', 'content']);
    $scoreLabel = samgyeong_api_v049_cell($row, ['score_label', 'score', 'points', 'point', 'range_label', 'criteria', 'condition']);

    if ($title === '') {
        $title = trim(($scoreLabel !== '' ? $scoreLabel . ' · ' : '') . ($action !== '' ? $action : $text));
    }

    if ($action === '') {
        $action = $title;
    }

    $description = $text;
    if ($principleText !== '') {
        $description .= "\n\n적용 원칙: " . $principleText;
    }

    return samgyeong_api_v049_public_rule([
        'id' => $id,
        'title' => samgyeong_api_v049_short_text($title, 100),
        'metric' => $metric,
        'comparator' => samgyeong_api_v049_extract_comparator($row, $text),
        'threshold' => $threshold,
        'action_label' => samgyeong_api_v049_short_text($action, 100),
        'description' => samgyeong_api_v049_short_text($description, 500),
    ]);
}

function samgyeong_api_v049_get_point_rules(PDO $db): array
{
    $cols = samgyeong_api_v049_table_columns($db, 'point_rules');
    if (!$cols) {
        return ['rules' => [], 'debug' => ['error' => 'point_rules_missing', 'row_count' => 0, 'columns' => []]];
    }

    $orderParts = [];
    foreach (['category', 'sort_order', 'id'] as $col) {
        if (isset($cols[$col])) {
            $orderParts[] = '"' . $col . '"';
        }
    }
    $orderBy = $orderParts ? (' ORDER BY ' . implode(', ', $orderParts)) : '';

    $rows = $db->query('SELECT * FROM point_rules' . $orderBy)->fetchAll(PDO::FETCH_ASSOC);

    $principles = [];
    foreach ($rows as $row) {
        $category = samgyeong_api_v049_cell($row, ['category', 'section', 'group_key', 'rule_type', 'type', 'kind', 'tab']);
        $text = samgyeong_api_v049_row_text($row);
        if (preg_match('/principle|원칙/u', strtolower($category) . ' ' . $text)) {
            $principles[] = $text;
        }
    }
    $principleText = samgyeong_api_v049_short_text(implode(' / ', array_unique(array_filter($principles))), 300);

    $rules = [];
    $seen = [];
    foreach ($rows as $row) {
        if (isset($cols['is_active']) && array_key_exists('is_active', $row) && (int) $row['is_active'] === 0) {
            continue;
        }

        $rule = samgyeong_api_v049_rule_from_point_rule_row($row, count($rules) + 1, $principleText);
        if (!$rule) {
            continue;
        }

        $key = $rule['metric'] . '|' . $rule['comparator'] . '|' . $rule['threshold'] . '|' . $rule['action_label'];
        if (isset($seen[$key])) {
            continue;
        }

        $seen[$key] = true;
        $rule['id'] = count($rules) + 1;
        $rules[] = $rule;
    }

    return [
        'rules' => $rules,
        'debug' => [
            'row_count' => count($rows),
            'rule_count' => count($rules),
            'columns' => array_keys($cols),
            'principles_found' => count($principles),
        ],
    ];
}

function samgyeong_api_v049_rule_matches(array $rule, array $studentSummary): bool
{
    $merit = (int) ($studentSummary['merit_total'] ?? 0);
    $demerit = (int) ($studentSummary['demerit_total'] ?? 0);
    $net = $merit - $demerit;

    $value = match ((string) ($rule['metric'] ?? 'demerit_total')) {
        'net_score' => $net,
        'merit_total' => $merit,
        default => $demerit,
    };

    $threshold = (int) ($rule['threshold'] ?? 0);
    return (string) ($rule['comparator'] ?? 'gte') === 'lte'
        ? $value <= $threshold
        : $value >= $threshold;
}
// SGMANAGER_V049_POINT_RULES_DIRECT_HELPERS_END

// SGMANAGER_V050_CANONICAL_DISCIPLINE_HELPERS_START
function samgyeong_api_v050_discipline_principle(): string
{
    return '적용 원칙: 개인 기준은 개인 누적 벌점, 학년 기준은 같은 학년 소속 학생의 벌점 합계, 관 기준은 같은 학생관 소속 학생의 벌점 합계, 전체 기준은 전체 활성 학생의 벌점 합계를 기준으로 자동 판단합니다. 여러 단계가 동시에 충족되면 각 단위별 가장 높은 단계만 알림에 표시합니다. 상점은 별도로 누적하며 징계 기준 산정에는 벌점 합계를 사용합니다.';
}

function samgyeong_api_v050_rule(int $id, string $scope, int $threshold, string $action): array
{
    $scopeLabel = match ($scope) {
        'personal' => '개인',
        'year' => '학년',
        'hall' => '관',
        'global' => '전체',
        default => $scope,
    };

    $title = '-' . $threshold . '점 · ' . $scopeLabel . ' · ' . $action;

    return [
        'id' => $id,
        'title' => $title,
        'metric' => 'demerit_total',
        'metric_label' => $scopeLabel . ' 벌점 합계',
        'comparator' => 'gte',
        'comparator_label' => '이상',
        'threshold' => $threshold,
        'action_label' => $action,
        'description' => samgyeong_api_v050_discipline_principle(),
        'is_active' => 1,
        'created_at' => null,
        'updated_at' => null,
        'source' => 'canonical_discipline_rules',
        'scope' => $scope,
        'scope_label' => $scopeLabel,
    ];
}

function samgyeong_api_v050_canonical_rules(): array
{
    $rows = [
        ['personal', 3, '참회록(반성문) 작성'],
        ['personal', 5, '버피 또는 토끼뜀 20회, 참회록(반성문) 작성'],
        ['personal', 8, '버피 또는 토끼뜀 30회, 참회록(반성문) 작성, 삼경원 관찰 꼬리표 3일 부착'],
        ['personal', 10, '버피 또는 토끼뜀 40회, 예절 교육기간 1일'],
        ['personal', 13, '버피 또는 토끼뜀 50회, 예절 교육기간 2일, 직속 3학년 선배(관장) 연대 참회록 작성'],
        ['personal', 15, '퇴학 처리 (재입학 불가)'],

        ['year', 10, '학년 전체 꼬리표 3일 부착, 학년 전체 참회록(반성문) 작성'],
        ['year', 15, '학년 릴레이 버피 또는 토끼뜀 20회, 릴레이 참회록(반성문) 작성, 삼경원 관찰 꼬리표 3일 부착'],
        ['year', 20, '학년 릴레이 버피 또는 토끼뜀 30회, 학년 예절 교육기간 1일'],
        ['year', 25, '학년 릴레이 버피 또는 토끼뜀 40회, 학년 예절 교육기간 2일'],
        ['year', 30, '학년 전체 집합'],

        ['hall', 10, '관 전체 꼬리표 3일 부착, 관 전체 참회록(반성문) 작성'],
        ['hall', 15, '관 소속 인원 릴레이 버피 또는 토끼뜀 20회, 릴레이 참회록(반성문) 작성, 삼경원 관찰 꼬리표 3일 부착'],
        ['hall', 20, '관 소속 인원 릴레이 버피 또는 토끼뜀 30회, 관 예절 교육기간 1일'],
        ['hall', 25, '관 소속 인원 릴레이 버피 또는 토끼뜀 40회, 관 예절 교육기간 2일'],
        ['hall', 30, '관 전체 집합'],

        ['global', 25, '전체 점호 실시 (삼경원 및 3학년 주도)'],
    ];

    $rules = [];
    $id = 1;
    foreach ($rows as $row) {
        $rules[] = samgyeong_api_v050_rule($id++, $row[0], $row[1], $row[2]);
    }

    return $rules;
}

function samgyeong_api_v050_pick_highest_rule(array $rules, string $scope, int $value): ?array
{
    $matched = null;
    foreach ($rules as $rule) {
        if (($rule['scope'] ?? '') !== $scope) {
            continue;
        }
        if ($value >= (int) $rule['threshold']) {
            if ($matched === null || (int) $rule['threshold'] > (int) $matched['threshold']) {
                $matched = $rule;
            }
        }
    }
    return $matched;
}

function samgyeong_api_v050_discipline_targets(PDO $db): array
{
    $rules = samgyeong_api_v050_canonical_rules();
    $summary = samgyeong_api_point_summary($db);

    $yearTotals = [];
    $hallTotals = [];
    $globalTotal = 0;

    foreach ($summary as $row) {
        $demerit = (int) ($row['demerit_total'] ?? 0);
        $year = (int) ($row['year'] ?? 0);
        $hall = (string) ($row['hall_key'] ?? '');

        if ($year > 0) {
            $yearTotals[$year] = ($yearTotals[$year] ?? 0) + $demerit;
        }
        if ($hall !== '') {
            $hallTotals[$hall] = ($hallTotals[$hall] ?? 0) + $demerit;
        }
        $globalTotal += $demerit;
    }

    $targets = [];

    foreach ($summary as $row) {
        $studentDemerit = (int) ($row['demerit_total'] ?? 0);
        $year = (int) ($row['year'] ?? 0);
        $hall = (string) ($row['hall_key'] ?? '');

        $matched = [];

        $personalRule = samgyeong_api_v050_pick_highest_rule($rules, 'personal', $studentDemerit);
        if ($personalRule !== null) {
            $personalRule['description'] .= ' 현재 개인 벌점: ' . $studentDemerit . '점.';
            $matched[] = $personalRule;
        }

        if ($year > 0) {
            $yearTotal = (int) ($yearTotals[$year] ?? 0);
            $yearRule = samgyeong_api_v050_pick_highest_rule($rules, 'year', $yearTotal);
            if ($yearRule !== null) {
                $yearRule['description'] .= ' 현재 ' . $year . '학년 벌점 합계: ' . $yearTotal . '점.';
                $matched[] = $yearRule;
            }
        }

        if ($hall !== '') {
            $hallTotal = (int) ($hallTotals[$hall] ?? 0);
            $hallRule = samgyeong_api_v050_pick_highest_rule($rules, 'hall', $hallTotal);
            if ($hallRule !== null) {
                $hallRule['description'] .= ' 현재 학생관 벌점 합계: ' . $hallTotal . '점.';
                $matched[] = $hallRule;
            }
        }

        $globalRule = samgyeong_api_v050_pick_highest_rule($rules, 'global', $globalTotal);
        if ($globalRule !== null) {
            $globalRule['description'] .= ' 현재 전체 벌점 합계: ' . $globalTotal . '점.';
            $matched[] = $globalRule;
        }

        if (!$matched) {
            continue;
        }

        $targets[] = [
            'student' => [
                'id' => (int) $row['id'],
                'username' => $row['username'] ?? '',
                'display_name' => $row['display_name'] ?? '',
                'hall_key' => $row['hall_key'] ?? '',
                'year' => (int) ($row['year'] ?? 0),
                'role' => 'student',
                'is_active' => 1,
            ],
            'merit_total' => (int) ($row['merit_total'] ?? 0),
            'demerit_total' => $studentDemerit,
            'net_score' => (int) ($row['merit_total'] ?? 0) - $studentDemerit,
            'matched_rules' => $matched,
            'group_totals' => [
                'year' => $year > 0 ? (int) ($yearTotals[$year] ?? 0) : 0,
                'hall' => $hall !== '' ? (int) ($hallTotals[$hall] ?? 0) : 0,
                'global' => $globalTotal,
            ],
        ];
    }

    usort($targets, function (array $a, array $b): int {
        return ($b['demerit_total'] <=> $a['demerit_total'])
            ?: ($a['net_score'] <=> $b['net_score']);
    });

    return [
        'rules' => $rules,
        'targets' => $targets,
        'summary' => [
            'target_count' => count($targets),
            'global_demerit_total' => $globalTotal,
            'year_totals' => $yearTotals,
            'hall_totals' => $hallTotals,
        ],
    ];
}
// SGMANAGER_V050_CANONICAL_DISCIPLINE_HELPERS_END

// SGMANAGER_FIX4_TOTAL_DISCIPLINE_HELPERS_START
function samgyeong_api_fix4_discipline_principle(): string
{
    return '적용 원칙: 상점과 벌점을 합산한 총합 점수를 기준으로 징계 기준을 판단합니다.';
}

function samgyeong_api_fix4_rule(int $id, string $scope, int $threshold, string $action): array
{
    $scopeLabel = match ($scope) {
        'personal' => '개인',
        'year' => '학년',
        'hall' => '관',
        'global' => '전체',
        default => $scope,
    };

    $title = '-' . $threshold . '점 · ' . $scopeLabel . ' · ' . $action;

    return [
        'id' => $id,
        'title' => $title,
        'metric' => 'net_score',
        'metric_label' => $scopeLabel . ' 총합 점수',
        'comparator' => 'lte',
        'comparator_label' => '이하',
        'threshold' => $threshold,
        'action_label' => $action,
        'description' => samgyeong_api_fix4_discipline_principle(),
        'is_active' => 1,
        'created_at' => null,
        'updated_at' => null,
        'source' => 'canonical_total_score_discipline_rules',
        'scope' => $scope,
        'scope_label' => $scopeLabel,
    ];
}

function samgyeong_api_fix4_canonical_rules(): array
{
    $rows = [
        ['personal', 3, '참회록(반성문) 작성'],
        ['personal', 5, '버피 또는 토끼뜀 20회, 참회록(반성문) 작성'],
        ['personal', 8, '버피 또는 토끼뜀 30회, 참회록(반성문) 작성, 삼경원 관찰 꼬리표 3일 부착'],
        ['personal', 10, '버피 또는 토끼뜀 40회, 예절 교육기간 1일'],
        ['personal', 13, '버피 또는 토끼뜀 50회, 예절 교육기간 2일, 직속 3학년 선배(관장) 연대 참회록 작성'],
        ['personal', 15, '퇴학 처리 (재입학 불가)'],

        ['year', 10, '학년 전체 꼬리표 3일 부착, 학년 전체 참회록(반성문) 작성'],
        ['year', 15, '학년 릴레이 버피 또는 토끼뜀 20회, 릴레이 참회록(반성문) 작성, 삼경원 관찰 꼬리표 3일 부착'],
        ['year', 20, '학년 릴레이 버피 또는 토끼뜀 30회, 학년 예절 교육기간 1일'],
        ['year', 25, '학년 릴레이 버피 또는 토끼뜀 40회, 학년 예절 교육기간 2일'],
        ['year', 30, '학년 전체 집합'],

        ['hall', 10, '관 전체 꼬리표 3일 부착, 관 전체 참회록(반성문) 작성'],
        ['hall', 15, '관 소속 인원 릴레이 버피 또는 토끼뜀 20회, 릴레이 참회록(반성문) 작성, 삼경원 관찰 꼬리표 3일 부착'],
        ['hall', 20, '관 소속 인원 릴레이 버피 또는 토끼뜀 30회, 관 예절 교육기간 1일'],
        ['hall', 25, '관 소속 인원 릴레이 버피 또는 토끼뜀 40회, 관 예절 교육기간 2일'],
        ['hall', 30, '관 전체 집합'],

        ['global', 25, '전체 점호 실시 (삼경원 및 3학년 주도)'],
    ];

    $rules = [];
    $id = 1;
    foreach ($rows as $row) {
        $rules[] = samgyeong_api_fix4_rule($id++, $row[0], $row[1], $row[2]);
    }
    return $rules;
}

function samgyeong_api_fix4_pick_highest_rule(array $rules, string $scope, int $totalScore): ?array
{
    $matched = null;
    foreach ($rules as $rule) {
        if (($rule['scope'] ?? '') !== $scope) {
            continue;
        }
        $threshold = abs((int) ($rule['threshold'] ?? 0));
        if ($totalScore <= -$threshold) {
            if ($matched === null || $threshold > abs((int) $matched['threshold'])) {
                $matched = $rule;
            }
        }
    }
    return $matched;
}

function samgyeong_api_fix4_row_total_score(array $row): int
{
    return (int) ($row['merit_total'] ?? 0) - (int) ($row['demerit_total'] ?? 0);
}

function samgyeong_api_fix4_discipline_targets(PDO $db): array
{
    $rules = samgyeong_api_fix4_canonical_rules();
    $summary = samgyeong_api_point_summary($db);

    $yearTotals = [];
    $hallTotals = [];
    $globalTotal = 0;

    foreach ($summary as $row) {
        $total = samgyeong_api_fix4_row_total_score($row);
        $year = (int) ($row['year'] ?? 0);
        $hall = (string) ($row['hall_key'] ?? '');

        if ($year > 0) {
            $yearTotals[$year] = ($yearTotals[$year] ?? 0) + $total;
        }
        if ($hall !== '') {
            $hallTotals[$hall] = ($hallTotals[$hall] ?? 0) + $total;
        }
        $globalTotal += $total;
    }

    $targets = [];

    foreach ($summary as $row) {
        $meritTotal = (int) ($row['merit_total'] ?? 0);
        $demeritTotal = (int) ($row['demerit_total'] ?? 0);
        $studentTotal = $meritTotal - $demeritTotal;
        $year = (int) ($row['year'] ?? 0);
        $hall = (string) ($row['hall_key'] ?? '');
        $matched = [];

        $personalRule = samgyeong_api_fix4_pick_highest_rule($rules, 'personal', $studentTotal);
        if ($personalRule !== null) {
            $personalRule['description'] .= ' 현재 개인 총합 점수: ' . $studentTotal . '점.';
            $matched[] = $personalRule;
        }

        if ($year > 0) {
            $yearTotal = (int) ($yearTotals[$year] ?? 0);
            $yearRule = samgyeong_api_fix4_pick_highest_rule($rules, 'year', $yearTotal);
            if ($yearRule !== null) {
                $yearRule['description'] .= ' 현재 ' . $year . '학년 총합 점수: ' . $yearTotal . '점.';
                $matched[] = $yearRule;
            }
        }

        if ($hall !== '') {
            $hallTotal = (int) ($hallTotals[$hall] ?? 0);
            $hallRule = samgyeong_api_fix4_pick_highest_rule($rules, 'hall', $hallTotal);
            if ($hallRule !== null) {
                $hallRule['description'] .= ' 현재 학생관 총합 점수: ' . $hallTotal . '점.';
                $matched[] = $hallRule;
            }
        }

        $globalRule = samgyeong_api_fix4_pick_highest_rule($rules, 'global', $globalTotal);
        if ($globalRule !== null) {
            $globalRule['description'] .= ' 현재 전체 총합 점수: ' . $globalTotal . '점.';
            $matched[] = $globalRule;
        }

        if (!$matched) {
            continue;
        }

        $targets[] = [
            'student' => [
                'id' => (int) $row['id'],
                'username' => $row['username'] ?? '',
                'display_name' => $row['display_name'] ?? '',
                'hall_key' => $row['hall_key'] ?? '',
                'year' => (int) ($row['year'] ?? 0),
                'role' => 'student',
                'is_active' => 1,
            ],
            'merit_total' => $meritTotal,
            'demerit_total' => $demeritTotal,
            'net_score' => $studentTotal,
            'total_score' => $studentTotal,
            'matched_rules' => $matched,
            'group_totals' => [
                'year' => $year > 0 ? (int) ($yearTotals[$year] ?? 0) : 0,
                'hall' => $hall !== '' ? (int) ($hallTotals[$hall] ?? 0) : 0,
                'global' => $globalTotal,
            ],
        ];
    }

    usort($targets, function (array $a, array $b): int {
        return ($a['net_score'] <=> $b['net_score'])
            ?: strcmp((string)($a['student']['display_name'] ?? ''), (string)($b['student']['display_name'] ?? ''));
    });

    return [
        'rules' => $rules,
        'targets' => $targets,
        'summary' => [
            'target_count' => count($targets),
            'global_total_score' => $globalTotal,
            'year_totals' => $yearTotals,
            'hall_totals' => $hallTotals,
        ],
    ];
}
// SGMANAGER_FIX4_TOTAL_DISCIPLINE_HELPERS_END

// SGMANAGER_FIX5_WARNING_DISCIPLINE_HELPERS_START
function samgyeong_api_fix5_now(): string
{
    return date('Y-m-d H:i:s');
}

function samgyeong_api_fix5_rule(int $id, string $scope, int $threshold, string $action): array
{
    $scopeLabel = match ($scope) {
        'personal' => '개인',
        'year' => '학년',
        'hall' => '관',
        'global' => '전체',
        default => $scope,
    };

    return [
        'id' => $id,
        'title' => '-' . $threshold . '점 | ' . $action,
        'metric' => 'net_score',
        'metric_label' => $scopeLabel . ' 합계 점수',
        'comparator' => 'lte',
        'comparator_label' => '이하',
        'threshold' => $threshold,
        'action_label' => $action,
        'description' => '',
        'is_active' => 1,
        'created_at' => null,
        'updated_at' => null,
        'source' => 'canonical_sum_score_discipline_rules',
        'scope' => $scope,
        'scope_label' => $scopeLabel,
    ];
}

function samgyeong_api_fix5_canonical_rules(): array
{
    $rows = [
        ['personal', 3, '참회록(반성문) 작성'],
        ['personal', 5, '버피 또는 토끼뜀 20회, 참회록(반성문) 작성'],
        ['personal', 8, '버피 또는 토끼뜀 30회, 참회록(반성문) 작성, 삼경원 관찰 꼬리표 3일 부착'],
        ['personal', 10, '버피 또는 토끼뜀 40회, 예절 교육기간 1일'],
        ['personal', 13, '버피 또는 토끼뜀 50회, 예절 교육기간 2일, 직속 3학년 선배(관장) 연대 참회록 작성'],
        ['personal', 15, '퇴학 처리 (재입학 불가)'],

        ['year', 10, '학년 전체 꼬리표 3일 부착, 학년 전체 참회록(반성문) 작성'],
        ['year', 15, '학년 릴레이 버피 또는 토끼뜀 20회, 릴레이 참회록(반성문) 작성, 삼경원 관찰 꼬리표 3일 부착'],
        ['year', 20, '학년 릴레이 버피 또는 토끼뜀 30회, 학년 예절 교육기간 1일'],
        ['year', 25, '학년 릴레이 버피 또는 토끼뜀 40회, 학년 예절 교육기간 2일'],
        ['year', 30, '학년 전체 집합'],

        ['hall', 10, '관 전체 꼬리표 3일 부착, 관 전체 참회록(반성문) 작성'],
        ['hall', 15, '관 소속 인원 릴레이 버피 또는 토끼뜀 20회, 릴레이 참회록(반성문) 작성, 삼경원 관찰 꼬리표 3일 부착'],
        ['hall', 20, '관 소속 인원 릴레이 버피 또는 토끼뜀 30회, 관 예절 교육기간 1일'],
        ['hall', 25, '관 소속 인원 릴레이 버피 또는 토끼뜀 40회, 관 예절 교육기간 2일'],
        ['hall', 30, '관 전체 집합'],

        ['global', 25, '전체 점호 실시 (삼경원 및 3학년 주도)'],
    ];

    $rules = [];
    $id = 1;
    foreach ($rows as $row) {
        $rules[] = samgyeong_api_fix5_rule($id++, $row[0], $row[1], $row[2]);
    }
    return $rules;
}

function samgyeong_api_fix5_pick_highest_rule(array $rules, string $scope, int $sumScore): ?array
{
    $matched = null;
    foreach ($rules as $rule) {
        if (($rule['scope'] ?? '') !== $scope) {
            continue;
        }
        $threshold = abs((int) ($rule['threshold'] ?? 0));
        if ($sumScore <= -$threshold) {
            if ($matched === null || $threshold > abs((int) $matched['threshold'])) {
                $matched = $rule;
            }
        }
    }
    return $matched;
}

function samgyeong_api_fix5_row_sum_score(array $row): int
{
    return (int) ($row['merit_total'] ?? 0) - (int) ($row['demerit_total'] ?? 0);
}

function samgyeong_api_fix5_student_payload(array $row): array
{
    return [
        'id' => (int) $row['id'],
        'username' => $row['username'] ?? '',
        'display_name' => $row['display_name'] ?? '',
        'hall_key' => $row['hall_key'] ?? '',
        'year' => (int) ($row['year'] ?? 0),
        'role' => 'student',
        'is_active' => 1,
    ];
}

function samgyeong_api_fix5_ensure_state_table(PDO $db): void
{
    $db->exec("CREATE TABLE IF NOT EXISTS discipline_alert_state (
        student_id INTEGER PRIMARY KEY,
        first_seen_at TEXT NOT NULL,
        last_seen_at TEXT NOT NULL,
        resolved_at TEXT NULL
    )");
}

function samgyeong_api_fix5_state_map(PDO $db): array
{
    samgyeong_api_fix5_ensure_state_table($db);
    $stmt = $db->query("SELECT student_id, first_seen_at, last_seen_at, resolved_at FROM discipline_alert_state");
    $map = [];
    foreach (($stmt ? $stmt->fetchAll(PDO::FETCH_ASSOC) : []) as $row) {
        $map[(int) $row['student_id']] = $row;
    }
    return $map;
}

function samgyeong_api_fix5_sync_warning_state(PDO $db, array $candidates): array
{
    samgyeong_api_fix5_ensure_state_table($db);
    $now = samgyeong_api_fix5_now();
    $candidateIds = [];
    foreach ($candidates as $candidate) {
        $studentId = (int) ($candidate['student']['id'] ?? 0);
        if ($studentId <= 0) {
            continue;
        }
        $candidateIds[$studentId] = true;
        $stmt = $db->prepare("INSERT INTO discipline_alert_state (student_id, first_seen_at, last_seen_at, resolved_at)
            VALUES (:student_id, :now, :now, NULL)
            ON CONFLICT(student_id) DO UPDATE SET last_seen_at = excluded.last_seen_at, resolved_at = NULL");
        $stmt->execute([':student_id' => $studentId, ':now' => $now]);
    }

    $state = samgyeong_api_fix5_state_map($db);
    foreach ($state as $studentId => $row) {
        if (!isset($candidateIds[$studentId]) && ($row['resolved_at'] ?? null) === null) {
            $stmt = $db->prepare("UPDATE discipline_alert_state SET resolved_at = :now, last_seen_at = :now WHERE student_id = :student_id");
            $stmt->execute([':student_id' => $studentId, ':now' => $now]);
        }
    }

    return samgyeong_api_fix5_state_map($db);
}

function samgyeong_api_fix5_candidate_targets(PDO $db): array
{
    $rules = samgyeong_api_fix5_canonical_rules();
    $summary = samgyeong_api_point_summary($db);

    $rowsById = [];
    $yearTotals = [];
    $hallTotals = [];
    $globalTotal = 0;

    foreach ($summary as $row) {
        $studentId = (int) ($row['id'] ?? 0);
        if ($studentId <= 0) {
            continue;
        }

        $rowsById[$studentId] = $row;
        $sumScore = samgyeong_api_fix5_row_sum_score($row);
        $year = (int) ($row['year'] ?? 0);
        $hall = (string) ($row['hall_key'] ?? '');

        if ($year > 0) {
            $yearTotals[$year] = ($yearTotals[$year] ?? 0) + $sumScore;
        }
        if ($hall !== '') {
            $hallTotals[$hall] = ($hallTotals[$hall] ?? 0) + $sumScore;
        }
        $globalTotal += $sumScore;
    }

    $yearRules = [];
    foreach ($yearTotals as $year => $total) {
        $rule = samgyeong_api_fix5_pick_highest_rule($rules, 'year', (int) $total);
        if ($rule !== null) {
            $rule['description'] = $year . '학년 합계 점수: ' . (int) $total . '점.';
            $yearRules[(int) $year] = $rule;
        }
    }

    $hallRules = [];
    foreach ($hallTotals as $hall => $total) {
        $rule = samgyeong_api_fix5_pick_highest_rule($rules, 'hall', (int) $total);
        if ($rule !== null) {
            $rule['description'] = '관 합계 점수: ' . (int) $total . '점.';
            $hallRules[(string) $hall] = $rule;
        }
    }

    $globalRule = samgyeong_api_fix5_pick_highest_rule($rules, 'global', $globalTotal);
    if ($globalRule !== null) {
        $globalRule['description'] = '전체 합계 점수: ' . $globalTotal . '점.';
    }

    $candidates = [];
    foreach ($rowsById as $studentId => $row) {
        $meritTotal = (int) ($row['merit_total'] ?? 0);
        $demeritTotal = (int) ($row['demerit_total'] ?? 0);
        $studentSum = $meritTotal - $demeritTotal;
        $year = (int) ($row['year'] ?? 0);
        $hall = (string) ($row['hall_key'] ?? '');
        $matched = [];

        $personalRule = samgyeong_api_fix5_pick_highest_rule($rules, 'personal', $studentSum);
        if ($personalRule !== null) {
            $personalRule['description'] = '개인 합계 점수: ' . $studentSum . '점.';
            $matched[] = $personalRule;
        }

        if ($year > 0 && isset($yearRules[$year])) {
            $matched[] = $yearRules[$year];
        }
        if ($hall !== '' && isset($hallRules[$hall])) {
            $matched[] = $hallRules[$hall];
        }
        if ($globalRule !== null) {
            $matched[] = $globalRule;
        }

        if (!$matched) {
            continue;
        }

        $candidates[] = [
            'student' => samgyeong_api_fix5_student_payload($row),
            'merit_total' => $meritTotal,
            'demerit_total' => $demeritTotal,
            'net_score' => $studentSum,
            'total_score' => $studentSum,
            'sum_score' => $studentSum,
            'matched_rules' => $matched,
            'group_totals' => [
                'year' => $year > 0 ? (int) ($yearTotals[$year] ?? 0) : 0,
                'hall' => $hall !== '' ? (int) ($hallTotals[$hall] ?? 0) : 0,
                'global' => $globalTotal,
            ],
        ];
    }

    usort($candidates, function (array $a, array $b): int {
        return ($a['net_score'] <=> $b['net_score'])
            ?: strcmp((string)($a['student']['display_name'] ?? ''), (string)($b['student']['display_name'] ?? ''));
    });

    return [
        'rules' => $rules,
        'candidates' => $candidates,
        'summary' => [
            'candidate_count' => count($candidates),
            'global_sum_score' => $globalTotal,
            'year_totals' => $yearTotals,
            'hall_totals' => $hallTotals,
        ],
    ];
}

function samgyeong_api_fix5_classified_discipline(PDO $db): array
{
    $base = samgyeong_api_fix5_candidate_targets($db);
    $state = samgyeong_api_fix5_sync_warning_state($db, $base['candidates']);
    $nowTs = time();
    $warningSeconds = 24 * 60 * 60;

    $warnings = [];
    $targets = [];

    foreach ($base['candidates'] as $candidate) {
        $studentId = (int) ($candidate['student']['id'] ?? 0);
        $firstSeen = (string) ($state[$studentId]['first_seen_at'] ?? samgyeong_api_fix5_now());
        $firstSeenTs = strtotime($firstSeen) ?: $nowTs;
        $elapsed = max(0, $nowTs - $firstSeenTs);
        $remaining = max(0, $warningSeconds - $elapsed);

        $candidate['first_seen_at'] = $firstSeen;
        $candidate['warning_until'] = date('Y-m-d H:i:s', $firstSeenTs + $warningSeconds);
        $candidate['remaining_warning_seconds'] = $remaining;

        if ($elapsed >= $warningSeconds) {
            $candidate['alert_status'] = 'target';
            $targets[] = $candidate;
        } else {
            $candidate['alert_status'] = 'warning';
            $warnings[] = $candidate;
        }
    }

    return [
        'rules' => $base['rules'],
        'warnings' => $warnings,
        'targets' => $targets,
        'summary' => $base['summary'] + [
            'warning_count' => count($warnings),
            'target_count' => count($targets),
            'warning_hours' => 24,
        ],
    ];
}
// SGMANAGER_FIX5_WARNING_DISCIPLINE_HELPERS_END

// SGMANAGER_FIX7_ADMIN_CODE_GUARD_HELPERS_START
function samgyeong_api_fix7_ensure_code_guard_schema(PDO $db): void
{
    if (function_exists('samgyeong_api_v0431_ensure_schema')) {
        samgyeong_api_v0431_ensure_schema($db);
    }

    $db->exec("CREATE TABLE IF NOT EXISTS api_app_access_codes (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        label TEXT NOT NULL DEFAULT '',
        code_hash TEXT NOT NULL,
        code_preview TEXT NOT NULL DEFAULT '',
        created_by INTEGER NOT NULL,
        created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
        revoked_at TEXT,
        revoked_by INTEGER
    )");
    $db->exec("CREATE TABLE IF NOT EXISTS api_app_access_code_uses (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        code_id INTEGER NOT NULL,
        user_id INTEGER NOT NULL,
        used_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
        ip_address TEXT,
        user_agent TEXT
    )");
    $db->exec("CREATE TABLE IF NOT EXISTS api_app_settings (
        key TEXT PRIMARY KEY,
        value TEXT NOT NULL,
        updated_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP
    )");

    $columns = [];
    foreach ($db->query('PRAGMA table_info(api_app_access_codes)') as $column) {
        $columns[(string) $column['name']] = true;
    }
    if (!isset($columns['owner_user_id'])) {
        $db->exec('ALTER TABLE api_app_access_codes ADD COLUMN owner_user_id INTEGER');
    }
    if (!isset($columns['is_system_protected'])) {
        $db->exec('ALTER TABLE api_app_access_codes ADD COLUMN is_system_protected INTEGER NOT NULL DEFAULT 0');
    }
    if (!isset($columns['code_fingerprint'])) {
        $db->exec('ALTER TABLE api_app_access_codes ADD COLUMN code_fingerprint TEXT');
    }

    $db->exec('CREATE INDEX IF NOT EXISTS idx_fix7_app_access_codes_owner ON api_app_access_codes(owner_user_id, is_system_protected)');
    $db->exec('CREATE INDEX IF NOT EXISTS idx_fix7_app_access_codes_fingerprint ON api_app_access_codes(code_fingerprint)');
    $db->exec('CREATE INDEX IF NOT EXISTS idx_fix7_app_access_uses_code_user ON api_app_access_code_uses(code_id, user_id)');
}

function samgyeong_api_fix7_normalized_code(string $code): string
{
    return strtoupper(preg_replace('/\s+/u', '', trim($code)) ?? trim($code));
}

function samgyeong_api_fix7_code_fingerprint(string $code): string
{
    return hash('sha256', samgyeong_api_fix7_normalized_code($code));
}

function samgyeong_api_fix7_admin_user(PDO $db): ?array
{
    $stmt = $db->query("SELECT * FROM users WHERE username = 'admin' ORDER BY id LIMIT 1");
    $admin = $stmt ? $stmt->fetch(PDO::FETCH_ASSOC) : false;
    if ($admin) {
        return $admin;
    }
    $stmt = $db->query("SELECT * FROM users WHERE role = 'admin' ORDER BY id LIMIT 1");
    $admin = $stmt ? $stmt->fetch(PDO::FETCH_ASSOC) : false;
    return $admin ?: null;
}

function samgyeong_api_fix7_is_admin_code(PDO $db, int $codeId): bool
{
    samgyeong_api_fix7_ensure_code_guard_schema($db);
    $admin = samgyeong_api_fix7_admin_user($db);
    if (!$admin) {
        return false;
    }
    $adminId = (int) $admin['id'];

    $stmt = $db->prepare('SELECT * FROM api_app_access_codes WHERE id = ? LIMIT 1');
    $stmt->execute([$codeId]);
    $code = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$code) {
        return false;
    }

    if ((int)($code['is_system_protected'] ?? 0) === 1) {
        return true;
    }
    if (isset($code['owner_user_id']) && (int)$code['owner_user_id'] === $adminId) {
        return true;
    }

    $stmt = $db->prepare('SELECT COUNT(*) FROM api_app_access_code_uses WHERE code_id = ? AND user_id = ?');
    $stmt->execute([$codeId, $adminId]);
    return ((int)$stmt->fetchColumn()) > 0;
}

function samgyeong_api_fix7_public_code(PDO $db, array $row): array
{
    $public = function_exists('samgyeong_api_v0431_public_code')
        ? samgyeong_api_v0431_public_code($db, $row)
        : $row;
    $codeId = (int)($row['id'] ?? $public['id'] ?? 0);
    $isAdminProtected = $codeId > 0 && samgyeong_api_fix7_is_admin_code($db, $codeId);
    $public['owner_user_id'] = isset($row['owner_user_id']) ? (int)$row['owner_user_id'] : null;
    $public['is_system_protected'] = isset($row['is_system_protected']) ? (int)$row['is_system_protected'] : 0;
    $public['is_admin_protected'] = $isAdminProtected;
    $public['can_revoke'] = !$isAdminProtected;
    return $public;
}
// SGMANAGER_FIX7_ADMIN_CODE_GUARD_HELPERS_END

// SGMANAGER_FIX9_ADMIN_CODE_REVOKE_GUARD_HELPERS_START
function samgyeong_api_fix9_ensure_code_guard_schema(PDO $db): void
{
    if (function_exists('samgyeong_api_fix7_ensure_code_guard_schema')) {
        samgyeong_api_fix7_ensure_code_guard_schema($db);
        return;
    }
    if (function_exists('samgyeong_api_v0431_ensure_schema')) {
        samgyeong_api_v0431_ensure_schema($db);
    }
    $db->exec("CREATE TABLE IF NOT EXISTS api_app_access_codes (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        label TEXT NOT NULL DEFAULT '',
        code_hash TEXT NOT NULL,
        code_preview TEXT NOT NULL DEFAULT '',
        created_by INTEGER NOT NULL,
        created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
        revoked_at TEXT,
        revoked_by INTEGER
    )");
    $db->exec("CREATE TABLE IF NOT EXISTS api_app_access_code_uses (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        code_id INTEGER NOT NULL,
        user_id INTEGER NOT NULL,
        used_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
        ip_address TEXT,
        user_agent TEXT
    )");
    $columns = [];
    foreach ($db->query('PRAGMA table_info(api_app_access_codes)') as $column) {
        $columns[(string)$column['name']] = true;
    }
    if (!isset($columns['owner_user_id'])) {
        $db->exec('ALTER TABLE api_app_access_codes ADD COLUMN owner_user_id INTEGER');
    }
    if (!isset($columns['is_system_protected'])) {
        $db->exec('ALTER TABLE api_app_access_codes ADD COLUMN is_system_protected INTEGER NOT NULL DEFAULT 0');
    }

    $tokenColumns = [];
    foreach ($db->query('PRAGMA table_info(api_tokens)') as $column) {
        $tokenColumns[(string)$column['name']] = true;
    }
    if (!isset($tokenColumns['app_access_code_id'])) {
        $db->exec('ALTER TABLE api_tokens ADD COLUMN app_access_code_id INTEGER');
    }
}

function samgyeong_api_fix9_admin_user(PDO $db): ?array
{
    $stmt = $db->query("SELECT * FROM users WHERE username = 'admin' ORDER BY id LIMIT 1");
    $admin = $stmt ? $stmt->fetch(PDO::FETCH_ASSOC) : false;
    if ($admin) {
        return $admin;
    }
    $stmt = $db->query("SELECT * FROM users WHERE role = 'admin' ORDER BY id LIMIT 1");
    $admin = $stmt ? $stmt->fetch(PDO::FETCH_ASSOC) : false;
    return $admin ?: null;
}

function samgyeong_api_fix9_is_admin_code(PDO $db, int $codeId): bool
{
    // SGMANAGER_FIX11_ALL_CODES_REUSABLE_ADMIN_CODE_CHECK_START
    samgyeong_api_fix9_ensure_code_guard_schema($db);
    $admin = samgyeong_api_fix9_admin_user($db);
    if (!$admin) {
        return false;
    }
    $adminId = (int)$admin['id'];
    $stmt = $db->prepare('SELECT * FROM api_app_access_codes WHERE id = ? LIMIT 1');
    $stmt->execute([$codeId]);
    $code = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$code) {
        return false;
    }

    // 관리자 코드는 명시적으로 보호 처리된 코드 또는 owner_user_id가 admin인 코드만 해당한다.
    // 과거처럼 "admin이 사용한 적 있는 코드"를 자동 관리자 코드로 승격하지 않는다.
    if ((int)($code['is_system_protected'] ?? 0) === 1) {
        return true;
    }
    if (isset($code['owner_user_id']) && (int)$code['owner_user_id'] === $adminId) {
        return true;
    }
    return false;
    // SGMANAGER_FIX11_ALL_CODES_REUSABLE_ADMIN_CODE_CHECK_END
}

function samgyeong_api_fix9_public_code(PDO $db, array $row): array
{
    $public = function_exists('samgyeong_api_fix7_public_code')
        ? samgyeong_api_fix7_public_code($db, $row)
        : (function_exists('samgyeong_api_v0431_public_code') ? samgyeong_api_v0431_public_code($db, $row) : $row);
    $codeId = (int)($row['id'] ?? $public['id'] ?? 0);
    $isProtected = $codeId > 0 && samgyeong_api_fix9_is_admin_code($db, $codeId);
    $public['is_admin_protected'] = $isProtected;
    $public['can_revoke'] = !$isProtected;
    return $public;
}
// SGMANAGER_FIX9_ADMIN_CODE_REVOKE_GUARD_HELPERS_END

// SGMANAGER_FIX12_MANAGEMENT_OPERATIONS_HELPERS_START
function samgyeong_api_fix12_table_exists(PDO $db, string $table): bool
{
    $stmt = $db->prepare("SELECT 1 FROM sqlite_master WHERE type = 'table' AND name = ? LIMIT 1");
    $stmt->execute([$table]);
    return (bool) $stmt->fetchColumn();
}

function samgyeong_api_fix12_table_columns(PDO $db, string $table): array
{
    if (!samgyeong_api_fix12_table_exists($db, $table)) {
        return [];
    }

    $columns = [];
    foreach ($db->query('PRAGMA table_info("' . str_replace('"', '""', $table) . '")') as $column) {
        $columns[(string) $column['name']] = $column;
    }
    return $columns;
}

function samgyeong_api_fix12_ensure_schema(PDO $db): void
{
    $db->exec('PRAGMA busy_timeout = 5000');
    $db->exec("CREATE TABLE IF NOT EXISTS point_resets (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        reset_at TEXT NOT NULL,
        reset_by INTEGER,
        reason TEXT,
        created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP
    )");
    $db->exec("CREATE TABLE IF NOT EXISTS api_point_reset_audit (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        reset_at TEXT NOT NULL,
        reset_by INTEGER NOT NULL,
        reason TEXT,
        source TEXT NOT NULL DEFAULT 'manual',
        created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP
    )");
    $db->exec("CREATE TABLE IF NOT EXISTS api_hall_change_log (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        batch_id TEXT NOT NULL,
        user_id INTEGER NOT NULL,
        old_hall_key TEXT NOT NULL DEFAULT '',
        new_hall_key TEXT NOT NULL,
        changed_by INTEGER NOT NULL,
        memo TEXT,
        changed_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP
    )");
    $db->exec('CREATE INDEX IF NOT EXISTS idx_fix12_point_reset_audit_at ON api_point_reset_audit(reset_at)');
    $db->exec('CREATE INDEX IF NOT EXISTS idx_fix12_hall_change_batch ON api_hall_change_log(batch_id, changed_at)');
}

function samgyeong_api_fix12_latest_reset_at(PDO $db): ?string
{
    samgyeong_api_fix12_ensure_schema($db);

    if (function_exists('current_point_reset_at')) {
        try {
            $value = current_point_reset_at($db);
            if (is_string($value) && trim($value) !== '') {
                return trim($value);
            }
        } catch (Throwable $e) {
            // 기존 웹 함수의 스키마 차이가 있어도 API fallback을 사용한다.
        }
    }

    $columns = samgyeong_api_fix12_table_columns($db, 'point_resets');
    foreach (['reset_at', 'created_at', 'updated_at'] as $candidate) {
        if (!isset($columns[$candidate])) {
            continue;
        }
        $value = $db->query('SELECT MAX("' . $candidate . '") FROM point_resets')->fetchColumn();
        if (is_string($value) && trim($value) !== '') {
            return trim($value);
        }
    }

    return null;
}

function samgyeong_api_fix12_active_record_count(PDO $db, ?string $resetAt): int
{
    if (!samgyeong_api_fix12_table_exists($db, 'point_records')) {
        return 0;
    }

    $sql = "SELECT COUNT(*) FROM point_records
        WHERE canceled_at IS NULL
          AND cancellation_of_id IS NULL";
    $params = [];
    if ($resetAt !== null && $resetAt !== '') {
        $sql .= ' AND created_at > ?';
        $params[] = $resetAt;
    }
    $stmt = $db->prepare($sql);
    $stmt->execute($params);
    return (int) $stmt->fetchColumn();
}

function samgyeong_api_fix12_insert_reset(PDO $db, array $issuer, string $reason): array
{
    samgyeong_api_fix12_ensure_schema($db);
    $now = date('Y-m-d H:i:s');
    $userId = (int) ($issuer['id'] ?? 0);
    $username = trim((string) ($issuer['username'] ?? 'admin'));
    $columns = samgyeong_api_fix12_table_columns($db, 'point_resets');

    $insert = [];
    foreach ($columns as $name => $definition) {
        if ((int) ($definition['pk'] ?? 0) === 1) {
            continue;
        }

        $type = strtoupper((string) ($definition['type'] ?? ''));
        $valueKnown = true;
        $value = null;

        if (in_array($name, ['reset_at', 'created_at', 'updated_at'], true)) {
            $value = $now;
        } elseif (in_array($name, ['reset_by', 'reset_by_user_id', 'user_id', 'admin_id', 'issuer_id'], true)) {
            $value = $userId;
        } elseif ($name === 'created_by') {
            $value = str_contains($type, 'INT') ? $userId : $username;
        } elseif (in_array($name, ['reason', 'note', 'memo'], true)) {
            $value = $reason !== '' ? $reason : null;
        } elseif ($name === 'source') {
            $value = 'manual';
        } else {
            $valueKnown = false;
        }

        if (!$valueKnown) {
            $notNull = (int) ($definition['notnull'] ?? 0) === 1;
            $default = $definition['dflt_value'] ?? null;
            if (!$notNull || $default !== null) {
                continue;
            }
            $value = str_contains($type, 'INT') || str_contains($type, 'REAL') ? 0 : '';
        }

        $insert[$name] = $value;
    }

    if (!$insert) {
        throw new RuntimeException('point_resets_insert_columns_not_found');
    }

    $quotedColumns = array_map(
        fn(string $name): string => '"' . str_replace('"', '""', $name) . '"',
        array_keys($insert)
    );
    $sql = 'INSERT INTO point_resets (' . implode(', ', $quotedColumns) . ') VALUES (' . implode(', ', array_fill(0, count($insert), '?')) . ')';
    $stmt = $db->prepare($sql);
    $stmt->execute(array_values($insert));
    $resetId = (int) $db->lastInsertId();

    $audit = $db->prepare('INSERT INTO api_point_reset_audit (reset_at, reset_by, reason, source) VALUES (?, ?, ?, ?)');
    $audit->execute([$now, $userId, $reason !== '' ? $reason : null, 'manual']);

    if (samgyeong_api_fix12_table_exists($db, 'discipline_alert_state')) {
        $stateColumns = samgyeong_api_fix12_table_columns($db, 'discipline_alert_state');
        if (isset($stateColumns['resolved_at'])) {
            $sets = ['resolved_at = ?'];
            $params = [$now];
            if (isset($stateColumns['last_seen_at'])) {
                $sets[] = 'last_seen_at = ?';
                $params[] = $now;
            }
            $db->prepare('UPDATE discipline_alert_state SET ' . implode(', ', $sets) . ' WHERE resolved_at IS NULL')->execute($params);
        }
    }

    return ['id' => $resetId, 'reset_at' => $now];
}

function samgyeong_api_fix12_hall_meta(string $hallKey): ?array
{
    return match ($hallKey) {
        'gyeongcheon' => ['name' => '경천관', 'meaning' => '바른 품행과 책임', 'color' => 'blue'],
        'gyeongin' => ['name' => '경인관', 'meaning' => '사람에 대한 존중', 'color' => 'gold'],
        'gyeongmul' => ['name' => '경물관', 'meaning' => '학문과 성취', 'color' => 'green'],
        default => null,
    };
}

function samgyeong_api_fix12_sync_hall_member(PDO $db, array $student): void
{
    if (function_exists('samgyeong_api_v041_sync_hall_member')) {
        samgyeong_api_v041_sync_hall_member($db, (int) $student['id']);
        return;
    }

    if (!samgyeong_api_fix12_table_exists($db, 'hall_members')) {
        return;
    }

    $meta = samgyeong_api_fix12_hall_meta((string) ($student['hall_key'] ?? ''));
    if ($meta === null) {
        return;
    }

    $stmt = $db->prepare('SELECT id FROM hall_members WHERE user_id = ? LIMIT 1');
    $stmt->execute([(int) $student['id']]);
    $memberId = $stmt->fetchColumn();

    if ($memberId) {
        $stmt = $db->prepare('UPDATE hall_members SET hall_key = ?, hall_name = ?, hall_meaning = ?, hall_color = ?, student_name = ?, year = ? WHERE id = ?');
        $stmt->execute([
            $student['hall_key'],
            $meta['name'],
            $meta['meaning'],
            $meta['color'],
            $student['display_name'] ?? $student['username'] ?? '',
            (int) ($student['year'] ?? 0),
            (int) $memberId,
        ]);
        return;
    }

    $stmt = $db->prepare('INSERT INTO hall_members (hall_key, hall_name, hall_meaning, hall_color, student_name, year, role_label, sort_order, photo_path, user_id) VALUES (?, ?, ?, ?, ?, ?, ?, 0, ?, ?)');
    $stmt->execute([
        $student['hall_key'],
        $meta['name'],
        $meta['meaning'],
        $meta['color'],
        $student['display_name'] ?? $student['username'] ?? '',
        max(1, min(3, (int) ($student['year'] ?? 1))),
        (string) ($student['role'] ?? '') === 'council' ? '삼경원' : '학생',
        $student['photo_path'] ?? null,
        (int) $student['id'],
    ]);
}

function samgyeong_api_fix12_bulk_change_halls(PDO $db, array $issuer, array $changes, string $memo): array
{
    samgyeong_api_fix12_ensure_schema($db);
    if (!$changes || count($changes) > 500) {
        samgyeong_api_json(['ok' => false, 'error' => 'invalid_changes'], 400);
    }

    $normalized = [];
    foreach ($changes as $change) {
        if (!is_array($change)) {
            samgyeong_api_json(['ok' => false, 'error' => 'invalid_changes'], 400);
        }
        $userId = (int) ($change['user_id'] ?? 0);
        $hallKey = trim((string) ($change['hall_key'] ?? ''));
        if ($userId <= 0 || samgyeong_api_fix12_hall_meta($hallKey) === null) {
            samgyeong_api_json(['ok' => false, 'error' => $userId <= 0 ? 'student_not_found' : 'invalid_hall_key'], 400);
        }
        $normalized[$userId] = $hallKey;
    }

    $userColumns = samgyeong_api_fix12_table_columns($db, 'users');
    $activeClause = isset($userColumns['is_active']) ? ' AND COALESCE(is_active, 1) = 1' : '';
    $batchId = 'hall-' . date('YmdHis') . '-' . bin2hex(random_bytes(4));
    $changedCount = 0;
    $unchangedCount = 0;
    $results = [];

    $db->beginTransaction();
    try {
        foreach ($normalized as $userId => $hallKey) {
            $stmt = $db->prepare("SELECT * FROM users WHERE id = ? AND role IN ('student', 'council'){$activeClause} LIMIT 1");
            $stmt->execute([$userId]);
            $student = $stmt->fetch(PDO::FETCH_ASSOC);
            if (!$student) {
                throw new RuntimeException('student_not_found:' . $userId);
            }

            $oldHallKey = (string) ($student['hall_key'] ?? '');
            $changed = $oldHallKey !== $hallKey;
            if ($changed) {
                $update = $db->prepare('UPDATE users SET hall_key = ? WHERE id = ?');
                $update->execute([$hallKey, $userId]);
                $student['hall_key'] = $hallKey;
                samgyeong_api_fix12_sync_hall_member($db, $student);

                $log = $db->prepare('INSERT INTO api_hall_change_log (batch_id, user_id, old_hall_key, new_hall_key, changed_by, memo) VALUES (?, ?, ?, ?, ?, ?)');
                $log->execute([
                    $batchId,
                    $userId,
                    $oldHallKey,
                    $hallKey,
                    (int) ($issuer['id'] ?? 0),
                    $memo !== '' ? $memo : null,
                ]);
                $changedCount++;
            } else {
                $unchangedCount++;
            }

            $results[] = [
                'user_id' => $userId,
                'display_name' => $student['display_name'] ?? '',
                'username' => $student['username'] ?? '',
                'old_hall_key' => $oldHallKey,
                'new_hall_key' => $hallKey,
                'changed' => $changed,
            ];
        }
        $db->commit();
    } catch (Throwable $e) {
        if ($db->inTransaction()) {
            $db->rollBack();
        }
        if (str_starts_with($e->getMessage(), 'student_not_found:')) {
            samgyeong_api_json(['ok' => false, 'error' => 'student_not_found'], 404);
        }
        throw $e;
    }

    return [
        'batch_id' => $batchId,
        'changed_count' => $changedCount,
        'unchanged_count' => $unchangedCount,
        'results' => $results,
    ];
}
// SGMANAGER_FIX12_MANAGEMENT_OPERATIONS_HELPERS_END

// SGMANAGER_FIX13_ACCESS_UI_SYNC_HELPERS_START
function samgyeong_api_fix13_table_signature(PDO $db, string $table): array
{
    $exists = $db->prepare("SELECT 1 FROM sqlite_master WHERE type='table' AND name=? LIMIT 1");
    $exists->execute([$table]);
    if (!$exists->fetchColumn()) return ['table' => $table, 'missing' => true];
    $columns = [];
    foreach ($db->query('PRAGMA table_info("' . str_replace('"', '""', $table) . '")') as $row) $columns[(string)$row['name']] = true;
    $result = ['table' => $table, 'count' => (int)$db->query('SELECT COUNT(*) FROM "' . str_replace('"','""',$table) . '"')->fetchColumn()];
    foreach (['id','updated_at','created_at','revoked_at','reset_at','issued_at','changed_at'] as $column) {
        if (isset($columns[$column])) $result[$column] = $db->query('SELECT MAX("' . $column . '") FROM "' . str_replace('"','""',$table) . '"')->fetchColumn();
    }
    return $result;
}
function samgyeong_api_fix13_sync_version(PDO $db): string
{
    $parts = [];
    foreach (['users','hall_members','point_records','point_resets','app_access_codes','posts','calendar_events','point_rules'] as $table)
        $parts[] = samgyeong_api_fix13_table_signature($db, $table);
    foreach (['/var/www/html/storage/data/samgyeong.sqlite','/var/www/html/storage/data/samgyeong.sqlite-wal'] as $file)
        $parts[] = ['file' => basename($file), 'mtime' => is_file($file) ? filemtime($file) : 0, 'size' => is_file($file) ? filesize($file) : 0];
    return hash('sha256', json_encode($parts, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
}
// SGMANAGER_FIX13_ACCESS_UI_SYNC_HELPERS_END
function samgyeong_api_handle_request(PDO $db, string $path, string $method): never
{
    samgyeong_api_ensure_schema($db);

    if ($method === 'OPTIONS') {
        samgyeong_api_json(['ok' => true], 200);
    }

    $path = rtrim($path, '/') ?: '/';
    $body = samgyeong_api_body();
    // SGMANAGER_FIX13_ACCESS_UI_SYNC_ROUTES_START
    header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
    header('Pragma: no-cache');

    if ($path === '/api/admin/sync-state' && $method === 'GET') {
        samgyeong_api_current_user($db);
        samgyeong_api_json(['ok' => true, 'version' => samgyeong_api_fix13_sync_version($db), 'server_time' => date('Y-m-d H:i:s')]);
    }

    if ($path === '/api/admin/dashboard-counts' && $method === 'GET') {
        samgyeong_api_current_user($db);
        $today = date('Y-m-d');
        $userColumns = [];
        foreach ($db->query('PRAGMA table_info(users)') as $column) $userColumns[(string)$column['name']] = true;
        $activeClause = isset($userColumns['is_active']) ? ' AND COALESCE(is_active, 1) = 1' : '';
        $activeStudents = (int)$db->query("SELECT COUNT(*) FROM users WHERE role IN ('student','council'){$activeClause}")->fetchColumn();
        $todayStmt = $db->prepare("SELECT COUNT(*) FROM point_records WHERE canceled_at IS NULL AND cancellation_of_id IS NULL AND substr(issued_at, 1, 10) = ?");
        $todayStmt->execute([$today]);
        samgyeong_api_json([
            'ok' => true,
            'active_student_count' => $activeStudents,
            'today_record_count' => (int)$todayStmt->fetchColumn(),
            'as_of' => date('Y-m-d H:i:s'),
        ]);
    }

    if (str_starts_with($path, '/api/admin/discipline-')) {
        samgyeong_api_json(['ok' => false, 'error' => 'feature_disabled', 'message' => '징계 기준 및 경고/징계 알림은 앱에서 제공하지 않습니다.'], 410);
    }
    // SGMANAGER_FIX13_ACCESS_UI_SYNC_ROUTES_END
    // SGMANAGER_FIX12_MANAGEMENT_OPERATIONS_ROUTES_START
    if ($path === '/api/admin/points/reset-status' && $method === 'GET') {
        samgyeong_api_current_user($db, ['admin']);
        samgyeong_api_fix12_ensure_schema($db);
        $lastResetAt = samgyeong_api_fix12_latest_reset_at($db);
        $resetCount = (int) $db->query('SELECT COUNT(*) FROM point_resets')->fetchColumn();
        samgyeong_api_json([
            'ok' => true,
            'last_reset_at' => $lastResetAt,
            'reset_count' => $resetCount,
            'active_record_count' => samgyeong_api_fix12_active_record_count($db, $lastResetAt),
        ]);
    }

    if ($path === '/api/admin/points/reset' && $method === 'POST') {
        $issuer = samgyeong_api_current_user($db, ['admin']);
        $confirmation = trim((string) ($body['confirmation'] ?? ''));
        if ($confirmation !== '초기화') {
            samgyeong_api_json(['ok' => false, 'error' => 'confirmation_required'], 400);
        }
        $reason = trim((string) ($body['reason'] ?? ''));
        if (function_exists('mb_substr')) {
            $reason = mb_substr($reason, 0, 200, 'UTF-8');
        } else {
            $reason = substr($reason, 0, 200);
        }

        $db->beginTransaction();
        try {
            $reset = samgyeong_api_fix12_insert_reset($db, $issuer, $reason);
            $db->commit();
        } catch (Throwable $e) {
            if ($db->inTransaction()) {
                $db->rollBack();
            }
            throw $e;
        }

        samgyeong_api_json([
            'ok' => true,
            'message' => '상벌점 합계 기준이 초기화되었습니다.',
            'reset_id' => (int) ($reset['id'] ?? 0),
            'reset_at' => $reset['reset_at'] ?? date('Y-m-d H:i:s'),
            'history_preserved' => true,
        ]);
    }

    if ($path === '/api/admin/students/hall-bulk' && $method === 'POST') {
        $issuer = samgyeong_api_current_user($db, ['admin']);
        $changes = $body['changes'] ?? null;
        if (!is_array($changes)) {
            samgyeong_api_json(['ok' => false, 'error' => 'invalid_changes'], 400);
        }
        $memo = trim((string) ($body['memo'] ?? ''));
        if (function_exists('mb_substr')) {
            $memo = mb_substr($memo, 0, 200, 'UTF-8');
        } else {
            $memo = substr($memo, 0, 200);
        }

        $result = samgyeong_api_fix12_bulk_change_halls($db, $issuer, $changes, $memo);
        samgyeong_api_json([
            'ok' => true,
            'message' => '직속 일괄 변경이 완료되었습니다.',
            'batch_id' => $result['batch_id'],
            'changed_count' => $result['changed_count'],
            'unchanged_count' => $result['unchanged_count'],
            'results' => $result['results'],
        ]);
    }
    // SGMANAGER_FIX12_MANAGEMENT_OPERATIONS_ROUTES_END


    if ($path === '/api/admin/login' && $method === 'POST') {
        $username = trim((string) ($body['username'] ?? ''));
        $password = (string) ($body['password'] ?? '');

        $stmt = $db->prepare("SELECT * FROM users WHERE username = ? LIMIT 1");
        $stmt->execute([$username]);
        $user = $stmt->fetch();

        if (!$user || !password_verify($password, $user['password_hash'])) {
            samgyeong_api_json(['ok' => false, 'error' => 'invalid_credentials'], 401);
        }

        if (!in_array($user['role'], ['admin', 'council'], true)) {
            samgyeong_api_json(['ok' => false, 'error' => 'forbidden'], 403);
        }

        
        // SGMANAGER_V041_FIX3_GUARDS_LOGIN_START
        if ((int) ($user['is_active'] ?? 1) !== 1) {
            samgyeong_api_json(['ok' => false, 'error' => 'account_inactive'], 403);
        }
        // SGMANAGER_V041_FIX3_GUARDS_LOGIN_END        // SGMANAGER_V0431_CODE_MANAGEMENT_LOGIN_CHECK_START
        samgyeong_api_v0431_enforce_login_code($db, $body);
        // SGMANAGER_V0431_CODE_MANAGEMENT_LOGIN_CHECK_END


$token = bin2hex(random_bytes(32));
        $tokenHash = hash('sha256', $token);
        $expiresAt = date('Y-m-d H:i:s', time() + 60 * 60 * 24 * 30);

        $stmt = $db->prepare('INSERT INTO api_tokens (user_id, token_hash, expires_at) VALUES (?, ?, ?)');
        $stmt->execute([(int) $user['id'], $tokenHash, $expiresAt]);
        // SGMANAGER_V0431_CODE_MANAGEMENT_AFTER_LOGIN_START
        samgyeong_api_v0431_after_login($db, $user, $tokenHash);
        // SGMANAGER_V0431_CODE_MANAGEMENT_AFTER_LOGIN_END

        samgyeong_api_json([
            'ok' => true,
            'token' => $token,
            'expires_at' => $expiresAt,
            'user' => [
                'id' => (int) $user['id'],
                'username' => $user['username'],
                'role' => $user['role'],
                'display_name' => $user['display_name'] ?? '',
                'hall_key' => $user['hall_key'] ?? '',
                'year' => (int) ($user['year'] ?? 0),
            ],
        ]);
    }

    if ($path === '/api/admin/logout' && $method === 'POST') {
        $token = samgyeong_api_token_from_request();
        if ($token !== '') {
            $stmt = $db->prepare('UPDATE api_tokens SET revoked_at = CURRENT_TIMESTAMP WHERE token_hash = ?');
            $stmt->execute([hash('sha256', $token)]);
        }
        samgyeong_api_json(['ok' => true]);
    }

    if ($path === '/api/admin/me' && $method === 'GET') {
        $user = samgyeong_api_current_user($db);
        samgyeong_api_json(['ok' => true, 'user' => $user]);
    }


    // SGMANAGER_FIX9_ADMIN_CODE_REVOKE_GUARD_ROUTES_START
    if ($path === '/api/admin/app-access-codes' && $method === 'GET') {
        samgyeong_api_current_user($db, ['admin']);
        samgyeong_api_fix9_ensure_code_guard_schema($db);

        $rows = $db->query("\n            SELECT c.*,\n                   creator.display_name AS created_by_name,\n                   revoker.display_name AS revoked_by_name,\n                   COUNT(uses.id) AS use_count,\n                   MAX(uses.used_at) AS last_used_at\n            FROM api_app_access_codes AS c\n            LEFT JOIN users AS creator ON creator.id = c.created_by\n            LEFT JOIN users AS revoker ON revoker.id = c.revoked_by\n            LEFT JOIN api_app_access_code_uses AS uses ON uses.code_id = c.id\n            GROUP BY c.id\n            ORDER BY c.revoked_at IS NOT NULL, c.created_at DESC, c.id DESC\n        ")->fetchAll(PDO::FETCH_ASSOC);

        $codes = [];
        foreach ($rows as $row) {
            $codes[] = samgyeong_api_fix9_public_code($db, $row);
        }

        samgyeong_api_json([
            'ok' => true,
            'policy' => function_exists('samgyeong_api_v0431_policy') ? samgyeong_api_v0431_policy($db) : null,
            'codes' => $codes,
        ]);
    }

    if (preg_match('#^/api/admin/app-access-codes/(\d+)/revoke$#', $path, $m) && $method === 'POST') {
        $admin = samgyeong_api_current_user($db, ['admin']);
        samgyeong_api_fix9_ensure_code_guard_schema($db);
        $codeId = (int)$m[1];

        $stmt = $db->prepare('SELECT * FROM api_app_access_codes WHERE id = ? LIMIT 1');
        $stmt->execute([$codeId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$row) {
            samgyeong_api_json(['ok' => false, 'error' => 'code_not_found'], 404);
        }

        if (samgyeong_api_fix9_is_admin_code($db, $codeId)) {
            samgyeong_api_json([
                'ok' => false,
                'error' => 'admin_app_access_code_protected',
                'message' => '관리자 코드는 폐기할 수 없습니다.',
            ], 403);
        }

        $stmt = $db->prepare('UPDATE api_app_access_codes SET revoked_at = CURRENT_TIMESTAMP, revoked_by = ? WHERE id = ? AND revoked_at IS NULL');
        $stmt->execute([(int)$admin['id'], $codeId]);

        $stmt = $db->prepare('UPDATE api_tokens SET revoked_at = CURRENT_TIMESTAMP WHERE app_access_code_id = ? AND revoked_at IS NULL');
        $stmt->execute([$codeId]);
        $tokensRevoked = $stmt->rowCount();

        if (function_exists('samgyeong_api_v0431_set_setting')) {
            samgyeong_api_v0431_set_setting($db, 'app_access_updated_at', date('Y-m-d H:i:s'));
        }

        $stmt = $db->prepare('SELECT * FROM api_app_access_codes WHERE id = ? LIMIT 1');
        $stmt->execute([$codeId]);
        $updated = $stmt->fetch(PDO::FETCH_ASSOC) ?: ['id' => $codeId];

        samgyeong_api_json([
            'ok' => true,
            'revoked' => true,
            'tokens_revoked' => $tokensRevoked,
            'code' => samgyeong_api_fix9_public_code($db, $updated),
            'policy' => function_exists('samgyeong_api_v0431_policy') ? samgyeong_api_v0431_policy($db) : null,
        ]);
    }
    // SGMANAGER_FIX9_ADMIN_CODE_REVOKE_GUARD_ROUTES_END
    // SGMANAGER_FIX7_ADMIN_CODE_GUARD_ROUTES_START
    if ($path === '/api/admin/app-access-codes' && $method === 'GET') {
        samgyeong_api_current_user($db, ['admin']);
        samgyeong_api_fix7_ensure_code_guard_schema($db);

        $rows = $db->query("\n            SELECT c.*,\n                   creator.display_name AS created_by_name,\n                   revoker.display_name AS revoked_by_name,\n                   COUNT(uses.id) AS use_count,\n                   MAX(uses.used_at) AS last_used_at\n            FROM api_app_access_codes AS c\n            LEFT JOIN users AS creator ON creator.id = c.created_by\n            LEFT JOIN users AS revoker ON revoker.id = c.revoked_by\n            LEFT JOIN api_app_access_code_uses AS uses ON uses.code_id = c.id\n            GROUP BY c.id\n            ORDER BY c.revoked_at IS NOT NULL, c.created_at DESC, c.id DESC\n        ")->fetchAll(PDO::FETCH_ASSOC);

        $codes = [];
        foreach ($rows as $row) {
            $codes[] = samgyeong_api_fix7_public_code($db, $row);
        }

        samgyeong_api_json([
            'ok' => true,
            'policy' => function_exists('samgyeong_api_v0431_policy') ? samgyeong_api_v0431_policy($db) : null,
            'codes' => $codes,
        ]);
    }

    if ($path === '/api/admin/app-access-codes' && $method === 'POST') {
        $admin = samgyeong_api_current_user($db, ['admin']);
        samgyeong_api_fix7_ensure_code_guard_schema($db);

        $label = samgyeong_api_trim_text((string) ($body['label'] ?? ''), 80);
        if ($label === '') {
            $label = '학생회 고유 코드 ' . date('Y-m-d H:i');
        }

        $plainCode = trim((string) ($body['app_access_code'] ?? $body['access_code'] ?? ''));
        if ($plainCode === '') {
            $plainCode = function_exists('samgyeong_api_v0431_generate_code') ? samgyeong_api_v0431_generate_code() : ('SG-' . strtoupper(bin2hex(random_bytes(4))));
        }
        if (strlen($plainCode) < 4) {
            samgyeong_api_json(['ok' => false, 'error' => 'app_access_code_too_short', 'message' => '학생회 고유 코드는 4자 이상이어야 합니다.'], 400);
        }

        $fingerprint = samgyeong_api_fix7_code_fingerprint($plainCode);
        $stmt = $db->prepare('SELECT COUNT(*) FROM api_app_access_codes WHERE code_fingerprint = ?');
        $stmt->execute([$fingerprint]);
        if (((int)$stmt->fetchColumn()) > 0) {
            samgyeong_api_json(['ok' => false, 'error' => 'app_access_code_already_exists', 'message' => '이미 발급된 적이 있는 학생회 고유 코드입니다. 한 번 사용했거나 폐기한 코드는 다시 사용할 수 없습니다.'], 409);
        }

        $stmt = $db->prepare("\n            INSERT INTO api_app_access_codes (label, code_hash, code_preview, code_fingerprint, created_by, owner_user_id, is_system_protected)\n            VALUES (?, ?, ?, ?, ?, NULL, 0)\n        ");
        $stmt->execute([
            $label,
            password_hash($plainCode, PASSWORD_DEFAULT),
            function_exists('samgyeong_api_v0431_code_preview') ? samgyeong_api_v0431_code_preview($plainCode) : ('••••' . substr(samgyeong_api_fix7_normalized_code($plainCode), -4)),
            $fingerprint,
            (int) $admin['id'],
        ]);
        $codeId = (int) $db->lastInsertId();

        if (function_exists('samgyeong_api_v0431_set_setting')) {
            samgyeong_api_v0431_set_setting($db, 'app_access_required', '1');
            samgyeong_api_v0431_set_setting($db, 'app_access_updated_at', date('Y-m-d H:i:s'));
        }

        $currentToken = samgyeong_api_token_from_request();
        $currentHash = $currentToken !== '' ? hash('sha256', $currentToken) : '';
        if ($currentHash !== '') {
            $stmt = $db->prepare("UPDATE api_tokens SET revoked_at = CURRENT_TIMESTAMP WHERE revoked_at IS NULL AND token_hash != ?");
            $stmt->execute([$currentHash]);
        } else {
            $db->exec("UPDATE api_tokens SET revoked_at = CURRENT_TIMESTAMP WHERE revoked_at IS NULL");
        }

        samgyeong_api_json([
            'ok' => true,
            'inserted' => true,
            'plain_code' => $plainCode,
            'code' => function_exists('samgyeong_api_v0431_fetch_code') ? samgyeong_api_v0431_fetch_code($db, $codeId) : ['id' => $codeId],
            'policy' => function_exists('samgyeong_api_v0431_policy') ? samgyeong_api_v0431_policy($db) : null,
        ], 201);
    }

    if (preg_match('#^/api/admin/app-access-codes/(\d+)/revoke$#', $path, $m) && $method === 'POST') {
        $admin = samgyeong_api_current_user($db, ['admin']);
        samgyeong_api_fix7_ensure_code_guard_schema($db);
        $codeId = (int) $m[1];

        $stmt = $db->prepare('SELECT * FROM api_app_access_codes WHERE id = ? LIMIT 1');
        $stmt->execute([$codeId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$row) {
            samgyeong_api_json(['ok' => false, 'error' => 'code_not_found'], 404);
        }

        if (samgyeong_api_fix7_is_admin_code($db, $codeId)) {
            samgyeong_api_json([
                'ok' => false,
                'error' => 'admin_app_access_code_protected',
                'message' => '관리자 코드는 폐기할 수 없습니다.',
            ], 403);
        }

        $stmt = $db->prepare('UPDATE api_app_access_codes SET revoked_at = CURRENT_TIMESTAMP, revoked_by = ? WHERE id = ? AND revoked_at IS NULL');
        $stmt->execute([(int) $admin['id'], $codeId]);

        $stmt = $db->prepare('UPDATE api_tokens SET revoked_at = CURRENT_TIMESTAMP WHERE app_access_code_id = ? AND revoked_at IS NULL');
        $stmt->execute([$codeId]);
        $tokensRevoked = $stmt->rowCount();

        if (function_exists('samgyeong_api_v0431_set_setting')) {
            samgyeong_api_v0431_set_setting($db, 'app_access_updated_at', date('Y-m-d H:i:s'));
        }

        samgyeong_api_json([
            'ok' => true,
            'revoked' => true,
            'tokens_revoked' => $tokensRevoked,
            'code' => function_exists('samgyeong_api_v0431_fetch_code') ? samgyeong_api_v0431_fetch_code($db, $codeId) : ['id' => $codeId],
            'policy' => function_exists('samgyeong_api_v0431_policy') ? samgyeong_api_v0431_policy($db) : null,
        ]);
    }
    // SGMANAGER_FIX7_ADMIN_CODE_GUARD_ROUTES_END
    // SGMANAGER_V0431_CODE_MANAGEMENT_ROUTES_START
    if ($path === '/api/admin/app-access-codes' && $method === 'GET') {
        samgyeong_api_current_user($db, ['admin']);
        samgyeong_api_v0431_ensure_schema($db);

        $rows = $db->query("\n            SELECT c.*,\n                   creator.display_name AS created_by_name,\n                   revoker.display_name AS revoked_by_name,\n                   COUNT(uses.id) AS use_count,\n                   MAX(uses.used_at) AS last_used_at\n            FROM api_app_access_codes AS c\n            LEFT JOIN users AS creator ON creator.id = c.created_by\n            LEFT JOIN users AS revoker ON revoker.id = c.revoked_by\n            LEFT JOIN api_app_access_code_uses AS uses ON uses.code_id = c.id\n            GROUP BY c.id\n            ORDER BY c.revoked_at IS NOT NULL, c.created_at DESC, c.id DESC\n        ")->fetchAll(PDO::FETCH_ASSOC);

        $codes = [];
        foreach ($rows as $row) {
            $codes[] = samgyeong_api_v0431_public_code($db, $row);
        }

        samgyeong_api_json([
            'ok' => true,
            'policy' => samgyeong_api_v0431_policy($db),
            'codes' => $codes,
        ]);
    }

    if ($path === '/api/admin/app-access-codes' && $method === 'POST') {
        $admin = samgyeong_api_current_user($db, ['admin']);
        samgyeong_api_v0431_ensure_schema($db);

        $label = samgyeong_api_trim_text((string) ($body['label'] ?? ''), 80);
        if ($label === '') {
            $label = '학생회 고유 코드 ' . date('Y-m-d H:i');
        }

        $plainCode = trim((string) ($body['app_access_code'] ?? $body['access_code'] ?? ''));
        if ($plainCode === '') {
            $plainCode = samgyeong_api_v0431_generate_code();
        }

        if (strlen($plainCode) < 4) {
            samgyeong_api_json(['ok' => false, 'error' => 'app_access_code_too_short', 'message' => '학생회 고유 코드는 4자 이상이어야 합니다.'], 400);
        }

        $stmt = $db->prepare("\n            INSERT INTO api_app_access_codes (label, code_hash, code_preview, created_by)\n            VALUES (?, ?, ?, ?)\n        ");
        $stmt->execute([
            $label,
            password_hash($plainCode, PASSWORD_DEFAULT),
            samgyeong_api_v0431_code_preview($plainCode),
            (int) $admin['id'],
        ]);
        $codeId = (int) $db->lastInsertId();

        samgyeong_api_v0431_set_setting($db, 'app_access_required', '1');
        samgyeong_api_v0431_set_setting($db, 'app_access_updated_at', date('Y-m-d H:i:s'));

        // 기존 앱 토큰은 보안을 위해 만료하되, 현재 관리자 토큰은 코드 복사/관리 화면 유지를 위해 살린다.
        $currentToken = samgyeong_api_token_from_request();
        $currentHash = $currentToken !== '' ? hash('sha256', $currentToken) : '';
        if ($currentHash !== '') {
            $stmt = $db->prepare("UPDATE api_tokens SET revoked_at = CURRENT_TIMESTAMP WHERE revoked_at IS NULL AND token_hash != ?");
            $stmt->execute([$currentHash]);
        } else {
            $db->exec("UPDATE api_tokens SET revoked_at = CURRENT_TIMESTAMP WHERE revoked_at IS NULL");
        }

        samgyeong_api_json([
            'ok' => true,
            'inserted' => true,
            'plain_code' => $plainCode,
            'code' => samgyeong_api_v0431_fetch_code($db, $codeId),
            'policy' => samgyeong_api_v0431_policy($db),
        ], 201);
    }

    if (preg_match('#^/api/admin/app-access-codes/(\d+)/revoke$#', $path, $m) && $method === 'POST') {
        $admin = samgyeong_api_current_user($db, ['admin']);
        samgyeong_api_v0431_ensure_schema($db);
        $codeId = (int) $m[1];

        $code = samgyeong_api_v0431_fetch_code($db, $codeId);
        if (!$code) {
            samgyeong_api_json(['ok' => false, 'error' => 'code_not_found'], 404);
        }

        $stmt = $db->prepare('UPDATE api_app_access_codes SET revoked_at = CURRENT_TIMESTAMP, revoked_by = ? WHERE id = ? AND revoked_at IS NULL');
        $stmt->execute([(int) $admin['id'], $codeId]);

        $stmt = $db->prepare('UPDATE api_tokens SET revoked_at = CURRENT_TIMESTAMP WHERE app_access_code_id = ? AND revoked_at IS NULL');
        $stmt->execute([$codeId]);
        $tokensRevoked = $stmt->rowCount();

        samgyeong_api_v0431_set_setting($db, 'app_access_updated_at', date('Y-m-d H:i:s'));

        samgyeong_api_json([
            'ok' => true,
            'revoked' => true,
            'tokens_revoked' => $tokensRevoked,
            'code' => samgyeong_api_v0431_fetch_code($db, $codeId),
            'policy' => samgyeong_api_v0431_policy($db),
        ]);
    }
    // SGMANAGER_V0431_CODE_MANAGEMENT_ROUTES_END
    // SGMANAGER_FIX5_WARNING_DISCIPLINE_ROUTES_START
    if ($path === '/api/admin/discipline-rules' && $method === 'GET') {
        samgyeong_api_current_user($db);
        samgyeong_api_json([
            'ok' => true,
            'source' => 'canonical_sum_score_discipline_rules',
            'rules' => samgyeong_api_fix5_canonical_rules(),
            'principle' => '상점과 벌점을 합산한 합계 점수를 기준으로 판단합니다.',
        ]);
    }

    if ($path === '/api/admin/discipline-warnings' && $method === 'GET') {
        samgyeong_api_current_user($db);
        $result = samgyeong_api_fix5_classified_discipline($db);
        samgyeong_api_json([
            'ok' => true,
            'source' => 'canonical_sum_score_discipline_warning_rules',
            'rules' => $result['rules'],
            'targets' => $result['warnings'],
            'summary' => $result['summary'],
            'principle' => '합계 점수가 징계 기준에 도달한 뒤 24시간 유예 중인 인원입니다.',
        ]);
    }

    if ($path === '/api/admin/discipline-targets' && $method === 'GET') {
        samgyeong_api_current_user($db);
        $result = samgyeong_api_fix5_classified_discipline($db);
        samgyeong_api_json([
            'ok' => true,
            'source' => 'canonical_sum_score_discipline_target_rules',
            'rules' => $result['rules'],
            'targets' => $result['targets'],
            'summary' => $result['summary'],
            'principle' => '합계 점수가 징계 기준에 도달한 뒤 24시간 유예를 넘긴 인원입니다.',
        ]);
    }
    // SGMANAGER_FIX5_WARNING_DISCIPLINE_ROUTES_END
    // SGMANAGER_FIX4_TOTAL_DISCIPLINE_ROUTES_START
    if ($path === '/api/admin/discipline-rules' && $method === 'GET') {
        samgyeong_api_current_user($db);
        samgyeong_api_json([
            'ok' => true,
            'source' => 'canonical_total_score_discipline_rules',
            'rules' => samgyeong_api_fix4_canonical_rules(),
            'principle' => samgyeong_api_fix4_discipline_principle(),
        ]);
    }

    if ($path === '/api/admin/discipline-targets' && $method === 'GET') {
        samgyeong_api_current_user($db);
        $result = samgyeong_api_fix4_discipline_targets($db);
        samgyeong_api_json([
            'ok' => true,
            'source' => 'canonical_total_score_discipline_rules',
            'rules' => $result['rules'],
            'targets' => $result['targets'],
            'summary' => $result['summary'],
            'principle' => samgyeong_api_fix4_discipline_principle(),
        ]);
    }
    // SGMANAGER_FIX4_TOTAL_DISCIPLINE_ROUTES_END
    // SGMANAGER_V050_CANONICAL_DISCIPLINE_ROUTES_START
    if ($path === '/api/admin/discipline-rules' && $method === 'GET') {
        samgyeong_api_current_user($db);

        samgyeong_api_json([
            'ok' => true,
            'source' => 'canonical_discipline_rules',
            'rules' => samgyeong_api_v050_canonical_rules(),
            'principle' => samgyeong_api_v050_discipline_principle(),
        ]);
    }

    if ($path === '/api/admin/discipline-targets' && $method === 'GET') {
        samgyeong_api_current_user($db);
        $result = samgyeong_api_v050_discipline_targets($db);

        samgyeong_api_json([
            'ok' => true,
            'source' => 'canonical_discipline_rules',
            'rules' => $result['rules'],
            'targets' => $result['targets'],
            'summary' => $result['summary'],
            'principle' => samgyeong_api_v050_discipline_principle(),
        ]);
    }
    // SGMANAGER_V050_CANONICAL_DISCIPLINE_ROUTES_END
    // SGMANAGER_V049_POINT_RULES_DIRECT_ROUTES_START
    if ($path === '/api/admin/discipline-rules' && $method === 'GET') {
        samgyeong_api_current_user($db);
        $source = samgyeong_api_v049_get_point_rules($db);

        samgyeong_api_json([
            'ok' => true,
            'source' => 'point_rules',
            'rules' => $source['rules'],
            'debug' => $source['debug'],
        ]);
    }

    if ($path === '/api/admin/discipline-targets' && $method === 'GET') {
        samgyeong_api_current_user($db);
        $source = samgyeong_api_v049_get_point_rules($db);
        $rules = $source['rules'];
        $summary = samgyeong_api_point_summary($db);

        $targets = [];
        foreach ($summary as $row) {
            $matched = [];
            foreach ($rules as $rule) {
                if (samgyeong_api_v049_rule_matches($rule, $row)) {
                    $matched[] = $rule;
                }
            }

            if (!$matched) {
                continue;
            }

            $targets[] = [
                'student' => [
                    'id' => (int) $row['id'],
                    'username' => $row['username'] ?? '',
                    'display_name' => $row['display_name'] ?? '',
                    'hall_key' => $row['hall_key'] ?? '',
                    'year' => (int) ($row['year'] ?? 0),
                    'role' => 'student',
                    'is_active' => 1,
                ],
                'merit_total' => (int) ($row['merit_total'] ?? 0),
                'demerit_total' => (int) ($row['demerit_total'] ?? 0),
                'net_score' => (int) ($row['merit_total'] ?? 0) - (int) ($row['demerit_total'] ?? 0),
                'matched_rules' => $matched,
            ];
        }

        usort($targets, function (array $a, array $b): int {
            return ($b['demerit_total'] <=> $a['demerit_total'])
                ?: ($a['net_score'] <=> $b['net_score']);
        });

        samgyeong_api_json([
            'ok' => true,
            'source' => 'point_rules',
            'rules' => $rules,
            'targets' => $targets,
            'debug' => [
                'rule_count' => count($rules),
                'target_count' => count($targets),
                'point_rules' => $source['debug'],
            ],
        ]);
    }
    // SGMANAGER_V049_POINT_RULES_DIRECT_ROUTES_END
    // SGMANAGER_V047_DISCIPLINE_SOURCE_ROUTES_START
    if ($path === '/api/admin/discipline-rules' && $method === 'GET') {
        samgyeong_api_current_user($db);
        $extracted = samgyeong_api_v047_extract_homepage_rules($db);

        samgyeong_api_json([
            'ok' => true,
            'source' => 'rules/discipline',
            'rules' => $extracted['rules'],
            'debug' => [
                'rule_count' => count($extracted['rules']),
                'db_matches' => $extracted['debug']['db_matches'],
                'file_matches' => $extracted['debug']['file_matches'],
            ],
        ]);
    }

    if ($path === '/api/admin/discipline-targets' && $method === 'GET') {
        samgyeong_api_current_user($db);
        $extracted = samgyeong_api_v047_extract_homepage_rules($db);
        $rules = $extracted['rules'];
        $summary = samgyeong_api_point_summary($db);

        $targets = [];
        foreach ($summary as $row) {
            $matched = [];
            foreach ($rules as $rule) {
                if (samgyeong_api_v047_rule_matches($rule, $row)) {
                    $matched[] = $rule;
                }
            }

            if (!$matched) {
                continue;
            }

            $targets[] = [
                'student' => [
                    'id' => (int) $row['id'],
                    'username' => $row['username'] ?? '',
                    'display_name' => $row['display_name'] ?? '',
                    'hall_key' => $row['hall_key'] ?? '',
                    'year' => (int) ($row['year'] ?? 0),
                    'role' => 'student',
                    'is_active' => 1,
                ],
                'merit_total' => (int) ($row['merit_total'] ?? 0),
                'demerit_total' => (int) ($row['demerit_total'] ?? 0),
                'net_score' => (int) ($row['merit_total'] ?? 0) - (int) ($row['demerit_total'] ?? 0),
                'matched_rules' => $matched,
            ];
        }

        usort($targets, function (array $a, array $b): int {
            return ($b['demerit_total'] <=> $a['demerit_total'])
                ?: ($a['net_score'] <=> $b['net_score']);
        });

        samgyeong_api_json([
            'ok' => true,
            'source' => 'rules/discipline',
            'rules' => $rules,
            'targets' => $targets,
            'debug' => [
                'rule_count' => count($rules),
                'target_count' => count($targets),
                'db_matches' => $extracted['debug']['db_matches'],
                'file_matches' => $extracted['debug']['file_matches'],
            ],
        ]);
    }
    // SGMANAGER_V047_DISCIPLINE_SOURCE_ROUTES_END
    // SGMANAGER_V046_HOMEPAGE_DISCIPLINE_ROUTES_START
    if ($path === '/api/admin/discipline-rules' && $method === 'GET') {
        samgyeong_api_current_user($db);
        $rules = samgyeong_api_v046_extract_homepage_discipline_rules($db);
        samgyeong_api_json([
            'ok' => true,
            'source' => 'homepage',
            'rules' => $rules,
        ]);
    }

    if ($path === '/api/admin/discipline-targets' && $method === 'GET') {
        samgyeong_api_current_user($db);
        $rules = samgyeong_api_v046_extract_homepage_discipline_rules($db);
        $summary = samgyeong_api_point_summary($db);

        $targets = [];
        foreach ($summary as $row) {
            $matched = [];
            foreach ($rules as $rule) {
                if (samgyeong_api_v046_rule_matches($rule, $row)) {
                    $matched[] = $rule;
                }
            }

            if (!$matched) {
                continue;
            }

            $targets[] = [
                'student' => [
                    'id' => (int) $row['id'],
                    'username' => $row['username'] ?? '',
                    'display_name' => $row['display_name'] ?? '',
                    'hall_key' => $row['hall_key'] ?? '',
                    'year' => (int) ($row['year'] ?? 0),
                    'role' => 'student',
                    'is_active' => 1,
                ],
                'merit_total' => (int) ($row['merit_total'] ?? 0),
                'demerit_total' => (int) ($row['demerit_total'] ?? 0),
                'net_score' => (int) ($row['merit_total'] ?? 0) - (int) ($row['demerit_total'] ?? 0),
                'matched_rules' => $matched,
            ];
        }

        usort($targets, function (array $a, array $b): int {
            return ($b['demerit_total'] <=> $a['demerit_total'])
                ?: ($a['net_score'] <=> $b['net_score']);
        });

        samgyeong_api_json([
            'ok' => true,
            'source' => 'homepage',
            'rules' => $rules,
            'targets' => $targets,
        ]);
    }
    // SGMANAGER_V046_HOMEPAGE_DISCIPLINE_ROUTES_END
    // SGMANAGER_V041_STUDENT_MANAGEMENT_API_ROUTES_FIX2_START
    if ($path === '/api/admin/students' && $method === 'GET') {
        samgyeong_api_v041_ensure_student_schema($db);

        $includeInactive = in_array(strtolower((string) ($_GET['include_inactive'] ?? '')), ['1', 'true', 'yes', 'all'], true);
        if ($includeInactive) {
            samgyeong_api_current_user($db, ['admin']);
        } else {
            samgyeong_api_current_user($db);
        }

        $activeWhere = $includeInactive ? '' : ' AND COALESCE(is_active, 1) = 1';
        $rows = $db->query("
            SELECT id, username, display_name, hall_key, year, role, photo_path,
                   COALESCE(is_active, 1) AS is_active, created_at
            FROM users
            WHERE role IN ('student', 'council')
              {$activeWhere}
            ORDER BY COALESCE(is_active, 1) DESC, hall_key, year DESC, display_name, username
        ")->fetchAll();

        foreach ($rows as &$row) {
            $row = samgyeong_api_v041_public_student($row);
        }

        samgyeong_api_json(['ok' => true, 'students' => $rows]);
    }

    if ($path === '/api/admin/students' && $method === 'POST') {
        samgyeong_api_v041_ensure_student_schema($db);
        samgyeong_api_current_user($db, ['admin']);

        $input = samgyeong_api_v041_clean_student_input($body, true);
        if (!$input['ok']) {
            samgyeong_api_json($input, 400);
        }

        if (samgyeong_api_v041_username_exists($db, $input['username'])) {
            samgyeong_api_json(['ok' => false, 'error' => 'username_exists'], 409);
        }

        $stmt = $db->prepare("
            INSERT INTO users (
                username, password_hash, role, display_name, hall_key, year, photo_path, is_active, must_change_password
            )
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");
        $stmt->execute([
            $input['username'],
            password_hash($input['password'], PASSWORD_DEFAULT),
            $input['role'],
            $input['display_name'],
            $input['hall_key'],
            $input['year'],
            $input['photo_path'],
            $input['is_active'],
            1,
        ]);

        $id = (int) $db->lastInsertId();
        samgyeong_api_v041_sync_hall_member($db, $id);

        samgyeong_api_json([
            'ok' => true,
            'inserted' => true,
            'student' => samgyeong_api_v041_fetch_student($db, $id),
        ], 201);
    }

    if (preg_match('#^/api/admin/students/(\d+)$#', $path, $m) && $method === 'PATCH') {
        samgyeong_api_v041_ensure_student_schema($db);
        samgyeong_api_current_user($db, ['admin']);

        $id = (int) $m[1];
        $current = samgyeong_api_v041_fetch_student($db, $id);
        if (!$current) {
            samgyeong_api_json(['ok' => false, 'error' => 'student_not_found'], 404);
        }

        $updates = [];
        $params = [];

        if (array_key_exists('username', $body)) {
            $username = trim((string) $body['username']);
            if ($username === '' || !preg_match('/^[A-Za-z0-9_.-]{3,40}$/', $username)) {
                samgyeong_api_json(['ok' => false, 'error' => 'invalid_username'], 400);
            }
            if (samgyeong_api_v041_username_exists($db, $username, $id)) {
                samgyeong_api_json(['ok' => false, 'error' => 'username_exists'], 409);
            }
            $updates[] = 'username = ?';
            $params[] = $username;
        }

        if (array_key_exists('display_name', $body) || array_key_exists('displayName', $body)) {
            $displayName = samgyeong_api_trim_text((string) ($body['display_name'] ?? $body['displayName'] ?? ''), 80);
            if ($displayName === '') {
                samgyeong_api_json(['ok' => false, 'error' => 'empty_display_name'], 400);
            }
            $updates[] = 'display_name = ?';
            $params[] = $displayName;
        }

        if (array_key_exists('role', $body)) {
            $role = trim((string) $body['role']);
            if (!samgyeong_api_v041_is_student_role($role)) {
                samgyeong_api_json(['ok' => false, 'error' => 'invalid_role'], 400);
            }
            $updates[] = 'role = ?';
            $params[] = $role;
        }

        if (array_key_exists('hall_key', $body) || array_key_exists('hallKey', $body)) {
            $hallKey = trim((string) ($body['hall_key'] ?? $body['hallKey'] ?? ''));
            if (!samgyeong_api_v041_is_hall_key($hallKey)) {
                samgyeong_api_json(['ok' => false, 'error' => 'invalid_hall_key'], 400);
            }
            $updates[] = 'hall_key = ?';
            $params[] = $hallKey;
        }

        if (array_key_exists('year', $body)) {
            $year = (int) $body['year'];
            if ($year < 1 || $year > 3) {
                samgyeong_api_json(['ok' => false, 'error' => 'invalid_year'], 400);
            }
            $updates[] = 'year = ?';
            $params[] = $year;
        }

        if (array_key_exists('photo_path', $body)) {
            $photoPath = samgyeong_api_trim_text((string) ($body['photo_path'] ?? ''), 200);
            $updates[] = 'photo_path = ?';
            $params[] = $photoPath === '' ? null : $photoPath;
        }

        if (array_key_exists('password', $body)) {
            $password = (string) $body['password'];
            if ($password !== '') {
                if (strlen($password) < 4) {
                    samgyeong_api_json(['ok' => false, 'error' => 'password_too_short'], 400);
                }
                $updates[] = 'password_hash = ?';
                $params[] = password_hash($password, PASSWORD_DEFAULT);
                $updates[] = 'must_change_password = 0';
            }
        }

        if (array_key_exists('is_active', $body)) {
            $updates[] = 'is_active = ?';
            $params[] = filter_var($body['is_active'], FILTER_VALIDATE_BOOLEAN) ? 1 : 0;
        }

        if (!$updates) {
            samgyeong_api_json(['ok' => false, 'error' => 'no_fields'], 400);
        }

        $params[] = $id;
        $stmt = $db->prepare('UPDATE users SET ' . implode(', ', $updates) . ' WHERE id = ?');
        $stmt->execute($params);

        samgyeong_api_v041_sync_hall_member($db, $id);

        if (array_key_exists('is_active', $body) && !filter_var($body['is_active'], FILTER_VALIDATE_BOOLEAN)) {
            $stmt = $db->prepare('UPDATE api_tokens SET revoked_at = CURRENT_TIMESTAMP WHERE user_id = ? AND revoked_at IS NULL');
            $stmt->execute([$id]);
        }

        samgyeong_api_json([
            'ok' => true,
            'updated' => true,
            'student' => samgyeong_api_v041_fetch_student($db, $id),
        ]);
    }

    if (preg_match('#^/api/admin/students/(\d+)/deactivate$#', $path, $m) && $method === 'POST') {
        samgyeong_api_v041_ensure_student_schema($db);
        samgyeong_api_current_user($db, ['admin']);

        $id = (int) $m[1];
        $current = samgyeong_api_v041_fetch_student($db, $id);
        if (!$current) {
            samgyeong_api_json(['ok' => false, 'error' => 'student_not_found'], 404);
        }

        $stmt = $db->prepare("UPDATE users SET is_active = 0 WHERE id = ? AND role IN ('student', 'council')");
        $stmt->execute([$id]);

        $stmt = $db->prepare('UPDATE api_tokens SET revoked_at = CURRENT_TIMESTAMP WHERE user_id = ? AND revoked_at IS NULL');
        $stmt->execute([$id]);

        samgyeong_api_v041_sync_hall_member($db, $id);

        samgyeong_api_json([
            'ok' => true,
            'deactivated' => true,
            'student' => samgyeong_api_v041_fetch_student($db, $id),
        ]);
    }

    if (preg_match('#^/api/admin/students/(\d+)/reactivate$#', $path, $m) && $method === 'POST') {
        samgyeong_api_v041_ensure_student_schema($db);
        samgyeong_api_current_user($db, ['admin']);

        $id = (int) $m[1];
        $current = samgyeong_api_v041_fetch_student($db, $id);
        if (!$current) {
            samgyeong_api_json(['ok' => false, 'error' => 'student_not_found'], 404);
        }

        $stmt = $db->prepare("UPDATE users SET is_active = 1 WHERE id = ? AND role IN ('student', 'council')");
        $stmt->execute([$id]);

        samgyeong_api_v041_sync_hall_member($db, $id);

        samgyeong_api_json([
            'ok' => true,
            'reactivated' => true,
            'student' => samgyeong_api_v041_fetch_student($db, $id),
        ]);
    }
    // SGMANAGER_V041_STUDENT_MANAGEMENT_API_ROUTES_FIX2_END
    if ($path === '/api/admin/students' && $method === 'GET') {
        samgyeong_api_current_user($db);
        $rows = $db->query("\n            SELECT id, username, display_name, hall_key, year, role, photo_path\n            FROM users\n            WHERE role IN ('student', 'council')\n            ORDER BY hall_key, year DESC, display_name, username\n        ")->fetchAll();

        foreach ($rows as &$row) {
            $row['id'] = (int) $row['id'];
            $row['year'] = (int) $row['year'];
        }

        samgyeong_api_json(['ok' => true, 'students' => $rows]);
    }

    if ($path === '/api/admin/points/check-duplicate' && $method === 'POST') {
        samgyeong_api_current_user($db);
        $result = samgyeong_api_check_point_duplicate($db, $body);
        if (!$result['ok']) {
            samgyeong_api_json($result, 400);
        }
        samgyeong_api_json($result);
    }

    if ($path === '/api/admin/points' && $method === 'POST') {
        $issuer = samgyeong_api_current_user($db);
        $result = samgyeong_api_insert_point($db, $issuer, $body);
        if (!$result['ok']) {
            samgyeong_api_json($result, 400);
        }
        samgyeong_api_json($result);
    }

    if ($path === '/api/admin/points/bulk' && $method === 'POST') {
        $issuer = samgyeong_api_current_user($db);
        $records = $body['records'] ?? [];
        if (!is_array($records)) {
            samgyeong_api_json(['ok' => false, 'error' => 'records_required'], 400);
        }

        $saved = [];
        $duplicates = [];
        $failed = [];

        $db->beginTransaction();
        try {
            foreach ($records as $index => $record) {
                if (!is_array($record)) {
                    $failed[] = ['index' => $index, 'error' => 'invalid_record'];
                    continue;
                }

                $result = samgyeong_api_insert_point($db, $issuer, $record);
                if ($result['ok'] && ($result['inserted'] ?? false)) {
                    $saved[] = ['index' => $index, 'id' => $result['id']];
                } elseif ($result['ok'] && ($result['duplicate'] ?? false)) {
                    $duplicates[] = [
                        'index' => $index,
                        'id' => $result['id'] ?? null,
                        'duplicate_type' => $result['duplicate_type'] ?? 'unknown',
                        'existing_record' => $result['existing_record'] ?? null,
                    ];
                } else {
                    $failed[] = ['index' => $index, 'error' => $result['error'] ?? 'unknown_error'];
                }
            }
            $db->commit();
        } catch (Throwable $e) {
            $db->rollBack();
            samgyeong_api_json(['ok' => false, 'error' => 'bulk_save_failed'], 500);
        }

        samgyeong_api_json([
            'ok' => true,
            'saved_count' => count($saved),
            'duplicate_count' => count($duplicates),
            'failed_count' => count($failed),
            'saved' => $saved,
            'duplicates' => $duplicates,
            'failed' => $failed,
        ]);
    }

    if ($path === '/api/admin/points/recent' && $method === 'GET') {
        samgyeong_api_current_user($db);
        $limit = max(1, min(200, (int) ($_GET['limit'] ?? 50)));

        $stmt = $db->prepare("\n            SELECT point_records.*,\n                   target.display_name AS target_name,\n                   target.username AS target_username,\n                   issuer.display_name AS issuer_name,\n                   issuer.username AS issuer_username\n            FROM point_records\n            JOIN users AS target ON target.id = point_records.user_id\n            JOIN users AS issuer ON issuer.id = point_records.issuer_id\n            ORDER BY point_records.issued_at DESC, point_records.id DESC\n            LIMIT ?\n        ");
        $stmt->bindValue(1, $limit, PDO::PARAM_INT);
        $stmt->execute();

        samgyeong_api_json(['ok' => true, 'records' => $stmt->fetchAll()]);
    }

    if ($path === '/api/admin/points/summary' && $method === 'GET') {
        samgyeong_api_current_user($db);
        samgyeong_api_json(['ok' => true, 'summary' => samgyeong_api_point_summary($db)]);
    }

    if ($path === '/api/admin/points/text' && $method === 'GET') {
        samgyeong_api_current_user($db);
        $rows = samgyeong_api_point_summary($db);

        $lines = ['[삼경 상벌점 현황 ' . date('Y-m-d') . ']'];
        foreach ($rows as $row) {
            if ((int) $row['merit_total'] === 0 && (int) $row['demerit_total'] === 0) {
                continue;
            }

            $name = trim((string) ($row['display_name'] ?: $row['username']));
            $lines[] = sprintf(
                '%s: 상점 %d점 / 벌점 %d점 / 소원권 %d개',
                $name,
                (int) $row['merit_total'],
                (int) $row['demerit_total'],
                (int) $row['wish_coupons']
            );
        }

        if (count($lines) === 1) {
            $lines[] = '상벌점 기록이 없습니다.';
        }

        samgyeong_api_json([
            'ok' => true,
            'text' => implode("\n", $lines),
        ]);
    }

    samgyeong_api_json(['ok' => false, 'error' => 'not_found'], 404);
}
