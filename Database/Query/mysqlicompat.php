<?php
/**
 * Statement handle for MySQLi, the "Improved" MySQL extension.
 * The name "mysqlicompat" was originally used for historical reasons and is being
 * maintained only for backwards compat.  How fitting.
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
 * @uses        Database_mysqlicompat.php
 * @uses        Database_Query.php
 *
 * $Id: mysqlicompat.php 8207 2010-09-15 04:22:23Z ccapps $
 **/

class Database_Query_mysqlicompat extends Database_Query {

    /** @var Database_mysqlicompat */
    public $db;

/**
 * Executes the query that was previously passed to the constructor.
 *
 * @param mixed  $arg      Query arguments to escape and insert at ? placeholders in $query
 * @param mixed  ...       Additional arguments
 **/
    public function execute() {
    // We can't very well perform a query wtihout an active connection.
        if( !(is_object($this->dbh) && $this->dbh instanceof mysqli) ) {
            $this->db->error('Lost connection to database.');
            return;
        }
    // Finish any previous statements
        $this->finish();
    // Replace in the arguments.  Yes, we're doing it manually.  While MySQLi
    // has prepared statements, the binding method requires that we know the
    // type of value being passed.  We can't guess it with certainty.
        $this->last_query = $this->replaceholders( Database::smart_args( func_get_args() ) );
        if(!$this->last_query)
            return;
    // Wrap the actual query execution in a bit of benchmarking.
        $before = microtime(true);
        $this->sh = mysqli_query($this->dbh, $this->last_query);
        $after = microtime(true);
        $this->db->mysql_time += $after - $before;

    // Non-select statements return a boolean, which means we call affected_rows.
        if (is_bool($this->sh)) {
            $this->insert_id     = mysqli_insert_id($this->dbh);
            $this->affected_rows = mysqli_affected_rows($this->dbh);
        }
    // Selects return a statement handle, which means we call num_rows.
        else {
            $this->num_rows      = mysqli_num_rows($this->sh);
        }

    // Can we pull down warnings from the server?
        $this->warnings = array();
        if ($this->db->enable_warning_logging && mysqli_warning_count($this->dbh)) {
            if ($sh = mysqli_query($this->dbh, 'SHOW WARNINGS')) {
                while ($row = mysqli_fetch_row($sh))
                    $this->warnings[] = array( '#' => $row[1], 'MSG' => $row[2] );
                mysqli_free_result($sh);
            // Push it upstream.
                $GLOBALS['_DEBUG']['Database Warnings'][] = array(
                    'Query'    => $this->last_query,
                    'Warnings' => $this->warnings
                );
            }
        }
    // Did it even work?
        if ($this->sh === false) {
            $this->db->error('SQL Error:');
            return false;
        }
        return true;
    }


/**
 * Fetch the first row in the first column
 * @return scalar
 **/
    public function fetch_col() {
        assert('is_object($this->sh)');
        list($return) = mysqli_fetch_row($this->sh);
        return $return;
    }


/**
 * Fetch a single row
 *
 * @link http://www.php.net/manual/en/function.mysqli-fetch-row.php
 * @return array
 **/
    public function fetch_row() {
        assert('is_object($this->sh)');
        return mysqli_fetch_row($this->sh);
    }


/**
 * Fetch a single assoc row
 *
 * @link http://www.php.net/manual/en/function.mysqli-fetch-assoc.php
 * @return assoc
 **/
    public function fetch_assoc() {
        assert('is_object($this->sh)');
        return mysqli_fetch_assoc($this->sh);
    }


/**
 * Fetch a single row as an array containing both numeric and assoc fields
 *
 * @link http://www.php.net/manual/en/function.mysqli-fetch-array.php
 * @return assoc
 **/
    public function fetch_array($result_type=MYSQLI_BOTH) {
        assert('is_object($this->sh)');
        return mysqli_fetch_array($this->sh, $result_type);
    }


/**
 * Fetch a single row as an object
 *
 * @link http://www.php.net/manual/en/function.mysqli-fetch-object.php
 * @return object
 **/
    public function fetch_object() {
        assert('is_object($this->sh)');
        return mysqli_fetch_object($this->sh);
    }


/**
 * Grab the number of rows returned by the current query.
 * @return int
 **/
    public function num_rows() {
        return $this->num_rows;
    }


/**
 * Grab the number of rows affected by the current query.
 * @return int
 **/
    public function affected_rows() {
        return $this->affected_rows;
    }


/**
 * Grab the last automatic identifier generated
 * @return int
 **/
    public function insert_id() {
        return $this->insert_id;
    }


/**
 * Cleanly close the statement handle.
 **/
    public function finish() {
        if ($this->sh && (is_object($this->sh) || is_resource($this->sh)))
            mysqli_free_result($this->sh);
        unset($this->sh);
    }

}
