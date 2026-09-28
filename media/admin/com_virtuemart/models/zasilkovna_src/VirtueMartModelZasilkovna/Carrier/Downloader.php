<?php

namespace VirtueMartModelZasilkovna\Carrier;

use JText;
use Joomla\CMS\Log\Log;

/**
 * Class Downloader downloads carriers' settings from API.
 */
class Downloader
{
    const API_URL = 'https://pickup-point.api.packeta.com/v5/%s/carrier/json?lang=%s';

    const LOG_CATEGORY = 'packeta.errors';

    const LOG_FILE = 'packeta.errors.php';

    const MAX_TEXT_LENGTH = 200;

    const HTTP_STATUS_SUCCESS_MIN = 200;

    const HTTP_STATUS_SUCCESS_MAX = 299;

    const HTTP_STATUS_UNAUTHORIZED = 401;

    const HTTP_STATUS_SERVER_ERROR_MIN = 500;

    const HTTP_STATUS_SERVER_ERROR_MAX = 599;

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
     * @return array{status: int|null, body: string}|false
     * @throws DownloadException
     */
    private function fetch($url)
    {
        if (!ini_get('allow_url_fopen')) {
            throw new DownloadException(JText::_('PLG_VMSHIPMENT_PACKETERY_CARRIER_DOWNLOADER_URLFOPEN_ERROR'));
        }

        $context = null;
        if (function_exists('stream_context_create')) {
            $context = stream_context_create(
                [
                    'http' => [
                        'timeout' => 20,
                        'ignore_errors' => true, //to get API response although headers are not 200
                    ],
                ]
            );
        }

        // fopen() gives the status line without $http_response_header, which PHP 8.5 deprecates.
        $handle = $context === null ? fopen($url, 'r') : fopen($url, 'r', false, $context);
        if ($handle === false) {
            return false;
        }

        $metaData = stream_get_meta_data($handle);
        $body = stream_get_contents($handle);
        fclose($handle);
        if ($body === false) {
            return false;
        }

        return [
            'status' => $this->getStatusCode($metaData),
            'body' => $body,
        ];
    }

    /**
     * Reads the HTTP status of the last response, after redirects
     * @param array $metaData Result of stream_get_meta_data().
     * @return int|null
     */
    private function getStatusCode(array $metaData)
    {
        $status = null;
        $headers = isset($metaData['wrapper_data']) && is_array($metaData['wrapper_data']) ? $metaData['wrapper_data'] : [];
        foreach ($headers as $headerLine) {
            if (preg_match('~^HTTP/\S+\s+(\d{3})~', $headerLine, $matches)) {
                $status = (int) $matches[1];
            }
        }

        return $status;
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
     * @return string
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

        // The feed returns 401 for an invalid API key. The text of the body is not used.
        if ($response['status'] === self::HTTP_STATUS_UNAUTHORIZED) {
            $this->log('HTTP 401. Data: ' . $this->truncate($response['body']));

            throw new DownloadException(JText::_('PLG_VMSHIPMENT_PACKETERY_CARRIER_DOWNLOADER_API_KEY_INVALID'));
        }

        // A server error comes with an HTML page. Its text does not tell the client anything.
        if ($response['status'] >= self::HTTP_STATUS_SERVER_ERROR_MIN
            && $response['status'] <= self::HTTP_STATUS_SERVER_ERROR_MAX
        ) {
            $this->log('HTTP ' . $response['status'] . '. Data: ' . $this->truncate($response['body']));

            throw new DownloadException(JText::_('PLG_VMSHIPMENT_PACKETERY_CARRIER_DOWNLOADER_SERVER_ERROR'));
        }

        // Another status outside 2xx means a wrong URL, a blocked account or a rate limit.
        // The body does not describe carriers, so it is not used.
        // A response without a status line (null) does not stop the download.
        if ($response['status'] !== null
            && ($response['status'] < self::HTTP_STATUS_SUCCESS_MIN
                || $response['status'] > self::HTTP_STATUS_SUCCESS_MAX)
        ) {
            $this->log('HTTP ' . $response['status'] . '. Data: ' . $this->truncate($response['body']));

            throw new DownloadException(JText::_('PLG_VMSHIPMENT_PACKETERY_CARRIER_DOWNLOADER_DOWNLOAD_ERROR'));
        }

        if (trim($response['body']) === '') {
            $this->log('Empty response body');

            throw new DownloadException(JText::_('PLG_VMSHIPMENT_PACKETERY_CARRIER_DOWNLOADER_DOWNLOAD_ERROR'));
        }

        return $response['body'];
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
            if (!is_string($carriersData['error']) || trim($carriersData['error']) === '') {
                $this->log('Invalid error field. Data: ' . $this->truncate($json));

                throw new DownloadException(JText::_('PLG_VMSHIPMENT_PACKETERY_CARRIER_DOWNLOADER_VALIDATION_ERROR'));
            }

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
