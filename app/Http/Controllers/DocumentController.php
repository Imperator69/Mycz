<?php

namespace App\Http\Controllers;

use App\Models\Document;
use App\Services\StatusExporter;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class DocumentController extends Controller
{
    public function download(Document $document): StreamedResponse
    {
        abort_unless($document->local_path && Storage::disk('local')->exists($document->local_path), 404);

        return Storage::disk('local')->download($document->local_path, $document->original_name);
    }

    public function status(StatusExporter $exporter): JsonResponse
    {
        return response()->json($exporter->build(), 200, [], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
    }
}
