<?php

namespace App\Events;

use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class SubmissionConfirmation
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public $submission;

    public $student;

    public function __construct($submission, $student)
    {
        $this->submission = $submission;
        $this->student = $student;
    }
}
