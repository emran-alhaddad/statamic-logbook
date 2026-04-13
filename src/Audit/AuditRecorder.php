<?php

namespace EmranAlhaddad\StatamicLogbook\Audit;

use Illuminate\Support\Facades\DB;
use EmranAlhaddad\StatamicLogbook\Support\DbConnectionResolver;
use EmranAlhaddad\StatamicLogbook\Support\LogSpool;

class AuditRecorder
{
    public function record(array $payload): void
    {
        try {
            $row = [
                'action'         => $payload['action'],
                'user_id'        => $payload['user_id'] ?? null,
                'user_email'     => $payload['user_email'] ?? null,
                'subject_type'   => $payload['subject_type'],
                'subject_id'     => $payload['subject_id'] ?? null,
                'subject_handle' => $payload['subject_handle'] ?? null,
                'subject_title'  => $payload['subject_title'] ?? null,
                'changes'        => isset($payload['changes']) ? json_encode($payload['changes'], JSON_UNESCAPED_UNICODE) : null,
                'meta'           => isset($payload['meta']) ? json_encode($payload['meta'], JSON_UNESCAPED_UNICODE) : null,
                'ip'             => $payload['ip'] ?? null,
                'user_agent'     => $payload['user_agent'] ?? null,
                'created_at'     => now(),
            ];

            if (LogSpool::enabled()) {
                LogSpool::enqueueAudit($row);
                return;
            }

            $conn = DbConnectionResolver::resolve();

            DB::connection($conn)->table('logbook_audit_logs')->insert([
                'action'         => $row['action'],
                'user_id'        => $row['user_id'],
                'user_email'     => $row['user_email'],
                'subject_type'   => $row['subject_type'],
                'subject_id'     => $row['subject_id'],
                'subject_handle' => $row['subject_handle'],
                'subject_title'  => $row['subject_title'],
                'changes'        => $row['changes'],
                'meta'           => $row['meta'],
                'ip'             => $row['ip'],
                'user_agent'     => $row['user_agent'],
                'created_at'     => $row['created_at'],
            ]);
        } catch (\Throwable $e) {
            // Never break the app because audit persistence failed.
        }
    }
}
