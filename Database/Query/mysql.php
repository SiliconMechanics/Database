<?php
/**
 * Statement handle for the MySQL extension.
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
 * @uses        Database_mysql.php
 * @uses        Database_Query.php
 *
 * $Id: mysql.php 8207 2010-09-15 04:22:23Z ccapps $
 **/

class Database_Query_mysql extends Database_Query {

    /** @var Database_mysql */
    public $db;

/**
 * Executes the query that was previously passed to the constructor.
 *
 * @param mixed  $arg      Query arguments to escape and insert at ? placeholders in $query
 * @param mixed  ...       Additional arguments
 **/
    public function execute() {
    // We can't very well perform a query wtihout an active connection.
        if ($this->dbh === false) {
            $this->db->error('Lost connection to database.');
            return;
        }
    // Finish any previous statements
        $this->finish();
    // Replace in the arguments
        $this->last_query = $this->replaceholders( Database::smart_args( func_get_args() ) );
        if(!$this->last_query)
            return;
    // Wrap the actual query execution in a bit of benchmarking.
        $before = microtime(true);
        $this->sh = mysql_query($this->last_query, $this->dbh);
        $after = microtime(true);
        $this->db->mysql_time += $after - $before;

    // Non-select statements return a boolean, which means we call affected_rows.
        if (is_bool($this->sh)) {
            $this->insert_id     = mysql_insert_id($this->dbh);
            $this->affected_rows = mysql_affected_rows($this->dbh);
        }
    // Selects return a statement handle, which means we call num_rows.
        else {
            $this->num_rows      = mysql_num_rows($this->sh);
        }

    // Can we pull down warnings from the server?  Because the mysql interface
    // doesn't give us the warning count, we have to check ourselves.
        $this->warnings = array();
        if ($this->db->enable_warning_logging) {
            if ($sh = mysql_query($this->dbh, 'SHOW WARNINGS')) {
                while ($row = mysql_fetch_row($sh))
                    $this->warnings[] = array( '#' => $row[1], 'MSG' => $row[2] );
                mysql_free_result($sh);
            // Push it upstream.
                $GLOBALS['_DEBUG']['Database Warnings'][] = array(
                    'Query'    => $this->last_query,
                    'Warnings' => $this->warnings
                );
            }
        }

    // No matter what, a false statement handle means something horrid happened.
        if ($this->sh === false) {
            $this->db->error('SQL Error:');
            return false;
        }
        return true;
    }


/**
 * Fetch a single column
 * @return string
 **/
    public function fetch_col() {
        list($return) = mysql_fetch_row($this->sh);
        return $return;
    }

/**
 * Fetch a single row
 *
 * @link http://www.php.net/manual/en/function.mysql-fetch-row.php
 * @return array
 **/
    public function fetch_row() {
        return mysql_fetch_row($this->sh);
    }

/**
 * Fetch a single assoc row
 *
 * @link http://www.php.net/manual/en/function.mysql-fetch-assoc.php
 * @return array
 **/
    public function fetch_assoc() {
        return mysql_fetch_assoc($this->sh);
    }

/**
 * Fetch a single row as an array containing both numeric and assoc fields
 *
 * @link http://www.php.net/manual/en/function.mysql-fetch-array.php
 * @return array
 **/
    public function fetch_array($result_type = MYSQL_BOTH) {
        return mysql_fetch_array($this->sh, $result_type);
    }

/**
 * Fetch a single row as an object
 *
 * @link http://www.php.net/manual/en/function.mysql-fetch-object.php
 * @return stdClass
 **/
    public function fetch_object() {
        return mysql_fetch_object($this->sh);
    }

/**
 * @link http://www.php.net/manual/en/function.mysql-num-rows.php
 * @return int
 **/
    public function num_rows() {
        return $this->num_rows;
    }

/**
 * @link http://www.php.net/manual/en/function.mysql-data-seek.php
 * @return int
 **/
    public function affected_rows() {
        return $this->affected_rows;
    }

/**
 * @link http://www.php.net/manual/en/function.mysql-insert-id.php
 * @return int
 **/
    public function insert_id() {
        return $this->insert_id;
    }

/**
 * Cleanly close the statement handle.
 **/
    public function finish() {
        if ($this->sh && is_resource($this->sh))
            mysql_free_result($this->sh);
        $this->sh = null;
    }

}

