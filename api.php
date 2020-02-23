<?php

const FIVE_MB = 5 * 1024 * 1024;
function only_visible_matches($value, $key)
{
    if ($value['visible']) {
        return TRUE;
    } else {
        return FALSE;
    }
}

function validateConfig($config)
{
    for ($gameNumber = 0; $gameNumber < count($config); $gameNumber++) {
        for ($subGameNumber = 0; $subGameNumber < count($config[$gameNumber]); $subGameNumber++) {
            $filename = $config[$gameNumber][$subGameNumber];
            $found = false;
            for ($i = 0; $i < count($_FILES['recs']['name']); $i++) {
                $name = $_FILES['recs']['name'][$i];
                if ($name === $filename) {
                    $found = true;
                    break;
                }
            }
            if (!$found) {
                echo json_encode(array('success' => false, 'message' => 'Invalid data.'));
                die();
            }
        }
    }
}

function getStartTime($round)
{
    switch ($round) {
        case 'ro32':
            return 1584452220; // 2020-03-17 13:37:00 UTC
        case 'ro16':
            return 1584538620; // 2020-03-18 13:37:00 UTC
        case 'qf':
            return 1584625020; // 2020-03-19 13:37:00 UTC
        case 'sf':
            return 1584711420; // 2020-03-20 13:37:00 UTC
        case 'f':
            return 1584797820; // 2020-03-21 13:37:00 UTC
    }
    return 1584365820; // 2020-03-16 13:37:00 UTC
}

function processUploadedFiles($config, $match, $active_player)
{
    $filenames = array();
    for ($gameNumber = 0; $gameNumber < count($config); $gameNumber++) {
        for ($subGameNumber = 0; $subGameNumber < count($config[$gameNumber]); $subGameNumber++) {
            $filename = $config[$gameNumber][$subGameNumber];
            for ($i = 0; $i < count($_FILES['recs']['name']); $i++) {
                $name = $_FILES['recs']['name'][$i];
                if ($name === $filename) {
                    $target_filename = getTargetFilename($match, $active_player, $gameNumber, $subGameNumber);
                    $target_filepath = "admin/data/recs/{$target_filename}";
                    move_uploaded_file($_FILES['recs']['tmp_name'][$i], $target_filepath);
                    $filenames[] = $target_filename;
                }
            }
        }
    }

    $filenames = fillWithFakeGames($config, $match, $active_player, $filenames);

    $targetFilesize = getTargetFilesize($filenames);
    $paddedFilenames = padFilesizes($filenames, $targetFilesize);

    $round = $match['round'];
    setLastModifiedTimestamps($round, $paddedFilenames);

    $zipFileName = createZipFile($paddedFilenames, $match, $active_player);

    return array('filenames' => $filenames, 'zipfilename' => $zipFileName);
}

function getTargetFilesize($filenames)
{
    $maxFileSize = 0;
    foreach ($filenames as $filename) {
        $filepath = "admin/data/recs/{$filename}";
        $maxFileSize = max($maxFileSize, filesize($filepath));
    }
    $targetFilesize = FIVE_MB;
    while ($maxFileSize > $targetFilesize) {
        $targetFilesize += FIVE_MB;
    }
    return $targetFilesize;
}

function getTargetFilename($match, $active_player, $gameNumber, $subGameNumber)
{
    $round = $match['round'];
    $player1name = $match['player1name'];
    $player2name = $match['player2name'];
    $pov = array(0 => '', 1 => '', 2 => '');
    $pov[$active_player] = '_(PoV)';
    $gameNumberD = $gameNumber + 1;
    return "HC3-{$round}-{$gameNumberD}.{$subGameNumber}-{$player1name}{$pov[1]}-vs-{$player2name}{$pov[2]}.aoe2record";
}

function getZipFileName($match, $active_player)
{
    $round = $match['round'];
    $player1name = $match['player1name'];
    $player2name = $match['player2name'];
    $pov = array(0 => '', 1 => '', 2 => '');
    $pov[$active_player] = '_(PoV)';
    return "HC3-{$round}-{$player1name}{$pov[1]}-vs-{$player2name}{$pov[2]}.zip";
}

function setLastModifiedTimestamps($round, array $filenames)
{
    $starttime = getStartTime($round) + rand(3600, 3600 * 10);
    for ($i = 0; $i < count($filenames); $i++) {
        $targetTime = $starttime - $i * 120;
        $filename = $filenames[$i];
        $filepath = __DIR__ . "/admin/data/recs/{$filename}";
        touch($filepath, $targetTime);
    }
}

function fillWithFakeGames($config, $match, $active_player, $filenames)
{
    $numberOfGames = $match['number_of_games'];

    for ($gameNumber = count($config); $gameNumber < $numberOfGames; $gameNumber++) {
        $numberOfFiles = 1;
        if (rand(0, 99) < 10) {
            $numberOfFiles = 2;
        }
        for ($subGameNumber = 0; $subGameNumber < $numberOfFiles; $subGameNumber++) {
            $target_filename = getTargetFilename($match, $active_player, $gameNumber, $subGameNumber);
            $target_filepath = "admin/data/recs/{$target_filename}";
            $source_filepath = "admin/data/recs/{$filenames[0]}";
            copy($source_filepath, $target_filepath);
            $filenames[] = $target_filename;
        }
    }
    return $filenames;
}

function padFilesizes($filenames, $targetFilesize)
{
    $paddedFilenames = array();
    foreach ($filenames as $filename) {
        $filepath = "admin/data/recs/{$filename}";
        $copyFilename = str_replace('.aoe2record', '.se.aoe2record', $filename);
        $copyFilepath = "admin/data/recs/{$copyFilename}";
        copy($filepath, $copyFilepath);
        $currentSize = filesize($copyFilepath);
        $diff = $targetFilesize - $currentSize;
        $fp = fopen($copyFilepath, 'a');
        $patch = pack('VVC@' . ($diff - 4) . 'V', 1, $diff, 0xFE, 0);
        fwrite($fp, $patch);
        fclose($fp);
        $paddedFilenames[] = $copyFilename;
    }
    return $paddedFilenames;
}

function createZipFile($filenames, $match, $active_player)
{
    $zipFileName = getZipFileName($match, $active_player);
    $zipFilePath = "data/{$zipFileName}";
    $zipArchive = new ZipArchive();
    $zipArchive->open($zipFilePath, ZipArchive::CREATE | ZipArchive::OVERWRITE);
    foreach ($filenames as $filename) {
        $zipArchive->addFile("admin/data/recs/{$filename}", $filename);
        $zipArchive->setCompressionName($filename, ZipArchive::CM_DEFLATE);
    }
    $zipArchive->close();

    $round = $match['round'];
    $targetTime = getStartTime($round);
    $filepath = __DIR__ . "/{$zipFilePath}";
    touch($filepath, $targetTime);

    return $zipFileName;
}

function getMatchIndex($matches, $code)
{
    for ($i = 0; $i < count($matches); $i++) {
        if ($matches[$i]['player1code'] === $code || $matches[$i]['player2code'] === $code) {
            return $i;
        }
    }
    return null;
}

if (isset($_GET['check'])) {
    $code = $_GET['check'];
    if (1 === preg_match('/[a-zA-Z]+/', $code)) {
        $filename = 'admin/data/matches.json';
        $matches = json_decode(file_get_contents($filename), true);

        $matchindex = getMatchIndex($matches, $code);
        if ($matchindex === null) {
            echo '{"exists":false}';
        } else {
            $match = array();
            $match['round'] = $matches[$matchindex]['round'];
            $match['number_of_games'] = $matches[$matchindex]['number_of_games'];
            $match['code'] = $code;
            if ($code === $matches[$matchindex]['player1code']) {
                $match['player'] = $matches[$matchindex]['player1name'];
                $match['opponent'] = $matches[$matchindex]['player2name'];
            } else if ($code === $matches[$matchindex]['player2code']) {
                $match['player'] = $matches[$matchindex]['player2name'];
                $match['opponent'] = $matches[$matchindex]['player1name'];
            }
            echo json_encode(array('exists' => true, 'match' => $match));
        }
    } else {
        echo '{"exists":false}';
    }
} else if (isset($_GET['matches'])) {
    $matches = json_decode(file_get_contents('admin/data/matches.json'), true);
    $filtered = array_values(array_filter($matches, 'only_visible_matches', ARRAY_FILTER_USE_BOTH));
    echo json_encode($filtered, JSON_PRETTY_PRINT);
} else if (isset($_POST['code'])) {
    $code = $_POST['code'];
    if (1 !== preg_match('/[a-zA-Z]+/', $code)) {
        echo '{"success":false,"message":"Invalid Code."}';
        die();
    }

    $filename = 'admin/data/matches.json';
    $matches = json_decode(file_get_contents($filename), true);

    $matchindex = getMatchIndex($matches, $code);
    if ($matchindex === null) {
        echo '{"success":false,"message":"Invalid Code. Maybe a typo?"}';
        die();
    }

    if ($matches[$matchindex]['locked']) {
        echo '{"success":false,"message":"Upload is already locked."}';
        die();
    }

    $config = json_decode($_POST['config']);
    validateConfig($config);

    $match = $matches[$matchindex];
    $active_player = 0;
    if ($code === $matches[$matchindex]['player1code']) {
        $active_player = 1;
    } else if ($code === $matches[$matchindex]['player2code']) {
        $active_player = 2;
    }

    $filenames = processUploadedFiles($config, $match, $active_player);

    if ($code === $matches[$matchindex]['player1code']) {
        $matches[$matchindex]['player1recs'] = $filenames['filenames'];
        $matches[$matchindex]['player1zip'] = $filenames['zipfilename'];
    } else if ($code === $matches[$matchindex]['player2code']) {
        $matches[$matchindex]['player2recs'] = $filenames['filenames'];
        $matches[$matchindex]['player2zip'] = $filenames['zipfilename'];
    }

    file_put_contents($filename, json_encode($matches, JSON_PRETTY_PRINT));
    echo '{"success":true}';
} else {
    echo 'false';
}
