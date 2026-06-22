<?php

class AppleMusicAccountConfig
{
    public function __construct(
        public string $auth_token,
        public string $media_token,
    ) {}
}

function getRecommandation(
    AppleMusicAccountConfig $account,
    ?string $debug_file_path = null,
): string {
    $ch = curl_init();
    curl_setopt_array($ch, [
        CURLOPT_URL => "https://amp-api.music.apple.com/v1/me/recommendations?art%5Burl%5D=f&displayFilter%5Bkind%5D=MusicCircleCoverShelf%2CMusicConcertsEmptyShelf%2CMusicCoverGrid%2CMusicCoverShelf%2CMusicNotesHeroShelf%2CMusicSocialCardShelf%2CMusicSuperHeroShelf&extend=editorialArtwork%2CeditorialVideo%2CplainEditorialCard%2CplainEditorialNotes&extend%5Bplaylists%5D=artistNames&extend%5Bstations%5D=airTime%2CsupportsAirTimeUpdates&fields%5Bartists%5D=name%2Cartwork%2Curl&format%5Bresources%5D=map&include%5Balbums%5D=artists&include%5Blibrary-playlists%5D=catalog&include%5Bpersonal-recommendation%5D=primary-content&include%5Bstations%5D=radio-show&meta%5Bstations%5D=inflectionPoints&name=listen-now&omit%5Bresource%5D=autos&platform=web&types=activities%2Calbums%2Capple-curators%2Cartists%2Cconcerts%2Ccurators%2Ceditorial-items%2Clibrary-albums%2Clibrary-playlists%2Cmusic-movies%2Cmusic-videos%2Cplaylists%2Csocial-profiles%2Csocial-upsells%2Csongs%2Cstations%2Ctv-episodes%2Ctv-shows%2Cuploaded-audios%2Cuploaded-videos&with=friendsMix%2Clibrary%2Csocial&timezone=%2B08%3A00",
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_ENCODING => "",
        CURLOPT_MAXREDIRS => 10,
        CURLOPT_TIMEOUT => 0,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
        CURLOPT_CUSTOMREQUEST => "GET",
        CURLOPT_HTTPHEADER => [
            "media-user-token: " . $account->media_token,
            "origin: https://music.apple.com",
            "Authorization: Bearer " . $account->auth_token,
        ],
    ]);
    $response = curl_exec($ch);
    if (curl_errno($ch)) {
        throw new Exception(
            json_encode(
                [
                    "success" => false,
                    "message" => curl_error($ch),
                    "code" => curl_errno($ch),
                ],
                JSON_UNESCAPED_UNICODE,
            ),
        );
    } else {
        if ($debug_file_path) {
            file_put_contents($debug_file_path, $response);
        }
        return $response;
    }
}

function getRecList(object $rec_obj): array
{
    $result = [];
    foreach ($rec_obj->data as $item) {
        try {
            // Disable non-title item
            if (
                property_exists(
                    $rec_obj->resources->{'personal-recommendation'}
                        ->{$item->id}->attributes,
                    "title",
                )
            ) {
                $result[] = getRecRow($rec_obj, (string) $item->id);
            }
        } catch (Exception $e) {
            continue;
        }
    }
    return $result;
}

function getRecRow(object $recObj, string $rid): array
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
                $result = getElementInRow($recObj, $item->id, $item->type);
                if ($result !== 1) {
                    $contents[] = $result;
                }
            }
            return $contents;
        })($recObj, $item->relationships->contents->data),
    ];
    return $element;
}

function getElementInRow(object $recObj, string $cid, string $type): array|int
{
    $item = $recObj->resources->{$type}->{$cid};

    if ($type === "albums") {
        return getAlbumInfo($item);
    } elseif ($type === "library-albums") {
        // return GetLibAlbumInfo($item);  // Can't get play url.
        return 1;
    } elseif ($type === "playlists") {
        return getPlaylistInfo($item);
    } elseif ($type === "library-playlists") {
        // return GetLibPlaylistInfo($item);  // Can't get play url.
        return 1;
    } else {
        return 1;
    }
    // TODO Artist, Station
}

function getPlaylistInfo(object $item): array
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
        ],
    ];
}

function getLibPlaylistInfo(object $item): array
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
        ],
    ];
}

function getAlbumInfo(object $item): array
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

function getLibAlbumInfo(object $item): array
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
/**
 * The Apple Music JSON recommendation internal type, add more when implemented function. JSON Path: `resources[personal-recommendation][][attributes][kind]`
 */
enum RecommandKindType: string
{
    case MusicRecommendations = "music-recommendations";
    // TODO more
}
/**
 * The Apple Music interal resource type, add more when implemented function. JSON Path: `resources[type]`
 */
enum ResourcesType: string
{
    case PersonalRecommendation = "personal-recommendation";
    case Albums = "albums";
    case Playlists = "playlists";
    // TODO Artist, Stations, Library-*
}
