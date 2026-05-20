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

        $rawBlocks = $isPreGrouped
            ? self::splitPreGrouped($content)
            : self::splitRawLines($content);

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
            if (isset($counts[$entryLevel])) {
                $counts[$entryLevel]++;
            }

            if ($levelFilter !== 'ALL' && $entryLevel !== $levelFilter) {
                continue;
            }

            if ($search !== '') {
                $haystack = strtolower(
                    ($entry['message'] ?? '') . ' ' .
                    ($entry['component'] ?? '') . ' ' .
                    ($entry['requestUrl'] ?? '') . ' ' .
                    json_encode($entry['context'] ?? '') . ' ' .
                    ($entry['stackTrace'] ?? '')
                );

                if (!str_contains($haystack, $search)) {
                    continue;
                }
            }

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

    private static function parseSingleEntry(string $entry): ?array
    {
        $memory = null;
        $context = null;
        $stackTrace = null;
        $exception = null;
        $requestUrl = null;
        $codeLocation = null;

        if (preg_match('/"memory":(\d+)/', $entry, $memMatch)) {
            $memory = (int) $memMatch[1];
            $entry = preg_replace('/\s*\{"memory":\d+\}/', '', $entry);
        }

        if (
            !preg_match(
                '/^(?<timestamp>\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2})\s+\[(?<level>[^\]]+)\](?:\s+\[(?<component>[^\]]+)\])?\s*(?<message>.*)/s',
                $entry,
                $matches
            )
        ) {
            return null;
        }

        $timestamp = $matches['timestamp'];
        $level = strtoupper(preg_replace('/^.*\./', '', $matches['level']));
        $component = $matches['component'] ?? null;
        $message = trim($matches['message']);

        if (preg_match('#\b(https?://[^\s"\']+|/(admin|api)/[^\s"\']+)#', $message, $m)) {
            $url = $m[1];

            $url = preg_replace('#^https?://[^/]+#', '', $url);

            $url = explode('?', $url)[0];

            $url = preg_split('/["\']|HTTP_|,/', $url)[0];

            $url = rtrim($url, " \t\n\r\0\x0B,.;");

            if (strlen($url) > 150) {
                $url = substr($url, 0, 150) . '…';
            }

            $requestUrl = $url;
        }

        if (preg_match('#(/[^\s()]+\.php:\d+)#', $message, $m)) {
            $codeLocation = $m[1];
        }

        if (stripos($message, 'request context') !== false) {

            if (preg_match('/request context:\s*(\{.*\})/is', $message, $m)) {
                $decoded = json_decode($m[1], true);

                $context = json_last_error() === JSON_ERROR_NONE
                    ? $decoded
                    : $m[1];

                $message = trim(str_replace($m[0], 'request context', $message));
            }

            elseif (preg_match('/request context:\s*array\s*\((.*?)\)/is', $message, $m)) {
                $context = trim($m[1]);
                $message = trim(str_replace($m[0], 'request context', $message));
            }

            else {
                if (preg_match('/request context:(.*)$/is', $message, $m)) {
                    $context = trim($m[1]);
                    $message = trim(str_replace($m[0], 'request context', $message));
                }
            }
        }

        if (preg_match('/(\{.*\})\s*$/s', $message, $jsonMatch)) {
            $json = json_decode($jsonMatch[1], true);

            if (json_last_error() === JSON_ERROR_NONE && is_array($json)) {
                $context = $json;
                $message = trim(str_replace($jsonMatch[1], '', $message));
            }
        }

        if (str_contains($message, 'Stack trace:') || preg_match('/#\d+\s+/', $message)) {
            $parts = preg_split('/Stack trace:/', $message, 2);

            if (count($parts) === 2) {
                $message = trim($parts[0]);
                $stackTrace = trim($parts[1]);
            } else {
                if (preg_match_all('/#\d+.*(?:\n|$)/', $message, $traceMatch)) {
                    $stackTrace = implode("\n", $traceMatch[0]);
                    $message = trim(str_replace($stackTrace, '', $message));
                }
            }

            if (preg_match('/#0\s+([^\s]+:\d+)/', $stackTrace ?? '', $m)) {
                $codeLocation = $m[1];
            }
        }

        if (is_array($context)) {
            if (isset($context['exception'])) {
                $exception = $context['exception'];
                unset($context['exception']);
            }

            if (isset($context['trace']) && is_array($context['trace'])) {
                $stackTrace = implode("\n", $context['trace']);
                unset($context['trace']);
            }
        }

        return [
            'timestamp' => $timestamp,
            'level' => $level,
            'component' => $component,
            'message' => $message ?: '(no message)',
            'stackTrace' => $stackTrace,
            'context' => $context,
            'exception' => $exception,
            'codeLocation' => $codeLocation,
            'requestUrl' => $requestUrl,
            'memory' => $memory,
        ];
    }

    private static function splitPreGrouped(string $content): array
    {
        $blocks = explode("---LOGDASH_SEP---", $content);
        return array_reverse(array_values(array_filter(array_map('trim', $blocks))));
    }

    private static function splitRawLines(string $content): array
    {
        $lines = explode("\n", $content);
        $lines = array_filter(array_map('rtrim', $lines));
        $lines = array_reverse(array_values($lines));

        $rawBlocks = [];
        $currentBlock = null;

        foreach ($lines as $line) {
            if (preg_match('/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}/', $line)) {
                if ($currentBlock !== null) {
                    array_unshift($currentBlock['lines'], $line);
                    $rawBlocks[] = implode("\n", $currentBlock['lines']);
                } else {
                    $rawBlocks[] = $line;
                }
                $currentBlock = null;
            } else {
                if ($currentBlock === null) {
                    $currentBlock = ['lines' => []];
                }
                array_unshift($currentBlock['lines'], $line);
            }
        }

        return $rawBlocks;
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
