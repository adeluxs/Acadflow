<?php

namespace App\Http\Controllers;

use App\Models\ResearchProject;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;

class ResearchExportController extends Controller
{
    private function state(Request $request, string $export): array
    {
        abort_unless(\Illuminate\Support\Str::isUuid($export), 404);
        $state = Cache::get('research.export.'.$export);
        abort_unless($state && (int) $state['user_id'] === (int) $request->user()->id, 404);
        $this->authorize('view', ResearchProject::findOrFail($state['project_id']));
        return $state;
    }

    public function show(Request $request, string $export)
    {
        $state = $this->state($request, $export);
        $research = ResearchProject::findOrFail($state['project_id']);
        $status = $state['status'];
        $download = $status === 'completed' ? route('research.exports.download', $export) : null;
        if ($request->expectsJson()) return response()->json(compact('status', 'download'))->header('Cache-Control', 'private, no-store');
        return response()->view('research.export-progress', compact('research', 'status', 'download', 'export'))->header('Cache-Control', 'private, no-store');
    }

    public function download(Request $request, string $export)
    {
        $state = $this->state($request, $export);
        $disk = Storage::disk($state['disk'] ?? 'local');
        abort_unless($state['status'] === 'completed' && isset($state['path']) && $disk->exists($state['path']), 404);
        return $disk->download($state['path'], $state['filename'], ['Cache-Control' => 'private, no-store']);
    }
}
