<?php
/**
 * Staff and Parents Import functions
 *
 * @package Staff and Parents Import module
 * @subpackage includes
 */

/**
 * Convert Excel file to CSV
 * Only 1st sheet!
 * (Deletes Excel file)
 * Or, simply convert CSV to UTF8!
 *
 * @uses PHPExcel class
 *
 * @param string $staff_parents_import_file_path Excel file path.
 *
 * @return string CSV file path.
 */
function ConvertExcelToCSV( $staff_parents_import_file_path )
{
	$excel_extensions = array( '.csv', '.xls', '.xlsx' );

	$file_ext = mb_strtolower( mb_strrchr( $staff_parents_import_file_path, '.' ) );

	if ( ! in_array( $file_ext, $excel_extensions ) )
	{
		// Not an Excel file.
		return $staff_parents_import_file_path;
	}


	if ( $file_ext === '.csv' )
	{
		$csv_file_path = $staff_parents_import_file_path;

		$csv_text = file_get_contents( $csv_file_path );

		/**
		 * Check if CSV is encoded in ISO-8859-1 or windows-1252.
		 *
		 * @todo How could we support other encodings?
		 */
		$is_windows1252_or_iso88591 = mb_check_encoding( $csv_text, 'ISO-8859-1' ) ||
			mb_check_encoding( $csv_text, 'windows-1252' );

		if ( $is_windows1252_or_iso88591 )
		{
			$csv_text_utf8 = utf8_encode( $csv_text );

			file_put_contents( $csv_file_path, $csv_text_utf8 );
		}

		return $csv_file_path;
	}

	require_once 'modules/Staff_Parents_Import/classes/PHPExcel/IOFactory.php';

	$objPHPExcel = PHPExcel_IOFactory::load( $staff_parents_import_file_path );

	$loadedSheetNames = $objPHPExcel->getSheetNames();

	$objWriter = PHPExcel_IOFactory::createWriter( $objPHPExcel, 'CSV' );

	unlink( $staff_parents_import_file_path );

	$csv_file_path = mb_substr(
		$staff_parents_import_file_path,
		0,
		mb_strrpos( $staff_parents_import_file_path, '.' )
	) . '.csv';

	foreach ( $loadedSheetNames as $sheetIndex => $loadedSheetName )
	{
		$objWriter->setSheetIndex( $sheetIndex );
		$objWriter->save( $csv_file_path );

		// Only 1st Excel sheet.
		break;
	}

	return $csv_file_path;
}


/**
 * Detect delimiter of cells.
 *
 * @param string $name CSV file path.
 *
 * @return array Return detected delimiter.
 */
function DetectCSVDelimiter( $name )
{
	$delimiters = array(
		';' => 0,
		',' => 0,
	);

	$handle = fopen( $name, 'r' );

	$first_line = fgets( $handle );

	fclose( $handle );

	foreach ( $delimiters as $delimiter => &$count )
	{
		$count = count( str_getcsv( $first_line, $delimiter ) );
	}

	return array_search( max( $delimiters ), $delimiters );
}



/**
 * Get CSV column name from number
 *
 * @param int $num Column number.
 *
 * @return string Column letter (eg.: "AB")
 */
function GetCSVColumnNameFromNumber( $num )
{
	$numeric = $num % 26;

	$letter = chr( 65 + $numeric );

	$num2 = intval( $num / 26 );

	if ( $num2 > 0 )
	{
		return GetCSVColumnNameFromNumber( $num2 - 1 ) . $letter;
	}
	else
	{
		return $letter;
	}
}


/**
 * Get CSV columns
 *
 * @param  string $csv_file_path  CSV file path.
 *
 * @return array  $csv_columns    CSV columns, eg.: "AB: User Name".
 */
function GetCSVColumns( $csv_file_path )
{
	$csv_handle = fopen( $csv_file_path, 'r' );

	if ( ! $csv_handle )
	{
		return array();
	}

	// Get 1st CSV row, columns delimited by comma (,).
	$csv_columns = fgetcsv( $csv_handle, 0, DetectCSVDelimiter( $csv_file_path ) );

	fclose( $csv_handle );

	$max = count( $csv_columns );

	for ( $i = 0; $i < $max; $i++ )
	{
		// Add column name before value.
		$csv_columns[ $i ] = GetCSVColumnNameFromNumber( $i ) . ': ' . $csv_columns[ $i ];
	}

	return $csv_columns;
}


function CSVImport( $csv_file_path )
{
	global $i;

	$csv_handle = fopen( $csv_file_path, 'r' );

	if ( ! $csv_handle
		|| ! isset( $_REQUEST['values'] ) )
	{
		return 0;
	}

	$row = 0;

	$users = $enrollment = array();

	$columns_values = my_array_flip( $_REQUEST['values'] );

	$delimiter = DetectCSVDelimiter( $csv_file_path );

	// Get CSV row.
	while ( ( $data = fgetcsv( $csv_handle, 0, $delimiter ) ) !== false )
	{
		// Trim.
		$data = array_map( 'trim', $data );

		// Import first row? (generally column names).
		if ( $row === 0 && ! $_REQUEST['import-first-row'] )
		{
			$row++;

			continue;
		}

		// For each column.
		for ( $col = 0, $col_max = count( $data ); $col < $col_max; $col++ )
		{
			if ( isset( $columns_values[ $col ] ) )
			{
				foreach ( (array) $columns_values[ $col ] as $field )
				{
					$users[ $row ][ $field ] = $data[ $col ];
				}
			}
		}

		$row++;
	}

	$enrollment = $_REQUEST['enrollment'];

	$enrollment['START_DATE'] = RequestedDate(
		$_REQUEST['year_enrollment']['START_DATE'],
		$_REQUEST['month_enrollment']['START_DATE'],
		$_REQUEST['day_enrollment']['START_DATE']
	);

	// Sanitize input: Remove HTML tags.
	array_rwalk( $users, 'strip_tags' );
	array_rwalk( $enrollment, 'strip_tags' );

	//var_dump( $users, $enrollment ); //exit;

	$max = count( $users );

	$i = $staff_parents_imported = 0;

	// Import first row? (generally column names).
	if ( ! $_REQUEST['import-first-row'] )
	{
		$max++;

		$i++;
	}

	for ( ; $i < $max; $i++ )
	{
		$user_sql = array();

		$user = $users[ $i ];

		if ( ! _checkUser( $user ) )
		{
			continue;
		}

		// Get Defined STUDENT_ID, check it, or get next ID.
		$user['STUDENT_ID'] = _getUserID( $user );

		$enrollment['GRADE_ID'] = _getGradeLevelID(
			isset( $user['GRADE_ID'] ) ? $user['GRADE_ID'] : $_REQUEST['values']['GRADE_ID']
		);

		unset( $user['GRADE_ID'] );

		// INSERT Enrollment.
		$user_sql[] = _insertUserEnrollment( $user['STUDENT_ID'], $enrollment );

		// INSERT User.
		$user_sql[] = _insertUser( $user );

		DBQuery( implode( '', $user_sql ) );

		$staff_parents_imported++;
	}

	fclose( $csv_handle );

	return $staff_parents_imported;
}


/**
 * Get Grade Level ID
 *
 * Local function
 *
 * @see CSVImport()
 *
 * @param  string $grade_title Grade Level Title.
 *
 * @return string Grade Level ID
 */
function _getGradeLevelID( $grade_title )
{
	// Requested Grade Level ID?
	if ( mb_strpos( $grade_title, 'ID_' ) !== false )
	{
		$grade_id = str_replace( 'ID_', '', $grade_title );
	}
	// Try to deduce Grade Level ID from its Title.
	elseif ( $grade_title )
	{
		$grade_id = DBGet( DBQuery( "SELECT ID
			FROM SCHOOL_GRADELEVELS
			WHERE SCHOOL_ID='" . UserSchool() . "'
			AND UPPER(TITLE)=UPPER('" . $grade_title . "')" ) );

		$grade_id = $grade_id[1]['ID'];
	}

	if ( ! isset( $grade_id ) )
	{
		// Do NOT fail, default to 1st grade level.
		$grade_id = DBGet( DBQuery( "SELECT ID
			FROM SCHOOL_GRADELEVELS
			WHERE SCHOOL_ID='" . UserSchool() . "'
			ORDER BY SORT_ORDER
			LIMIT 1" ) );

		$grade_id = $grade_id[1]['ID'];
	}

	return $grade_id;
}


/**
 * Get Default Enrollment Code
 *
 * Local function
 *
 * @see _insertUserEnrollment()
 *
 * @return string Default Enrollment Code
 */
function _getDefaultEnrollmentCode()
{
	static $enrollment_code = null;

	if ( ! $enrollment_code )
	{
		$enrollment_code = DBGet( DBQuery( "SELECT ID
			FROM STUDENT_ENROLLMENT_CODES
			WHERE SYEAR='" . UserSyear() . "'
			AND DEFAULT_CODE='Y'" ) );

		$enrollment_code = $enrollment_code[1]['ID'];
	}

	return $enrollment_code;
}


/**
 * Get Default Calendar ID
 *
 * Local function
 *
 * @see _insertUserEnrollment()
 *
 * @return string Default Calendar ID
 */
function _getDefaultCalendarID()
{
	static $calendar_id = null;

	if ( ! $calendar_id )
	{
		$calendar_id = DBGet( DBQuery( "SELECT CALENDAR_ID
			FROM ATTENDANCE_CALENDARS
			WHERE SYEAR='" . UserSyear() . "'
			AND SCHOOL_ID='" . UserSchool() . "'
			AND DEFAULT_CALENDAR='Y'" ) );

		$calendar_id = $calendar_id[1]['CALENDAR_ID'];
	}

	return $calendar_id;
}


/**
 * Check for existing User
 * Existing First & Last name
 * or Username
 *
 * Local function
 *
 * @see CSVImport()
 *
 * @param  array $user_fields User Fields.
 *
 * @return string false if incomplete or existing user.
 */
function _checkUser( $user_fields )
{
	global $warning,
		$i;

	// First & Last name cannot be empty.
	if ( ! $user_fields['FIRST_NAME']
		|| ! $user_fields['LAST_NAME'] )
	{
		$warning[] = 'Row #' . ( $i + 1 ) . ': ' .
			dgettext( 'Staff_Parents_Import', 'No names were found.' );

		return false;
	}

	// Or Username.
	if ( isset( $user_fields['USERNAME'] )
		&& $user_fields['USERNAME'] )
	{
		$existing_username = DBGet( DBQuery( "SELECT 'exists'
			FROM STAFF
			WHERE USERNAME='" . $user_fields['USERNAME'] . "'
			AND SYEAR='" . UserSyear() ."'
			UNION SELECT 'exists'
			FROM STUDENTS
			WHERE USERNAME='" . $user_fields['USERNAME'] . "'" ) );

		if ( $existing_username )
		{
			$warning[] = 'Row #' . ( $i + 1 ) . ': ' .
				_( 'A user with that username already exists. Choose a different username and try again.' );

			return false;
		}
	}

	/*$user = DBGet( DBQuery( "SELECT STUDENT_ID
		FROM STUDENTS
		WHERE FIRST_NAME='" . $user_fields['FIRST_NAME'] . "'
		AND LAST_NAME='" . $user_fields['LAST_NAME'] . "'" ) );

	if ( $user )
	{
		$user_id = $user[1]['STUDENT_ID'];
	}*/

	return true;
}


/**
 * Get Defined STUDENT_ID, or get next ID.
 *
 * Local function
 *
 * @see CSVImport()
 *
 * @param  array $user_fields User Fields.
 *
 * @return string User ID
 */
function _getUserID( $user_fields )
{
	$user_id = 0;

	// Defined User ID.
	if ( isset( $user_fields['STUDENT_ID'] ) )
	{
		$user_id = (int) $user_fields['STUDENT_ID'];

		if ( $user_id < 0 )
		{
			$user_id = 0;
		}
	}

	while ( ! $user_id
		|| DBGet( DBQuery( "SELECT STUDENT_ID
			FROM STUDENTS
			WHERE STUDENT_ID='" . $user_id . "'" ) ) )
	{
		$user_id = DBGet( DBQuery( 'SELECT ' . db_seq_nextval( 'STUDENTS_SEQ' ) . ' AS STUDENT_ID' ) );
		$user_id = $user_id[1]['STUDENT_ID'];
	}

	return $user_id;
}


/**
 * Insert User in Database
 *
 * Local function
 *
 * @see CSVImport()
 *
 * @param  array $user_fields User Fields.
 *
 * @return string SQL INSERT
 */
function _insertUser( $user_fields )
{
	static $custom_fields_RET = null;

	if ( ! $custom_fields_RET )
	{
		$custom_fields_RET = DBGet( DBQuery( "SELECT ID,TYPE
			FROM CUSTOM_FIELDS
			ORDER BY SORT_ORDER"), array(), array( 'ID' ) );
	}

	// INSERT users.
	$sql = "INSERT INTO STUDENTS ";

	if ( isset( $user_fields['PASSWORD'] )
		&& $user_fields['PASSWORD'] != '' )
	{
		$user_fields['PASSWORD'] = encrypt_password( $user_fields['PASSWORD'] );
	}

	foreach ( $user_fields as $field => $value )
	{
		if ( ! empty( $value )
			|| $value == '0' )
		{
			$field_type = $custom_fields_RET[ str_replace( 'CUSTOM_', '', $field ) ][1]['TYPE'];

			// Check field type.
			if ( ( $value = _checkFieldType( $value, $field_type ) ) === false )
			{
				continue;
			}

			if ( function_exists( 'DBEscapeIdentifier' ) ) // RosarioSIS 3.0+.
			{
				$fields .= DBEscapeIdentifier( $field ) . ',';
			}
			else
			{
				$fields .= '"' . mb_strtolower( $field ) . '",';
			}

			$values .= "'" . $value . "',";
		}
	}

	$sql .= '(' . mb_substr( $fields, 0, -1 ) . ') values(' . mb_substr( $values, 0, -1 ) . ');';

	return $sql;
}


function _checkFieldType( $value, $field_type )
{
	global $error,
		$i;

	// Check text and apparented fields.
	if ( $field_type  == 'text'
		|| $field_type  == 'exports'
		|| $field_type  == 'select'
		|| $field_type  == 'autos'
		|| $field_type  == 'edits' )
	{
		// If string length > 255, strip.
		return mb_strlen( $value ) > 255 ? mb_substr( $value, 0, 255 ) : $value;
	}
	// Check textarea fields.
	elseif ( $field_type  == 'textarea' )
	{
		// If string length > 5000, strip.
		return mb_strlen( $value ) > 5000 ? mb_substr( $value, 0, 5000 ) : $value;
	}
	// Check numeric fields.
	elseif ( $field_type  == 'numeric'
		&& ( $value = _checkNumeric( $value ) ) === false )
	{
		$error[] = 'Row #' . ( $i + 1 ) . ', ' . $column . ':' .
			_( 'Please enter valid Numeric data.' );

		return false;
	}
	// Check dates.
	elseif ( $field_type == 'date'
		&& ( $value = _checkDate( $value ) ) === false )
	{
		$error[] = 'Row #' . ( $i + 1 ) . ', ' . $column . ':' .
			_( 'Some dates were not entered correctly.' );

		return false;
	}
	// Check checkbox.
	elseif ( $field_type == 'radio' )
	{
		// Return nothing if anything different than Y!
		return mb_strtolower( $value ) === mb_strtolower( 'Y' ) ? 'Y' : '';
	}
	// Check multiple.
	elseif ( $field_type == 'multiple' )
	{
		return _checkMultiple( $value );
	}
	// TODO: codeds?

	return $value;
}



/**
 * Check Multiple
 *
 * @param  string $multiple Multiple.
 *
 * @return Formatted Multiple:
 */
function _checkMultiple( $multiple )
{
	if ( $multiple === '' )
	{
		return '';
	}

	$separator = _detectMultipleSeparator( $multiple );

	$multiple = str_replace( $separator, '||', $multiple );

	return '||' . $multiple . '||';
}



/**
 * Detect separator of multiple values.
 * Allowed separators: semi-colons (;) and pipes (|)
 *
 * @param string $multiple Multiple value.
 *
 * @return array Return detected separator.
 */
function _detectMultipleSeparator( $multiple )
{
	$separators = array(
		';' => 0,
		'|' => 0,
	);

	foreach ( $separators as $separator => &$count )
	{
		$count = count( str_getcsv( $multiple, $separator ) );
	}

	return array_search( max( $separators ), $separators );
}




/**
 * Check Date
 *
 * @param  string $date Date.
 *
 * @return false if not a date, else ISO formatted Date
 */
function _checkDate( $date )
{
	if ( $date === '' )
	{
		return '';
	}

	if ( ! strtotime( $date ) )
	{
		return false;
	}

	return date( 'Y-m-d', strtotime( $date ) );
}


/**
 * Check Numeric
 *
 * @uses _parseFloat()
 * @uses _tofloat()
 *
 * @param  string $numeric Numeric.
 *
 * @return false if not a numeric, else Formatted Numeric
 */
function _checkNumeric( $numeric )
{
	if ( $numeric === '' )
	{
		return '';
	}

	if ( ! is_numeric( $numeric ) )
	{
		$numeric = _formatLongInteger( $numeric );
	}

	if ( ! is_numeric( $numeric ) )
	{
		$numeric = _tofloat( $numeric );
	}

	if ( $numeric === '' )
	{
		return false;
	}

	// Respect format: NUMERIC(20,2).
	if ( strlen( substr( $numeric, 0, strrpos( $num, '.' ) ) ) > 20 )
	{
		return false;
	}

	return $numeric;
}


/**
 * Floatval pro:
 * Takes the last comma or dot (if any) to make a clean float,
 * ignoring thousand separator, currency or any other letter
 *
 * @link http://php.net/manual/en/function.floatval.php#114486
 *
 * @param  string $float Float string.
 *
 * @return float         Parsed Float
 */
function _tofloat( $num )
{
	$dotPos = strrpos($num, '.');

	$commaPos = strrpos($num, ',');

	$sep = (($dotPos > $commaPos) && $dotPos) ? $dotPos :
		((($commaPos > $dotPos) && $commaPos) ? $commaPos : false);

	if ( ! $sep )
	{
		return floatval( preg_replace( "/[^0-9]/", "", $num ) );
	}

	return floatval(
		preg_replace("/[^0-9]/", "", substr($num, 0, $sep)) . '.' .
		preg_replace("/[^0-9]/", "", substr($num, $sep+1, strlen($num)))
	);
}


/**
 * Format long integer exponential notation
 *
 * @link http://stackoverflow.com/questions/4964059/convert-exponential-to-a-whole-number-in-php
 *
 * @param  string $long_int Long integer.
 *
 * @return string           Formatted long integer (no E+XX)
 */
function _formatLongInteger( $long_int )
{
	if ( mb_stripos( $long_int, 'E+' ) !== false )
	{
		// Ex.: 1.234E+12
		return number_format( (float) $long_int, 0, '.', '' );
	}

	return $long_int;
}



/**
 * Insert User Enrollment in Database
 *
 * Local function
 *
 * @see CSVImport()
 *
 * @uses _getDefaultEnrollmentCode()
 * @uses _getDefaultCalendarID()
 *
 * @param array  $user_id User ID.
 * @param array  $enrollment Enrollment array: 'START_DATE','GRADE_ID','ENROLLMENT_CODE','NEXT_SCHOOL','CALENDAR_ID'.
 *
 * @return string SQL INSERT
 */
function _insertUserEnrollment( $user_id, $enrollment )
{
	if ( ! $enrollment['ENROLLMENT_CODE'] )
	{
		$enrollment['ENROLLMENT_CODE'] = _getDefaultEnrollmentCode();
	}

	if ( ! $enrollment['CALENDAR_ID'] )
	{
		$enrollment['CALENDAR_ID'] = _getDefaultCalendarID();
	}

	$sql = "INSERT INTO STUDENT_ENROLLMENT ";

	$fields = 'ID,SYEAR,SCHOOL_ID,STUDENT_ID,';

	$values = "nextval('STUDENT_ENROLLMENT_SEQ'),'" . UserSyear() . "','" .
		UserSchool() . "','" . $user_id . "',";

	$fields .= 'START_DATE,GRADE_ID,ENROLLMENT_CODE,NEXT_SCHOOL,CALENDAR_ID';

	$values .= "'" . $enrollment['START_DATE'] . "','" .
		$enrollment['GRADE_ID'] . "','" .
		$enrollment['ENROLLMENT_CODE'] . "','" .
		$enrollment['NEXT_SCHOOL'] . "','" .
		$enrollment['CALENDAR_ID'] . "'";

	$sql .= '(' . $fields . ') values(' . $values . ');';

	return $sql;
}



function _makeCheckboxInput( $column, $value, $title, $array = 'values' )
{
	return CheckboxInput( $value, $array . '[' . $column . ']', $title, '', true );
}



function _makeDateInput( $column, $title, $allow_na, $array = 'values' )
{
	return DateInput( DBDate(), $array . '[' . $column . ']', $title, false, $allow_na );
}


function _makeSelectInput( $column, $options, $title, $extra = '', $chosen = true, $array = 'values' )
{
	static $chosen_included = false;

	if ( $chosen
		&& ! $chosen_included ) :
		/**
		 * Chosen is a library for making long, unwieldy select boxes more friendly.
		 *
		 * @link https://github.com/harvesthq/chosen
		 */
		?>
		<script src="modules/Staff_Parents_Import/js/chosen.jquery.min.js"></script>
		<link rel="stylesheet" href="modules/Staff_Parents_Import/css/chosen.min.css">
		<script>
			$(function(){
				window.setTimeout(function () {
				var config = {
				  '.chosen-select'           : {}/*,
				  '.chosen-select-deselect'  : {allow_single_deselect:true},
				  '.chosen-select-no-single' : {disable_search_threshold:10},
				  '.chosen-select-no-results': {no_results_text:'Oops, nothing found!'},
				  '.chosen-select-width'     : {width:"95%"}*/
				}
				for (var selector in config) {
				  $(selector).chosen(config[selector]);
				}
				}, 500);
			});
		</script>
		<?php $chosen_included = true;
	endif;

	$chosen_class = $chosen ? ' class="chosen-select"' : '';

	return SelectInput( '', $array . '[' . $column . ']', $title, $options, 'N/A', $extra . ' style="max-width:280px;"' . $chosen_class );
}


function _makeFieldTypeTooltip( $type, $extra_text = '' )
{
	$type_labels = array(
		'select' => _( 'Pull-Down' ),
		'autos' => _( 'Auto Pull-Down' ),
		'edits' => _( 'Edit Pull-Down' ),
		'text' => _( 'Text' ),
		'radio' => _( 'Checkbox' ),
		'codeds' => _( 'Coded Pull-Down' ),
		'exports' => _( 'Export Pull-Down' ),
		'numeric' => _( 'Number' ),
		'multiple' => _( 'Select Multiple from Options' ),
		'date' => _( 'Date' ),
		'textarea' => _( 'Long Text' ),
	);

	$label = $type_labels[ $type ];

	$tooltip_text = $label;

	switch ( $type )
	{
		case 'text':
		case 'textarea':
		case 'numeric':
		case 'textarea':
		case 'select':
		case 'autos':
		case 'edits':
		case 'exports':
		case 'codeds':

		break;

		case 'date':

			// $tooltip_text .= ': <span class="custom-date-format">' . _( 'YYYY-MM-DD' ) . '</span>';
		break;


		case 'radio':

			$tooltip_text .= ': <span class="custom-checkbox-format">Y</span>';
		break;
	}

	return $tooltip_text ? '<div class="tooltip"><i>' . $tooltip_text . $extra_text . '</i></div>' : '';
}


/**
 * My array_flip()
 * Handles multiple occurrences of a value
 *
 * @param  array $array Input array.
 *
 * @return array        Flipped array.
 */
function my_array_flip( $array )
{
	$flipped = array();

	foreach ( $array as $key => $value )
	{
		$flipped[ $value ][] = $key;
	}

	return $flipped;
}

