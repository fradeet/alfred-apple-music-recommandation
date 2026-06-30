<?php

function getCacheFileExtension(string $url): string
{
    $path = parse_url($url, PHP_URL_PATH) ?: "";
    $extension = strtolower(pathinfo($path, PATHINFO_EXTENSION));
    if (in_array($extension, ["jpg", "jpeg", "png", "webp"], true)) {
        return $extension === "jpeg" ? "jpg" : $extension;
    }
    return "jpg";
}

function sanitizeCacheNamePart(string $value): string
{
    return trim(preg_replace("/[^A-Za-z0-9._-]+/", "_", $value), "_");
}

function getCachePathForRequest(
    string|array $request,
    string $cacheDir,
): ?string {
    $url = is_array($request) ? $request["url"] ?? null : $request;
    if (!is_string($url) || $url === "") {
        return null;
    }

    if (is_array($request) && isset($request["type"], $request["id"])) {
        $type = sanitizeCacheNamePart((string) $request["type"]);
        $id = sanitizeCacheNamePart((string) $request["id"]);
        $size = sanitizeCacheNamePart((string) ($request["size"] ?? "origin"));
        $extension = getCacheFileExtension($url);
        return $cacheDir .
            DIRECTORY_SEPARATOR .
            "{$type}_{$id}_{$size}.{$extension}";
    }

    $extension = getCacheFileExtension($url);
    return $cacheDir . DIRECTORY_SEPARATOR . sha1($url) . ".{$extension}";
}

// Codex TODO 获取缓存函数
// 输入：文件 URL
// 输出：缓存文件路径或空
function checkFileCache(string $url, string $cacheDir): ?string
{
    $path = getCachePathForRequest($url, $cacheDir);
    if ($path !== null && is_file($path) && is_readable($path)) {
        return $path;
    }
    return null;
}

// Codex TODO 并行缓存函数
// 输入：数组：URL，缓存目录
// 输出：缓存文件路径数组
function cacheFilesParallel(array $urls, string $cacheDir): array
{
    if (!is_dir($cacheDir)) {
        mkdir($cacheDir, recursive: true);
    }

    $results = [];
    $pending = [];

    foreach ($urls as $key => $request) {
        $path = getCachePathForRequest($request, $cacheDir);
        $url = is_array($request) ? $request["url"] ?? null : $request;
        if ($path === null || !is_string($url) || $url === "") {
            $results[$key] = null;
            continue;
        }
        if (is_file($path) && is_readable($path)) {
            $results[$key] = $path;
            continue;
        }
        $results[$key] = null;
        $pending[] = [
            "key" => $key,
            "url" => $url,
            "path" => $path,
        ];
    }

    $multi = curl_multi_init();
    $running = 0;
    $next = 0;
    $handles = [];
    $concurrency = 5;

    $addHandle = function () use (&$next, $pending, $multi, &$handles): void {
        if (!isset($pending[$next])) {
            return;
        }
        $item = $pending[$next++];
        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL => $item["url"],
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_MAXREDIRS => 5,
            CURLOPT_CONNECTTIMEOUT => 15,
            CURLOPT_TIMEOUT => 60,
            CURLOPT_USERAGENT => "alfred-apple-music-recommandation/0.1",
        ]);
        curl_multi_add_handle($multi, $ch);
        $handles[spl_object_id($ch)] = [
            "handle" => $ch,
            "key" => $item["key"],
            "path" => $item["path"],
        ];
    };

    for ($i = 0; $i < $concurrency && $i < count($pending); $i++) {
        $addHandle();
    }

    do {
        do {
            $status = curl_multi_exec($multi, $running);
        } while ($status === CURLM_CALL_MULTI_PERFORM);

        while ($info = curl_multi_info_read($multi)) {
            $ch = $info["handle"];
            $handleId = spl_object_id($ch);
            $meta = $handles[$handleId] ?? null;
            if ($meta !== null) {
                $body = curl_multi_getcontent($ch);
                $httpCode = curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
                if (
                    $info["result"] === CURLE_OK &&
                    $httpCode >= 200 &&
                    $httpCode < 300 &&
                    is_string($body) &&
                    $body !== "" &&
                    file_put_contents($meta["path"], $body) !== false
                ) {
                    $results[$meta["key"]] = $meta["path"];
                }
                unset($handles[$handleId]);
            }
            curl_multi_remove_handle($multi, $ch);
            $addHandle();
        }

        if ($running > 0) {
            curl_multi_select($multi, 1.0);
        }
    } while ($running > 0 || $next < count($pending) || count($handles) > 0);

    curl_multi_close($multi);

    return $results;
}
