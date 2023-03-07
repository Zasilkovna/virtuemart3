<?php

namespace VirtueMartModelZasilkovna\Carrier;

use JText;

/**
 *
 */
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
     * @return ApiCarrier[]
     * @throws DownloadException
     */
    public function fetchAsArray($lang)
    {
        $json = $this->downloadJson($lang);

        $carriersData = $this->getFromJson($json);
        $apiCarriers = [];

        foreach ($carriersData as $carrier) {
            $apiCarrier = ApiCarrier::fromJsonObject($carrier);
            if ($apiCarrier !== null) {
                $apiCarriers[] = $apiCarrier;
            }
        }

        return $apiCarriers;
    }

    /**
     * @param string $language
     * @throws DownloadException
     */
    private function downloadJson($language)
    {
        $url = sprintf(self::API_URL, $this->apiKey, 'carrier', $language);
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
        $carriersData = json_decode($json, false);

        if ($carriersData === null) {
            throw new DownloadException(JText::_('PLG_VMSHIPMENT_PACKETERY_CARRIER_DOWNLOADER_JSON_ERROR'));
        }

        if (!is_array($carriersData) && $carriersData->error) {
            throw new DownloadException($carriersData->error);
        }

        return $carriersData;
    }
}
