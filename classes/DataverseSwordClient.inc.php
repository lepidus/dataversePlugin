<?php

require_once('plugins/generic/dataverse/libs/swordappv2-php-library/swordappclient.php');

class DataverseSwordClient extends SWORDAPPClient
{
    private $debug = false;
    private $curl_opts = array();

    function completeIncompleteDeposit($sac_url, $sac_u, $sac_p, $sac_obo) {
        $sac_curl = $this->curl_init($sac_url, $sac_u, $sac_p);

        curl_setopt($sac_curl, CURLOPT_POST, true);

        $headers = $this->_init_headers();
        global $sal_useragent;
        array_push($headers, $sal_useragent);
        if (!empty($sac_obo)) {
            array_push($headers, "On-Behalf-Of: " . $sac_obo);
        }
        array_push($headers, "Content-Length: 0");
        array_push($headers, "In-Progress: false");

        curl_setopt($sac_curl, CURLOPT_POSTFIELDS, array(
            'file' => '@' .realpath('-')
        ));

        curl_setopt($sac_curl, CURLOPT_HTTPHEADER, $headers);

        $sac_resp = curl_exec($sac_curl);
        $sac_status = curl_getinfo($sac_curl, CURLINFO_HTTP_CODE);
        curl_close($sac_curl);

        $sac_response = new SWORDAPPResponse($sac_status, $sac_resp);

        return $sac_response;
    }

    private function curl_init($sac_url, $sac_user, $sac_password) {
        $sac_curl = curl_init();

        curl_setopt($sac_curl, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($sac_curl, CURLOPT_VERBOSE, $this->debug);
        curl_setopt($sac_curl, CURLOPT_URL, $sac_url);

        if(!empty($sac_user) && !empty($sac_password)) {
            curl_setopt($sac_curl, CURLOPT_USERPWD, $sac_user . ":" . $sac_password);
        }

        if(!empty($sac_user) && empty($sac_password)) {
            curl_setopt($sac_curl, CURLOPT_USERPWD, $sac_user . ":" . '');
        }

        foreach ($this->curl_opts as $opt => $val) {
            curl_setopt($sac_curl, $opt, $val);
        }
        
        return $sac_curl;
    }
}
