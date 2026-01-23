<?php

namespace VirtueMartModelZasilkovna\Carrier;

use JText;
use vRequest;

/**
 * Class Downloader downloads carriers' settings from API.
 */
class Downloader
{
    const API_URL = 'https://pickup-point.api.packeta.com/v5/%s/carrier.json?lang=%s';

    /** @var string */
    private $apiKey;

    /** @var bool */
    private $debug = false;

    /**
     * Downloader constructor.
     * @param string $apiKey
     */
    public function __construct($apiKey)
    {
        $this->apiKey = $apiKey;

        $getParams = vRequest::getGet();
        if (isset($getParams['debug']) && (string)$getParams['debug'] === '1') {
            $this->debug = true;
        }
    }

    /**
     * @param string $lang
     * @return array
     * @throws DownloadException
     */
    public function run($lang)
    {
        $carriers = $this->fetchAsArray($lang);

        $errorDetails = [];
        if (!$this->validateCarrierData($carriers, $errorDetails)) {
            throw new DownloadException(
                JText::_('PLG_VMSHIPMENT_PACKETERY_CARRIER_DOWNLOADER_VALIDATION_ERROR') .
                ($this->debug === true ? ' ' . json_encode($errorDetails) : '')
            );
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
        if (!$this->apiKey) {
            throw new DownloadException(JText::_('PLG_VMSHIPMENT_PACKETERY_API_KEY_NOT_SET'));
        }

        $url = sprintf(self::API_URL, $this->apiKey, $language);
        $response = $this->fetch($url);

        if ($response === false) {
            $lastError = error_get_last();
            $appendError = isset($lastError['message']) && $this->debug === true;

            throw new DownloadException(
                JText::_('PLG_VMSHIPMENT_PACKETERY_CARRIER_DOWNLOADER_DOWNLOAD_ERROR') .
                ($appendError ? ': ' . $lastError['message'] : '')
            );
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
            $error = json_last_error_msg();
            $appendError = json_last_error() !== JSON_ERROR_NONE  && $this->debug === true;

            throw new DownloadException(
                JText::_('PLG_VMSHIPMENT_PACKETERY_CARRIER_DOWNLOADER_JSON_ERROR') .
                ($appendError ? " (JSON error: $error). Data: " . htmlspecialchars(substr($json, 0, 100)) . '...' : '')
            );
        }

        if (isset($carriersData['error'])) {
            throw new DownloadException($carriersData['error']);
        }

        return $carriersData;
    }


    /**
     * Validates data from API.
     *
     * @param array $carriers Data retrieved from API.
     * @param string|null $errorDetails
     * @return bool
     */
    private function validateCarrierData(array $carriers, &$errorDetails = null)
    {
        if (empty($carriers)) {
            $errorDetails = 'Empty carrier data.';
            return false;
        }

        $requiredFields = [
            'id',
            'name',
            'country',
            'currency',
            'pickupPoints',
            'apiAllowed',
            'separateHouseNumber',
            'customsDeclarations',
            'requiresEmail',
            'requiresPhone',
            'requiresSize',
            'disallowsCod',
            'maxWeight',
        ];

        foreach ($carriers as $carrier) {
            $missingFields = [];
            foreach ($requiredFields as $field) {
                if (!isset($carrier[$field])) {
                    $missingFields[] = $field;
                }
            }

            if (!empty($missingFields)) {
                $carrierId = isset($carrier['id']) ? $carrier['id'] : 'unknown';
                $errorDetails = sprintf('Carrier ID %s is missing fields: %s', $carrierId, implode(', ', $missingFields));
                return false;
            }
        }

        return true;
    }

}
