<?php

namespace EmranAlhaddad\StatamicLogbook\Audit;

class ChangeDetector
{
    public function diff(array $before, array $after): array
    {
        $ignore = (array) config('logbook.audit_logs.ignore_fields', []);
        $maxLen = (int) config('logbook.audit_logs.max_value_length', 2000);

        foreach ($ignore as $k) {
            unset($before[$k], $after[$k]);
        }

        $keys = array_unique(array_merge(array_keys($before), array_keys($after)));
        $changes = [];

        foreach ($keys as $key) {
            $b = $before[$key] ?? null;
            $a = $after[$key] ?? null;

            if ($this->equal($b, $a)) continue;

            $changes[$key] = [
                'from' => $this->limit($b, $maxLen),
                'to'   => $this->limit($a, $maxLen),
            ];
        }

        return $changes;
    }

    private function equal(mixed $a, mixed $b): bool
    {
        if (is_array($a) && is_array($b)) {
            return json_encode($a, JSON_UNESCAPED_UNICODE) === json_encode($b, JSON_UNESCAPED_UNICODE);
        }
        return $a === $b;
    }

    private function limit(mixed $v, int $maxLen): mixed
    {
        if (is_string($v) && mb_strlen($v) > $maxLen) {
            return mb_substr($v, 0, $maxLen) . '…';
        }
        return $v;
    }
}
