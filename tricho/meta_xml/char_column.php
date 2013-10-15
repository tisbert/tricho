<?php
/**
 * This file is part of Tricho and is copyright (C) Transmogrifier E-Solutions.
 * It is released under the GNU General Public License, version 3 or later.
 * See COPYRIGHT.txt and LICENCE.txt in the tricho directory for more details.
 */

/**
 * @package meta_xml
 */

/**
 * Stores meta-data about a column that stores some kind of string data
 * @package meta_xml
 */
class CharColumn extends StringColumn {
    static function getAllowedSqlTypes () {
        return array ('CHAR', 'VARCHAR', 'BINARY', 'VARBINARY');
    }
    
    static function getDefaultSqlType () {
        return 'VARCHAR';
    }
    
}
?>
