<?php

namespace VirtueMartModelZasilkovna\Carrier;

use JText;

class Downloader
{
    const API_URL = 'https://pickup-point.api.packeta.com/v5/%s/%s/json?lang=%s';
    private $apiKey;

    /**
     * Downloader constructor.
     * @param string $apiKey
     */
    public function __construct($apiKey)
    {
        $this->apiKey = $apiKey;
    }

    /**
     * @param string $url
     * @return false|string
     */
    public function fetch($url)
    {
        if (ini_get('allow_url_fopen')) {
            if (function_exists('stream_context_create')) {
                $ctx = stream_context_create(
                    array(
                        'http' => array(
                            'timeout' => 20,
                            'ignore_errors' => true, //to get API response although headers are not 200
                        )
                    )
                );

                return file_get_contents($url, 0, $ctx);
            }

            return file_get_contents($url);
        }

        return false;
    }

    /**
     * @param string $lang
     * @return array
     * @throws \RuntimeException
     */
    public function fetchCarriers($lang)
    {
        $url = sprintf(self::API_URL, $this->apiKey, 'carrier', $lang);
        $response = $this->fetch($url);

        if ($response === false) {
            throw new \RuntimeException(JText::_('PLG_VMSHIPMENT_PACKETERY_CARRIERS_JSON_ERROR'));
        }

        $carriers = json_decode($response, false);
        if (!is_array($carriers) && $carriers->error) {
            throw new \RuntimeException($carriers->error);
        }

        return $carriers;
    }
}