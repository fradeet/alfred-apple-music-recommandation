<?php
declare(strict_types=1);
require_once __DIR__ . "/share/AppleMusic.php";
require_once __DIR__ . "/share/LocalCache.php";
require_once __DIR__ . "/AlfredSFType/AlfredScriptFilterType.php";

/** Define a custom error handler to avoid warning messages break result extraction in Alfred */
set_error_handler(function ($severity, $message, $file, $line) {
    if (!(error_reporting() & $severity)) {
        return false;
    }
    fwrite(
        STDERR,
        sprintf("[%s] %s in %s:%d\n", $severity, $message, $file, $line),
    );
    return true;
});

/**
 * Pre-cache album and playlist artwork files from the recommendation JSON.
 *
 * @param object $rec_obj
 * @param string $cache_dir
 * @return void
 */
function cacheArtworkResources(object $rec_obj, string $cache_dir): void
{
    $artwork_items = [];

    foreach (
        [ResourcesType::Albums, ResourcesType::Playlists]
        as $resource_type
    ) {
        $resources = $rec_obj->resources->{$resource_type->value} ?? null;
        if (!is_object($resources)) {
            continue;
        }

        foreach ($resources as $item) {
            $artwork_url = $item->attributes->artwork->url ?? null;
            if (!is_string($artwork_url) || $artwork_url === "") {
                continue;
            }
            $artwork_items[] = [
                "resource_type" => $resource_type->value,
                "artwork_url" => $artwork_url,
            ];
        }
    }

    foreach (
        cacheArtworkFilesConcurrently($artwork_items, $cache_dir)
        as $result
    ) {
        if (!isset($result["error"])) {
            continue;
        }

        fwrite(
            STDERR,
            sprintf(
                "[artwork-cache] %s %s\n",
                $result["resource_type"],
                $result["error"],
            ),
        );
    }
}

/**
 * Build an Alfred icon object from a cached local artwork file when available.
 *
 * @param object $item
 * @param ResourcesType $resource_type
 * @param string $cache_dir
 * @return null|AlfredSFItemIcon
 */
function getCachedArtworkIcon(
    object $item,
    ResourcesType $resource_type,
    string $cache_dir,
): ?AlfredSFItemIcon {
    $artwork_url = $item->attributes->artwork->url ?? null;
    if (!is_string($artwork_url) || $artwork_url === "") {
        return null;
    }

    try {
        $cache_path = getCachedArtworkPath(
            $artwork_url,
            $resource_type->value,
            $cache_dir,
        );
    } catch (Throwable) {
        return null;
    }

    if (!is_file($cache_path)) {
        return null;
    }

    return new AlfredSFItemIcon($cache_path);
}

/**
 * Build the subtitle text for a playlist item.
 *
 * @param object $item
 * @return null|string
 */
function getPlaylistSubtitle(object $item): ?string
{
    $parts = array_values(
        array_filter([
            $item->attributes->curatorName ?? null,
            $item->attributes->playlistType ?? null,
        ]),
    );

    if ($parts === []) {
        return null;
    }

    return implode(" - ", $parts);
}

/**
 * Collect the first cached artwork paths for a recommendation row.
 *
 * @param object $rec_obj Recommendation JSON object.
 * @param object $rec_row Recommendation row object.
 * @param string $cache_dir Alfred workflow cache directory.
 * @param int $limit Max artwork count to collect.
 * @return array<int, string> Cached artwork file paths.
 */
function getRecommendationRowArtworkPaths(
    object $rec_obj,
    object $rec_row,
    string $cache_dir,
    int $limit = 4,
): array {
    $paths = [];

    foreach ($rec_row->relationships->contents->data as $content_item) {
        if (
            !in_array(
                $content_item->type,
                [ResourcesType::Albums->value, ResourcesType::Playlists->value],
                true,
            )
        ) {
            continue;
        }

        $resource =
            $rec_obj->resources->{$content_item->type}->{$content_item->id} ??
            null;
        $artwork_url = $resource?->attributes?->artwork?->url ?? null;
        if (!is_string($artwork_url) || $artwork_url === "") {
            continue;
        }

        try {
            $cache_path = getCachedArtworkPath(
                $artwork_url,
                $content_item->type,
                $cache_dir,
            );
        } catch (Throwable) {
            continue;
        }

        if (!is_file($cache_path)) {
            continue;
        }

        $paths[] = $cache_path;
        if (count($paths) >= $limit) {
            break;
        }
    }

    return $paths;
}

/**
 * Build an Alfred icon object for a recommendation row composite artwork image.
 *
 * @param object $rec_obj Recommendation JSON object.
 * @param object $rec_row Recommendation row object.
 * @param string $cache_dir Alfred workflow cache directory.
 * @return null|AlfredSFItemIcon
 */
function getRecommendationRowIcon(
    object $rec_obj,
    object $rec_row,
    string $cache_dir,
): ?AlfredSFItemIcon {
    $artwork_paths = getRecommendationRowArtworkPaths(
        $rec_obj,
        $rec_row,
        $cache_dir,
    );
    if ($artwork_paths === []) {
        return null;
    }

    try {
        $composite_path = createArtworkCompositeImage(
            (string) $rec_row->id,
            $artwork_paths,
            $cache_dir,
        );
    } catch (Throwable $e) {
        fwrite(STDERR, sprintf("[artwork-composite] %s\n", $e->getMessage()));
        return null;
    }

    return new AlfredSFItemIcon($composite_path);
}

/**
 * Fetch Apple Music recommendations, cache the JSON and artwork files, and build the top-level Alfred list.
 *
 * @param string $auth_token Apple Music authorization token.
 * @param string $media_token Apple Music media user token.
 * @param string $cache_dir Alfred workflow cache directory.
 * @param int $cache_duration_time Alfred Script Filter cache duration in seconds.
 * @return AlfredSF Alfred Script Filter result for recommendation rows.
 */
function initRecPageAlfred(
    string $auth_token,
    string $media_token,
    string $cache_dir,
    int $cache_duration_time = 14400,
): AlfredSF {
    $rec_json = getRecommandation(
        new AppleMusicAccountConfig($auth_token, $media_token),
    );
    $filename = time() . ".json";
    $cache_file_path =
        $cache_dir .
        DIRECTORY_SEPARATOR .
        "Music-Recommend" .
        DIRECTORY_SEPARATOR .
        $filename;
    $dir = dirname($cache_file_path);
    if (!is_dir($dir)) {
        mkdir($dir, recursive: true);
    }
    $rec_obj = json_decode($rec_json);
    cacheArtworkResources($rec_obj, $cache_dir);
    file_put_contents($cache_file_path, $rec_json);
    $items = [];
    $rec_res = $rec_obj->resources->{"personal-recommendation"};
    foreach ($rec_obj->data as $item) {
        $res_item = $rec_res->{$item->id};
        // Filter out items without a title
        if (
            property_exists($res_item->attributes, "title") &&
            RecommandKindType::tryFrom($res_item->attributes->kind) !== null
        ) {
            $items[] = new AlfredSFItem(
                $res_item->attributes->title->stringForDisplay,
                text: new AlfredSFItemText(
                    copy: $res_item->attributes->title->stringForDisplay,
                    largetype: $res_item->attributes->title->stringForDisplay,
                ),
                variables: ["rid" => $res_item->id],
                quicklookurl: "https://music.apple.com/",
                icon: getRecommendationRowIcon($rec_obj, $res_item, $cache_dir),
            );
        }
    }

    $cache_config =
        $cache_duration_time != null
            ? new AlfredSFCache($cache_duration_time, true)
            : null;
    return new AlfredSF(
        items: $items,
        cache: $cache_config,
        variables: ["cache_file_name" => $filename],
    );
}

/**
 * Read a cached recommendation JSON file and build the Alfred detail list for a recommendation row.
 *
 * @param string $rid
 * @param string $cache_dir
 * @param string $cache_file_name Use cache file name to avoid get other file when refresh with loosereload.
 * @return AlfredSF
 */
function getRecRowDetailAlfred(
    string $rid,
    string $cache_dir,
    string $cache_file_name,
): AlfredSF {
    $cache_path =
        $cache_dir .
        DIRECTORY_SEPARATOR .
        "Music-Recommend" .
        DIRECTORY_SEPARATOR .
        $cache_file_name;
    $rec_data = file_get_contents($cache_path) |> json_decode(...);
    $rec_row = $rec_data->resources->{"personal-recommendation"}->{$rid};
    $rec_row_data = $rec_row->relationships->contents->data;
    $items = [];
    $rec_res = $rec_data->resources;
    foreach ($rec_row_data as $item) {
        if (
            array_any(
                $rec_row->attributes->resourceTypes,
                fn($v) => ResourcesType::tryFrom($v) !== null,
            )
        ) {
            $res_type = ResourcesType::tryFrom($item->type);
            $item = $rec_res->{$res_type->value}->{$item->id};
            if ($res_type === ResourcesType::Albums) {
                $items[] = new AlfredSFItem(
                    $item->attributes->name,
                    subtitle: $item->attributes->artistName .
                        " - " .
                        $item->attributes->releaseDate,
                    arg: $item->attributes->url,
                    icon: getCachedArtworkIcon($item, $res_type, $cache_dir),
                );
            } elseif ($res_type === ResourcesType::Playlists) {
                $items[] = new AlfredSFItem(
                    $item->attributes->name,
                    subtitle: getPlaylistSubtitle($item),
                    arg: $item->attributes->url,
                    icon: getCachedArtworkIcon($item, $res_type, $cache_dir),
                );
            }
        }
    }
    return new AlfredSF($items);
}
