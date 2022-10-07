<?php

declare(strict_types=1);

namespace Divinity76\httpreadfile;

function httpreadfile(string $path): void
{
    try {
        if (!is_readable($path)) {
            throw new \RuntimeException("file not readable: '{$path}'");
        }
        $cacheTimeSeconds = 1 * 60 * 60 * 24;
        $mtime = filemtime($path);
        if ($mtime !== false) {
            $ifModifiedSince = $_SERVER['HTTP_IF_MODIFIED_SINCE'] ?? null;
            if ($ifModifiedSince !== null) {
                $ifModifiedSince = strtotime($ifModifiedSince);
                if ($ifModifiedSince === false) {
                    http_response_code(400);
                    echo "Invalid If-Modified-Since header";
                    return;
                }
                if ($mtime <= $ifModifiedSince) {
                    http_response_code(304);
                    return;
                }
            }
            header("Last-Modified: " . gmdate('D, d M Y H:i:s', $mtime) . ' GMT');
        }
        header("Expires: " . gmdate('D, d M Y H:i:s', time() + $cacheTimeSeconds) . ' GMT');
        header("Cache-Control: max-age={$cacheTimeSeconds}");
        header("Pragma: cache");
        $filesize = filesize($path);
        if ($filesize == false) {
            // unable to get filesize?? having a hard time imagining this actually happening
            header("Accept-Ranges: none");
            if (false === readfile($path)) {
                throw new \RuntimeException("failed to readfile('{$path}')");
            }
            return;
        }
        $range = $_SERVER['HTTP_RANGE'] ?? null;
        if ($range === null) {
            header("Accept-Ranges: bytes");
            header("Content-Length: {$filesize}");
            if (false === readfile($path)) {
                throw new \RuntimeException("failed to readfile('{$path}')");
            }
            return;
        }
        if (substr_compare($range, "bytes=", 0, 6, false) !== 0) {
            http_response_code(416);
            echo "Invalid Range header: does not start with 'bytes='";
            return;
        }
        $range = substr($range, strlen("bytes="));
        $range = explode('-', $range);
        if (count($range) < 2) {
            http_response_code(416);
            echo "Invalid range: dash missing";
            return;
        }
        if (count($range) > 2) {
            http_response_code(416);
            echo "Invalid range: more than 1 dash";
            return;
        }
        $start = filter_var(trim($range[0]), FILTER_VALIDATE_INT);
        if ($start === false || $start < 0) {
            http_response_code(416);
            echo "Invalid range: start is not an integer >=0";
            return;
        }
        if ($start >= $filesize) {
            http_response_code(416);
            echo "Invalid range: start is >= filesize";
            return;
        }
        $end = $range[1];
        if ($end === "") {
            $end = $filesize - 1;
        } else {
            $end = filter_var(trim($range[1]), FILTER_VALIDATE_INT);
            if ($end === false) {
                http_response_code(416);
                echo "Invalid range: end is not an integer";
                return;
            }
            if ($end >= $filesize) {
                //echo "Invalid range: end is larger than filesize";
                // this request is actually legal, i think. at least nginx accepts it:
                $end = $filesize - 1;
            } elseif ($end < $start) {
                http_response_code(416);
                echo "Invalid range: end is smaller than start";
                return;
            }
        }
        $length = ($end - $start) + 1;
        $fp = fopen($path, 'rb');
        if ($fp === false) {
            throw new \RuntimeException("Failed to fopen('{$path}','rb');");
        }
        // using this php://output hack because fpassthru is unsuitable: https://github.com/php/php-src/issues/9673
        $output = fopen('php://output', 'wb');
        if ($output === false) {
            fclose($fp);
            throw new \RuntimeException("Failed to fopen('php://output','wb');");
        }
        http_response_code(206);
        header("Content-Length: {$length}");
        header("Content-Range: bytes {$start}-{$end}/{$filesize}");
        $sent = stream_copy_to_stream($fp, $output, $length, $start);
        fclose($fp);
        fclose($output);
        if ($sent === false) {
            throw new \RuntimeException("Failed to stream_copy_to_stream()");
        }
    } catch (\Throwable $ex) {
        $errstr = "500 Internal Server Error: An internal server error has been occurred.";
        if (filter_var(ini_get('display_errors'), FILTER_VALIDATE_BOOLEAN)) {
            $errstr .= " " . var_export(["ex" => $ex, "error_get_last" => error_get_last()], true);
        }
        if (!headers_sent()) {
            header("Content-Length: ", true, 500);
        }
        echo $errstr;
        throw $ex;
    }
}
