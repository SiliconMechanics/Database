<?php
/**
 * Database Functions
 * This is a class to hold any function dependencies for the Database project
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

class Database_Functions {

/**
 * Make sure the array is valid for usage in a foreach loop.
 * It needs to be an array and have a positive count
 *
 * @param string $array     Array to check
 * @return bool             Valid for a foreach loop?
 **/
    public static function is_valid_array(&$array = null) {
        return is_array($array) && count($array);
    }

}
