<?php

/**
 * Cronjob Addon
 *
 * @author gharlan[at]web[dot]de Gregor Harlan
 *
 * @package redaxo4
 * @version svn:$Id$
 */

class rex_cronjob_urlrequest extends rex_cronjob
{ 
  /*public*/ function execute()
  {
    $parts = parse_url($this->getParam('url'));
    if (!isset($parts['host']))
      return array(false, 'Invalid URL');
    if (!isset($parts['scheme']))
      $parts['scheme'] = 'http';
    if (!isset($parts['port']))
    {
      switch($parts['scheme'])
      {
        case 'http' : $parts['port'] = 80;  break;
        case 'https': $parts['port'] = 443; break;
        default: return array(false, 'Unknown port');
      }
    }
    if (!isset($parts['path']))
      $parts['path'] = '/';
    if (isset($parts['query']))
      $parts['path'] .= '?'. $parts['query'];
    $sockhost = $parts['host'];
    if ($parts['scheme'] == 'https')
      $sockhost = 'ssl://'. $sockhost;

    if ($fp = @fsockopen($sockhost, $parts['port'], $errno, $errstr))
    {
      $method = 'GET';
      $out_add = '';
      $data = '';
      if ($this->getParam('http-auth') == '|1|')
      {
        $usr = $this->getParam('user');
        $pwd = $this->getParam('password');
        $out_add .= 'Authorization: Basic '. base64_encode($usr .':'. $pwd) ."\r\n";
      }
      if ($this->getParam('post') != '')
      {
        $method = 'POST';
        $data = $this->getParam('post');
        $out_add .= "Content-Type: application/x-www-form-urlencoded;\r\n";
        $out_add .= 'Content-Length: '. strlen($data) ."\r\n";
      }
      $out = $method .' '. $parts['path'] ." HTTP/1.1\r\n";
      $out .= 'Host: '. $parts['host'] ."\r\n";
      $out .= $out_add;
      $out .= "Connection: Close\r\n\r\n";
      $out .= $data;
      $content = '';
      fwrite($fp, $out);
      while (!feof($fp)) {
        $content .= fgets($fp);
      }
      fclose($fp);

      if (stripos($content, 'HTTP/') !== 0)
        return array(false, 'Unknown response');

      $lines = explode("\r\n", $content);
      $parts = explode(' ', $lines[0], 3);
      $parts[1] = (int) $parts[1];
      $success = $parts[1] >= 200 && $parts[1] < 300;
      $message = $parts[1] .' '. $parts[2];
      if (in_array($parts[1], array(301, 302, 303, 307))
        && $this->getParam('redirect', true) 
        && preg_match('/Location: ([^\s]*)/', $content, $matches)
        && isset($matches[1]))
      {
        // nur eine Umleitung zulassen
        $this->setParam('redirect', false);
        $this->setParam('url', $matches[1]);
        // rekursiv erneut ausfuehren
        $return = (array) $this->execute();
        $success = $return[0];
        if (isset($return[1]))
          $message .= ' -> '. $return[1];
        else
          $message .= ' -> Unknown error';
      }
      return array($success, $message);
    }
    return array(false, $errno .' '. $errstr);
  }
  
  /*public*/ function getTypeName()
  {
    global $I18N;
    return $I18N->msg('cronjob_type_urlrequest');
  }
  
  /*public*/ function getParamFields()
	{
		global $I18N;

		return array(
  		array(
        'label' => $I18N->msg('cronjob_type_urlrequest_url'),
        'name'  => 'url',
        'type'  => 'text',
        'default' => 'http://'
      ),
      array(
        'label' => $I18N->msg('cronjob_type_urlrequest_post'),
        'name'  => 'post',
        'type'  => 'text'
      ),
      array(
        'name'  => 'http-auth',
        'type'  => 'checkbox',
        'options' => array(1 => $I18N->msg('cronjob_type_urlrequest_httpauth'))
      ),
      array(
        'label' => $I18N->msg('cronjob_type_urlrequest_user'),
        'name'  => 'user',
        'type'  => 'text',
        'visible_if' => array('http-auth' => 1)
      ),
      array(
        'label' => $I18N->msg('cronjob_type_urlrequest_password'),
        'name'  => 'password',
        'type'  => 'text',
        'visible_if' => array('http-auth' => 1)
      )
    );
	}
}