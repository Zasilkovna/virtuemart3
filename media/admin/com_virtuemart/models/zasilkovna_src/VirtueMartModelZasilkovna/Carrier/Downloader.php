<?php

namespace VirtueMartModelZasilkovna\Carrier;

use JText;

/**
 * Class Downloader downloads carriers' settings from API.
 */
class Downloader
{
    const API_URL = 'https://pickup-point.api.packeta.com/v5/%s/carrier.json?lang=%s';

    /** @var string */
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
     * @param string $lang
     * @return array
     * @throws DownloadException
     */
    public function run($lang)
    {
        $carriers = $this->fetchAsArray($lang);

        if (!$this->validateCarrierData($carriers)) {
            throw new DownloadException(JText::_('PLG_VMSHIPMENT_PACKETERY_CARRIER_DOWNLOADER_JSON_ERROR'));
        }

        return $carriers;
    }

    /**
     * @param string $url
     * @return false|string
     * @throws DownloadException
     */
    private function fetch($url)
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

        throw new DownloadException(JText::_('PLG_VMSHIPMENT_PACKETERY_CARRIER_DOWNLOADER_URLFOPEN_ERROR'));
    }

    /**
     * Downloads carriers and returns in array.
     * @param string $lang
     * @return array
     * @throws DownloadException
     */
    private function fetchAsArray($lang)
    {
        $json = $this->downloadJson($lang);

        return $this->getFromJson($json);
    }

    /**
     * @param string $language
     * @throws DownloadException
     */
    private function downloadJson($language)
    {
        $url = sprintf(self::API_URL, $this->apiKey, $language);
        $response = $this->fetch($url);

        if ($response === false) {
            throw new DownloadException(JText::_('PLG_VMSHIPMENT_PACKETERY_CARRIER_DOWNLOADER_JSON_ERROR'));
        }

        return $response;
    }

    /**
     * @param string $json
     * @return array
     * @throws DownloadException
     */
    private function getFromJson($json)
    {
        $carriersData = json_decode($json, true);

        if (!is_array($carriersData)) {
            throw new DownloadException(JText::_('PLG_VMSHIPMENT_PACKETERY_CARRIER_DOWNLOADER_JSON_ERROR'));
        }

        if (isset($carriersData['error'])) {
            throw new DownloadException($carriersData->error);
        }

        return $carriersData;
    }


    /**
     * Validates data from API.
     *
     * @param array $carriers Data retrieved from API.
     * @return bool
     */
    private function validateCarrierData(array $carriers)
    {
        foreach ($carriers as $carrier) {
            if (!isset(
                $carrier['id'],
                $carrier['name'],
                $carrier['country'],
                $carrier['currency'],
                $carrier['pickupPoints'],
                $carrier['apiAllowed'],
                $carrier['separateHouseNumber'],
                $carrier['customsDeclarations'],
                $carrier['requiresEmail'],
                $carrier['requiresPhone'],
                $carrier['requiresSize'],
                $carrier['disallowsCod'],
                $carrier['maxWeight']
            )) {
                return false;
            }
        }

        return true;
    }

}
