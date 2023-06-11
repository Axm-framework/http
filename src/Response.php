<?php

namespace Axm\Http;

use Axm;
use Axm\Exception\AxmException;
use RuntimeException;

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
  private $cyclic = 0;
  private $message;

  protected $allowedMimeTypes = [
    'text/html'              => ['html', 'htm'],
    'text/plain'             => ['txt'],
    'application/json'       => ['json'],
    'application/xml'        => ['xml'],
    'text/css'               => ['css'],
    'application/javascript' => ['js'],
    'image/jpeg'             => ['jpg', 'jpeg'],
    'image/png'              => ['png'],
    'image/gif'              => ['gif']
  ];

  protected $_outputType = [
    'text/plain'       => 'text/plain',
    'text/xml'         => 'text/xml',
    'text/csv'         => 'text/csv',
    'application/json' => 'application/json',
    'application/xml'  => 'application/xml',
  ];

  /**
   * HTTP 1.1 status messages based on code
   *
   * @link http://www.w3.org/Protocols/rfc2616/rfc2616-sec10.html
   * @type array
   */
  protected $http_messages = [
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

  /**
   * 
   */
  public function getStatusCode()
  {
    return http_response_code();
  }

  /**
   * 
   */
  public function setStatusCode(int $code, ?string $message = null): void
  {
    http_response_code($code);
    $this->message = $message ?: $this->getMessageFromCode($code);
  }

  /**
   * 
   */
  public function reload()
  {
    echo '<script> location.reload();</script>';
    exit;
  }

  /**
   * 
   */
  public function back(int $pos = 1)
  {
    echo '<script>history.go(-' . $pos . ');</script>';
    exit;
  }

  /**
   * 
   */
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

  /**
   * 
   */
  public function redirect($page = null)
  {
    $event = Axm::app()->event();
    $event->triggerEvent(self::EVENT_BEFORE_REDIRECT);
    $this->redirector(go($page));
    $event->triggerEvent(self::EVENT_AFTER_REDIRECT);
  }



  /**
   * Outputs the given content encoded as JSON string.
   */
  public function json($content, $code = 200, $charset = 'utf-8')
  {
    // Establecer el código de respuesta HTTP
    http_response_code($code);

    // Verificar si el contenido ya es una cadena JSON
    if (is_string($content)) {
      // Verificar si la cadena es una representación válida de JSON
      if (!json_decode($content)) {
        throw new \InvalidArgumentException('El contenido proporcionado no es una cadena JSON válida.');
      }
      // Establecer la cabecera Content-Type
      header('Content-Type: application/json;charset=' . $charset);
      // Imprimir la cadena JSON directamente
      echo $content;
    } else {
      // Convertir el contenido a una cadena JSON
      $jsonContent = json_encode($content);
      // Verificar si la conversión fue exitosa
      if ($jsonContent === false) {
        throw new \RuntimeException('No se pudo convertir el contenido a JSON.');
      }
      // Establecer la cabecera Content-Type
      header('Content-Type: application/json;charset=' . $charset);
      // Imprimir la cadena JSON
      echo $jsonContent;
    }
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
  public function toArray(string $content)
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
  public function getMessageFromCode($int)
  {
    if (isset($this->http_messages[$int]))
      return $this->http_messages[$int];
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
  public function send($content = '', $code = 200, $mimeType = 'text/plain', $charset = 'utf-8')
  {
    // Validar el código de respuesta
    if (!is_numeric($code) || $code < 100 || $code > 599) {
      throw new RuntimeException('Invalid HTTP response code');
    }

    // Establecer el código de respuesta
    http_response_code($code);

    // Establecer los encabezados personalizados
    $outType = $this->_outputType[$mimeType] ?? null;
    header(sprintf('Content-Type: %s;charset=%s', $outType, $charset));

    // Enviar el contenido
    if (is_string($content)) {
      echo $content . PHP_EOL;
    } elseif (is_array($content)) {
      foreach ($content as $value) {
        echo $value . PHP_EOL;
      }
    }

    // Detener la ejecución del script
    exit();
  }


  /**
   * Setea los headers HTTP de la respuesta.
   *
   * @param int $statusCode el código de estado HTTP de la respuesta
   * @param array $headers un arreglo asociativo de headers (nombre => valor)
   */
  public function setHeader(int $statusCode, array $headers = []): void
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
