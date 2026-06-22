<?php
require_once __DIR__ . "/share/GetMusicList.php";
require_once __DIR__ . "/share/GetMusicRecommandation.php";
require_once __DIR__ . "/share/MusicRecommandType.php";
require_once __DIR__ . "/alfred/ScriptFilterType.php";

function InitRecPageAlfred(
    string $auth_token,
    string $media_token,
    string $alfred_cache_dir,
    int $cache_duration_time = 14400,
): AlfredSF {
    $rec_json = RequestMusicJson(new AccountConfig($auth_token, $media_token));
    $filename = time() . ".json";
    $cache_file_path =
        $alfred_cache_dir .
        DIRECTORY_SEPARATOR .
        "Music-Recommend" .
        DIRECTORY_SEPARATOR .
        $filename;
    $dir = dirname($cache_file_path);
    if (!is_dir($dir)) {
        mkdir($dir, recursive: true);
    }

    file_put_contents($cache_file_path, $rec_json);
    $rec_obj = json_decode($rec_json);
    $items = [];
    $rec_res = $rec_obj->resources->{"personal-recommendation"};
    foreach ($rec_obj->data as $item) {
        $res_item = $rec_res->{$item->id};
        // Filter out items without a title
        if (
            property_exists($res_item->attributes, "title") &&
            RecKindType::tryFrom($res_item->attributes->kind) !== null
        ) {
            $items[] = new AlfredSFItem(
                title: $res_item->attributes->title->stringForDisplay,
                text: new AlfredSFItemText(
                    copy: $res_item->attributes->title->stringForDisplay,
                    largetype: $res_item->attributes->title->stringForDisplay,
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
}
