<?php

namespace App\Events;

use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class AssignmentCreated
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public $task;

    public $course;

    public $students;

    public function __construct($task, $course, $students)
    {
        $this->task = $task;
        $this->course = $course;
        $this->students = $students;
    }
}
