<?php
/**
 * This file is part of Tricho and is copyright (C) Transmogrifier E-Solutions.
 * It is released under the GNU General Public License, version 3 or later.
 * See COPYRIGHT.txt and LICENCE.txt in the tricho directory for more details.
 */

require '../../tricho.php';
require_once ROOT_PATH_FILE. 'tricho/data_objects.php';
test_setup_login(true, SETUP_ACCESS_LIMITED);
require 'order_functions.php';
require_once ROOT_PATH_FILE. 'tricho/data_setup.php';

$db = Database::parseXML();
$table = $db->getTable($_GET['t']);

list ($in_list, $out_list) = get_in_out_lists ($table, "search");

/*
example URL (broken up):
    order_iframe_action.php?
    -->num=1+
    -->sect=in
    go=down
    id=1
*/

// echo "(Before) Table: <pre>"; print_r ($table); echo "</pre><br>\n";

if ($_GET['sect'] == 'in') {
    // perform action and don't care
    $table->searchMove ($_GET['go'], $_GET['id']);
} else if ($_GET['sect'] == 'out') {
    if ($_GET['go'] == 'up') {
        $item = $out_list[$_GET['id']];
        $table->searchAdd ($item);
    }
}

// echo "(After) Table: <pre>"; print_r ($table); echo "</pre><br>\n";
$url = 'table_edit_search_iframe.php?t=' . urlencode($_GET['t']);
$db->dumpXML('', $url);

?>
