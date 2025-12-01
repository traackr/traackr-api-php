<?php

namespace Traackr;

class Analysis extends TraackrApiObject
{
    /**
     * Get top links
     *
     * @param array $p
     * @return mixed
     */
    public static function toplinks($p = array('min_linkbacks' => 10, 'count' => 5))
    {
        $analysis = new Analysis();

        $p = $analysis->addCustomerKey($p);

        // Manual transformation of arrays to strings (legacy support)
        // The parent TraackrApiObject only converts booleans automatically.
        if (isset($p['influencers'])) {
            $p['influencers'] = is_array($p['influencers']) ?
                implode(',', $p['influencers']) : $p['influencers'];
        }
        if (isset($p['tags'])) {
            $p['tags'] = is_array($p['tags']) ?
                implode(',', $p['tags']) : $p['tags'];
        }

        return $analysis->get(TraackrApi::$apiBaseUrl . 'analysis/toplinks', $p);
    }

    /**
     * Get keywords analysis
     *
     * @param array $p
     * @return mixed
     */
    public static function keywords($p = array())
    {
        $analysis = new Analysis();

        // Note: The third parameter 'true' indicates that the request is a JSON body.
        return $analysis->post(TraackrApi::$apiBaseUrl . 'analysis/keywords', $p, true);
    }
}