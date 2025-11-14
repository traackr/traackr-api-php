<?php

namespace Traackr;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\ClientException;
use GuzzleHttp\Exception\ServerException;
use GuzzleHttp\Exception\RequestException;
use Psr\Http\Message\ResponseInterface;

abstract class TraackrApiObject
{
    public static $connectionTimeout = 10;
    public static $timeout = 10;
    public static $sslVerifyPeer = true;

    /**
     * @var Client Instancia del cliente Guzzle
     */
    private $client;

    // Headers base pasados con cada request
    private $base_headers = [
        'Cache-Control' => 'no-cache',
        'Pragma' => 'no-cache',
        'Expect' => '', // Evita errores 417 en algunos proxies
        'Accept-Charset' => 'utf-8',
        'Accept' => '*/*'
    ];

    public function __construct()
    {
        // Configuración base para el cliente Guzzle
        $config = [
            'connect_timeout' => self::$connectionTimeout,
            'timeout' => self::$timeout,
            'verify' => self::$sslVerifyPeer,
            'headers' => $this->base_headers,
            'http_errors' => true, // Guzzle lanzará excepciones en errores 4xx y 5xx
        ];

        // init Guzzle Client
        $this->client = new Client($config);
    }

    /**
     * initCurlOpts() ya no es necesario, Guzzle se configura en el constructor.
     */

    protected function checkRequiredParams($params, $fields)
    {
        foreach ($fields as $f) {
            // empty(false) returns true so need extra test for that
            if (empty($params[$f]) && !(isset($params[$f]) && is_bool($params[$f]))) {
                throw new MissingParameterException('Missing parameter: ' . $f);
            }
        }
    }

    protected function addCustomerKey(&$params)
    {
        $key = TraackrApi::getCustomerKey();
        if (!empty($key) && empty($params[PARAM_CUSTOMER_KEY])) {
            $params[PARAM_CUSTOMER_KEY] = $key;
        }

        if (!empty($params[PARAM_CUSTOMER_KEY]) && is_array($params[PARAM_CUSTOMER_KEY])) {
            $params[PARAM_CUSTOMER_KEY] = implode(',', $params[PARAM_CUSTOMER_KEY]);
        }

        return $params;
    }

    protected function convertBool($params, $key)
    {
        if (!isset($params[$key])) {
            return 'false';
        }

        $bool = $params[$key];

        if (is_bool($bool)) {
            return $bool ? 'true' : 'false';
        }

        if (strtolower($bool) === 'true') {
            return 'true';
        }

        return 'false';
    }

    private function prepareParameters($params)
    {
        foreach ($params as $key => $value) {
            if ($params[$key] === true) {
                $params[$key] = 'true';
            }

            if ($params[$key] === false) {
                $params[$key] = 'false';
            }
        }

        return $params;
    }

    /**
     * Método wrapper principal para realizar todas las llamadas con Guzzle.
     * Maneja las excepciones de Guzzle y las convierte en las excepciones personalizadas.
     */
    private function request($method, $url, $options, $decode)
    {
        $logger = TraackrAPI::getLogger();
        $logger->debug("Calling ({$method}): {$url}", $options);

        try {
            $response = $this->client->request($method, $url, $options);
            $body = $response->getBody()->getContents();

        } catch (ClientException $e) {
            // --- Manejo de errores 4xx ---
            $response = $e->getResponse();
            $httpcode = $response->getStatusCode();
            $body = $response->getBody()->getContents();

            if ($httpcode === 400) {
                if ($body === 'Customer key not found') {
                    $message = 'Invalid Customer Key (HTTP 400)';
                    $logger->error($message);
                    throw new InvalidCustomerKeyException($message . ': ' . $body, $httpcode, $e);
                }
                $message = 'Missing or Invalid argument/parameter (HTTP 400)';
                $logger->error($message);
                throw new MissingParameterException($message . ': ' . $body, $httpcode, $e);
            }

            if ($httpcode === 403) {
                $message = 'Invalid API key (HTTP 403)';
                $logger->error($message);
                throw new InvalidApiKeyException($message . ': ' . $body, $httpcode, $e);
            }

            if ($httpcode === 404) {
                $message = 'API resource not found (HTTP 404)';
                $logger->error($message);
                throw new NotFoundException($message . ': ' . $url, $httpcode, $e);
            }

            // Otro error 4xx
            $message = 'API HTTP Error (HTTP ' . $httpcode . ')';
            $logger->error($message);
            throw new TraackrApiException($message . ': ' . $body, $httpcode, $e);

        } catch (ServerException $e) {
            // --- Manejo de errores 5xx ---
            $response = $e->getResponse();
            $httpcode = $response->getStatusCode();
            $body = $response->getBody()->getContents();
            $message = 'API HTTP Error (HTTP ' . $httpcode . ')';

            $logger->error($message);
            throw new TraackrApiException($message . ': ' . $body, $httpcode, $e);

        } catch (RequestException $e) {
            // --- Manejo de errores de red (timeout, DNS, etc.) ---
            $message = 'API call failed: ' . $e->getMessage();
            $logger->error($message);
            throw new TraackrApiException($message, 0, $e);
        }

        // --- Éxito ---

        if (empty($body)) { // El body puede ser string vacío, no null
             $logger->debug('API call successful with empty response body.');
             return false; // Replicando lógica original de 'null === $rez'
        }

        // API MUST return UTF8
        if ($decode) {
            $rez = json_decode($body, true);
            // Comprobar error de JSON
            if (json_last_error() !== JSON_ERROR_NONE) {
                 $message = 'Failed to decode JSON response: ' . json_last_error_msg();
                 $logger->error($message);
                 throw new TraackrApiException($message . ': ' . $body);
            }
        } else {
            $rez = $body;
        }

        return null === $rez ? false : $rez;
    }

    public function get($url, $params = [])
    {
        // Add API key parameter if not present
        $api_key = TraackrApi::getApiKey();
        if (!isset($params[PARAM_API_KEY]) && !empty($api_key)) {
            $params[PARAM_API_KEY] = $api_key;
        }

        // Preparar parámetros (bools a strings)
        if (!empty($params)) {
            $params = $this->prepareParameters($params);
        }

        // Opciones de Guzzle para GET
        $options = [
            'query' => $params, // Guzzle construye la query string
            'headers' => array_merge(
                ['Content-Type' => 'application/json;charset=utf-8'],
                TraackrApi::getExtraHeaders()
            )
        ];

        return $this->request('GET', $url, $options, !TraackrAPI::isJsonOutput());
    }

    public function post($url, $params = [], $isJson = false)
    {
        // Add API key parameter to URL query string
        $api_key = TraackrApi::getApiKey();
        if (!empty($api_key)) {
            $url .= '?' . PARAM_API_KEY . '=' . $api_key;
        }

        $options = [];

        if (!$isJson) {
            // application/x-www-form-urlencoded
            $params = $this->prepareParameters($params);
            $options['form_params'] = $params; // Guzzle maneja el encoding
            $contentType = 'application/x-www-form-urlencoded;charset=utf-8';
        } else {
            // application/json
            // No es necesario 'prepareParameters' si Guzzle envía JSON
            $options['json'] = $params; // Guzzle maneja json_encode y Content-Type
            $contentType = 'application/json;charset=utf-8';
        }

        // Añadir headers
        $options['headers'] = array_merge(
            ['Content-Type' => $contentType],
            TraackrApi::getExtraHeaders()
        );

        return $this->request('POST', $url, $options, !TraackrAPI::isJsonOutput());
    }

    public function delete($url, $params = [])
    {
        // Add API key parameter to URL query string
        $api_key = TraackrApi::getApiKey();
        if (!empty($api_key)) {
            $url .= '?' . PARAM_API_KEY . '=' . $api_key;
        }

        // Preparar parámetros
        $params = $this->prepareParameters($params);

        // Opciones de Guzzle para DELETE (enviando como form params en el body)
        $options = [
            'form_params' => $params,
            'headers' => array_merge(
                ['Content-Type' => 'application/x-www-form-urlencoded;charset=utf-8'],
                TraackrApi::getExtraHeaders()
            )
        ];

        return $this->request('DELETE', $url, $options, false);
    }

    private function processNdjsonBuffer($ndjsonBuffer, $entityKey = 'influencers')
    {
        $mergedResponse = [
            $entityKey => [],
            'errors' => [],
            'count' => 0,
        ];

        // Dividimos el buffer por saltos de línea
        $lines = explode("\n", $ndjsonBuffer);

        foreach ($lines as $line) {
            if (empty(trim($line))) {
                continue; // Ignorar líneas vacías
            }

            $jsonLine = json_decode($line, true);
            if (json_last_error() !== JSON_ERROR_NONE) {
                $mergedResponse['errors'][] = [
                    'message' => 'Failed to decode JSON line: ' . json_last_error_msg(),
                    'line' => $line
                ];
                continue;
            }

            // Asumimos que cada línea tiene una estructura { "influencers": [...] }
            if (isset($jsonLine[$entityKey]) && is_array($jsonLine[$entityKey])) {
                $mergedResponse[$entityKey] = array_merge(
                    $mergedResponse[$entityKey],
                    $jsonLine[$entityKey]
                );
            }
            
            // Acumulamos el conteo si existe
            if (isset($jsonLine['count'])) {
                $mergedResponse['count'] += (int)$jsonLine['count'];
            }
        }

        return $mergedResponse;
    }


    public function postStream($url, $params = [], $entityKey = 'influencers')
    {
        $logger = TraackrAPI::getLogger();

        // 1. Añadir API key al query string de la URL
        $api_key = TraackrApi::getApiKey();
        if (!empty($api_key)) {
            $url .= '?' . PARAM_API_KEY . '=' . $api_key;
        }

        // 2. Preparar parámetros (bools a strings)
        $params = $this->prepareParameters($params);

        // 3. Opciones de Guzzle para la solicitud
        $options = [
            // Usar 'form_params' para 'application/x-www-form-urlencoded'
            'form_params' => $params,
            
            // --- ¡LA CLAVE! ---
            // Pedir a Guzzle que no descargue todo, sino que nos dé el "grifo"
            'stream' => true, 
            
            'headers' => array_merge(
                ['Content-Type' => 'application/x-www-form-urlencoded;charset=utf-8'],
                TraackrApi::getExtraHeaders()
            )
        ];

        // Logueamos antes de la llamada
        $logger->debug('Calling (POST-STREAM): ' . $url . ' [' . http_build_query($params) . ']');
        
        $rawNdjsonBuffer = '';

        try {
            // 4. Hacer la llamada
            $response = $this->client->request('POST', $url, $options);
            
            // 5. Obtener el cuerpo (el "grifo" de datos)
            $bodyStream = $response->getBody();

            // 6. Leer del "grifo" en trozos (chunks) hasta que se acabe
            while (!$bodyStream->eof()) {
                $rawNdjsonBuffer .= $bodyStream->read(1024); // Leemos en trozos de 1KB
            }
            
            $httpcode = $response->getStatusCode(); // Debería ser 2xx

        } catch (ClientException | ServerException $e) {
            // 7. Manejar errores 4xx/5xx
            $response = $e->getResponse();
            $httpcode = $response->getStatusCode();
            // Leer el cuerpo del error (Guzzle lo bufferea en caso de error)
            $errorMessage = $response->getBody()->getContents();

            // Re-implementar la lógica de errores del cURL original
            if ($httpcode === 400) {
                if ($errorMessage === 'Customer key not found') {
                    throw new InvalidCustomerKeyException('Invalid Customer Key (HTTP 400): ' . $errorMessage, $httpcode, $e);
                }
                throw new MissingParameterException('Missing or Invalid argument/parameter (HTTP 400): ' . $errorMessage, $httpcode, $e);
            }
            if ($httpcode === 403) {
                throw new InvalidApiKeyException('Invalid API key (HTTP 403): ' . $errorMessage, $httpcode, $e);
            }
            if ($httpcode === 404) {
                throw new NotFoundException('API resource not found (HTTP 404): ' . $url, $httpcode, $e);
            }
            // Error general
            throw new TraackrApiException('API HTTP Error (HTTP ' . $httpcode . '): ' . $errorMessage, $httpcode, $e);

        } catch (RequestException $e) {
            // 8. Manejar errores de red (timeout, DNS, etc.)
            $message = 'API stream call failed: ' . $e->getMessage();
            $logger->error($message);
            throw new TraackrApiException($message, 0, $e);
        }

        // 9. Procesar la respuesta exitosa (igual que en el original)
        
        // Si la API está configurada para devolver JSON crudo, devolvemos el buffer
        if (TraackrAPI::isJsonOutput()) {
            return $rawNdjsonBuffer;
        }

        // Si no, procesamos el buffer NDJSON con nuestro método helper
        // Pasamos el buffer como argumento, ya no usamos una propiedad de clase
        return $this->processNdjsonBuffer($rawNdjsonBuffer, $entityKey);
    }
}
