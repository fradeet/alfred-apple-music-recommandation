<?php

enum RecKindType: string
{
    case MusicRecommendations = "music-recommendations";
    // TODO more
}

enum ResType: string
{
    case PersonalRecommendation = "personal-recommendation";
    case Albums = "albums";
    case Playlists = "playlists";
    // TODO Artist, Stations, Library-*
}
