<?php

function detect_cms() {
    if (is_dir("../components")) return "joomla";
    if (is_dir("../wp-content")) return "wordpress";
    return false;
}

function manage_loading($task, $total, $counter) {
    global $loading_file;
    $loading_file = (preg_match('/^([-\.\w]+)$/', $loading_file) > 0) ? $loading_file : "loading.txt"; // safe filename
    $data = file($loading_file);
    list($loaded,) = explode("|", $data[0]);
    $loaded = (int)$loaded;
    $percent = (int)(100 * ($counter / $total));
    if ($percent > $loaded) {
        $fp = fopen($loading_file, "w");
        fwrite($fp, $percent . '|' . $task);
        fclose($fp);
    }
}

function reset_loading() {
    global $loading_file;
    $fp = fopen($loading_file, "w");
    fwrite($fp, '');
    fclose($fp);
}

function limit_text($text) {
    if (empty($max_chars)) return $text; // no limitation
    $len = strlen($text);
    $text = unicode_substr($text, 0, $max_chars);
    if (strlen($text) < $len) $text = trim($text) . "...";
    return $text;
}

function emptyDirectory($dir, $exclude_zip) {
    $files = glob($dir . '*');
    foreach ($files as $file) {
        if (is_file($file)) {
            if ($exclude_zip && pathinfo($file, PATHINFO_EXTENSION) == "zip") continue; // skip zip file
            unlink($file);
        }
    }
}

function getTimestamp($date) {
    $thedate = DateTime::createFromFormat("Y-m-d G:i:s", $date, new DateTimeZone("Europe/Athens"));
    return date_format($thedate, 'U');
}

function create_zip($files = array(), $destination = '', $path = true) {
    $valid_files = array();
    if (is_array($files)) {
        foreach ($files as $file) {
            if (file_exists($file)) {
                $valid_files[] = $file;
            }
        }
    }
    if (count($valid_files)) {
        $zip = new ZipArchive();
        $zip_results = $zip->open($destination, ZIPARCHIVE::CREATE);
        if ($zip_results !== true) {
            return $zip_results; // return error code
        }
        foreach ($valid_files as $file) {
            $new_filename = (!$path) ? substr($file, strrpos($file, '/') + 1) : $file; // strip path or keep it
            $zip->addFile($file, $new_filename);
        }
        $zip->close();
        return file_exists($destination);
    } else {
        return false;
    }
}

function getZipError($code) {
    switch ($code) {
        case 0:
            return 'No error';
        case 1:
            return 'Multi-disk zip archives not supported';
        case 2:
            return 'Renaming temporary file failed';
        case 3:
            return 'Closing zip archive failed';
        case 4:
            return 'Seek error';
        case 5:
            return 'Read error';
        case 6:
            return 'Write error';
        case 7:
            return 'CRC error';
        case 8:
            return 'Containing zip archive was closed';
        case 9:
            return 'No such file';
        case 10:
            return 'File already exists';
        case 11:
            return 'Can`t open file';
        case 12:
            return 'Failure to create temporary file';
        case 13:
            return 'Zlib error';
        case 14:
            return 'Malloc failure';
        case 15:
            return 'Entry has been changed';
        case 16:
            return 'Compression method not supported';
        case 17:
            return 'Premature EOF';
        case 18:
            return 'Invalid argument';
        case 19:
            return 'Not a zip archive';
        case 20:
            return 'Internal error';
        case 21:
            return 'Zip archive inconsistent';
        case 22:
            return 'Can`t remove file';
        case 23:
            return 'Entry has been deleted';
        default:
            return 'An unknown error has occurred (' . intval($code) . ')';
    }
}


