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

// Codex TODO 填写（替换）URL 中的模版
// 输入：缩略图大小，URL 模版 例如 https://is1-ssl.mzstatic.com/image/thumb/Music69/v4/13/2e/c3/132ec362-7577-8f63-cbad-162f1e61fe89/075679916068.jpg/{w}x{h}bb.{f}
// 输出：替换后的 URL
function fillThumbTemplate(string $url_template, string $px_size): string
{
    // 处理 w 和 h 模版（宽与高），正方形。f 为原扩展名
    // 文件扩展名与原文件相同
	return value;
}

// Codex TODO 清洗对象中不显示的类型
// 输入：Music 推荐对象
// 输出：处理后的 Music 推荐对象
// 理由：剔除不需要显示的项目，防止引入获取不到所需字段的项目（例如 Library 系列项目）
function clearUnUseItem(object $rec): object
{
    // 根据 ResourcesType 清理 Resource 中的字段
    // 清理角度：1. Resource 中除 ResourcesType 的类型；2. PersonalRecommendation 推荐列表中的非 ResourcesType 的元素。
    // 直接移除不需要的元素
    return value;
}

// Codex TODO 获取整行的推荐对象的封面图
// 输入：一个推荐行
// 输出：字典嵌套数组：项目类型（例如 Albums）嵌套元素为“键：项目 ID，值：拼接后的图片路径”
// 示例：
// [
//   "albums" => [
//     "11111" => "~/path/to"
//   ]
// ]
function getRowThumb(object $rec_row): array
{
    // code
    // 调用 fillThumbTemplate
    return value;
}


// Codex TODO 缓存每个推荐列表（每行）的前 4 张封面图，并拼接为一张大图
// 输入：Music 对象
// 输出：字典嵌套数组：键：推荐 ID，值：拼接后的图片路径
// 示例：
function genHead4Thumb(object $rec): array
{
    // 调用 fillThumbTemplate
    // 使用 GD 拼图
    // 返回示例：
    // [
    //   "11111" => "~/path/to"
    // ]

    return value;
}
