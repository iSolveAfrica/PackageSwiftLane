<?php
/**
 * Safe file uploads. Every endpoint that stores an uploaded file should go through cdp_uploadStore():
 *  - the type is detected from the file contents (finfo / getimagesize), never from the browser's
 *    MIME type or the file name;
 *  - only the allowed kinds are accepted (images and PDF by default, never PHP/HTML/SVG/JS);
 *  - the stored name is ours (prefix + random hex) with the extension of the detected type, so an
 *    upload can never run as code, overwrite another file or escape the target folder.
 */

if (!function_exists('cdp_uploadKinds')) {

    /** Detected MIME type => extension we store with. */
    function cdp_uploadKinds(string $set = 'docs'): array
    {
        $images = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/gif' => 'gif', 'image/webp' => 'webp', 'image/heic' => 'heic', 'image/heif' => 'heif', 'image/avif' => 'avif', 'image/bmp' => 'bmp', 'image/tiff' => 'tif'];
        if ($set === 'images') return $images;
        $docs = $images + ['application/pdf' => 'pdf'];
        if ($set === 'docs') return $docs;
        // office files for back-office attachments (customs papers, invoices)
        return $docs + ['application/msword' => 'doc', 'application/vnd.openxmlformats-officedocument.wordprocessingml.document' => 'docx',
            'application/vnd.ms-excel' => 'xls', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet' => 'xlsx', 'text/csv' => 'csv', 'text/plain' => 'txt'];
    }

    /** The MIME type of a file from its contents. */
    function cdp_uploadDetect(string $tmp): string
    {
        if (!is_file($tmp)) return '';
        if (function_exists('finfo_open')) {
            $f = finfo_open(FILEINFO_MIME_TYPE);
            $t = (string) finfo_file($f, $tmp);
            finfo_close($f);
            if ($t !== '') return strtolower($t);
        }
        $img = @getimagesize($tmp);
        return $img ? strtolower((string) $img['mime']) : '';
    }

    /**
     * Validate and move one uploaded file. $file is one entry of $_FILES (or a single index of a
     * multiple upload, as ['name','tmp_name','error','size']). Returns
     * ['ok' => true, 'name' => stored file name, 'path' => full path, 'mime' => detected type, 'original' => client name]
     * or ['ok' => false, 'error' => message for the user].
     */
    function cdp_uploadStore(array $file, string $dir, string $prefix = '', string $set = 'docs', int $maxBytes = 10485760): array
    {
        if ((int) ($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK || empty($file['tmp_name']) || !is_uploaded_file($file['tmp_name'])) {
            return ['ok' => false, 'error' => 'The file did not arrive. Try again.'];
        }
        if ((int) ($file['size'] ?? 0) > $maxBytes) {
            return ['ok' => false, 'error' => 'The file is too large (' . round($maxBytes / 1048576) . ' MB at most).'];
        }
        $kinds = cdp_uploadKinds($set);
        $mime = cdp_uploadDetect($file['tmp_name']);
        if (!isset($kinds[$mime])) {
            return ['ok' => false, 'error' => 'This file type is not allowed (' . htmlspecialchars((string) ($file['name'] ?? ''), ENT_QUOTES, 'UTF-8') . '). Upload ' . ($set === 'images' ? 'a photo' : 'a photo or PDF') . '.'];
        }
        $dir = rtrim($dir, '/\\');
        if (!is_dir($dir) && !@mkdir($dir, 0755, true)) {
            return ['ok' => false, 'error' => 'The upload folder could not be created.'];
        }
        $prefix = preg_replace('/[^A-Za-z0-9_-]/', '', $prefix);
        $name = ($prefix !== '' ? $prefix . '_' : '') . date('Ymd') . '_' . bin2hex(random_bytes(8)) . '.' . $kinds[$mime];
        $path = $dir . DIRECTORY_SEPARATOR . $name;
        if (!move_uploaded_file($file['tmp_name'], $path)) {
            return ['ok' => false, 'error' => 'The file could not be stored.'];
        }
        @chmod($path, 0644);
        return ['ok' => true, 'name' => $name, 'path' => $path, 'mime' => $mime, 'original' => basename((string) ($file['name'] ?? ''))];
    }

    /** Index $i of a multiple upload field ($_FILES['x'] with array name/tmp_name/...) as one file. */
    function cdp_uploadPick(array $multi, int $i): array
    {
        return ['name' => $multi['name'][$i] ?? '', 'tmp_name' => $multi['tmp_name'][$i] ?? '', 'error' => $multi['error'][$i] ?? UPLOAD_ERR_NO_FILE, 'size' => $multi['size'][$i] ?? 0];
    }
}
