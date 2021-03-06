<?php
/**
 * This file is part of Tricho and is copyright (C) Transmogrifier E-Solutions.
 * It is released under the GNU General Public License, version 3 or later.
 * See COPYRIGHT.txt and LICENCE.txt in the tricho directory for more details.
 */

namespace Tricho\Query;

class RawQuery extends Query {
    protected $query = '';
    
    
    function __construct($query = '') {
        $this->setQuery($query);
    }
    
    
    function setQuery($query) {
        $this->query = (string) $query;
    }
    
    
    function __toString() {
        return $this->query;
    }
}
