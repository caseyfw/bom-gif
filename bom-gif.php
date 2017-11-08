<?php

// Fetch last 10 images.

require_once('vendor/autoload.php');

use Intervention\Image\ImageManagerStatic as Image;

const EPOCH_PERIOD = 6;

$debug = boolval(param('debug'));
$noCache = boolval(param('noCache'));
$frames = intval(param('frames', '10'));
$radarId = param('radarId', 'IDR664');
$cacheDir = param('cacheDir', 'cache/');
debug('? debug = '.strval($debug));
debug('? noCache = '.false);
debug('? frames = '.strval($frames));
debug('? radarId = '.strval($radarId));
debug('? cacheDir = '.strval($cacheDir));

$epochs = getEpochs(date_create('now', timezone_open('Etc/UCT')), $frames);
$gifPath = $cacheDir.implode('.', [$radarId, $epochs[0]->format('YmdHi'), $frames, 'gif']);

if (!file_exists($gifPath) || $noCache) {
    debug("! Cache miss for gif.");
    // Create composite background image if it doesn't already exist.
    if (!file_exists($cacheDir.$radarId.'.composite-background.png') || $noCache) {
        debug("! Cache miss for background image.");
        // If any of the required background images are not present, fetch them.
        if (
            !file_exists($cacheDir.$radarId.'.background.png') ||
            !file_exists($cacheDir.$radarId.'.topography.png') ||
            !file_exists($cacheDir.$radarId.'.locations.png') ||
            $noCache
        ) {
            debug("! Cache miss for source background images.");
            $baseUrl = 'http://m.bom.gov.au/products/radar_transparencies/';
            if (!(fetchFile($baseUrl.$radarId.'.background.png', $cacheDir.$radarId.'.background.png') &&
                fetchFile($baseUrl.$radarId.'.topography.png', $cacheDir.$radarId.'.topography.png') &&
                fetchFile($baseUrl.$radarId.'.locations.png', $cacheDir.$radarId.'.locations.png'))) {
                die("Failed to download radar background images.");
            }
        }
        debug("> Building composite background image.");
        $composite = Image::make($cacheDir.$radarId.'.background.png')
            ->insert($cacheDir.$radarId.'.topography.png')
            ->insert($cacheDir.$radarId.'.locations.png')
            ->save($cacheDir.$radarId.'.composite-background.png');
    }

    $imageFiles = [];

    // Fetch radar images.
    foreach($epochs as $epoch) {
        debug("> Building composite background image.");
        $filename = $radarId.'.T.'.$epoch->format('YmdHi').'.png';
        $url = 'http://m.bom.gov.au/radar/'.$filename;
        $compositeFilename = $radarId.'.T.'.$epoch->format('YmdHi').'.composite.png';

        // Generate composite radar image with background if it doesn't exist.
        if (!file_exists($cacheDir.$compositeFilename) || $noCache) {

            // Fetch radar image if it doesn't exist.
            if (!file_exists($cacheDir.$filename) || $noCache) {
                if (!fetchFile($url, $cacheDir.$filename)) {
                    // If fetching image fails, just ignore it.
                    continue;
                };
            }
            $compositeRadarImage = Image::make($cacheDir.$radarId.'.composite-background.png')
                ->insert($cacheDir.$filename)
                ->save($cacheDir.$compositeFilename);
        }
        $imageFiles[] = $cacheDir.$compositeFilename;
    }
    $fileListPath = tempnam('/tmp', 'bom-gif-image-files-list');
    file_put_contents($fileListPath, implode("\n", $imageFiles));

    // @todo Deal with errors.
    shell_exec('convert -delay 20 -loop 0 @'.$fileListPath.' '.$gifPath);
}

if ($debug) {
    debug('Process resulted in a '.filesize($gifPath).'k gif file at '.$gifPath.'.');
} else {
    header('Content-Type: image/gif');
    readfile($gifPath);
}


function getLatestEpoch(DateTime $date): DateTime
{
    $date->setTime(
        $date->format('G'),
        intdiv((int) $date->format('i'), EPOCH_PERIOD) * EPOCH_PERIOD
    );
    return $date;
}

function getEpochs(DateTime $date, int $count): array
{
    // Mutate date so it's minute component is a multiple of 6 - 00,06,12,18,etc.
    $date->setTime(
        $date->format('G'),
        intdiv((int) $date->format('i'), EPOCH_PERIOD) * EPOCH_PERIOD
    );
    $epochInterval = new DateInterval('PT'.EPOCH_PERIOD.'M');

    $epochs = [];
    for ($i = 0; $i < $count; $i++) {
        // Clone $date object to avoid copy-by-reference side effects.
        $epochs[] = (clone $date);
        $date->sub($epochInterval);
    }
    return array_reverse($epochs);
}

function fetchFile(string $url, string $outputPath): bool
{
    $file = @file_get_contents($url);
    if ($file === false) {
        return false;
    }
    file_put_contents($outputPath, $file);
    return true;
}

function param(string $name, $default = false)
{
    if (getenv($name)) {
        return getenv($name);
    }
    if (array_key_exists('name', $_GET)) {
        return $_GET[$name];
    }
    if (array_key_exists('name', $_POST)) {
        return $_POST[$name];
    }
    return $default;
}

function debug(string $message)
{
    global $debug;
    if ($debug) {
        echo $message."\n";
    }
}
