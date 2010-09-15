<?php
/**
 * Database adapter for the PDO extension.
 *
 * NOTICE: Due to the paramater juggling, this adapter is about 13% slower than
 * mysqli and about 17% slower than the mysql extension.  If you do thousands
 * of queries per page, you might care about this.  To be frank, the PDO adapter
 * is a proof of concept and not intended for production.
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
 * @uses        Database_Query_pdo.php
 *
 * $Id: pdo.php 8206 2010-09-15 04:17:47Z ccapps $
 **/

class Database_pdo extends Database {

    /** @var Exception */
    protected $_connection_exception;
    /** @var Database_Query_pdo */
    public $last_sh;

/**
 * Constructor.
 *
 * **You MUST construct your PDO DSN manually!**
 * There is too much variation between the PDO drivers and their desired
 * DSN formats to put it together properly ourselves in this code.  Besides,
 * an abstraction layer on top of an abstraction interface is already silly
 * enough without that kind of nonsense.
 *
 * Options:
 *  - dsn:              The DSN, formatted as required by the driver being used.
 *                      You *may* need to repeat the username and password
 *                      in the DSN, depending on the driver.
 *  - driver_options:   Any PDO Driver Options, as defined by the driver being used.
 *
 * DSN example for SQLite:
 * $db = Database::connect('', '', '', '', '', array( 'dsn' => 'sqlite:/tmp/foo.sqlite' ));
 *
 * @param string $db_name   Database to use, once connected
 * @param string $login     Login name
 * @param string $password  Password
 * @param string $server    Hostname to connect to.  Defaults to "localhost"
 * @param string $port      Port or socket path to connect to
 * @param array  $options   List of driver-specific options
 **/
    protected function __construct($db_name, $login, $password, $server = 'localhost', $port = NULL, $options = array()) {
        if(!isset($options['driver_options']) || !is_array($options['driver_options']))
            $options['driver_options'] = null;
    // We'd normally prefer to assemble the DSN ourselves, but each PDO
    // driver has an entirely different set of DSN options...
        try {
            $this->dbh = new PDO($options['dsn'], $login, $password, $options['driver_options']);
        } catch(Exception $e) {
        // Database wants a call to error, not the exception that we just caught.
        // However, the call to error wants the error code, which the
        // exception actually contains.  Stash it away for reference.
            $this->_connection_exception = $e;
            $this->error("Can't connect to the database server. ($login@$server:$port/$db_name)", false);
            $this->dbh = null;
            return;
        } // end try
        $this->dbh->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    }


/**
 * Escape a string.  Implementation specific behavior.
 *
 * @param string $string    string to escape
 * @return string           escaped string
 **/
    public function escape_string($string) {
        return $this->dbh->quote($string);
    }


/**
 * Returns an un-executed Database_Query object
 *
 * @param string $query    The query string
 *
 * @return Database_Query_pdo
 **/
    public function &prepare($query) {
        $new_query = new Database_Query_pdo($this, $query);
        return $new_query;
    }


/**
 * Return the string error from the last error.
 * 
 * @return string
 **/
    public function _errstr() {
    // PDO actually does the sane thing and has error checking at both the
    // object level and at the statement level, in addition to exceptions.
    // This makes our life a bit harder than it would be otherwise.  Let's
    // first check the exception...
        $errorinfo = null;
        if($this->last_sh && $this->last_sh->last_exception instanceof Exception) {
            $errorinfo = array(
                '',
                $this->last_sh->last_exception->getCode(),
                $this->last_sh->last_exception->getMessage()
            );
        }
    // Then the statement...
        if(is_null($errorinfo) && $this->last_sh && $this->last_sh->sh instanceof PDOStatement) {
            $errorinfo = $this->last_sh->sh->errorInfo();
            if(is_null($errorinfo) || $errorinfo[0] === '00000')
                $errorinfo = null;
        }
    // Now check the database handle.
        if(is_null($errorinfo) && $this->dbh instanceof PDO) {
            $errorinfo = $this->dbh->errorInfo();
            if(is_null($errorinfo) || $errorinfo[0] === '00000')
                $errorinfo = null;
        }
    // We'll get back the SQLSTATE, the driver error code and message.  We
    // care only about the driver error here, SQLSTATE gets returned by errno.
        if(is_array($errorinfo)) {
            return '[' . $errorinfo[1] . '] ' . $errorinfo[2];
        }
    // If we didn't get anything from errorInfo, let's see if we're working
    // with a failed connection instead.
        elseif(is_null($errorinfo) && $this->_connection_exception instanceof Exception) {
            return $this->_connection_exception->getMessage();
        }
        return '';
    }


/**
 * Return the numeric error from the last error.
 * 
 * @return mixed SQLSTATE
 **/
    public function _errno() {
    // Same deal here as in errstr, check the exception first...
        $sqlstate = null;
        if($this->last_sh && $this->last_sh->last_exception instanceof Exception) {
            $sqlstate = $this->last_sh->last_exception->getCode();
        }
        if(is_null($sqlstate) && $this->last_sh && $this->last_sh->sh instanceof PDOStatement) {
            $sqlstate = $this->last_sh->sh->errorCode();
            if(is_null($sqlstate) || $sqlstate === '00000')
                $sqlstate = null;
        }
    // Now check the database handle.
        if(is_null($sqlstate) && $this->db instanceof PDO) {
            $sqlstate = $this->dbh->errorCode();
            if(is_null($sqlstate) || $sqlstate === '00000')
                $sqlstate = null;
        }
    // Only return real errors...
        if(!is_null($sqlstate) && $sqlstate !== '00000') {
            return $sqlstate;
        }
    // If we didn't get a bad SQLSTATE, let's see if we're working with a
    // failed connection instead.
        elseif((is_null($errorinfo) || $sqlstate === '00000') && $this->_connection_exception instanceof Exception) {
            return $this->_connection_exception->getCode();
        }
        return '';
    }


/**
 * Close the connection to the database.
 **/
    public function close() {
        unset($this->dbh);
    }

}

