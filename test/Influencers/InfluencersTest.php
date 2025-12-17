<?php

class InfluencersTest extends PHPUnit_Framework_TestCase
{
    private $infUid = '1395be8293373465ab172b8b1b677e31';
    private $infTag = 'traackr-api-test';
    private $infTag2 = 'inf-tag-test';
    private $infTagUTF8 = 'påverkare marknadsföring traackr-api-test';
    private $infName = 'David Chancogne';
    private $channel = 'http://traackr.com/blog';
    private $infTwitterHandle = 'dchancogne';
    private $infTwitterId = '7772342';
    private $infTwitterUrl = 'http://twitter.com/dchancogne';

    private $infUid2 = 'ae1955b0f92037c895e5bfdd259a1304';

    private $savedCustomerKey;


    public function setUp()
    {
        $this->savedCustomerKey = Traackr\TraackrApi::getCustomerKey();

        // Try to remove all existing tags
        // when run with @read-ony group this APi call might not be allowed
        // so catch exception and ignore
        try {
            // remove all tags
            Traackr\Influencers::tagRemove(
                array(
                    'tags' => array($this->infTag, $this->infTag2, $this->infTagUTF8),
                    'all' => true
                )
            );
        } catch (Traackr\TraackrApiException $e) {
            // Ignore
        }

        // Ensure output is PHP by default
        Traackr\TraackrApi::setJsonOutput(false);
    }

    public function tearDown()
    {
        Traackr\TraackrApi::setCustomerKey($this->savedCustomerKey);
    }

    public function testShowWithTags()
    {
        $inf = Traackr\Influencers::show($this->infUid);
        // Check result is there
        $this->assertArrayHasKey('influencer', $inf, 'No influencer found');
        $this->assertArrayHasKey($this->infUid, $inf['influencer'], 'Invalid influencer found');
        // Check appropriate fields are present
        $this->assertArrayHasKey('uid', $inf['influencer'][$this->infUid], 'UID filed is missing');
        $this->assertArrayHasKey('name', $inf['influencer'][$this->infUid], 'Name field missing');
        $this->assertArrayHasKey('description', $inf['influencer'][$this->infUid], 'Description field missing');
        $this->assertArrayHasKey('title', $inf['influencer'][$this->infUid], 'Title field missing');
        $this->assertArrayHasKey('location', $inf['influencer'][$this->infUid], 'Location field missing');
        $this->assertArrayHasKey('avatar', $inf['influencer'][$this->infUid], 'Avatar field missing');
        $this->assertArrayHasKey('reach', $inf['influencer'][$this->infUid], 'Reach field missing');
        $this->assertArrayHasKey('resonance', $inf['influencer'][$this->infUid], 'Resonance field missing');
        $this->assertArrayNotHasKey('channels', $inf['influencer'][$this->infUid], 'Channels field should not have be returned');
        $this->assertArrayHasKey('tags', $inf['influencer'][$this->infUid], 'Tags field should not have be returned');
        // Check some values
        $this->assertEquals($this->infUid, $inf['influencer'][$this->infUid]['uid'], 'Incorrect UID');
        $this->assertEquals($this->infName, $inf['influencer'][$this->infUid]['name'], 'Incorrect name');
    }

    /**
     * @group read-only
     */
    public function testShow()
    {
        // Unsetting customer key so 'tags' are not returned
        Traackr\TraackrApi::setCustomerKey('');
        $inf = Traackr\Influencers::show($this->infUid);
        // Check result is there
        $this->assertArrayHasKey('influencer', $inf, 'No influencer found');
        $this->assertArrayHasKey($this->infUid, $inf['influencer'], 'Invalid influencer found');
        // Check appropriate fields are present
        $this->assertArrayHasKey('uid', $inf['influencer'][$this->infUid], 'UID filed is missing');
        $this->assertArrayHasKey('name', $inf['influencer'][$this->infUid], 'Name field missing');
        $this->assertArrayHasKey('description', $inf['influencer'][$this->infUid], 'Description field missing');
        $this->assertArrayHasKey('title', $inf['influencer'][$this->infUid], 'Title field missing');
        $this->assertArrayHasKey('location', $inf['influencer'][$this->infUid], 'Location field missing');
        $this->assertArrayHasKey('avatar', $inf['influencer'][$this->infUid], 'Avatar field missing');
        $this->assertArrayHasKey('reach', $inf['influencer'][$this->infUid], 'Reach field missing');
        $this->assertArrayHasKey('resonance', $inf['influencer'][$this->infUid], 'Resonance field missing');
        $this->assertArrayNotHasKey('tags', $inf['influencer'][$this->infUid], 'Tags field should not have been returned');
        // Check some values
        $this->assertEquals($this->infUid, $inf['influencer'][$this->infUid]['uid'], 'Incorrect UID');
        $this->assertEquals($this->infName, $inf['influencer'][$this->infUid]['name'], 'Incorrect name');

        // show influencer w/ channels
        $inf = Traackr\Influencers::show($this->infUid, array('with_channels' => true));
        $this->assertArrayHasKey('channels', $inf['influencer'][$this->infUid], 'Channels not returned');
        $twitter = array_values(
            array_filter($inf['influencer'][$this->infUid]['channels'], function ($elm) {
                return $elm['root_domain'] == 'twitter';
            })
        );
        $this->assertEquals('http://twitter.com/dchancogne', $twitter[0]['url']);

        $infs = Traackr\Influencers::show(array($this->infUid, $this->infUid2));
        $this->assertCount(2, $infs['influencer'], 'Incorrect number of influencers returned');
        $infs = Traackr\Influencers::show($this->infUid . ',' . $this->infUid2);
        $this->assertCount(2, $infs['influencer'], 'Incorrect number of influencers returned');
    }

    /**
     * @group read-only
     * @expectedException Traackr\NotFoundException
     */
    public function testShowNotFound()
    {
        Traackr\Influencers::show('00000');
    }

    /**
     * @group read-only
     * @expectedException Traackr\MissingParameterException
     */
    public function testShowMissingParameter()
    {
        Traackr\Influencers::show('');
    }

    /**
     * @group read-only
     */
    public function testLookupSocialTwitter()
    {
        $twitterHandle = 'dchancogne';

        $inf = Traackr\Influencers::lookupSocial($twitterHandle);
        // Check result is there
        $this->assertArrayHasKey('influencer', $inf, 'No influencer found');
        $this->assertArrayHasKey($twitterHandle, $inf['influencer'], 'Invalid influencer found');
        // Check appropriate fields are present
        $this->assertArrayHasKey('uid', $inf['influencer'][$twitterHandle], 'UID filed is missing');
        $this->assertArrayHasKey('name', $inf['influencer'][$twitterHandle], 'Name field missing');
        $this->assertArrayHasKey('description', $inf['influencer'][$twitterHandle], 'Description field missing');
        $this->assertArrayHasKey('title', $inf['influencer'][$twitterHandle], 'Title field missing');
        $this->assertArrayHasKey('location', $inf['influencer'][$twitterHandle], 'Location field missing');
        $this->assertArrayHasKey('avatar', $inf['influencer'][$twitterHandle], 'Avatar field missing');
        $this->assertArrayHasKey('reach', $inf['influencer'][$twitterHandle], 'Reach field missing');
        $this->assertArrayHasKey('resonance', $inf['influencer'][$twitterHandle], 'Resonance field missing');
        $this->assertArrayNotHasKey('channels', $inf['influencer'][$twitterHandle], 'Channels should not have be returned');
        $this->assertArrayNotHasKey('tags', $inf['influencer'][$twitterHandle], 'Tags field missing');
        // Check some values
        $this->assertEquals($this->infUid, $inf['influencer'][$twitterHandle]['uid'], 'Incorrect UID');
        $this->assertEquals($this->infName, $inf['influencer'][$twitterHandle]['name'], 'Incorrect name');

        // NOTE
        // Disable customer key so that 'show' does not return tags b/c lookupSocial doesn't currently
        Traackr\TraackrApi::setCustomerKey('');
        $inf = Traackr\Influencers::show($this->infUid);
        $twitter = Traackr\Influencers::lookupSocial('dchancogne');
        $this->assertJsonStringEqualsJsonString(
            json_encode($inf['influencer'][$this->infUid]),
            json_encode($twitter['influencer']['dchancogne'])
        );

        // Test based on Twitter ID type parameter
        $twitter = Traackr\Influencers::lookupSocial('7772342', 'TWITTER', 'USER_ID');
        $this->assertJsonStringEqualsJsonString(
            json_encode($inf['influencer'][$this->infUid]),
            json_encode($twitter['influencer']['7772342'])
        );
    }

    /**
     * @group read-only
     */
    public function testLookupSocialInstagram()
    {
        $instaHandle = 'dchancogne';

        $inf = Traackr\Influencers::lookupSocial($instaHandle, 'INSTAGRAM');
        // Check result is there
        $this->assertArrayHasKey('influencer', $inf, 'No influencer found');
        $this->assertArrayHasKey($instaHandle, $inf['influencer'], 'Invalid influencer found');
        // Check appropriate fields are present
        $this->assertArrayHasKey('uid', $inf['influencer'][$instaHandle], 'UID filed is missing');
        $this->assertArrayHasKey('name', $inf['influencer'][$instaHandle], 'Name field missing');
        $this->assertArrayHasKey('description', $inf['influencer'][$instaHandle], 'Description field missing');
        $this->assertArrayHasKey('title', $inf['influencer'][$instaHandle], 'Title field missing');
        $this->assertArrayHasKey('location', $inf['influencer'][$instaHandle], 'Location field missing');
        $this->assertArrayHasKey('avatar', $inf['influencer'][$instaHandle], 'Avatar field missing');
        $this->assertArrayHasKey('reach', $inf['influencer'][$instaHandle], 'Reach field missing');
        $this->assertArrayHasKey('resonance', $inf['influencer'][$instaHandle], 'Resonance field missing');
        $this->assertArrayNotHasKey('channels', $inf['influencer'][$instaHandle], 'Channels should not have be returned');
        $this->assertArrayNotHasKey('tags', $inf['influencer'][$instaHandle], 'Tags field missing');
        // Check some values
        $this->assertEquals($this->infUid, $inf['influencer'][$instaHandle]['uid'], 'Incorrect UID');
        $this->assertEquals($this->infName, $inf['influencer'][$instaHandle]['name'], 'Incorrect name');

        // NOTE
        // Disable customer key so that 'show' does not return tags b/c lookupSocial doesn't currently
        Traackr\TraackrApi::setCustomerKey('');
        $inf = Traackr\Influencers::show($this->infUid);
        $insta = Traackr\Influencers::lookupSocial('dchancogne', 'INSTAGRAM');
        $this->assertJsonStringEqualsJsonString(
            json_encode($inf['influencer'][$this->infUid]),
            json_encode($insta['influencer']['dchancogne'])
        );

        // Test based on Insta ID type parameter
        $insta = Traackr\Influencers::lookupSocial('400455', 'INSTAGRAM', 'USER_ID');
        $this->assertJsonStringEqualsJsonString(
            json_encode($inf['influencer'][$this->infUid]),
            json_encode($insta['influencer']['400455'])
        );
    }

    /**
     * @group read-only
     */
    public function testLookupSocialTiktok()
    {
        $handle = 'masonfulp';
        $uid = 'fab5dd3e749d46099a54461406dad7a1';

        $inf = Traackr\Influencers::lookupSocial($handle, 'TIKTOK');
        // Check result is there
        $this->assertArrayHasKey('influencer', $inf, 'No influencer found');
        $this->assertArrayHasKey($handle, $inf['influencer'], 'Invalid influencer found');
        // Check appropriate fields are present
        $this->assertArrayHasKey('uid', $inf['influencer'][$handle], 'UID filed is missing');
        $this->assertArrayHasKey('name', $inf['influencer'][$handle], 'Name field missing');
        $this->assertArrayHasKey('description', $inf['influencer'][$handle], 'Description field missing');
        $this->assertArrayHasKey('title', $inf['influencer'][$handle], 'Title field missing');
        $this->assertArrayHasKey('location', $inf['influencer'][$handle], 'Location field missing');
        $this->assertArrayHasKey('avatar', $inf['influencer'][$handle], 'Avatar field missing');
        $this->assertArrayHasKey('reach', $inf['influencer'][$handle], 'Reach field missing');
        $this->assertArrayHasKey('resonance', $inf['influencer'][$handle], 'Resonance field missing');
        $this->assertArrayNotHasKey('channels', $inf['influencer'][$handle], 'Channels should not have be returned');
        $this->assertArrayNotHasKey('tags', $inf['influencer'][$handle], 'Tags field missing');
        // Check some values
        $this->assertEquals($uid, $inf['influencer'][$handle]['uid'], 'Incorrect UID');
        $this->assertEquals('Mason Fulp', $inf['influencer'][$handle]['name'], 'Incorrect name');

        // NOTE
        // Disable customer key so that 'show' does not return tags b/c lookupSocial doesn't currently
        Traackr\TraackrApi::setCustomerKey('');
        $inf = Traackr\Influencers::show($uid);
        $tiktok = Traackr\Influencers::lookupSocial($handle, 'TIKTOK');
        $this->assertJsonStringEqualsJsonString(
            json_encode($inf['influencer'][$uid]),
            json_encode($tiktok['influencer'][$handle])
        );
    }

    /**
     * @group read-only
     * @expectedException Traackr\MissingParameterException
     */
    public function testLookupSocialInvalidPlatformParameter()
    {
        Traackr\Influencers::lookupSocial('dchancogne', '');
    }

    /**
     * @group read-only
     * @expectedException \UnexpectedValueException
     */
    public function testLookupSocialInvalidTypeParameter()
    {
        Traackr\Influencers::lookupSocial('dchancogne', 'TWITTER', 'INVALID');
    }

    /**
     * @group read-only
     * @expectedException Traackr\NotFoundException
     */
    public function testLookupSocialNotFound()
    {
        Traackr\Influencers::lookupSocial('000RandomHandle000');
    }

    public function testAddSocialByUsername()
    {
        $result = Traackr\Influencers::addSocial([
            'username' => $this->infTwitterHandle,
            'platform' => 'TWITTER'
        ]);

        $this->assertNotEmpty($result['influencer'][$this->infTwitterHandle]);
        $this->assertEquals($result['influencer'][$this->infTwitterHandle]['uid'], $this->infUid);
    }

    public function testAddSocialByUserId()
    {
        $result = Traackr\Influencers::addSocial([
            'user_id' => $this->infTwitterId,
            'platform' => 'TWITTER'
        ]);

        $this->assertNotEmpty($result['influencer'][$this->infTwitterId]);
        $this->assertEquals($result['influencer'][$this->infTwitterId]['uid'], $this->infUid);
    }

    public function testAddSocialByURL()
    {
        $result = Traackr\Influencers::addSocial([
            'url' => $this->infTwitterUrl,
        ]);

        $this->assertNotEmpty($result['influencer'][$this->infTwitterId]);
        $this->assertEquals($result['influencer'][$this->infTwitterId]['uid'], $this->infUid);
    }

    /**
     * @expectedException Traackr\MissingParameterException
     */
    public function testAddSocialByUsernameAndUserId()
    {
        // this will fail and throw an expected exception
        Traackr\Influencers::addSocial([
            'username' => $this->infTwitterHandle,
            'user_id' => $this->infTwitterId,
            'platform' => 'TWITTER'
        ]);
    }

    /**
     * @expectedException Traackr\MissingParameterException
     */
    public function testAddSocialMissingUserParam()
    {
        // this will fail and throw an expected exception
        Traackr\Influencers::addSocial([
            'platform' => 'TWITTER'
        ]);
    }

    /**
     * @expectedException Traackr\MissingParameterException
     */
    public function testAddSocialMissingPlatformParam()
    {
        // this will fail and throw an expected exception
        Traackr\Influencers::addSocial([
            'username' => $this->infTwitterHandle
        ]);
    }

    /**
     * @expectedException Traackr\UnexpectedValueException
     */
    public function testAddSocialInvalidPlatformParam()
    {
        // this will fail and throw an expected exception
        Traackr\Influencers::addSocial([
            'username' => $this->infTwitterHandle,
            'platform' => 'INVALID'
        ]);
    }

    public function testTagAdd()
    {
        $inf = Traackr\Influencers::show($this->infUid);
        $this->assertCount(0, $inf['influencer'][$this->infUid]['tags']);

        // Test regular ASCII tag
        Traackr\Influencers::tagAdd(array(
            'influencers' => $this->infUid,
            'tags' => $this->infTag));
        $inf = Traackr\Influencers::show($this->infUid);
        $this->assertCount(1, $inf['influencer'][$this->infUid]['tags']);
        $this->assertTrue(in_array($this->infTag, $inf['influencer'][$this->infUid]['tags']));

        Traackr\Influencers::tagRemove(array(
            'influencers' => $this->infUid,
            'tags' => $this->infTag));

        // Test UTF-8 tag
        Traackr\Influencers::tagAdd(array(
            'influencers' => $this->infUid,
            'tags' => $this->infTagUTF8));
        $inf = Traackr\Influencers::show($this->infUid);
        $this->assertCount(1, $inf['influencer'][$this->infUid]['tags'], 'UTF-8 tag not found');
        $this->assertTrue(in_array($this->infTagUTF8, $inf['influencer'][$this->infUid]['tags']), 'UTF-8 tag not found');

        Traackr\Influencers::tagRemove(array(
            'influencers' => $this->infUid,
            'tags' => $this->infTagUTF8));

        $inf = Traackr\Influencers::show($this->infUid);
        $this->assertCount(0, $inf['influencer'][$this->infUid]['tags']);
        $inf2 = Traackr\Influencers::show($this->infUid2);
        $this->assertCount(0, $inf2['influencer'][$this->infUid2]['tags']);

        Traackr\Influencers::tagAdd(array(
            'influencers' => array($this->infUid, $this->infUid2),
            'tags' => $this->infTag));
        $inf = Traackr\Influencers::show($this->infUid);
        $this->assertCount(1, $inf['influencer'][$this->infUid]['tags']);
        $this->assertTrue(in_array($this->infTag, $inf['influencer'][$this->infUid]['tags']));
        $inf2 = Traackr\Influencers::show($this->infUid2);
        $this->assertCount(1, $inf2['influencer'][$this->infUid2]['tags']);
        $this->assertTrue(in_array($this->infTag, $inf2['influencer'][$this->infUid2]['tags']));

        Traackr\Influencers::tagRemove(array(
            'influencers' => $this->infUid,
            'tags' => $this->infTag));
        Traackr\Influencers::tagRemove(array(
            'influencers' => $this->infUid2,
            'tags' => $this->infTag));

        $inf = Traackr\Influencers::show($this->infUid);
        $this->assertCount(0, $inf['influencer'][$this->infUid]['tags']);
        $inf2 = Traackr\Influencers::show($this->infUid2);
        $this->assertCount(0, $inf2['influencer'][$this->infUid2]['tags']);

        Traackr\Influencers::tagAdd(array(
            'influencers' => $this->infUid . ',' . $this->infUid2,
            'tags' => $this->infTag));
        $inf = Traackr\Influencers::show($this->infUid);
        $this->assertCount(1, $inf['influencer'][$this->infUid]['tags']);
        $this->assertTrue(in_array($this->infTag, $inf['influencer'][$this->infUid]['tags']));
        $inf2 = Traackr\Influencers::show($this->infUid2);
        $this->assertCount(1, $inf2['influencer'][$this->infUid2]['tags']);
        $this->assertTrue(in_array($this->infTag, $inf2['influencer'][$this->infUid2]['tags']));

        Traackr\Influencers::tagRemove(array(
            'influencers' => $this->infUid,
            'tags' => $this->infTag));
        Traackr\Influencers::tagRemove(array(
            'influencers' => $this->infUid2,
            'tags' => $this->infTag));
    }

    public function testTagRemove()
    {
        $inf = Traackr\Influencers::show($this->infUid);
        $this->assertCount(0, $inf['influencer'][$this->infUid]['tags']);

        Traackr\Influencers::tagAdd(array(
            'influencers' => $this->infUid,
            'tags' => $this->infTag));

        try {
            Traackr\Influencers::tagRemove(array(
                'tags' => $this->infTag));
        } catch (Traackr\MissingParameterException $e) {
            $this->assertInstanceOf('Traackr\MissingParameterException', $e, 'Missing argument missed');
        }
        // Make sure exception is not thrown
        Traackr\Influencers::tagRemove(array(
            'tags' => $this->infTag,
            'all' => true));
        $inf = Traackr\Influencers::show($this->infUid);
        $this->assertCount(0, $inf['influencer'][$this->infUid]['tags']);

        Traackr\Influencers::tagAdd(array(
            'influencers' => $this->infUid,
            'tags' => $this->infTag));
        Traackr\Influencers::tagRemove(array(
            'influencers' => $this->infUid,
            'tags' => $this->infTag));
        $this->assertCount(0, $inf['influencer'][$this->infUid]['tags']);
        $this->assertNotTrue(in_array($this->infTag, $inf['influencer'][$this->infUid]['tags']));
    }

    public function testTagBulkEdit()
    {
        $inf = Traackr\Influencers::show($this->infUid);
        $this->assertCount(0, $inf['influencer'][$this->infUid]['tags']);

        // Test adding tags to one influencer
        Traackr\Influencers::tagBulkEdit(array(
            'influencers' => $this->infUid,
            'tags_add' => array(
                $this->infTag,
                $this->infTag2,
                $this->infTagUTF8
            )
        ));
        $inf = Traackr\Influencers::show($this->infUid);
        $this->assertCount(3, $inf['influencer'][$this->infUid]['tags']);
        $this->assertTrue(in_array($this->infTag, $inf['influencer'][$this->infUid]['tags']));
        $this->assertTrue(in_array($this->infTag2, $inf['influencer'][$this->infUid]['tags']));
        $this->assertTrue(in_array($this->infTagUTF8, $inf['influencer'][$this->infUid]['tags']));

        // Test removing tags from one influencer
        Traackr\Influencers::tagBulkEdit(array(
            'influencers' => $this->infUid,
            'tags_remove' => array(
                $this->infTag,
                $this->infTagUTF8
            )
        ));
        $inf = Traackr\Influencers::show($this->infUid);
        $this->assertCount(1, $inf['influencer'][$this->infUid]['tags']);
        $this->assertFalse(in_array($this->infTag, $inf['influencer'][$this->infUid]['tags']));
        $this->assertFalse(in_array($this->infTagUTF8, $inf['influencer'][$this->infUid]['tags']));

        // Test removing and adding tags to multiple influencers
        Traackr\Influencers::tagBulkEdit(array(
            'influencers' => array(
                $this->infUid,
                $this->infUid2
            ),
            'tags_add' => array(
                $this->infTag
            ),
            'tags_remove' => array(
                $this->infTag2,
                $this->infTagUTF8
            )
        ));
        $infs = Traackr\Influencers::show(array(
            $this->infUid,
            $this->infUid2
        ));
        $this->assertCount(1, $inf['influencer'][$this->infUid]['tags']);
        $this->assertTrue(in_array($this->infTag, $infs['influencer'][$this->infUid]['tags']));
        $this->assertFalse(in_array($this->infTag2, $infs['influencer'][$this->infUid]['tags']));
        $this->assertFalse(in_array($this->infTagUTF8, $infs['influencer'][$this->infUid]['tags']));
        $this->assertCount(1, $infs['influencer'][$this->infUid2]['tags']);
        $this->assertTrue(in_array($this->infTag, $infs['influencer'][$this->infUid2]['tags']));
        $this->assertFalse(in_array($this->infTag2, $infs['influencer'][$this->infUid2]['tags']));
        $this->assertFalse(in_array($this->infTagUTF8, $infs['influencer'][$this->infUid2]['tags']));



        // Test removing tags from multiple influencers
        Traackr\Influencers::tagBulkEdit(array(
            'influencers' => array(
                $this->infUid,
                $this->infUid2
            ),
            'tags_remove' => array(
                $this->infTag
            )
        ));
        $infs = Traackr\Influencers::show(array(
            $this->infUid,
            $this->infUid2
        ));
        $this->assertCount(0, $inf['influencer'][$this->infUid]['tags']);
        $this->assertFalse(in_array($this->infTag, $infs['influencer'][$this->infUid]['tags']));
        $this->assertCount(0, $infs['influencer'][$this->infUid2]['tags']);
        $this->assertFalse(in_array($this->infTag, $infs['influencer'][$this->infUid2]['tags']));
    }

    public function testTagList()
    {
        $infs = Traackr\Influencers::tagList(array('tag' => 'SomeRandomTagNeverUser'));
        $this->assertCount(0, $infs['influencers']);

        Traackr\Influencers::tagAdd(array(
            'influencers' => $this->infUid,
            'tags' => $this->infTag));
        $infs = Traackr\Influencers::tagList(array('tag' => $this->infTag));
        $this->assertCount(1, $infs['influencers']);
        $this->assertTrue(in_array($this->infUid, $infs['influencers']));
        Traackr\Influencers::tagRemove(array(
            'influencers' => $this->infUid,
            'tags' => $this->infTag));

        // Test finding multiple infs with prefix
        Traackr\Influencers::tagAdd(array(
            'influencers' => $this->infUid,
            'tags' => $this->infTag));
        Traackr\Influencers::tagAdd(array(
            'influencers' => $this->infUid2,
            'tags' => $this->infTag . 'inf2'));
        $infs = Traackr\Influencers::tagList(array('tag' => $this->infTag, 'is_prefix' => false));
        $this->assertCount(1, $infs['influencers']);
        $infs = Traackr\Influencers::tagList(array('tag' => $this->infTag, 'is_prefix' => true));
        $this->assertCount(2, $infs['influencers']);
        Traackr\Influencers::tagRemove(array(
            'influencers' => array($this->infUid, $this->infUid2),
            'tags' => array($this->infTag, $this->infTag . 'inf2')));
    }

    public function testChannelsAdd()
    {
        $ownership = 'reference';

        $result = Traackr\Influencers::channelsAdd([
            'influencer' => $this->infUid,
            'url' => $this->channel,
            'ownership' => $ownership
        ]);

        $this->assertNotEmpty($result['uid']);
        $this->assertEquals($this->infUid, $result['influencer_uid']);
        $this->assertNotEmpty($result['ownership']);
    }

    public function testReport()
    {
        $reportText = 'Testing PHP client...';

        $result = Traackr\Influencers::report([
            'influencer' => $this->infUid,
            'url' => $this->channel,
            'reportText' => $reportText
        ]);

        $this->assertEquals($this->infUid, $result['influencer_uid']);
        $this->assertEquals($this->channel, $result['url']);
        $this->assertEquals($reportText, $result['reportText']);
    }

    /**
     * @group read-only
     */
    public function testLookupRO()
    {
        $inf = Traackr\Influencers::lookup(array('name' => 'zzzNONEXISTENT999zzz'));
        $this->assertCount(0, $inf['influencers'], 'Results found');

        $inf = Traackr\Influencers::lookup(array('name' => $this->infName));
        // Check results format
        $this->assertArrayHasKey('page_info', $inf, 'No paging info');
        $this->assertArrayHasKey('influencers', $inf, 'No influencers info');
        // Should only have found 1 result
        $this->assertCount(1, $inf['influencers'], 'Found multiple results');
        // Check some values
        $this->assertEquals($this->infUid, $inf['influencers'][0]['uid'], 'Invalid influencer/UID found');

        // Check appropriate fields are present
        $this->assertArrayHasKey('uid', $inf['influencers'][0], 'UID filed is missing');
        $this->assertArrayHasKey('name', $inf['influencers'][0], 'Name field missing');
        $this->assertArrayHasKey('description', $inf['influencers'][0], 'Description field missing');
        $this->assertArrayHasKey('title', $inf['influencers'][0], 'Title field missing');
        $this->assertArrayHasKey('location', $inf['influencers'][0], 'Location field missing');
        $this->assertArrayHasKey('avatar', $inf['influencers'][0], 'Avatar field missing');
        $this->assertArrayHasKey('reach', $inf['influencers'][0], 'Reach field missing');
        $this->assertArrayHasKey('resonance', $inf['influencers'][0], 'Resonance field missing');
        $this->assertArrayNotHasKey('channels', $inf['influencers'][0], 'Channels should not have be returned');
        $this->assertArrayNotHasKey('tags', $inf['influencers'][0], 'Tags field missing');
        // Check some values
        $this->assertEquals($this->infUid, $inf['influencers'][0]['uid'], 'Incorrect UID');
        $this->assertEquals($this->infName, $inf['influencers'][0]['name'], 'Incorrect name');

        // With country aggregations
        $inf = Traackr\Influencers::lookup(array(
            'name' => 'John',
            'enable_country_aggregation' => true));
        $this->assertArrayHasKey('aggregations', $inf, 'Country aggregation missing');
        $this->assertArrayHasKey('countryIsoCode', $inf['aggregations'], 'Country Aggregation: Country ISO key missing');
        $this->assertArrayHasKey('buckets', $inf['aggregations']['countryIsoCode'], 'Country Aggregation: buckets key missing');
        $this->assertNotEmpty($inf['aggregations']['countryIsoCode']['buckets'], 'No country aggregations found');
        $this->assertGreaterThan(0, $inf['aggregations']['countryIsoCode']['buckets'][0]['count'], 'There should be more than zero matches for the first country');

        // With audience aggregations
        $inf = Traackr\Influencers::lookup(array(
            'name' => 'John',
            'enable_audience_aggregation' => true));
        $this->assertArrayHasKey('aggregations', $inf, 'Audience aggregation missing');
        $this->assertArrayHasKey('audienceStatsTotal', $inf['aggregations'], 'Audience Aggregation: Audience Stats Total key missing');
        $this->assertNotEmpty($inf['aggregations']['audienceStatsTotal'], 'No audience stats total aggregations found');


        // Lookup By Email
        $inf = Traackr\Influencers::search(array('keywords' => 'fun', 'emails' => array('dchancogne@traackr.com', 'pierreloic@traackr.com')));
        $this->assertGreaterThan(0, count($inf['influencers']), 'No results found');
        $this->assertCount(2, $inf['influencers'], 'Two results should have been found');

        // Lookup By Email String
        $inf = Traackr\Influencers::search(array('keywords' => 'fun', 'emails' => 'dchancogne@traackr.com,pierreloic@traackr.com'));
        $this->assertGreaterThan(0, count($inf['influencers']), 'No results found');
        $this->assertCount(2, $inf['influencers'], 'Two results should have been found');
    }

    public function testLookup()
    {
        Traackr\Influencers::tagAdd(array(
            'influencers' => $this->infUid,
            'tags' => $this->infTag,
            'strict' => true));
        sleep(1); // Make sure tag operation is done indexing
        // Finds result with prefix
        $inf = Traackr\Influencers::lookup(array('tags' => 'traackr-api-', 'is_tag_prefix' => true));
        $this->assertCount(1, $inf['influencers'], 'Unexpected results found');
        // No result with exact tag match
        $inf = Traackr\Influencers::lookup(array('tags' => 'traackr-api-', 'is_tag_prefix' => false));
        $this->assertCount(0, $inf['influencers'], 'Unexpected results found');
        // Test tags aggregation
        $inf = Traackr\Influencers::lookup(array(
            'tags' => 'traackr-api-',
            'is_tag_prefix' => true,
            'enable_tags_aggregation' => true));
        $this->assertArrayHasKey('aggregations', $inf, 'Missing aggregation data');
        $this->assertCount(1, $inf['aggregations']['tags']['buckets'], 'Invalid number of aggregation buckets in result');
        $this->assertEquals($this->infTag, $inf['aggregations']['tags']['buckets'][0]['key'], 'Invalid buckets');
        $this->assertEquals(1, $inf['aggregations']['tags']['buckets'][0]['count'], 'Invalid tag aggregation count');

        // Test tags_exclusive
        Traackr\Influencers::tagAdd(array(
            'influencers' => $this->infUid2,
            'tags' => array($this->infTag, $this->infTag2)));
        sleep(1); // Make sure tag operation is done indexing
        $infs = Traackr\Influencers::lookup(array('tags' => $this->infTag));
        $this->assertCount(2, $infs['influencers'], 'Unexpected results found');
        $infs = Traackr\Influencers::lookup(array('tags' => $this->infTag, 'tags_exclusive' => $this->infTag2));
        $this->assertCount(1, $infs['influencers'], 'Unexpected results found');

        Traackr\Influencers::tagRemove(array(
            'influencers' => array($this->infUid, $this->infUid2),
            'tags' => array($this->infTag, $this->infTag2)));
    }

    /**
     * @group error-check
     * @group read-only
     * @expectedException Traackr\MissingParameterException
     * @expectedExceptionMessage Missing parameter: must provide keywords, audience, or social data search parameter
     */
    public function testSearchMissingRequiredParameter()
    {
        Traackr\Influencers::search([]);
    }

    /**
     * @group read-only
     */
    public function testSearchRO()
    {
        $inf = Traackr\Influencers::search(array('keywords' => 'traackr'));
        $this->assertGreaterThan(0, $inf['influencers'], 'No results found');
        $this->assertArrayHasKey('audience', $inf['influencers'][0], 'Audience metric missing');

        // With audience aggregation
        $inf = Traackr\Influencers::search(array('keywords' => 'traackr', 'enable_audience_aggregation' => true));
        $this->assertArrayHasKey('aggregations', $inf, 'Audience aggregation missing');
        $this->assertArrayHasKey('audienceStatsTotal', $inf['aggregations'], 'Audience aggregation missing');
        $this->assertGreaterThan(
            $inf['aggregations']['audienceStatsTotal']['min'],
            $inf['aggregations']['audienceStatsTotal']['max'],
            'Max audience not greater than min audience'
        );

        // With country aggregations
        $inf = Traackr\Influencers::search(array('keywords' => 'traackr', 'enable_country_aggregation' => true));
        $this->assertArrayHasKey('aggregations', $inf, 'Country aggregation missing');
        $this->assertArrayHasKey('countryIsoCode', $inf['aggregations'], 'Country Aggregation: Country ISO key missing');
        $this->assertArrayHasKey('buckets', $inf['aggregations']['countryIsoCode'], 'Country Aggregation: buckets key missing');
        $this->assertNotEmpty($inf['aggregations']['countryIsoCode']['buckets'], 'No country aggregations found');
        $this->assertGreaterThan(0, $inf['aggregations']['countryIsoCode']['buckets'][0]['count'], 'There should be more than zero matches for the first country');

        // With audience aggregations
        $inf = Traackr\Influencers::search(array('keywords' => 'traackr', 'enable_audience_aggregation' => true));
        $this->assertArrayHasKey('aggregations', $inf, 'Audience aggregation missing');
        $this->assertArrayHasKey('audienceStatsTotal', $inf['aggregations'], 'Audience Aggregation: Audience Stats key missing');
        $this->assertNotEmpty($inf['aggregations']['audienceStatsTotal'], 'No audience aggregations found');

        $inf = Traackr\Influencers::search(array('keywords' => 'zzzNONEXISTENT999zzz'));
        $this->assertCount(0, $inf['influencers'], 'Results found');

        // Search Email
        $inf = Traackr\Influencers::search(array('keywords' => 'traackr', 'emails' => array('dchancogne@traackr.com')));
        $this->assertGreaterThan(0, $inf['influencers'], 'No results found');
        $this->assertEquals('David Chancogne', $inf['influencers'][0]['name'], 'Name does not match expected result by email address: dchancogne@traackr.com');

        // Search Email (Emails param is string, not array)
        $inf = Traackr\Influencers::search(array('keywords' => 'traackr', 'emails' => 'dchancogne@traackr.com'));
        $this->assertGreaterThan(0, $inf['influencers'], 'No results found');
        $this->assertEquals('David Chancogne', $inf['influencers'][0]['name'], 'Name does not match expected result by email address: dchancogne@traackr.com');
    }

    public function testSearch()
    {
        Traackr\Influencers::tagAdd(array(
            'influencers' => $this->infUid,
            'tags' => $this->infTag));
        sleep(1); // Make sure tag operation is done indexing
        // Finds result with prefix
        $inf = Traackr\Influencers::search(array('keywords' => 'traackr', 'tags' => 'traackr-api-', 'is_tag_prefix' => true));
        $this->assertGreaterThan(0, $inf['influencers'], 'No results found');
        // No result with exact tag match
        $inf = Traackr\Influencers::search(array('keywords' => 'traackr', 'tags' => 'traackr-api-', 'is_tag_prefix' => false));
        $this->assertCount(0, $inf['influencers'], 'Results found');

        // Test tags_exclusive
        Traackr\Influencers::tagAdd(array(
            'influencers' => $this->infUid2,
            'tags' => array($this->infTag, $this->infTag2)));
        sleep(1); // Make sure tag operation is done indexing
        $infs = Traackr\Influencers::search(array('keywords' => 'traackr', 'tags' => $this->infTag));
        $this->assertCount(2, $infs['influencers'], 'No results found');
        $infs = Traackr\Influencers::search(array('keywords' => 'traackr', 'tags' => $this->infTag, 'tags_exclusive' => $this->infTag2));
        $this->assertCount(1, $infs['influencers'], 'Results found');

        Traackr\Influencers::tagRemove(array(
            'influencers' => array($this->infUid, $this->infUid2),
            'tags' => array($this->infTag, $this->infTag2)));
    }

    public function testQuickLookup()
    {
        $inf = Traackr\Influencers::quickLookup(array('query' => 'zzzNONEXISTENT999zzz'));
        $this->assertCount(0, $inf['influencers'], 'Results found');

        $inf = Traackr\Influencers::quickLookup(array('query' => $this->infName));
        // Check results format
        $this->assertArrayHasKey('page_info', $inf, 'No paging info');
        $this->assertArrayHasKey('influencers', $inf, 'No influencers info');
        // Should only have found 1 result
        $this->assertCount(1, $inf['influencers'], 'Found multiple results');
        // Check some values
        $this->assertEquals($this->infUid, $inf['influencers'][0]['uid'], 'Invalid influencer/UID found');

        // Check appropriate fields are present
        $this->assertArrayHasKey('uid', $inf['influencers'][0], 'UID filed is missing');
        $this->assertArrayHasKey('name', $inf['influencers'][0], 'Name field missing');
        $this->assertArrayHasKey('description', $inf['influencers'][0], 'Description field missing');
        $this->assertArrayHasKey('verified', $inf['influencers'][0], 'Verified field missing');
        $this->assertArrayHasKey('thumbnail', $inf['influencers'][0], 'Thumbnail field missing');
        $this->assertArrayHasKey('avatar', $inf['influencers'][0], 'Avatar field missing');
        $this->assertArrayHasKey('audience', $inf['influencers'][0], 'Audience field missing');

        // Check some values
        $this->assertEquals($this->infUid, $inf['influencers'][0]['uid'], 'Incorrect UID');
        $this->assertEquals($this->infName, $inf['influencers'][0]['name'], 'Incorrect name');
    }

    /**
     * @group error-check
     * @group read-only
     * @expectedException Traackr\MissingParameterException
     * @expectedExceptionMessage Missing parameter: query
     */
    public function testQuickLookupMissingRequiredParameter()
    {
        // this will fail and throw an expected exception
        Traackr\Influencers::quickLookup([]);
    }
}
