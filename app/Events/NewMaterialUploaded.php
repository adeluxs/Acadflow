<?php

namespace App\Events;

use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class NewMaterialUploaded
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public $material;

    public $course;

    public $students;

    public function __construct($material, $course, $students)
    {
        $this->material = $material;
        $this->course = $course;
        $this->students = $students;
    }
}
