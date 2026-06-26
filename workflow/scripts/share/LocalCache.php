<?php
const APPLE_MUSIC_ARTWORK_SIZE = 256;
const DEFAULT_CONCURRENT_DOWNLOADS = 8;
const DEFAULT_COMPOSITE_TILE_SIZE = 128;

/**
 * Get the resource filename from a URL path.
 *
 * @param string $url URL String
 * @return string filename (10000.jpg)
 */
function getUrlResourceName(string $url): string
{
    $path = parse_url($url, PHP_URL_PATH);
    if (!is_string($path) || $path === "") {
        throw new InvalidArgumentException("Invalid URL path: {$url}");
    }

    $segments = array_reverse(
        array_values(array_filter(explode("/", trim($path, "/")), "strlen")),
    );

    foreach ($segments as $segment) {
        if (str_contains($segment, "{") || str_contains($segment, "}")) {
            continue;
        }

        $extension = pathinfo($segment, PATHINFO_EXTENSION);
        $filename = pathinfo($segment, PATHINFO_FILENAME);
        if ($extension !== "" && $filename !== "") {
            return $segment;
        }
    }

    throw new InvalidArgumentException(
        "Cannot resolve resource name from URL: {$url}",
    );
}

/**
 * Resolve an Apple Music artwork template URL to a fixed-size downloadable URL.
 *
 * @param string $artwork_url
 * @param int $size
 * @return string
 * @throws InvalidArgumentException
 */
function resolveAppleMusicArtworkUrl(
    string $artwork_url,
    int $size = APPLE_MUSIC_ARTWORK_SIZE,
): string {
    $resource_name = getUrlResourceName($artwork_url);
    $extension = pathinfo($resource_name, PATHINFO_EXTENSION);
    if ($extension === "") {
        throw new InvalidArgumentException(
            "Cannot resolve artwork extension from URL: {$artwork_url}",
        );
    }

    $resolved_url = strtr($artwork_url, [
        "{w}" => (string) $size,
        "{h}" => (string) $size,
        "{f}" => $extension,
    ]);

    if (str_contains($resolved_url, "{") || str_contains($resolved_url, "}")) {
        throw new InvalidArgumentException(
            "Artwork URL still contains unresolved placeholders: {$artwork_url}",
        );
    }

    return $resolved_url;
}

/**
 * Build the artwork cache directory for a resource type.
 *
 * @param string $cache_dir Base cache directory.
 * @param string $resource_type Resource type such as albums or playlists.
 * @return string Artwork cache directory path.
 */
function getArtworkCacheDirectory(
    string $cache_dir,
    string $resource_type,
): string {
    return rtrim($cache_dir, DIRECTORY_SEPARATOR) .
        DIRECTORY_SEPARATOR .
        "Music-Recommend" .
        DIRECTORY_SEPARATOR .
        "artwork" .
        DIRECTORY_SEPARATOR .
        $resource_type;
}

/**
 * Build the cache directory for recommendation row composite artwork.
 *
 * @param string $cache_dir Base cache directory.
 * @return string Composite artwork cache directory path.
 */
function getArtworkCompositeDirectory(string $cache_dir): string
{
    return rtrim($cache_dir, DIRECTORY_SEPARATOR) .
        DIRECTORY_SEPARATOR .
        "Music-Recommend" .
        DIRECTORY_SEPARATOR .
        "artwork" .
        DIRECTORY_SEPARATOR .
        "sections";
}

/**
 * Build the local cache path for an artwork file without downloading it.
 *
 * @param string $artwork_url Apple Music artwork template URL.
 * @param string $resource_type Resource type such as albums or playlists.
 * @param string $cache_dir Base cache directory.
 * @return string Local artwork cache file path.
 */
function getCachedArtworkPath(
    string $artwork_url,
    string $resource_type,
    string $cache_dir,
): string {
    return getArtworkCacheDirectory($cache_dir, $resource_type) .
        DIRECTORY_SEPARATOR .
        getUrlResourceName($artwork_url);
}

/**
 * Cache an Apple Music artwork file to the typed artwork cache directory.
 *
 * @param string $artwork_url Apple Music artwork template URL.
 * @param string $resource_type Resource type such as albums or playlists.
 * @param string $cache_dir Base cache directory.
 * @return string Local cached artwork file path.
 */
function cacheArtworkFile(
    string $artwork_url,
    string $resource_type,
    string $cache_dir,
): string {
    $resolved_url = resolveAppleMusicArtworkUrl($artwork_url);
    $cache_path = getCachedArtworkPath(
        $artwork_url,
        $resource_type,
        $cache_dir,
    );
    return cacheFileToPath($resolved_url, $cache_path);
}

/**
 * Build the local cache path for a recommendation row composite artwork image.
 *
 * @param string $section_id Recommendation row identifier.
 * @param array<int, string> $source_paths Source artwork file paths.
 * @param string $cache_dir Base cache directory.
 * @return string Local composite artwork file path.
 */
function getArtworkCompositePath(
    string $section_id,
    array $source_paths,
    string $cache_dir,
): string {
    $hash = substr(md5(implode("|", $source_paths)), 0, 12);
    return getArtworkCompositeDirectory($cache_dir) .
        DIRECTORY_SEPARATOR .
        "{$section_id}-{$hash}.png";
}

/**
 * Cache multiple Apple Music artwork files concurrently.
 *
 * @param array<array{artwork_url: string, resource_type: string}> $artwork_items Artwork download tasks.
 * @param string $cache_dir Base cache directory.
 * @param int $concurrency Max concurrent downloads.
 * @return array<int, array{resource_type: string, artwork_url: string, cache_path?: string, error?: string}> Per-item cache results.
 */
function cacheArtworkFilesConcurrently(
    array $artwork_items,
    string $cache_dir,
    int $concurrency = DEFAULT_CONCURRENT_DOWNLOADS,
): array {
    $results = [];
    $queue = [];

    foreach ($artwork_items as $item) {
        try {
            $resolved_url = resolveAppleMusicArtworkUrl($item["artwork_url"]);
            $cache_path = getCachedArtworkPath(
                $item["artwork_url"],
                $item["resource_type"],
                $cache_dir,
            );
        } catch (Throwable $e) {
            $results[] = [
                "resource_type" => $item["resource_type"],
                "artwork_url" => $item["artwork_url"],
                "error" => $e->getMessage(),
            ];
            continue;
        }

        if (is_file($cache_path)) {
            $results[] = [
                "resource_type" => $item["resource_type"],
                "artwork_url" => $item["artwork_url"],
                "cache_path" => $cache_path,
            ];
            continue;
        }

        $file_dir = dirname($cache_path);
        if (
            !is_dir($file_dir) &&
            !mkdir($file_dir, recursive: true) &&
            !is_dir($file_dir)
        ) {
            $results[] = [
                "resource_type" => $item["resource_type"],
                "artwork_url" => $item["artwork_url"],
                "error" => "Failed to create cache directory: {$file_dir}",
            ];
            continue;
        }

        $queue[] = [
            "resource_type" => $item["resource_type"],
            "artwork_url" => $item["artwork_url"],
            "resolved_url" => $resolved_url,
            "cache_path" => $cache_path,
        ];
    }

    if ($queue === []) {
        return $results;
    }

    $multi = curl_multi_init();
    $active_handles = [];
    $queue_index = 0;
    $concurrency = max(1, $concurrency);

    $addHandle = function (array $task) use ($multi, &$active_handles): void {
        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL => $task["resolved_url"],
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_MAXREDIRS => 10,
            CURLOPT_TIMEOUT => 30,
            CURLOPT_FAILONERROR => false,
        ]);
        curl_multi_add_handle($multi, $ch);
        $active_handles[(int) $ch] = [
            "handle" => $ch,
            "task" => $task,
        ];
    };

    while (
        $queue_index < count($queue) &&
        count($active_handles) < $concurrency
    ) {
        $addHandle($queue[$queue_index]);
        $queue_index++;
    }

    do {
        do {
            $status = curl_multi_exec($multi, $running);
        } while ($status === CURLM_CALL_MULTI_PERFORM);

        while (($info = curl_multi_info_read($multi)) !== false) {
            $ch = $info["handle"];
            $handle_key = (int) $ch;
            $task = $active_handles[$handle_key]["task"];

            if ($info["result"] !== CURLE_OK) {
                $results[] = [
                    "resource_type" => $task["resource_type"],
                    "artwork_url" => $task["artwork_url"],
                    "error" => curl_error($ch),
                ];
            } else {
                $response = curl_multi_getcontent($ch);
                $http_code = (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE);

                if ($http_code !== 200) {
                    $results[] = [
                        "resource_type" => $task["resource_type"],
                        "artwork_url" => $task["artwork_url"],
                        "error" => "Failed to download file from {$task["resolved_url"]}, HTTP status: {$http_code}",
                    ];
                } elseif (!is_string($response) || $response === "") {
                    $results[] = [
                        "resource_type" => $task["resource_type"],
                        "artwork_url" => $task["artwork_url"],
                        "error" => "Downloaded empty response from {$task["resolved_url"]}",
                    ];
                } elseif (
                    file_put_contents($task["cache_path"], $response) === false
                ) {
                    $results[] = [
                        "resource_type" => $task["resource_type"],
                        "artwork_url" => $task["artwork_url"],
                        "error" => "Failed to write cache file: {$task["cache_path"]}",
                    ];
                } else {
                    $results[] = [
                        "resource_type" => $task["resource_type"],
                        "artwork_url" => $task["artwork_url"],
                        "cache_path" => $task["cache_path"],
                    ];
                }
            }

            curl_multi_remove_handle($multi, $ch);
            unset($active_handles[$handle_key]);

            if ($queue_index < count($queue)) {
                $addHandle($queue[$queue_index]);
                $queue_index++;
            }
        }

        if ($running > 0) {
            curl_multi_select($multi, 1.0);
        }
    } while ($running > 0 || $active_handles !== []);

    curl_multi_close($multi);
    return $results;
}

/**
 * Build and cache a 2x2 composite artwork image from up to 4 local artwork files.
 *
 * @param string $section_id Recommendation row identifier.
 * @param array<int, string> $source_paths Source artwork file paths.
 * @param string $cache_dir Base cache directory.
 * @param int $tile_size Size of each tile in pixels.
 * @return string Local composite artwork file path.
 */
function createArtworkCompositeImage(
    string $section_id,
    array $source_paths,
    string $cache_dir,
    int $tile_size = DEFAULT_COMPOSITE_TILE_SIZE,
): string {
    $source_paths = array_values(array_slice($source_paths, 0, 4));
    if ($source_paths === []) {
        throw new InvalidArgumentException(
            "Missing source artwork files for section: {$section_id}",
        );
    }

    $cache_path = getArtworkCompositePath(
        $section_id,
        $source_paths,
        $cache_dir,
    );
    if (is_file($cache_path)) {
        return $cache_path;
    }

    $file_dir = dirname($cache_path);
    if (
        !is_dir($file_dir) &&
        !mkdir($file_dir, recursive: true) &&
        !is_dir($file_dir)
    ) {
        throw new RuntimeException(
            "Failed to create cache directory: {$file_dir}",
        );
    }

    $canvas_size = $tile_size * 2;
    $canvas = imagecreatetruecolor($canvas_size, $canvas_size);
    if ($canvas === false) {
        throw new RuntimeException("Failed to create composite image canvas");
    }

    $background = imagecolorallocate($canvas, 24, 24, 24);
    imagefill($canvas, 0, 0, $background);

    foreach ($source_paths as $index => $source_path) {
        $image_data = file_get_contents($source_path);
        if ($image_data === false) {
            throw new RuntimeException(
                "Failed to read artwork file: {$source_path}",
            );
        }

        $source_image = imagecreatefromstring($image_data);
        if ($source_image === false) {
            throw new RuntimeException(
                "Failed to decode artwork file: {$source_path}",
            );
        }

        $dst_x = ($index % 2) * $tile_size;
        $dst_y = intdiv($index, 2) * $tile_size;
        imagecopyresampled(
            $canvas,
            $source_image,
            $dst_x,
            $dst_y,
            0,
            0,
            $tile_size,
            $tile_size,
            imagesx($source_image),
            imagesy($source_image),
        );
    }

    if (!imagepng($canvas, $cache_path)) {
        throw new RuntimeException(
            "Failed to write composite artwork file: {$cache_path}",
        );
    }

    return $cache_path;
}

/**
 * Cache a URL file into a directory using the URL filename as the cache key.
 *
 * @param string $url Remote file URL.
 * @param string $file_dir Target cache directory.
 * @return string Local cached file path.
 */
function cacheFile(string $url, string $file_dir): string
{
    $resource_name = getUrlResourceName($url);
    $cache_path =
        rtrim($file_dir, DIRECTORY_SEPARATOR) .
        DIRECTORY_SEPARATOR .
        $resource_name;

    return cacheFileToPath($url, $cache_path);
}

/**
 * Cache a URL file to an explicit local path.
 *
 * @param string $url Remote file URL.
 * @param string $cache_path Full local cache file path.
 * @return string Local cached file path.
 */
function cacheFileToPath(string $url, string $cache_path): string
{
    $file_dir = dirname($cache_path);

    if (is_file($cache_path)) {
        return $cache_path;
    }

    if (
        !is_dir($file_dir) &&
        !mkdir($file_dir, recursive: true) &&
        !is_dir($file_dir)
    ) {
        throw new RuntimeException(
            "Failed to create cache directory: {$file_dir}",
        );
    }

    $ch = curl_init();
    curl_setopt_array($ch, [
        CURLOPT_URL => $url,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_MAXREDIRS => 10,
        CURLOPT_TIMEOUT => 30,
        CURLOPT_FAILONERROR => false,
    ]);

    $response = curl_exec($ch);
    $http_code = (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE);

    if (curl_errno($ch)) {
        $message = curl_error($ch);
        $code = curl_errno($ch);
        throw new RuntimeException(
            json_encode(
                [
                    "success" => false,
                    "message" => $message,
                    "code" => $code,
                ],
                JSON_UNESCAPED_UNICODE,
            ),
        );
    }

    if ($http_code !== 200) {
        throw new RuntimeException(
            "Failed to download file from {$url}, HTTP status: {$http_code}",
        );
    }

    if (!is_string($response) || $response === "") {
        throw new RuntimeException("Downloaded empty response from {$url}");
    }

    if (file_put_contents($cache_path, $response) === false) {
        throw new RuntimeException("Failed to write cache file: {$cache_path}");
    }

    return $cache_path;
}
