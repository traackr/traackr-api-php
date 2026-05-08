<?php

class TraackrApiTest extends PHPUnit\Framework\TestCase {

   private $singleHeader = "X-TraackrTest: xxxxx";

   private $arrayHeader = array(
   "X-TraackrTest1: xxxxx",
   "X-TraackrTest2: yyyyy");


   /**
    * @group read-only
    */
   public function testSetExtraHeaders() {

      $this->assertFalse(Traackr\TraackrApi::setExtraHeaders(1));
      $this->assertTrue(Traackr\TraackrApi::setExtraHeaders($this->singleHeader));
      $this->assertTrue(Traackr\TraackrApi::setExtraHeaders($this->arrayHeader));

   } // End function testSetExtraHeaders

   /**
    * @group read-only
    */
   public function testGetExtraHeaders() {

      Traackr\TraackrApi::setExtraHeaders($this->singleHeader);
      $h = Traackr\TraackrApi::getExtraHeaders();
      $this->assertIsArray($h);
      $this->assertEquals(1, sizeof($h));
      $this->assertContains($this->singleHeader, $h);

      Traackr\TraackrApi::setExtraHeaders($this->arrayHeader);
      $h = Traackr\TraackrApi::getExtraHeaders();
      $this->assertIsArray($h);
      $this->assertEquals(2, sizeof($h));
      $this->assertContains($this->arrayHeader[0], $h);
      $this->assertContains($this->arrayHeader[1], $h);

   } // End function testGetExtraHeaders

}
