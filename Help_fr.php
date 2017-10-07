<?php
/**
 * French Help texts
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
	<i>Import d'Élèves</i> vous permet l'import d'une base de données d'élèves contenue dans un fichier <b>Excel</b> ou <b>CSV</b>.
</p>
<p>
	D'abord, il est recommandé procéder à un <b>backup de la base de données</b> au cas ou surviendrai de problème.
</p>
<p>
	Premièrement, sélectionnez le fichier Excel (.xls, .xlsx) ou CSV (.csv) qui contient les données de vos élèves à l'aide du champ "Sélectionner fichier CSV ou Excel".
	Ensuite, opprimez le bouton "Envoyer" pour uploader le fichier.
	Si vous sélectionnez un fichier Excel, seule la première feuille de calcul sera uploadée.
</p>
<p>
	Sur l'écran suivant, vous pourrez associer une colonne à chaque Champs Élève.
	Vous pourrez aussi définir les options d'Inscription pour tous les élèves importés.
	Notez s'il vous plaît que les champs en <span style="color:red;">rouge</span> sont obligatoires.
	Cochez la case "Importer la première ligne" en haut de l'écran si la première ligne du fichier contient les infos d'un étudiant au lieu de noms de colonnes.
	Notez aussi que les champs de type <i>Case à Cocher</i> seront cochés avec la valeur <i>Y</i>.
	Une fois prêt, opprimez le bouton "Importer Élèves".
</p>

HTML;

endif;
