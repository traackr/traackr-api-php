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
}
