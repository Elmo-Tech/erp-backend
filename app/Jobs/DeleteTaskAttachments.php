<?php

namespace App\Jobs;

use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Storage;

class DeleteTaskAttachments implements ShouldQueue
{
    use Queueable;
    public int $tries = 5;
    public function __construct(public array $paths) {}
    public function handle(): void
    {
        foreach ($this->paths as $path) {
            if (Storage::disk('local')->exists($path) && ! Storage::disk('local')->delete($path)) throw new \RuntimeException('Task attachment cleanup failed.');
        }
    }
}
