<?php
/**
 * API abstract class
 *
 * @category Abstract
 * @author   Romain Laneuville <romain.laneuville@hotmail.fr>
 */

namespace abstracts;

use \classes\ExceptionManager as Exception;

/**
 * Abstract API pattern
 *
 * @abstract
 */
abstract class API
{
    /**
     * @var array $HTTP_MESSAGE_STATUS HTTP message code
     * @link https://en.wikipedia.org/wiki/List_of_HTTP_status_codes Description there
     */
    public static $HTTP_MESSAGE_STATUS = array(
        100 => 'Continue',
        101 => 'Switching Protocols',
        102 => 'Processing',
        200 => 'OK',
        201 => 'Created',
        202 => 'Accepted',
        203 => 'Non-Authoritative Information',
        204 => 'No Content',
        205 => 'Reset Content',
        206 => 'Partial Content',
        207 => 'Multi-Status',
        210 => 'Content Different',
        226 => 'IM Used',
        300 => 'Multiple Choices',
        301 => 'Moved Permanently',
        302 => 'Moved Temporarily',
        303 => 'See Other',
        304 => 'Not Modified',
        305 => 'Use Proxy',
        306 => '',
        307 => 'Temporary Redirect',
        308 => 'Permanent Redirect',
        310 => 'Too many Redirects',
        400 => 'Bad Request',
        401 => 'Unauthorized',
        402 => 'Payment Required',
        403 => 'Forbidden',
        404 => 'Not Found',
        405 => 'Method Not Allowed',
        406 => 'Not Acceptable',
        407 => 'Proxy Authentication Required',
        408 => 'Request Time-out',
        409 => 'Conflict',
        410 => 'Gone',
        411 => 'Length Required',
        412 => 'Precondition Failed',
        413 => 'Request Entity Too Large',
        414 => 'Request-URI Too Long',
        415 => 'Unsupported Media Type',
        416 => 'Requested range unsatisfiable',
        417 => 'Expectation failed',
        418 => 'I\'m a teapot',
        422 => 'Unprocessable entity',
        423 => 'Locked',
        424 => 'Method failure',
        425 => 'Unordered Collection',
        426 => 'Upgrade Required',
        428 => 'Precondition Required',
        429 => 'Too Many Requests',
        431 => 'Request Header Fields Too Large',
        449 => 'Retry With',
        450 => 'Blocked by Windows Parental Controls',
        451 => 'Unavailable For Legal Reason',
        456 => 'Unrecoverable Error',
        499 => 'client has closed connection',
        500 => 'Internal Server Error',
        501 => 'Not Implemented',
        502 => 'Bad Gateway ou Proxy Error',
        503 => 'Service Unavailable',
        504 => 'Gateway Time-out',
        505 => 'HTTP Version not supported',
        506 => 'Variant also negociate',
        507 => 'Insufficient storage',
        508 => 'Loop detected',
        509 => 'Bandwidth Limit Exceeded',
        510 => 'Not extended',
        511 => 'Network authentication required',
        520 => 'Web server is returning an unknown error'
    );

    /**
     * @var string $method The HTTP method this request was made in, either GET, POST, PUT or DELETE
     */
    protected $method;
    /**
     * @var string $endpoint The Model requested in the URI. eg: /files
     */
    protected $endpoint;
    /**
     * @var string $verb An optional additional descriptor about the endpoint, used for things that can not be handled
     * by the basic methods. eg: /files/process
     */
    protected $verb;
    /**
     * @var array $args Any additional URI components after the endpoint and verb have been removed, in our case, an
     * integer ID for the resource. eg: /<endpoint>/<verb>/<arg0>/<arg1> or /<endpoint>/<arg0>
     */
    protected $args = array();
    /**
     * @var string $file Stores the input of the PUT request
     */
    protected $file;

    /**
     * Constructor, allow for CORS, assemble and pre-process the data
     *
     * @param array $request The request to treat
     */
    public function __construct($request)
    {
        header('Access-Control-Allow-Orgin: *');
        header('Access-Control-Allow-Methods: *');
        header('Content-Type: application/json');

        $this->args     = explode('/', rtrim($request, '/'));
        $this->endpoint = array_shift($this->args);

        if (array_key_exists(0, $this->args) && !is_numeric($this->args[0])) {
            $this->verb = array_shift($this->args);
        }

        $this->method = $_SERVER['REQUEST_METHOD'];

        if ($this->method === 'POST' && array_key_exists('HTTP_X_HTTP_METHOD', $_SERVER)) {
            if ($_SERVER['HTTP_X_HTTP_METHOD'] === 'DELETE') {
                $this->method = 'DELETE';
            } elseif ($_SERVER['HTTP_X_HTTP_METHOD'] === 'PUT') {
                $this->method = 'PUT';
            } else {
                throw new Exception('Unexpected Header', Exception::PARAMETER);
            }
        }

        switch ($this->method) {
        case 'DELETE':
        case 'POST':
            $this->request = $_POST;
            break;

        case 'GET':
            $this->request = $_GET;
            break;

        case 'PUT':
            $this->request = $_GET;
            $this->file = file_get_contents('php://input');
            break;

        default:
            $this->response(400);
            break;
        }
    }

    /**
     * Call the API method with parameters
     *
     * @return string The JSON response as a string
     */
    public function processAPI()
    {
        if (method_exists($this, $this->endpoint)) {
            return $this->response($this->{$this->endpoint}($this->args));
        }

        return $this->response(404, 'No endpoint: '. $this->endpoint);
    }

    /**
     * Format the response in a JSON format
     *
     * @param  integer $status The HTTP repsonse code DEFAULT 200
     * @param  array   $data   The respsonse data to parse DEFAULT array()
     * @return string          The repsonse in a JSON format as a string
     */
    private function response($status = 200, $data = array())
    {
        header('HTTP/1.1 ' . $status . ' ' . static::$HTTP_MESSAGE_STATUS[$status]);

        return json_encode($data);
    }
}
