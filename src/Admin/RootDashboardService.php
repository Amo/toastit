<?php

namespace App\Admin;

use Doctrine\DBAL\Connection;

final class RootDashboardService
{
    public function __construct(
        private readonly Connection $connection,
    ) {
    }

    /**
     * @return array{
     *   otpRequestsOverTime: list<array{day: string, count: int}>,
     *   emailsReceivedOverTime: list<array{day: string, count: int}>,
     *   xaiCallsOverTime: list<array{day: string, count: int}>
     * }
     */
    public function buildOverview(): array
    {
        return [
            'otpRequestsOverTime' => $this->fetchSeries('auth.otp_requested'),
            'emailsReceivedOverTime' => $this->fetchSeries('inbound.email_received'),
            'xaiCallsOverTime' => $this->fetchSeries('xai.call'),
        ];
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function buildUsers(): array
    {
        $sql = <<<'SQL'
SELECT
    u.id,
    u.email,
    u.created_at AS createdAt,
    COALESCE(last_event.occurred_at, last_refresh.lastSeenAt) AS lastConnectionAt,
    COALESCE(workspaces.workspaceCount, 0) AS workspaceCount,
    COALESCE(toasts.totalToastCount, 0) AS totalToastCount,
    COALESCE(toasts.activeToastCount, 0) AS activeToastCount,
    COALESCE(xai.totalXaiCallCount, 0) AS totalXaiCallCount
FROM user u
LEFT JOIN (
    SELECT organizer_id AS user_id, COUNT(*) AS workspaceCount
    FROM team
    WHERE deleted_at IS NULL
    GROUP BY organizer_id
) workspaces ON workspaces.user_id = u.id
LEFT JOIN (
    SELECT
        author_id AS user_id,
        COUNT(*) AS totalToastCount,
        SUM(CASE WHEN status = 'open' AND discussion_status = 'pending' THEN 1 ELSE 0 END) AS activeToastCount
    FROM parking_lot_item
    GROUP BY author_id
) toasts ON toasts.user_id = u.id
LEFT JOIN (
    SELECT user_id, COUNT(*) AS totalXaiCallCount
    FROM app_event
    WHERE kind = 'xai.call'
      AND status = 'succeeded'
      AND user_id IS NOT NULL
    GROUP BY user_id
) xai ON xai.user_id = u.id
LEFT JOIN (
    SELECT user_id, MAX(occurred_at) AS occurred_at
    FROM app_event
    WHERE kind = 'auth.login_succeeded'
      AND user_id IS NOT NULL
    GROUP BY user_id
) last_event ON last_event.user_id = u.id
LEFT JOIN (
    SELECT user_id, MAX(last_used_at) AS lastSeenAt
    FROM api_refresh_token
    GROUP BY user_id
) last_refresh ON last_refresh.user_id = u.id
ORDER BY u.created_at DESC, u.id DESC
SQL;

        return array_map(static function (array $row): array {
            return [
                'id' => (int) $row['id'],
                'email' => (string) $row['email'],
                'createdAt' => (string) $row['createdAt'],
                'lastConnectionAt' => null !== $row['lastConnectionAt'] ? (string) $row['lastConnectionAt'] : null,
                'workspaceCount' => (int) $row['workspaceCount'],
                'totalToastCount' => (int) $row['totalToastCount'],
                'activeToastCount' => (int) $row['activeToastCount'],
                'totalXaiCallCount' => (int) $row['totalXaiCallCount'],
            ];
        }, $this->connection->fetchAllAssociative($sql));
    }

    /**
     * @return list<array{day: string, count: int}>
     */
    private function fetchSeries(string $kind): array
    {
        $sql = <<<'SQL'
SELECT DATE(occurred_at) AS day, COUNT(*) AS count
FROM app_event
WHERE kind = :kind
  AND occurred_at >= DATE_SUB(UTC_TIMESTAMP(), INTERVAL 30 DAY)
GROUP BY DATE(occurred_at)
ORDER BY day ASC
SQL;

        return array_map(static function (array $row): array {
            return [
                'day' => (string) $row['day'],
                'count' => (int) $row['count'],
            ];
        }, $this->connection->fetchAllAssociative($sql, ['kind' => $kind]));
    }
}
