<?php

class AnalysisTest extends PHPUnit\Framework\TestCase {

   private $savedCustomerKey;


   public function setUp(): void {
      $this->savedCustomerKey = Traackr\TraackrApi::getCustomerKey();
      // Ensure outout is PHP by default
      Traackr\TraackrApi::setJsonOutput(false);
   } // End function setUp()

   public function tearDown(): void {
      Traackr\TraackrApi::setCustomerKey($this->savedCustomerKey);
   } // End functiuon tearDown()

    /**
    * @group read-only
    */
    public function testKeywords() {
        // Skip test when running against public API (endpoint not available)
        if (strpos(Traackr\TraackrApi::$apiBaseUrl, 'api.traackr.com') !== false) {
            $this->markTestSkipped('analysis/keywords endpoint not available on public API');
        }

        $json = array('keywords' => array(
            array('label' => 'default',
            'context' => 'POST',
            'query_string' => 'hello world')));
        $result = Traackr\Analysis::keywords($json);
        $this->assertTrue($result['keywords']['default']['is_valid']);

        $json = array('keywords' => array(
            array('label' => 'default',
            'context' => 'POST',
            'query_string' => 'a')));
        $result = Traackr\Analysis::keywords($json);
        $this->assertFalse($result['keywords']['default']['is_valid']);
    }
} // End class AnalysisTest
