<?php

declare(strict_types=1);

final class TallyWebhookController
{
    private const BOARD_SLUG = 'basic-literacy';

    public function __construct(private PDO $db)
    {
    }

    public function handle(string $rawBody): never
    {
        header('Content-Type: application/json; charset=utf-8');

        if (!$this->verifySignature($rawBody)) {
            http_response_code(401);
            echo json_encode(['ok' => false, 'message' => 'Invalid Tally signature'], JSON_UNESCAPED_UNICODE);
            exit;
        }

        $payload = json_decode($rawBody, true);
        if (!is_array($payload)) {
            http_response_code(400);
            echo json_encode(['ok' => false, 'message' => 'Invalid JSON payload'], JSON_UNESCAPED_UNICODE);
            exit;
        }

        $eventId = $this->eventId($payload, $rawBody);
        $existing = $this->existingPostId($eventId);
        if ($existing !== null) {
            echo json_encode(['ok' => true, 'duplicate' => true, 'post_id' => $existing], JSON_UNESCAPED_UNICODE);
            exit;
        }

        $answers = $this->answers($payload);
        $files = $this->downloadFiles($this->fileAnswers($answers));
        $adminId = $this->adminUserId();
        $title = $this->postTitle($payload, $answers);
        $body = $this->postBody($payload, $answers);

        $this->db->beginTransaction();
        try {
            $stmt = $this->db->prepare('
                INSERT INTO posts (board, tag, title, body, file_name, file_path, author_id)
                VALUES (?, ?, ?, ?, ?, ?, ?)
            ');
            $firstFile = $files[0] ?? ['name' => null, 'path' => null];
            $stmt->execute([
                self::BOARD_SLUG,
                '제출',
                $title,
                $body,
                $firstFile['name'],
                $firstFile['path'],
                $adminId,
            ]);

            $postId = (int) $this->db->lastInsertId();
            $this->insertPostFiles($postId, $files);
            $this->recordEvent($eventId, $postId, $rawBody);
            $this->db->commit();
        } catch (Throwable $exception) {
            $this->db->rollBack();
            http_response_code(500);
            echo json_encode(['ok' => false, 'message' => 'Failed to store submission'], JSON_UNESCAPED_UNICODE);
            exit;
        }

        echo json_encode(['ok' => true, 'post_id' => $postId], JSON_UNESCAPED_UNICODE);
        exit;
    }

    private function verifySignature(string $rawBody): bool
    {
        $secret = trim((string) getenv('SAMGYEONG_TALLY_SIGNING_SECRET'));
        if ($secret === '') {
            return false;
        }

        $signature = trim((string) ($_SERVER['HTTP_TALLY_SIGNATURE'] ?? ''));
        if ($signature === '') {
            return false;
        }

        $signature = preg_replace('/^sha256=/i', '', $signature) ?? $signature;
        $decoded = json_decode($rawBody, true);
        $normalizedBody = is_array($decoded)
            ? json_encode($decoded, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)
            : null;

        $candidates = [
            hash_hmac('sha256', $rawBody, $secret),
            base64_encode(hash_hmac('sha256', $rawBody, $secret, true)),
        ];

        if (is_string($normalizedBody)) {
            $candidates[] = hash_hmac('sha256', $normalizedBody, $secret);
            $candidates[] = base64_encode(hash_hmac('sha256', $normalizedBody, $secret, true));
        }

        foreach (array_unique($candidates) as $expected) {
            if (hash_equals($expected, $signature)) {
                return true;
            }
        }

        return false;
    }

    private function eventId(array $payload, string $rawBody): string
    {
        foreach ([
            $payload['eventId'] ?? null,
            $payload['id'] ?? null,
            $payload['data']['responseId'] ?? null,
            $payload['data']['submissionId'] ?? null,
            $payload['responseId'] ?? null,
            $payload['submissionId'] ?? null,
        ] as $candidate) {
            if (is_string($candidate) && trim($candidate) !== '') {
                return trim($candidate);
            }
        }

        return hash('sha256', $rawBody);
    }

    private function existingPostId(string $eventId): ?int
    {
        $stmt = $this->db->prepare('SELECT post_id FROM tally_webhook_events WHERE event_id = ?');
        $stmt->execute([$eventId]);
        $value = $stmt->fetchColumn();

        if ($value === false) {
            return null;
        }

        $postId = (int) $value;
        $stmt = $this->db->prepare('SELECT COUNT(*) FROM posts WHERE id = ? AND board = ?');
        $stmt->execute([$postId, self::BOARD_SLUG]);
        if ((int) $stmt->fetchColumn() > 0) {
            return $postId;
        }

        $this->db->prepare('DELETE FROM tally_webhook_events WHERE event_id = ?')->execute([$eventId]);
        return null;
    }

    private function answers(array $payload): array
    {
        $fields = $payload['data']['fields'] ?? $payload['fields'] ?? $payload['data']['answers'] ?? $payload['answers'] ?? [];
        if (!is_array($fields)) {
            return [];
        }

        $answers = [];
        foreach ($fields as $index => $field) {
            if (!is_array($field)) {
                continue;
            }

            if ($this->isDerivedCheckboxField($field)) {
                continue;
            }

            $label = $this->fieldLabel($field, (int) $index);
            $value = $field['value'] ?? $field['answer'] ?? $field['text'] ?? $field['values'] ?? null;
            $answers[] = [
                'label' => $label,
                'value' => $value,
                'display' => $this->displayFieldValue($field, $value),
            ];
        }

        return array_values(array_filter($answers, fn (array $answer): bool => $answer['display'] !== ''));
    }

    private function fieldLabel(array $field, int $index): string
    {
        $key = (string) ($field['key'] ?? '');
        $aliases = [
            'question_ELopOl' => '지원자 이름',
            'question_rV8XZo' => '지원학년',
            'question_4LlZbr' => '직속팸 경험 여부',
            'question_jL8KVQ' => '활동 가능한 시간대',
        ];

        if (isset($aliases[$key])) {
            return $aliases[$key];
        }

        foreach (['label', 'title', 'question', 'name', 'key'] as $key) {
            if (isset($field[$key]) && is_string($field[$key]) && trim($field[$key]) !== '') {
                return trim($field[$key]);
            }
        }

        return '답변 ' . ($index + 1);
    }

    private function isDerivedCheckboxField(array $field): bool
    {
        return ($field['type'] ?? '') === 'CHECKBOXES'
            && is_string($field['key'] ?? null)
            && preg_match('/^question_[A-Za-z0-9]+_[0-9a-f-]{36}$/', $field['key']) === 1;
    }

    private function displayFieldValue(array $field, mixed $value): string
    {
        if (is_array($value) && isset($field['options']) && is_array($field['options'])) {
            $optionMap = [];
            foreach ($field['options'] as $option) {
                if (!is_array($option) || !isset($option['id'], $option['text'])) {
                    continue;
                }
                $optionMap[(string) $option['id']] = $this->normalizeOptionText((string) $option['text']);
            }

            $mapped = [];
            foreach ($value as $item) {
                if (is_scalar($item)) {
                    $mapped[] = $optionMap[(string) $item] ?? (string) $item;
                }
            }

            if ($mapped !== []) {
                return implode(', ', array_filter($mapped));
            }
        }

        return $this->displayValue($value);
    }

    private function normalizeOptionText(string $text): string
    {
        $text = trim($text);
        return match ($text) {
            '1俳鰍' => '1학년',
            '2俳鰍' => '2학년',
            '3俳鰍' => '3학년',
            '森' => '있음',
            '焼艦神' => '없음',
            default => $text,
        };
    }

    private function displayValue(mixed $value): string
    {
        if ($value === null || $value === '') {
            return '';
        }

        if (is_bool($value)) {
            return $value ? '예' : '아니오';
        }

        if (is_scalar($value)) {
            return trim((string) $value);
        }

        if (is_array($value)) {
            $parts = [];
            foreach ($value as $item) {
                if (is_array($item)) {
                    $parts[] = $this->displayValue($item['text'] ?? $item['name'] ?? $item['label'] ?? $item['url'] ?? $item);
                } else {
                    $parts[] = $this->displayValue($item);
                }
            }

            return trim(implode(', ', array_filter($parts)));
        }

        return '';
    }

    private function fileAnswers(array $answers): array
    {
        $files = [];
        foreach ($answers as $answer) {
            $this->collectFiles($answer['value'], $files);
        }

        return $files;
    }

    private function collectFiles(mixed $value, array &$files): void
    {
        if (!is_array($value)) {
            return;
        }

        if (isset($value['url']) && is_string($value['url'])) {
            $files[] = [
                'url' => $value['url'],
                'name' => is_string($value['name'] ?? null) ? $value['name'] : basename(parse_url($value['url'], PHP_URL_PATH) ?: 'tally-file'),
            ];
            return;
        }

        foreach ($value as $item) {
            $this->collectFiles($item, $files);
        }
    }

    private function downloadFiles(array $fileAnswers): array
    {
        $storedFiles = [];
        $uploadDir = __DIR__ . '/../storage/uploads';
        if (!is_dir($uploadDir)) {
            mkdir($uploadDir, 0775, true);
        }

        foreach ($fileAnswers as $file) {
            $url = (string) ($file['url'] ?? '');
            if (!preg_match('#^https://#i', $url)) {
                continue;
            }

            $original = basename((string) ($file['name'] ?? 'tally-file'));
            $safeName = preg_replace('/[^A-Za-z0-9._-]/', '_', $original) ?: 'tally-file';
            $stored = date('YmdHis') . '_' . bin2hex(random_bytes(4)) . '_' . $safeName;
            $target = $uploadDir . '/' . $stored;

            $context = stream_context_create(['http' => ['timeout' => 10], 'https' => ['timeout' => 10]]);
            $contents = @file_get_contents($url, false, $context);
            if ($contents === false || $contents === '') {
                continue;
            }

            file_put_contents($target, $contents);
            $storedFiles[] = ['name' => $original, 'path' => $stored];
        }

        return $storedFiles;
    }

    private function postTitle(array $payload, array $answers): string
    {
        $fields = is_array($payload['data']['fields'] ?? null) ? $payload['data']['fields'] : [];
        $name = $this->displayValue($this->fieldValueByKey($fields, 'question_ELopOl'));
        $gradeField = $this->fieldByKey($fields, 'question_rV8XZo');
        $grade = $gradeField ? $this->displayFieldValue($gradeField, $gradeField['value'] ?? null) : '';

        if ($name === '') {
            $name = $this->answerByLabel($answers, ['이름', '성명', '학생 이름', '지원자 이름', '제출자']);
        }
        if ($grade === '') {
            $grade = $this->answerByLabel($answers, ['학년', '지원 학년']);
        }

        $date = substr((string) ($payload['createdAt'] ?? $payload['data']['createdAt'] ?? date('Y-m-d')), 0, 10);

        if ($name !== '') {
            $prefix = $grade !== '' ? $grade . ' ' : '';
            return $prefix . $name . ' 입학생 기초 소양 제출';
        }

        return '입학생 기초 소양 제출 - ' . $date;
    }

    private function fieldByKey(array $fields, string $key): ?array
    {
        foreach ($fields as $field) {
            if (is_array($field) && ($field['key'] ?? '') === $key) {
                return $field;
            }
        }

        return null;
    }

    private function fieldValueByKey(array $fields, string $key): mixed
    {
        $field = $this->fieldByKey($fields, $key);
        return $field['value'] ?? null;
    }

    private function answerByLabel(array $answers, array $needles): string
    {
        foreach ($answers as $answer) {
            $label = $answer['label'];
            foreach ($needles as $needle) {
                if (mb_stripos($label, $needle) !== false) {
                    return $answer['display'];
                }
            }
        }

        return '';
    }

    private function postBody(array $payload, array $answers): string
    {
        $lines = [];
        $lines[] = '<p><strong>Tally 제출이 자동으로 등록되었습니다.</strong></p>';
        $lines[] = '<ul>';
        foreach ($answers as $answer) {
            $lines[] = '<li><strong>' . e($answer['label']) . '</strong>: ' . e($answer['display']) . '</li>';
        }
        $lines[] = '</ul>';

        $submittedAt = $payload['createdAt'] ?? $payload['data']['createdAt'] ?? null;
        if (is_string($submittedAt) && $submittedAt !== '') {
            $lines[] = '<p>제출 시각: ' . e($submittedAt) . '</p>';
        }

        return sanitize_post_body(implode("\n", $lines));
    }

    private function adminUserId(): int
    {
        $stmt = $this->db->query("SELECT id FROM users WHERE username = 'admin' LIMIT 1");
        $adminId = (int) $stmt->fetchColumn();
        if ($adminId > 0) {
            return $adminId;
        }

        return (int) $this->db->query('SELECT id FROM users ORDER BY id ASC LIMIT 1')->fetchColumn();
    }

    private function insertPostFiles(int $postId, array $files): void
    {
        if ($files === []) {
            return;
        }

        $stmt = $this->db->prepare('INSERT INTO post_files (post_id, file_name, file_path) VALUES (?, ?, ?)');
        foreach ($files as $file) {
            $stmt->execute([$postId, $file['name'], $file['path']]);
        }
    }

    private function recordEvent(string $eventId, int $postId, string $rawBody): void
    {
        $stmt = $this->db->prepare('
            INSERT INTO tally_webhook_events (event_id, board, post_id, payload)
            VALUES (?, ?, ?, ?)
        ');
        $stmt->execute([$eventId, self::BOARD_SLUG, $postId, $rawBody]);
    }
}
