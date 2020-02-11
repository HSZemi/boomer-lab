<?php

function generateRandomString($length = 6)
{
    $characters = 'abcdefghijkmnopqrstuvwxyzABCDEFGHJKLMNOPQRSTUVWXYZ';
    $charactersLength = strlen($characters);
    $randomString = '';
    for ($i = 0; $i < $length; $i++) {
        $randomString .= $characters[rand(0, $charactersLength - 1)];
    }
    return $randomString;
}

function addMatch($round, $player1name, $player2name, $player1code, $player2code, $numberOfGames)
{
    $filename = 'data/matches.json';
    $matches = json_decode(file_get_contents($filename), true);
    $matches[] = array(
        'round' => $round,
        'number_of_games' => $numberOfGames,
        'player1name' => $player1name,
        'player2name' => $player2name,
        'player1code' => $player1code,
        'player2code' => $player2code,
        'player1recs' => array(),
        'player2recs' => array(),
        'player1zip' => null,
        'player2zip' => null,
        'locked' => false,
        'visible' => false,
    );
    file_put_contents($filename, json_encode($matches, JSON_PRETTY_PRINT));
}

$data = json_decode(file_get_contents('php://input'), true);
if (isset($data['creatematch'])) {
    $player1code = generateRandomString();
    $player2code = generateRandomString();
    $parameters = $data['creatematch'];

    $round = $parameters['round'];
    $player1name = $parameters['player1'];
    $player2name = $parameters['player2'];
    $numberOfGames = $parameters['number_of_games'];
    addMatch($round, $player1name, $player2name, $player1code, $player2code, $numberOfGames);

    echo json_encode(array('player1code' => $player1code, 'player2code' => $player2code));
} else if (isset($data['lock'])) {
    $player1code = $data['lock']['player1code'];
    $player2code = $data['lock']['player2code'];
    $filename = 'data/matches.json';
    $matches = json_decode(file_get_contents($filename), true);
    foreach ($matches as &$match) {
        if ($match['player1code'] === $player1code && $match['player2code'] === $player2code) {
            $match['locked'] = true;
        }
    }
    file_put_contents($filename, json_encode($matches, JSON_PRETTY_PRINT));
    echo '{"success":true}';
} else if (isset($data['publish'])) {
    $player1code = $data['publish']['player1code'];
    $player2code = $data['publish']['player2code'];
    $filename = 'data/matches.json';
    $matches = json_decode(file_get_contents($filename), true);
    foreach ($matches as &$match) {
        if ($match['player1code'] === $player1code && $match['player2code'] === $player2code) {
            $match['visible'] = true;
        }
    }
    file_put_contents($filename, json_encode($matches, JSON_PRETTY_PRINT));
    echo '{"success":true}';
} else {
    echo 'false';
}

?>