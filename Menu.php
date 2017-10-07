<?php
/**
 * Menu.php file
 *
 * Required
 * - Add Menu entries to other modules
 *
 * @package Staff and Parents Import module
 */

// Use dgettext() function instead of _() for Module specific strings translation.
// See locale/README file for more information.
$module_name = dgettext( 'Staff_Parents_Import', 'Staff and Parents Import' );

// Add a Menu entry to the Users module.
if ( $RosarioModules['Users'] ) // Verify Users module is activated.
{
	$menu['Users']['admin'] += array(
		'Staff_Parents_Import/StaffParentsImport.php' => dgettext( 'Staff_Parents_Import', 'Staff and Parents Import' ),
	);
}
