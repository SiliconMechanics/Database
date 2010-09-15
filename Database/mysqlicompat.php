<?php
/**
 * Database adapter for MySQLi, the "Improved" MySQL extension.
 *
 * Trivia: mysqli, or at least this interface to it, is roughly 5% slower than
 * the mysql extension.  Haven't looked into why yet.
 *
 * This file is based on code originally written by Chris Petersen for several
 * different open source projects.  He has granted Silicon Mechanics permission
 * to use this file under the LGPL license, on the condition that SiMech release
 * any changes under the GPL, so that improvements can be merged back into GPL
 * projects.
 *
 * @copyright   Silicon Mechanics
 * @license     GPL for public distribution
 *
 * @package     SiMech
 * @subpackage  Shared-OSS
 * @subpackage  Database
 *
 * @uses        Database.php
 * @uses        Database_Query_mysqlicompat.php
 *
 * $Id: mysqlicompat.php 8207 2010-09-15 04:22:23Z ccapps $
 **/

class Database_mysqlicompat extends Database {

/**
 * Constructor.
 *
 * Options:
 *  - pconnect: If we're on PHP 5.3 or better, attempt a persistent connection.
 *              Do not use persistent connections unless you are fully aware
 *              of the problems they present.  You can also use the 'p:' prefix
 *              in the server name as outlined in the mysqli_connect manual page.
 *
 * @param string $db_name   Database to use, once connected
 * @param string $login     Login name
 * @param string $password  Password
 * @param string $server    Hostname to connect to.  Defaults to "localhost"
 * @param string $port      Port or socket path to connect to
 * @param array  $options   List of driver-specific options
 **/
    protected function __construct($db_name, $login, $password, $server = 'localhost', $port = NULL, $options = array()) {
        if(array_key_exists('pconnect', $options) && $options['pconnect']) {
            if(defined('PHP_VERSION_ID') && PHP_VERSION_ID >= 50300 && strpos($server, 'p:') !== 0)
                $server = 'p:' . $server;
        }
        $socket = null;
        if(!is_numeric($port)) {
            $socket = $port;
            $port = null;
        }
        $this->dbh = mysqli_connect($server, $login, $password, $db_name, $port, $socket);
        if( !(is_object($this->dbh) && $this->dbh instanceof mysqli) ) {
            $this->dbh = null;
            $this->error("Can't connect to the database server. ($login@$server:$port/$db_name)", false);
        }
    }


/**
 * Escape a string.  Implementation specific behavior.
 *
 * @param string $string    string to escape
 * @return string           escaped string
 **/
    public function escape_string($string) {
        return mysqli_real_escape_string( $this->dbh, (string)$string );
    }


/**
 * Returns an un-executed Database_Query object
 *
 * @param string $query    The query string
 *
 * @return Database_Query_mysqlicompat
 **/
    public function &prepare($query) {
        $new_query = new Database_Query_mysqlicompat($this, $query);
        return $new_query;
    }


/**
 * Return the string error from the last error.
 * 
 * @return string
 **/
    public function _errstr() {
        return $this->dbh ? mysqli_error($this->dbh) : mysqli_connect_error();
    }


/**
 * Return the numeric error from the last error.
 * 
 * @return int
 **/
    public function _errno() {
        return $this->dbh ? mysqli_errno($this->dbh) : mysqli_connect_errno();
    }


/**
 * Close the connection to the database.
 **/
    public function close() {
        return mysqli_close($this->dbh);
    }

}

