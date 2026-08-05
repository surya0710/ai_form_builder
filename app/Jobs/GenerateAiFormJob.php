<?php

namespace App\Jobs;

use App\Exceptions\AI\AIException;
use App\Models\AiGenerationLog;
use App\Models\User;
use App\Services\AI\AIService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;
use Throwable;

class GenerateAiFormJob implements ShouldQueue
{
    use Queueable;

    public int $tries = 1;

    public function __construct(
        public int $logId,
        public int $userId,
        public string $prompt,
        public bool $persist = true,
    ) {}

    public function handle(AIService $ai): void
    {
        $log = AiGenerationLog::query()->findOrFail($this->logId);
        $user = User::query()->findOrFail($this->userId);

        $log->update(['status' => 'generating']);

        try {
            $preview = $ai->generatePreview($user, $this->prompt, $log);

            if ($this->persist) {
                $form = $ai->persistGeneratedForm($user, $preview);
                $log->update([
                    'form_id' => $form->id,
                    'response' => array_merge($log->response ?? [], ['form_uuid' => $form->uuid]),
                ]);
            }
        } catch (Throwable $e) {
            $log->update([
                'status' => 'failed',
                'error_message' => $e instanceof AIException
                    ? $e->publicMessage()
                    : 'Unable to generate form. Please try again.',
            ]);

            Log::error('GenerateAiFormJob failed', [
                'log_id' => $this->logId,
                'message' => $e->getMessage(),
            ]);
        }
    }
}
