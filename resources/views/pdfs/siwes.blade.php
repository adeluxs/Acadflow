<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>SIWES Report - {{ $submission->title }}</title>
    <style>
        body { font-family: Times New Roman, serif; margin: 2cm; line-height: 1.5; }
        .header { text-align: center; margin-bottom: 2cm; }
        .header img { height: 80px; }
        .header h1 { font-size: 18pt; margin: 10px 0; }
        .header p { font-size: 12pt; margin: 5px 0; }
        .title { text-align: center; font-size: 16pt; font-weight: bold; margin: 2cm 0; text-transform: uppercase; }
        .meta { margin: 1cm 0; }
        .meta p { margin: 5px 0; }
        .section { margin-top: 1cm; }
        .section h2 { font-size: 14pt; margin-bottom: 0.5cm; border-bottom: 1px solid #ccc; padding-bottom: 5px; }
        .footer { margin-top: 2cm; text-align: center; font-size: 10pt; }
        .page-break { page-break-after: always; }
    </style>
</head>
<body>
    <div class="header">
        <img src="{{ public_path('images/university-logo.png') }}" alt="University Logo">
        <h1>{{ $submission->course->department->faculty->university->name ?? 'University Name' }}</h1>
        <p>Student Industrial Work Experience Scheme (SIWES)</p>
        <p>Department of {{ $submission->course->department->name }}</p>
    </div>

    <div class="title">{{ $submission->title }}</div>

    <div class="meta">
        <p><strong>Student:</strong> {{ $submission->user->first_name }} {{ $submission->user->last_name }}</p>
        <p><strong>Matric Number:</strong> {{ $submission->user->student_id ?? 'N/A' }}</p>
        <p><strong>Course:</strong> {{ $submission->course->name }} ({{ $submission->course->code }})</p>
        <p><strong>Submission Date:</strong> {{ $submission->submitted_at?->format('F d, Y') }}</p>
        @if($submission->grade)
            <p><strong>Grade:</strong> {{ $submission->grade->score }}/{{ $submission->grade->max_score }}</p>
        @endif
    </div>

    <div class="section">
        <h2>Introduction</h2>
        <p>{{ $submission->description ?? 'No description provided.' }}</p>
    </div>

    <div class="section">
        <h2>Company Profile</h2>
        <p>[Company information to be filled based on submission content]</p>
    </div>

    <div class="section">
        <h2>Activities Undertaken</h2>
        <p>[Activities to be detailed based on submission content]</p>
    </div>

    <div class="section">
        <h2>Learning Outcomes</h2>
        <p>[Learning outcomes to be detailed based on submission content]</p>
    </div>

    <div class="page-break"></div>

    <div class="section">
        <h2>Attachments</h2>
        @foreach($submission->versions()->where('is_current', true)->get() as $version)
            <p>File: {{ $version->file_name }} ({{ round($version->file_size / 1024, 2) }} KB)</p>
        @endforeach
    </div>

    <div class="footer">
        <p>Generated on {{ $generated_at->format('F d, Y H:i:s') }}</p>
        <p>UniAcademic - University Academic Management Platform</p>
    </div>
</body>
</html>
