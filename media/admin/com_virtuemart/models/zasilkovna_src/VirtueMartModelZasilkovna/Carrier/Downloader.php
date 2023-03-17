<?php

namespace VirtueMartModelZasilkovna\Carrier;

use JText;

/**
 * Class Downloader downloads carriers' settings from API.
 */
class Downloader
{
    const API_URL = 'https://pickup-point.api.packeta.com/v5/%s/carrier.json?lang=%s';

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
     * @throws DownloadException
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

        throw new DownloadException(JText::_('PLG_VMSHIPMENT_PACKETERY_CARRIER_DOWNLOADER_URLFOPEN_ERROR'));
    }

    /**
     * Downloads carriers and returns in array.
     * @param string $lang
     * @return array
     * @throws DownloadException
     */
    public function fetchAsArray($lang)
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
     *
     * @return bool
     */
    public function validateCarrierData(array $carriers)
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

    /**
     * @param array $carrier
     * @return array
     */
    public function mapToDb(array $carrier)
    {
        return [
            'id' => (int)$carrier['id'],
            'name' => $carrier['name'],
            'country' => $carrier['country'],
            'currency' => $carrier['currency'],
            'pickup_points' => filter_var($carrier['pickupPoints'], FILTER_VALIDATE_BOOLEAN),
            'api_allowed' => filter_var($carrier['apiAllowed'], FILTER_VALIDATE_BOOLEAN),
            'separate_house_number' => filter_var($carrier['separateHouseNumber'], FILTER_VALIDATE_BOOLEAN),
            'customs_declarations' => filter_var($carrier['customsDeclarations'], FILTER_VALIDATE_BOOLEAN),
            'requires_email' => filter_var($carrier['requiresEmail'], FILTER_VALIDATE_BOOLEAN),
            'requires_phone' => filter_var($carrier['requiresPhone'], FILTER_VALIDATE_BOOLEAN),
            'requires_size' => filter_var($carrier['requiresSize'], FILTER_VALIDATE_BOOLEAN),
            'disallows_cod' => filter_var($carrier['disallowsCod'], FILTER_VALIDATE_BOOLEAN),
            'max_weight' => (float)$carrier['maxWeight'],
        ];
    }

    /**
     * @param string $lang
     * @throws DownloadException
     */
    public function run($lang)
    {
        $carriers = $this->fetchAsArray($lang);

        if (!$this->validateCarrierData($carriers)) {
            throw new DownloadException(JText::_('PLG_VMSHIPMENT_PACKETERY_CARRIER_DOWNLOADER_JSON_ERROR'));
        }

        return array_map([$this, 'mapToDb'], $carriers);
    }
}
