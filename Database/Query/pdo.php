<?php
/**
 * Statement handle for PDO
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
 * @uses        Database_pdo.php
 * @uses        Database_Query.php
 *
 * $Id: pdo.php 8206 2010-09-15 04:17:47Z ccapps $
 **/

class Database_Query_pdo extends Database_Query {

    /** @var PDO */
    public $dbh;
    /** @var Database_pdo */
    public $db;
    /** @var PDOStatement */
    public $sh;
    /** @var Exception */
    public $last_exception;

/**
 * Executes the query that was previously passed to the constructor.
 *
 * @param mixed  $arg      Query arguments to escape and insert at ? placeholders in $query
 * @param mixed  ...       Additional arguments
 **/
    public function execute() {
    // We can't very well perform a query wtihout an active connection.
        if(!is_object($this->dbh)) {
            $this->db->error('Lost connection to database.');
            return;
        } // end if
    // Finish any previous statements
        $this->finish();
    // We need to pre-process the arguments for literals and bytefields.  We
    // can't safely pass them into the PDO placeholder system, so we'll
    // have to stick them into the query at the right places before then.
        $args = Database::smart_args( func_get_args() );
        list($query, $nargs, $nargn) = $this->reprocess_query($args);
        if(!$query)
            return;
        $this->last_query = $this->unprocess_query($query, $nargn);

    // Wrap the actual query execution in a bit of benchmarking.
        $before = microtime(true);
        $this->last_exception = null;
        try {
        // We will always create a new statement.  The possibility of missing
        // & pre-replaced placeholders breaks our ability to work with actual
        // prepared statements.  Additionally, PDO tends to break easily when
        // working on prepared statements that return multiple result sets
        // combined with multiple concurrent statement handles.  See the
        // comments on the PDOStatement::closeCursor() manual page.
            $this->sh = $this->dbh->prepare($this->last_query);
            $this->sh->execute($nargs);
        } catch(PDOException $e) {
            $this->last_exception = $e;
            $this->db->error('SQL Error:');
        } // end try
        $after = microtime(true);
        $this->db->mysql_time += $after - $before;

    // The other adapters handle these here.  Fun times.
    // Problem 1:
    //  PDO does not provide a clear way to get the last inserted primary key.
    //  There's PDO::lastInsertId(), but it's poorly supported and sometimes
    //  requires extra arguments be passed.  There's undoubtedly a better
    //  way to deal with this than to not deal with it.
        $this->insert_id     = null;
    // Problem 2:
    //  PDOStatement::rowCount()'s manual page says:
    //   "If the last SQL statement executed by the associated PDOStatement
    //    was a SELECT statement, some databases may return the number of
    //    rows returned by that statement. However, this behaviour is not
    //    guaranteed for all databases and should not be relied on for
    //    portable applications."
    //  So, unlike the other adapters, there's no separate call for getting
    //  the number of returned rows vs the number of affected rows.
    //  We'll just use it here and hope it works and that calling code doesn't
    //  care too much.
    // Overall, PDO kind of sucks for this kind of thing.
        $this->affected_rows = $this->sh->rowCount();
        $this->num_rows      = $this->sh->rowCount();

    // PDO has no way to check warnings.  We won't try.
        $this->warnings = array();
    // But we do have errors, though this *should* never be called thanks to the
    // exception catching above.
        $last_notice = $this->sh->errorCode();
        if($last_notice == '00000' || empty($last_notice))
            $last_notice = null;
        if ($last_notice) {
            $this->db->error('SQL Error:');
            return false;
        }
        return true;
    }


/**
 * Fetch a single column
 * @return mixed
 **/
    public function fetch_col() {
        assert('is_object($this->sh)');
        return $this->sh->fetchColumn(0);
    }


/**
 * Fetch a single row
 *
 * @return array
 **/
    public function fetch_row() {
        assert('is_object($this->sh)');
        return $this->sh->fetch(PDO::FETCH_NUM);
    }


/**
 * Fetch a single assoc row
 *
 * @return array
 **/
    public function fetch_assoc() {
        assert('is_object($this->sh)');
        return $this->sh->fetch(PDO::FETCH_ASSOC);
    }


/**
 * Fetch a single row as an array containing both numeric and assoc fields
 *
 * @return array
 **/
    public function fetch_array($result_type=PDO::FETCH_BOTH) {
        assert('is_object($this->sh)');
        return $this->sh->fetch($result_type);
    }


/**
 * Fetch a single row as an object
 *
 * @return stdClass
 **/
    public function fetch_object() {
        assert('is_object($this->sh)');
        return $this->sh->fetchObject();
    }


/**
 * @return int
 **/
    public function num_rows() {
        return $this->num_rows;
    }


/**
 * @return int
 **/
    public function affected_rows() {
        return $this->affected_rows;
    }


/**
 * @return int
 **/
    public function insert_id() {
        assert('false; // Database_Query_pdo::insert_id unimplemented');
        return false;
    }


/**
 * Close the statement handle
 **/
    public function finish() {
        if ($this->sh && is_object($this->sh))
            $this->sh->closeCursor();
        unset($this->sh);
    }


}
