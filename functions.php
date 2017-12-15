<?php

@include("custom_functions.php");

function do_login() {
    header("WWW-Authenticate: Basic realm=\"Login to access the export script\"");
    header("HTTP/1.0 401 Unauthorized");
    echo "Access Denied\n";
    exit;
}

function authenticate() {
    global $username;
    global $password;
    if ($_SERVER['PHP_AUTH_PW'] != $password || $_SERVER['PHP_AUTH_USER'] != $username) do_login();
}

function get_libraries($rootPath = '', $libraries = array()) {
    foreach (glob($rootPath . "library/*.php") as $filename) {
        $library_name = str_replace(".php", "", basename($filename));
        if (in_array($library_name, $libraries)) require_once($filename);
    }
    foreach ($libraries as $library) {
        global ${$library};
        ${$library} = new $library();
    }
}

function setErrorReporting() {
    if (isset($_GET['debug_errors']) || $_SERVER['SERVER_NAME'] == "localhost") { //force or development error reporting!
        if (defined('E_DEPRECATED')) {
            error_reporting(E_ALL ^ E_NOTICE ^ E_WARNING ^ E_DEPRECATED);
        } else {
            error_reporting(E_ALL ^ E_NOTICE ^ E_WARNING);
        }
    } else {
        error_reporting(0); // normal behaviour for production
    }
}

function getHeaders() {
    session_set_cookie_params(NULL, NULL, NULL, false, true);
    session_start();
    mb_internal_encoding("utf-8");
    header('P3P:CP="IDC DSP COR ADM DEVi TAIi PSA PSD IVAi IVDi CONi HIS OUR IND CNT"');
    header('Content-type: text/html; charset=utf-8');
    $_SERVER['PHP_SELF'] = str_replace('"', '', htmlspecialchars(str_replace('script', '', $_SERVER['PHP_SELF']), ENT_QUOTES));
}

function getProtocol() {
    if (isset($_GET['secure_protocol'])) {
        $protocol = ($_GET['secure_protocol'] == "1") ? "https" : "http";
    } else {
        $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off' || $_SERVER['SERVER_PORT'] == 443) ? "https" : "http";
    }
    return $protocol;
}

function __autoload($className) {
    global $imports;
    if (isset($imports[$className])) {
        include_once($imports[$className]);
    }
}

function import($import, $admin = false) {
    global $library;
    global $imports;
    $path = ($admin) ? "../" : NULL;
    $lastDot = strrpos($import, '.');
    $class = $lastDot ? substr($import, $lastDot + 1) : $import;
    $package = substr($import, 0, $lastDot);
    if (isset($imports[$class]) || isset($imports[$package . '.*'])) return true;
    $folder = $path . ($package ? str_replace('.', '/', $package) : '');
    $file = $path . $folder . "/" . $class . ".php";
    if (!file_exists($folder)) {
        $back = debug_backtrace();
        return trigger_error("There is no such package <strong>'$package'</strong> -- Checked folder <strong>'$folder'</strong><br>
            Imported from <strong>'{$back[0]['file']}'</strong> on line <strong>'{$back[0]['line']}'</strong><br>", E_USER_WARNING);
    } elseif ($class != '*' && !file_exists($file)) {
        $back = debug_backtrace();
        return trigger_error("There is no such Class <strong>'$import'</strong> -- Checked for file <strong>'$file'</strong><br>
            Imported from <strong>'{$back[0]['file']}'</strong> on line <strong>'{$back[0]['line']}'</strong><br>", E_USER_WARNING);
    }
    if ($class != '*') {
        $imports[$class] = $file;
    } else {
        $imports["$package.*"] = 1;
        $dir = opendir($folder);
        while (($file = readdir($dir)) !== false) {
            if (strrpos($file, '.php')) {
                $class = str_replace('.php', '', $file);
                $imports[$class] = $folder . "/" . $file;
                $library[] = $class;
            }
        }
    }
}

function debug($var) {
    echo "<pre>" . PHP_EOL;
    var_dump($var);
    echo "</pre>" . PHP_EOL;
}

function escapeJsonString($value) {
    $escapers = array("\\", "/", "\"", "\n", "\r", "\t", "\x08", "\x0c");
    $replacements = array("\\\\", "\\/", "\\\"", "\\n", "\\r", "\\t", "\\f", "\\b");
    $result = str_replace($escapers, $replacements, $value);
    return $result;
}

if (!function_exists('mb_ucfirst')) {
    function mb_ucfirst($str, $encoding = "UTF-8", $lower_str_end = false) {
        $first_letter = mb_strtoupper(mb_substr($str, 0, 1, $encoding), $encoding);
        if ($lower_str_end) {
            $str_end = mb_strtolower(mb_substr($str, 1, mb_strlen($str, $encoding), $encoding), $encoding);
        } else {
            $str_end = mb_substr($str, 1, mb_strlen($str, $encoding), $encoding);
        }
        $str = $first_letter . $str_end;
        return $str;
    }
}

function unicode_substr($text, $start, $length = NULL) {
    if (!function_exists("mb_substr")) {
        return $length === NULL ? mb_substr($text, $start) : mb_substr($text, $start, $length);
    } else {
        $strlen = strlen($text);
        $bytes = 0;
        if ($start > 0) {
            $bytes = -1;
            $chars = -1;
            while ($bytes < $strlen - 1 && $chars < $start) {
                $bytes++;
                $c = ord($text[$bytes]);
                if ($c < 0x80 || $c >= 0xC0) {
                    $chars++;
                }
            }
        } elseif ($start < 0) {
            $start = abs($start);
            $bytes = $strlen;
            $chars = 0;
            while ($bytes > 0 && $chars < $start) {
                $bytes--;
                $c = ord($text[$bytes]);
                if ($c < 0x80 || $c >= 0xC0) {
                    $chars++;
                }
            }
        }
        $istart = $bytes;
        if ($length === NULL) {
            $iend = $strlen;
        } elseif ($length > 0) {
            $iend = $istart - 1;
            $chars = -1;
            $last_real = FALSE;
            while ($iend < $strlen - 1 && $chars < $length) {
                $iend++;
                $c = ord($text[$iend]);
                $last_real = FALSE;
                if ($c < 0x80 || $c >= 0xC0) {
                    $chars++;
                    $last_real = TRUE;
                }
            }
            if ($last_real && $chars >= $length) {
                $iend--;
            }
        } elseif ($length < 0) {
            $length = abs($length);
            $iend = $strlen;
            $chars = 0;
            while ($iend > 0 && $chars < $length) {
                $iend--;
                $c = ord($text[$iend]);
                if ($c < 0x80 || $c >= 0xC0) {
                    $chars++;
                }
            }
            if ($iend > 0) {
                $iend--;
            }
        } else {
            return '';
        }
        return substr($text, $istart, max(0, $iend - $istart + 1));
    }
}

/*******************decode unicode text - Usage: $decoded = unicode_decode('\u003c');*******************/
function replace_unicode_escape_sequence($match) {
    return mb_convert_encoding(pack('H*', $match[1]), 'UTF-8', 'UCS-2BE');
}

function unicode_decode($str) {
    return preg_replace_callback('/\\\\u([0-9a-f]{4})/i', 'replace_unicode_escape_sequence', $str);
}

/*************************************************************************************/


?>