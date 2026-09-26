<?php

namespace App\Jobs;

use App\Http\Controllers\AssetImportController;
use App\Models\User;
use App\Services\AuditService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class ProcessAssetImport implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $timeout = 900;

    public int $tries = 1;

    public function __construct(public int $importId, public int $actorId) {}

    public function handle(AssetImportController $imports, AuditService $audit): void
    {
        $imports->process($this->importId, User::query()->findOrFail($this->actorId), $audit);
    }

    public function failed(\Throwable $exception): void
    {
        app(AssetImportController::class)->markAsFailed($this->importId, $exception);
    }
}
