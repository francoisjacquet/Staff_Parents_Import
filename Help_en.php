<?php
/**
 * English Help texts
 *
 * Texts are organized by:
 * - Module
 * - Profile
 *
 * Please use this file as a model to translate the texts to your language
 * The new resulting Help file should be named after the following convention:
 * Help_[two letters language code].php
 *
 * @author François Jacquet
 *
 * @uses Heredoc syntax
 * @see  http://php.net/manual/en/language.types.string.php#language.types.string.syntax.heredoc
 *
 * @package Staff and Parents Import module
 * @subpackage Help
 */

// STAFF AND PARENTS IMPORT ---.
if ( User( 'PROFILE' ) === 'admin' ) :

	$help['Staff_Parents_Import/StaffParentsImport.php'] = <<<HTML
<p>
	<i>Staff and Parents Import</i> allows you to import a user database contained in an <b>Excel</b> spreadsheet or a <b>CSV</b> file.
</p>
<p>
	First thing, it is recommended to <b>backup your database</b> in case something goes wrong.
</p>
<p>
	First, select the Excel (.xls, .xlsx) or CSV (.csv) file containing your users data using the "Select CSV or Excel file".
	Then, click the "Submit" button to upload the file.
	Please note that if you select an Excel file, only the first spreadsheet will be uploaded.
</p>
<p>
	On the next screen, you will be able to associate a column to each User Field.
	Also set the Enrollment options that will apply to every user.
	Please note that the fields in <span style="color:red;">red</span> are mandatory.
	Check the "Import first row" checkbox at the top of the screen if your file's first row contains user data instead of column labels.
	Please also note that the <i>Checkbox</i> fields checked state is <i>Y</i>.
	Once you are set, click the "Import Users" button.
</p>

HTML;

endif;
