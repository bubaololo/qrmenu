<?php

namespace App\Support\PageCache;

use Silber\PageCache\Cache;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * page-cache store that also writes a pre-compressed `.gz` sibling next to each
 * cached file, so nginx `gzip_static on` serves it with zero per-request gzip
 * CPU. Compression runs once at write time, not on every hit.
 */
class GzipCache extends Cache
{
    public function cache(Request $request, Response $response)
    {
        parent::cache($request, $response);

        [$path, $file] = $this->getDirectoryAndFileNames($request, $response);
        $compressed = gzencode((string) $response->getContent(), 9);

        if ($compressed !== false) {
            $this->files->put($this->join([$path, $file]).'.gz', $compressed, true);
        }
    }

    public function forget($slug)
    {
        $deleted = parent::forget($slug);

        foreach (['html', 'json', 'xml'] as $ext) {
            $this->files->delete($this->getCachePath("{$slug}.{$ext}.gz"));
        }

        return $deleted;
    }
}
