<?php namespace Seiger\sApi\Logging;

use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Log;

final class AuditLogger
{
    public function event(string $event, array $context = [], string $level = 'notice'): void
    {
        $event = trim($event);
        if ($event === '') {
            return;
        }

        if (!(bool)(int)env('SAPI_LOGGING_ENABLED', 1)) {
            return;
        }

        if (!(bool)(int)env('SAPI_LOG_AUDIT_ENABLED', 1)) {
            return;
        }

        $excludeRaw = (string)env('SAPI_AUDIT_EXCLUDE_EVENTS', '');
        $exclude = $excludeRaw !== '' ? array_values(array_filter(array_map('trim', explode(',', $excludeRaw)))) : [];
        foreach ($exclude as $pattern) {
            $pattern = trim((string)$pattern);
            if ($pattern !== '' && $this->eventMatches($event, $pattern)) {
                return;
            }
        }

        $redactKeysRaw = (string)env('SAPI_REDACT_BODY_KEYS', 'password,token,refresh_token,jwt,secret');
        $redactKeys = array_values(array_filter(array_map('trim', explode(',', $redactKeysRaw))));
        $context = $this->redactKeys($context, $redactKeys);

        $maxBytes = (int)env('SAPI_AUDIT_MAX_CONTEXT_BYTES', 8192);
        if ($maxBytes < 1) {
            $maxBytes = 8192;
        }
        $context = $this->limitContextBytes($context, $maxBytes);

        $payload = [
            'ts' => Carbon::now()->toIso8601String(),
            'type' => 'audit',
            'event' => $event,
            'request_id' => RequestContext::get('request_id'),
            'sub' => RequestContext::get('sub'),
            'route' => RequestContext::get('route'),
            'user_id' => RequestContext::get('user_id'),
            'context' => $context,
        ];

        $payload = array_filter($payload, static fn($v) => $v !== null && $v !== '');

        try {
            $logger = Log::channel('sapi');
            $level = strtolower(trim($level));
            if (!method_exists($logger, $level)) {
                $level = 'notice';
            }

            $logger->{$level}($event, $payload);
        } catch (\Throwable) {
            // Never fail the request because of audit logging issues.
        }
    }

    private function eventMatches(string $event, string $pattern): bool
    {
        if ($event === $pattern) {
            return true;
        }

        if (function_exists('fnmatch')) {
            return fnmatch($pattern, $event);
        }

        if (str_ends_with($pattern, '.*')) {
            $prefix = substr($pattern, 0, -2);
            return $prefix !== '' && ($event === $prefix || str_starts_with($event, $prefix . '.'));
        }

        return false;
    }

    private function redactKeys(array $data, array $redactKeys): array
    {
        $normalized = [];
        foreach ($redactKeys as $key) {
            $key = strtolower(trim((string)$key));
            if ($key !== '') {
                $normalized[$key] = true;
            }
        }

        return $this->redactKeysRecursive($data, $normalized);
    }

    private function redactKeysRecursive(array $data, array $redactMap): array
    {
        foreach ($data as $key => $value) {
            $keyString = is_string($key) ? strtolower($key) : null;

            if ($keyString !== null && isset($redactMap[$keyString])) {
                $data[$key] = '***';
                continue;
            }

            if (is_array($value)) {
                $data[$key] = $this->redactKeysRecursive($value, $redactMap);
            }
        }

        return $data;
    }

    private function limitContextBytes(array $context, int $maxBytes): array
    {
        $encoded = json_encode($context, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        if (is_string($encoded) && strlen($encoded) <= $maxBytes) {
            return $context;
        }

        $context = $this->truncateRecursive($context, 512, 50, 6);
        $context['_truncated'] = true;

        $encoded = json_encode($context, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        if (is_string($encoded) && strlen($encoded) <= $maxBytes) {
            return $context;
        }

        return ['_truncated' => true];
    }

    private function truncateRecursive(array $data, int $maxStringLen, int $maxItems, int $depth): array
    {
        if ($depth < 1) {
            return ['_truncated' => true];
        }

        $out = [];
        $count = 0;
        foreach ($data as $key => $value) {
            if ($count >= $maxItems) {
                $out['_truncated_items'] = true;
                break;
            }
            $count++;

            if (is_string($value)) {
                $out[$key] = strlen($value) > $maxStringLen ? (substr($value, 0, $maxStringLen) . '…') : $value;
                continue;
            }

            if (is_array($value)) {
                $out[$key] = $this->truncateRecursive($value, $maxStringLen, $maxItems, $depth - 1);
                continue;
            }

            $out[$key] = $value;
        }

        return $out;
    }
}
