<?php
/* Copyright (C) 2014      Ion Agorria          <ion@agorria.com>
 *
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 2 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program. If not, see <http://www.gnu.org/licenses/>.
 */

/**
 *      \file       actions_sepadolibarr.class.php
 *      \brief      File of hooks for module
 */

require_once DOL_DOCUMENT_ROOT.'/core/lib/functions.lib.php';
if (!dol_include_once('/sepadolibarr/config.php'))
{
    dol_syslog("Config could not be included");
}

class ActionsSepaDolibarr // extends CommonObject
{
    /**
     * This hook is called before printing/showing left menu block
     * Our job here is to replace the dummy url with a #
     *
     */
    function printLeftBlock($parameters, &$object, &$action, $hookmanager)
    {
        global $langs;
        
        echo '
<script type="text/javascript">
jQuery(document).ready(run);
var max_recursion = 6;
var stop_flag = false;
        		
function run() {
	var main_menu = document.getElementById("id-left");
	recursive_scan(main_menu, max_recursion);
}
        		
function recursive_scan(target_element, count) {
	if (count < 1) {
    	console.log("reached max recursion: " + target_element);
    	return false;
    }
    count = count - 1;
    if (target_element.childNodes.length) {
    	var children = target_element.childNodes;
	    for (var i = 0; i < children.length; i++) {
      		var value = children[i].href;
		    if (value) {
			    var result = value.search("/'.SEPADOLIBARR_REPLACE_URL.'");
			    if (result != -1) {
			    	//console.log("found: " + value + " count: " + count);
			    	children[i].href = "#";
    				return true;
			    }
			}
       		recursive_scan(children[i], count);
	    }
	}
}
</script>
        ';
        
        return 1;
    }
}
?>
