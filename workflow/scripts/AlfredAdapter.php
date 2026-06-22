<?php
require_once __DIR__ . "/share/AppleMusic.php";
require_once __DIR__ . "/alfred/ScriptFilterType.php";

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

    file_put_contents($cache_file_path, $rec_json);
    $rec_obj = json_decode($rec_json);
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
            if ($res_type === ResourcesType::Albums) {
                $items[] = new AlfredSFItem(
                    $rec_res->{$res_type->value}->{$item->id}->attributes->name,
                );
            }
        }
    }
    return new AlfredSF($items);
}
