<?php
/**
 * This file is part of Tricho and is copyright (C) Transmogrifier E-Solutions.
 * It is released under the GNU General Public License, version 3 or later.
 * See COPYRIGHT.txt and LICENCE.txt in the tricho directory for more details.
 */

require '../../tricho.php';
require_once ROOT_PATH_FILE. 'tricho/data_objects.php';
require_once ROOT_PATH_FILE. 'tricho/data_setup.php';
test_setup_login (true, SETUP_ACCESS_LIMITED);

$db = Database::parseXML ('../tables.xml');
$table = $db->getTable ($_POST['table']);

if ($table == null) {
    report_error ("Unknown table");
    die ();
}

// check user has access to the table
if ($table->getAccessLevel () == TABLE_ACCESS_SETUP_FULL and
        $_SESSION['setup']['level'] == SETUP_ACCESS_LIMITED) {
    redirect ('./');
}

$table_name = $table->getName ();

// remove row data - this will delete any files, images, and sub-records
try {
    $res = execq("SHOW COLUMNS FROM `{$table_name}`", false, false);
} catch (QueryException $ex) {
    $res = false;
}

if ($res) {
    $primary_key_cols = $table->getPKnames ();
    
    $q = "SELECT ";
    $pk_col_num = 0;
    foreach ($primary_key_cols as $pk_col) {
        if (++$pk_col_num > 1) $q .= ', ';
        $q .= "`{$pk_col}`";
    }
    $q .= "\nFROM `{$table_name}`";
    $res = execq($q);
    
    while ($row = fetch_row($res)) {
        $table->deleteRecord ($row);
    }
}

// delete table from database
$q = "DROP TABLE IF EXISTS `". $table_name. "`";
if (!execq($q)) {
    $_SESSION['setup']['err'] = "Table couldn't be removed from the database";
    redirect ('./');
}

// delete reference to table in meta-data store
// first, clear all links that point to this table
$tables = $db->getTables();
foreach ($tables as $curr_table) {
    if ($table === $curr_table) continue;
    $columns = $curr_table->getColumns();
    foreach ($columns as $col) {
        if (!($col instanceof LinkColumn)) continue;
        $target = $col->getTarget();
        if ($target->getTable() !== $table) continue;
        
        // TODO: possibly make this behave the same as LinkColumn copy
        // behaviour in column_definition.php
        $class = get_class($target);
        $replacement = new $class($col->getName());
        $replacement->setEngName($col->getEngName());
        $replacement->setSqlType($col->getSqlType());
        $replacement->setSqlSize($col->getSqlSize());
        $attribs = $col->getSqlAttributes();
        $key = array_search('AUTO_INCREMENT', $attribs);
        if ($key !== false) {
            unset($attribs[$key]);
        }
        $attribs = implode(' ', $attribs);
        $replacement->setSqlAttributes($attribs);
        $replacement->setMandatory($col->isMandatory());
        $config = $col->getConfigArray();
        $errors = array();
        $replacement->applyConfig($config, $errors);
        $curr_table->replaceColumn($col, $replacement);
    }
}

// then remove the table itself
if ($db->removeTable ($table_name)) {
    try {
        $db->dumpXML ('../tables.xml', null);
        $log_message = "Removed table {$table_name}";
        log_action ($db, $log_message, $q);
    } catch (FileNotWriteableException $ex) {
        $_SESSION['setup']['err'] = "Failed to save XML";
    }
    redirect ('./');
} else {
    $_SESSION['setup']['err'] = "Table data couldn't be removed from meta-data store";
    redirect ('./');
}
?>
