<?php

function only_visible_matches($value, $key){
    if($value['visible']){
        return TRUE;
    } else {
        return FALSE;
    }
}

if(isset($_GET['check'])){
    echo 'true';
} else if(isset($_GET['matches'])){
    $matches = json_decode(file_get_contents('admin/data/matches.json'), true);
    $filtered = array_filter($matches, 'only_visible_matches', ARRAY_FILTER_USE_BOTH);
    echo json_encode($filtered, JSON_PRETTY_PRINT);
} else {
    echo 'false';
}

?>