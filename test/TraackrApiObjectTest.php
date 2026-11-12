<?php

use GuzzleHttp\Client;
use GuzzleHttp\Exception\ConnectException;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Middleware;
use GuzzleHttp\Psr7\NoSeekStream;
use GuzzleHttp\Psr7\Request;
use GuzzleHttp\Psr7\Response;

class CapturingApiLogger implements Traackr\ApiLoggerInterface {

   public $lines = [];

   public function debug($string) {
      $this->lines[] = $string;
   }

   public function error($string) {
      $this->lines[] = $string;
   }

} // End class CapturingApiLogger

class TraackrApiObjectTest extends PHPUnit\Framework\TestCase {

   // search-api's real answer to an influencers list over the cap (SUPP-1098)
   const CAP_BODY = '`influencers` Number of values must be between 1-65000 (inclusive)';

   /**
    * Tomcat answers any Expect value other than 100-continue, an empty one included, with a 417.
    *
    * @group read-only
    */
   public function testRequestsCarryNoExpectHeader() {

      $history = [];
      $stack = HandlerStack::create(new MockHandler([new Response(200, [], '{}')]));
      $stack->push(Middleware::history($history));

      // Rebuild the library's own client config around a mock handler
      $property = new ReflectionProperty(Traackr\TraackrApiObject::class, 'client');
      $property->setAccessible(true);
      $config = $property->getValue(new Traackr\Posts())->getConfig();
      $client = new Client(['handler' => $stack] + $config);

      // Over 1 MB, the size at which Guzzle would otherwise add "Expect: 100-continue"
      $client->request('POST', 'http://traackr.invalid/1.0/posts/search', [
         'form_params' => ['keywords' => str_repeat('k', 1100000)],
      ]);

      $this->assertCount(1, $history);
      $this->assertFalse($history[0]['request']->hasHeader('Expect'));

   } // End function testRequestsCarryNoExpectHeader

   /**
    * A rejected stream call must say why: the body is the only place search-api does.
    *
    * @group read-only
    */
   public function testStreamErrorCarriesResponseBodyAndNoApiKey() {

      // A streamed body is not seekable, so Guzzle leaves it out of its own message
      $response = new Response(400, [], new NoSeekStream(GuzzleHttp\Psr7\stream_for(self::CAP_BODY)));
      $logger = $this->captureLogs();

      try {
         $this->mockedObject([$response])->postStream('http://traackr.invalid/1.0/influencers/stream', ['influencers' => 'a,b']);
         $this->fail('Expected a TraackrApiException');
      } catch (Traackr\TraackrApiException $e) {
         $expected = 'API stream call failed (HTTP 400) POST http://traackr.invalid/1.0/influencers/stream?api_key=[REDACTED]: ' . self::CAP_BODY;
         $this->assertStreamExceptionShape($e);
         $this->assertSame($expected, $e->getMessage());
         $this->assertContains($expected, $logger->lines);
         $this->assertKeyNotIn($logger->lines);
      } finally {
         Traackr\TraackrApi::setLogger(new Traackr\DefaultApiLogger());
      }

   } // End function testStreamErrorCarriesResponseBodyAndNoApiKey

   /**
    * @group read-only
    */
   public function testStreamNetworkErrorCarriesNoApiKey() {

      $logger = $this->captureLogs();

      try {
         $this->mockedObject([$this->connectError()])->postStream('http://traackr.invalid/1.0/influencers/stream', ['influencers' => 'a']);
         $this->fail('Expected a TraackrApiException');
      } catch (Traackr\TraackrApiException $e) {
         $this->assertStreamExceptionShape($e);
         $this->assertTrue(strpos($e->getMessage(), 'api_key=[REDACTED]') !== false, $e->getMessage());
         $this->assertKeyNotIn([$e->getMessage()]);
         $this->assertKeyNotIn($logger->lines);
      } finally {
         Traackr\TraackrApi::setLogger(new Traackr\DefaultApiLogger());
      }

   } // End function testStreamNetworkErrorCarriesNoApiKey

   /**
    * A body can echo the key without the api_key= name in front of it.
    *
    * @group read-only
    */
   public function testStreamServerErrorBodyEchoingTheKeyIsRedacted() {

      $body = 'Invalid key ' . Traackr\TraackrApi::getApiKey() . ' (' . rawurlencode(Traackr\TraackrApi::getApiKey()) . ')';
      $response = new Response(500, [], new NoSeekStream(GuzzleHttp\Psr7\stream_for($body)));
      $logger = $this->captureLogs();

      try {
         $this->mockedObject([$response])->postStream('http://traackr.invalid/1.0/posts/stream', ['keywords' => 'k']);
         $this->fail('Expected a TraackrApiException');
      } catch (Traackr\TraackrApiException $e) {
         $this->assertStreamExceptionShape($e);
         $this->assertTrue(strpos($e->getMessage(), '(HTTP 500)') !== false, $e->getMessage());
         $this->assertTrue(strpos($e->getMessage(), 'Invalid key [REDACTED]') !== false, $e->getMessage());
         $this->assertKeyNotIn([$e->getMessage()]);
         $this->assertKeyNotIn($logger->lines);
      } finally {
         Traackr\TraackrApi::setLogger(new Traackr\DefaultApiLogger());
      }

   } // End function testStreamServerErrorBodyEchoingTheKeyIsRedacted

   /**
    * @group read-only
    */
   public function testRequestErrorsCarryNoApiKey() {

      $logger = $this->captureLogs();
      $object = $this->mockedObject([new Response(404, [], 'not found'), $this->connectError()]);

      try {
         try {
            $object->post('http://traackr.invalid/1.0/posts/search', ['keywords' => 'k']);
            $this->fail('Expected a NotFoundException');
         } catch (Traackr\NotFoundException $e) {
            $this->assertTrue(strpos($e->getMessage(), 'api_key=[REDACTED]') !== false, $e->getMessage());
            $this->assertKeyNotIn([$e->getMessage()]);
         }

         try {
            $object->post('http://traackr.invalid/1.0/posts/search', ['keywords' => 'k']);
            $this->fail('Expected a TraackrApiException');
         } catch (Traackr\TraackrApiException $e) {
            $this->assertKeyNotIn([$e->getMessage()]);
         }

         $this->assertNotEmpty($logger->lines);
         $this->assertKeyNotIn($logger->lines);
      } finally {
         Traackr\TraackrApi::setLogger(new Traackr\DefaultApiLogger());
      }

   } // End function testRequestErrorsCarryNoApiKey

   private function mockedObject(array $queue) {

      $object = new Traackr\Influencers();
      $property = new ReflectionProperty(Traackr\TraackrApiObject::class, 'client');
      $property->setAccessible(true);
      $config = $property->getValue($object)->getConfig();
      $property->setValue($object, new Client(['handler' => HandlerStack::create(new MockHandler($queue))] + $config));

      return $object;

   } // End function mockedObject

   private function captureLogs() {

      $logger = new CapturingApiLogger();
      Traackr\TraackrApi::setLogger($logger);

      return $logger;

   } // End function captureLogs

   // Same shape as the curl handler's message: it names the full URL, key included
   private function connectError() {

      $url = 'http://traackr.invalid/1.0/influencers/stream?api_key=' . Traackr\TraackrApi::getApiKey();

      return new ConnectException('cURL error 7: Failed to connect (see ' . $url . ')', new Request('POST', $url));

   } // End function connectError

   // bi maps getCode() to HTTP statuses, so the stream path keeps the base class and code 0
   private function assertStreamExceptionShape(Exception $e) {

      $this->assertSame('Traackr\TraackrApiException', get_class($e));
      $this->assertSame(0, $e->getCode());

   } // End function assertStreamExceptionShape

   private function assertKeyNotIn(array $texts) {

      $key = Traackr\TraackrApi::getApiKey();
      $this->assertNotEmpty($key);
      foreach ($texts as $text) {
         $this->assertFalse(strpos($text, $key), $text);
      }

   } // End function assertKeyNotIn

} // End class TraackrApiObjectTest
