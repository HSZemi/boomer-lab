<?php

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

function processUploadedFiles($config, $code, $round, $player, $opponent, $numberOfGames)
{
    $filenames = array();
    for ($gameNumber = 0; $gameNumber < count($config); $gameNumber++) {
        for ($subGameNumber = 0; $subGameNumber < count($config[$gameNumber]); $subGameNumber++) {
            $filename = $config[$gameNumber][$subGameNumber];
            for ($i = 0; $i < count($_FILES['recs']['name']); $i++) {
                $name = $_FILES['recs']['name'][$i];
                if ($name === $filename) {
                    $target_filename = $round . '-'
                        . $code . '-'
                        . ($gameNumber + 1) . '.' . $subGameNumber . '-'
                        . $player . '-vs-' . $opponent
                        . '.aoe2record';
                    $target_filepath = 'admin/data/recs/' . $target_filename;
                    move_uploaded_file($_FILES['recs']['tmp_name'][$i], $target_filepath);
                    $filenames[] = $target_filename;
                }
            }
        }
    }

    $filenames = fillWithFakeGames($config, $code, $round, $player, $opponent, $numberOfGames, $filenames);

    $targetFilesize = 5 * 1024 * 1024;
    $paddedFilenames = padFilesizes($filenames, $targetFilesize);
    $zipFileName = createZipFile($paddedFilenames, $code, $round, $player, $opponent);

    return array('filenames' => $filenames, 'zipfilename' => $zipFileName);
}

function fillWithFakeGames($config, $code, $round, $player, $opponent, $numberOfGames, $filenames)
{
    for ($gameNumber = count($config); $gameNumber < $numberOfGames; $gameNumber++) {
        $numberOfFiles = 1;
        if (rand(0, 99) < 10) {
            $numberOfFiles = 2;
        }
        for ($subGameNumber = 0; $subGameNumber < $numberOfFiles; $subGameNumber++) {
            $target_filename = $round . '-'
                . $code . '-'
                . ($gameNumber + 1) . '.' . $subGameNumber . '-'
                . $player . '-vs-' . $opponent
                . '.aoe2record';
            $target_filepath = 'admin/data/recs/' . $target_filename;
            $source_filepath = 'admin/data/recs/' . $filenames[0];
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
        $filepath = 'admin/data/recs/' . $filename;
        $copyFilename = str_replace('.aoe2record', '.se.aoe2record', $filename);
        $copyFilepath = 'admin/data/recs/' . $copyFilename;
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

function createZipFile($filenames, $code, $round, $player, $opponent)
{
    $zipFileName = $round . '-' . $code . '-' . $player . '-vs-' . $opponent . '.zip';
    $zipFilePath = 'data/' . $zipFileName;
    $zipArchive = new ZipArchive();
    $zipArchive->open($zipFilePath, ZipArchive::CREATE);
    foreach ($filenames as $filename) {
        $zipArchive->addFile('admin/data/recs/' . $filename, $filename);
        $zipArchive->setCompressionName($filename, ZipArchive::CM_STORE);
    }
    $zipArchive->close();
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

    $player = '';
    $opponent = '';
    if ($code === $matches[$matchindex]['player1code']) {
        $player = $matches[$matchindex]['player1name'];
        $opponent = $matches[$matchindex]['player2name'];
    } else if ($code === $matches[$matchindex]['player2code']) {
        $player = $matches[$matchindex]['player2name'];
        $opponent = $matches[$matchindex]['player1name'];
    }

    $filenames = processUploadedFiles($config, $code, $matches[$matchindex]['round'], $player, $opponent, $matches[$matchindex]['number_of_games']);

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
