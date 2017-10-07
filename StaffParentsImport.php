<?php
/**
 * Staff and Parents Import
 *  1. Upload CSV or Excel file
 *  2. Associate CSV columns to User Fields
 *  3. Import users
 *
 * @package Staff and Parents Import module
 */

require_once 'ProgramFunctions/FileUpload.fnc.php';

require_once 'modules/Staff_Parents_Import/includes/StaffParentsImport.fnc.php';

DrawHeader( ProgramTitle() ); // Display main header with Module icon and Program title.

// Upload.
if ( $_REQUEST['modfunc'] === 'upload' )
{
	$error = array();

	if ( ! isset( $_SESSION['StaffParentsImport.php']['csv_file_path'] )
		|| ! $_SESSION['StaffParentsImport.php']['csv_file_path'] )
	{
		// Save original file name.
		$_SESSION['StaffParentsImport.php']['original_file_name'] = $_FILES['users-import-file']['name'];

		// Upload CSV file.
		$staff_parents_import_file_path = FileUpload(
			'users-import-file',
			sys_get_temp_dir() . DIRECTORY_SEPARATOR, // Temporary directory.
			array( '.csv', '.xls', '.xlsx' ),
			0,
			$error
		);

		if ( empty( $error ) )
		{
			// Convert Excel files to CSV.
			$csv_file_path = ConvertExcelToCSV( $staff_parents_import_file_path );

			// Open file.
			if ( ( fopen( $csv_file_path, 'r' ) ) === false )
			{
				$error[] = dgettext( 'Staff_Parents_Import', 'Cannot open file.' );
			}
			else
			{
				$_SESSION['StaffParentsImport.php']['csv_file_path'] = $csv_file_path;
			}
		}
	}

	if ( $error )
	{
		if ( function_exists( 'RedirectURL' ) )
		{
			// @since 3.3.
			RedirectURL( 'modfunc' );
		}
		else
		{
			// @deprecated.
			unset( $_REQUEST['modfunc'] );
			unset( $_SESSION['_REQUEST_vars']['modfunc'] );
		}
	}
}
// Import.
elseif ( $_REQUEST['modfunc'] === 'import' )
{
	// Open file.
	if ( ! isset( $_SESSION['StaffParentsImport.php']['csv_file_path'] )
		|| fopen( $_SESSION['StaffParentsImport.php']['csv_file_path'], 'r' ) === false )
	{
		$error[] = dgettext( 'Staff_Parents_Import', 'Cannot open file.' );
	}
	else
	{
		// Import users.
		$staff_parents_imported = CSVImport( $_SESSION['StaffParentsImport.php']['csv_file_path'] );

		$staff_parents_imported_txt = sprintf(
			dgettext( 'Staff_Parents_Import', '%s users were imported.' ),
			$staff_parents_imported
		);

		if ( $staff_parents_imported )
		{
			$note[] = button( 'check' ) . '&nbsp;' . $staff_parents_imported_txt;
		}
		else
		{
			$warning[] = $staff_parents_imported_txt;
		}

		// Remove CSV file.
		unlink( $_SESSION['StaffParentsImport.php']['csv_file_path'] );
	}

	if ( function_exists( 'RedirectURL' ) )
	{
		// @since 3.3.
		RedirectURL( 'modfunc' );
	}
	else
	{
		// @deprecated.
		unset( $_REQUEST['modfunc'] );
		unset( $_SESSION['_REQUEST_vars']['modfunc'] );
	}

	unset( $_SESSION['StaffParentsImport.php']['csv_file_path'] );
}

// Display error messages.
echo ErrorMessage( $error, 'error' );

// Display warnings.
echo ErrorMessage( $warning, 'warning' );

// Display note.
echo ErrorMessage( $note, 'note' );


if ( ! $_REQUEST['modfunc'] )
{
	/*if ( isset( $_SESSION['StaffParentsImport.php']['csv_file_path'] ) )
	{
		// Remove CSV file.
		@unlink( $_SESSION['StaffParentsImport.php']['csv_file_path'] );*/

		unset( $_SESSION['StaffParentsImport.php']['csv_file_path'] );
	//}

	// Form.
	echo '<form action="Modules.php?modname=' . $_REQUEST['modname'] .
		'&modfunc=upload" method="POST" enctype="multipart/form-data">';

	if ( AllowEdit( 'School_Setup/DatabaseBackup.php' ) )
	{
		DrawHeader( '<a href="Modules.php?modname=School_Setup/DatabaseBackup.php">' .
			_( 'Database Backup' ) . '</a>' );
	}

	DrawHeader( '<input type="file" name="users-import-file" accept=".csv, application/vnd.openxmlformats-officedocument.spreadsheetml.sheet, application/vnd.ms-excel" required title="' .
			sprintf( _( 'Maximum file size: %01.0fMb' ), FileUploadMaxSize() ) . '" />
		<span class="loading"></span>
		<br /><span class="legend-red">' . dgettext( 'Staff_Parents_Import', 'Select CSV or Excel file' ) . '</span>' );

	echo '<br /><div class="center">' . SubmitButton( _( 'Submit' ) ) . '</div>';

	echo '</form>';
}
// Uploaded: show import form!
elseif ( $_REQUEST['modfunc'] === 'upload' )
{
	// Get CSV columns.
	$csv_columns = GetCSVColumns( $_SESSION['StaffParentsImport.php']['csv_file_path'] );

	if ( ! $csv_columns )
	{
		$error = array( 'No columns were found in the uploaded file.' );

		echo ErrorMessage( $error );
	}
	else
	{
		// Form.
		echo '<form action="Modules.php?modname=' . $_REQUEST['modname'] .
			'&modfunc=import" method="POST" class="import-users-form">';

		$rows_number = file( $_SESSION['StaffParentsImport.php']['csv_file_path'] );

		$rows_number = count( $rows_number );

		DrawHeader(
			$_SESSION['StaffParentsImport.php']['original_file_name'] . ': ' .
				sprintf( dgettext( 'Staff_Parents_Import', '%s rows' ), $rows_number ),
			SubmitButton(
				dgettext( 'Staff_Parents_Import', 'Import Users' ),
				'',
				' class="import-users-button"'
			)
		);
		?>
		<script>
		$(function(){
			$('.import-users-form').submit(function(){

				var alertTxt = <?php echo json_encode( dgettext(
						'Staff_Parents_Import',
						'Are you absolutely ready to import users? Make sure you have backed up your database!'
					) ); ?>;

				// Alert.
				if ( ! window.confirm( alertTxt ) ) return false;

				var $buttons = $('.import-users-button'),
					buttonTxt = $buttons.val(),
					seconds = 5,
					stopButtonHTML = <?php echo json_encode( SubmitButton(
						dgettext( 'Staff_Parents_Import', 'Stop' ),
						'',
						'class="stop-button"'
					) ); ?>;

				$buttons.css('pointer-events', 'none').attr('disabled', true).val( buttonTxt + ' ... ' + seconds );

				var countdown = setInterval( function(){
					if ( seconds == 0 ) {
						clearInterval( countdown );
						$('.import-users-form').off('submit').submit();
						return;
					}

					$buttons.val( buttonTxt + ' ... ' + --seconds );
				}, 1000 );

				// Insert stop button.
				$( stopButtonHTML ).click( function(){
					clearInterval( countdown );
					$('.stop-button').remove();
					$buttons.css('pointer-events', '').attr('disabled', false).val( buttonTxt );
					return false;
				}).insertAfter( $buttons );
			});
		});
		</script>
		<?php

		// Import first row? (generally column names).
		DrawHeader( CheckboxInput(
				'',
				'import-first-row',
				dgettext( 'Staff_Parents_Import', 'Import first row' ),
				'',
				true
			),
			'<a href="Modules.php?modname=' . $_REQUEST['modname'] . '&modfunc=upload">' .
				dgettext( 'Staff_Parents_Import', 'Reset form' ) . '</a>'
		);

		// Premium: Custom date format (update tooltips on change), may be necessary for Japan: YY-MM-DD?
		// Premium: Custom checkbox checked format (update tooltips on change).

		echo '<br /><table class="widefat cellspacing-0 center">';

		/**
		 * User Fields.
		 */
		echo '<tr><td><h4>' . _( 'User Fields' ) . '</h4></td></tr>';

		echo '<tr><td>' .
			_makeSelectInput( 'FIRST_NAME', $csv_columns,  _( 'First Name' ), 'required' ) .
		'</td></tr>';

		echo '<tr><td>' .
			_makeSelectInput( 'MIDDLE_NAME', $csv_columns, _( 'Middle Name' ) ) .
		'</td></tr>';

		echo '<tr><td>' .
			_makeSelectInput( 'LAST_NAME', $csv_columns, _( 'Last Name' ), 'required' ) .
		'</td></tr>';

		$tooltip = _makeFieldTypeTooltip(
			'numeric',
			'; ' . dgettext( 'Staff_Parents_Import', 'IDs are automatically generated if you select "N/A".' )
		);

		echo '<tr><td>' .
			_makeSelectInput( 'STUDENT_ID', $csv_columns, sprintf( _( '%s ID' ), Config( 'NAME' ) ) . $tooltip ) .
		'</td></tr>';

		echo '<tr><td>' .
			_makeSelectInput( 'USERNAME', $csv_columns, _( 'Username' ) ) .
		'</td></tr>';

		echo '<tr><td>' .
			_makeSelectInput( 'PASSWORD', $csv_columns, _( 'Password' ) ) .
		'</td></tr>';

		/**
		 * Custom User Fields.
		 */
		$fields_RET = DBGet( DBQuery( "SELECT cf.ID,cf.TITLE,cf.TYPE,cf.SELECT_OPTIONS,
			cf.REQUIRED,cf.CATEGORY_ID,sfc.TITLE AS CATEGORY_TITLE
			FROM CUSTOM_FIELDS cf, STUDENT_FIELD_CATEGORIES sfc
			WHERE cf.CATEGORY_ID=sfc.ID
			ORDER BY sfc.SORT_ORDER, cf.SORT_ORDER") );

		$category_id_last = 0;

		foreach ( (array) $fields_RET as $field )
		{
			if ( $category_id_last !== $field['CATEGORY_ID'] )
			{
				// Add Category name as User Fields separator!
				echo '<tr><td><h4>' . ParseMLField( $field['CATEGORY_TITLE'] ) . '</h4></td></tr>';
			}

			$category_id_last = $field['CATEGORY_ID'];

			$tooltip = _makeFieldTypeTooltip( $field['TYPE'] );

			echo '<tr><td>' .
				_makeSelectInput(
					'CUSTOM_' . $field['ID'],
					$csv_columns,
					ParseMLField( $field['TITLE'] ) . $tooltip,
					$field['REQUIRED'] ? 'required' : ''
				) .
			'</td></tr>';
		}


		/**
		 * Enrollment.
		 */
		echo '<tr><td><h4>' . _( 'Enrollment' ) . '</h4></td></tr>';

		$gradelevels_RET = DBGet( DBQuery( "SELECT ID,TITLE
			FROM SCHOOL_GRADELEVELS
			WHERE SCHOOL_ID='" . UserSchool() . "'
			ORDER BY SORT_ORDER" ) );

		$options = array();

		foreach ( (array) $gradelevels_RET as $gradelevel )
		{
			// Add 'ID_' prefix not to mix with CSV columns.
			$options[ 'ID_' . $gradelevel['ID'] ] = $gradelevel['TITLE'];
		}

		// Add CSV columns to set Grade Level.
		$options += $csv_columns;

		echo '<tr><td>' .
			_makeSelectInput( 'GRADE_ID', $options, _( 'Grade Level' ), 'required', true ) .
		'</td></tr>';

		$calendars_RET = DBGet( DBQuery( "SELECT CALENDAR_ID,DEFAULT_CALENDAR,TITLE
			FROM ATTENDANCE_CALENDARS
			WHERE SYEAR='" . UserSyear() . "'
			AND SCHOOL_ID='" . UserSchool() . "'
			ORDER BY DEFAULT_CALENDAR ASC" ) );

		$options = array();

		foreach ( (array) $calendars_RET as $calendar )
		{
			$options[ $calendar['CALENDAR_ID'] ] = $calendar['TITLE'];

			if ( $calendar['DEFAULT_CALENDAR'] )
			{
				$options[ $calendar['CALENDAR_ID'] ] .= ' (' . _( 'Default' ) . ')';
			}
		}

		$no_chosen = false;

		echo '<tr><td>' .
			_makeSelectInput(
				'CALENDAR_ID',
				$options,
				_( 'Calendar' ),
				'required',
				$no_chosen,
				'enrollment'
			) .
		'</td></tr>';

		$schools_RET = DBGet( DBQuery( "SELECT ID,TITLE
			FROM SCHOOLS
			WHERE ID!='" . UserSchool() . "'
			AND SYEAR='" . UserSyear() . "'" ) );

		$options = array(
			UserSchool() => _( 'Next grade at current school' ),
			'0' => _( 'Retain' ),
			'-1' => _( 'Do not enroll after this school year' ),
		);

		foreach ( (array) $schools_RET as $school )
		{
			$options[ $school['ID'] ] = $school['TITLE'];
		}

		echo '<tr><td>' .
			_makeSelectInput(
				'NEXT_SCHOOL',
				$options,
				_( 'Rolling / Retention Options' ),
				'required',
				$no_chosen,
				'enrollment'
		) .
		'</td></tr>';

		$enrollment_codes_RET = DBGet( DBQuery( "SELECT ID,TITLE AS TITLE
			FROM STUDENT_ENROLLMENT_CODES
			WHERE SYEAR='" . UserSyear() . "'
			AND TYPE='Add'
			ORDER BY SORT_ORDER" ) );

		$options = array();

		foreach ( (array) $enrollment_codes_RET as $enrollment_code )
		{
			$options[ $enrollment_code['ID'] ] = $enrollment_code['TITLE'];
		}

		echo '<tr><td>' .
			_makeDateInput( 'START_DATE', '', true, 'enrollment' ) . ' -<br />' .
			_makeSelectInput(
				'ENROLLMENT_CODE',
				$options,
				_( 'Attendance Start Date this School Year' )
					. '<div class="tooltip"><i>' .
					dgettext( 'Staff_Parents_Import', 'If the date is left empty, users will not be enrolled (inactive).' ) .
					'</i></div>',
				'',
				$no_chosen,
				'enrollment'
			) .
		'</td></tr>';

		echo '</table>';

		echo '<br /><div class="center">' . SubmitButton(
			dgettext( 'Staff_Parents_Import', 'Import Users' ),
			'',
			' class="import-users-button"'
		) . '</div></form>';
	}
}
