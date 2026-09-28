<?php

namespace VirtueMartModelZasilkovna\Carrier;

use JText;
use Joomla\CMS\Log\Log;

/**
 * Class Downloader downloads carriers' settings from API.
 */
class Downloader
{
    const API_URL = 'https://pickup-point.api.packeta.com/v5/%s/carrier.json?lang=%s';

    const LOG_CATEGORY = 'packeta.errors';

    const LOG_FILE = 'packeta.errors.php';

    const MAX_TEXT_LENGTH = 200;

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

        $errorDetails = null;
        if (!$this->validateCarrierData($carriers, $errorDetails)) {
            $this->log('Validation failed: ' . $errorDetails);

            throw new DownloadException(JText::_('PLG_VMSHIPMENT_PACKETERY_CARRIER_DOWNLOADER_VALIDATION_ERROR'));
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
        $warnings = [];
        set_error_handler(
            function ($severity, $message) use (&$warnings) {
                $warnings[] = $message;

                return true;
            },
            E_WARNING
        );

        try {
            $response = $this->fetch($url);
        } finally {
            restore_error_handler();
        }

        if ($response === false) {
            $this->log('Download failed: ' . implode('; ', $warnings));

            throw new DownloadException(JText::_('PLG_VMSHIPMENT_PACKETERY_CARRIER_DOWNLOADER_DOWNLOAD_ERROR'));
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
            $this->log('JSON error: ' . json_last_error_msg() . '. Data: ' . $this->truncate($json));

            throw new DownloadException(JText::_('PLG_VMSHIPMENT_PACKETERY_CARRIER_DOWNLOADER_JSON_ERROR'));
        }

        if (isset($carriersData['error'])) {
            throw new DownloadException($this->escape($this->truncate($carriersData['error'])));
        }

        return $carriersData;
    }

    /**
     * Writes the technical cause of a failure to the log, the client sees only the translated message
     * @param string $detail
     * @return void
     */
    private function log($detail)
    {
        try {
            Log::addLogger(
                [
                    'text_file' => self::LOG_FILE,
                    // Without the format the logger writes the client IP address into the file.
                    'text_entry_format' => '{DATETIME} {PRIORITY} {CATEGORY} {MESSAGE}',
                ],
                Log::ALL,
                [self::LOG_CATEGORY]
            );
            // The VirtueMart log view prints the lines without escaping.
            Log::add($this->escape(str_replace(["\r", "\n"], ' ', $detail)), Log::ERROR, self::LOG_CATEGORY);
        } catch (\Throwable $e) {
            // A failed log must not replace the message for the client.
        }
    }

    /**
     * Escapes text from the feed before it goes to HTML output
     *
     * Without ENT_SUBSTITUTE invalid UTF-8 gives an empty string.
     * @param string $text
     * @return string
     */
    private function escape($text)
    {
        return htmlspecialchars($text, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }

    /**
     * Shortens text from the feed without cutting a UTF-8 character in half
     *
     * mb_substr() replaces invalid UTF-8, so only the length tells whether the text was cut.
     * @param string $text
     * @return string
     */
    private function truncate($text)
    {
        if (mb_strlen($text, 'UTF-8') <= self::MAX_TEXT_LENGTH) {
            return $text;
        }

        return mb_substr($text, 0, self::MAX_TEXT_LENGTH, 'UTF-8') . '...';
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
