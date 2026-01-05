<?php

namespace Traackr;

class Analysis extends TraackrApiObject {

    public static function keywords($p = array())
    {
        $analysis = new Analysis();

        // Note: The third parameter 'true' indicates that the request is a JSON body.
        return $analysis->post(TraackrApi::$apiBaseUrl . 'analysis/keywords', $p, true);
    }
}