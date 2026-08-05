<?php

namespace App\Jobs;

use App\Exceptions\AI\AIException;
use App\Models\AiGenerationLog;
use App\Models\Form;
use App\Models\User;
use App\Services\AI\AIService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;
use Throwable;

class EditAiFormJob implements ShouldQueue
{
    use Queueable;

    public int $tries = 1;

    public function __construct(
        public int $logId,
        public int $userId,
        public int $formId,
        public string $prompt,
    ) {}

    public function handle(AIService $ai): void
    {
        $log = AiGenerationLog::query()->findOrFail($this->logId);
        $user = User::query()->findOrFail($this->userId);
        $form = Form::query()->findOrFail($this->formId);

        $log->update(['status' => 'generating']);

        try {
            $ai->previewEdit($user, $form, $this->prompt, $log);
        } catch (Throwable $e) {
            $log->update([
                'status' => 'failed',
                'error_message' => $e instanceof AIException
                    ? $e->publicMessage()
                    : 'Unable to generate form. Please try again.',
            ]);

            Log::error('EditAiFormJob failed', [
                'log_id' => $this->logId,
                'form_id' => $this->formId,
                'message' => $e->getMessage(),
            ]);
        }
    }
}
