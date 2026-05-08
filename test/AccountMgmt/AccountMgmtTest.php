<?php

class AccountMgmtTest extends PHPUnit\Framework\TestCase {

   private $infUid = '1395be8293373465ab172b8b1b677e31';
   private $infTag = 'traackr-api-test';
   private $infName = 'David Chancogne';
   private $createdCustomerKey;

   private $savedCustomerKey;


   public function setUp(): void {

      $this->savedCustomerKey = Traackr\TraackrApi::getCustomerKey();

      // Ensure outout is PHP by default
      Traackr\TraackrApi::setJsonOutput(false);

   } // End function setUp()

   public function tearDown(): void {

      Traackr\TraackrApi::setCustomerKey($this->savedCustomerKey);

   } // End functiuon tearDown()


   public function testCustomerkeyCreate() {

      try {
         Traackr\AccountMgmt::customerkeyCreate(array('customer_name' => 'traackr-api-test'));
      }
      catch (Traackr\TraackrApiException $e) {
         $this->assertEquals($e->getMessage(), 'Missing or Invalid argument/parameter (HTTP 400): Customer key exists for given api_key/customer_name');
         $this->assertEquals($e->getCode(), 400);
      }

   } // End function testCreateCustomerKey()

   public function testTagList() {

      $tags = Traackr\AccountMgmt::tagList();

      // $tags_list = array_reduce($tags, function(&$results, $item) { $results[] = $item['tag']; }, array());
      foreach ($tags['account']['tags'] as $tag) {
         if ( $tag['tag'] == $this->infTag ) {
            $this->assertSame(0, $tag['ref_count']);
         }
      }

      Traackr\Influencers::tagAdd(array(
         'influencers' => $this->infUid,
         'tags' => $this->infTag));
      $tags = Traackr\AccountMgmt::tagList();
      foreach ($tags['account']['tags'] as $tag) {
         if ( $tag['tag'] == $this->infTag ) {
            $this->assertSame(1, $tag['ref_count']);
         }
      }
      $tags = Traackr\AccountMgmt::tagList(array('tag_prefix_filter' => 'traackr-api'));
      foreach ($tags['account']['tags'] as $tag) {
         if ( $tag['tag'] == $this->infTag ) {
            $this->assertSame(1, $tag['ref_count']);
         }
      }

      Traackr\Influencers::tagRemove(array(
         'influencers' => $this->infUid,
         'tags' => $this->infTag));

   } // End function testTagList()

   public function testSTagListInvalidCustomerKey() {

      $this->expectException(\Traackr\InvalidCustomerKeyException::class);
      Traackr\TraackrApi::setCustomerKey('xxxRandomInvalidCustomerKeyxxxx');
      Traackr\AccountMgmt::tagList();

   } // End function testShowNotFound()

   /**
    * Tests create, edit, and view customer key
    */
   public function testCreateEditViewKey() {
      // Create Customer Key
      $create_response = Traackr\AccountMgmt::customerkeyCreate(array('customer_name' => 'another_unknown_customer_for_a_customer_key'));
      $this->assertNotEmpty($create_response);
      $this->assertNotEmpty($create_response['customer_key']);
      $customer_key = $create_response['customer_key'];

      // Edit Customer Key
      $edit_response = Traackr\AccountMgmt::customerkeyEdit(array('customer_key' => $customer_key, 'iso_location' => '[{"type":"INCLUSION","fields":{"COUNTRY":"US"}},{"type":"EXCLUSION","fields":{"STATE":"US-MA"}}]'));
      $this->assertNotEmpty($edit_response);
      $this->assertNotEmpty($edit_response['status']);
      $status = $edit_response['status'];
      $this->assertEquals($status, 'ok');

      // View Customer Key
      $view_response = Traackr\AccountMgmt::customerkeyView(array('customer_key' => $customer_key));
      $this->assertNotEmpty($view_response);
      $this->assertNotEmpty($view_response['customer_key']);
      $this->assertNotEmpty($view_response['customer_name']);
      $this->assertNotEmpty($view_response['iso_location']);
      $customer_key2 = $view_response['customer_key'];
      $customer_name = $view_response['customer_name'];
      $iso_location = $view_response['iso_location'];
      $this->assertEquals($customer_key2, $customer_key);
      $this->assertEquals($customer_name, 'another_unknown_customer_for_a_customer_key');
      $this->assertEquals(json_encode($iso_location), '[{"type":"INCLUSION","fields":{"COUNTRY":"US"}},{"type":"EXCLUSION","fields":{"STATE":"US-MA"}}]');

      try {
         Traackr\AccountMgmt::customerkeyDelete(array('customer_key' => $customer_key));
      }
      catch (Traackr\TraackrApiException $e) {
         $this->assertEquals($e->getMessage(), 'Invalid Customer Key (HTTP 400): Customer key not found');
         $this->assertEquals($e->getCode(), 400);
      }
   } // End function testCreateEditViewKey()

   /**
    * Tests create and delete customer key
    */
   public function testCreateAndDeleteKey() {
      // Create Customer Key
      $create_response = Traackr\AccountMgmt::customerkeyCreate(array('customer_name' => 'some_unknown_customer_for_a_customer_key'));
      $this->assertNotEmpty($create_response);
      $this->assertNotEmpty($create_response['customer_key']);
      $customer_key = $create_response['customer_key'];

      // Delete Customer Key
      $delete_response = Traackr\AccountMgmt::customerkeyDelete(array('customer_key' => $customer_key));
      $this->assertEmpty($delete_response); // delete now returns empty-string (and no exception) upon success

      try {
         Traackr\AccountMgmt::customerkeyDelete(array('customer_key' => $customer_key));
      }
      catch (Traackr\TraackrApiException $e) {
         $this->assertEquals($e->getMessage(), 'Invalid Customer Key (HTTP 400): Customer key not found');
         $this->assertEquals($e->getCode(), 400);
      }
   } // End function testCreateAndDeleteKey()

} // End class AccountMgmt
