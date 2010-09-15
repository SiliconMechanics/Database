<?php
/**
 * Database abstraction interface: statement handle wrapper.
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
 *
 * $Id: Query.php 8207 2010-09-15 04:22:23Z ccapps $
 **/

abstract class Database_Query {

/** @var Database   Our parent Database object */
    public $db  = NULL;

/** @var resource   The related database connection handle */
    public $dbh = NULL;

/** @var resource   The current active statement handle */
    public $sh = NULL;

/** @var array      The query string, (depending on the engine, broken apart where arguments should be inserted) */
    public $query = array();

/** @var string     The most recent query sent to the server */
    public $last_query = '';

/** @var array      An array of warnings that this query generated */
    public $warnings = array();

/** @var int        Number of arguments required by $query */
    public $num_args_needed = 0;

/** @var int        Cache of xxx_num_rows() so multiple queries don't trip over each other */
    protected $num_rows;

/** @var int        Cache of xxx_affected_rows() so multiple queries don't trip over each other */
    protected $affected_rows;

/** @var int        Cache of xxx_insert_id() so multiple queries don't trip over each other */
    protected $insert_id;

/**
 * Constructor.  Parses $query and splits it at ? characters for later
 * substitution in execute().
 *
 * @param Database $dbh    The parent Database object
 * @param string   $query  The query string
 **/
    public function __construct(&$db, $query) {
        $this->dbh              = $db->dbh;
        $this->db               = $db;
        $q                      = $this->process_query($query);
        $this->query            = $q[0];
        $this->num_args_needed  = $q[1];
    }


/**
 * Process the passed query, pulling out placeholders.
 *
 * @param string $query
 * @return array
 **/
    public function process_query($query) {
        $split_query = array();
        $num_args_needed =  max(0, substr_count($query, '?') - substr_count($query, '\\?'));
    // Break apart our query on the placeholders for easy replacement later.
        if($num_args_needed > 0) {
            foreach (preg_split('/(\\\\?\\?)/', $query, -1, PREG_SPLIT_DELIM_CAPTURE) as $part) {
                switch ($part) {
                    case '?':
                        break;
                    case '\\?':
                        $split_query[min(0, count($this->query) - 1)] .= '?';
                        break;
                    default:
                        $split_query[] = $part;
                }
            }
        }
        elseif(substr_count($query, '\\?') > 0) {
        // It's possible that the query contains nothing but escaped placeholders.
            $split_query = array(str_replace('\\?', '?', $query));
        }
        else {
            $split_query = array($query);
        }
        return array($split_query, $num_args_needed);
    }


/**
 * Create a re-processed query based on the passed paramaters.  This method
 * is only used by adapters that attempt to use native placeholders.
 * Some paramaters have special or different behavior.  For example, a
 * Database_Literal can not get quoted, and thus can't be passed in to any
 * functions (like native placeholders) that perform escaping.
 *
 * @param array $args
 * @return array Array with three elements: 0: new query, 1: new arguments, 2: expected argument count
 **/
    public function reprocess_query($args) {
        $query = $this->query;
        $nargs = array();
    // Example:
    //      SELECT * FROM table WHERE a = ? AND b < ? AND c > ?
    // gets split into this in process_query:
    //      'SELECT * FROM TABLE WHERE a = ',
    //      ' AND b < '
    //      ' AND c > '
    // If $args[1] (b) is Database_Literal 'NOW()', the following code will
    // modify $query[1] to read:
    //      ' AND b < NOW()'
        foreach($args as $i => $arg) {
            if($arg instanceof Database_Literal)
                $query[$i] .= (string)$arg;
            elseif($arg instanceof Database_ByteField)
                $query[$i] .= $this->db->escape_bytefield($arg);
            else
                $nargs[$i] = $arg;
        }
    // We now have our query and our new argument list, but if we try to join
    // the two together, we'll end up with a mess: there are now fewer placeholders
    // than places to put them.  The placeholder count is right, so we need to 
    // re-split the query array on where placeholders should be.  To do this,
    // we'll rebuild it as a string and use the placeholder processor again.
        $nquery = '';
        foreach($query as $i => $chunk) {
            $nquery .= $chunk;
            if(array_key_exists($i, $nargs))
                $nquery .= '?';
        }
        if(array_key_exists(++$i, $nargs))
            $nquery .= '?';
    // Now we can split it apart again...
        list($query, $num_args_needed) = $this->process_query($nquery);
        if($num_args_needed != count($nargs)) {
            $this->db->error("Database_Query::reprocess_query: Derived " . count($nargs) . " arguments, but it looks like I need {$num_args_needed} instead.", E_USER_ERROR);
        }
    // Okay, we should be good to go.
        $nargs = array_values($nargs);
        return array(
            &$query,
            &$nargs,
            $num_args_needed
        );
    }


/**
 * Unprocess the statement query, returning it as a string, placeholders in-tact.
 *
 * @param array $query Optional query, uses the built-in one otherwise.
 * @param int $nargn Optional number of arguments expected, uses the built-in count otherwise.
 * @return string
 **/
    public function unprocess_query($query = null, $nargn = null) {
        if(is_null($nargn))
            $nargn = $this->num_args_needed;
        $args = array();
        if($nargn > 0)
            $args = array_fill(0, $nargn, '?');
        return $this->replaceholders($args, $query);
    }


/**
 * Replace placeholders in the current query with the passed values.
 *
 * @param array $args
 * @param array $query Optional query, uses the built-in one otherwise.
 * @param int $nargn Optional number of arguments expected, uses the built-in count otherwise.
 * @return string
 **/
    public function replaceholders($args, $query = null, $nargn = null) {
        if(is_null($nargn))
            $nargn = $this->num_args_needed;
        if(is_null($query))
            $query = $this->query;
        if(!is_array($args))
            $args = array();

        if(count($args) != $nargn) {
            $this->db->error('Database_Query::replaceholders called with '.count($args)." arguments, but requires {$nargn}.", E_USER_ERROR);
            return;
        }
        if(!count($args))
            return join('', $query);
        $sql = '';
        foreach ($query as $part) {
            $sql .= $part;
            if(count($args)) {
                $arg = array_shift($args);
                if($arg !== '?')
                    $arg = $this->db->escape($arg, false);
                $sql .= $arg;
            }
        }
        return $sql;
    }


/***************************************************************************
 * Abstract methods.  Adapters must implement these.
 **/


/**
 * Executes the query that was previously passed to the constructor.
 *
 * @param mixed  $arg      Query arguments to escape and insert at ? placeholders in $query
 * @param mixed  ...       Additional arguments
 **/
    abstract public function execute();


/**
 * Fetch the first row in the first column
 * @return scalar
 **/
    abstract public function fetch_col();


/**
 * Fetch a single row
 * @return array
 **/
    abstract public function fetch_row();


/**
 * Fetch a single row as an associative array
 * @return array
 **/
    abstract public function fetch_assoc();


/**
 * Fetch a single row as an array containing both numeric and assoc fields
 * @return array
 **/
    abstract public function fetch_array($result_type = null);


/**
 * Fetch a single row as an object
 * @return stdClass
 **/
    abstract public function fetch_object();


/**
 * Grab the number of rows returned by the current query.
 * @return int
 **/
    abstract public function num_rows();


/**
 * Grab the number of rows affected by the current query.
 * @return int
 **/
    abstract public function affected_rows();


/**
 * Grab the last automatic identifier generated
 * @return int
 **/
    abstract public function insert_id();


/**
 * Cleanly close the statement handle.
 **/
    abstract public function finish();

}
