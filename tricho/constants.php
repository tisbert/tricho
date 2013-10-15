<?php
/**
 * This file is part of Tricho and is copyright (C) Transmogrifier E-Solutions.
 * It is released under the GNU General Public License, version 3 or later.
 * See COPYRIGHT.txt and LICENCE.txt in the tricho directory for more details.
 */

/**
 * @package constants
 */

/**
 * Version
 * 
 * X._._ Major version: Major change in system architecture, or rewrite from scratch.<br>
 * _.X._ Minor version: Feature changes or additions. Upgrades may require tuning, but generally shouldn't.<br>
 * _._.X Bug fixes, spelling errors, setup or tool features, etc. Should almost always be directly upgradeable.
 */
define ('TRICHO_VERSION', '0.1.0-dev');

// Use standard settings when values haven't been explicitly specified.
if (!defined ('ADMIN_DIR')) define ('ADMIN_DIR', 'admin/');
if (!defined ('SQL_ENGINES')) define ('SQL_ENGINES', 'InnoDB, MyISAM, ARCHIVE');
if (!defined ('SQL_CHARSETS')) define ('SQL_CHARSETS', 'utf8, latin1');
if (!defined ('SQL_DEFAULT_COLLATION')) {
    define ('SQL_DEFAULT_COLLATION', 'utf8_unicode_ci');
}


// Menu types
define ('MENU_TYPE_SELECT', 1);
define ('MENU_TYPE_LIST', 2);

// Table display types
define ('TABLE_DISPLAY_STYLE_ROWS', 1);
define ('TABLE_DISPLAY_STYLE_TREE', 2);

// Setup access levels - used for test_setup_login to determine authentication
define ('SETUP_ACCESS_LIMITED', 1);
define ('SETUP_ACCESS_FULL', 2);

// Table access levels - used on a per-table basis on main, main_edit, etc. to determine whether
// an admin or setup user has rights to view or edit the data stored in each table
define ('TABLE_ACCESS_ADMIN', 1);
define ('TABLE_ACCESS_SETUP_LIMITED', 2);
define ('TABLE_ACCESS_SETUP_FULL', 3);

// IP lockouts (after multiple login failures) apply for 24 hrs by default
define ('DEFAULT_LOCKOUT_PERIOD', 1440);

// SQL types for whole numbers

/** Bytes: 4, Signed: -2147483648 to 2147483647, Unsigned: 0 to 4294967295 */
define ('SQL_TYPE_INT', 1);
define ('SQL_TYPE_INTEGER', 1);

/** Bytes: 1, Signed: -128 to 127, Unsigned: 0 to 255 */
define ('SQL_TYPE_TINYINT', 2);

/** Bytes: 2, Signed: -32768 to 32767, Unsigned: 0 to 65535 */
define ('SQL_TYPE_SMALLINT', 3);

/** Bytes: 3, Signed: -8388608 to 8388607, Unsigned: 0 to 16777215 */
define ('SQL_TYPE_MEDIUMINT', 4);

/** Bytes: 8, Signed: -9223372036854775808 to 9223372036854775807, Unsigned: 0 to 18446744073709551615 */
define ('SQL_TYPE_BIGINT', 5);

/** Uses 1 bit of storage (in theory), value is either 0 or 1 */
define ('SQL_TYPE_BIT', 6);

// SQL types for numbers with decimal places
/** Fixed point number, i.e. an exact value with a set number of digits before and after the decimal point */
define ('SQL_TYPE_DECIMAL', 7);

/** Floating point number (4 bytes, single precision) */
define ('SQL_TYPE_FLOAT', 8);

/** Floating point number (8 bytes, double precision) */
define ('SQL_TYPE_DOUBLE', 9);

// SQL string types
define ('SQL_TYPE_CHAR', 10);
define ('SQL_TYPE_VARCHAR', 11);
define ('SQL_TYPE_BINARY', 12);
define ('SQL_TYPE_VARBINARY', 13);

// SQL types that can hold strings of arbitrary(-ish) length
define ('SQL_TYPE_TEXT', 14);
define ('SQL_TYPE_TINYTEXT', 15);
define ('SQL_TYPE_MEDIUMTEXT', 16);
define ('SQL_TYPE_LONGTEXT', 17);
define ('SQL_TYPE_BLOB', 18);
define ('SQL_TYPE_TINYBLOB', 19);
define ('SQL_TYPE_MEDIUMBLOB', 20);
define ('SQL_TYPE_LONGBLOB', 21);


// SQL types for date and time
/** YYYY-MM-DD */
define ('SQL_TYPE_DATE', 22);

/** YYYY-MM-DD hh:mm:ss */
define ('SQL_TYPE_DATETIME', 23);

/** hh:mm:ss */
define ('SQL_TYPE_TIME', 24);

// Logic condition types
define ('LOGIC_TREE_COND', 1);
define ('LOGIC_TREE_AND', 2);
define ('LOGIC_TREE_OR', 3);

// Query ordering
define ('ORDER_DIR_ASC', 1);
define ('ORDER_DIR_DESC', 2);

// Query joins
define ('SQL_JOIN_TYPE_INNER', 1);
define ('SQL_JOIN_TYPE_LEFT', 2);

// options for finding query select fields from a partially built query
define ('FIND_SELECT_TYPE_COLUMN', 1);
define ('FIND_SELECT_TYPE_LITERAL', 2);
define ('FIND_SELECT_TYPE_FUNCTION', 4);
define ('FIND_SELECT_TYPE_ANY', 7);

// Different ways to display data in a <TD> on main.php or similar
// Standard display, left aligned
define ('MAIN_COL_TYPE_DEFAULT', 1);
// Right aligned
define ('MAIN_COL_TYPE_NUMERIC', 2);
// Right aligned with $
define ('MAIN_COL_TYPE_CURRENCY', 3);
// File name with possible icon in previous or next column
define ('MAIN_COL_TYPE_FILE', 4);
// Image name with possible thumbnail in previous or next column
define ('MAIN_COL_TYPE_IMAGE', 5);
// Ordering arrows (possibly, depending on previous and next rows)
define ('MAIN_COL_TYPE_ORDER', 6);
// Yes or No
define ('MAIN_COL_TYPE_BINARY', 7);

// Picture in a column
define ('MAIN_PIC_NONE', 1);
define ('MAIN_PIC_LEFT', 2);
define ('MAIN_PIC_RIGHT', 3);
define ('MAIN_PIC_ONLY_IMAGE', 4);

// Actions
define ('MAIN_PAGE_MAIN', 1);
define ('MAIN_PAGE_ACTION', 2);
define ('MAIN_PAGE_ADD', 3);
define ('MAIN_PAGE_ADD_ACTION', 4);
define ('MAIN_PAGE_EDIT', 5);
define ('MAIN_PAGE_EDIT_ACTION', 6);
define ('MAIN_PAGE_SEARCH_ACTION', 7);
define ('MAIN_PAGE_ORDER', 8);
define ('MAIN_PAGE_JOINER_ACTION', 9);
define ('MAIN_PAGE_INLINE_SEARCH', 10);
define ('MAIN_PAGE_EXPORT', 11);

// What is allowed
define ('MAIN_OPTION_ALLOW_ADD', 1);
define ('MAIN_OPTION_ALLOW_DEL', 2);
define ('MAIN_OPTION_CONFIRM_DEL', 3);
define ('MAIN_OPTION_CSV', 4);

// Alternate buttons
define ('MAIN_TEXT_ADD_BUTTON', 1);
define ('MAIN_TEXT_DEL_BUTTON', 2);
define ('MAIN_TEXT_DEL_POPUP', 3);
define ('MAIN_TEXT_CSV_BUTTON', 4);
define ('MAIN_TEXT_NO_RECORDS', 5);
define ('MAIN_TEXT_NOT_FOUND', 6);
define ('MAIN_TEXT_ADD_CONDITION', 7);
define ('MAIN_TEXT_APPLY_CONDITIONS', 8);
define ('MAIN_TEXT_CLEAR_CONDITIONS', 9);

// Logic conditions
/* These values must match the JavaScript equivalents (search_functions.js) for the filtering system to work */
define ('LOGIC_CONDITION_LIKE', 1);
define ('LOGIC_CONDITION_EQ', 2);
define ('LOGIC_CONDITION_STARTS_WITH', 3);
define ('LOGIC_CONDITION_ENDS_WITH',     4);
define ('LOGIC_CONDITION_BETWEEN', 5);
define ('LOGIC_CONDITION_LT', 6);
define ('LOGIC_CONDITION_GT', 7);
define ('LOGIC_CONDITION_LT_OR_EQ', 8);
define ('LOGIC_CONDITION_GT_OR_EQ', 9);
define ('LOGIC_CONDITION_NOT_LIKE', 10);
define ('LOGIC_CONDITION_NOT_EQ', 11);
define ('LOGIC_CONDITION_IS', 12);
define ('LOGIC_CONDITION_IN', 13);    // Not (yet) implemented on the JavaScript side
define ('LOGIC_CONDITION_NOT_BETWEEN', 14);

// Columns that link to another table will show the other table's records as either a select list
// or radio buttons
define ('LINK_FORMAT_SELECT', 1);
define ('LINK_FORMAT_RADIO', 2);
define ('LINK_FORMAT_INLINE_SEARCH', 3);

// Ordering methods for linked columns
define ('ORDER_DESCRIPTORS', 1);
define ('ORDER_LINKED_TABLE', 2);

// Default rules for HTML tags received from users via rich text input fields
if (!defined ('HTML_TAGS_ALLOW')) {
    define (
        'HTML_TAGS_ALLOW',
        'a:href;hreflang;type,blockquote,br,code,del,em,hr,img:src;alt,li,ol,'.
        'p,strong,sub,sup,table,tbody,td:align,th:align,thead,tr:valign,ul'
    );
}
if (!defined ('HTML_TAGS_REPLACE')) define ('HTML_TAGS_REPLACE', 'b=strong,i=em');
if (!defined ('HTML_TAGS_DENY'))        define ('HTML_TAGS_DENY', 'script');

// sub-action types
define ('SA_DEL_ENTIRE', 1);
define ('SA_DEL_ONE_RECORD', 2);

// Validation result status codes
define ('VALIDATION_NOT_CHANGED', 1);
define ('VALIDATION_CHANGED', 2);
define ('VALIDATION_RUBBISH', 3);

define ('CONVERT_OUTPUT_FAIL', 1);
define ('CONVERT_OUTPUT_WARN', 2);
define ('CONVERT_OUTPUT_NONE', 3);

// export types
define ('EXPORT_TYPE_CSV', 1);
define ('EXPORT_TYPE_TSV', 2);

// number of records to be displayed in admin/main.php
if (!defined ('MAIN_VIEW_PER_PAGE_MIN')) define ('MAIN_VIEW_PER_PAGE_MIN', 5);
if (!defined ('MAIN_VIEW_PER_PAGE_MAX')) define ('MAIN_VIEW_PER_PAGE_MAX', 5000);

// Determine maximum uploadable file size from INI settings
$_ini_file_size_settings = array(
    ini_get('upload_max_filesize'),
    ini_get('post_max_size')
);
$_ini_file_size_values = array();

// The ini settings for upload sizes are saved as strings;
// convert to number of bytes
foreach ($_ini_file_size_settings as $_ini_max_upload) {
    $_matches = array ();
    preg_match ('/^([0-9]+)([kmg])$/i', $_ini_max_upload, $_matches);
    list($_junk, $_ini_max_upload, $_type) = $_matches;
    $_ini_max_upload = (int) $_ini_max_upload;
    if ($_ini_max_upload > 0) {
        switch (strtolower ($_type)) {
        case 'g':
            $_ini_max_upload *= 1024;
        case 'm':
            $_ini_max_upload *= 1024;
        case 'k':
            $_ini_max_upload *= 1024;
        }
    }
    $_ini_file_size_values[] = $_ini_max_upload;
}
define ('INI_MAX_UPLOAD_SIZE', min($_ini_file_size_values));
unset($_ini_file_size_settings, $_ini_file_size_values, $_ini_max_upload);
unset($_matches, $_type, $_junk);

$enforceable_data_types = array (
    'any',
    'alpha',
    'alphanum',
    'alphanum_space',
    'binary',
    'currency',
    'date_time',
    'decimal',
    'email',
    'filename',
    'integer',
    'person_name',
    'person_title',
    'phone',
    'title',
    'url',
    'username',
);
// also 'eval .*', 'person_name_part'

$recognised_SQL_types = array (
    'Integer types' => array (
        'INT',
        'TINYINT',
        'SMALLINT',
        'MEDIUMINT',
        'BIGINT',
        'BIT'
    ),
    
    'Decimal types' => array (
        'DECIMAL',
        'FLOAT',
        'DOUBLE'
    ),
    
    'Text types' => array (
        'CHAR',
        'VARCHAR',
        
        'TEXT',
        'TINYTEXT',
        'MEDIUMTEXT',
        'LONGTEXT'
    ),
    
    'Binary types' => array (
        'BINARY',
        'VARBINARY',
        'BLOB',
        'TINYBLOB',
        'MEDIUMBLOB',
        'LONGBLOB'
    ),
    
    'Date and time types' => array (
        'DATE',
        'DATETIME',
        'TIME'
    )
);

$image_cache_scales = array (
    'm' => array ('name' => 'Minutes', 'seconds' => 60),
    'h' => array ('name' => 'Hours',     'seconds' => 3600),
    'd' => array ('name' => 'Days',        'seconds' => 86400)
);


/**
 * Converts a string name for an SQL type to its appropriate constant definition.
 * E.g. 'varchar' becomes SQL_TYPE_VARCHAR
 * 
 * This is used when reading in the tables.xml
 * 
 * @param string $str the name of the type
 * @return int the type from a constant definition
 */
function sql_type_string_to_defined_constant ($str) {
    $str = strtoupper ($str);
    $type_name = 'SQL_TYPE_'. $str;
    if (defined ($type_name)) {
        $type = constant ($type_name);
    } else if ($str == 'REAL' or $str == 'DOUBLE PRECISION') {
        $type = SQL_TYPE_DOUBLE;
    } else {
        $type = -1;
        throw new Exception ("Unknown SQL type: {$str}");
    }
    return $type;
}


/**
 * Converts a constant definition for an SQL type to its appropriate string name.
 * E.g. SQL_TYPE_VARCHAR becomes 'VARCHAR'
 * 
 * This is used when saving the tables.xml
 * 
 * @param int the type from a constant definition
 * @return string $str the name of the type
 */
function sql_type_string_from_defined_constant ($type) {
    $type_names = array (
        SQL_TYPE_INT => 'INT',
        SQL_TYPE_TINYINT => 'TINYINT',
        SQL_TYPE_SMALLINT => 'SMALLINT',
        SQL_TYPE_MEDIUMINT => 'MEDIUMINT',
        SQL_TYPE_BIGINT => 'BIGINT',
        SQL_TYPE_BIT => 'BIT',
        
        SQL_TYPE_DECIMAL => 'DECIMAL',
        SQL_TYPE_FLOAT => 'FLOAT',
        SQL_TYPE_DOUBLE => 'DOUBLE',
        
        SQL_TYPE_CHAR => 'CHAR',
        SQL_TYPE_VARCHAR => 'VARCHAR',
        SQL_TYPE_BINARY => 'BINARY',
        SQL_TYPE_VARBINARY => 'VARBINARY',
        
        SQL_TYPE_TEXT => 'TEXT',
        SQL_TYPE_TINYTEXT => 'TINYTEXT',
        SQL_TYPE_MEDIUMTEXT => 'MEDIUMTEXT',
        SQL_TYPE_LONGTEXT => 'LONGTEXT',
        SQL_TYPE_BLOB => 'BLOB',
        SQL_TYPE_TINYBLOB => 'TINYBLOB',
        SQL_TYPE_MEDIUMBLOB => 'MEDIUMBLOB',
        SQL_TYPE_LONGBLOB => 'LONGBLOB',
        
        SQL_TYPE_DATE => 'DATE',
        SQL_TYPE_DATETIME => 'DATETIME',
        SQL_TYPE_TIME => 'TIME'
    );
    return (string) $type_names[$type];
}
?>
