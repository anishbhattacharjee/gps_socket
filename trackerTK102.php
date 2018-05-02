
<?php
###################################################################
# tracker is developped with GPL Licence 2.0
#
# GPL License: http://www.gnu.org/licenses/old-licenses/gpl-2.0.txt
#
# Developped by : Cyril Feraudet
#
###################################################################
# This program is free software; you can redistribute it and/or
# modify it under the terms of the GNU General Public License
# as published by the Free Software Foundation; either version 2
# of the License, or (at your option) any later version.
#
# This program is distributed in the hope that it will be useful,
# but WITHOUT ANY WARRANTY; without even the implied warranty of
# MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
# GNU General Public License for more details.
#
#    For information : cyril@feraudet.com
####################################################################
/**
  * Database creation script
  * CREATE DATABASE `tracker` DEFAULT CHARACTER SET latin1 COLLATE latin1_swedish_ci;
  * USE `tracker`;
  * CREATE TABLE IF NOT EXISTS `gprmc` (
  *   `id` int(11) NOT NULL AUTO_INCREMENT,
  *   `date` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  *   `imei` varchar(17) NOT NULL,
  *   `phone` varchar(20) DEFAULT NULL,
  *   `trackerdate` varchar(10) NOT NULL,
  *   `satelliteDerivedTime` varchar(10) NOT NULL,
  *   `satelliteFixStatus` char(1) NOT NULL,
  *   `latitudeDecimalDegrees` varchar(12) NOT NULL,
  *   `latitudeHemisphere` char(1) NOT NULL,
  *   `longitudeDecimalDegrees` varchar(12) NOT NULL,
  *   `longitudeHemisphere` char(1) NOT NULL,
  *   `speed` float NOT NULL,
  *   `bearing` float NOT NULL,
  *   `utcDate` varchar(6) NOT NULL,
  *   `checksum` varchar(10) NOT NULL,
  *   `gpsSignalIndicator` char(1) NOT NULL,
  *   `other` varchar(50) DEFAULT NULL,
  *   PRIMARY KEY (`id`),
  *   KEY `imei` (`imei`)
  * ) ENGINE=MyISAM  DEFAULT CHARSET=latin1;
  */

/**
  * Listens for requests and forks on each connection
  */

$ip = '172.30.111.186';
$port = 8080;

$__server_listening = true;

error_reporting(E_ALL);
set_time_limit(0);
ob_implicit_flush();
declare(ticks = 1);

if(!isset($argv[1]) || $argv[1] != '-f') {
	become_daemon();
}

/* nobody/nogroup, change to your host's uid/gid of the non-priv user */
change_identity(65534, 65534);

/* handle signals */
pcntl_signal(SIGTERM, 'sig_handler');
pcntl_signal(SIGINT, 'sig_handler');
pcntl_signal(SIGCHLD, 'sig_handler');

/* change this to your own host / port */
server_loop($ip, $port);

/**
  * Change the identity to a non-priv user
  */
function change_identity( $uid, $gid )
{
    if( !posix_setgid( $gid ) )
    {
        print "Unable to setgid to " . $gid . "!\n";
        exit;
    }

    if( !posix_setuid( $uid ) )
    {
        print "Unable to setuid to " . $uid . "!\n";
        exit;
    }
}

/**
  * Creates a server socket and listens for incoming client connections
  * @param string $address The address to listen on
  * @param int $port The port to listen on
  */
function server_loop($address, $port)
{
    GLOBAL $__server_listening;

    if(($sock = socket_create(AF_INET, SOCK_STREAM, 0)) < 0)
    {
        echo "failed to create socket: ".socket_strerror($sock)."\n";
        exit();
    }

    if(($ret = socket_bind($sock, $address, $port)) < 0)
    {
        echo "failed to bind socket: ".socket_strerror($ret)."\n";
        exit();
    }

    if( ( $ret = socket_listen( $sock, 0 ) ) < 0 )
    {
        echo "failed to listen to socket: ".socket_strerror($ret)."\n";
        exit();
    }

    socket_set_nonblock($sock);
   
    echo "waiting for clients to connect\n";

    while ($__server_listening)
    {
        $connection = @socket_accept($sock);
        if ($connection === false)
        {
            usleep(100);
        }elseif ($connection > 0)
        {
            handle_client($sock, $connection);
        }else
        {
            echo "error: ".socket_strerror($connection);
            die;
        }
    }
}

/**
  * Signal handler
  */
function sig_handler($sig)
{
    switch($sig)
    {
        case SIGTERM:
        case SIGINT:
            exit();
        break;

        case SIGCHLD:
            pcntl_waitpid(-1, $status);
        break;
    }
}

/**
  * Handle a new client connection
  */
function handle_client($ssock, $csock)
{
    GLOBAL $__server_listening;

    $pid = pcntl_fork();

    if ($pid == -1)
    {
        /* fork failed */
        echo "fork failure!\n";
        die;
    }elseif ($pid == 0)
    {
        /* child process */
        $__server_listening = false;
        socket_close($ssock);
        interact($csock);
        socket_close($csock);
    }else
    {
        socket_close($csock);
    }
}

function interact($socket)
{
    	/* TALK TO YOUR CLIENT */
	$rec = "";
	socket_recv($socket, $rec, 2048, 0);
	$parts = split(',',$rec);
	$cnx = mysql_connect('bigperl.cqhgvggnarsv.us-east-2.rds.amazonaws.com', 'root', 'bigperlroot');
	/*
	Array
	(
	    [0] => 0908242216
	    [1] => 0033663282263
	    [2] => GPRMC
	    [3] => 212442.000
	    [4] => A
	    [5] => 4849.0475
	    [6] => N
	    [7] => 00219.4763
	    [8] => E
	    [9] => 2.29
	    [10] =>
	    [11] => 220809
	    [12] =>
	    [13] =>
	    [14] => A*70
	    [15] => L
	    [16] => imei:359587017313647
	    [17] => 101Q
	    [18] =>

	)
	*/


	$trackerdate 			= mysql_real_escape_string($parts[0]);
	$phone 				= mysql_real_escape_string($parts[1]);
	$gprmc 				= mysql_real_escape_string($parts[2]);
	$satelliteDerivedTime 		= mysql_real_escape_string($parts[3]);
	$satelliteFixStatus 		= mysql_real_escape_string($parts[4]);
	$latitudeDecimalDegrees 	= mysql_real_escape_string($parts[5]);
	$latitudeHemisphere 		= mysql_real_escape_string($parts[6]);
	$longitudeDecimalDegrees 	= mysql_real_escape_string($parts[7]);
	$longitudeHemisphere 		= mysql_real_escape_string($parts[8]);
	$speed 				= mysql_real_escape_string($parts[9]);
	$bearing 			= mysql_real_escape_string($parts[10]);
	$utcDate 			= mysql_real_escape_string($parts[11]);
	// = $parts[12];
	// = $parts[13];
	$checksum 			= mysql_real_escape_string($parts[14]);
	$gpsSignalIndicator 		= mysql_real_escape_string($parts[15]);
	if(ereg("imei",$parts[16]))
	{
		$imei 				= mysql_real_escape_string($parts[16]);
		$other 				= mysql_real_escape_string($parts[17].' '.$parts[18]);
	}
	else
	{
		$imei 				= mysql_real_escape_string($parts[17]);
		$other 				= mysql_real_escape_string($parts[18].' '.$parts[19]);
	}
	
	$imei = substr($imei,5);

	mysql_select_db('bigperldb', $cnx);
	if($gpsSignalIndicator != 'L')
		mysql_query("INSERT INTO gprmc (date, imei, phone, trackerdate, satelliteDerivedTime, satelliteFixStatus, latitudeDecimalDegrees, latitudeHemisphere, longitudeDecimalDegrees, longitudeHemisphere, speed, Bearing, utcDate, Checksum, gpsSignalIndicator, other) VALUES (now(), '$imei', '$phone', '$trackerdate', '$satelliteDerivedTime', '$satelliteFixStatus', '$latitudeDecimalDegrees', '$latitudeHemisphere', '$longitudeDecimalDegrees', '$longitudeHemisphere', '$speed', '$bearing', '$utcDate', '$checksum', '$gpsSignalIndicator', '$other')", $cnx);
	mysql_close($cnx);
}

/**
  * Become a daemon by forking and closing the parent
  */
function become_daemon()
{
    $pid = pcntl_fork();
   
    if ($pid == -1)
    {
        /* fork failed */
        echo "fork failure!\n";
        exit();
    }elseif ($pid)
    {
        /* close the parent */
        exit();
    }else
    {
        /* child becomes our daemon */
        posix_setsid();
        chdir('/');
        umask(0);
        return posix_getpid();

    }
} 

?>
