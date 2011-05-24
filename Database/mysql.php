<?php
/**
 * Database adapter for the MySQL extension.
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
 * @uses        Database_Query_mysql.php
 *
 * $Id: mysql.php 8207 2010-09-15 04:22:23Z ccapps $
 **/

class Database_mysql extends Database {

/**
 * Constructor.
 *
 * Available options:
 *  - pconnect: Set to true to attempt a persistent connection.  Do not use
 *              persistent connections unless you are fully aware of the
 *              problems they present.
 *  - flags:    Any combination of "MySQL client constants."
 *              See PHP manual: http://php.net/mysql.constants
 *  - new_link: True by default, set to false to not create a new connection
 *              when identical credentials are passed.
 *
 * @param string $db_name   Database to use, once connected
 * @param string $login     Login name
 * @param string $password  Password
 * @param string $server    Hostname to connect to.  Defaults to "localhost"
 * @param string $port      Port or socket path to connect to
 * @param array  $options   List of driver-specific options
 **/
    protected function __construct($db_name, $login, $password, $server = 'localhost', $port = NULL, $options = NULL) {
        $pconnect = false;
        $flags = null;
        $new_link = true;
        if(Database_Functions::is_valid_array($options)) {
            $pconnect = array_key_exists('pconnect', $options) ? $options['pconnect'] : false;
            $flags = array_key_exists('flags', $options) ? $options['flags'] : null;
            $new_link = array_key_exists('new_link', $options) ? $options['new_link'] : true;
        }
    // Open our connection...
        if($pconnect)
            $this->dbh = @mysql_pconnect($port ? "$server:$port" : $server, $login, $password, $new_link, $flags);
        else
            $this->dbh = @mysql_connect($port ? "$server:$port" : $server, $login, $password, $new_link, $flags);
    // ... and ask for the database
        if ($this->dbh) {
            @mysql_select_db($db_name, $this->dbh)
                or $this->error("Can't access the database file. ($login@$server:$port/$db_name)", false);
        } else {
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
        return mysql_real_escape_string( (string)$string, $this->dbh );
    }


/**
 * Returns an un-executed Database_Query object
 *
 * @param string $query    The query string
 *
 * @return Database_Query_mysql
 **/
    public function &prepare($query) {
        $new_query = new Database_Query_mysql($this, $query);
        return $new_query;
    }


/**
 * Return the string error from the last error.
 *
 * @return string
 **/
    public function _errstr() {
        return $this->dbh ? mysql_error($this->dbh) : mysql_error();
    }


/**
 * Return the numeric error from the last error.
 *
 * @return int
 **/
    public function _errno() {
        return $this->dbh ? mysql_errno($this->dbh) : mysql_errno();
    }


/**
 * Close the connection to the database.
 **/
    public function close() {
        return mysql_close($this->dbh);
    }

}
