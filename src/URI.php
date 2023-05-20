<?php

namespace Axm\Http;

use Axm;
use Axm\Exception\AxmException;

/**
 * Class Request
 *
 * @author  Juan Cristobal <juancristobalgd1@gmail.com>
 * @package Axm\HTTP
 */
abstract class URI
{
    /**
     * Current URI string
     * $uriString es el retorno filtrado de la uri sin quitar la roodir
     * @var string
     */
    protected $cleanedUri;

    /**
     * List of URI segments.
     *
     * Starts at 1 instead of 0
     *
     * @var array
     */
    protected $segments = [];

    /**
     * The URI Scheme.
     *
     * @var string
     */
    protected $scheme = 'http';

    /**
     * URI User Info
     *
     * @var string
     */
    protected $user;

    /**
     * URI User Password
     *
     * @var string
     */
    protected $password;

    /**
     * URI Host
     *
     * @var string
     */
    protected $host;

    /**
     * URI Port
     *
     * @var int
     */
    protected $port;

    /**
     * URI path.
     *
     * @var string
     */
    protected $path;

    /**
     * The name of any fragment.
     *
     * @var string
     */
    protected $fragment = '';

    /**
     * The query string.
     *
     * @var array
     */
    protected $query = [];

    /**
     * Default schemes/ports.
     *
     * @var array
     */
    protected $defaultPorts = [
        'http'  => 80,
        'https' => 443,
        'ftp'   => 21,
        'sftp'  => 22,
    ];

    /**
     * Whether passwords should be shown in userInfo/authority calls.
     * Default to false because URIs often show up in logs
     *
     * @var bool
     */
    protected $showPassword = false;

    /**
     * If true, will continue instead of throwing exceptions.
     *
     * @var bool
     */
    protected $silent = false;

    /**
     * If true, will use raw query string.
     *
     * @var bool
     */
    protected $rawQueryString = false;

    /**
     * If true, will use raw query string.
     *
     * @var bool
     */
    protected $uri;

    /**
     * 
     */
    private $pathClearUriLength;

    /**
     * Returns the cleaned and formatted URI for the current request.
     * 
     * @return string The cleaned and formatted URI for the current request.
     */
    // public function getUri(): ?string
    // {
    //     $path             = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
    //     $this->cleanedUri = filter_var($path, FILTER_SANITIZE_URL);
    //     $baseUri          = str_replace(PATH_CLEAR_URI, '', $this->cleanedUri);
    //     $formattedUri     = '/' . trim($baseUri, '/');

    //     // Validación adicional para asegurarse de que la URI sea segura
    //     $formattedUri     = preg_replace('/[^a-zA-Z0-9\/\-\_\.\~]/', '', $formattedUri);
    //     $queryStringStart = strpos($formattedUri, '?');

    //     if ($queryStringStart !== false) {
    //         $formattedUri = substr($formattedUri, 0, $queryStringStart);
    //     }

    //     return $formattedUri;
    // }

    /**
     * Define the current relative URI.
     *
     * @return string
     */
    public function getUri(): ?string
    {

        // Get the current Request URI and remove rewrite base path from it
        $uri = substr(parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH), $this->pathClearUriLength());

        if (strstr($uri, '?')) {
            $uri = substr($uri, 0, strpos($uri, '?'));
        }

        return '/' . trim($uri, '/');
    }


    /**
     * Devuelve e argo de PATH_CLEAR_URI
     */
    private function pathClearUriLength()
    {
        return $this->pathClearUriLength ??= strlen(PATH_CLEAR_URI);
    }


    /**
     * Obtiene la Uri string.
     *
     * @return string $uriString
     */
    public function getUriString(): ?string
    {
        return $this->cleanedUri;
    }


    /**
     * Builds a representation of the string from the component parts.     *
     */
    protected static function createURIString(?string $scheme = null, ?string $host = null, ?string $path = null, string|object|array $query = null, ?string $fragment = null): string
    {
        $route = $scheme . '://' . $host . $path;

        if (!empty($query)) {
            $route .= '?' . http_build_query($query, '', '&', PHP_QUERY_RFC3986);
        }

        if (!empty($fragment)) {
            $route .= '#' . rawurlencode($fragment);
        }

        return $route;
    }

    /**
     * Used when resolving and merging paths to correctly interpret and
     * remove single and double dot segments from the path per
     * RFC 3986 Section 5.2.4
     *
     * @see http://tools.ietf.org/html/rfc3986#section-5.2.4
     *
     * @internal
     */
    protected static function removeDotSegments(string $path): string
    {
        if ($path === '' || $path === '/') {
            return $path;
        }

        $output = [];

        $input = explode('/', $path);

        if ($input[0] === '') {
            unset($input[0]);
            $input = array_values($input);
        }

        // This is not a perfect representation of the
        // RFC, but matches most cases and is pretty
        // much what Guzzle uses. Should be good enough
        // for almost every real use case.
        foreach ($input as $segment) :
            if ($segment === '..')
                array_pop($output);
            elseif ($segment !== '.' && $segment !== '')
                $output[] = $segment;
        endforeach;

        $output = implode('/', $output);
        $output = trim($output, '/ ');

        // Add leading slash if necessary
        if (strpos($path, '/') === 0) {
            $output = '/' . $output;
        }

        // Add trailing slash if necessary
        if ($output !== '/' && substr($path, -1, 1) === '/') {
            $output .= '/';
        }

        return $output;
    }


    /**
     * Retrieve the scheme component of the URI.
     *
     * If no scheme is present, this method MUST return an empty string.
     *
     * The value returned MUST be normalized to lowercase, per RFC 3986
     * Section 3.1.
     *
     * The trailing ":" character is not part of the scheme and MUST NOT be
     * added.
     *
     * @see    https://tools.ietf.org/html/rfc3986#section-3.1
     *
     * @return string The URI scheme.
     */
    public function getScheme(): string
    {
        // Check for forced HTTPS
        if (Axm::app()->config()->get('forceGlobalSecureRequests')) {
            $this->scheme = 'https';
        }

        return $this->scheme ?? $_SERVER['REQUEST_SCHEME'];
    }


    /**
     * Retrieve the authority component of the URI.
     *
     * If no authority information is present, this method MUST return an empty
     * string.
     *
     * The authority syntax of the URI is:
     *
     * <pre>
     * [user-info@]host[:port]
     * </pre>
     *
     * If the port component is not set or is the standard port for the current
     * scheme, it SHOULD NOT be included.
     *
     * @see https://tools.ietf.org/html/rfc3986#section-3.2
     *
     * @return string The URI authority, in "[user-info@]host[:port]" format.
     */
    protected function getAuthority(bool $ignorePort = false): string
    {
        if (empty($this->host)) return '';

        $authority = $this->host;
        if (!empty($userInfo = $this->getUserInfo())) {
            $authority = $userInfo . '@' . $authority;
        }
        if (!empty($this->port) && !$ignorePort && $this->port !== $this->defaultPorts[$this->scheme]) {
            $authority .= ':' . $this->port;
        }

        $this->showPassword = false;
        return $authority;
    }


    /**
     * Retrieve the user information component of the URI.
     *
     * If no user information is present, this method MUST return an empty
     * string.
     *
     * If a user is present in the URI, this will return that value;
     * additionally, if the password is also present, it will be appended to the
     * user value, with a colon (":") separating the values.
     *
     * NOTE that be default, the password, if available, will NOT be shown
     * as a security measure as discussed in RFC 3986, Section 7.5. If you know
     * the password is not a security issue, you can force it to be shown
     * with $this->showPassword();
     *
     * The trailing "@" character is not part of the user information and MUST
     * NOT be added.
     *
     * @return string|null The URI user information, in "username[:password]" format.
     */
    protected function getUserInfo()
    {
        $userInfo = $this->user;
        if ($this->showPassword === true && !empty($this->password)) {
            $userInfo .= ':' . $this->password;
        }

        return $userInfo;
    }



    /**
     * Temporarily sets the URI to show a password in userInfo. Will
     * reset itself after the first call to authority().
     *
     * @return URI
     */
    protected function showPassword(bool $val = true)
    {
        $this->showPassword = $val;

        return $this;
    }


    /**
     * Retrieve the host component of the URI.
     *
     * If no host is present, this method MUST return an empty string.
     *
     * The value returned MUST be normalized to lowercase, per RFC 3986
     * Section 3.2.2.
     *
     * @see    http://tools.ietf.org/html/rfc3986#section-3.2.2
     *
     * @return string The URI host.
     */
    protected function getHost(): string
    {
        return $this->host ?? $_SERVER["HTTP_HOST"];
    }


    /**
     * Retrieve the port component of the URI.
     *
     * If a port is present, and it is non-standard for the current scheme,
     * this method MUST return it as an integer. If the port is the standard port
     * used with the current scheme, this method SHOULD return null.
     *
     * If no port is present, and no scheme is present, this method MUST return
     * a null value.
     *
     * If no port is present, but a scheme is present, this method MAY return
     * the standard port for that scheme, but SHOULD return null.
     *
     * @return int|null The URI port.
     */
    protected function getPort()
    {
        return $this->port ?? $_SERVER["HTTP_PORT"];
    }


    /**
     * Retrieve the path component of the URI.
     *
     * @return string The URI path.
     */
    protected function getPath(): string
    {
        return $this->path ?? '';
    }


    /**
     * Esta función toma un array de opciones y una variable interna llamada $query, y devuelve una cadena de 
     * consulta HTTP construida a partir de $query.
     * Dependiendo de las claves especificadas en el array de opciones, la función puede incluir solo ciertas 
     * claves de $query o excluir ciertas claves de $query antes de construir la cadena de consulta HTTP.
     */
    protected function getQueryString(array $options = []): ?array
    {
        $query = $this->query;

        if (is_array($options)) {
            if (array_key_exists('except', $options)) {
                $query = array_diff_key($query, array_flip($options['except']));
            } elseif (array_key_exists('only', $options)) {
                $query = array_intersect_key($query, array_flip($options['only']));
            }
        }

        return ($query);
    }


    /**
     * Retrieve the query string
     */
    public function getQuery(array $options = []): string
    {
        $vars = $this->query;

        if (array_key_exists('except', $options)) {
            if (!is_array($options['except'])) {
                $options['except'] = [$options['except']];
            }

            foreach ($options['except'] as $var) {
                unset($vars[$var]);
            }
        } elseif (array_key_exists('only', $options)) {
            $temp = [];

            if (!is_array($options['only'])) {
                $options['only'] = [$options['only']];
            }

            foreach ($options['only'] as $var) {
                if (array_key_exists($var, $vars)) {
                    $temp[$var] = $vars[$var];
                }
            }

            $vars = $temp;
        }

        return empty($vars) ? '' : http_build_query($vars);
    }


    /**
     * Retrieve a URI fragment
     */
    protected function getFragment(): string
    {
        return $this->fragment ?? '';
    }


    /**
     * Returns the segments of the path as an array.
     */
    protected function getSegments(): array
    {
        return $this->segments;
    }


    /**
     * Returns the value of a specific segment of the URI path.
     *
     * @param int    $number  Segment number
     * @param string $default Default value
     *
     * @return string The value of the segment. If no segment is found,
     *                throws InvalidArgumentError
     */
    protected function getSegment(int $number, string $default = ''): string
    {
        // The segment should treat the array as 1-based for the user
        // but we still have to deal with a zero-based array.
        $number--;

        if ($number > count($this->segments) && !$this->silent)
            throw new AxmException("segmento fuera de rango $number");

        return $this->segments[$number] ?? $default;
    }


    /**
     * Returns the total number of segments.
     */
    protected function getTotalSegments(): int
    {
        return count($this->segments);
    }


    protected function createNewUrl(string $uri): string
    {
        $scheme = $this->getScheme();
        $authority = $this->getAuthority();
        $query = $this->getQuery();
        $fragment = $this->getFragment();

        return static::createURIString($scheme, $authority, $uri, $query, $fragment);
    }


    protected function getUrl(): string
    {
        $scheme = $this->getScheme();
        $authority = $this->getAuthority();
        $cleanedUri = $this->cleanedUri ?? '';
        $query = $this->getQuery();
        $fragment = $this->getFragment();

        return static::createURIString($scheme, $authority, $cleanedUri, $query, $fragment);
    }
}
