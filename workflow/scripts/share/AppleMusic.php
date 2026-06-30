<?php
require_once __DIR__ . "/LocalCache.php";

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

// Codex TODO 填写（替换）URL 中的模版
// 输入：缩略图大小，URL 模版 例如 https://is1-ssl.mzstatic.com/image/thumb/Music69/v4/13/2e/c3/132ec362-7577-8f63-cbad-162f1e61fe89/075679916068.jpg/{w}x{h}bb.{f}
// 输出：替换后的 URL
function fillThumbTemplate(string $url_template, string $px_size): string
{
    $extension = "jpg";
    if (preg_match("/\\.([A-Za-z0-9]+)\\/\\{w\\}/", $url_template, $matches)) {
        $extension = strtolower($matches[1]);
    } else {
        $path = parse_url($url_template, PHP_URL_PATH) ?: "";
        $pathBeforeTemplate = strstr($path, "/{w}", true);
        $candidate = pathinfo($pathBeforeTemplate ?: $path, PATHINFO_EXTENSION)
            |> strtolower(...)
        ;
        if ($candidate !== "") {
            $extension = $candidate;
        }
    }

    return str_replace(
        ["{w}", "{h}", "{f}"],
        [$px_size, $px_size, $extension],
        $url_template,
    );
}

// Codex TODO 清洗对象中不显示的类型
// 输入：Music 推荐对象
// 输出：处理后的 Music 推荐对象
// 理由：剔除不需要显示的项目，防止引入获取不到所需字段的项目（例如 Library 系列项目）
function clearUnUseItem(object $rec): object
{
    if (!isset($rec->resources) || !is_object($rec->resources)) {
        return $rec;
    }

    $allowedResourceTypes = array_map(
        fn(ResourcesType $type): string => $type->value,
        ResourcesType::cases(),
    );
    $displayResourceTypes = [
        ResourcesType::Albums->value,
        ResourcesType::Playlists->value,
    ];

    foreach (get_object_vars($rec->resources) |> array_keys(...) as $type) {
        if (!in_array($type, $allowedResourceTypes, true)) {
            unset($rec->resources->{$type});
        }
    }

    $personalType = ResourcesType::PersonalRecommendation->value;
    if (
        !isset($rec->resources->{$personalType}) ||
        !is_object($rec->resources->{$personalType})
    ) {
        return $rec;
    }

    foreach (
        get_object_vars($rec->resources->{$personalType})
        as $rid => $row
    ) {
        $resourceTypes =
            isset($row->attributes->resourceTypes) &&
            is_array($row->attributes->resourceTypes)
                ? $row->attributes->resourceTypes
                : [];
        $row->attributes->resourceTypes = array_filter(
            $resourceTypes,
            fn($type): bool => is_string($type) &&
                in_array($type, $displayResourceTypes, true),
        )
            |> array_values(...)
        ;

        $contents =
            isset($row->relationships->contents->data) &&
            is_array($row->relationships->contents->data)
                ? $row->relationships->contents->data
                : [];
        $row->relationships->contents->data = array_values(
            array_filter($contents, function ($item) use (
                $rec,
                $displayResourceTypes,
            ): bool {
                if (
                    !isset($item->type, $item->id) ||
                    !in_array($item->type, $displayResourceTypes, true)
                ) {
                    return false;
                }
                return isset($rec->resources->{$item->type}) &&
                    is_object($rec->resources->{$item->type}) &&
                    isset($rec->resources->{$item->type}->{$item->id});
            }),
        );

        if (
            $row->attributes->resourceTypes === [] ||
            $row->relationships->contents->data === []
        ) {
            unset($rec->resources->{$personalType}->{$rid});
        }
    }

    if (isset($rec->data) && is_array($rec->data)) {
        $rec->data = array_filter(
            $rec->data,
            fn($item): bool => isset($item->id, $item->type) &&
                $item->type === $personalType &&
                isset($rec->resources->{$personalType}->{$item->id}),
        )
            |> array_values(...)
        ;
    }

    return $rec;
}

// Codex TODO 获取整行的推荐对象的封面图
// 输入：一个推荐行
// 输出：字典嵌套数组：项目类型（例如 Albums）嵌套元素为“键：项目 ID，值：拼接后的图片路径”
// 没有的就下载
// 示例：
// [
//   "albums" => [
//     "11111" => "~/path/to"
//   ]
// ]
function getRowThumb(
    object $rec_row,
    ?object $resources,
    string $cacheDir,
    string $px_size = "256",
): array {
    $result = [];
    $requests = [];
    $requestKeys = [];
    $contents =
        isset($rec_row->relationships->contents->data) &&
        is_array($rec_row->relationships->contents->data)
            ? $rec_row->relationships->contents->data
            : [];

    foreach ($contents as $item) {
        if (!isset($item->type, $item->id)) {
            continue;
        }
        $resType = ResourcesType::tryFrom($item->type);
        if (
            $resType !== ResourcesType::Albums &&
            $resType !== ResourcesType::Playlists
        ) {
            continue;
        }

        $resource = null;
        if (
            $resources !== null &&
            isset($resources->{$resType->value}) &&
            is_object($resources->{$resType->value}) &&
            isset($resources->{$resType->value}->{$item->id})
        ) {
            $resource = $resources->{$resType->value}->{$item->id};
        } elseif (isset($item->attributes->artwork)) {
            $resource = $item;
        }

        if (!isset($resource->attributes->artwork->url)) {
            continue;
        }

        $requestKeys[] = [
            "type" => $resType->value,
            "id" => (string) $item->id,
        ];
        $requests[] = [
            "url" => fillThumbTemplate(
                (string) $resource->attributes->artwork->url,
                $px_size,
            ),
            "type" => $resType->value,
            "id" => (string) $item->id,
            "size" => $px_size,
        ];
    }

    foreach (cacheFilesParallel($requests, $cacheDir) as $index => $path) {
        if (!is_string($path) || !isset($requestKeys[$index])) {
            continue;
        }
        $result[$requestKeys[$index]["type"]][
            $requestKeys[$index]["id"]
        ] = $path;
    }

    return $result;
}

function createHead4Thumb(
    array $thumbPaths,
    string $outputPath,
    int $tileSize = 256,
): bool {
    $validPaths = array_values(
        array_filter(
            $thumbPaths,
            fn($path): bool => is_string($path) && is_file($path),
        ),
    );
    if ($validPaths === []) {
        return false;
    }

    $canvasSize = $tileSize * 2;
    $canvas = imagecreatetruecolor($canvasSize, $canvasSize);
    $background = imagecolorallocate($canvas, 245, 245, 245);
    imagefill($canvas, 0, 0, $background);

    foreach (array_slice($validPaths, 0, 4) as $index => $path) {
        $source = @imagecreatefromstring((string) file_get_contents($path));
        if ($source === false) {
            continue;
        }

        $width = imagesx($source);
        $height = imagesy($source);
        $cropSize = min($width, $height);
        $srcX = (int) floor(($width - $cropSize) / 2);
        $srcY = (int) floor(($height - $cropSize) / 2);
        $dstX = ($index % 2) * $tileSize;
        $dstY = intdiv($index, 2) * $tileSize;

        imagecopyresampled(
            $canvas,
            $source,
            $dstX,
            $dstY,
            $srcX,
            $srcY,
            $tileSize,
            $tileSize,
            $cropSize,
            $cropSize,
        );
    }

    $dir = dirname($outputPath);
    if (!is_dir($dir)) {
        mkdir($dir, recursive: true);
    }
    $saved = imagejpeg($canvas, $outputPath, 90);

    return $saved;
}

// Codex TODO 缓存每个推荐列表（每行）的前 4 张封面图，并拼接为一张大图
// 输入：Music 对象
// 输出：字典嵌套数组：键：推荐 ID，值：拼接后的图片路径
// 示例：
function genHead4Thumb(object $rec, string $cacheDir): array
{
    $result = [];
    $artworkCacheDir = $cacheDir . DIRECTORY_SEPARATOR . "Artwork";
    $headCacheDir = $cacheDir . DIRECTORY_SEPARATOR . "Head4";
    $personalType = ResourcesType::PersonalRecommendation->value;

    if (
        !isset($rec->data, $rec->resources->{$personalType}) ||
        !is_array($rec->data)
    ) {
        return $result;
    }

    foreach ($rec->data as $dataItem) {
        if (
            !isset(
                $dataItem->id,
                $rec->resources->{$personalType}->{$dataItem->id},
            )
        ) {
            continue;
        }

        $rid = (string) $dataItem->id;
        $row = $rec->resources->{$personalType}->{$rid};
        $thumbGroups = getRowThumb($row, $rec->resources, $artworkCacheDir);
        $thumbPaths = [];
        foreach ($thumbGroups as $items) {
            foreach ($items as $path) {
                $thumbPaths[] = $path;
                if (count($thumbPaths) >= 4) {
                    break 2;
                }
            }
        }
        if ($thumbPaths === []) {
            continue;
        }

        $headPath =
            $headCacheDir .
            DIRECTORY_SEPARATOR .
            sanitizeCacheNamePart($rid) .
            ".jpg";
        if (is_file($headPath) && is_readable($headPath)) {
            $result[$rid] = $headPath;
            continue;
        }

        if (createHead4Thumb($thumbPaths, $headPath)) {
            $result[$rid] = $headPath;
        }
    }

    return $result;
}
