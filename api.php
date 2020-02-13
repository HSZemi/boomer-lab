<?php

function only_visible_matches($value, $key){
    if($value['visible']){
        return TRUE;
    } else {
        return FALSE;
    }
}

if(isset($_GET['check'])){
    $code = $_GET['check'];
    if(1 === preg_match('/[a-zA-Z]+/', $code)){
        $filename = 'admin/data/'.$code.'.json';
        if(file_exists($filename)){
            $match = json_decode(file_get_contents($filename), true);
            echo json_encode(array('exists' => true, 'match' => $match));
        } else {
            echo '{"exists":false}';
        }
    } else {
        echo '{"exists":false}';
    }
} else if(isset($_GET['matches'])){
    $matches = json_decode(file_get_contents('admin/data/matches.json'), true);
    $filtered = array_filter($matches, 'only_visible_matches', ARRAY_FILTER_USE_BOTH);
    echo json_encode($filtered, JSON_PRETTY_PRINT);
} else {
    echo 'false';
}

?>