<?php
defined('CMS') or die("This file cannot run this way!");

class db
{

    private $dbUser;
    private $dbPass;
    private $dbName;
    private $dbHost;
    private $dbPort;
    private $mysqli;
    private $conn = null;

    function __construct($credentials = null) {
        $this->DBsetup($credentials);
    }


    function DBsetup($credentials) {
        global $dbUser, $dbPass, $dbName, $dbHost;
        if (is_array($credentials)) {
            $this->dbUser = $credentials['dbUser'];
            $this->dbPass = $credentials['dbPass'];
            $this->dbName = $credentials['dbName'];
            $host_parameters = explode(":", $credentials['dbHost']);
            $host = $host_parameters[0];
            $port = $host_parameters[1];
            if (empty($port)) $port = 3306;
            $this->dbHost = $host;
            $this->dbPort = $port;
        } else {
            $this->dbUser = $dbUser;
            $this->dbPass = $dbPass;
            $this->dbName = $dbName;
            $host_parameters = explode(":", $dbHost);
            $host = $host_parameters[0];
            $port = $host_parameters[1];
            if (empty($port)) $port = 3306;
            $this->dbHost = $host;
            $this->dbPort = $port;
        }

        unset($dbUser, $dbPass, $dbName, $dbHost);
        $this->mysqli = new mysqli($this->dbHost, $this->dbUser, $this->dbPass, $this->dbName);
        $this->connect();
    }

    private function connect() {
        try {
            $DBH = new PDO("mysql:host=" . $this->dbHost . ";port=" . $this->dbPort . ";dbname=" . $this->dbName, $this->dbUser, $this->dbPass);
            $DBH->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        } catch (PDOException $e) {
            file_put_contents('PDOErrors.txt', $e->getMessage(), FILE_APPEND);
            die("A Database error has been recorded! Bad formatted query or wrong credentials");
        }
        $this->conn = $DBH;
    }

    function getRecords($query, $obj = true) {
        $this->cache();
        global $cache;
        global $cachedQueries;
        global $allQueries;
        $allQueries++;
        if ($cache && gettype($query) != "object") {
            if (array_key_exists($query, $_SESSION['cache'])) {
                $cachedQueries++;
                return $_SESSION['cache'][$query];
            }
        }
        if (gettype($query) != "object") {
            $res = $this->query($query);
        } else {
            $res = $query;
        }
        $rows = $this->getRows($res, $obj);
        if ($cache) $_SESSION['cache'][$query] = $rows;
        return $rows;
    }

    function cache() {
        global $cache;
        if (empty($cache)) return false; // cache is diasabled
        $timeout = 10; // time in minutes that cache will hold data
        $timeout = $timeout * 60; // convert to seconds in order to compare it with timestamp
        if (is_array($_SESSION['cache'])) { // cache already created
            $time_elapsed = (int)@time() - (int)$_SESSION['start_cache'];
            if ($time_elapsed > $timeout) { // destroy existing cache
                $this->clearCache();
            } else {
                return; // continue to use the existing cache
            }
        }
        if (empty($_SESSION['cache'])) { // create new cache
            $_SESSION['cache'] = array();
            $_SESSION['start_cache'] = @time();
        }
    }

    function clearCache() {
        unset($_SESSION['cache']);
    }

    function query($query, $log = true) {
        $this->query = $query;
        //var_dump($query);
        try {
            $result = $this->conn->query($query);
        } catch (PDOException $e) {
            if ($log) {
                global $messages;
                $messages->addError("A Database error has been recorded in the log file");
                $messages->printSystemMessages();
                //debug($e->getMessage() . "\r\n" . date('d-m-Y H:i:s') . " -> Related Query:" . $query);
                file_put_contents('PDOErrors.txt', $e->getMessage() . "\r\n" . date('d-m-Y H:i:s') . " -> Related Query:" . $query . "\r\n", FILE_APPEND);
            }
        }
        return $result;
    }

    function getRows($result, $obj = true) {
        $result->execute();
        if ($obj) {
            $res = $result->fetchAll(PDO::FETCH_OBJ);
        } else {
            $res = $result->fetchAll(PDO::FETCH_ASSOC);
        }
        return $res;
    }

    function addRecord($table, $vars, $global = true, $obj = null) {
        $table = str_replace("`", null, $table);
        $values = $this->getValues($vars, $global, $obj);
        array_shift($values); // remove first element (id)
        $fieldNames = $fieldValues = '';
        foreach ($values as $key => $value) {
            $fieldNames .= "`" . $key . "`,";
            $fieldValues .= "'" . $value . "', ";
        }
        $fieldNames = substr($fieldNames, 0, strlen($fieldNames) - 1); // no trailing commas
        $fieldValues = substr($fieldValues, 0, strlen($fieldValues) - 2); // no trailing commas and spaces
        $q = "INSERT INTO `$table` ($fieldNames) VALUES($fieldValues)";
        if ($this->query($q)) {
            //return $this->lastID();
            return $this->conn->lastInsertId();
        } else {
            return false;
        }
    }

    private function getValues($vars, $global, $obj) {
        if (empty($global) && empty($obj)) die("Wrong parameters in db->getValues for global and obj");
        $values = array();
        foreach ($vars as $fieldName) {
            if ($global) {
                global ${$fieldName};
            } else {
                ${$fieldName} = $obj->$fieldName;
            }
            $values[$fieldName] = ${$fieldName};
        }
        return $values;
    }

    function updateRecord($table, $id, $vars, $global = true, $obj = null) {
        $table = str_replace("`", null, $table);
        $values = $this->getValues($vars, $global, $obj);
        $update = "SET ";
        foreach ($values as $key => $value) {
            $update .= $key . "='" . $value . "', ";
        }
        $update = substr($update, 0, strlen($update) - 2); // no trailing commas and spaces
        $q = "UPDATE `$table` " . $update . " where id='$id' ";
        if ($this->query($q)) {
            return true;
        } else {
            return false;
        }
    }

    function lastID() {
        return $this->conn->lastInsertId();
    }

    function getNextID($table) {
        global $dbName;
        $q = "SELECT `AUTO_INCREMENT` as nextID FROM  INFORMATION_SCHEMA.TABLES WHERE TABLE_SCHEMA = '" . $dbName . "' AND TABLE_NAME = '" . $table . "'";
        $nextID = $this->getRecord($q)->nextID;
        return $nextID;
    }

    function getRecord($query, $obj = true) {
        $this->cache();
        global $cache;
        global $cachedQueries;
        global $allQueries;
        $allQueries++;
        if ($cache && gettype($query) != "object") {
            if (array_key_exists($query, $_SESSION['cache'])) {
                $cachedQueries++;
                return $_SESSION['cache'][$query];
            }
        }
        if (gettype($query) != "object") {
            $res = $this->query($query);
        } else {
            $res = $query;
        }
        $results = $this->getRow($res, $obj);
        if ($cache) $_SESSION['cache'][$query] = $results;
        return $results;
    }

    function getRow($result, $obj = true) {
        if ($obj) {
            $res = $result->fetch(PDO::FETCH_OBJ);
        } else {
            $res = $result->fetch(PDO::FETCH_ASSOC);
        }
        return $res;
    }

    function getFieldsNames($query) {
        if (gettype($query) != "object") {
            $query = $this->query($query);
        }
        $numOfFields = $query->columnCount();
        for ($i = 0; $i < $numOfFields; $i++) {
            $attributes = $query->getColumnMeta($i);
            $names[] = $attributes['name'];
        }
        if (count($names)) {
            return $names;
        } else {
            return false;
        }
    }

    function getFieldName($result, $index) {
        $attributes = $result->getColumnMeta($index);
        return $attributes['name'];
    }

    function getNumFields($result) {
        return $result->columnCount();
    }

    function getdbName() {
        return ($this->dbName) ? $this->dbName : null;
    }

    function getdbPort() {
        return ($this->dbPort) ? $this->dbPort : null;
    }

    function getMysqli() {
        return $this->mysqli;
    }

    function isTableExists($table) {
        if (!$table) return false;
        return $this->getNumRows("show tables like '$table'");
    }

    function getNumRows($result) {
        if (gettype($result) != "object") {
            $result = $this->query($result);
        }
        $result->execute();
        $value = count($result->fetchAll());
        return $value;
    }

    function toObject($array) {
        $object = new stdClass();
        if (is_array($array) && count($array) > 0) {
            foreach ($array as $name => $value) {
                $name = strtolower(trim($name));
                if (!empty($name)) {
                    $object->$name = $value;
                }
            }
        }
        return $object;
    }

    function toArray($object) {
        $array = array();
        if (is_object($object)) {
            $array = get_object_vars($object);
        }
        return $array;
    }

    function importSQL($file, $delimiter = ';') {
        global $messages;
        set_time_limit(0);
        if (is_file($file) === true) {
            $file = fopen($file, 'r');
            if (is_resource($file) === true) {
                $query = array();
                while (feof($file) === false) {
                    $query[] = fgets($file);
                    if (preg_match('~' . preg_quote($delimiter, '~') . '\s*$~iS', end($query)) === 1) {
                        $query = trim(implode('', $query));
                        if ($this->query($query, false) === NULL) {
                            $messages->addError("The update of the database failed");
                            $messages->addError("Could not execute MySQL query! Query was: " . $query);
                        }
                        while (ob_get_level() > 0) {
                            ob_end_flush();
                        }
                        flush();
                    }
                    if (is_string($query) === true) $query = array();
                }
                fclose($file);
            }
        }
    }

} // end class
?>