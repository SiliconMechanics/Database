<?php
/**
 * Database Literal
 * Has magic behavior when sent to Database::escape:
 * No escaping, no quoting.  Goes through as a literal, unless it's null, which
 * gets converted to "NULL".
 *
 * This file depends on/extends code originally written by Chris Petersen for several
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
 * $Id: Literal.php 8206 2010-09-15 04:17:47Z ccapps $
 **/

class Database_Literal {

    private $string;

    public function __construct($string) {
        $this->$string = is_null($string) ? 'NULL' : (string)$string;
    }

    public function __toString() {
        return $this->string;
    }

}
