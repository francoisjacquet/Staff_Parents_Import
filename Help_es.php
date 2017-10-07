<?php
/**
 * Spanish Help texts
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
	<i>Importación de Estudiantes</i> le permite importar una base de datos de estudiantes contenida en un archivo <b>Excel</b> o <b>CSV</b>.
</p>
<p>
	Primero, se recomienda proceder a un <b>backup de la base de datos</b> en casos de falla.
</p>
<p>
	Primero, seleccione el archivo Excel (.xls, .xlsx) o CSV (.csv) que contiene los datos de sus estudiantes usando el campo "Seleccionar archivo CSV o Excel".
	Luego, presione el botón "Enviar" para subir el archivo.
	Por favor note que si selecciona un archivo Excel, solamente la primera hoja de cálculo está subida.
</p>
<p>
	En la pantalla siguiente, podrá asociar una columna a cada Campo de Estudiante.
	También podrá definir la opciones de Matricula que aplicarán para todos los estudiantes.
	Por favor nota que los campos en <span style="color:red;">rojo</span> son obligatorios.
	Marque la casilla "Importar la primera línea" arriba en la pantalla si la primera línea del archivo contiene los datos de un estudiante en vez de los nombres de las columnas.
	Por favor nota también que los campos <i>Casilla</i> están marcados con el valor <i>Y</i>.
	Una vez listo, haga clic en el botón "Importar Estudiantes".
</p>

HTML;

endif;
