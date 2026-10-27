<?php

use GuzzleHttp\Client;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Middleware;
use GuzzleHttp\Psr7\Response;

class TraackrApiObjectTest extends PHPUnit\Framework\TestCase {

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

} // End class TraackrApiObjectTest
