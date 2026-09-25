<?php

namespace VirtueMartModelZasilkovna\Carrier;

use JText;
use JUri;
use Joomla\CMS\Factory;
use Joomla\CMS\Log\Log;

/**
 * Class Downloader downloads carriers' settings from API.
 */
class Downloader
{
    const API_URL = 'https://pickup-point.api.packeta.com/v5/%s/carrier.json?lang=%s';

    const API_ERROR_INVALID_KEY = 'Invalid API key';

    const MAX_TEXT_LENGTH = 200;

    const LOG_CATEGORY_ERRORS = 'packeta.errors';

    const LOG_CATEGORY_WARNINGS = 'packeta.warnings';

    const FEED_COPY_FILE = 'packeta.feed.php';

    // The log view has no ACL action of its own, so whoever can update carriers can open it.
    const LOG_VIEW_PATH = 'administrator/index.php?option=com_virtuemart&amp;view=log';

    const REQUIRED_CARRIER_FIELDS = array(
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
    );

    /** @var string */
    private $apiKey;

    /** @var string[] */
    private $fetchWarnings = array();

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
        $this->fetchWarnings = array();

        $json = $this->downloadJson($lang);
        $carriers = $this->getFromJson($json);

        $this->validateCarrierData($carriers, $json);

        return $carriers;
    }

    /**
     * @return string[] Warnings of the last download.
     */
    public function getFetchWarnings()
    {
        return $this->fetchWarnings;
    }

    /**
     * @param array<int, array<string, scalar>> $carriers Return value of run().
     * @param \Exception $cause
     * @return DownloadException
     */
    public function saveFailure(array $carriers, \Exception $cause)
    {
        $stored = self::storeFeedCopy(json_encode($carriers, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));

        // getMessage() lacks the SQL error code, which tells a deadlock from damaged data.
        $detail = "saving carriers failed: {$cause->getCode()} {$cause->getMessage()}";

        return new DownloadException(
            JText::_('PLG_VMSHIPMENT_PACKETERY_CARRIER_DOWNLOADER_SAVE_ERROR') . self::feedCopyNote($stored),
            0,
            new \Exception($detail, 0, $cause)
        );
    }

    /**
     * @param string $detail
     * @param int $severity One of the Log severity constants.
     * @return void
     */
    public function log($detail, $severity = Log::ERROR)
    {
        $category = $severity === Log::WARNING ? self::LOG_CATEGORY_WARNINGS : self::LOG_CATEGORY_ERRORS;

        if (!self::isLogWritable($category)) {
            return;
        }

        // The log view echoes every line into <li> unescaped.
        $message = htmlspecialchars(self::flattenForLog($detail), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');

        try {
            // Without the format the logger writes {CLIENTIP} into a file the client sends to support.
            Log::addLogger(
                array(
                    'text_file' => "{$category}.php",
                    'text_entry_format' => '{DATETIME} {PRIORITY} {CATEGORY} {MESSAGE}',
                ),
                Log::ALL,
                array($category)
            );
            Log::add($message, $severity, $category);
        } catch (\Throwable $e) {
            // A failed log must not replace the message with an error page.
        }
    }

    /**
     * @param string $url
     * @return false|string
     * @throws DownloadException
     */
    private function fetch($url)
    {
        if (!ini_get('allow_url_fopen')) {
            throw new DownloadException(
                self::carrierError(JText::_('PLG_VMSHIPMENT_PACKETERY_CARRIER_DOWNLOADER_URLFOPEN_ERROR'))
            );
        }

        set_error_handler(
            function ($severity, $message) {
                $this->fetchWarnings[] = $message;

                return true;
            },
            E_WARNING
        );

        try {
            if (!function_exists('stream_context_create')) {
                return file_get_contents($url);
            }

            $ctx = stream_context_create(
                array(
                    'http' => array(
                        'timeout' => 20,
                        'ignore_errors' => true, //to get API response although headers are not 200
                    )
                )
            );

            return file_get_contents($url, false, $ctx);
        } finally {
            restore_error_handler();
        }
    }

    /**
     * @param string $message Text for the client.
     * @param string $detail Technical cause for the log.
     * @return DownloadException
     */
    private function failure($message, $detail)
    {
        if ($this->fetchWarnings) {
            $detail .= '; PHP warnings: ' . implode('; ', $this->fetchWarnings);
        }

        return new DownloadException($message, 0, new \Exception($detail));
    }

    /**
     * @param string $key Language key of the cause.
     * @param string $detail Technical cause for the log.
     * @param string $json Feed body for the copy.
     * @return DownloadException
     */
    private function feedFailure($key, $detail, $json)
    {
        $stored = self::storeFeedCopy($json);

        return $this->failure(self::carrierError(JText::_($key), self::feedCopyNote($stored)), $detail);
    }

    /**
     * @param string $sentence Translated cause.
     * @param string $note
     * @return string
     */
    private static function carrierError($sentence, $note = '')
    {
        return JText::_('PLG_VMSHIPMENT_PACKETERY_CARRIER_DOWNLOADER_FAILED') . ' ' . $sentence . $note;
    }

    /**
     * @param string $language
     * @return string
     * @throws DownloadException
     */
    private function downloadJson($language)
    {
        if (!$this->apiKey) {
            throw $this->failure(
                self::carrierError(JText::_('PLG_VMSHIPMENT_PACKETERY_CARRIER_DOWNLOADER_API_KEY_MISSING')),
                'API key is not set'
            );
        }

        $url = sprintf(self::API_URL, $this->apiKey, $language);
        $response = $this->fetch($url);

        if ($response === false) {
            throw $this->failure(
                self::carrierError(
                    JText::_('PLG_VMSHIPMENT_PACKETERY_CARRIER_DOWNLOADER_DOWNLOAD_ERROR'),
                    self::logNote()
                ),
                'download failed'
            );
        }

        if (trim($response) === '') {
            throw $this->failure(
                self::carrierError(
                    JText::_('PLG_VMSHIPMENT_PACKETERY_CARRIER_DOWNLOADER_DOWNLOAD_ERROR'),
                    self::logNote()
                ),
                'empty response body'
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
            throw $this->feedFailure(
                'PLG_VMSHIPMENT_PACKETERY_CARRIER_DOWNLOADER_JSON_ERROR',
                self::describeJsonFailure($json),
                $json
            );
        }

        if (array_key_exists('error', $carriersData)) {
            $apiError = trim($carriersData['error']);
            $flattened = self::flattenForLog($apiError);
            $detail = self::truncateForLog($flattened, self::MAX_TEXT_LENGTH);

            // Substring, not equality: the API wraps the phrase in a whole sentence.
            if (stripos($apiError, self::API_ERROR_INVALID_KEY) !== false) {
                throw $this->failure(
                    self::carrierError(JText::_('PLG_VMSHIPMENT_PACKETERY_CARRIER_DOWNLOADER_API_KEY_INVALID')),
                    $detail
                );
            }

            throw $this->failure(
                self::carrierError(
                    JText::sprintf(
                        'PLG_VMSHIPMENT_PACKETERY_CARRIER_DOWNLOADER_API_ERROR_REPORTED',
                        htmlspecialchars(
                            self::truncateForLog($flattened, self::MAX_TEXT_LENGTH, '…'),
                            // Without ENT_SUBSTITUTE invalid UTF-8 gives an empty string.
                            ENT_QUOTES | ENT_SUBSTITUTE,
                            'UTF-8'
                        )
                    ),
                    self::logNoteOrSupport()
                ),
                $detail
            );
        }

        return $carriersData;
    }

    /**
     * @param string $json
     * @return string
     */
    private static function describeJsonFailure($json)
    {
        $reason = json_last_error() === JSON_ERROR_NONE ? 'not a JSON array' : json_last_error_msg();
        $preview = self::truncateForLog(self::flattenForLog($json), self::MAX_TEXT_LENGTH);

        return "{$reason}. Data: {$preview}";
    }

    /**
     * Validates data from API.
     * @param array<int, mixed> $carriers Data retrieved from API, each item validated as a carrier.
     * @param string $json Raw feed body, kept for support when the data is damaged.
     * @return void
     * @throws DownloadException
     */
    private function validateCarrierData(array $carriers, $json)
    {
        if (empty($carriers)) {
            throw $this->feedFailure(
                'PLG_VMSHIPMENT_PACKETERY_CARRIER_DOWNLOADER_FEED_DATA_ERROR',
                'feed returned no carriers',
                $json
            );
        }

        $invalidCarriers = array();

        foreach ($carriers as $carrier) {
            $missing = array();
            $nulled = array();

            foreach (self::REQUIRED_CARRIER_FIELDS as $field) {
                if (!is_array($carrier) || !array_key_exists($field, $carrier)) {
                    $missing[] = $field;
                } elseif ($carrier[$field] === null) {
                    $nulled[] = $field;
                }
            }

            if ($missing || $nulled) {
                $invalidCarriers[] = array(
                    'id' => isset($carrier['id']) ? $carrier['id'] : '?',
                    'missing' => $missing,
                    'nulled' => $nulled,
                );
            }
        }

        if (!$invalidCarriers) {
            return;
        }

        $detail = sprintf(
            'carrier %s has missing fields: %s; fields with no value: %s (%d of %d carriers are invalid)',
            $invalidCarriers[0]['id'],
            self::formatFieldList($invalidCarriers[0]['missing']),
            self::formatFieldList($invalidCarriers[0]['nulled']),
            count($invalidCarriers),
            count($carriers)
        );

        if (self::isFeedFormatChange($invalidCarriers, count($carriers))) {
            throw $this->feedFailure(
                'PLG_VMSHIPMENT_PACKETERY_CARRIER_DOWNLOADER_FEED_VERSION_ERROR',
                $detail,
                $json
            );
        }

        throw $this->feedFailure('PLG_VMSHIPMENT_PACKETERY_CARRIER_DOWNLOADER_FEED_DATA_ERROR', $detail, $json);
    }

    /**
     * @param string[] $fields
     * @return string
     */
    private static function formatFieldList(array $fields)
    {
        return $fields ? implode(', ', $fields) : 'none';
    }

    /**
     * @param bool $stored Return value of storeFeedCopy().
     * @return string
     */
    private static function feedCopyNote($stored)
    {
        if (!$stored) {
            return self::logNoteOrSupport();
        }

        return ' ' . JText::sprintf(
            'PLG_VMSHIPMENT_PACKETERY_CARRIER_DOWNLOADER_FEED_COPY_STORED',
            self::FEED_COPY_FILE,
            self::logViewLinkOpen(),
            '</a>'
        );
    }

    /**
     * @return string
     */
    private static function logNote()
    {
        if (!self::isLogWritable(self::LOG_CATEGORY_ERRORS)) {
            return '';
        }

        return ' ' . JText::sprintf(
            'PLG_VMSHIPMENT_PACKETERY_CARRIER_DOWNLOADER_LOG_DETAIL_STORED',
            self::logViewLinkOpen(),
            '</a>'
        );
    }

    /**
     * @return string
     */
    private static function logNoteOrSupport()
    {
        $note = self::logNote();

        return $note === ''
            ? ' ' . JText::_('PLG_VMSHIPMENT_PACKETERY_CARRIER_DOWNLOADER_CONTACT_SUPPORT')
            : $note;
    }

    /**
     * @return string
     */
    private static function logViewLinkOpen()
    {
        return '<a href="' . JUri::root() . self::LOG_VIEW_PATH . '">';
    }

    /**
     * The same fields missing for every carrier mean Packeta changed the feed, otherwise the data is damaged
     * @param array<int, array{id: mixed, missing: string[], nulled: string[]}> $invalidCarriers
     * @param int $carrierCount
     * @return bool
     */
    private static function isFeedFormatChange(array $invalidCarriers, $carrierCount)
    {
        if (count($invalidCarriers) !== $carrierCount) {
            return false;
        }

        foreach ($invalidCarriers as $carrier) {
            if ($carrier['nulled']) {
                return false;
            }

            if ($carrier['missing'] !== $invalidCarriers[0]['missing']) {
                return false;
            }
        }

        if (count($invalidCarriers[0]['missing']) === count(self::REQUIRED_CARRIER_FIELDS)) {
            return true;
        }

        return $carrierCount >= 2;
    }

    /**
     * @param string $json
     * @return bool Whether the copy is on disk.
     */
    private static function storeFeedCopy($json)
    {
        $header = sprintf("#<?php die('Forbidden.'); ?>\n# Downloaded %s\n", gmdate('Y-m-d H:i:s') . ' UTC');

        // The log view echoes a text/plain file unescaped, and a JSON parser reads \u003C back as `<`.
        $content = $header . str_replace('<', '\\u003C', $json);

        return @file_put_contents(self::logPath(self::FEED_COPY_FILE), $content) !== false;
    }

    /**
     * Uses the default of FormattedtextLogger::initFile(), so our files land next to the Joomla ones
     * @param string $file
     * @return string
     */
    private static function logPath($file)
    {
        return Factory::getApplication()->get('log_path', JPATH_ADMINISTRATOR . '/logs') . '/' . $file;
    }

    /**
     * Checked up front, because Joomla 5 ignores a failed write and Joomla 4 throws
     * @param string $category
     * @return bool
     */
    private static function isLogWritable($category)
    {
        $file = self::logPath("{$category}.php");

        if (file_exists($file)) {
            return is_writable($file);
        }

        $folder = dirname($file);

        // A missing folder is not a failure: the logger creates it in initFile().
        return !is_dir($folder) || is_writable($folder);
    }

    /**
     * @param string $message
     * @return string
     */
    private static function flattenForLog($message)
    {
        // strip_tags() keeps the contents of <style> and <script>, which would fill the preview with CSS.
        $withoutHeads = preg_replace('#<(script|style)\b[^>]*>.*?</\1>#is', ' ', $message);

        if ($withoutHeads !== null) {
            $message = $withoutHeads;
        }

        return trim(preg_replace('/\s+/', ' ', strip_tags($message)));
    }

    /**
     * @param string $text
     * @param int $length
     * @param string $marker Appended only when the text is shortened.
     * @return string
     */
    private static function truncateForLog($text, $length, $marker = '')
    {
        $cut = mb_substr($text, 0, $length, 'UTF-8');

        if ($marker !== '' && $cut !== $text) {
            $cut .= $marker;
        }

        return $cut;
    }

}
