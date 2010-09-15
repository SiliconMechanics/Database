<?php
/**
 * Statement handle for PostgreSQL
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
 * $Id: pg.php 8207 2010-09-15 04:22:23Z ccapps $
 **/

class Database_Query_pg extends Database_Query {

    /** @var Database_pg */
    public $db;

    /** @var string Last error message string */
    public $errstr;
    /** @var string Last error message SQLSTATE */
    public $errno;

/**
 * Executes the query that was previously passed to the constructor.
 *
 * @param mixed  $arg      Query arguments to escape and insert at ? placeholders in $query
 * @param mixed  ...       Additional arguments
 **/
    public function execute() {
    // We can't very well perform a query wtihout an active connection.
        if ($this->dbh === false || pg_connection_status($this->dbh) !== PGSQL_CONNECTION_OK) {
            $this->db->error('Lost connection to database.');
            return;
        }
    // Finish any previous statements
        $this->finish();
    // We need to pre-process the arguments for literals and bytefields.  We
    // can't safely pass them into the naitve placeholder system, so we'll
    // have to stick them into the query at the right places before then.
    // Additionally, PG uses the string values 't' and 'f' for boolean true
    // and false, and will choke over PHP true/false when passed in this way.
        $args = Database::smart_args( func_get_args() );
        foreach($args as $i => $arg)
            if(is_bool($arg))
                $args[$i] = $arg === true ? 't' : 'f';
        list($query, $nargs, $nargn) = $this->reprocess_query($args);
        if(!$query)
            return;

    // Prepare the placeholders.  PG uses $1, $2 .. $n as their placeholders.
    // Continuing the example from Database_Query::reprocess_query, we should
    // have the following $query array:
    //      'SELECT * FROM table WHERE a = ',
    //      ' AND b < NOW() AND c > '
    // which will get turned into the following string:
    //      SELECT * FROM table WHERE a = $1 AND b < NOW() AND c > $2
        $this->last_query = '';
        $placeholder_count = 1;
        foreach($query as $i => $chunk) {
            $this->last_query .= $chunk;
            if($placeholder_count <= $nargn) {
                $this->last_query .= '$' . $placeholder_count;
                $placeholder_count++;
            }
        }

    // Wrap the actual query execution in a bit of benchmarking.
        $before = microtime(true);
    // We're only using placeholders here, not prepared statements.  Why not?
    // The possibility of missing / pre-replaced placeholders means that the
    // actual query we get to execute changes each time.
        $this->sh = false;
        $this->errno = null;
        $state = pg_send_query_params($this->dbh, $this->last_query, $nargs);
        if($state) {
        // So, here's some fun.  Like PDO, PG has error reporting bits at both
        // the top level and at the statement level.  However, the normal
        // query method returns false instead of a statement handle, and we
        // need the statement handle to pull errors back.  This means that
        // we need to use the async query sending method and check every single
        // time for an error message.  We can then nuke the statement handle
        // so things that try to look for it to detect errors can work.
            $this->sh = pg_get_result($this->dbh);
            $sqlstate = pg_result_error_field($this->sh, PGSQL_DIAG_SQLSTATE);
            if($sqlstate && $sqlstate != '00000') {
                $this->errno = $sqlstate;
                $this->errstr = pg_result_error_field($this->sh, PGSQL_DIAG_MESSAGE_PRIMARY)
                              . '  [detail='
                              . pg_result_error_field($this->sh, PGSQL_DIAG_MESSAGE_DETAIL)
                              . '] (hint='
                              . pg_result_error_field($this->sh, PGSQL_DIAG_MESSAGE_HINT)
                              . ') at character '
                              . pg_result_error_field($this->sh, PGSQL_DIAG_STATEMENT_POSITION);
                $this->sh = false;
            }
        } else {
            $this->db->error('Could not send query to server.');
            return;
        }
        $after = microtime(true);
        $this->db->mysql_time += $after - $before;

    // The other adapters also fetch the last insert id here, which we can't
    // effectively do, as it's not exposed by PG.  As an alternative, you can
    // use a RETURNING clause in your query to fetch generated values:
    // INSERT INTO table(foo, bar) VALUES(?, ?) RETURNING id;
        if (is_bool($this->sh) || is_null($this->sh)) {
            $this->affected_rows = 0;
            $this->num_rows      = 0;
        }
    // Non-select statements return a boolean, which means we call affected_rows.
    // Selects return a statement handle, which means we call num_rows.
        elseif(is_resource($this->sh)) {
            $this->affected_rows = pg_affected_rows($this->sh);
            $this->num_rows      = pg_num_rows($this->sh);
        } else {
            assert('false; // statement handler was not a bool or resource, it was a: ' . gettype($this->sh));
        }

    // Upstream code may care about warnings.  PG can return multiple notices
    // for any given query.  It's unclear whether or not this will work, as
    // I haven't been able to actually find any query that does emit multiple
    // notices.  If your code suddenly launches into an infinite loop, now you
    // know why.  Fun times indeed.
        if($this->db->enable_warning_logging) {
            $this->warnings = array();
            while(($last_notice = pg_last_notice($this->dbh)) !== false) {
                $this->warnings[] = $last_notice;
            }
            $GLOBALS['_DEBUG']['Database Warnings'][] = array(
                'Query' => $this->last_query,
                'Warnings' => $this->warnings
            );
        }
    // Finally, did it even work?
        if ($this->sh === false) {
            $this->db->error('SQL Error:');
            return false;
        }
        return true;
    }


/**
 * Fetch the first column from the first row
 * @return mixed
 **/
    public function fetch_col() {
        assert('is_resource($this->sh)');
        list($return) = pg_fetch_row($this->sh);
        return $return;
    }


/**
 * Fetch a single row
 *
 * @return array
 **/
    public function fetch_row() {
        assert('is_resource($this->sh)');
        return pg_fetch_row($this->sh);
    }


/**
 * Fetch a single assoc row
 *
 * @return array
 **/
    public function fetch_assoc() {
        assert('is_resource($this->sh)');
        return pg_fetch_assoc($this->sh);
    }


/**
 * Fetch a single row as an array containing both numeric and assoc fields
 *
 * @return array
 **/
    public function fetch_array($result_type=PGSQL_BOTH) {
        assert('is_resource($this->sh)');
        return pg_fetch_array($this->sh, null, $result_type);
    }

/**
 * Fetch a single row as an object
 *
 * @return stdClass
 **/
    public function fetch_object() {
        assert('is_resource($this->sh)');
        return pg_fetch_object($this->sh);
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
    // Yes, this is safe, for pg only.
        return $this->db->insert_id();
    }


/**
 * Close the statement handle
 **/
    public function finish() {
        if ($this->sh && is_resource($this->sh))
            pg_free_result($this->sh);
        unset($this->sh);
    }

}
