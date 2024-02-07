<?php
/*https://stackoverflow.com/users/731138/alain*/

class ImapReader
{

    private $host;
    private $port;
    private $user;
    private $pass;
    private $box;
    private $box_list;
    private $errors;
    private $connected;
    private $list;
    private $deleted;

    const FROM = 0;
    const TO = 1;
    const REPLY_TO = 2;
    const SUBJECT = 3;
    const CONTENT = 4;
    const ATTACHMENT = 5;

    public function __construct($host = null, $port = '143', $user = null, $pass = null)
    {
        $this->host = $host;
        $this->port = $port;
        $this->user = $user;
        $this->pass = $pass;
        $this->box = null;
        $this->box_list = null;
        $this->errors = array ();
        $this->connected = false;
        $this->list = null;
        $this->deleted = false;
    }

    public function __destruct()
    {
        if ($this->isConnected())
        {
            $this->disconnect();
        }
    }

    public function changeServer($host = null, $port = '143', $user = null, $pass = null)
    {
        if ($this->isConnected())
        {
            $this->disconnect();
        }
        $this->host = $host;
        $this->port = $port;
        $this->user = $user;
        $this->pass = $pass;
        $this->box_list = null;
        $this->errors = array ();
        $this->list = null;
        return $this;
    }

    public function canConnect()
    {
        return (($this->connected == false) && (is_string($this->host)) && (!empty($this->host))
           && (is_numeric($this->port)) && ($this->port >= 1) && ($this->port <= 65535)
           && (is_string($this->user)) && (!empty($this->user)) && (is_string($this->pass)) && (!empty($this->pass)));
    }

    public function connect()
    {
        if ($this->canConnect())
        {
            $this->box = @imap_open("{{$this->host}:{$this->port}/imap/ssl/novalidate-cert}INBOX", $this->user,
                  $this->pass);
            if ($this->box !== false)
            {
                $this->_connected();
            }
            else
            {
                $this->errors = array_merge($this->errors, imap_errors());
            }
        }
        return $this;
    }

    public function boxList()
    {
        if (is_null($this->box_list))
        {
            $list = imap_getsubscribed($this->box, "{{$this->host}:{$this->port}}", "*");
            $this->box_list = array ();
            foreach ($list as $box)
            {
                $this->box_list[] = $box->name;
            }
        }
        return $this->box_list;
    }

    public function fetchAllHeaders($mbox)
    {
        if ($this->isConnected())
        {
            $test = imap_reopen($this->box, "{$mbox}");
            if (!$test)
            {
                return false;
            }
            $num_msgs = imap_num_msg($this->box);
            $this->list = array ();
            for ($id = 1; ($id <= $num_msgs); $id++)
            {
                $this->list[] = $this->_fetchHeader($mbox, $id);
            }
            return true;
        }
        return false;
    }

    public function fetchSearchHeaders($mbox, $criteria)
    {
        if ($this->isConnected())
        {
            $test = imap_reopen($this->box, "{$mbox}");
            if (!$test)
            {
                return false;
            }
            $msgs = imap_search($this->box, $criteria);
            if ($msgs)
            {
                foreach ($msgs as $id)
                {
                    $this->list[] = $this->_fetchHeader($mbox, $id);
                }
            }
            return true;
        }
        return false;
    }

    public function isConnected()
    {
        return $this->connected;
    }

    public function disconnect()
    {
        if ($this->connected)
        {
            if ($this->deleted)
            {
                imap_expunge($this->box);
                $this->deleted = false;
            }
            imap_close($this->box);
            $this->connected = false;
            $this->box = null;
        }
        return $this;
    }

    /**
     * Took from khigashi dot oang at gmail dot com at php.net
     * with replacement of ereg family functions by preg's ones.
     *
     * @param string $str
     * @return string
     */
    private function _fix($str)
    {
        if (preg_match("/=\?.{0,}\?[Bb]\?/", $str))
        {
            $str = preg_split("/=\?.{0,}\?[Bb]\?/", $str);
            while (list($key, $value) = each($str))
            {
                if (preg_match("/\?=/", $value))
                {
                    $arrTemp = preg_split("/\?=/", $value);
                    $arrTemp[0] = base64_decode($arrTemp[0]);
                    $str[$key] = join("", $arrTemp);
                }
            }
            $str = join("", $str);
        }

        if (preg_match("/=\?.{0,}\?Q\?/", $str))
        {
            $str = quoted_printable_decode($str);
            $str = preg_replace("/=\?.{0,}\?[Qq]\?/", "", $str);
            $str = preg_replace("/\?=/", "", $str);
        }
        return trim($str);
    }

    private function _connected()
    {
        $this->connected = true;
        return $this;
    }

    public function getErrors()
    {
        $errors = $this->errors;
        $this->errors = array ();
        return $errors;
    }

    public function count()
    {
        if (is_null($this->list))
        {
            return 0;
        }
        return count($this->list);
    }

    public function get($nbr = null)
    {
        if (is_null($nbr))
        {
            return $this->list;
        }
        if ((is_array($this->list)) && (isset($this->list[$nbr])))
        {
            return $this->list[$nbr];
        }
        return null;
    }

    public function fetch($nbr = null)
    {
        return $this->_callById('_fetch', $nbr);
    }

    private function _fetchHeader($mbox, $id)
    {
        $header = imap_header($this->box, $id);
        if (!is_object($header))
        {
            return;
        }
        $mail = new stdClass();
        $mail->id = $id;
        $mail->mbox = $mbox;
        $mail->timestamp = (isset($header->udate)) ? ($header->udate) : ('');
        $mail->date = date("d/m/Y H:i:s", (isset($header->udate)) ? ($header->udate) : (''));
        $mail->from = $this->_fix(isset($header->fromaddress) ? ($header->fromaddress) : (''));
        $mail->to = $this->_fix(isset($header->toaddress) ? ($header->toaddress) : (''));
        $mail->reply_to = $this->_fix(isset($header->reply_toaddress) ? ($header->reply_toaddress) : (''));
        $mail->subject = $this->_fix(isset($header->subject) ? ($header->subject) : (''));
        $mail->content = array ();
        $mail->attachments = array ();
        $mail->deleted = false;
        return $mail;
    }

    private function _fetch($mail)
    {
        $test = imap_reopen($this->box, "{$mail->mbox}");
        if (!$test)
        {
            return $mail;
        }
        $structure = imap_fetchstructure($this->box, $mail->id);
        if ((!isset($structure->parts)) || (!is_array($structure->parts)))
        {
            $body = imap_body($this->box, $mail->id);
            $content = new stdClass();
            $content->type = 'content';
            $content->mime = $this->_fetchType($structure);
            $content->charset = $this->_fetchParameter($structure->parameters, 'charset');
            $content->data = $this->_decode($body, $structure->type);
            $content->size = strlen($content->data);
            $mail->content[] = $content;
            return $mail;
        }
        else
        {
            $parts = $this->_fetchPartsStructureRoot($mail, $structure);
            foreach ($parts as $part)
            {
                $content = new stdClass();
                $content->type = null;
                $content->data = null;
                $content->mime = $this->_fetchType($part->data);
                if ((isset($part->data->disposition))
                   && ((strcmp('attachment', $part->data->disposition) == 0)
                   || (strcmp('inline', $part->data->disposition) == 0)))
                {
                    $content->type = $part->data->disposition;
                    $content->name = null;
                    if (isset($part->data->dparameters))
                    {
                        $content->name = $this->_fetchParameter($part->data->dparameters, 'filename');
                    }
                    if (is_null($content->name))
                    {
                        if (isset($part->data->parameters))
                        {
                            $content->name = $this->_fetchParameter($part->data->parameters, 'name');
                        }
                    }
                    $mail->attachments[] = $content;
                }
                else if ($part->data->type == 0)
                {
                    $content->type = 'content';
                    $content->charset = null;
                    if (isset($part->data->parameters))
                    {
                        $content->charset = $this->_fetchParameter($part->data->parameters, 'charset');
                    }
                    $mail->content[] = $content;
                }
                $body = imap_fetchbody($this->box, $mail->id, $part->no);
                if (isset($part->data->encoding))
                {
                    $content->data = $this->_decode($body, $part->data->encoding);
                }
                else
                {
                    $content->data = $body;
                }
                $content->size = strlen($content->data);
            }
        }
        return $mail;
    }

    private function _fetchPartsStructureRoot($mail, $structure)
    {
        $parts = array ();
        if ((isset($structure->parts)) && (is_array($structure->parts)) && (count($structure->parts) > 0))
        {
            foreach ($structure->parts as $key => $data)
            {
                $this->_fetchPartsStructure($mail, $data, ($key + 1), $parts);
            }
        }
        return $parts;
    }

    private function _fetchPartsStructure($mail, $structure, $prefix, &$parts)
    {
        if ((isset($structure->parts)) && (is_array($structure->parts)) && (count($structure->parts) > 0))
        {
            foreach ($structure->parts as $key => $data)
            {
                $this->_fetchPartsStructure($mail, $data, $prefix . "." . ($key + 1), $parts);
            }
        }

        $part = new stdClass;
        $part->no = $prefix;
        $part->data = $structure;

        $parts[] = $part;
    }

    private function _fetchParameter($parameters, $key)
    {
        foreach ($parameters as $parameter)
        {
            if (strcmp($key, $parameter->attribute) == 0)
            {
                return $parameter->value;
            }
        }
        return null;
    }

    private function _fetchType($structure)
    {
        $primary_mime_type = array ("TEXT", "MULTIPART", "MESSAGE", "APPLICATION", "AUDIO", "IMAGE", "VIDEO", "OTHER");
        if ((isset($structure->subtype)) && ($structure->subtype) && (isset($structure->type)))
        {
            return $primary_mime_type[(int) $structure->type] . '/' . $structure->subtype;
        }
        return "TEXT/PLAIN";
    }

    private function _decode($message, $coding)
    {
        switch ($coding)
        {
            case 2:
                $message = imap_binary($message);
                break;
            case 3:
                $message = imap_base64($message);
                break;
            case 4:
                $message = imap_qprint($message);
                break;
            case 5:
                break;
            default:
                break;
        }
        return $message;
    }

    private function _callById($method, $data)
    {
        $callback = array ($this, $method);

        // data is null
        if (is_null($data))
        {
            $result = array ();
            foreach ($this->list as $mail)
            {
                $result[] = $this->_callById($method, $mail);
            }
            return $result;
        }

        // data is an array
        if (is_array($data))
        {
            $result = array ();
            foreach ($data as $elem)
            {
                $result[] = $this->_callById($method, $elem);
            }
            return $result;
        }

        // data is an object
        if ((is_object($data)) && ($data instanceof stdClass) && (isset($data->id)))
        {
            return call_user_func($callback, $data);
        }

        // data is numeric
        if (($this->isConnected()) && (is_array($this->list)) && (is_numeric($data)))
        {
            foreach ($this->list as $mail)
            {
                if ($mail->id == $data)
                {
                    return call_user_func($callback, $mail);
                }
            }
        }

        return null;
    }

    public function delete($nbr)
    {
        $this->_callById('_delete', $nbr);
        return;
    }

    private function _delete($mail)
    {
        if ($mail->deleted == false)
        {
            $test = imap_reopen($this->box, "{$mail->mbox}");
            if ($test)
            {
                $this->deleted = true;
                imap_delete($this->box, $mail->id);
                $mail->deleted = true;
            }
        }
    }

    public function searchBy($pattern, $type)
    {
        $result = array ();
        if (is_array($this->list))
        {
            foreach ($this->list as $mail)
            {
                $match = false;
                switch ($type)
                {
                    case self::FROM:
                        $match = $this->_match($mail->from, $pattern);
                        break;
                    case self::TO:
                        $match = $this->_match($mail->to, $pattern);
                        break;
                    case self::REPLY_TO:
                        $match = $this->_match($mail->reply_to, $pattern);
                        break;
                    case self::SUBJECT:
                        $match = $this->_match($mail->subject, $pattern);
                        break;
                    case self::CONTENT:
                        foreach ($mail->content as $content)
                        {
                            $match = $this->_match($content->data, $pattern);
                            if ($match)
                            {
                                break;
                            }
                        }
                        break;
                    case self::ATTACHMENT:
                        foreach ($mail->attachments as $attachment)
                        {
                            $match = $this->_match($attachment->name, $pattern);
                            if ($match)
                            {
                                break;
                            }
                        }
                        break;
                }
                if ($match)
                {
                    $result[] = $mail;
                }
            }
        }
        return $result;
    }

    private function _nmatch($string, $pattern, $a, $b)
    {
        if ((!isset($string[$a])) && (!isset($pattern[$b])))
        {
            return 1;
        }

        if ((isset($pattern[$b])) && ($pattern[$b] == '*'))
        {
            if (isset($string[$a]))
            {
                return ($this->_nmatch($string, $pattern, ($a + 1), $b) + $this->_nmatch($string, $pattern, $a, ($b + 1)));
            }
            else
            {
                return ($this->_nmatch($string, $pattern, $a, ($b + 1)));
            }
        }

        if ((isset($string[$a])) && (isset($pattern[$b])) && ($pattern[$b] == '?'))
        {
            return ($this->_nmatch($string, $pattern, ($a + 1), ($b + 1)));
        }

        if ((isset($string[$a])) && (isset($pattern[$b])) && ($pattern[$b] == '\\'))
        {
            if ((isset($pattern[($b + 1)])) && ($string[$a] == $pattern[($b + 1)]))
            {
                return ($this->_nmatch($string, $pattern, ($a + 1), ($b + 2)));
            }
        }

        if ((isset($string[$a])) && (isset($pattern[$b])) && ($string[$a] == $pattern[$b]))
        {
            return ($this->_nmatch($string, $pattern, ($a + 1), ($b + 1)));
        }

        return 0;
    }

    private function _match($string, $pattern)
    {
        return $this->_nmatch($string, $pattern, 0, 0);
    }

}