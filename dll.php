<?php
define("CMS", "korCMS");
require_once("functions.php");
require_once("config/config.php");    // access from root folder
if($private_content) authenticate();
setErrorReporting();
getHeaders();
$protocol = getProtocol();
$domainPath = $protocol . "://" . $_SERVER['HTTP_HOST'] . "/";
$imports = array();
$library = array();
import("library.*", false); // load all Odyssey classes
$messages = new messages();
$db = new db();
$db->query("SET NAMES 'utf8'");
reset_loading();
?>
