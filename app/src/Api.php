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
    samgyeong_api_v0431_ensure_schema($db);
    $stmt = $db->query('SELECT * FROM api_app_access_codes WHERE revoked_at IS NULL ORDER BY id DESC');
    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
        if (password_verify($code, (string) $row['code_hash'])) {
            return $row;
        }
    }
    return null;
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
    samgyeong_api_v0431_ensure_schema($db);
    $codeId = isset($GLOBALS['samgyeong_v0431_app_access_code_id']) ? (int) $GLOBALS['samgyeong_v0431_app_access_code_id'] : 0;
    if ($codeId <= 0) {
        return;
    }

    $stmt = $db->prepare('UPDATE api_tokens SET app_access_code_id = ? WHERE token_hash = ?');
    $stmt->execute([$codeId, $tokenHash]);

    $stmt = $db->prepare('INSERT INTO api_app_access_code_uses (code_id, user_id, ip_address, user_agent) VALUES (?, ?, ?, ?)');
    $stmt->execute([
        $codeId,
        (int) $user['id'],
        $_SERVER['REMOTE_ADDR'] ?? null,
        $_SERVER['HTTP_USER_AGENT'] ?? null,
    ]);
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
function samgyeong_api_handle_request(PDO $db, string $path, string $method): never
{
    samgyeong_api_ensure_schema($db);

    if ($method === 'OPTIONS') {
        samgyeong_api_json(['ok' => true], 200);
    }

    $path = rtrim($path, '/') ?: '/';
    $body = samgyeong_api_body();

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
                username, password_hash, role, display_name, hall_key, year, photo_path, is_active
            )
            VALUES (?, ?, ?, ?, ?, ?, ?, ?)
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
