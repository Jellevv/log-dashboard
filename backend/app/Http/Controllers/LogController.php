<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Services\LogParser;
use App\Services\RemoteLogStorage;

class LogController extends Controller
{
    private const TAIL_BYTES_ALL = 300_000;
    private const TAIL_BYTES_FILTERED = 1_000_000;
    private const TAIL_BYTES_MAX = 5_000_000;
    private const SEARCH_CACHE_TTL = 1800;
    private const SEARCH_MAX_MATCHES = 50_000;

    public function connect(Request $request)
    {
        //TIJDELIJK (live debug) 
        if ($request->input('mode') === 'local') {
            $path = trim($request->input('logsPath'));

            if (!is_dir($path)) {
                return response()->json(['error' => 'Path does not exist or is not a directory'], 422);
            }

            return response()->json([
                'id' => 'dynamic',
                'label' => $request->input('projectName'),
                'local' => ['path' => $path],
            ]);
        }

        $request->validate([
            'sshHost' => 'required|string',
            'logsPath' => 'required|string',
            'projectName' => 'required|string',
            'authMode' => 'required|in:password,key',
            'password' => 'nullable|string',
        ]);

        $sshHost = trim($request->input('sshHost'));

        if (!preg_match('/^(?<user>[^@]+)@(?<host>.+)$/', $sshHost, $matches)) {
            return response()->json(['error' => 'SSH host must be in the form user@host'], 422);
        }

        $authMode = $request->input('authMode');

        $password = null;
        $sshKey = null;
        $sshKeyPassphrase = is_string(config('ssh.passphrase')) ? config('ssh.passphrase') : null;

        if ($authMode === 'password') {
            $password = $request->input('password');

            if (!$password) {
                return response()->json(['error' => 'Password required'], 422);
            }
        }

        if ($authMode === 'key') {
            $keyPath = config('ssh.key_path');

            if (!file_exists($keyPath)) {
                return response()->json(['error' => 'SSH key not found'], 422);
            }

            $sshKey = file_get_contents($keyPath);
        }

        $sshConfig = [
            'host' => trim($matches['host']),
            'user' => trim($matches['user']),
            'path' => trim($request->input('logsPath')),
            'authMode' => $authMode,
            'password' => $authMode === 'password' ? $password : null,
        ];

        try {
            $storage = new RemoteLogStorage(
                $sshConfig['host'],
                $sshConfig['user'],
                $sshConfig['password'],
                $sshConfig['path'],
                $sshKey,
                $sshKeyPassphrase
            );

            if (!$storage->validate()) {
                return response()->json(['error' => 'SSH connectie gefaald'], 422);
            }

        } catch (\Throwable $e) {
            return response()->json(['error' => $e->getMessage()], 422);
        }

        return response()->json([
            'id' => 'dynamic',
            'label' => $request->input('projectName'),
            'ssh' => $sshConfig,
        ]);
    }


    public function getLogs(Request $request)
    {
        $projectId = $request->query('project', $request->input('project'));
        $project = $this->resolveProject($request, $projectId);

        if (!$project) {
            return response()->json(['error' => 'Unknown project'], 404);
        }

        try {
            if ($project['type'] === 'local') {
                return response()->json($this->listLocalLogs($project['path']));
            }

            [$password, $sshKey] = $this->resolveAuth($project);

            $storage = new RemoteLogStorage(
                $project['host'],
                $project['user'],
                $password,
                $project['path'],
                $sshKey,
                config('ssh.passphrase')
            );

            return response()->json($storage->listLogFiles());
        } catch (\Throwable $e) {
            return response()->json(['error' => $e->getMessage()], 422);
        }
    }

    public function getLogContent(Request $request)
    {
        $projectId = $request->query('project', $request->input('project'));
        $fileName = $request->query('file', $request->input('file'));

        if (!$fileName) {
            return response()->json(['error' => 'Missing file parameter'], 400);
        }

        $project = $this->resolveProject($request, $projectId);

        if (!$project) {
            return response()->json(['error' => 'Unknown project'], 404);
        }

        $page = max(1, (int) ($request->query('page', $request->input('page', 1))));
        $limit = max(1, min(500, (int) ($request->query('limit', $request->input('limit', 100)))));
        $level = strtoupper($request->query('level', $request->input('level', 'ALL')));
        $search = trim((string) $request->query('search', $request->input('search', '')));

        try {
            if ($project['type'] === 'local') {
                $result = $this->handleLocal($project, $fileName, $page, $limit, $level, $search);
            } else {
                [$password, $sshKey] = $this->resolveAuth($project);

                $storage = new RemoteLogStorage(
                    $project['host'],
                    $project['user'],
                    $password,
                    $project['path'],
                    $sshKey,
                    config('ssh.passphrase')
                );

                $result = $search !== ''
                    ? $this->fetchSearchPage($storage, $fileName, $page, $limit, $level, $search)
                    : $this->fetchBrowsePage($storage, $fileName, $page, $limit, $level);
            }

            return response()->json($result);
        } catch (\Throwable $e) {
            return response()->json(['error' => $e->getMessage()], 422);
        }
    }

    private function resolveAuth(array $project): array
    {
        if ($project['authMode'] === 'key') {
            $keyPath = config('ssh.key_path');
            $key = file_get_contents($keyPath);

            if ($key === false) {
                throw new \RuntimeException('Could not read SSH key file');
            }

            return [null, $key];
        }

        return [$project['password'] ?? null, null];
    }

    private function resolveProject(Request $request, ?string $projectId): ?array
    {
        if (!$projectId || $projectId !== 'dynamic') {
            return null;
        }

        $local = $request->input('local');
        if (is_array($local) && !empty($local['path'])) {
            return ['type' => 'local', 'path' => $local['path']];
        }

        $ssh = $request->input('ssh');
        if (
            !is_array($ssh)
            || empty($ssh['host'])
            || empty($ssh['user'])
            || empty($ssh['path'])
            || empty($ssh['authMode'])
            // for password auth, password must also be present
            || ($ssh['authMode'] === 'password' && empty($ssh['password']))
        ) {
            return null;
        }

        return [
            'type' => 'ssh',
            'host' => (string) $ssh['host'],
            'user' => (string) $ssh['user'],
            'path' => (string) $ssh['path'],
            'password' => $ssh['password'] ?? null,
            'authMode' => (string) $ssh['authMode'],
        ];
    }

    private function fetchBrowsePage(
        RemoteLogStorage $storage,
        string $fileName,
        int $page,
        int $limit,
        string $level
    ): array {
        $tailBytes = $level === 'ALL'
            ? max(self::TAIL_BYTES_ALL, $page * $limit * 1_500)
            : max(self::TAIL_BYTES_FILTERED, $page * $limit * 4_000);

        $result = null;

        do {
            ['path' => $tempPath, 'isComplete' => $isComplete] =
                $storage->downloadTailToTemp($fileName, $tailBytes);

            try {
                $result = LogParser::parseLogs($tempPath, $limit, $page, $level, '');
            } finally {
                @unlink($tempPath);
            }

            $hasFullPage = count($result['entries']) >= $limit;
            $canRetry = !$isComplete && $tailBytes < self::TAIL_BYTES_MAX;

            if ($hasFullPage || !$canRetry)
                break;

            $tailBytes = min($tailBytes * 3, self::TAIL_BYTES_MAX);
        } while (true);

        $result['total'] = -1;
        $result['totalFiltered'] = -1;

        return $result;
    }

    private function fetchSearchPage(
        RemoteLogStorage $storage,
        string $fileName,
        int $page,
        int $limit,
        string $level,
        string $search
    ): array {
        $cacheKey = 'logdash:'
            . $storage->getConnectionKey()
            . ':' . md5($fileName . $level . $search);

        /** @var array $allMatches */
        $allMatches = cache()->remember(
            $cacheKey,
            self::SEARCH_CACHE_TTL,
            function () use ($storage, $fileName, $level, $search): array {
                $tempPath = $storage->downloadToTemp($fileName);
                try {
                    $result = LogParser::parseLogs(
                        $tempPath,
                        self::SEARCH_MAX_MATCHES,
                        1,
                        $level,
                        $search
                    );
                    return $result['entries'];
                } finally {
                    @unlink($tempPath);
                }
            }
        );

        $total = count($allMatches);
        $offset = ($page - 1) * $limit;

        $counts = ['ERROR' => 0, 'WARNING' => 0, 'INFO' => 0];
        foreach ($allMatches as $entry) {
            $lvl = $entry['level'] ?? 'INFO';
            if (isset($counts[$lvl]))
                $counts[$lvl]++;
        }

        return [
            'entries' => array_slice($allMatches, $offset, $limit),
            'total' => $total,
            'totalFiltered' => $total,
            'counts' => $counts,
            'page' => $page,
            'limit' => $limit,
        ];
    }

    //TIJDELIJK (live debug)
    private function listLocalLogs(string $path): array
    {
        $files = [];
        foreach (glob(rtrim($path, '/') . '/*.log') as $filePath) {
            $files[] = [
                'name' => basename($filePath),
                'size' => round(filesize($filePath) / 1024, 1) . ' KB',
                'modified' => date('d-m-Y H:i', filemtime($filePath)),
            ];
        }
        return $files;
    }

    //TIJDELIJK (live debug)
    private function handleLocal(array $project, string $fileName, int $page, int $limit, string $level, string $search): array
    {
        $filePath = rtrim($project['path'], '/') . '/' . basename($fileName);

        if (!file_exists($filePath)) {
            throw new \RuntimeException('Local file not found: ' . $fileName);
        }

        if ($search !== '') {
            $cacheKey = 'logdash:local:' . md5($filePath . $level . $search);
            $allMatches = cache()->remember($cacheKey, self::SEARCH_CACHE_TTL, function () use ($filePath, $level, $search) {
                $result = LogParser::parseLogs($filePath, self::SEARCH_MAX_MATCHES, 1, $level, $search);
                return $result['entries'];
            });

            $total = count($allMatches);
            $offset = ($page - 1) * $limit;
            $counts = ['ERROR' => 0, 'WARNING' => 0, 'INFO' => 0];
            foreach ($allMatches as $entry) {
                $lvl = $entry['level'] ?? 'INFO';
                if (isset($counts[$lvl]))
                    $counts[$lvl]++;
            }

            return [
                'entries' => array_slice($allMatches, $offset, $limit),
                'total' => $total,
                'totalFiltered' => $total,
                'counts' => $counts,
                'page' => $page,
                'limit' => $limit,
            ];
        }

        $result = LogParser::parseLogs($filePath, $limit, $page, $level, '');
        $result['total'] = -1;
        $result['totalFiltered'] = -1;
        return $result;
    }
}
