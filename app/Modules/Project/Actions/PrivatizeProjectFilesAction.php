<?php

namespace App\Modules\Project\Actions;

use App\Modules\Project\Models\ProjectFile;
use Illuminate\Support\Facades\Storage;

class PrivatizeProjectFilesAction
{
    /**
     * @return array{alreadyPrivate: int, pending: int, moved: int, missing: int, failed: int}
     */
    public function execute(bool $moveFiles): array
    {
        $result = [
            'alreadyPrivate' => 0,
            'pending' => 0,
            'moved' => 0,
            'missing' => 0,
            'failed' => 0,
        ];

        ProjectFile::query()
            ->where('type', 'file')
            ->whereNotNull('file_path')
            ->orderBy('id')
            ->each(function (ProjectFile $file) use ($moveFiles, &$result): void {
                $this->processFile($file, $moveFiles, $result);
            });

        return $result;
    }

    /**
     * @param  array{alreadyPrivate: int, pending: int, moved: int, missing: int, failed: int}  $result
     */
    private function processFile(ProjectFile $file, bool $moveFiles, array &$result): void
    {
        $path = $file->file_path;

        if (! $path) {
            $result['missing']++;

            return;
        }

        $privateDisk = Storage::disk('local');
        $publicDisk = Storage::disk('public');
        $privateExists = $privateDisk->exists($path);
        $publicExists = $publicDisk->exists($path);

        if ($privateExists && ! $publicExists) {
            $result['alreadyPrivate']++;

            return;
        }

        if (! $publicExists) {
            $result['missing']++;

            return;
        }

        if (! $moveFiles) {
            $result['pending']++;

            return;
        }

        if ($privateExists) {
            if ($privateDisk->size($path) !== $publicDisk->size($path) || ! $publicDisk->delete($path)) {
                $result['failed']++;

                return;
            }

            $result['moved']++;

            return;
        }

        $stream = $publicDisk->readStream($path);

        if (! is_resource($stream)) {
            $result['failed']++;

            return;
        }

        try {
            $written = $privateDisk->writeStream($path, $stream);
        } finally {
            fclose($stream);
        }

        if (! $written
            || ! $privateDisk->exists($path)
            || $privateDisk->size($path) !== $publicDisk->size($path)
            || ! $publicDisk->delete($path)) {
            $privateDisk->delete($path);
            $result['failed']++;

            return;
        }

        $result['moved']++;
    }
}
