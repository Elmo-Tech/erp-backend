<?php

namespace App\Jobs;

use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Storage;

class DeleteProjectUploads implements ShouldQueue
{
    use Queueable;

    public int $tries = 5;

    public function __construct(public array $paths) {}

    public function handle(): void
    {
        foreach ($this->paths as $path) {
            if (Storage::disk('public')->exists($path) && ! Storage::disk('public')->delete($path)) {
                throw new \RuntimeException('Project upload cleanup failed.');
            }
        }
    }
}
