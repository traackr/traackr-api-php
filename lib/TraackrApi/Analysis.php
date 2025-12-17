<?php

namespace Traackr;

class Analysis extends TraackrApiObject {

    public static function keywords($p = array())
    {
        $analysis = new Analysis();
        return $analysis->post(TraackrApi::$apiBaseUrl.'analysis/keywords', $p, true);
    }
}