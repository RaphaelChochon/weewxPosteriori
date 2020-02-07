<?php
/*
 * Génération d'un CSV pour la récupération à posteriori sur Infoclimat
 * des données issues d'une base de données locales WeeWX sous SQLite ou
 * MySQL, avec détection auto des unités et conversion
*/

/*
 * Pour lancer le script: 
 * php /mon/chemin/weewxPosteriori.php
 * 
 * Pour automatiser la création du fichier toutes les heures dans le crontab: 
 * 00 * * * * php /mon/chemin/weewxPosteriori.php >/dev/null 2>&1
*/

// CONFIG DEBUG & RECUP
	$debug         = True;           // True ou False
	$periodeRecup  = 2 * 24 * 3600;  // Doit être en secondes | Par défaut = 2 jours
	$intervalRecup = 10;             // Doit être en minutes | Par défaut à 10 minutes quand récup sur quelques heures, pourra être passé à 60 pour une récup de plusieurs jours

// CONFIG BDD & FILES

	/*
	* Mode SQLite ou MySQL ?
	*/
	$db_type = 'sqlite';  // deux valeurs possibles : sqlite ou mysql

	/*
	* Emplacement de la BDD SQLite de WeeWX (si $db_type = "sqlite")
	*/
	$db_file         = '/home/weewx/archive/weewx.sdb';  // Emplacement du fichier archive SQLite
	$db_table_sqlite = 'archive';                        // Nom de la table (par défaut : weewx)

	/*
	* Parametres de connexion à la base de données WeeWX (si $db_type = "mysql")
	*/
	$db_host        = 'localhost';
	$db_user        = 'weewx';
	$db_pass        = 'passe';
	$db_name_mysql  = 'weewx';
	$db_table_mysql = 'archive';

// ID STATION
	$id_station = '0001';  // ID de la station qui servira de nom au fichier texte créé (le nom du fichier aura le préfixe "recup_weewx_" suivi de l'ID, suivi du suffixe ".csv.gz")

// EMPLACEMENT FICHIER CSV EN LOCAL
	$folder = '/dev/shm/';  // Emplacement du fichier CSV de sortie avec le slash de fin

// CONFIG FTP
	$ftp_enable   = False;  // Activer ou désactiver l'envoi FTP
	$ftp_server   = '';     // Host
	$ftp_username = '';     // Utilisateur
	$ftp_password = '';     // Mot de passe

?>