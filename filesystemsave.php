<?php

/**
 * myBackware - MySQL database backup script
 *
 * @version 1.0b1
 * @author David Norman
 * @copyright 2004 David Norman
 * @package myBackware
 * @license Artistic License
 */
 
define('DEBUG', FALSE);
error_reporting(E_ALL);
set_time_limit(0);
ini_set('display_startup_errors', '1');
ini_set('display_errors', '1');

class Backup {

    /**
     * @var $dbuser Username to logon to database. For complete database
     *              dumps, this is probably root.
     */
    var $dbuser = 'root';

    /**
     * @var $dbpass Password to logon to database.
     */ 
    var $dbpass = 'password';

    /**
     * @var $databases Databases to dump to a backup file
     *                 Setting for all databases (default):
     *                 '' ;
     *                 Setting for specific databases:
     *                 array('db1','db2','db3');
     */
    // $databases = array('mysql','test');   // selected databases 
    var $databases = '';                         // all databases

    /**
     * @var $workingdirectory Full path of temporary directory to store
     *                        database dump files while packaging, compressing
     *                        and/or encrypting them. This should probably be a
     *                        place that not all users can view on a shared
     *                        machine. In most cases, /tmp/ will work fine
     *                        on a *nix or BSD based machine and C:\WinNT\temp\\
     *                        on a Win32 machine. The user running the script
     *                        should also have permission to read, write, and
     *                        modify files in this directory.
     *                        * Slashes on the end of the pathname are required 
     *                        * The backwards slashes on Win32 pathnames escape
     *                        the quotes on the variable and need to be escaped
     *                        with an extra slash before the end.
     */
    var $working_directory = 'C:\WINDOWS\temp\\';   // Win32
    //var $working_directory = '/tmp/';               // *nix and BSD

    /**
     * @var $packaging Decide how to package, compress, and/or encrypt email
     *                 attachments. Only compressing using compress, gzip,
     *                 or bzip2, will create multiple file attachments for
     *                 selected table backups rather than a full database dump.
     *                 Add the numbers together for each listed below to
     *                 generate the setting for $packaging. For example
     *                 the default is 1+16+32=49 for a .tar.bz2.gpg backup.
     *                 * For GPG asymmetric encryption, the secret key must
     *                 be previously imported on the GPG keyring of the user
     *                 running the script.
     *                 0  = No packaging, compression, or encryption
     *                 1  = .tar (tar)
     *                 2  = .zip (zip)
     *                 4  = .gz  (gzip)
     *                 8  = .bz2 (bzip2)
     *                 16 = .gpg (symmetric passphrase or asymmetric key)
     */
    var $packaging = 8;

    /**
     * @var $packaging Decide what to do with the dump. It can be attached
     *                 to an email, saved to the filesystem, or both.
     *                 0  = reserved (does nothing)
     *                 1  = email
     *                 2  = filesystem save
     */
    var $action = 2;

    /**
     * @var $compression_level Compression level to compress database dump file.
     *                         Only used if $packaging is set to use a type
     *                         of compression.
     *                         Greater number is greater compression. 
     *                         Range: 0-9
     * @see $packaging 
     */
    var $compression_level = 9;

    /**
     * @var $gpg_text Only applies when .gpg is set in $packaging
     *                - Set with gpg key's email address
     * @see $packaging
      */
    var $gpg_text = 'secure@advancedautomationinc.com';

    /**
     * @var $save_directory Directory in which to save rotating dumps and
     *                      number to save in a rotation. 
     */
    var $directory_save = array('C:\Web\AAI\backup\db\\', 60);

    /**
     * @var $email_recipients Name and email address of recipients for emails
     *                        containing database dumps.
     *                        Single recipient example:
     *                        array('user@domain.com');
     *                        Multiple recipient example:
     *                        array('user@domain.com','foo@bar.com');
     */
    var $email_recipients = array('bounce@change_me.com');

    /**
     * @var $email_subject Subject line of email containing database dump
     */
    var $email_subject = 'Database backup'; 

    /**
     * @var $email_from_name Name of the person mailing the database dump email
     */
    var $email_from_name = 'Backup script';

    /**
     * @var $email_from_email Email address of the person sending the database dump email
     */
    var $email_from_email = 'no_reply@domain.com';

    /**
     * @var $dump_filename Default filename of dump
     */
    var $dump_filename;

	// {{{ function Backup()

	/**
	 * Constructor function
	 *
	 * @since 20031114
	 */ 
	function Backup()
	{
        $this->dump_filename = date('YmdHis') . strstr(substr(microtime(), 0, strpos(microtime(), ' ')), '.') . '.sql';
        $fileinfo = $this->dumpDB();
		$fileinfo = $this->packageDB();
        if($this->action & 1) { // send email
            Mailer::Mailer(array($fileinfo['directory'] => $fileinfo['filename']), $this->email_recipients, $this->email_from_name, $this->email_from_email, $this->email_subject, file_get_contents($this->email_message));
        }
        if($this->action & 2) { // save to filesystem
		    $this->filesystemSave();
        }
        
	} // end func Backup

	// }}}
    // {{{ function dumpDB()

    /**
     * Dump MySQL data to a text file
     *
     * @since 20030801
     * @param string $user Username for administrator MySQL login
     * @param string $password Password for administrator MySQL login
     * @param string $working_directory Optional - Directory to write MySQL dump. Default /tmp.
     * @param string $filename Optional - Filename for MySQL dump. Default in time format: YYYYMMDDHHMMSS.mysql
     * @return array Associative array containing directory and filename.
     */
    function dumpDB()
    {
		if(is_array($this->databases)) {
			$cmd = 'mysqldump --add-locks -e -l -u '. $this->dbuser .' -p'. $this->dbpass  .' --databases';
			foreach($this->databases as $value) {
				$cmd .= ' '. $value;
			}
			$cmd .= ' > '. $this->working_directory.$this->dump_filename;
			if(DEBUG) {
                print_r($cmd);
			}
			shell_exec($cmd);
		} else {
			$cmd = 'mysqldump --add-locks -A -e -l -u '. $this->dbuser .' -p'. $this->dbpass .' > '. $this->working_directory.$this->dump_filename;
			if(DEBUG) print_r($cmd);
			shell_exec($cmd);
		}
        return array('directory' => $this->working_directory,
                     'filename' => $this->dump_filename);
    } // end func dumpDB

    // }}}
	// {{{ function filesystemSave()

	/**
	 * Decide what files stay and go in the storage directory
	 *
	 * @since 20040103
	 */ 
	function filesystemSave()
	{
        $dh = opendir($this->directory_save[0]);
        while(($filename = readdir($dh)) !== false) {
            if($filename != '.'
               && $filename != '..'
               && !is_dir($this->directory_save[0].$filename)) {
                $files[] = $filename;
            }
        }
		if(isset($files) && sizeof($files) > 0) {
            foreach($files as $filename) {
                $filesm[$filename] = filemtime($this->directory_save[0].$filename);
            }
            asort($filesm);
            reset($filesm);
            for($i=$this->directory_save[1]-1, $j=sizeof($filesm); $i<$j; $i+=1) {
                unlink($this->directory_save[0].key($filesm));
                next($filesm);
            }
        }
        if(copy($this->working_directory.$this->dump_filename, $this->directory_save[0].$this->dump_filename)) {
		    unlink($this->working_directory.$this->dump_filename);
        }
    } // end func filesystemSave

    // }}}
    // {{{ function packageDB()

    /**
     * Package and/or compress MySQL dump file
     *
     * @since 20030801
     * @param string $format Format of compression. Choices are tar.gz, tar.Z, tar.bz2, and gzip. Defaults to bzip2.
     * @param string $working_directory Directory to write MySQL dump. Default /tmp/.
     * @param string $filename Filename for MySQL dump. Default in time format: YYYYMMDDHHMMSS.mysql
     * @param int $level Optional - Compression level
     * @return array Associative array containing updated directory and filename
     */
    function packageDB()
    {
        $filename = empty($this->dump_filename) ? date('YmdHis') . strstr(substr(microtime(), 0, strpos(microtime(), ' ')), '.') . '.sql' : $this->dump_filename;

		if($this->packaging & 1) { // tar
			$cmd = 'tar --remove-files -cf '. $this->working_directory.$filename .'.tar '. $this->working_directory.$filename;
			if(DEBUG) print_r($cmd);
            shell_exec($cmd);
            $filename .= '.tar';
	    }
		if($this->packaging & 2) { // zip
			$cmd = 'zip -m -'. $this->compression_level .' '. $this->working_directory.$filename .'.zip '. $this->working_directory.$filename;
			if(DEBUG) print_r($cmd);
            shell_exec($cmd);
            $filename .= '.zip';
	    }
		if($this->packaging & 4) { // gzip
			$cmd = 'gzip -'. $this->compression_level .' '. $this->working_directory.$filename;
			if(DEBUG) print_r($cmd);
            shell_exec($cmd);
            $filename .= '.gz';
	    }
		if($this->packaging & 8) { // bzip2
			$cmd = 'bzip2 -'. $this->compression_level .' '. $this->working_directory.$filename;
			if(DEBUG) print_r($cmd);
            shell_exec($cmd);
            $filename .= '.bz2';
	    }
		if($this->packaging & 16) { // GnuPG gpg encryption
			$cmd = 'gpg --no-tty -qe -r '. $this->gpg_text .' '. $this->working_directory.$filename;
			if(DEBUG) print_r($cmd);
			shell_exec($cmd);
            unlink($this->working_directory . $filename);
			$filename .= '.gpg';
	    }
        $this->dump_filename = $filename;
        return array('directory' => $this->working_directory,
                     'filename' => $filename);
    } // end func packageDB

    // }}}
    // {{{ function setVars()

    /**
     * Override default variable values with input from system
     *
     * @since 20040103
     */
    function setVars()
    {
        // foreach here would start with [0] as the filename
        // which we want to ignore
        for($i=1, $j=sizeof($_SERVER['argv']); $i<$j; $i+=1) {
            if(substr_count($_SERVER['argv'][$i], '=') > 0) {
				$input = explode('=', $_SERVER['argv'][$i], 2);
                $this->$input[0] = substr_count($input[1], ',') > 0 ? explode(',', $input[1]) : $input[1];
            }
        }
    } // end func setVars

    // }}}
}

/**
 * Email attachment class
 *
 * @version 20031114
 * @author David Norman <deekayen@deekayen.net>
 * @copyright 2004 David Norman
 * @package myBackware
 */
class Mailer {

    // {{{ Mailer()

    /**
     * Constructor function
     *
     * Accepts multiple recipients, but only a single sender.
     * To have multiple senders, call the function multiple times.
     *
     * @since 20030731
     * @param array $files List of files to attach to email
     * @param array $recipient Recipient name(s) and address(es)
     * @param string $sender_name Name of person sending the email
     * @param string $return_address Email address to return replies or bounces
     */
    function Mailer($files, $recipient, $sender_name, $return_address, $subject, $message)
    {
        ini_set("memory_limit", "250M");

        $message = StripSlashes($message);

        error_reporting(63);

        // Defining CRLF is optional because otherwise
        // the attachment class will do it and default to \n.
        // If you're having problems, try changing
        // this to either \n (unix) or \r (Mac)
        define('CRLF', "\r\n", TRUE);

        // Create the mail object. Optional headers
        // argument. Do not put From: here, this
        // will be added when $mail->send
        // Does not have to have trailing \r\n
        // but if adding multiple headers, must
        // be seperated by whatever you're using
        // as line ending (usually either \r\n or \n)
        $mail = new Mime_Mail(array('X-Mailer: PHP/'. phpversion() ."\r\nReply-To: $return_address"));

        // set text body of email
        $mail->addText($message);
        unset($message);

        $type_array = array(".xls" => "application/excel",
                    ".doc"  => "application/msword",
                    ".pdf"  => "application/pdf",
                    ".ppt"  => "application/powerpoint",
                    ".wp"   => "application/wordperfect5.1",
                    ".mdb"  => "application/msaccess",
                    ".zip"  => "application/zip",
                    ".pgp"  => "application/pgp",
                    ".tar"  => "application/x-tar",
                    ".gz"   => "application/x-gzip",
                    ".bz2"  => "application/x-bz2",
                    ".gtar" => "application/x-gtar",
                    ".ai"   => "application/postscript",
                    ".eps"  => "application/postscript",
                    ".ps"   => "application/postscript",
                    ".rtf"  => "text/richtext",
                    ".rtx"  => "text/richtext",
                    ".txt"  => "text/plain",
                    ".html" => "text/html",
                    ".htm"  => "text/html",
                    ".bmp"  => "image/bmp",
                    ".gif"  => "image/gif",
                    ".jpg"  => "image/jpeg",
                    ".jpeg" => "image/jpeg",
                    ".png"  => "image/png",
                    ".tif"  => "image/tiff",
                    ".tiff" => "image/tiff");

        foreach($files as $location => $filename) {
            $extension = substr($filename, strrpos($filename, '.'), strlen($filename));

            foreach($type_array as $key => $value) {
                if(!strcmp($key, $extension)) {
                    $c_type=$value;
                    continue;
                }
            }
            $c_type = (isset($c_type)) ? $c_type : 'application/octet-stream';

            if(file_exists($location . $filename)) {
                $attachment = $mail->getFile($location . $filename);
                $mail->addAttachment($attachment, $filename, $c_type);
                unlink($location . $filename);
            } else {
                echo $filename .' could not be attached to the message. Try again.';
            }
            unset($c_type);
        } // end foreach
        unset($files, $type_array);

        $mail->buildMessage();

        // $mail->buildMessage() is seperate from $mail->send so that the
        // same email can be sent many times to differing recipients
        // simply by putting $mail->send() in a loop.

//        foreach($recipient as $name => $address) {
//            $mail->send($name, $address, $sender_name, $return_address, $subject);
//        }
          $mail->send($recipient, $sender_name, $return_address, $subject);
    } // end func Mailer

    // }}}
}

/**
 * Text Mime Mail class
 *
 * @version 20030802
 * @author Unknown
 * @package myBackware
 */

class Mime_Mail extends Mailer {

    /**
     * @var string The message or body of the email
     */
    var $text;

    /**
     * @var string The message or body of the email plus the encoded file attachment(s)
     */
    var $output;

    /**
     * @var array Elements of attachment body, filename, file type, and encoding
     */
    var $attachments;

    /**
     * @var array Standard and custom email headers
     */
    var $headers;

    // {{{ Mime_Mail()

    /**
     * Constructor function. Sets the headers
     * if supplied.
     *
     * @since 20030731
     * @param array $headers Custom headers to add to the email
     */
    function Mime_Mail($headers = array())
    {
        // Make sure CRLF is defined. IT should
        // be \r\n, but due to many people having
        // trouble with that, it is by default \n
        // If you leave it as is, you will be breaking
        // quite a few standards.
        if(!defined('CRLF'))
            define('CRLF', "\n", TRUE);

        $this->headers = array();

        // If you want the auto load functionality
        // to find other image/file types, add the
        // extension and content type here.
        $this->image_types = array('gif' => 'image/gif',
                        'jpg'  => 'image/jpeg',
                        'jpeg' => 'image/jpeg',
                        'jpe'  => 'image/jpeg',
                        'bmp'  => 'image/bmp',
                        'png'  => 'image/png',
                        'tif'  => 'image/tiff',
                        'tiff' => 'image/tiff',
                        'swf'  => 'application/x-shockwave-flash'
                      );

        $this->build_params['text_encoding'] = '7bit';
        $this->build_params['text_charset']  = 'iso-8859-1';
        $this->build_params['text_wrap']     = 998;

        // This makes sure the MIME version header is first.
        $this->headers[] = 'MIME-Version: 1.0';

        foreach($headers as $value) {
            if(!empty($value))
                $this->headers[] = $value;
        }
    } // end func Mime_Mail
    
    // }}}
    // {{{ getFile()

    /**
     * Read in file from a supplied filename
     *
     * @since 20030731
     * @see addAttachment()
     * @param string $filename Filename including path for the file to be attached to the email
     * @return mixed File contents as string on success, boolean false on failure
     */
    function getFile($filename)
    {
        if($fp = fopen($filename, 'rb')) {
            $return = fread($fp, filesize($filename));
            fclose($fp);
            return $return;

        } else {
            return false;
        }
    } // end func getFile
    
    // }}}
    // {{{ addText()

    /**
     * Adds plain text to running string containing email content
     *
     * @since 20030731
     * @param string $text Text to add to the email - HTML is not translated
     * @return bool True if length of text is greater than 0, otherwise false
     */
    function addText($text = '')
    {
        $this->text = $text;
        return strlen($this->text) > 0 ? true : false;
    } // end func addText

    // }}}
    // {{{ addAttachment()

    /**
     * Adds a file to the list of attachments.
     *
     * @since 20030731
     * @param string $file String with the contents of the attachment
     * @param string $name default empty
     * @param string $c_type
     * @param string $encoding
     * @return bool Always true
     */
    function addAttachment($file, $name = '', $c_type='application/octet-stream', $encoding = 'base64')
    {
        $this->attachments[] = array('body' => $file,
                'name'     => $name,
                'c_type'   => $c_type,
                'encoding' => $encoding
        );
        return true;
    } // end func addAttachment

    // }}}
    // {{{ _addTextPart()

    /**
     * Adds a text subpart to a Mime_Part object
     *
     * @since 20030731
     * @see buildMessage()
     * @see _addMixedPart()
     * @param mixed $obj Either NULL or an object to a new Mime_Part class from {@link buildMessage()} which got it from {@link _addMixedPart()}
     * @param string $text
     * @uses Mime_Part
     * @return mixed If $obj is NULL, this function returns an object to Mime_Part or if $obj is an object it returns an array from {@link _addSubpart()}
     */
    function &_addTextPart(&$obj, $text)
    {
        $params['content_type'] = 'text/plain';
        $params['encoding']     = $this->build_params['text_encoding'];
        $params['charset']      = $this->build_params['text_charset'];
        return is_object($obj) ? $obj->_addSubpart($text, $params) : new Mime_Part($text, $params);
    } // end func _addTextPart

    // }}}
    // {{{ _addMixedPart()

    /**
     * Starts a message with a mixed part
     *
     * @since 20030731
     * @uses Mime_Part
     * @return object
     */
    function &_addMixedPart()
    {
        $params['content_type'] = 'multipart/mixed';
        return new Mime_Part('', $params);
    } // end func _addMixedPart

    // }}}
    // {{{ _addAttachmentPart()

    /**
     * Adds an attachment subpart to a Mime_Part object
     *
     * @since 20030731
     * @see buildMessage
     * @see _addMixedPart
     * @param object $obj Object containing class Mime_Part. Notice {@link buildMessage()} gets it from {@link _addMixedPart()}.
     * @param array $value Elements "c_type", "encoding", "name", and "body" are relevant in this function
     * @return array
     */
    function &_addAttachmentPart(&$obj, $value)
    {
        $params['content_type'] = $value['c_type'];
        $params['encoding']     = $value['encoding'];
        $params['disposition']  = 'attachment';
        $params['dfilename']    = $value['name'];
        return $obj->_addSubpart($value['body'], $params);
    } // end func _addAttachmentPart

    // }}}
    // {{{ buildMessage()

    /**
     * Builds the multipart message from the collected components.
     *
     * $params['text_encoding'] - The type of encoding to use on plain text Valid options are
     *                            "7bit", "quoted-printable" or "base64" (all without quotes).
     *                            Default is 7bit
     * $params['text_wrap']     - The character count at which to wrap 7bit encoded data.
     *                            Default this is 998.
     * $params['text_charset']  - The character set to use for a text section.
     *                          - Default is iso-8859-1
     *
     * @since 20030731
     * @param array $params
     * @return bool
     */
    function buildMessage($params = array())
    {
        if(count($params) > 0) {
            while(list($key, $value) = each($params)) {
                $this->build_params[$key] = $value;
            }
        }

        $null        = NULL;
        $attachments = !empty($this->attachments) ? TRUE : FALSE;
        $text        = !empty($this->text)        ? TRUE : FALSE;

        switch(true) {
            case $text AND !$attachments:
                $message =& $this->_addTextPart($null, $this->text);
                break;

            case $text AND $attachments:
                $message =& $this->_addMixedPart();
                $this->_addTextPart($message, $this->text);

                for($i=0; $i<count($this->attachments); $i+=1) {
                    $this->_addAttachmentPart($message, $this->attachments[$i]);
                }
                break;
        }

        if(isset($message)) {
            $output = $message->encode();
            $this->output = $output['body'];
            $this->headers = array_merge($this->headers, $output['headers']);
            return true;
        } else {
            return false;
        }
    } // end func buildMessage

    // }}}
    // {{{ send()

    /**
     * Sends the mail.
     *
     * @since 20030731
     * @param array $recipient Associative array with recipient names and addresses
     * @param string $from_name Name of the sender
     * @param string $from_addr Email address of the sender
     * @param string $subject Subject of the email; defaults to empty
     * @param string $headers Headers to add to the top of the email; default to empty, but probably won't ever be empty
     * @return bool
     */
    function send($recipient, $from_name, $from_addr, $subject = '', $headers = '')
    {
        $from = ($from_name != '') ? '"'.$from_name.'" <'.$from_addr.'>' : $from_addr;

        if(is_string($headers)) {
            $headers = explode(CRLF, trim($headers));
        }

        for($i=0; $i<count($headers); $i+=1) {
            if(is_array($headers[$i])) {
                for($j=0; $j<count($headers[$i]); $j+=1) {
                    if($headers[$i][$j] != '') {
                        $xtra_headers[] = $headers[$i][$j];
                    }
                }
            }

            if($headers[$i] != '') {
                $xtra_headers[] = $headers[$i];
            }
        }

        if(!isset($xtra_headers)) {
            $xtra_headers = array();
        }

        $to = '';
        foreach($recipient as $address) {
            $to .= $address .',';
        }
        $to = substr($to, 0, strlen($to)-1);

        return mail($to, $subject, $this->output, 'From: '.$from.CRLF.implode(CRLF, $this->headers).CRLF.implode(CRLF, $xtra_headers));
    } // end func send
    
    // }}}

} // end class Mime_Mail

/**
 * The mime part class which handles the
 * build of the email
 *
 * @version 20030801
 * @author Unknown
 * @package myBackware
 */

class Mime_Part extends Mime_Mail {

    /**
     * @var string
     */
    var $encoding;

    /**
     * @var string
     */
    var $subparts;

    /**
     * @var string
     */
    var $encoded;

    /**
     * @var string
     */
    var $headers;

    /**
     * @var string
     */
    var $params;

    /**
     * @var string
     */
    var $body;

    // {{{ Mime_Part()

    /**
     * Constructor function.
     *
     * $body   - The body
     * $params - Various parameters for the part:
     *   content-type - Content type
     *   encoding     - Encoding type to use
     *   cid          - Content ID if any
     *   disposition  - Disposition (inline or attachment)
     *   dfilename    - Filename parameter of disposition
     *   description  - Content Description
     *
     * @since 20030731
     * @param string $body The body or message of the email
     * @param array $params Contains information that will go to separating the message from the attachments
     */
    function Mime_Part($body, $params = array())
    {
        if(!defined('CRLF'))
            define('CRLF', "\r\n", TRUE);

        foreach($params as $key => $value){
            switch($key) {
                case 'content_type':
                    $headers['Content-Type'] = $value.(isset($charset) ? '; charset="'.$charset.'"' : '');
                    break;

                case 'encoding':
                    $this->encoding = $value;
                    $headers['Content-Transfer-Encoding'] = $value;
                    break;

                case 'cid':
                    $headers['Content-ID'] = '<'.$value.'>';
                    break;

                case 'disposition':
                    $headers['Content-Disposition'] = $value.(isset($dfilename) ? '; filename="'.$dfilename.'"' : '');
                    break;

                case 'dfilename':
                    if(isset($headers['Content-Disposition'])){
                        $headers['Content-Disposition'] .= '; filename="'.$value.'"';
                    } else {
                        $dfilename = $value;
                    }
                    break;

                case 'description':
                    $headers['Content-Description'] = $value;
                    break;

                case 'charset':
                    if(isset($headers['Content-Type'])) {
                        $headers['Content-Type'] .= '; charset="'.$value.'"';
                    } else {
                        $charset = $value;
                    }
                    break;
            }
        }

        // Default content-type
        if(!isset($headers['Content-Type'])) {
            $headers['Content-Type'] = 'text/plain';
        }

        // Assign stuff to member variables
        $this->encoded  =  array();
        $this->headers  =& $headers;
        $this->body     =  $body;
    } // end func Mime_Part

    // }}}
    // {{{ encode()

    /**
     * Encodes and returns the email.
     *
     * Stores the email in array elements according to RFC822
     *
     * @since 20030731
     * @return array
     */
    function encode()
    {
        $encoded =& $this->encoded;

        if(!empty($this->subparts)){
            srand((double)microtime()*1000000);
            $boundary = '=_'.md5(uniqid(rand()).microtime());
            $this->headers['Content-Type'] .= ';'.CRLF.chr(9).'boundary="'.$boundary.'"';

            // Add body parts to $subparts
            for($i=0; $i<count($this->subparts); $i+=1) {
                $tmp = $this->subparts[$i]->encode();
                $subparts[] = implode(CRLF, $tmp['headers']).CRLF.CRLF.$tmp['body'];
            }

            $encoded['body'] = '--'.$boundary . CRLF .
                               implode('--'.$boundary.CRLF, $subparts) .
                               '--'.$boundary.'--'.CRLF;
        } else {
            $encoded['body'] = $this->_getEncodedData($this->body, $this->encoding).CRLF;
        }

        // Add headers to $encoded
        foreach($this->headers as $key => $value){
            $encoded['headers'][] = $key.': '.$value;
        }

        return $encoded;
    } // end func encode

    // }}}
    // {{{ _addSubpart()

    /**
     * Adds a subpart, either text or an attachment
     * 
     * @since 20030731
     * @see _addTextPart()
     * @see _addAttachmentPart()
     * @param string $body Email message
     * @param array $params Information to ensure correct encoding
     * @return array
     * @internal {@link _addTextPart()} sends the email message
     * in $body with content type, encoding, and charset in $params
     * while {@link _addAttachmentPart()} sends the email message in $body with
     * content type, encoding, and disposition, and dfilename in $params.
     */
    function &_addSubpart($body, $params)
    {
        $this->subparts[] = new Mime_Part($body, $params);
        return $this->subparts[count($this->subparts) - 1];
    } // end func _addSubpart

    // }}}
    // {{{ _getEncodedData()

    /**
     * Returns encoded data
     *
     * @since 20030731
     * @param string $data The body or message of the email
     * @param string $encoding Either 7bit, quoted-printable, or base64. Most attachments are base64.
     * @return string
     */
    function _getEncodedData($data, $encoding)
    {
        switch($encoding) {
            case '7bit':
                return $data;
                break;

            case 'quoted-printable':
                return $this->_quotedPrintableEncode($data);
                break;

            case 'base64':
                return rtrim(chunk_split(base64_encode($data), 76, CRLF));
                break;
        }
    } // end func _getEncodedData

    // }}}
    // {{{ _quotedPrintableEncode()

    /**
     * Encodes text to quoted printable standard.
     *
     * @since 20030731
     * @param string $input The message or body of the email
     * @param integer $line_max Maximum line length for each line in the email
     * @return string
     */
    function _quotedPrintableEncode($input, $line_max = 76)
    {
        $lines  = preg_split("/\r\n|\r|\n/", $input);
        $eol    = CRLF;
        $escape = '=';
        $output = '';
            
        while(list(, $line) = each($lines)) {
            $linlen  = strlen($line);
            $newline = '';

            for($i = 0; $i < $linlen; $i+=1) {
                $char = substr($line, $i, 1);
                $dec  = ord($char);
    
                if(($dec == 32) AND ($i == ($linlen - 1))) {    // convert space at eol only
                    $char = '=20';
                } elseif($dec == 9) {
                    continue; // Do nothing if a tab.
                } elseif(($dec == 61) OR ($dec < 32 ) OR ($dec > 126)) {
                    $char = $escape.strtoupper(sprintf('%02s', dechex($dec)));
                }

                if((strlen($newline) + strlen($char)) >= $line_max) {    // CRLF is not counted
                    $output  .= $newline.$escape.$eol;                    // soft line break; " =\r\n" is okay
                    $newline  = '';
                }
                $newline .= $char;
            } // end of for
            $output .= $newline.$eol;
        }

        $output = substr($output, 0, -1*strlen($eol)); // Don't want last crlf
        return $output;
    } // end func _quotedPrintableEncode

    // }}}

} // end class Mime_Part

/**
 * Text of the dump email
 *
 * @version 20040103
 * @copyright 2004 David Norman
 * @author David Norman <deekayen@deekayen.net>
 * @package myBackware
 */
class EmailText extends Backup {

    // {{{ function EmailText()

    /**
     * Constructor function. Text of the dump email. Contains information on
     * how to get the script to work and how to restore from a dump if needed
	 *
     * @return string
     */
    function EmailText() {
        return <<<EOD
Attached is a MySQL database dump. It contains all information
necessary to re-create a MySQL database in the event of a complete
loss. Of course, the attached file isn't much good if nobody knows
or can remember how to transform it from its compressed and encrypted
format back to something useful. What follows is background
information, instructions, and examples.

The database was dumped to a text file using the command:

mysqldump -A -l -u $user -p$password > $directory$filename

The correct values for processing are stored in the variables
$user, $password, $directory, and $filename. Each dump has a
filename in the date format YYYYMMDDHHMMSS, with .mysql appended
as the file extension to identify the file as a mysql dump file.

The dump file is then compressed using the bzip2 compression format.
Bzip2 is generally provides 10-15% better compression than gzip and
15-25% better compression than zip. Since bandwidth is metered with
most any internet service provider hosting servers, bzip2 is being
used even though the popular Windows archiver WinZip has chosen not
to implement .bz2 file (de)compression. Other (de)compression
utilities are available for Windows which support bzip2, including
PowerArchiver (http://www.powerarchiver.com/) and WinRAR
(http://www.rarlab.com/). Bzip2 is installed by default on most
Linux distributions, including Redhat and Mandrake.

The attached database information is also encrypted using GnuPG,
downloaded from http://www.gnupg.org/. The server uses a public
PGP encryption key to encrypt the backup file. The file can only
be decrypted using a private key that was created at the same time
as the public key to fit with the public key to make a key pair.
The strength of PGP style encryption produced by GPG assures that
only the possessor of the matching private key can read the contents
of the file. This deters several types of attacks from malicious
people on the Internet attempting both passive and active attacks.
The objective is not to provide data integrity, but rather data
confidentiality - anyone can encrypt a file to send to the possessor
of the private key using the public key, but the public key
can not decrypt a file encrypted with the public key.

A properly secured and maintained server should be considered a
relatively difficult place to steal information stored in a
database, however when transferring data over the Internet, an email
might pass through as many as forty different servers to reach its
destination. Any of those server administrators or people with
access to parts of the network that provide Internet to those
servers are able to read email transmissions, intercept them, copy
and modify their contents, replay them over the network, and
masquerade as the sender. In an academic database, it would give
the attacker access to user passwords, performance scores, and
other information that likely shouldn't be in the hands of random
people. PGP is a well known standard for email content encryption
and is quite possibly the most secure encryption in existence.

So how does this whole system work? A cron job runs regularly,
executing a PHP script that creates the MySQL dump file, compresses
and encrypts it, and mails it to a list of recipients. A GPG key
pair was created in Linux.

    bash-2.05$ gpg --gen-key
    gpg (GnuPG) 1.2.2; Copyright (C) 2003 Free Software Foundation, Inc.
    This program comes with ABSOLUTELY NO WARRANTY.
    This is free software, and you are welcome to redistribute it
    under certain conditions. See the file COPYING for details.
 
    Please select what kind of key you want:
       (1) DSA and ElGamal (default)
       (2) DSA (sign only)
       (5) RSA (sign only)
    Your selection? 1
    DSA keypair will have 1024 bits.
    About to generate a new ELG-E keypair.
                  minimum keysize is  768 bits
                  default keysize is 1024 bits
        highest suggested keysize is 2048 bits
    What keysize do you want? (1024)
    Requested keysize is 1024 bits
    Please specify how long the key should be valid.
             0 = key does not expire
          <n>  = key expires in n days
          <n>w = key expires in n weeks
          <n>m = key expires in n months
          <n>y = key expires in n years
    Key is valid for? (0)
    Key does not expire at all
    Is this correct (y/n)? y

    You need a User-ID to identify your key; the software constructs the user id
    from Real Name, Comment and Email Address in this form:
        "Heinrich Heine (Der Dichter) <heinrichh@duesseldorf.de>"
 
    Real name: Advanced Automation Inc
    Email address: secure@advancedautomationinc.com
    Comment:
    You selected this USER-ID:
        "Advanced Automation Inc <secure@advancedautomationinc.com>"
 
    Change (N)ame, (C)omment, (E)mail or (O)kay/(Q)uit? o
    You need a Passphrase to protect your secret key.
 
    Enter passphrase:
    gpg:

A long password was entered. Often the weakest part of encryption
is the password, not the method of encryption chosen. In Windows,
the process is very similar. Download the GnuPG distribution file
from http://www.gnupg.org/. Unzip it to c:\GnuPG and open the
gnupg-w32.reg file to merge it with the registry. In the Start
menu, select Run. Type 'command' in the Open box and hit OK for
Windows 95/98/ME, or type 'cmd' in the Open box and hit OK for
Windows NT/2000/XP or newer. The GPG for Windows output will be
identical to Linux.

    Microsoft Windows XP [Version 5.1.2600]
    (C) Copyright 1985-2001 Microsoft Corp.

    C:\Documents and Settings\deekayen>cd \

    C:\>cd gnupg
    
    C:\>gpg --gen-key

Then you'll need to create a copy of the public key to load to
the server for encrypting backups.

    C:\>gpg -ao secureadvancedautomation.gpg --export secure@advancedautomationinc.com

The private key needs to be absolutely secure. If it is compromised,
an attacker can brute force search for the password rather than
having to brute force the 1024 bits of randomness that would unlock
the encryption created by the public key. It would take more
processing power to brute force attack the 1024 bits of randomness
in the private key than is estimated to exist in the next billion
years, however finding the password to unlock the private key
if the private key is in the attackers possession could take a
matter of hours. For that reason, the private key should not be
transmitted over the insecure pipes of the Internet. With that in
mind, export a copy of the private key:

    C:\>gpg -ao secureadvancedautomation-private.gpg --export-secret-keys secure@advancedautomationinc.com

Copy the resulting file to somewhere secure as a backup. Burning it
to a CD and postal mailing it to another trusted person in the
organization who also receives a copy of the backup file and/or might
one day need to recover the backup.

Upload the GPG public key to the server with the database to backup.
The following will get GPG prepped:

    bash-2.05$ gpg --import advancedautomationinc.gpg
    bash-2.05$ gpg --edit-key secure@advancedautomationinc.com
    gpg (GnuPG) 1.0.6; Copyright (C) 2001 Free Software Foundation, Inc.
    This program comes with ABSOLUTELY NO WARRANTY.
    This is free software, and you are welcome to redistribute it
    under certain conditions. See the file COPYING for details.

    gpg: Warning: using insecure memory!

    pub  1024D/9F888EF1  created: 2003-08-02 expires: never      trust: -/q
    sub  1024g/E5DF491E  created: 2003-08-02 expires: never
    (1). Advanced Automation Inc <secure@advancedautomationinc.com>
 
    Command> trust
    pub  1024D/9F888EF1  created: 2003-08-02 expires: never      trust: -/q
    sub  1024g/E5DF491E  created: 2003-08-02 expires: never
    (1). Advanced Automation Inc <secure@advancedautomationinc.com>

    Please decide how far you trust this user to correctly
    verify other users' keys (by looking at passports,
    checking fingerprints from different sources...)?
 
     1 = Don't know
     2 = I do NOT trust
     3 = I trust marginally
     4 = I trust fully
     s = please show me more information
     m = back to the main menu

    Your decision? 4
                 
    pub  1024D/9F888EF1  created: 2003-08-02 expires: never      trust: f/q
    sub  1024g/E5DF491E  created: 2003-08-02 expires: never
    (1). Advanced Automation Inc <secure@advancedautomationinc.com>
 
    Command> quit
    bash-2.05$ gpg --list-key --with-colons
    /home/advance/.gnupg/pubring.gpg
    --------------------------------
    pub:u:1024:17:845E17589F888EF1:2003-08-02::59:f:Advanced Automation Inc <secure@advancedautomationinc.com>::scESC:
    sub:u:1024:16:2CFC2116E5DF491E:2003-08-02::59::::e:

Here you want to write down the 2CFC2116E5DF491E segment so it can
be added to the list of trusted keys. Add the following lines to
'~/.gnupg/options'.

        trusted-key 2CFC2116E5DF491E
        no-secmem-warning

The regular backup script will execute the following:

    gpg -qe -o $directory$filename.gpg -r $key $directory$filename
    
Let's say that the worst has happened and you need to but the
backup to use. Find where you stored your public and private keys.
Copy the keys and the backup file to your GnuPG directory.
Follow along using the command window from Run in the Start menu
as aforementioned.

    Microsoft Windows XP [Version 5.1.2600]
    (C) Copyright 1985-2001 Microsoft Corp.

    C:\Documents and Settings\deekayen>cd \

    C:\>cd gnupg

    C:\GnuPG>dir
     Volume in drive C has no label.
     Volume Serial Number is 7057-682D

     Directory of C:\GnuPG

    08/02/2003  02:15 AM    <DIR>          .
    08/02/2003  02:15 AM    <DIR>          ..
    05/01/2003  05:29 PM           111,982 ca.mo
    05/01/2003  05:29 PM            18,332 COPYING
    05/01/2003  05:29 PM           109,595 cs.mo
    05/01/2003  05:29 PM            27,444 da.mo
    05/01/2003  05:29 PM           118,517 de.mo
    08/02/2003  02:11 AM           554,511 20030802054512.mysql.bz2.gpg
    05/01/2003  05:29 PM           113,734 el.mo
    05/01/2003  05:29 PM            82,974 eo.mo
    05/01/2003  05:29 PM           110,986 es.mo
    05/01/2003  05:29 PM           106,893 et.mo
    05/01/2003  05:29 PM            55,430 FAQ
    05/01/2003  05:29 PM           109,533 fi.mo
    05/01/2003  05:29 PM           113,967 fr.mo
    05/01/2003  05:29 PM           111,663 gl.mo
    05/01/2003  05:29 PM               385 gnupg-w32.reg
    05/01/2003  05:29 PM               664 gnupg.man
    05/01/2003  05:29 PM           610,304 gpg.exe
    05/01/2003  05:29 PM            86,001 gpg.man
    05/01/2003  05:29 PM            13,824 gpgkeys_ldap.exe
    05/01/2003  05:29 PM            47,616 gpgsplit.exe
    05/01/2003  05:29 PM           267,264 gpgv.exe
    05/01/2003  05:29 PM             3,976 gpgv.man
    05/01/2003  05:29 PM           111,385 hu.mo
    05/01/2003  05:29 PM           108,176 id.mo
    05/01/2003  05:29 PM           111,043 it.mo
    05/01/2003  05:29 PM            95,731 ja.mo
    05/01/2003  05:29 PM            59,231 nl.mo
    05/01/2003  05:29 PM           104,625 pl.mo
    05/01/2003  05:29 PM           103,755 pt.mo
    05/01/2003  05:29 PM            57,470 pt_BR.mo
    08/02/2003  02:13 AM                 0 pubring.bak
    08/02/2003  02:13 AM               926 pubring.gpg
    05/01/2003  05:29 PM            27,124 README
    05/01/2003  05:29 PM             3,993 README.W32
    08/02/2003  02:15 AM             1,064 secring.gpg
    08/02/2003  02:14 AM             1,545 secureadvancedautomationinc-private.gpg
    08/02/2003  02:11 AM             1,357 secureadvancedautomationinc.gpg
    05/01/2003  05:29 PM           109,798 sk.mo
    05/01/2003  05:29 PM            90,963 sv.mo
    05/01/2003  05:29 PM           110,534 tr.mo
    08/02/2003  02:17 AM             1,240 trustdb.gpg
    05/01/2003  05:29 PM            94,979 zh_TW.mo
                  42 File(s)      3,970,534 bytes
                   2 Dir(s)   1,187,332,096 bytes free

    C:\GnuPG>gpg --import secureadvancedautomationinc.gpg
    gpg: keyring `C:/GnuPG\secring.gpg' created
    gpg: keyring `C:/GnuPG\pubring.gpg' created
    gpg: C:/GnuPG\trustdb.gpg: trustdb created
    gpg: key 9F888EF1: public key "Advanced Automation Inc <secure@advancedautomationinc.com>" imported
    gpg: Total number processed: 1
    gpg:               imported: 1

    C:\GnuPG>gpg --import secureadvancedautomationinc-private.gpg
    gpg: key 9F888EF1: secret key imported
    gpg: Total number processed: 1
    gpg:       secret keys read: 1
    gpg:   secret keys imported: 1

    C:\GnuPG>gpg --edit-key secure@advancedautomationinc.com
    gpg (GnuPG) 1.2.2; Copyright (C) 2003 Free Software Foundation, Inc.
    This program comes with ABSOLUTELY NO WARRANTY.
    This is free software, and you are welcome to redistribute it
    under certain conditions. See the file COPYING for details.

    Secret key is available.

    gpg: checking the trustdb
    gpg: no ultimately trusted keys found
    pub  1024D/9F888EF1  created: 2003-08-02 expires: never      trust: -/-
    sub  1024g/E5DF491E  created: 2003-08-02 expires: never
    (1). Advanced Automation Inc <secure@advancedautomationinc.com>

    Command> trust
    pub  1024D/9F888EF1  created: 2003-08-02 expires: never      trust: -/-
    sub  1024g/E5DF491E  created: 2003-08-02 expires: never
    (1). Advanced Automation Inc <secure@advancedautomationinc.com>

    Please decide how far you trust this user to correctly
    verify other users' keys (by looking at passports,
    checking fingerprints from different sources...)?

     1 = Don't know
     2 = I do NOT trust
     3 = I trust marginally
     4 = I trust fully
     5 = I trust ultimately
     m = back to the main menu

    Your decision? 5
    Do you really want to set this key to ultimate trust? yes

    pub  1024D/9F888EF1  created: 2003-08-02 expires: never      trust: u/-
    sub  1024g/E5DF491E  created: 2003-08-02 expires: never
    (1). Advanced Automation Inc <secure@advancedautomationinc.com>
    Please note that the shown key validity is not necessarily correct
    unless you restart the program.

    Command> quit

    C:\GnuPG>gpg --decrypt 20030802054512.mysql.bz2.gpg > 20030802054512.mysql.bz2

    You need a passphrase to unlock the secret key for
    user: "Advanced Automation Inc <secure@advancedautomationinc.com>"
    1024-bit ELG-E key, ID E5DF491E, created 2003-08-02 (main key ID 9F888EF1)

    gpg: encrypted with 1024-bit ELG-E key, ID E5DF491E, created 2003-08-02
          "Advanced Automation Inc <secure@advancedautomationinc.com>"

    C:\GnuPG>

Now you should have the compressed database file. If you want to decompress
it in Linux, upload it and type the following:

    bash-2.05$ bunzip2 20030802054512.mysql.bz2
    
The result will be 20030802054512.mysql. If you know you dumped
the original backup as the root MySQL user, you might need to edit
the MySQL dump backup to remove my mysql database to prevent
overwriting anything that might be version specific in your
replacement installation of MySQL. In most cases, you should just
be able to go from the decompression to importing to MySQL by
doing:

    bash-2.05$ mysql -u username -ppassword < 20030802054512.mysql

Other methods exists for importing data into MySQL.
See http://mysql.com/ if you're interested in reading more.
EOD;
    } // end func EmailText

    // }}}
} // end class EmailText

new Backup();
