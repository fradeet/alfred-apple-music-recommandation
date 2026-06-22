<?php

function GetRecommendList(object $recObj): array
{
    $result = [];
    foreach ($recObj->data as $item) {
        try {
            // Disable non-title item
            if (property_exists($recObj->resources->{'personal-recommendation'}->{$item->id}->attributes, "title")) {
                $result[] = GetRecommandationRow($recObj, (string) $item->id);
            }
        } catch (Exception $e) {
            continue;
        }
    }
    return $result;
}

function GetRecommandationRow(object $recObj, string $rid): array
{
    $item = $recObj->resources->{'personal-recommendation'}->{$rid};
    $element = [
        "id" => (string) $item->id,
        "type" => (string) $item->type,
        "href" => (string) $item->href,
        "title" => (string) $item->attributes->title->stringForDisplay,
        "kind" => (string) $item->attributes->kind,
        "version" => (string) $item->attributes->version,
        "nextUpdateDate" => (string) $item->attributes->nextUpdateDate,
        "resourceTypes" => (array) $item->attributes->resourceTypes,
        "contents" => (array) (function (object $recObj, array $datalist) {
            $contents = [];
            foreach ($datalist as $item) {
                 $result = GetElementInRow($recObj, $item->id, $item->type);
                 if ($result !== 1) {
                    $contents[] = $result;
                 }
            }
            return $contents;
        })($recObj, $item->relationships->contents->data),
    ];
    return $element;
}

function GetElementInRow(object $recObj, string $cid, string $type): array|int
{
    $item = $recObj->resources->{$type}->{$cid};

    if ($type === "albums") {
        return GetAlbumInfo($item);
    } else if ($type === "library-albums") {
        // return GetLibAlbumInfo($item);  // Can't get play url.
        return 1;
    } else if ($type === "playlists") {
        return GetPlaylistInfo($item);
    } else if ($type === "library-playlists") {
        // return GetLibPlaylistInfo($item);  // Can't get play url.
        return 1;
    } else {
        return 1;
    }
    // TODO Artist, Station
}

function GetPlaylistInfo(object $item): array
{
    return [
        "id" => (int) $item->id,
        "name" => (string) $item->attributes->name,
        "type" => (string) $item->type,
        "url" => (string) $item->attributes->url,
        "href" => (string) $item->href,
        "artwork" => [
            "bgColor" => (string) $item->attributes->artwork->bgColor,
            "url" => (string) $item->attributes->artwork->url,
            "height" => (int) $item->attributes->artwork->height,
            "width" => (int) $item->attributes->artwork->width,
        ],
        "meta" => [
            "curatorName" => (string) $item->attributes->curatorName,
            "playlistType" => (string) $item->attributes->playlistType,
            // "description" => (array) $item->attributes->description,
        ]
    ];
}

function GetLibPlaylistInfo(object $item): array
{
    return [
        "id" => (int) $item->id,
        "name" => (string) $item->attributes->name,
        "type" => (string) $item->type,
        "href" => (string) $item->href,
        "artwork" => [
            "url" => (string) $item->attributes->artwork->url,
            "height" => (int) $item->attributes->artwork->height,
            "width" => (int) $item->attributes->artwork->width,
        ],
        "meta" => [
            // "description" => (array) $item->attributes->description,
        ]
    ];
}

function GetAlbumInfo(object $item): array
{
    return [
        "id" => (int) $item->id,
        "name" => (string) $item->attributes->name,
        "type" => (string) $item->type,
        "url" => (string) $item->attributes->url,
        "href" => (string) $item->href,
        "artwork" => [
            "bgColor" => (string) $item->attributes->artwork->bgColor,
            "url" => (string) $item->attributes->artwork->url,
            "height" => (int) $item->attributes->artwork->height,
            "width" => (int) $item->attributes->artwork->width,
        ],
        "meta" => [
            "artistName" => (string) $item->attributes->artistName,
            "releaseDate" => (string) $item->attributes->releaseDate,
            "genreNames" => (array) $item->attributes->genreNames,
        ],
    ];
}

function GetLibAlbumInfo(object $item): array
{
    return [
        "id" => (int) $item->id,
        "name" => (string) $item->attributes->name,
        "type" => (string) $item->type,
        "href" => (string) $item->href,
        "artwork" => [
            "url" => (string) $item->attributes->artwork->url,
            "height" => (int) $item->attributes->artwork->height,
            "width" => (int) $item->attributes->artwork->width,
        ],
        "meta" => [
            "artistName" => (string) $item->attributes->artistName,
            "releaseDate" => (string) $item->attributes->releaseDate,
            "genreNames" => (array) $item->attributes->genreNames,
        ],
    ];
}
