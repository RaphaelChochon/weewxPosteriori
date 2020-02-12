
weewxPosteriori
===============

## Préambule

**Description**  
Ce script permet de produire un fichier CSV contenant les données de votre station météo. Ce script est fait pour les stations fonctionnant sous le logiciel WeeWX, sur une base de données SQLite ou MySQL. Il permet de combler d'éventuels trous dans les données (suite à une coupure Internet par exemple), et ainsi de parfaire la climato de la station sur le site Infoclimat.

**Système d'unités utilisé**  
Le script est capable de trouver automatiquement le système d'unités utilisé dans la BDD, et convertit les valeurs si nécessaires en système métrique.
Il fonctionne avec les deux principaux types de bases de données supportés par WeeWX : SQLite (BDD par défaut) et MySQL.

**Envoi FTP vers www.infoclimat.fr**  
Le script permet un envoi du fichier CSV sur le FTP d'Infoclimat (ou tout autre FTP).

**Moyenne angulaire pour la direction du vent**  
La direction moyenne du vent sur 10 minutes nécessite de faire une moyenne angulaire, et non une moyenne "traditionnelle". 
La fonction utilisée pour la moyenne d'angles est inspirée de : https://gist.github.com/carloscabo/96e70853f1bb6b6a4d33e4c5c90c6cbb

## Requis
* Une station météo fonctionnant déjà avec Weewx
	* Cette installation de WeeWX (sur un Raspberry Pi, ou autre) peut stocker les données dans une base de données SQLite ou MySQL
	* Cette installation de WeeWX peut utiliser n'importe quel système d'unités, le script détectera l'unité et fera les conversions nécessaires
* Un accès en ligne de commande à votre Raspberry Pi. Si vous avez installé WeeWX ce ne devrait pas être un souci
* Un accès FTP sur Infoclimat (en faire la demande explicite lors de la demande d'intégration au réseau StatIC - les identifiants vous sont ensuite fournis par l'équipe)

## Structure du fichier CSV généré
Le fichier CSV de sortie comprend de nombreuses colonnes dont voici le descriptif, dans l'ordre :
* ``dateTime``, --> date et heure UTC de l'enregistrement
* ``TempNow``, --> la température qu'il faisait à dateTime
* ``HrNow``, --> idem pour l’humidité
* ``TdNow``, --> idem pour le point de rosée
* ``barometerNow``, --> idem pour la pression atmosphérique
* ``rainRateNow``, --> idem pour l'intensité de précipitations
* ``radiationNow``, --> idem pour le rayonnement solaire
* ``UvNow``, --> idem pour l'indice UV
* ``Tn``, --> la température minimal sur l'intervalle en cours
* ``Tx``, --> la température maximal sur l'intervalle en cours
* ``rainCumul``, --> le cumul de pluie sur l'intervalle en cours
* ``rainRateMax``, --> l'intensité de précipitations max sur l'intervalle en cours
* ``radiationMax``, --> le rayonnement max sur l'intervalle en cours
* ``UvMax``, --> l'UV max sur l'intervalle en cours
* ``windGustMax1h``, --> la rafale de vent max sur une heure (si intervalle de une heure, c'est sur l'heure glissante, si intervalle de 10 minutes, c'est aussi sur l'heure glissante) (cf norme OMM)
* ``windGustMaxDir1h``, --> la direction de la rafale de vent max selectionnée dans le paramètre précédent
* ``windGustMaxdt1h``, --> l'heure exacte (UTC) de la rafale de vent max selectionnée dans le paramètre précédent
* ``windGustMax10min``, --> la rafale de vent max sur les dix dernières minutes (peu importe la configuration de l'intervalle) (cf norme OMM)
* ``windGustMaxDir10min``, --> la direction de la rafale de vent max sur les dix dernières minutes, selectionnée dans le paramètre précédent
* ``windGustMaxdt10min``, --> l'heure exacte (UTC) de la rafale de vent max sur les dix dernières minutes selectionnée dans le paramètre précédent
* ``windSpeedAvg10min``, --> la moyenne du vent moyen des dix dernières minutes
* ``windDirAvg10min``, --> la direction de la moyenne du vent moyen des dix dernières minutes
* ``rainCumulMonth``, --> le cumul de pluie sur le mois en cours (depuis minuit UTC du 1er du mois jusqu'à l'enregistrement en cours INCLUS (``dateTime``))
* ``rainCumulYear`` --> le cumul de pluie sur l'année en cours (depuis minuit UTC du 1er janvier jusqu'à l'enregistrement en cours INCLUS(``dateTime``))


## Installation
### Installation de git et php
Git est un logiciel permettant de cloner rapidement les deux fichiers nécessaires au fonctionnement de ce script.
PHP (php-cli dans notre cas) va permettre d'exécuter le script.
Il peut également être nécéssaire d'intaller le paquet ``php-sqlite3`` si vous utilisez une base de données SQLite sur votre instance de WeeWX
```
sudo apt update && sudo apt install git php-cli
## Si utilisation de SQLite, ajouter :
sudo apt install php-sqlite3
```
### Copie des fichiers
Se placer dans un premier temps dans le répertoire ou l'on veut copier le script, puis cloner le répertoire
```
cd /home/pi/
git clone https://github.com/AssociationInfoclimat/weewxPosteriori.git
```
### Configuration
On peut maintenant se placer dans le répertoire du script afin de modifier le fichier de configuration.
```
cd weewxPosteriori
nano config.php
```
**Tous les paramètres sont commentés directement dans le fichier.**

> **:exclamation: Attention :exclamation:**
> Ne pas modifier le fichier ``weewxPosteriori.php``, toute la configuration se trouve dans le script ``config.php``

**Debug et période de récup**
> ``$periodeRecup`` permet d'indiquer sur combien de temps en arrière on souhaite retourner pour la génération du fichier. Au choix entre une heure et XXX jours.  
> ``$intervalRecup`` permet d'indiquer l'intervalle de temps voulu entre chaque enregistrement pour la génération du fichier. Deux options possibles : 10 minutes, ou 60 minutes
>
> La combinaison des deux permet d'affiner le type de récupération voulu. Plus on augmente la période de récupération (et surtout si l'intervalle de récup est configuré à 10 minutes), plus le fichier sera lourd. A prendre en compte sur des installations avec débit Internet réduit.

**Type de base de données**
> Ici il faut renseigné le type de base de données utilisé par votre instance de WeeWX.
>
> Si vous utilisez une base de données SQLite, il faudra renseigner le paramètre de cette manière :
> ```
> $db_type = 'sqlite';
> ```
> Si vous avez personnalisé votre instance de WeeWX pour pouvoir utiliser une base de données MySQL au lieu de SQLite, il faudra renseigner le paramètre de cette manière :
> ```
> $db_type = 'mysql';
> ```

**Paramètres de connexion à la base de données**
> En fonction de votre choix précédent il va falloir renseigner différemment cette partie :
> * Si vous avez une base de données SQLite, il suffit d'indiquer l'emplacement du fichier SQLite, et le nom de la table principal. Pour ce dernier paramètre, il est probable que vous ne l'ayez pas changé et qu'il soit ``archive`` :
> ```
> $db_file = '/var/lib/weewx/weewx.sdb';
> $db_table_sqlite = 'archive';
> ```
> L'emplacement de ce fichier peut varier en fonction de votre méthode d'installation de WeeWX. Référer vous à la documentation [d'installation de WeeWX](http://www.weewx.com/docs/usersguide.htm#installation_methods).
>
>
> * Si vous avez une base de données MySQL, il va falloir renseigner les paramètres de connexion à la base :
> ```
> $db_host = 'localhost';
> $db_user = 'weewx';
> $db_pass = 'passe';
> $db_name_mysql = 'weewx';
> $db_table_mysql = 'archive';
> ```
>
> * ``db_host`` : qui est l'adresse de l'hôte de la base de données. Probablement ``localhost`` si la base de données est hébergée sur votre Raspberry Pi
> * ``db_user`` : le nom d'utilisateur qui a accès à la BDD **en lecture seule de préférence** !
> * ``db_pass`` : le mot de passe de cet utilisateur ;
> * ``db_name_mysql`` : le nom de la base de données. Par défaut WeeWX la nomme ``weewx`` ;
> * ``db_table_mysql`` : Ici il s'agit du nom de la première table contenant tous les enregistrements. Par défaut WeeWX la nomme ``archive``.
>
> Vous pouvez renseigner les même paramètres de connexion que ceux de votre fichier de configuration de WeeWX, mais ce n'est pas recommandé car l'utilisateur ``weewx`` a les droits d'écritures sur la base. L'idéal est plutôt de créer un autre utilisateur avec seulement les droits de lecture sur la base de données (select).
> Cependant, cela fonctionnera aussi avec l'utilisateur ``weewx``.

**Informations d'enregistrement du fichier**
> Cette dernière partie de la configuration concerne le répertoire d'enregistrement du fichier texte dans votre Raspberry Pi.
> Il faut dans un premier temps donner un "ID" à votre station, qui viendra compléter le nom du fichier.
> ```
> $id_station = '0001';
> ```
> Avec l'exemple ci-dessus, le nom du fichier sera : ``weewxPosteriori_0001``.
>
> Il faut maintenant renseigner le répertoire d'enregistrement :
> ```
> $folder = "/var/www/html/IC/";
> ```
> Vous pouvez ici renseigner n'importe quel répertoire **existant** sur votre Raspberry Pi. Ou metre ceci pour enregistrer dans la RAM (fichier temporaire) : ``/dev/shm/``

**FTP Infoclimat**
> Enfin, cette partie concerne la configuration de la connexion au FTP de l'association Infoclimat. Ces identifiants sont à demander directement à l'équipe lors de la demande d'intégration de votre station.  
Le premier paramètre ``$ftp_enable`` permet d'activer l'envoi ou le désactiver. ``True`` pour l'activer, et ``False`` pour le désactiver.
> ```
> $ftp_enable   = False;
> $ftp_server = "ftp.infoclimat.fr";
> $ftp_username = "user";
> $ftp_password = "passe";
> ```


## Test du script
Pour lancer le script en ligne de commande :
```
php /home/pi/weewxPosteriori/weewxPosteriori.php
```

## Automatisation

Pour automatiser le script toutes les heures (à HH:01) dans un cron.  
Edition de la crontab :
```
crontab -e
```
Puis ajouter :
```
01 * * * * php /home/pi/weewxPosteriori/weewxPosteriori.php
```
Pour l'automatiser une seule fois par jour à 23h01 :
```
01 23 * * * php /home/pi/weewxPosteriori/weewxPosteriori.php
```


## Mise à jour du script

Ce script peut subir des modifications, visant à apporter des correctifs de bugs, ou des améliorations pour une meilleure intégration de vos données sur le site Infoclimat.

Dans ce cas, si le script est modifié après que vous l'ayez installé, il faudra suivre la procédure ci-après pour profiter des dernières mises à jour.
Cette manipulation permet de conserver une copie du fichier de configuration ``config.php``.

Il faudra adapter les commandes suivantes à votre configuration (emplacement du script).

Se déplacer dans le répertoire parent du script :
```
cd /home/pi/
```

Puis déplacer l'actuel dossier vers un autre répertoire (dans le but de ne pas perdre le fichier de configuration) :
```
mv weewxPosteriori weewxPosteriori.old
```

Récupération de la nouvelle version du script :
```
git clone https://github.com/AssociationInfoclimat/weewxPosteriori.git
```

Copie de la sauvegarde du fichier de configuration ``config.php`` dans le nouveau répertoire :
```
cp weewxPosteriori.old/config.php weewxPosteriori/config.php
```

C'est tout, le script est de nouveau fonctionnel !


## Changelog
* V1.0 - 2020.02.07
    * Premier dépôt du script