<?php
require_once getenv("alfred_preferences") .
    DIRECTORY_SEPARATOR .
    "workflows" .
    DIRECTORY_SEPARATOR .
    getenv("alfred_workflow_uid") .
    DIRECTORY_SEPARATOR .
    "scripts" .
    DIRECTORY_SEPARATOR .
    "AlfredAdapter.php";

$rid = getenv("rid");
$cache_dir = getenv("alfred_workflow_cache");
$cache_file_name = getenv("cache_file_name");

$result = getRecRowDetailAlfred($rid, $cache_dir, $cache_file_name)
    |> (fn($x) => json_encode($x, JSON_UNESCAPED_UNICODE))
;
echo $result;
