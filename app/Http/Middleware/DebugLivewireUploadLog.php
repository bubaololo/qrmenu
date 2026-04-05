<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class DebugLivewireUploadLog
{
    private const LOG_PATH = '.cursor/debug-9b30c2.log';

    private const SESSION_ID = '9b30c2';

    public function handle(Request $request, Closure $next): Response
    {
        $path = $request->path();
        if (! preg_match('#^livewire-[a-f0-9]{8}/upload-file$#', $path)) {
            return $next($request);
        }

        // #region agent log
        $this->agentLog('H1', 'upload-file request limits', [
            'upload_max_filesize' => ini_get('upload_max_filesize'),
            'post_max_size' => ini_get('post_max_size'),
            'memory_limit' => ini_get('memory_limit'),
            'content_length' => $request->header('Content-Length'),
        ]);
        // #endregion

        // #region agent log
        $filesMeta = [];
        foreach ($_FILES ?? [] as $key => $file) {
            $filesMeta[$key] = $this->sanitizeFilesArray($file);
        }
        $this->agentLog('H3', 'upload-file FILES meta', ['files' => $filesMeta]);
        // #endregion

        /** @var Response $response */
        $response = $next($request);

        // #region agent log
        $bodySnippet = '';
        if ($response->getStatusCode() >= 400) {
            $raw = $response->getContent();
            $bodySnippet = is_string($raw) ? mb_substr($raw, 0, 2000) : '';
        }
        $this->agentLog('H2', 'upload-file response', [
            'status' => $response->getStatusCode(),
            'content_type' => $response->headers->get('Content-Type'),
            'body_snippet' => $bodySnippet,
        ]);
        // #endregion

        return $response;
    }

    /**
     * @param  array<string, mixed>|list<mixed>  $file
     * @return array<string, mixed>|list<mixed>
     */
    private function sanitizeFilesArray(array $file): array
    {
        if (isset($file['name']) && is_array($file['name'])) {
            $out = [];
            foreach (array_keys($file['name']) as $i) {
                $out[$i] = [
                    'name' => $file['name'][$i] ?? null,
                    'type' => $file['type'][$i] ?? null,
                    'size' => $file['size'][$i] ?? null,
                    'error' => $file['error'][$i] ?? null,
                    'error_label' => $this->uploadErrorLabel($file['error'][$i] ?? null),
                ];
            }

            return $out;
        }

        return [
            'name' => $file['name'] ?? null,
            'type' => $file['type'] ?? null,
            'size' => $file['size'] ?? null,
            'error' => $file['error'] ?? null,
            'error_label' => $this->uploadErrorLabel($file['error'] ?? null),
        ];
    }

    private function uploadErrorLabel(mixed $code): string
    {
        if (! is_int($code)) {
            return 'unknown';
        }

        return match ($code) {
            UPLOAD_ERR_OK => 'UPLOAD_ERR_OK',
            UPLOAD_ERR_INI_SIZE => 'UPLOAD_ERR_INI_SIZE',
            UPLOAD_ERR_FORM_SIZE => 'UPLOAD_ERR_FORM_SIZE',
            UPLOAD_ERR_PARTIAL => 'UPLOAD_ERR_PARTIAL',
            UPLOAD_ERR_NO_FILE => 'UPLOAD_ERR_NO_FILE',
            UPLOAD_ERR_NO_TMP_DIR => 'UPLOAD_ERR_NO_TMP_DIR',
            UPLOAD_ERR_CANT_WRITE => 'UPLOAD_ERR_CANT_WRITE',
            UPLOAD_ERR_EXTENSION => 'UPLOAD_ERR_EXTENSION',
            default => 'UPLOAD_ERR_'.$code,
        };
    }

    private function agentLog(string $hypothesisId, string $message, array $data): void
    {
        $path = base_path(self::LOG_PATH);
        $line = json_encode([
            'sessionId' => self::SESSION_ID,
            'timestamp' => (int) round(microtime(true) * 1000),
            'hypothesisId' => $hypothesisId,
            'location' => 'DebugLivewireUploadLog',
            'message' => $message,
            'data' => $data,
            'runId' => 'pre-fix',
        ], JSON_UNESCAPED_UNICODE)."\n";
        file_put_contents($path, $line, FILE_APPEND | LOCK_EX);
    }
}
