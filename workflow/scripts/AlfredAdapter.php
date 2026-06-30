<?php
declare(strict_types=1);
require_once __DIR__ . "/share/AppleMusic.php";
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

function initRecPageAlfred(
    string $auth_token,
    string $media_token,
    string $cache_dir,
    int $cache_duration_time = 14400,
): AlfredSF {
    try {
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
        $cache_thumb_path =
            $cache_dir . DIRECTORY_SEPARATOR . "Cache" . DIRECTORY_SEPARATOR;

        $dir = dirname($cache_file_path);
        if (!is_dir($dir)) {
            mkdir($dir, recursive: true);
        }
        $rec_obj = json_decode($rec_json) |> clearUnUseItem(...);
        $head_4_paths = genHead4Thumb($rec_obj, $cache_thumb_path);
        file_put_contents(
            $cache_file_path,
            json_encode($rec_obj, JSON_UNESCAPED_UNICODE),
        );
        $items = [];
        $rec_res = $rec_obj->resources->{"personal-recommendation"};
        foreach ($rec_obj->data as $item) {
            $res_item = $rec_res->{$item->id};
            // Filter out items without a title
            if (
                isset($res_item->attributes->title->stringForDisplay) &&
                RecommandKindType::tryFrom($res_item->attributes->kind) !== null
            ) {
                $items[] = new AlfredSFItem(
                    $res_item->attributes->title->stringForDisplay,
                    icon: isset($head_4_paths[$res_item->id])
                        ? new AlfredSFItemIcon($head_4_paths[$res_item->id])
                        : null,
                    text: new AlfredSFItemText(
                        copy: $res_item->attributes->title->stringForDisplay,
                        largetype: $res_item->attributes->title
                            ->stringForDisplay,
                    ),
                    variables: ["rid" => $res_item->id],
                    quicklookurl: "https://music.apple.com/",
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
    } catch (Exception $e) {
        fwrite(
            STDERR,
            sprintf("%s\n", $e),
        );
        return RETURN_ERROR_ALFRED;
    }
}

/**
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
    $thumb_cache_path =
        $cache_dir .
        DIRECTORY_SEPARATOR .
        "Cache" .
        DIRECTORY_SEPARATOR .
        "Artwork";
    $row_thumb_path_map = getRowThumb($rec_row, $rec_res, $thumb_cache_path);

    foreach ($rec_row_data as $item) {
        if (
            // TODO Maybe can remove after clearUnUseItem
            array_any(
                $rec_row->attributes->resourceTypes,
                fn($v) => ResourcesType::tryFrom($v) !== null,
            )
        ) {
            $res_type = ResourcesType::tryFrom($item->type);
            if ($res_type === null) {
                continue;
            }
            $item = $rec_res->{$res_type->value}->{$item->id};
            if ($res_type === ResourcesType::Albums) {
                $items[] = new AlfredSFItem(
                    $item->attributes->name,
                    icon: isset(
                        $row_thumb_path_map[$res_type->value][$item->id],
                    )
                        ? new AlfredSFItemIcon(
                            $row_thumb_path_map[$res_type->value][$item->id],
                        )
                        : null,
                    subtitle: $item->attributes->artistName .
                        " - " .
                        $item->attributes->releaseDate,
                    arg: $item->attributes->url,
                );
            } elseif ($res_type === ResourcesType::Playlists) {
                $items[] = new AlfredSFItem(
                    $item->attributes->name,
                    icon: isset(
                        $row_thumb_path_map[$res_type->value][$item->id],
                    )
                        ? new AlfredSFItemIcon(
                            $row_thumb_path_map[$res_type->value][$item->id],
                        )
                        : null,
                    subtitle: trim(
                        ($item->attributes->curatorName ?? "") .
                            " - " .
                            ($item->attributes->playlistType ?? ""),
                        " -",
                    ),
                    arg: $item->attributes->url,
                );
            }
        }
    }
    return new AlfredSF($items);
}
