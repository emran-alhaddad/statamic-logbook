<?php

namespace EmranAlhaddad\StatamicLogbook\Audit;

use Illuminate\Support\Facades\DB;
use EmranAlhaddad\StatamicLogbook\Support\DbConnectionResolver;

class AuditRecorder
{
    public function record(array $payload): void
    {
        $conn = DbConnectionResolver::resolve();

        DB::connection($conn)->table('logbook_audit_logs')->insert([
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
        ]);
    }
}
