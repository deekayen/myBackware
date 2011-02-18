<?php

/**
 * Email attachment class
 *
 * @version 1.0b1
 * @author David Norman
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
        ini_set("memory_limit", "100M");

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
//            while (list($key, $value) = each ($type_array)) {
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
 * @author David Norman <deekayen@deekayen.net>
 * @copyright 2004 David Norman
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
        $this->image_types = array(    'gif' => 'image/gif',
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

        foreach($headers as $value){
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
        if($fp = fopen($filename, 'rb')){
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
        if(count($params) > 0)
            while(list($key, $value) = each($params))
                $this->build_params[$key] = $value;

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

                for($i=0; $i<count($this->attachments); $i++)
                    $this->_addAttachmentPart($message, $this->attachments[$i]);
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
//    function send($to_name, $to_addr, $from_name, $from_addr, $subject = '', $headers = '')
    {
//        $to    = ($to_name != '')   ? '"'.$to_name.'" <'.$to_addr.'>' : $to_addr;
        $from    = ($from_name != '') ? '"'.$from_name.'" <'.$from_addr.'>' : $from_addr;

        if(is_string($headers))
            $headers = explode(CRLF, trim($headers));

        for($i=0; $i<count($headers); $i++){
            if(is_array($headers[$i]))
                for($j=0; $j<count($headers[$i]); $j++)
                    if($headers[$i][$j] != '')
                        $xtra_headers[] = $headers[$i][$j];

            if($headers[$i] != '')
                $xtra_headers[] = $headers[$i];
        }
        if(!isset($xtra_headers))
            $xtra_headers = array();

        $to = '';
        foreach($recipient as $name => $address) {
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
 * @author David Norman <deekayen@deekayen.net>
 * @copyright 2004 David Norman
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
        if(!isset($headers['Content-Type']))
            $headers['Content-Type'] = 'text/plain';

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
            for($i=0; $i<count($this->subparts); $i++) {
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
        $lines    = preg_split("/\r\n|\r|\n/", $input);
        $eol    = CRLF;
        $escape    = '=';
        $output    = '';
            
        while(list(, $line) = each($lines)) {
            $linlen     = strlen($line);
            $newline = '';
    
            for($i = 0; $i < $linlen; $i++) {
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
