<?php
/**
 * Database adapter for PostgreSQL.
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
 * @uses        Database_Query_pg.php
 *
 * $Id: pg.php 8207 2010-09-15 04:22:23Z ccapps $
 **/

class Database_pg extends Database {

    /** @var Database_Query_pg */
    public $last_sh;

/**
 * Constructor.
 *
 * Options:
 *  - connect_timeout: Passed into the DSN, see pg documentation.
 *  - options: Passed into the DSN, see pg documentation.
 *  - sslmode: Passed into the DSN, see pg documentation.
 *  - service: Passed into the DSN, see pg documentation.
 *  - new_link: True by default, set to false to not create a new connection
 *              when identical credentials are passed.
 *  - pconnect: Set to true to attempt a persistent connection.  Do not use
 *              persistent connections unless you are fully aware of the
 *              problems they present.  Further, PG has much better options
 *              for the problem pconnect solves.  Connection pools, use'em!
 *
 * @param string $db_name   Database to use, once connected
 * @param string $login     Login name
 * @param string $password  Password
 * @param string $server    Hostname to connect to.  Defaults to "localhost"
 * @param string $port      Port or socket path to connect to
 * @param array  $options   List of driver-specific options
 **/
    protected function __construct($db_name, $login, $password, $server = 'localhost', $port = NULL, $options = array()) {
    // PG uses a DSN to connect rather than a plethora of positional options.
        $dsn = array(
            'host' => $server,
            'dbname' => $db_name,
            'user' => $login,
            'password' => $password
        );
        if($port)
            $dsn['port'] = $port;
        $legal_options = array('connect_timeout', 'options', 'sslmode', 'service');
        foreach($legal_options as $option )
            if(array_key_exists($option, $options))
                $dsn[$option] = $options[$option];
        $dsn_string = array();
    // "Single quotes and backslashes within the value must be escaped with a backslash"
        foreach($dsn as $k => $v) {
            $dsn_string[] = $k
                          . " = '"
                          . str_replace("'", "\\'", str_replace('\\', '\\\\', $v))
                          . "'";
        }
        $dsn_string = join(' ', $dsn_string);
    // Options
        $pconnect = array_key_exists('pconnect', $options) ? $options['pconnect'] : false;
        $new_link = array_key_exists('new_link', $options) ? $options['new_link'] : false;
        $connect_type = null;
        if($new_link)
            $connect_type = PGSQL_CONNECT_FORCE_NEW;
    // Connect to the database
        if($pconnect)
            $this->dbh = pg_pconnect($dsn_string, $connect_type);
        else
            $this->dbh = pg_connect($dsn_string, $connect_type);
        if(!is_resource($this->dbh)) {
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
        return pg_escape_string($this->dbh, $string);
    }


/**
 * Encode and quote a string for inclusion in a query.  Automatically attempts
 * to escape any question marks in the string which would otherwise be mistaken
 * for placeholders.
 *
 * This PG-specific implementation adds ByteField support.
 *
 * @param string $string
 * @param bool $escape_question_marks Set to false to not escape question marks.
 *
 * @return string
 **/
    public function escape($string, $escape_question_marks) {
        if(is_object($string) && $string instanceof Database_ByteField)
            return $this->escape_bytefield($string);
        else
            return parent::escape($string, $escape_question_marks);
    }


/**
 * Escape a bytefield.  Most databases consider these strings, so we'll try
 * handling it as a string by default.
 *
 * @param string $bf Bytefield to escape
 * @return string
 **/
    public function escape_bytefield($bf) {
        return "E'" . pg_escape_bytea($this->dbh, (string)$string) . "'::bytea";
    }


/**
 * Undo ByteField encoding, if needed.  Impl specific.
 * @return string
 **/
    public function unescape_byte_field($bytes) {
        return pg_unescape_bytea($bytes);
    }


/**
 *  Returns an un-executed Database_Query_mysqlicompat object
 *
 *  @param string $query    The query string
 *
 *  @return Database_Query_mysqli
 **/
    public function &prepare($query) {
        $new_query = new Database_Query_pg($this, $query);
        return $new_query;
    }


/**
 * Return the string error from the last error.
 * 
 * @return string
 **/
    public function _errstr() {
    // Like PDO, we have both top level and statement level errors.  How fun!
        if(isset($this->last_sh) && $this->last_sh->errno)
            return $this->last_sh->errstr;
        if(is_resource($this->dbh))
            return pg_last_error($this->dbh);
        return '';
    }


/**
 * Return the numeric error from the last error.
 * 
 * @return mixed
 **/
    public function _errno() {
    // We can only get the numeric error from the last query, not from
    // the top level error.  Meh.
        if(isset($this->last_sh) && $this->last_sh->errno)
            return $this->last_sh->errno;
        return -1;
    }


/**
 * Close the connection to the database.
 **/
    public function close() {
    // "pg_close() will not close persistent links generated by pg_pconnect()."
        return pg_close($this->dbh);
    }


/**
 * Grab the last inserted ID as generated by a sequence.  It's worth noting
 * that lastval() was added in 8.1.  You should *probably* use the RETURNING
 * clause instead of relying on insert_id().
 *  
 * @return int But can also return false (query failure) or null (no last value)
 **/
    public function insert_id() {
        $last_insert_sh = pg_query($this->dbh, 'SELECT lastval()');
        if(!is_resource($last_insert_sh))
            return false;
        $lastval = pg_fetch_array($last_insert_sh, 0, PGSQL_NUM);
        pg_free_result($last_insert_sh);
        return is_array($lastval) ? $lastval[0] : null;
    }
}

