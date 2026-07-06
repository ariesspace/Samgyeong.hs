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

    $stmt = $db->prepare("SELECT id FROM users WHERE id = ? AND role IN ('student', 'council')");
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
    $stmt = $db->query("\n        SELECT\n            users.id,\n            users.username,\n            users.display_name,\n            users.hall_key,\n            users.year,\n            COALESCE(SUM(CASE\n                WHEN point_records.type = 'merit'\n                 AND point_records.canceled_at IS NULL\n                 AND point_records.cancellation_of_id IS NULL\n                THEN point_records.points ELSE 0 END), 0) AS merit_total,\n            COALESCE(SUM(CASE\n                WHEN point_records.type = 'demerit'\n                 AND point_records.canceled_at IS NULL\n                 AND point_records.cancellation_of_id IS NULL\n                THEN point_records.points ELSE 0 END), 0) AS demerit_total\n        FROM users\n        LEFT JOIN point_records ON point_records.user_id = users.id\n        WHERE users.role IN ('student', 'council')\n        GROUP BY users.id\n        ORDER BY users.hall_key, users.year DESC, users.display_name, users.username\n    ");

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

        $token = bin2hex(random_bytes(32));
        $tokenHash = hash('sha256', $token);
        $expiresAt = date('Y-m-d H:i:s', time() + 60 * 60 * 24 * 30);

        $stmt = $db->prepare('INSERT INTO api_tokens (user_id, token_hash, expires_at) VALUES (?, ?, ?)');
        $stmt->execute([(int) $user['id'], $tokenHash, $expiresAt]);

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
