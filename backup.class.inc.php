<?php

/**
 * myBackware - MySQL database backup script
 *
 * @version 20030802
 * @author David Norman
 * @copyright 2004 David Norman
 * @package myBackware
 */
class Backup {

    // {{{ dumpDB()

    /**
     * Dump MySQL data to a text file
     *
     * @since 20030801
     * @param string $user Username for administrator MySQL login
     * @param string $password Password for administrator MySQL login
     * @param string $workingdirectory Optional - Directory to write MySQL dump. Default /tmp.
     * @param string $filename Optional - Filename for MySQL dump. Default in time format: YYYYMMDDHHMMSS.mysql
     * @return array Associative array containing directory and filename.
     */
    function dumpDB($user, $password, $workingdirectory = '/tmp/', $filename = NULL, $mysqldump_location = 'mysqldump')
    {
        shell_exec("$mysqldump_location -A -l -u $user -p$password > $workingdirectory$filename");
        return array('directory' => $workingdirectory,
                     'filename' => $filename);
    } // end func dumpDB

    // }}}
    // {{{ packageDB()

    /**
     * Package and/or compress MySQL dump file
     *
     * @since 20030801
     * @param string $format Format of compression. Choices are tar.gz, tar.Z, tar.bz2, and gzip. Defaults to bzip2.
     * @param string $workingdirectory Directory to write MySQL dump. Default /tmp/.
     * @param string $filename Filename for MySQL dump. Default in time format: YYYYMMDDHHMMSS.mysql
     * @param int $level Optional - Compression level
     * @return array Associative array containing updated directory and filename
     */
    function packageDB($format, $workingdirectory, $filename, $level=9)
    {
        $filename = empty($filename) ? date('YmdHis') .'.mysql' : $filename;

        switch($format) {
            case 'tar.gz': // gzip tarball
                shell_exec("tar --remove-files -czf $workingdirectory$filename.tar.gz $workingdirectory$filename");
                $filename .= '.tar.gz';
                break;
            case 'tar.Z':  // compress tarball
                shell_exec("tar --remove-files -cZf $workingdirectory$filename.tar.Z $workingdirectory$filename");
                $filename .= '.tar.Z';
                break;
            case 'tar.bz2':  // bz2 tarball
                shell_exec("tar --remove-files -cjf $workingdirectory$filename.tar.bz2 $workingdirectory$filename");
                $filename .= '.tar.Z';
                break;
            case 'gz':  // gzip
                shell_exec("gzip -$level $workingdirectory$filename");
                $filename .= '.gz';
                break;
            case 'bz2':
                shell_exec("bzip2 -$level $workingdirectory$filename");
                $filename .= '.bz2';
                break;
        }
        return array('directory' => $workingdirectory,
                     'filename' => $filename);
    } // end func packageDB

    // }}}
    // {{{ encryptDB()

    /**
     * Encrypt input file with GPG
     *
     * @since 20030801
     * @param string $workingdirectory Directory of MySQL dump file
     * @param string $filename Filename of the MySQL dump file
     * @param string $key GPG key recipient in the form of email address
     * @return array Associative array containing updated directory and filename
     */
    function encryptDB($workingdirectory, $filename, $key, $gpg_location = 'gpg')
    {
        shell_exec("$gpg_location -qe -o $workingdirectory$filename.gpg -r $key $workingdirectory$filename");
        unlink($workingdirectory . $filename);
        return array('directory' => $workingdirectory,
                     'filename' => $filename.'.gpg');
    } // end func encryptDB

    // }}}
}
