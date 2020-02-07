<?php
/*
 * Génération d'un CSV pour la récupération à posteriori sur Infoclimat
 * des données issues d'une base de données locales WeeWX sous SQLite ou
 * MySQL, avec détection auto des unités et conversion
*/

// CONFIG
	require_once "config.php";

// GO -- NE PLUS TOUCHER

// Timestamp debut du script
	$timeStartScript = microtime(true);

// Date UTC
	date_default_timezone_set('UTC');

//
	if ($db_type === "sqlite") {
		$db_table = $db_table_sqlite;
	} elseif ($db_type === "mysql") {
		$db_name  = $db_name_mysql;
		$db_table = $db_table_mysql;
	}

// Connection à la BDD
	if ($db_type === "sqlite") {
		try {
			// Connection
			$db_handle = new SQLite3($db_file);
		}
		catch (Exception $exception) {
			// sqlite3 throws an exception when it is unable to connect
			echo "Erreur de connexion à la base de données SQLite".PHP_EOL;
			if ($debug) {
				echo $exception->getMessage().PHP_EOL.PHP_EOL;
				exit();
			}
		}
	} elseif ($db_type === "mysql") {
		$db_handle = mysqli_connect($db_host,$db_user,$db_pass,$db_name);
		// Vérification de la connexion
		if ($db_handle->connect_errno && $debug) {
			printf("Echec de la connexion: %s\n", $db_handle->connect_error);
			exit();
		}
	}


// FONCTION arondi des minutes
	/**
	 * Round down minutes to the nearest lower interval of a DateTime object.
	 * 
	 * @param \DateTime $dateTime
	 * @param int $minuteInterval
	 * @return \DateTime
	 */
	function roundDownToMinuteInterval(\DateTime $dateTime) {
		global $intervalRecup;
		if ($intervalRecup == 10) {
			$minuteInterval = 10;
		} elseif ($intervalRecup == 60) {
			$minuteInterval = 60;
		}
		return $dateTime->setTime(
			$dateTime->format('H'),
			floor($dateTime->format('i') / $minuteInterval) * $minuteInterval,
			0
		);
	}

// FONCTION : RECUP des intervalles à traiter
	function getDatesFromRange($start, $end, $format = 'Y-m-d H:i:00') {
		global $intervalRecup;
		$array = array();
		if ($intervalRecup == 10) {
			$interval = new DateInterval('PT10M');
		} elseif ($intervalRecup == 60) {
			$interval = new DateInterval('PT1H');
		}

		$realEnd = new DateTime($end);
		$realEnd->add($interval);

		$period = new DatePeriod(new DateTime($start), $interval, $realEnd);

		foreach($period as $date) {
			$array[] = $date->format($format);
		}

		return $array;
	}

// FONCTION moyenne d'angles angulaires
	function mean_of_angles( $angles, $degrees = true ) {
		if ( $degrees ) {
			$angles = array_map("deg2rad", $angles);  // Convert to radians
		}
		$s_  = 0;
		$c_  = 0;
		$len = count( $angles );
		for ($i = 0; $i < $len; $i++) {
			$s_ += sin( $angles[$i] );
			$c_ += cos( $angles[$i] );
		}
		// $s_ /= $len;
		// $c_ /= $len;
		$mean = atan2( $s_, $c_ );
		if ( $degrees ) {
			$mean = rad2deg( $mean );  // Convert to degrees
		}
		if ($mean < 0) {
			$mean_ok = $mean + 360;
		} else {
			$mean_ok = $mean;
		}
		return $mean_ok;
	}

###############################################################
##### MAIN
###############################################################


// CSV FILE PUSH HEADER
	$prepareCSV = array();
	$prepareCSV[] = array ('dateTime', 'TempNow', 'HrNow', 'TdNow', 'barometerNow', 'rainRateNow', 'radiationNow', 'UvNow', 'Tn', 'Tx', 'rainCumul', 'rainRateMax', 'radiationMax', 'UvMax', 'windGustMax1h', 'windGustMaxDir1h', 'windGustMaxdt1h', 'windGustMax10min', 'windGustMaxDir10min', 'windGustMaxdt10min', 'windSpeedAvg10min', 'windDirAvg10min');

// Établissement des timestamp stop et start
	$query_string = "SELECT `dateTime` FROM $db_table ORDER BY `dateTime` DESC LIMIT 1;";
	$result       = $db_handle->query($query_string);

	if (!$result and $debug) {
		// Erreur et debug activé
		echo "Erreur dans la requete ".$query_string.PHP_EOL;
		if ($db_type === "sqlite") {
			echo $db_handle->lastErrorMsg().PHP_EOL.PHP_EOL;
		} elseif ($db_type === "mysql") {
			printf("Message d'erreur : %s\n", $db_handle->error).PHP_EOL.PHP_EOL;
		}
	}
	if ($result) {
		if ($db_type === "sqlite") {
			$row = $result->fetchArray(SQLITE3_ASSOC);
		} elseif ($db_type === "mysql") {
			$row = mysqli_fetch_assoc($result);
		}

		$tsStop = $row['dateTime'];  // stop = dernier relevé dispo en BDD en timestamp Unix
		
		// Arrondi du datetime Stop
		$datetimeStop = new DateTime();
		$datetimeStop->setTimestamp($tsStop);
		$dtStop = roundDownToMinuteInterval($datetimeStop);

		$dtStop = $dtStop->format("d-m-Y H:i:s");
		$tsStop = strtotime($dtStop);

		$tsStart = $tsStop-($periodeRecup);       // start = dernier relevé - le temps demandé dans le fichier de config (en secondes)
		$dtStart = date('d-m-Y H:i:s',$tsStart);
	}

// Génération de la liste (dans un tableau) des dates a générer
	$dtGenerations = getDatesFromRange($dtStart, $dtStop);

// Affichage du tableau contenant les dates à générer
	if ($debug) {
		echo "Liste des dates à générer :".PHP_EOL;
		echo '<pre>';
		print_r($dtGenerations);
		echo '</pre>';
	}
	if ($debug) {
		echo PHP_EOL.PHP_EOL."Affichage du résultat du traitement pour chaque date :".PHP_EOL.PHP_EOL.PHP_EOL;
	}

	// Boucle sur chaque dates à générer
	foreach ($dtGenerations as $dtGeneration) {
		// CONVERT date en timestamp
		$dtStopActu  = $dtGeneration;
		$tsStopActu  = strtotime($dtStopActu);
		$tsStartActu = $tsStopActu-($intervalRecup*60);
		$dtStartActu = date('Y-m-d H:i:s',$tsStartActu);

		if ($debug) {
			echo "Traitement du ".$dtStopActu.PHP_EOL;
		}

		// PARAMS TEMPS REEL
		$query_string = "SELECT * FROM $db_table WHERE `dateTime` = '$tsStopActu';";
		$result       = $db_handle->query($query_string);

		if (!$result && $debug) {
			// Erreur et debug activé
			echo "Erreur dans la requete ".$query_string.PHP_EOL;
			if ($db_type === "sqlite") {
				echo $db_handle->lastErrorMsg().PHP_EOL.PHP_EOL;
			} elseif ($db_type === "mysql") {
				printf("Message d'erreur : %s\n", $db_handle->error).PHP_EOL.PHP_EOL;
			}
		}
		if ($result) {
			$tempNow      = null;
			$HrNow        = null;
			$TdNow        = null;
			$barometerNow = null;
			$rainRateNow  = null;
			$radiationNow = null;
			$UvNow        = null;

			if ($db_type === "sqlite") {
				$row = $result->fetchArray(SQLITE3_ASSOC);
			} elseif ($db_type === "mysql") {
				$row = mysqli_fetch_assoc($result);
			}

			// UNITS
			$unit = $row['usUnits'];

			// PARAMS TEMPS REEL
			if (!is_null ($row['outTemp'])) {
				if ($unit == '1') {
					$tempNow = round(($row['outTemp']-32)/1.8,1);
				}elseif ($unit == '16' || $unit == '17') {
					$tempNow = round($row['outTemp'],1);
				}
			}
			if (!is_null ($row['outHumidity'])) {
				$HrNow  = round($row['outHumidity'],1);
			}
			if (!is_null ($row['dewpoint'])) {
				if ($unit == '1') {
					$TdNow = round(($row['dewpoint']-32)/1.8,1);
				}elseif ($unit == '16' || $unit == '17') {
					$TdNow = round($row['dewpoint'],1);
				}
			}
			if (!is_null ($row['barometer'])) {
				if ($unit == '1') {
					$barometerNow = round($row['barometer']*33.8639,1);
				}elseif ($unit == '16' || $unit == '17') {
					$barometerNow = round($row['barometer'],1);
				}
			}
			if (!is_null ($row['rainRate'])) {
				if ($unit == '1') {
					$rainRateNow = round($row['rainRate']*25.4,1);
				}elseif ($unit=='16') {
					$rainRateNow = round($row['rainRate']*10,1);
				}elseif ($unit=='17') {
					$rainRateNow = round($row['rainRate'],1);
				}
			}
			if (!is_null ($row['radiation'])) {
				$radiationNow  = round($row['radiation'],0);
			}
			if (!is_null ($row['UV'])) {
				$UvNow  = round($row['UV'],1);
			}
		}

		// Calcul Tn
		$query_string = "SELECT MIN(`outTemp`) AS `Tn` FROM $db_table WHERE `dateTime` > '$tsStartActu' AND `dateTime` <= '$tsStopActu';";
		$result       = $db_handle->query($query_string);
		if (!$result && $debug) {
			// Erreur et debug activé
			echo "Erreur dans la requete ".$query_string.PHP_EOL;
			if ($db_type === "sqlite") {
				echo $db_handle->lastErrorMsg().PHP_EOL.PHP_EOL;
			} elseif ($db_type === "mysql") {
				printf("Message d'erreur : %s\n", $db_handle->error).PHP_EOL.PHP_EOL;
			}
		}
		if ($result) {
			if ($db_type === "sqlite") {
				$row = $result->fetchArray(SQLITE3_ASSOC);
			} elseif ($db_type === "mysql") {
				$row = mysqli_fetch_assoc($result);
			}
			$Tn  = null;
			if (!is_null ($row['Tn'])) {
				if ($unit == '1') {
					$Tn = round(($row['Tn']-32)/1.8,1);
				}elseif ($unit == '16' || $unit == '17') {
					$Tn = round($row['Tn'],1);
				}
			}
			
		}

		// Calcul Tx
		$query_string = "SELECT MAX(`outTemp`) AS `Tx` FROM $db_table WHERE `dateTime` > '$tsStartActu' AND `dateTime` <= '$tsStopActu';";
		$result       = $db_handle->query($query_string);
		if (!$result && $debug) {
			// Erreur et debug activé
			echo "Erreur dans la requete ".$query_string.PHP_EOL;
			if ($db_type === "sqlite") {
				echo $db_handle->lastErrorMsg().PHP_EOL.PHP_EOL;
			} elseif ($db_type === "mysql") {
				printf("Message d'erreur : %s\n", $db_handle->error).PHP_EOL.PHP_EOL;
			}
		}
		if ($result) {
			if ($db_type === "sqlite") {
				$row = $result->fetchArray(SQLITE3_ASSOC);
			} elseif ($db_type === "mysql") {
				$row = mysqli_fetch_assoc($result);
			}
			$Tx  = null;
			if (!is_null ($row['Tx'])) {
				if ($unit == '1') {
					$Tx = round(($row['Tx']-32)/1.8,1);
				}elseif ($unit == '16' || $unit == '17') {
					$Tx = round($row['Tx'],1);
				}
			}
		}

		// Calcul RAIN cumul
		$query_string = "SELECT SUM(`rain`) AS `rainCumul` FROM $db_table WHERE `dateTime` > '$tsStartActu' AND `dateTime` <= '$tsStopActu';";
		$result       = $db_handle->query($query_string);
		if (!$result && $debug) {
			// Erreur et debug activé
			echo "Erreur dans la requete ".$query_string.PHP_EOL;
			if ($db_type === "sqlite") {
				echo $db_handle->lastErrorMsg().PHP_EOL.PHP_EOL;
			} elseif ($db_type === "mysql") {
				printf("Message d'erreur : %s\n", $db_handle->error).PHP_EOL.PHP_EOL;
			}
		}
		if ($result) {
			if ($db_type === "sqlite") {
				$row = $result->fetchArray(SQLITE3_ASSOC);
			} elseif ($db_type === "mysql") {
				$row = mysqli_fetch_assoc($result);
			}
			$rainCumul = null;
			if (!is_null ($row['rainCumul'])) {
				if ($unit == '1') {
					$rainCumul = round($row['rainCumul']*25.4,1);
				}elseif ($unit == '16') {
					$rainCumul = round($row['rainCumul']*10,1);
				}elseif ($unit == '17') {
					$rainCumul = round($row['rainCumul'],1);
				}
			}
		}

		// Récup rainRate max
		$query_string = "SELECT MAX(`rainRate`) AS `rainRateMax` FROM $db_table WHERE `dateTime` > '$tsStartActu' AND `dateTime` <= '$tsStopActu';";
		$result       = $db_handle->query($query_string);
		if (!$result && $debug) {
			// Erreur et debug activé
			echo "Erreur dans la requete ".$query_string.PHP_EOL;
			if ($db_type === "sqlite") {
				echo $db_handle->lastErrorMsg().PHP_EOL.PHP_EOL;
			} elseif ($db_type === "mysql") {
				printf("Message d'erreur : %s\n", $db_handle->error).PHP_EOL.PHP_EOL;
			}
		}
		if ($result) {
			if ($db_type === "sqlite") {
				$row = $result->fetchArray(SQLITE3_ASSOC);
			} elseif ($db_type === "mysql") {
				$row = mysqli_fetch_assoc($result);
			}
			$rainRateMax = null;
			if (!is_null ($row['rainRateMax'])) {
				if ($unit == '1') {
					$rainRateMax = round($row['rainRateMax']*25.4,1);
				}elseif ($unit == '16') {
					$rainRateMax = round($row['rainRateMax']*10,1);
				}elseif ($unit == '17') {
					$rainRateMax = round($row['rainRateMax'],1);
				}
			}
		}

		// Récup rayonnement max
		$query_string = "SELECT MAX(`radiation`) AS `radiationMax` FROM $db_table WHERE `dateTime` > '$tsStartActu' AND `dateTime` <= '$tsStopActu';";
		$result       = $db_handle->query($query_string);
		if (!$result && $debug) {
			// Erreur et debug activé
			echo "Erreur dans la requete ".$query_string.PHP_EOL;
			if ($db_type === "sqlite") {
				echo $db_handle->lastErrorMsg().PHP_EOL.PHP_EOL;
			} elseif ($db_type === "mysql") {
				printf("Message d'erreur : %s\n", $db_handle->error).PHP_EOL.PHP_EOL;
			}
		}
		if ($result) {
			if ($db_type === "sqlite") {
				$row = $result->fetchArray(SQLITE3_ASSOC);
			} elseif ($db_type === "mysql") {
				$row = mysqli_fetch_assoc($result);
			}
			$radiationMax = null;
			if (!is_null ($row['radiationMax'])) {
				$radiationMax = round($row['radiationMax'],0);
			}
		}

		// Récup UV max
		$query_string = "SELECT MAX(`UV`) AS `UvMax` FROM $db_table WHERE `dateTime` > '$tsStartActu' AND `dateTime` <= '$tsStopActu';";
		$result       = $db_handle->query($query_string);
		if (!$result && $debug) {
			// Erreur et debug activé
			echo "Erreur dans la requete ".$query_string.PHP_EOL;
			if ($db_type === "sqlite") {
				echo $db_handle->lastErrorMsg().PHP_EOL.PHP_EOL;
			} elseif ($db_type === "mysql") {
				printf("Message d'erreur : %s\n", $db_handle->error).PHP_EOL.PHP_EOL;
			}
		}
		if ($result) {
			if ($db_type === "sqlite") {
				$row = $result->fetchArray(SQLITE3_ASSOC);
			} elseif ($db_type === "mysql") {
				$row = mysqli_fetch_assoc($result);
			}
			$UvMax = null;
			if (!is_null ($row['UvMax'])) {
				$UvMax = round($row['UvMax'],1);
			}
		}

		// Récup rafales max et sa direction sur une heure glissante
		$query_string = "SELECT `dateTime`, `windGust`, `windGustDir` FROM $db_table WHERE `dateTime` > '$tsStartActu' AND `dateTime` <= '$tsStopActu' AND windGust = (SELECT MAX(`windGust`) FROM $db_table WHERE `dateTime` > '$tsStartActu' AND `dateTime` <= '$tsStopActu');";
		$result       = $db_handle->query($query_string);
		if (!$result && $debug) {
			// Erreur et debug activé
			echo "Erreur dans la requete ".$query_string.PHP_EOL;
			if ($db_type === "sqlite") {
				echo $db_handle->lastErrorMsg().PHP_EOL.PHP_EOL;
			} elseif ($db_type === "mysql") {
				printf("Message d'erreur : %s\n", $db_handle->error).PHP_EOL.PHP_EOL;
			}
		}
		if ($result) {
			if ($db_type === "sqlite") {
				$row = $result->fetchArray(SQLITE3_ASSOC);
			} elseif ($db_type === "mysql") {
				$row = mysqli_fetch_assoc($result);
			}
			$windGustMax1h    = null;
			$windGustMaxDir1h = null;
			$windGustMaxdt1h  = null;
			if (!is_null ($row['windGust'])) {
				if ($unit=='1') {
					$windGustMax1h = round($row['windGust']*1.60934,1);
				}elseif ($unit=='16') {
					$windGustMax1h = round($row['windGust'],1);
				}elseif ($unit=='17') {
					$windGustMax1h = round($row['windGust']*3.6,1);
				}
				if (!is_null ($row['windGustDir'])) { $windGustMaxDir1h = round($row['windGustDir'],1); }
				$windGustMaxdt1h = date('Y-m-d H:i:s',$row['dateTime']);
			}
		}

		// Récup rafales max et sa direction sur les dix dernières minutes
		$tsStart10min = $tsStopActu-(10*60);
		$query_string = "SELECT `dateTime`, `windGust`, `windGustDir` FROM $db_table WHERE `dateTime` > '$tsStart10min' AND `dateTime` <= '$tsStopActu' AND windGust = (SELECT MAX(`windGust`) FROM $db_table WHERE `dateTime` > '$tsStart10min' AND `dateTime` <= '$tsStopActu');";
		$result       = $db_handle->query($query_string);
		if (!$result && $debug) {
			// Erreur et debug activé
			echo "Erreur dans la requete ".$query_string.PHP_EOL;
			if ($db_type === "sqlite") {
				echo $db_handle->lastErrorMsg().PHP_EOL.PHP_EOL;
			} elseif ($db_type === "mysql") {
				printf("Message d'erreur : %s\n", $db_handle->error).PHP_EOL.PHP_EOL;
			}
		}
		if ($result) {
			if ($db_type === "sqlite") {
				$row = $result->fetchArray(SQLITE3_ASSOC);
			} elseif ($db_type === "mysql") {
				$row = mysqli_fetch_assoc($result);
			}
			$windGustMax10min    = null;
			$windGustMaxDir10min = null;
			$windGustMaxdt10min  = null;
			if (!is_null ($row['windGust'])) {
				if ($unit=='1') {
					$windGustMax10min = round($row['windGust']*1.60934,1);
				}elseif ($unit=='16') {
					$windGustMax10min = round($row['windGust'],1);
				}elseif ($unit=='17') {
					$windGustMax10min = round($row['windGust']*3.6,1);
				}
				if (!is_null ($row['windGustDir'])) { $windGustMaxDir10min = round($row['windGustDir'],1); }
				$windGustMaxdt10min = date('Y-m-d H:i:s',$row['dateTime']);
			}
		}

		// Calcul vitesse moyenne du vent moyen sur les 10 dernières minutes, peu importe l'intervalle
		$tsStart10min = $tsStopActu-(10*60);
		$query_string = "SELECT AVG(`windSpeed`) AS `windSpeedAvg10min` FROM $db_table WHERE `dateTime` > '$tsStart10min' AND `dateTime` <= '$tsStopActu';";
		$result       = $db_handle->query($query_string);
		if (!$result && $debug) {
			// Erreur et debug activé
			echo "Erreur dans la requete ".$query_string.PHP_EOL;
			if ($db_type === "sqlite") {
				echo $db_handle->lastErrorMsg().PHP_EOL.PHP_EOL;
			} elseif ($db_type === "mysql") {
				printf("Message d'erreur : %s\n", $db_handle->error).PHP_EOL.PHP_EOL;
			}
		}
		if ($result) {
			if ($db_type === "sqlite") {
				$row = $result->fetchArray(SQLITE3_ASSOC);
			} elseif ($db_type === "mysql") {
				$row = mysqli_fetch_assoc($result);
			}
			$windSpeedAvg10min = null;
			if (!is_null ($row['windSpeedAvg10min'])) {
				if ($unit=='1') {
					$windSpeedAvg10min = round($row['windSpeedAvg10min']*1.60934,1);
				}elseif ($unit=='16') {
					$windSpeedAvg10min = round($row['windSpeedAvg10min'],1);
				}elseif ($unit=='17') {
					$windSpeedAvg10min = round($row['windSpeedAvg10min']*3.6,1);
				}
			}
		}

		// Calcul de la direction moyenne du vent moyen sur les 10 dernières minutes, peu importe l'intervalle
		// Requete + mise en tableau de la réponse
		$windDirArray        = null;
		$windDirAvg10minTemp = null;
		$query_string        = "SELECT `windDir` AS `windDir` FROM $db_table WHERE `dateTime` > '$tsStart10min' AND `dateTime` <= '$tsStopActu';";
		$result              = $db_handle->query($query_string);
		if (!$result && $debug) {
			// Erreur et debug activé
			echo "Erreur dans la requete ".$query_string.PHP_EOL;
			if ($db_type === "sqlite") {
				echo $db_handle->lastErrorMsg().PHP_EOL.PHP_EOL;
			} elseif ($db_type === "mysql") {
				printf("Message d'erreur : %s\n", $db_handle->error).PHP_EOL.PHP_EOL;
			}
		}
		if ($result) {
			// Construct tableau
			if ($db_type === "sqlite") {
				while ($row=$result->fetchArray(SQLITE3_ASSOC)) {
					if (!is_null ($row['windDir'])) {
						$windDirArray[] = $row['windDir'];
					}
				}
			} elseif ($db_type === "mysql") {
				while ($row=mysqli_fetch_assoc($result)) {
					if (!is_null ($row['windDir'])) {
						$windDirArray[] = $row['windDir'];
					}
				}
			}
			// Calcul de la moyenne avec la fonction `mean_of_angles` et le tableau
			if (!is_null ($windDirArray)) { $windDirAvg10minTemp = mean_of_angles($windDirArray); }
			// Vérif not null
			$windDirAvg10min = null;
			if (!is_null ($windDirAvg10minTemp)) {
				$windDirAvg10min = round($windDirAvg10minTemp,1);
			}
		}


		if ($debug) {
			echo "Unite BDD  | ".$unit." | (1 = US ; 16 = METRIC ; 17 = METRICWX)".PHP_EOL;
			echo "Intervalle de ".$intervalRecup." minutes (de ".$dtStartActu." à ".$dtStopActu.")".PHP_EOL;
			echo "CLIMATO		| Tn : ".$Tn."°C".PHP_EOL;
			echo "		| Tx : ".$Tx."°C".PHP_EOL;
			echo "		| rainCumul : ".$rainCumul." mm".PHP_EOL;
			echo "		| rainRateMax : ".$rainRateMax." mm".PHP_EOL;
			echo "		| radiationMax : ".$radiationMax.PHP_EOL;
			echo "		| UvMax : ".$UvMax.PHP_EOL;
			echo "		|".PHP_EOL;
			echo "TEMPS REEL	| tempNow : ".$tempNow.PHP_EOL;
			echo "		| HrNow : ".$HrNow.PHP_EOL;
			echo "		| TdNow : ".$TdNow.PHP_EOL;
			echo "		| barometerNow : ".$barometerNow.PHP_EOL;
			echo "		| rainRateNow : ".$rainRateNow.PHP_EOL;
			echo "		| radiationNow : ".$radiationNow.PHP_EOL;
			echo "		| UvNow : ".$UvNow.PHP_EOL;
			echo "		|".PHP_EOL;
			echo "VENT		| Sur une heure gliss. : Raf de ".$windGustMax1h." km/h, dir ".$windGustMaxDir1h."° à ".$windGustMaxdt1h.PHP_EOL;
			echo "VENT		| Sur 10 min.          : Raf de ".$windGustMax10min." km/h, dir ".$windGustMaxDir10min."° à ".$windGustMaxdt10min.PHP_EOL;
			echo "VENT MOY	| Sur 10 min.          : Moy de ".$windSpeedAvg10min." km/h, dir moy ".$windDirAvg10min."°".PHP_EOL.PHP_EOL.PHP_EOL;
		}

		// Insert dans le tableau des valeurs
		$prepareCSV[] = array ($dtStopActu, $tempNow, $HrNow, $TdNow, $barometerNow, $rainRateNow, $radiationNow, $UvNow, $Tn, $Tx, $rainCumul, $rainRateMax, $radiationMax, $UvMax, $windGustMax1h, $windGustMaxDir1h, $windGustMaxdt1h, $windGustMax10min, $windGustMaxDir10min, $windGustMaxdt10min, $windSpeedAvg10min, $windDirAvg10min);
	}

	// Insert dans le fichier CSV
	$csvFile = $folder."/weewxPosteriori_".$id_station.".csv";
	$fp      = fopen($csvFile, 'w');
	foreach ($prepareCSV as $fields) {
		fputcsv($fp, $fields);
	}
	fclose($fp);

	// Push du fichier sur le FTP IC
	if ($ftp_enable) {
		passthru("gzip -f ${csvFile}");
		$conn_id = ftp_connect($ftp_server) or die("Connexion impossible à $ftp_server");
		if (!@ftp_login($conn_id, $ftp_username, $ftp_password)) { die("Identifiants FTP incorects");}
		$remote = "weewxPosteriori_".$id_station.".csv.gz";
		ftp_put($conn_id, $remote, $csvFile.".gz", FTP_ASCII);
		ftp_close($conn_id);
	}

	// FIN
	$timeEndScript        = microtime(true);
	$timeGenerationScript = $timeEndScript - $timeStartScript;
	$pageLoadTime         = number_format($timeGenerationScript, 3);
	if ($debug) {
		echo "Execution en ".$pageLoadTime."secondes".PHP_EOL;
	}

?>
