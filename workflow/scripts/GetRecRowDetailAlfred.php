<?php
require_once __DIR__ . "/share/GetMusicList.php";
require_once __DIR__ . "/share/MusicRecommandType.php";
require_once __DIR__ . "/alfred/ScriptFilterType.php";

function GetRecRowDetailAlfred(
    string $rid,
    string $alfred_cache_dir,
    string $cache_file_name,
): AlfredSF {
    $cache_path =
        $alfred_cache_dir .
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
                fn($v) => ResType::tryFrom($v) !== null,
            )
        ) {
            $res_type = ResType::tryFrom(
                $item->type,
            );
            if ($res_type === ResType::Albums) {
                $items[] = new AlfredSFItem(
                    $rec_res->{$res_type->value}->{$item->id}->attributes->name,
                );
            }
        }
    }
    return new AlfredSF($items);
}
