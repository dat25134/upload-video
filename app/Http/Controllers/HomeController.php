<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class HomeController extends Controller
{
    public function upload()
    {
        return view('upload');
    }

    public function uploadWorker()
    {
        return view('upload-worker');
    }

    public function uploadFile(Request $request)
    {
        try {
            $validator = Validator::make($request->all(), [
                'name' => 'required|string|max:255|min:5',
            ]);

            if ($validator->fails()) {
                return response()->json(['message' => 'Validation failed'], 422);
            }

            $file = $request->file('file');
            $file->storeAs('uploads', $file->getClientOriginalName());
            return response()->json(['message' => 'File uploaded successfully']);
        } catch (\Exception $e) {
            return response()->json(['message' => 'File upload failed'], 500);
        }
    }

    public function uploadOptimized()
    {
        return view('upload-optimized');
    }

    public function initUpload(Request $request)
    {
        $sessionId = Str::uuid();

        Cache::put("upload_session_{$sessionId}", [
            'filename' => $request->filename,
            'total_chunks' => $request->totalChunks,
            'uploaded_chunks' => []
        ], 24 * 3600);

        return response()->json(['sessionId' => $sessionId]);
    }

    public function uploadChunk(Request $request)
    {
        $chunk = $request->file('chunk');
        $sessionId = $request->sessionId;
        $chunkIndex = $request->chunkIndex;

        // Lưu chunk vào temporary storage
        Storage::disk('public')->putFileAs(
            "chunks/{$sessionId}",
            $chunk,
            "chunk_{$chunkIndex}"
        );

        // Cập nhật session
        $session = Cache::get("upload_session_{$sessionId}");
        $session['uploaded_chunks'][] = $chunkIndex;
        Cache::put("upload_session_{$sessionId}", $session, 24 * 3600);

        return response()->json(['success' => true]);
    }

    public function finalizeUpload(Request $request)
    {
        $sessionId = $request->sessionId;
        $session = Cache::get("upload_session_{$sessionId}");

        try {
            // Đảm bảo các thư mục tồn tại
            Storage::disk('public')->makeDirectory('uploads', 0755, true);
            Storage::disk('public')->makeDirectory('uploads/hls', 0755, true);

            $uploadDir = dirname(Storage::disk('public')->path("uploads/{$sessionId}/{$session['filename']}"));
            if (!is_dir($uploadDir)) {
                mkdir($uploadDir, 0755, true);
            }

            // Tạo file gốc trước
            $outputPath = Storage::disk('public')->path("uploads/{$sessionId}/{$session['filename']}");
            touch($outputPath);
            $outputFile = fopen($outputPath, 'wb');

            // Merge các chunks
            for ($i = 0; $i < $session['total_chunks']; $i++) {
                $chunkPath = Storage::disk('public')->path("chunks/{$sessionId}/chunk_{$i}");
                $chunkContent = file_get_contents($chunkPath);
                fwrite($outputFile, $chunkContent);
            }
            fclose($outputFile);

            // Tạo HLS segments
            $filename = pathinfo($session['filename'], PATHINFO_FILENAME);
            $hlsDir = dirname(Storage::disk('public')->path("uploads/hls/{$sessionId}/{$filename}.m3u8"));
            if (!is_dir($hlsDir)) {
                mkdir($hlsDir, 0755, true);
            }
            $hlsDir = Storage::disk('public')->path("uploads/hls/{$sessionId}/{$filename}.m3u8");
            $cmd = "ffmpeg -i {$outputPath} -codec: copy -start_number 0 -hls_time 10 -hls_list_size 0 -f hls {$hlsDir}";
            Log::info("Starting FFmpeg command: " . $cmd);

            exec($cmd . " 2>&1", $output, $returnCode);

            Log::info("FFmpeg output: " . implode("\n", $output));

            if ($returnCode !== 0) {
                Log::error("FFmpeg failed with return code: " . $returnCode);
                Log::error("FFmpeg error output: " . implode("\n", $output));
                throw new \Exception('Failed to create HLS segments. Error: ' . implode("\n", $output));
            }

            Log::info("FFmpeg command completed successfully");

            // Cleanup
            Storage::disk('public')->deleteDirectory("chunks/{$sessionId}");
            Storage::disk('public')->deleteDirectory("uploads/{$sessionId}");
            Cache::forget("upload_session_{$sessionId}");

            return response()->json([
                'success' => true,
                'original' => "/uploads/{$sessionId}/{$session['filename']}",
                'hls' => "/uploads/hls/{$sessionId}/{$filename}.m3u8"
            ]);

        } catch (\Exception $e) {
            // Cleanup khi có lỗi
            Storage::disk('public')->deleteDirectory("chunks/{$sessionId}");
            Storage::disk('public')->deleteDirectory("uploads/{$sessionId}");
            Storage::disk('public')->deleteDirectory("uploads/hls/{$sessionId}");
            Cache::forget("upload_session_{$sessionId}");

            return response()->json([
                'success' => false,
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function videos()
    {
        $videos = Storage::disk('public')->allFiles('uploads/hls');
        $videosFiltered = collect($videos)->filter(function ($video) {
            return pathinfo($video, PATHINFO_EXTENSION) === 'm3u8';
        });

        return view('videos', compact('videosFiltered'));
    }
}
