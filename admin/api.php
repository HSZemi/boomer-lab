<?php

function generateRandomString($length = 6) {
    $characters = 'abcdefghijkmnopqrstuvwxyzABCDEFGHJKLMNOPQRSTUVWXYZ';
    $charactersLength = strlen($characters);
    $randomString = '';
    for ($i = 0; $i < $length; $i++) {
        $randomString .= $characters[rand(0, $charactersLength - 1)];
    }
    return $randomString;
}

function createJsonFile($id, $round, $playerName, $opponentName, $numberOfGames){
    $filename = 'data/'.$id.'.json';
    $value = array(
        'id' => $id,
        'round' => $round,
        'player' => $playerName,
        'opponent' => $opponentName,
        'number_of_games' => $numberOfGames,
        'files' => Array(),
        'locked' => false,
    );
    file_put_contents($filename, json_encode($value, JSON_PRETTY_PRINT));
}

$data = json_decode(file_get_contents('php://input'), true);
if(isset($data['creatematch'])){
    $player1code = generateRandomString();
    $player2code = generateRandomString();
    $parameters = $data['creatematch'];

    createJsonFile($player1code, $parameters['round'], $parameters['player1'], $parameters['player2'], $parameters['number_of_games']);
    createJsonFile($player2code, $parameters['round'], $parameters['player2'], $parameters['player1'], $parameters['number_of_games']);

    $filename = 'data/matches.json';
    $matches = json_decode(file_get_contents($filename), true);
    $matches[] = array(
        'round' => $parameters['round'],
        'player1' => $parameters['player1'],
        'player2' => $parameters['player2'],
        'player1code' => $player1code,
        'player2code' => $player2code,
        'number_of_games' => $parameters['number_of_games'],
        'visible' => false,
    );
    file_put_contents($filename, json_encode($matches, JSON_PRETTY_PRINT));

    echo json_encode(array('player1code' => $player1code, 'player2code' => $player2code));
} else {
    echo 'false';
}

?>