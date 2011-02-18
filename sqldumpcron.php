<?php

/**
 * myBackware
 *
 * This software is licensed under the Artistic License.
 * A copy of the license for this software is included
 * in the distribution.
 *
 * @author David Norman
 * @copyright 2004 David Norman
 * @package myBackware
 * @license Artistic License
 */

/*** CHANGE ME ********************************************/
$workingdirectory  = 'C:\WINDOWS\temp\\';
$dbuser   = 'root';
$dbpass   = 'password';
$os	  = 'W';           // U for Unix system, W for Windows

// File compression
$compress_file = true;    // true or false to compress the archive
$compress_method = 'bz2'; // bz2, gz, tar.gz, tar.bz2, or tar.Z

// GPG encryption
$encrypt_file = true;  // true or false to use GPG encryption
$gpg_public_key = 'secure@advancedautomationinc.com'; // email address of public key
$gpg_location = 'C:\GnuPG\gpg.exe';
$mysqldump_location = 'C:\mysql\bin\mysqldump.exe';

$mail_from_addr = 'secure@advancedautomationinc.com';

// Single recipient
$recipients = array('David Norman' => 'bounce@change_me.com');

// uncomment for multiple recipients
//$recipients = array('Example Recipient Name' => 'user@domain.com',
//                    'Another Recipient' => 'user2@somewhere.com',
//                    'The Last Guy' => 'last@guy.com');

/*** END CHANGE ME *****************************************/

define('DEBUG', FALSE);
error_reporting(E_ALL);
set_time_limit(0);
ini_set('display_startup_errors', '1');
ini_set('display_errors', '1');

require 'C:\Web\AAI\backup\backup.class.inc.php';
require 'C:\Web\AAI\backup\mailer.class.inc.php';
$message = 'C:\Web\AAI\backup\emailmessage.txt';

$date = date('YmdHis') . strstr(substr(microtime(), 0, strpos(microtime(), ' ')), '.');
$filename = $date .'.mysql';

$fileinfo = Backup::dumpDB($dbuser, $dbpass, $workingdirectory, $filename, $mysqldump_location);
if($compress_file) {
    $fileinfo = Backup::packageDB($compress_method, $fileinfo['directory'], $fileinfo['filename']);
}
if($encrypt_file) {
    $fileinfo = Backup::encryptDB($fileinfo['directory'], $fileinfo['filename'], $gpg_public_key, $gpg_location);
}

$message = file_get_contents($message);

// each recipient's name must be different so array elements don't clash
new Mailer(array($fileinfo['directory'] => $fileinfo['filename']),
                $recipients,
                'MySQL backup', $mail_from_addr,
                'Database backup '. $date, $message);

