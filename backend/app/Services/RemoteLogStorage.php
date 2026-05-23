<?php

namespace App\Services;

use phpseclib3\Net\SFTP;
use phpseclib3\Crypt\PublicKeyLoader;

class RemoteLogStorage
{
    public function __construct(
        protected string $host,
        protected string $user,
        protected ?string $password,
        protected string $remotePath,
        protected ?string $sshKey = null,
        protected ?string $sshKeyPassphrase = null,
    ) {
    }

    protected function connect(): SFTP
    {
        $sftp = new SFTP($this->host);
        $sftp->setTimeout(5);

        if ($this->sshKey) {
            $key = PublicKeyLoader::load($this->sshKey, $this->sshKeyPassphrase);

            if (!$sftp->login($this->user, $key)) {
                throw new \RuntimeException('SSH key auth failed');
            }

            return $sftp;
        }

        if ($this->password) {
            if (!$sftp->login($this->user, $this->password)) {
                throw new \RuntimeException('SSH password auth failed');
            }

            return $sftp;
        }

        throw new \RuntimeException('No authentication method provided');
    }

    public function validate(): bool
    {
        return $this->connect()->is_dir($this->remotePath);
    }

    public function getConnectionKey(): string
    {
        return md5($this->host . $this->user . $this->remotePath);
    }

    public function listLogFiles(): array
    {
        $sftp = $this->connect();

        if (!$sftp->is_dir($this->remotePath)) {
            throw new \RuntimeException('Remote log path is not a directory');
        }

        $items = $sftp->nlist($this->remotePath);
        if ($items === false) {
            throw new \RuntimeException('Unable to list remote log directory');
        }

        $files = [];
        foreach ($items as $item) {
            if ($item === '.' || $item === '..')
                continue;

            $remoteFile = $this->resolveRemoteFile($item);
            if (!$sftp->is_file($remoteFile))
                continue;
            if (pathinfo($item, PATHINFO_EXTENSION) !== 'log')
                continue;

            $size = $sftp->filesize($remoteFile);
            $mtime = $sftp->filemtime($remoteFile);

            $files[] = [
                'name' => $item,
                'size' => $size === false ? 'N/A' : round($size / 1024, 1) . ' KB',
                'modified' => $mtime === false ? 'N/A' : date('d-m-Y H:i', $mtime),
            ];
        }

        return $files;
    }

    public function downloadTailToTemp(string $fileName, int $maxBytes): array
    {
        $remoteFile = $this->resolveRemoteFile($fileName);
        $sftp = $this->connect();

        $fileSize = $sftp->filesize($remoteFile);
        if ($fileSize === false) {
            throw new \RuntimeException('Cannot stat remote file: ' . $fileName);
        }

        $offset = max(0, $fileSize - $maxBytes);
        $isComplete = $offset === 0;
        $tempPath = tempnam(sys_get_temp_dir(), 'logdash_');

        if (!$sftp->get($remoteFile, $tempPath, $offset)) {
            throw new \RuntimeException('Failed to download remote log file');
        }

        return ['path' => $tempPath, 'isComplete' => $isComplete];
    }

    public function downloadToTemp(string $fileName): string
    {
        $remoteFile = $this->resolveRemoteFile($fileName);
        $sftp = $this->connect();

        if (!$sftp->is_file($remoteFile)) {
            throw new \RuntimeException('Remote file not found: ' . $fileName);
        }

        $tempPath = tempnam(sys_get_temp_dir(), 'logdash_');
        if (!$sftp->get($remoteFile, $tempPath)) {
            throw new \RuntimeException('Failed to download remote log file');
        }

        return $tempPath;
    }

    private function resolveRemoteFile(string $fileName): string
    {
        return rtrim($this->remotePath, '/') . '/' . basename($fileName);
    }
}
