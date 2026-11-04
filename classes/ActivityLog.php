<?php
declare(strict_types=1);

/**
 * Log aktivitas penting pengguna.
 */
final class ActivityLog
{
    public static function record(
        string $activity,
        string $description = '',
        ?string $relatedType = null,
        ?int $relatedId = null
    ): void {
        $actor = $_SESSION['auth_user'] ?? [];
        self::write(
            isset($actor['id']) ? (int) $actor['id'] : null,
            isset($actor['username']) ? (string) $actor['username'] : null,
            isset($actor['role']) ? (string) $actor['role'] : null,
            $activity,
            $description,
            $relatedType,
            $relatedId
        );
    }

    /**
     * Untuk percobaan login gagal (belum ada user login).
     */
    public static function recordGuest(string $username, string $activity, string $description = ''): void
    {
        self::write(null, mb_substr($username, 0, 50), 'guest', $activity, $description, null, null);
    }

    private static function write(
        ?int $userId,
        ?string $username,
        ?string $role,
        string $activity,
        string $description,
        ?string $relatedType,
        ?int $relatedId
    ): void {
        try {
            $stmt = db()->prepare(
                'INSERT INTO activity_logs
                    (user_id, username, role, activity, description, related_type, related_id, ip_address, user_agent, created_at)
                 VALUES
                    (:user_id, :username, :role, :activity, :description, :related_type, :related_id, :ip, :agent, NOW())'
            );
            $stmt->execute([
                ':user_id'      => $userId,
                ':username'     => $username,
                ':role'         => $role,
                ':activity'     => mb_substr($activity, 0, 100),
                ':description'  => $description !== '' ? $description : null,
                ':related_type' => $relatedType,
                ':related_id'   => $relatedId,
                ':ip'           => client_ip() !== '' ? client_ip() : null,
                ':agent'        => client_user_agent() !== '' ? client_user_agent() : null,
            ]);
        } catch (Throwable $exception) {
            @file_put_contents(
                LOG_PATH . '/app.log',
                '[' . date('Y-m-d H:i:s') . '] ACTIVITY LOG ERROR: ' . $exception->getMessage() . PHP_EOL,
                FILE_APPEND | LOCK_EX
            );
        }
    }

    /**
     * Daftar log dengan filter dan pagination server-side.
     *
     * @param array{user_id?:int,activity?:string,q?:string,date_from?:string,date_to?:string} $filters
     * @return array{items: array<int, array<string, mixed>>, total: int, pages: int}
     */
    public static function paginate(array $filters, int $page = 1, int $perPage = 25): array
    {
        $where = [];
        $params = [];

        if (!empty($filters['user_id'])) {
            $where[] = 'l.user_id = :user_id';
            $params[':user_id'] = (int) $filters['user_id'];
        }
        if (!empty($filters['activity'])) {
            $where[] = 'l.activity = :activity';
            $params[':activity'] = (string) $filters['activity'];
        }
        if (!empty($filters['q'])) {
            $where[] = '(l.description LIKE :q1 OR l.username LIKE :q2 OR l.activity LIKE :q3)';
            $keyword = '%' . $filters['q'] . '%';
            $params[':q1'] = $keyword;
            $params[':q2'] = $keyword;
            $params[':q3'] = $keyword;
        }
        if (!empty($filters['date_from'])) {
            $where[] = 'l.created_at >= :date_from';
            $params[':date_from'] = $filters['date_from'] . ' 00:00:00';
        }
        if (!empty($filters['date_to'])) {
            $where[] = 'l.created_at <= :date_to';
            $params[':date_to'] = $filters['date_to'] . ' 23:59:59';
        }

        $whereSql = $where !== [] ? ' WHERE ' . implode(' AND ', $where) : '';

        $countStmt = db()->prepare('SELECT COUNT(*) FROM activity_logs l' . $whereSql);
        $countStmt->execute($params);
        $total = (int) $countStmt->fetchColumn();

        $perPage = max(10, min(100, $perPage));
        $pages = max(1, (int) ceil($total / $perPage));
        $page = max(1, min($page, $pages));
        $offset = ($page - 1) * $perPage;

        $stmt = db()->prepare(
            'SELECT l.*, u.name AS user_name
             FROM activity_logs l
             LEFT JOIN users u ON u.id = l.user_id'
            . $whereSql
            . ' ORDER BY l.created_at DESC, l.id DESC LIMIT :limit OFFSET :offset'
        );
        foreach ($params as $key => $value) {
            $stmt->bindValue($key, $value);
        }
        $stmt->bindValue(':limit', $perPage, PDO::PARAM_INT);
        $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
        $stmt->execute();

        return [
            'items' => $stmt->fetchAll(),
            'total' => $total,
            'pages' => $pages,
        ];
    }

    /**
     * Log yang terkait dengan satu record (mis. riwayat perubahan group).
     *
     * @return array<int, array<string, mixed>>
     */
    public static function forRelated(string $relatedType, int $relatedId, int $limit = 50): array
    {
        $limit = max(1, min(200, $limit));
        $stmt = db()->prepare(
            'SELECT l.*, u.name AS user_name
             FROM activity_logs l
             LEFT JOIN users u ON u.id = l.user_id
             WHERE l.related_type = :related_type AND l.related_id = :related_id
             ORDER BY l.created_at DESC, l.id DESC
             LIMIT :limit'
        );
        $stmt->bindValue(':related_type', $relatedType);
        $stmt->bindValue(':related_id', $relatedId, PDO::PARAM_INT);
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll();
    }

    /**
     * @return array<int, string>
     */
    public static function activityOptions(): array
    {
        return db()->query('SELECT DISTINCT activity FROM activity_logs ORDER BY activity ASC')
            ->fetchAll(PDO::FETCH_COLUMN);
    }
}
