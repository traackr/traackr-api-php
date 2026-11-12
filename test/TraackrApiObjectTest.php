<?php

use GuzzleHttp\Client;
use GuzzleHttp\Exception\ConnectException;
use GuzzleHttp\Exception\TransferException;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Middleware;
use GuzzleHttp\Psr7\FnStream;
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

   // URL-special characters, so the raw and URL-encoded forms of the key differ
   const KEY = '5ad+b/9=c';

   const STREAM_URL = 'http://traackr.invalid/1.0/influencers/stream';

   private $savedKey;
   private $savedLogger;
   private $logger;

   protected function setUp(): void {

      $this->savedKey = Traackr\TraackrApi::getApiKey();
      $this->savedLogger = Traackr\TraackrApi::getLogger();
      Traackr\TraackrApi::setApiKey(self::KEY);
      $this->logger = new CapturingApiLogger();
      Traackr\TraackrApi::setLogger($this->logger);

   } // End function setUp

   protected function tearDown(): void {

      Traackr\TraackrApi::setApiKey($this->savedKey);
      Traackr\TraackrApi::setLogger($this->savedLogger);

   } // End function tearDown

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

      $e = $this->streamError(400, self::CAP_BODY);

      $expected = 'API stream call failed (HTTP 400) POST ' . self::STREAM_URL . '?api_key=[REDACTED]: ' . self::CAP_BODY;
      $this->assertSame($expected, $e->getMessage());
      $this->assertContains($expected, $this->logger->lines);
      $this->assertKeyNotLogged($e);

   } // End function testStreamErrorCarriesResponseBodyAndNoApiKey

   /**
    * @group read-only
    */
   public function testStreamNetworkErrorCarriesNoApiKey() {

      $e = $this->streamError($this->connectError(self::STREAM_URL));

      $this->assertMessageContains('api_key=[REDACTED]', $e);
      $this->assertKeyNotLogged($e);

   } // End function testStreamNetworkErrorCarriesNoApiKey

   /**
    * A body can echo the key without the api_key= name in front of it.
    *
    * @group read-only
    */
   public function testStreamServerErrorBodyEchoingTheKeyIsRedacted() {

      $e = $this->streamError(500, 'Invalid key ' . self::KEY . ' (' . rawurlencode(self::KEY) . ')');

      $this->assertMessageContains('(HTTP 500)', $e);
      $this->assertMessageContains('Invalid key [REDACTED] ([REDACTED])', $e);
      $this->assertKeyNotLogged($e);

   } // End function testStreamServerErrorBodyEchoingTheKeyIsRedacted

   /**
    * @group read-only
    */
   public function testStreamBodyExcerptIsCappedAtTwoKilobytes() {

      $e = $this->streamError(500, str_repeat('x', 3000));

      $this->assertSame(': ' . str_repeat('x', 2048), substr($e->getMessage(), -2050));

   } // End function testStreamBodyExcerptIsCappedAtTwoKilobytes

   /**
    * Redacting after the cut would miss a key that crosses it and leave most of it in the message.
    *
    * @group read-only
    */
   public function testStreamKeyStraddlingTheCutIsRedacted() {

      $e = $this->streamError(500, str_repeat('z', 2044) . self::KEY);

      $this->assertFalse(strpos($e->getMessage(), substr(self::KEY, 0, 4)), $e->getMessage());
      $this->assertKeyNotLogged($e);

   } // End function testStreamKeyStraddlingTheCutIsRedacted

   /**
    * Redacting a long key and folding control characters both shrink the text before the cut, which
    * would otherwise pull a key fragment from the end of the read inside it.
    *
    * @group read-only
    */
   public function testShrinkingTheBodyDoesNotPullAKeyFragmentIntoTheExcerpt() {

      $key = 'abcdef0123456789abcdef0123456789';
      Traackr\TraackrApi::setApiKey($key);

      foreach ([$key . str_repeat('x', 2028) . $key, str_repeat("\n", 1000) . str_repeat('y', 1060) . $key] as $body) {
         $e = $this->streamError(500, $body);
         $this->assertFalse(strpos($e->getMessage(), 'abcdef0'), substr($e->getMessage(), -60));
      }

   } // End function testShrinkingTheBodyDoesNotPullAKeyFragmentIntoTheExcerpt

   /**
    * A body must not be able to forge log lines or break a JSON encoder downstream.
    *
    * @group read-only
    */
   public function testStreamBodyExcerptIsOneLineOfValidUtf8() {

      $forged = $this->streamError(400, "real\n2026-11-12 00:00:00 Error: forged\e[2J");
      $this->assertMessageContains('real 2026-11-12 00:00:00 Error: forged [2J', $forged);
      foreach (array_merge([$forged->getMessage()], $this->logger->lines) as $text) {
         $this->assertSame(0, preg_match('/[\x00-\x1F\x7F]/', $text), $text);
      }

      foreach ([str_repeat('x', 2047) . 'é', str_repeat("\xC3", 3000), "short \xE9 latin1"] as $body) {
         $message = $this->streamError(400, $body)->getMessage();
         $this->assertTrue(mb_check_encoding($message, 'UTF-8'), bin2hex(substr($message, -8)));
         $this->assertNotFalse(json_encode($message));
      }

   } // End function testStreamBodyExcerptIsOneLineOfValidUtf8

   /**
    * The body is read inside a catch block, so a read failure must not escape as a raw RuntimeException.
    *
    * @group read-only
    */
   public function testStreamUnreadableBodyKeepsTheExceptionShape() {

      $body = FnStream::decorate(GuzzleHttp\Psr7\stream_for(''), [
         'read' => function () {
            throw new \RuntimeException('boom');
         },
         'isSeekable' => function () {
            return false;
         },
      ]);

      $e = $this->streamError(new Response(400, [], $body));

      $this->assertMessageContains('(HTTP 400)', $e);
      $this->assertMessageContains('(response body unreadable: boom)', $e);

   } // End function testStreamUnreadableBodyKeepsTheExceptionShape

   /**
    * @group read-only
    */
   public function testStreamOtherTransferErrorIsRedacted() {

      $e = $this->streamError(new TransferException('transfer failed for ' . self::STREAM_URL . '?api_key=' . self::KEY));

      $this->assertMessageContains('api_key=[REDACTED]', $e);
      $this->assertKeyNotLogged($e);

   } // End function testStreamOtherTransferErrorIsRedacted

   /**
    * @group read-only
    */
   public function testRequestErrorsCarryNoApiKey() {

      $object = $this->mockedObject([
         new Response(404, [], 'not found'),
         $this->connectError('http://traackr.invalid/1.0/posts/search'),
         new Response(403, [], 'Invalid key ' . self::KEY),
         new Response(500, [], 'echo ' . rawurlencode(self::KEY)),
      ]);

      $expectations = [
         ['Traackr\NotFoundException', 'api_key=[REDACTED]'],
         ['Traackr\TraackrApiException', 'api_key=[REDACTED]'],
         ['Traackr\InvalidApiKeyException', 'Invalid key [REDACTED]'],
         ['Traackr\TraackrApiException', 'echo [REDACTED]'],
      ];
      foreach ($expectations as list($class, $fragment)) {
         try {
            $object->post('http://traackr.invalid/1.0/posts/search', ['keywords' => 'k']);
            $this->fail('Expected ' . $class);
         } catch (Traackr\TraackrApiException $e) {
            $this->assertSame($class, get_class($e));
            $this->assertMessageContains($fragment, $e);
            $this->assertKeyNotLogged($e);
         }
      }

   } // End function testRequestErrorsCarryNoApiKey

   /**
    * A key passed in the call's own parameters is not the global one, so only the api_key= pattern catches it.
    *
    * @group read-only
    */
   public function testPerCallApiKeyIsRedacted() {

      try {
         $this->mockedObject([new Response(404, [], 'not found')])
            ->get('http://traackr.invalid/1.0/posts/show', ['api_key' => 'PERCALLKEY42']);
         $this->fail('Expected a NotFoundException');
      } catch (Traackr\NotFoundException $e) {
         $this->assertNotEmpty($this->logger->lines);
         foreach (array_merge([$e->getMessage()], $this->logger->lines) as $text) {
            $this->assertFalse(strpos($text, 'PERCALLKEY42'), $text);
         }
      }

   } // End function testPerCallApiKeyIsRedacted

   /**
    * @group read-only
    */
   public function testKeyWithAmpersandOrSpaceIsRedactedWhole() {

      Traackr\TraackrApi::setApiKey('ab&cd ef');
      $redact = new ReflectionMethod(Traackr\TraackrApiObject::class, 'redactApiKey');
      $redact->setAccessible(true);

      $this->assertSame('http://x.invalid/s?api_key=[REDACTED]&n=1', $redact->invoke(null, 'http://x.invalid/s?api_key=ab&cd ef&n=1'));

   } // End function testKeyWithAmpersandOrSpaceIsRedactedWhole

   /**
    * The key sits in the URL already, so the parameters must follow with '&': after a second '?' the
    * redaction would swallow the first one.
    *
    * @group read-only
    */
   public function testStreamDebugLineKeepsTheFirstParameter() {

      $this->streamError(400, self::CAP_BODY);

      $this->assertContains('Calling (STREAM): ' . self::STREAM_URL . '?api_key=[REDACTED]&influencers=a%2Cb', $this->logger->lines);

   } // End function testStreamDebugLineKeepsTheFirstParameter

   // Runs postStream against one queued response or exception and returns the TraackrApiException it throws
   private function streamError($responseOrStatus, $body = '') {

      $queued = is_int($responseOrStatus)
         ? new Response($responseOrStatus, [], new NoSeekStream(GuzzleHttp\Psr7\stream_for($body)))
         : $responseOrStatus;

      try {
         $this->mockedObject([$queued])->postStream(self::STREAM_URL, ['influencers' => 'a,b']);
      } catch (Traackr\TraackrApiException $e) {
         // bi maps getCode() to HTTP statuses, so the stream path keeps the base class and code 0
         $this->assertSame('Traackr\TraackrApiException', get_class($e));
         $this->assertSame(0, $e->getCode());
         return $e;
      }
      $this->fail('Expected a TraackrApiException');

   } // End function streamError

   private function mockedObject(array $queue) {

      $object = new Traackr\Influencers();
      $property = new ReflectionProperty(Traackr\TraackrApiObject::class, 'client');
      $property->setAccessible(true);
      $config = $property->getValue($object)->getConfig();
      $property->setValue($object, new Client(['handler' => HandlerStack::create(new MockHandler($queue))] + $config));

      return $object;

   } // End function mockedObject

   // Same shape as the curl handler's message: it names the full URL, key included
   private function connectError($url) {

      $url .= '?api_key=' . self::KEY;

      return new ConnectException('cURL error 7: Failed to connect (see ' . $url . ')', new Request('POST', $url));

   } // End function connectError

   private function assertMessageContains($fragment, Exception $e) {

      $this->assertTrue(strpos($e->getMessage(), $fragment) !== false, $e->getMessage());

   } // End function assertMessageContains

   private function assertKeyNotLogged(Exception $e) {

      foreach (array_merge([$e->getMessage()], $this->logger->lines) as $text) {
         $this->assertFalse(strpos($text, self::KEY), $text);
         $this->assertFalse(strpos($text, rawurlencode(self::KEY)), $text);
      }

   } // End function assertKeyNotLogged

} // End class TraackrApiObjectTest
