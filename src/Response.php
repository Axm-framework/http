<?php

namespace Axm\Http;

use Axm;
use Axm\Exception\AxmException;

/**
 * Class Response
 *
 * @author  Juan Cristobal <juancristobalgd1@gmail.com>
 * @package Axm\HTTP
 */
class Response
{

  const EVENT_BEFORE_REDIRECT = 'beforeRedirect';
  const EVENT_AFTER_REDIRECT  = 'afterRedirect';

  private $_httpVersion;
  private $message;
  private $cyclic = 0;

  /**
   * HTTP 1.1 status messages based on code
   *
   * @link http://www.w3.org/Protocols/rfc2616/rfc2616-sec10.html
   * @type array
   */
  protected static $http_messages = [
    // Informational 1xx
    100 => 'Continue',
    101 => 'Switching Protocols',

    // Successful 2xx
    200 => 'OK',
    201 => 'Created',
    202 => 'Accepted',
    203 => 'Non-Authoritative Information',
    204 => 'No Content',
    205 => 'Reset Content',
    206 => 'Partial Content',

    // Redirection 3xx
    300 => 'Multiple Choices',
    301 => 'Moved Permanently',
    302 => 'Found',
    303 => 'See Other',
    304 => 'Not Modified',
    305 => 'Use Proxy',
    306 => '(Unused)',
    307 => 'Temporary Redirect',

    // Client Error 4xx
    400 => 'Bad Request',
    401 => 'Unauthorized',
    402 => 'Payment Required',
    403 => 'Forbidden, You don\'t have permission to access this page',
    404 => 'Not Found',
    405 => 'Method Not Allowed',
    406 => 'Not Acceptable',
    407 => 'Proxy Authentication Required',
    408 => 'Request Timeout',
    409 => 'Conflict',
    410 => 'Gone',
    411 => 'Length Required',
    412 => 'Precondition Failed',
    413 => 'Request Entity Too Large',
    414 => 'Request-URI Too Long',
    415 => 'Unsupported Media Type',
    416 => 'Requested Range Not Satisfiable',
    417 => 'Expectation Failed',

    // Server Error 5xx
    500 => 'Internal Server Error',
    501 => 'Not Implemented',
    502 => 'Bad Gateway',
    503 => 'Service Unavailable',
    504 => 'Gateway Timeout',
    505 => 'HTTP Version Not Supported',
  ];


  public function getStatusCode()
  {
    return http_response_code();
  }


  public function setStatusCode(int $code, $message = null)
  {
    http_response_code($code);

    if (null === $message)
      return $message = static::getMessageFromCode($code);

    return $this->message = $message;
  }


  public function reload()
  {
    echo '<script> location.reload();</script>';
    exit;
  }


  public function back(int $pos = 1)
  {
    echo '<script>history.go(-' . $pos . ');</script>';
    exit;
  }



  private function redirector($page = null, $maxRedirects = 100)
  {
    if (is_null($page)) {
      $this->reload();
      return;
    }

    if (!filter_var($page, FILTER_VALIDATE_URL)) {
      throw new AxmException('La URL proporcionada no es válida');
    }

    if (++$this->cyclic > $maxRedirects) {
      throw new AxmException('Se ha detectado un enrutamiento cíclico. Esto puede causar problemas de estabilidad');
    }

    header("Location: $page");
  }


  public function redirect($page = null)
  {
    Axm::app()->event()->triggerEvent(self::EVENT_BEFORE_REDIRECT);
    $this->redirector(go($page));
    Axm::app()->event()->triggerEvent(self::EVENT_AFTER_REDIRECT);
  }



  /**
   * Outputs the given content encoded as JSON string.
   */
  public function outJson($content, int $code = 200, string $charset = 'utf-8')
  {
    /*mime type para el contenido*/
    http_response_code($code);
    header(sprintf('Content-Type: application/json;charset=%s', $charset));
    echo json_encode($content);
  }

  /**
   * Devuelve un JSON.
   */
  public function toJson($content)
  {
    return json_encode($content);
  }

  /**
   * Devuelve un array.
   */
  public static function toArray(string $content)
  {
    return (array) json_decode($content);
  }


  /**
   * Get our HTTP 1.1 message from our passed code
   *
   * Returns null if no corresponding message was
   * found for the passed in code
   *
   * @param int $int
   * @return string|null
   */
  public static function getMessageFromCode($int)
  {
    if (isset(static::$http_messages[$int]))
      return static::$http_messages[$int];
    else
      return null;
  }


  /**
   * Returns the version of the HTTP protocol used by client.
   *
   * @return string the version of the HTTP protocol.
   * @since 1.1.16
   */
  public function getHttpVersion()
  {
    if ($this->_httpVersion === null) {
      if (isset($_SERVER['SERVER_PROTOCOL']) && $_SERVER['SERVER_PROTOCOL'] === 'HTTP/1.0')
        $this->_httpVersion = '1.0';
      else
        $this->_httpVersion = '1.1';
    }
    return $this->_httpVersion;
  }


  /**
   * Outputs the given parameters based on a HTTP response.
   *
   * @param string $content The HTTP response body
   * @param int $code The HTTP response code code
   * @param string $mimeType A value for HTTP Header Content-Type
   * @param string $charset The HTTP response charset
   *
   * @return int Returns 1, always
   */
  public function output(string $content = '', int $code = 204, string $mimeType = 'text/plain', string $charset = 'utf-8'): int
  {
    http_response_code($code);
    \header(sprintf('Content-Type: %s;charset=%s', $mimeType, $charset));

    return print $content;
  }


  /**
   * Setea los headers HTTP de la respuesta.
   *
   * @param int $statusCode el código de estado HTTP de la respuesta
   * @param array $headers un arreglo asociativo de headers (nombre => valor)
   */
  function setHeader(int $statusCode, array $headers = []): void
  {
    // Validamos el código de estado
    if ($statusCode < 100 || $statusCode > 599) {
      throw new AxmException('Código de estado HTTP inválido');
    }
    http_response_code($statusCode);

    // Validamos y establecemos los headers
    array_walk($headers, function ($value, $name) {
      $value = filter_var($value, FILTER_SANITIZE_STRING);
      header(sprintf('%s: %s', $name, $value), false);
    });
  }
}
