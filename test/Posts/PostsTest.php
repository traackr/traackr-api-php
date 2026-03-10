<?php

class PostsTest extends PHPUnit_Framework_TestCase {

   private $infUid = '1395be8293373465ab172b8b1b677e31';

   private $savedCustomerKey;

   public function setUp() {

      $this->savedCustomerKey = Traackr\TraackrApi::getCustomerKey();

      // Ensure outout is PHP by default
      Traackr\TraackrApi::setJsonOutput(false);

   } // End function setUp()

   public function tearDown() {

      Traackr\TraackrApi::setCustomerKey($this->savedCustomerKey);

   } // End functiuon tearDown()


   /**
    * @group read-only
    */
   public function testLookup() {

      $posts = Traackr\Posts::lookup(array('influencers' => $this->infUid));
      $this->assertArrayHasKey('page_info', $posts, 'No paging info');
      $this->assertGreaterThan(0, $posts['posts'], 'No results found');
      $this->assertEquals($this->infUid, $posts['posts'][0]['influencer_uid'], 'Invalid influencer author found');

      $posts = Traackr\Posts::lookup(array('influencers' => '000000'));
      $this->assertCount(0, $posts['posts'], 'Results found');

      // With use_primary_location
      $posts = Traackr\Posts::lookup(array('influencers' => $this->infUid, 'use_primary_location' => true));
      $this->assertArrayHasKey('page_info', $posts, 'No paging info');
      $this->assertArrayHasKey('posts', $posts, 'No posts key');

   } // End function testLookup()

   /**
    * Having a false keyword match param, should still return
    * valid results, even with keyword aggregation turned on
    * @group read-only
    */
   public function testSearchWithKeywordAggregations() {
      $posts = Traackr\Posts::search(array(
         'keywords' => array('traackr', '"content marketing"'),
         'include_keyword_matches' => false,
         'aggregations' => json_encode(['agg_keyword' => true])
      ));
      $this->assertGreaterThan(0, $posts['posts'], 'No results found');
   }

   /**
    * @group read-only
    */
   public function testSearch() {

      $posts = Traackr\Posts::search(array('keywords' => array('traackr', '"content marketing"')));
      $this->assertArrayHasKey('posts', $posts, 'No posts found');

      // With use_primary_location
      $posts = Traackr\Posts::search(array('keywords' => 'traackr', 'use_primary_location' => true));
      $this->assertArrayHasKey('posts', $posts, 'No posts found');

   } // End function testSearch()

   /**
    * Test the stream method
    * @group read-only
    */
    public function testStream() {
      $params = array(
         'keywords' => array('traackr', 'marketing'),
         'sort' => 'date',
         'count' => 10
      );

      $result = Traackr\Posts::stream($params);

      $this->assertInternalType('array', $result, 'The return method should be an array');
      $this->assertArrayHasKey('page_info', $result, 'page_info is missing');
      $this->assertArrayHasKey('posts', $result, 'The "posts" key is missing');

      $postsGenerator = $result['posts'];
      $this->assertTrue(
          is_array($postsGenerator) || $postsGenerator instanceof Traversable, 
          'The value of the "posts" key should be iterable (Generator)'
      );

      $found = false;
      foreach ($postsGenerator as $post) {
         $found = true;
         $this->assertArrayHasKey('influencer_uid', $post, 'The post does not have influencer_uid');
         $this->assertArrayHasKey('url', $post, 'The post does not have url');
         
         break; 
      }
      
   }

   /**
    * Test stream with influencers
    * @group read-only
    */
   public function testStreamWithInfluencers() {
      $params = array(
         'influencers' => array($this->infUid),
         'count' => 5
      );

      $result = Traackr\Posts::stream($params);

      $this->assertArrayHasKey('posts', $result);

      // With use_primary_location
      $params['use_primary_location'] = true;
      $result = Traackr\Posts::stream($params);

      $this->assertArrayHasKey('posts', $result);
      
      foreach ($result['posts'] as $post) {
         $this->assertEquals($this->infUid, $post['influencer_uid'], 'The post UID does not match');
         break;
      }
   }

   public function testStreamWithInvalidCustomerKey() {
      Traackr\TraackrApi::setCustomerKey('invalid');

      $this->expectException(Traackr\TraackrApiException::class);
      $this->expectExceptionMessage('Invalid customer key');

      Traackr\Posts::stream(array('count' => 5));

      Traackr\TraackrApi::setCustomerKey($this->savedCustomerKey);
   }
} // End class PostsTest
