<?php

namespace App\Jobs;

use App\Models\KnowledgePublication;
use App\Services\Knowledge\QuizBankGenerator;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

/** Writes the AI question bank for one published guide in the background. */
class GenerateKnowledgeQuiz implements ShouldQueue, ShouldBeUnique
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 1;
    public int $timeout = 180;
    public int $uniqueFor = 600;

    public function __construct(public int $publicationId, public bool $force = false) {}

    public function uniqueId(): string
    {
        return 'knowledge-quiz:'.$this->publicationId;
    }

    public function handle(QuizBankGenerator $generator): void
    {
        $publication = KnowledgePublication::withoutGlobalScopes()->find($this->publicationId);
        if ($publication) {
            $generator->generate($publication, $this->force);
        }
    }
}
