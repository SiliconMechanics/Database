<?php
/**
 * Database ByteField
 * Has magic behavior when sent to Database::escape:
 * Implementation specific escaping for non-BLOB binary data.
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
 * $Id: ByteField.php 8207 2010-09-15 04:22:23Z ccapps $
 **/

class Database_ByteField {

    private $bytes;

    public function __construct($bytes) {
        $this->bytes = $bytes;
    }

    public function __toString() {
        return $this->bytes;
    }

}
