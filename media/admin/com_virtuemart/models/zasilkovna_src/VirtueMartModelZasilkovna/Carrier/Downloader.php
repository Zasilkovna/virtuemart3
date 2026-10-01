<?php

namespace VirtueMartModelZasilkovna\Carrier;

use JFactory;
use JText;
use JUri;
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

    // The VirtueMart log view shows the content of this log file.
    const LOG_VIEW_PATH = 'administrator/index.php?option=com_virtuemart&amp;view=log&amp;task=edit&amp;logfile=' . self::LOG_FILE;

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
        $json = $this->downloadJson($lang);
        $carriers = $this->getFromJson($json);

        $errorDetails = null;
        if (!$this->validateCarrierData($carriers, $errorDetails)) {
            $this->log('Validation failed: ' . $errorDetails);
            $this->storeFeedCopy($json);

            throw new DownloadException(JText::sprintf('PLG_VMSHIPMENT_PACKETERY_CARRIER_DOWNLOADER_VALIDATION_ERROR', $this->getLogLink()));
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

            throw new DownloadException(JText::sprintf('PLG_VMSHIPMENT_PACKETERY_CARRIER_DOWNLOADER_DOWNLOAD_ERROR', $this->getLogLink()));
        }

        // The body of a status other than 200 does not describe carriers, so the client never sees it.
        // A status other than 401 or 5xx means a wrong URL, a blocked account or a rate limit.
        // A response without a status line (null) goes on to the body check.
        $status = $response['status'];
        if ($status !== null && $status !== 200) {
            if ($status === 401) {
                $message = JText::_('PLG_VMSHIPMENT_PACKETERY_CARRIER_DOWNLOADER_API_KEY_INVALID');
            } elseif ($status >= 500 && $status <= 599) {
                $message = JText::_('PLG_VMSHIPMENT_PACKETERY_CARRIER_DOWNLOADER_SERVER_ERROR');
            } else {
                $message = JText::sprintf('PLG_VMSHIPMENT_PACKETERY_CARRIER_DOWNLOADER_DOWNLOAD_ERROR', $this->getLogLink());
            }

            $this->log('HTTP ' . $status . '. Data: ' . $this->truncate($response['body']));

            throw new DownloadException($message);
        }

        if (trim($response['body']) === '') {
            $this->log('Empty response body');

            throw new DownloadException(JText::sprintf('PLG_VMSHIPMENT_PACKETERY_CARRIER_DOWNLOADER_DOWNLOAD_ERROR', $this->getLogLink()));
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

            throw new DownloadException(JText::sprintf('PLG_VMSHIPMENT_PACKETERY_CARRIER_DOWNLOADER_JSON_ERROR', $this->getLogLink()));
        }

        if (isset($carriersData['error'])) {
            if (!is_string($carriersData['error']) || trim($carriersData['error']) === '') {
                $this->log('Invalid error field. Data: ' . $this->truncate($json));
                $this->storeFeedCopy($json);

                throw new DownloadException(JText::sprintf('PLG_VMSHIPMENT_PACKETERY_CARRIER_DOWNLOADER_VALIDATION_ERROR', $this->getLogLink()));
            }

            $this->log('Feed error. Data: ' . $this->truncate($json));

            throw new DownloadException(JText::sprintf('PLG_VMSHIPMENT_PACKETERY_CARRIER_DOWNLOADER_FEED_ERROR', $this->escape($this->truncate($carriersData['error'])), $this->getLogLink()));
        }

        return $carriersData;
    }

    /**
     * Returns a link to the VirtueMart log view
     * @return string
     */
    private function getLogLink()
    {
        return '<a href="' . JUri::root() . self::LOG_VIEW_PATH . '">' . JText::_('PLG_VMSHIPMENT_PACKETERY_CARRIER_DOWNLOADER_LOG_LINK') . '</a>';
    }

    /**
     * Saves the last invalid feed next to the log, so that support can analyse it
     * @param string $json
     * @return void
     */
    private function storeFeedCopy($json)
    {
        $path = JFactory::getConfig()->get('log_path') . '/packeta.feed.php';
        // The leading # keeps the file plain text, so the VirtueMart log view shows it as a link.
        $header = "#<?php die('Forbidden.'); ?>\n";
        // The VirtueMart log view prints the lines without escaping.
        if (@file_put_contents($path, $header . $this->escape($json)) === false) {
            $this->log('Cannot write the feed copy: ' . $path);
        }
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
