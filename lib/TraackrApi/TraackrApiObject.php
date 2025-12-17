<?php

namespace Traackr;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\ClientException;
use GuzzleHttp\Exception\ServerException;
use GuzzleHttp\Exception\RequestException;
use Psr\Http\Message\ResponseInterface;
use JsonMachine\Items;
use GuzzleHttp\Psr7\StreamWrapper;

abstract class TraackrApiObject
{
    public static $connectionTimeout = 10;
    public static $timeout = 10;
    public static $sslVerifyPeer = true;

    /**
     * @var Client Guzzle client instance
     */
    private $client;

    // Headers base passed with each request
    private $base_headers = [
        'Cache-Control' => 'no-cache',
        'Pragma' => 'no-cache',
        'Expect' => '', // Avoid 417 errors in some proxies
        'Accept-Charset' => 'utf-8',
        'Accept' => '*/*'
    ];

    public function __construct()
    {
        // Base configuration for the Guzzle client
        $config = [
            'connect_timeout' => self::$connectionTimeout,
            'timeout' => self::$timeout,
            'verify' => self::$sslVerifyPeer,
            'headers' => $this->base_headers,
            'http_errors' => true, // Guzzle will throw exceptions on 4xx and 5xx errors
        ];

        // Initialize Guzzle Client
        $this->client = new Client($config);
    }

    /**
    * Check if required parameters are present
    * @param array $params The parameters to check
    * @param array $fields The fields to check
    * @throws MissingParameterException If a required parameter is missing
    */
    protected function checkRequiredParams($params, $fields)
    {
        foreach ($fields as $f) {
            // empty(false) returns true so an extra test is needed for that
            if (empty($params[$f]) && !(isset($params[$f]) && is_bool($params[$f]))) {
                throw new MissingParameterException('Missing parameter: ' . $f);
            }
        }
    }

    /**
     * Add customer key to parameters
     * @param array &$params The parameters to add the customer key to
     * @return array The parameters with the customer key added
     */
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

    /**
     * Convert boolean to string
     * @param array $params The parameters to convert the boolean to a string
     * @param string $key The key of the parameter to convert
     * @return string The boolean as a string
     */
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

    /**
     * Prepare parameters
     * @param array $params The parameters to prepare
     * @return array The prepared parameters
     */
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
     * Make a request to the API
     * @param string $method The HTTP method to use
     * @param string $url The URL to request
     * @param array $options The options to pass to Guzzle
     * @param bool $decode Whether to decode the response
     * @return mixed The response from the API
     * @throws InvalidCustomerKeyException If the customer key is invalid
     * @throws MissingParameterException If a required parameter is missing
     * @throws InvalidApiKeyException If the API key is invalid
     * @throws NotFoundException If the API resource is not found
     * @throws TraackrApiException If the API call fails with a generic error
     */
    private function request($method, $url, $options, $decode)
    {
        $logger = TraackrAPI::getLogger();
        $logger->debug("Calling ({$method}): {$url}", $options);

        try {
            $response = $this->client->request($method, $url, $options);
            $body = $response->getBody()->getContents();

        } catch (ClientException $e) {
            // Handle 4xx errors
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

            // Other 4xx error
            $message = 'API HTTP Error (HTTP ' . $httpcode . ')';
            $logger->error($message);
            throw new TraackrApiException($message . ': ' . $body, $httpcode, $e);

        } catch (ServerException $e) {
            // Handle 5xx errors
            $response = $e->getResponse();
            $httpcode = $response->getStatusCode();
            $body = $response->getBody()->getContents();
            $message = 'API HTTP Error (HTTP ' . $httpcode . ')';

            $logger->error($message);
            throw new TraackrApiException($message . ': ' . $body, $httpcode, $e);

        } catch (RequestException $e) {
            // Handle network errors (timeout, DNS, etc.)
            $message = 'API call failed: ' . $e->getMessage();
            $logger->error($message);
            throw new TraackrApiException($message, 0, $e);
        }

        // Success

        if (empty($body)) { // The body can be an empty string, not null
             $logger->debug('API call successful with empty response body.');
             return false;
        }

        // API must return UTF8
        if ($decode) {
            $rez = json_decode($body, true);
            // Check JSON error
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

    /**
     * Make a GET request to the API
     * @param string $url The URL to request
     * @param array $params The parameters to pass to the API
     * @return mixed The response from the API
     */
    public function get($url, $params = [])
    {
        $api_key = TraackrApi::getApiKey();
        if (!isset($params[PARAM_API_KEY]) && !empty($api_key)) {
            $params[PARAM_API_KEY] = $api_key;
        }

        // Prepare parameters (bools to strings)
        if (!empty($params)) {
            $params = $this->prepareParameters($params);
        }

        // Guzzle options for GET
        $options = [
            'query' => $params,
            'headers' => array_merge(
                ['Content-Type' => 'application/json;charset=utf-8'],
                TraackrApi::getExtraHeaders()
            )
        ];

        return $this->request('GET', $url, $options, !TraackrAPI::isJsonOutput());
    }

    /**
     * Make a POST request to the API
     * @param string $url The URL to request
     * @param array $params The parameters to pass to the API
     * @param bool $isJson Whether to send the parameters as JSON
     * @return mixed The response from the API
     */
    public function post($url, $params = [], $isJson = false)
    {
        $api_key = TraackrApi::getApiKey();
        if (!empty($api_key)) {
            $url .= '?' . PARAM_API_KEY . '=' . $api_key;
        }

        $options = [];

        if (!$isJson) {
            // application/x-www-form-urlencoded
            $params = $this->prepareParameters($params);
            $options['form_params'] = $params; 
            $contentType = 'application/x-www-form-urlencoded;charset=utf-8';
        } else {
            // application/json
            $options['json'] = $params;
            $contentType = 'application/json;charset=utf-8';
        }

        // Add headers
        $options['headers'] = array_merge(
            ['Content-Type' => $contentType],
            TraackrApi::getExtraHeaders()
        );

        return $this->request('POST', $url, $options, !TraackrAPI::isJsonOutput());
    }

    /**
     * Make a DELETE request to the API
     * @param string $url The URL to request
     * @param array $params The parameters to pass to the API
     * @return mixed The response from the API
     */
    public function delete($url, $params = [])
    {
        $api_key = TraackrApi::getApiKey();
        if (!empty($api_key)) {
            $url .= '?' . PARAM_API_KEY . '=' . $api_key;
        }

        $params = $this->prepareParameters($params);

        $options = [
            'form_params' => $params,
            'headers' => array_merge(
                ['Content-Type' => 'application/x-www-form-urlencoded;charset=utf-8'],
                TraackrApi::getExtraHeaders()
            )
        ];

        return $this->request('DELETE', $url, $options, false);
    }

    /**
     * Make a POST request to the API and yield items
     * @param string $url The URL to request
     * @param array $params The parameters to pass to the API
     * @param string $entityKey The key of the entity list to yield (e.g., 'posts')
     * @return \Generator Returns a generator that yields each batch of items
     * @throws TraackrApiException If the JSON response is invalid
     * Other exceptions are thrown and described in the request() method
     */
    public function postStream($url, $params = [], $entityKey = 'influencers')
    {
        $logger = TraackrAPI::getLogger();
        $api_key = TraackrApi::getApiKey();
        if (!empty($api_key)) {
            $url .= '?' . PARAM_API_KEY . '=' . $api_key;
        }

        $params = $this->prepareParameters($params);

        $options = [
            'form_params' => $params,
            'stream' => true,
            'headers' => array_merge(
                ['Content-Type' => 'application/x-www-form-urlencoded;charset=utf-8'],
                TraackrApi::getExtraHeaders()
            )
        ];

        $logger->debug('Calling (STREAM): ' . $url . ' with params: ' . json_encode($params));

        try {
            $response = $this->client->request('POST', $url, $options);
            $phpStream = StreamWrapper::getResource($response->getBody());

            // When there is not results, the api returns an empty body. We return a default page info.
            if (fgetc($phpStream) === false) {
                return [
                    'page_info' => [
                        'current_page' => 0,
                        'has_more' => false,
                        'next_page' => 0,
                        'page_count' => 0,
                        'results_count' => 0,
                        'total_results_count' => 0,
                        'total_results_count_capped' => false
                    ],
                    $entityKey => []
                ];
            }

            rewind($phpStream);
            $pageInfoIterator = Items::fromStream($phpStream, [
                'pointer' => '/page_info'
            ]);

            $pageInfo = iterator_to_array($pageInfoIterator)['page_info'] ?? null;
            
            // Rewind the stream to the beginning
            rewind($phpStream);

            $items = Items::fromStream($phpStream, [
                'pointer' => '/' . $entityKey
            ]);
            
            return [
                'page_info' => $pageInfo,
                [$entityKey] => $items
            ];
        } catch (InvalidArgumentException $e) {
            // Handle invalid JSON response
            $logger->error('Invalid JSON response: ' . $e->getMessage());
            throw new TraackrApiException('Invalid JSON response: ' . $e->getMessage(), 0, $e);
        }
    }
}
