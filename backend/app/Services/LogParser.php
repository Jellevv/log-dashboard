<?php

namespace App\Services;

class LogParser
{
    public static function parseCraftLogs(
        string $filePath,
        int $limit = 100,
        int $page = 1,
        string $level = 'ALL',
        string $search = ''
    ): array {
        $content = file_get_contents($filePath);
        if ($content === false) {
            return self::emptyResult($page, $limit);
        }

        $isPreGrouped = str_contains($content, '---LOGDASH_SEP---');
        $rawBlocks = self::splitRawLines($content);

        $search = strtolower(trim($search));
        $levelFilter = strtoupper(trim($level));
        $offset = ($page - 1) * $limit;
        $collected = [];
        $totalAll = 0;
        $totalMatch = 0;
        $counts = ['ERROR' => 0, 'WARNING' => 0, 'INFO' => 0];

        foreach ($rawBlocks as $block) {
            $entry = self::parseSingleEntry($block);
            if (!$entry)
                continue;

            $totalAll++;
            $entryLevel = $entry['level'] ?? 'INFO';
            if (isset($counts[$entryLevel]))
                $counts[$entryLevel]++;

            if ($levelFilter !== 'ALL' && $entryLevel !== $levelFilter)
                continue;

            $searchText = strtolower(
                $entry['message'] . ' ' .
                ($entry['exception'] ?? '') . ' ' .
                ($entry['stackTrace'] ?? '') . ' ' .
                json_encode($entry['context'] ?? [])
            );

            if ($search !== '' && !str_contains($searchText, $search))
                continue;

            $totalMatch++;
            if ($totalMatch > $offset && count($collected) < $limit) {
                $collected[] = $entry;
            }
        }

        return [
            'entries' => $collected,
            'total' => $totalAll,
            'totalFiltered' => $totalMatch,
            'counts' => $counts,
            'page' => $page,
            'limit' => $limit,
        ];
    }

    private static function parseSingleEntry(string $raw): ?array
    {
        $raw = trim($raw);

        if (
            !preg_match(
                '/^(?<timestamp>\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2})\s+\[(?<level>[^\]]+)\](?:\s+\[(?<component>[^\]]+)\])?\s*(?<body>.*)/s',
                $raw,
                $m
            )
        )
            return null;

        $timestamp = $m['timestamp'];
        $level = strtoupper(preg_replace('/^.*\./', '', $m['level']));
        $component = ($m['component'] ?? '') ?: null;
        $body = trim($m['body']);

        $memory = null;
        $stackTrace = null;
        $codeLocation = null;
        $requestUrl = null;
        $context = null;
        $exception = null;

        if (preg_match('/"memory":(\d+)/', $body, $mm)) {
            $memory = (int) $mm[1];
            $body = trim(preg_replace('/,?\s*"memory":\d+/', '', $body));
            $body = trim(preg_replace('/\{\s*\}/', '', $body));
        }

        if (str_contains($body, 'Stack trace:')) {
            [$before, $after] = explode('Stack trace:', $body, 2);
            $body = rtrim($before);
            $stackTrace = trim($after);

            if (preg_match('/#0\s+(\/\S+\(\d+\))/', $stackTrace, $cl)) {
                $codeLocation = $cl[1];
            }
        }

        if (preg_match('/^(.*?)\s*(\{.+\})\s*$/s', $body, $jm)) {
            $decoded = json_decode($jm[2], true);
            if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
                $beforeJson = trim($jm[1]);
                if ($beforeJson !== '') {
                    $body = $beforeJson;
                } elseif (isset($decoded['message'])) {
                    $body = (string) $decoded['message'];
                    unset($decoded['message']);
                }

                if (isset($decoded['trace'])) {
                    $traceData = $decoded['trace'];
                    if (is_array($traceData)) {
                        $stackTrace = $stackTrace ?? implode("\n", $traceData);
                    }
                    unset($decoded['trace']);
                }

                if (isset($decoded['exception'])) {
                    $exception = (string) $decoded['exception'];
                    unset($decoded['exception']);
                }

                if (isset($decoded['memory'])) {
                    $memory = $memory ?? (int) $decoded['memory'];
                    unset($decoded['memory']);
                }

                if (isset($decoded['vars']['_SERVER']['REQUEST_URI'])) {
                    $requestUrl = $decoded['vars']['_SERVER']['REQUEST_URI'];
                    $requestUrl = explode('?', $requestUrl)[0];
                }

                if (!empty($decoded)) {
                    $context = $decoded;
                }
            }
        }

        if (!$requestUrl && preg_match('/\b(GET|POST|PUT|DELETE|PATCH)\s+(\/[a-zA-Z0-9_\-\/]+)/', $body, $ru)) {
            $requestUrl = $ru[1] . ' ' . $ru[2];
        }

        if (!$codeLocation && $exception && preg_match('#at\s+(/.+\.php:\d+)#', $exception, $cl)) {
            $codeLocation = $cl[1];
        }

        $message = trim($body) ?: '(no message)';

        return [
            'timestamp' => $timestamp,
            'level' => $level,
            'component' => $component,
            'message' => $message,
            'stackTrace' => $stackTrace,
            'codeLocation' => $codeLocation,
            'requestUrl' => $requestUrl,
            'memory' => $memory,
            'context' => $context,
            'exception' => $exception,
        ];
    }

    private static function splitRawLines(string $content): array
    {
        $lines = explode("\n", $content);
        $lines = array_map(fn($l) => rtrim($l, "\r"), $lines);

        $rawBlocks = [];
        $current = [];

        foreach ($lines as $line) {
            if (preg_match('/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}/', $line)) {
                if (!empty($current)) {
                    $rawBlocks[] = implode("\n", $current);
                }
                $current = [$line];
            } else {
                if (!empty($current)) {
                    $current[] = $line;
                }
            }
        }

        if (!empty($current)) {
            $rawBlocks[] = implode("\n", $current);
        }

        return array_reverse($rawBlocks);
    }

    private static function emptyResult(int $page, int $limit): array
    {
        return [
            'entries' => [],
            'total' => 0,
            'totalFiltered' => 0,
            'counts' => ['ERROR' => 0, 'WARNING' => 0, 'INFO' => 0],
            'page' => $page,
            'limit' => $limit,
        ];
    }
}
