<?php

class AccountConfig
{
    public function __construct(
        public string $authToken,
        public string $mediaToken,
    ) {}
}

function RequestMusicJson(
    AccountConfig $account,
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
            "media-user-token: " . $account->mediaToken,
            "origin: https://music.apple.com",
            "Authorization: Bearer " . $account->authToken,
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
