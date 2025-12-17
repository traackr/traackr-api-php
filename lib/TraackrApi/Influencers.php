<?php

namespace Traackr;

class Influencers extends TraackrApiObject
{
    /**
     * Get an influencer's data
     *
     * @param string $uid
     * @param array $p
     * @return bool|mixed
     * @throws MissingParameterException
     */
    public static function show($uid, $p = array('with_channels' => false))
    {
        if (empty($uid)) {
            throw new MissingParameterException("Missing Influencer UID parameter");
        }

        // API Object
        $inf = new Influencers();
        //Sanitize default values
        $p['with_channels'] = $inf->convertBool($p, 'with_channels');
        // support for multi params
        $uid = is_array($uid) ? implode(',', $uid) : $uid;
        $p['uids'] = $uid;
        // Add customer key + check required params
        $p = $inf->addCustomerKey($p);
        $inf->checkRequiredParams($p, array('with_channels'));

        return $inf->post(TraackrApi::$apiBaseUrl . 'influencers/show/', $p);
    }

    /**
     * Lookup Influencer by a social handle
     *
     * @param string $username
     * @param string $platform e.g. TWITTER | INSTAGRAM | TIKTOK
     * @param string $type USERNAME | TWITTER_ID
     * @return bool|mixed
     * @throws MissingParameterException
     * @throws \UnexpectedValueException
     */
    public static function lookupSocial($username, $platform = 'TWITTER', $type = 'USERNAME')
    {
        if (empty($username)) {
            throw new MissingParameterException("Missing username parameter");
        }

        // note that we do not specifically validate our growing list of platforms
        if (empty($platform)) {
            throw new MissingParameterException("Missing platform parameter");
        }

        if ('USERNAME' !== $type && 'USER_ID' !== $type) {
            throw new \UnexpectedValueException('Type parameter must be "USERNAME" or "USER_ID".');
        }

        $inf = new Influencers();

        $parameters = [
            'platform' => $platform,
            'type' => $type
        ];

        return $inf->get(TraackrApi::$apiBaseUrl . 'influencers/lookup/social/' . $username, $parameters);
    }

    /**
     * Add social account
     *
     * @param array $p
     * @return bool|mixed
     * @throws MissingParameterException
     * @throws \UnexpectedValueException
     */
    public static function addSocial($p = array())
    {
        $inf = new Influencers();

        $p = $inf->addCustomerKey($p);
        // note that we do not specifically validate our growing list of platforms
        $inf->checkRequiredParams($p, array('customer_key'));

        // Trim and clean up platform
        $p['platform'] = trim($p['platform']);
        if (empty($p['platform'])) {
            $p['platform'] = null;
        } else {
            $p['platform'] = strtoupper($p['platform']);
        }

        // Validate business requirements
        if (empty($p['username']) && empty($p['user_id']) && empty($p['url'])) {
            throw new MissingParameterException("Either username, user_id, or url must be present");
        }

        $count = !empty($p['username']) + !empty($p['user_id']) + !empty($p['url']);
        if ($count !== 1) {
            throw new MissingParameterException("Only one of username, user_id, or url must be present");
        }

        if ((!empty($p['username']) || !empty($p['user_id'])) && $p['platform'] === null) {
            throw new MissingParameterException("Platform must be passed with username or user_id");
        }

        // support multi params
        if (!empty($p['tags'])) {
            $p['tags'] = is_array($p['tags']) ? implode(',', $p['tags']) : $p['tags'];
        }

        return $inf->post(TraackrApi::$apiBaseUrl . 'influencers/add/social', $p);
    }

    /**
     * Add influencer by name and primary URL
     *
     * @param array $p
     * @return bool|mixed
     * @throws MissingParameterException
     */
    public static function add($p = array())
    {
        $inf = new Influencers();

        $p = $inf->addCustomerKey($p);
        $inf->checkRequiredParams($p, array('name', 'url', 'customer_key'));

        // support multi params
        if (!empty($p['tags'])) {
            $p['tags'] = is_array($p['tags']) ? implode(',', $p['tags']) : $p['tags'];
        }

        return $inf->post(TraackrApi::$apiBaseUrl . 'influencers/add', $p);
    }

    /**
     * Add a tag to an influencer
     *
     * @param array $p
     * @return bool|mixed
     * @throws MissingParameterException
     */
    public static function tagAdd($p = array('strict' => false))
    {
        $inf = new Influencers();

        // Sanitize default values
        $p['strict'] = $inf->convertBool($p, 'strict');

        $p = $inf->addCustomerKey($p);
        $inf->checkRequiredParams($p, array('influencers', 'tags', 'customer_key', 'strict'));

        // support for multi params
        $p['influencers'] = is_array($p['influencers']) ?
            implode(',', $p['influencers']) : $p['influencers'];
        $p['tags'] = is_array($p['tags']) ?
            implode(',', $p['tags']) : $p['tags'];

        return $inf->post(TraackrApi::$apiBaseUrl . 'influencers/tag/add', $p);
    }

    /**
     * Remove a tag from an influencer
     *
     * @param array $p
     * @return bool|mixed
     * @throws MissingParameterException
     */
    public static function tagRemove($p = array('all' => false))
    {
        $inf = new Influencers();

        // Sanitize default values
        $p['all'] = $inf->convertBool($p, 'all');

        $p = $inf->addCustomerKey($p);
        // 'influencers' is not required if 'all' is set to true
        // by then 'all' has already be converted to a string
        if ($p['all'] === 'false') {
            $inf->checkRequiredParams($p, array('influencers', 'tags', 'customer_key', 'all'));
        } else {
            $inf->checkRequiredParams($p, array('tags', 'customer_key', 'all'));
        }

        // support for multi params
        if (!empty($p['influencers'])) {
            $p['influencers'] = is_array($p['influencers']) ?
                implode(',', $p['influencers']) : $p['influencers'];
        }
        $p['tags'] = is_array($p['tags']) ?
            implode(',', $p['tags']) : $p['tags'];

        return $inf->post(TraackrApi::$apiBaseUrl . 'influencers/tag/remove', $p);
    }

    /**
     * Add or remove tags associated to an influencer(s)
     *
     * @param array $p
     * @return bool|mixed
     * @throws MissingParameterException
     */
    public static function tagBulkEdit($p = array())
    {
        $inf = new Influencers();

        $p = $inf->addCustomerKey($p);
        $inf->checkRequiredParams($p, array('influencers', 'customer_key'));

        // support for multi params
        $p['influencers'] = is_array($p['influencers']) ?
            implode(',', $p['influencers']) : $p['influencers'];
        if (!empty($p['tags_add'])) {
            $p['tags_add'] = is_array($p['tags_add']) ?
                implode(',', $p['tags_add']) : $p['tags_add'];
        }
        if (!empty($p['tags_remove'])) {
            $p['tags_remove'] = is_array($p['tags_remove']) ?
                implode(',', $p['tags_remove']) : $p['tags_remove'];
        }

        return $inf->post(TraackrApi::$apiBaseUrl . 'influencers/tag/bulkedit', $p);
    }

    /**
     * Get the tags on an influencer
     *
     * @param array $p
     * @return bool|mixed
     * @throws MissingParameterException
     */
    public static function tagList($p = array(
        'is_prefix' => false,
        'type' => 'person'))
    {
        $inf = new Influencers();

        // Sanitize default values
        $p['is_prefix'] = $inf->convertBool($p, 'is_prefix');

        $p = $inf->addCustomerKey($p);
        $inf->checkRequiredParams($p, array('tag', 'is_prefix', 'customer_key'));

        return $inf->get(TraackrApi::$apiBaseUrl . 'influencers/tag/list', $p);
    }

    /**
     * Lookup influencers
     *
     * @param array $p
     * @return bool|mixed
     */
    public static function lookup($p = array(
        'is_tag_prefix' => false,
        'gender' => 'all',
        'type' => 'person',
        'enable_tags_aggregation' => false,
        'enable_country_aggregation' => false,
        'enable_audience_aggregation' => false,
        'enable_uids_aggregation' => false,
        'count' => 25, 'page' => 0,
        'sort' => 'name', 'sort_order' => 'asc'))
    {
        $inf = new Influencers();

        // Sanitize default values
        $p['is_tag_prefix'] = $inf->convertBool($p, 'is_tag_prefix');
        $p['enable_tags_aggregation'] = $inf->convertBool($p, 'enable_tags_aggregation');
        $p['enable_country_aggregation'] = $inf->convertBool($p, 'enable_country_aggregation');
        $p['enable_audience_aggregation'] = $inf->convertBool($p, 'enable_audience_aggregation');
        $p['enable_uids_aggregation'] = $inf->convertBool($p, 'enable_uids_aggregation');
        $p['use_alternate_vit'] = $inf->convertBool($p, 'use_alternate_vit');

        $p = $inf->addCustomerKey($p);

        // support for multi params
        if (isset($p['influencers'])) {
            $p['influencers'] = is_array($p['influencers']) ?
                implode(',', $p['influencers']) : $p['influencers'];
        }
        if (isset($p['influencers_exclusive'])) {
            $p['influencers_exclusive'] = is_array($p['influencers_exclusive']) ?
                implode(',', $p['influencers_exclusive']) : $p['influencers_exclusive'];
        }
        if (isset($p['tags'])) {
            $p['tags'] = is_array($p['tags']) ?
                implode(',', $p['tags']) : $p['tags'];
        }
        if (isset($p['tags_exclusive'])) {
            $p['tags_exclusive'] = is_array($p['tags_exclusive']) ?
                implode(',', $p['tags_exclusive']) : $p['tags_exclusive'];
        }
        if (isset($p['emails'])) {
            $p['emails'] = is_array($p['emails']) ?
                implode(',', $p['emails']) : $p['emails'];
        }
        // include parameter if set (true by default)
        if (isset($p['enable_regional_country_exclusions'])) {
            $p['enable_regional_country_exclusions'] = $inf->convertBool($p, 'enable_regional_country_exclusions');
        }
        return $inf->post(TraackrApi::$apiBaseUrl . 'influencers/lookup', $p);
    }

    /**
     * Search for influencers
     *
     * @param array $p
     * @return bool|mixed
     * @throws MissingParameterException
     */
    public static function search($p = array(
        'is_tag_prefix' => false,
        'gender' => 'all',
        'type' => 'person',
        'lang' => 'all',
        'enable_audience_aggregation' => false,
        'enable_country_aggregation' => false,
        'enable_uids_aggregation' => false,
        'count' => 25))
    {
        $inf = new Influencers();

        // Sanitize default values
        $p['is_tag_prefix'] = $inf->convertBool($p, 'is_tag_prefix');
        $p['enable_audience_aggregation'] = $inf->convertBool($p, 'enable_audience_aggregation');
        $p['enable_country_aggregation'] = $inf->convertBool($p, 'enable_country_aggregation');
        $p['enable_uids_aggregation'] = $inf->convertBool($p, 'enable_uids_aggregation');
        $p['use_alternate_vit'] = $inf->convertBool($p, 'use_alternate_vit');


        $p = $inf->addCustomerKey($p);

        $hasContentCriteria = false;
        try {
            $inf->checkRequiredParams($p, ['keywords']);
            $hasContentCriteria = true;
        } catch (MissingParameterException $e) {
        }

        $hasAudienceCriteria = false;
        try {
            $inf->checkRequiredParams($p, ['audience']);
            $hasAudienceCriteria = true;
        } catch (MissingParameterException $e) {
        }

        $hasSocialDataSearchCriteria = false;
        try {
            $inf->checkRequiredParams($p, ['social_data_audience_search_criteria']);
            $hasSocialDataSearchCriteria = true;
        } catch (MissingParameterException $e) {
        }

        if (!$hasContentCriteria && !$hasAudienceCriteria && !$hasSocialDataSearchCriteria) {
            throw new MissingParameterException('Missing parameter: must provide keywords, audience, or social data search parameter');
        }

        // support for multi params
        if (isset($p['keywords'])) {
            $p['keywords'] = is_array($p['keywords']) ?
                implode(',', $p['keywords']) : $p['keywords'];
        }
        if (isset($p['influencers'])) {
            $p['influencers'] = is_array($p['influencers']) ?
                implode(',', $p['influencers']) : $p['influencers'];
        }
        if (isset($p['influencers_exclusive'])) {
            $p['influencers_exclusive'] = is_array($p['influencers_exclusive']) ?
                implode(',', $p['influencers_exclusive']) : $p['influencers_exclusive'];
        }
        if (isset($p['tags'])) {
            $p['tags'] = is_array($p['tags']) ?
                implode(',', $p['tags']) : $p['tags'];
        }
        if (isset($p['tags_exclusive'])) {
            $p['tags_exclusive'] = is_array($p['tags_exclusive']) ?
                implode(',', $p['tags_exclusive']) : $p['tags_exclusive'];
        }
        if (isset($p['exclusion_keywords'])) {
            $p['exclusion_keywords'] = is_array($p['exclusion_keywords']) ?
                implode(',', $p['exclusion_keywords']) : $p['exclusion_keywords'];
        }
        if (isset($p['root_urls_inclusive'])) {
            $p['root_urls_inclusive'] = is_array($p['root_urls_inclusive']) ?
                implode(',', $p['root_urls_inclusive']) : $p['root_urls_inclusive'];
        }
        if (isset($p['root_urls_exclusive'])) {
            $p['root_urls_exclusive'] = is_array($p['root_urls_exclusive']) ?
                implode(',', $p['root_urls_exclusive']) : $p['root_urls_exclusive'];
        }
        if ( isset($p['publication_types_inclusive']) ) {
            $p['publication_types_inclusive'] = is_array($p['publication_types_inclusive']) ?
                implode(',', $p['publication_types_inclusive']) : $p['publication_types_inclusive'];
        }
        if ( isset($p['publication_types_exclusive']) ) {
            $p['publication_types_exclusive'] = is_array($p['publication_types_exclusive']) ?
                implode(',', $p['publication_types_exclusive']) : $p['publication_types_exclusive'];
        }
        if (isset($p['emails'])) {
            $p['emails'] = is_array($p['emails']) ?
                implode(',', $p['emails']) : $p['emails'];
        }
        if (isset($p['posts_inclusive'])) {
            $p['posts_inclusive'] = is_array($p['posts_inclusive']) ?
                implode(',', $p['posts_inclusive']) : $p['posts_inclusive'];
        }
        if (isset($p['posts_exclusive'])) {
            $p['posts_exclusive'] = is_array($p['posts_exclusive']) ?
                implode(',', $p['posts_exclusive']) : $p['posts_exclusive'];
        }
        // include parameter if set (true by default)
        if (isset($p['enable_regional_country_exclusions'])) {
            $p['enable_regional_country_exclusions'] = $inf->convertBool($p, 'enable_regional_country_exclusions');
        }
        return $inf->post(TraackrApi::$apiBaseUrl . 'influencers/search', $p);
    }

    /**
     * Quick search for influencers
     *
     * @param array $p
     * @return bool|mixed
     * @throws MissingParameterException
     */
    public static function quickLookup($p = array())
    {
        $inf = new Influencers();

        $p = $inf->addCustomerKey($p);

        $hasContentCriteria = false;

        // Input text to search
        $inf->checkRequiredParams($p, ['query']);

        // Sanitize default values
        if (isset($p['debug'])) {
            $p['debug'] = $inf->convertBool($p, 'debug');
        }

        return $inf->get(TraackrApi::$apiBaseUrl . 'influencers/quick-lookup', $p);
    }

    /**
     * Add a channel URL to an influencer
     *
     * @param array $p
     * @return bool|mixed
     * @throws MissingParameterException
     */
    public static function channelsAdd($p = [])
    {
        $inf = new Influencers();

        $inf->checkRequiredParams($p, ['influencer', 'url']);

        return $inf->post(TraackrApi::$apiBaseUrl . 'influencers/channels/add', $p);
    }

    /**
     * Report an influencer
     *
     * @param array $p
     * @return bool|mixed
     * @throws MissingParameterException
     */
    public static function report($p = [])
    {
        $inf = new Influencers();

        $inf->checkRequiredParams($p, ['influencer', 'url']);

        return $inf->post(TraackrApi::$apiBaseUrl . 'influencers/report', $p);
    }
}
