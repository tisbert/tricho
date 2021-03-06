<?php
/**
 * This file is part of Tricho and is copyright (C) Transmogrifier E-Solutions.
 * It is released under the GNU General Public License, version 3 or later.
 * See COPYRIGHT.txt and LICENCE.txt in the tricho directory for more details.
 */

use Tricho\Meta\Database;

require_once '../tricho.php';
test_admin_login();

$db = Database::parseXML();
$table = $db->getTable ($_POST['_t']);

// get the number
$num = (int) $_POST['num'];
if ($num < MAIN_VIEW_PER_PAGE_MIN) $num = MAIN_VIEW_PER_PAGE_MIN;
if ($num > MAIN_VIEW_PER_PAGE_MAX) $num = MAIN_VIEW_PER_PAGE_MAX;

// set it
$_SESSION[ADMIN_KEY]['num_per_page'][$table->getName ()] = $num;

redirect ($_POST['_c']);

?>
