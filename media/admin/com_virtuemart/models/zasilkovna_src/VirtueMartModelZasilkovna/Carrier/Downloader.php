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

    // Enough to reach past the <head> of an HTML error page to the title that names the cause.
    const MAX_LOG_TEXT_LENGTH = 500;

    const MAX_SCREEN_TEXT_LENGTH = 200;

    const LOG_CATEGORY_ERRORS = 'packeta.errors';

    const LOG_CATEGORY_WARNINGS = 'packeta.warnings';

    const FEED_COPY_FILE = 'packeta.feed.php';

    // The log view has no ACL action of its own, so whoever can update carriers can open it.
    const LOG_VIEW_PATH = 'administrator/index.php?option=com_virtuemart&amp;view=log';

    // Cap on the text handed to strip_tags(), so the error path itself cannot exhaust memory.
    const MAX_FLATTEN_LENGTH = 65536;

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

    /** @var string */
    private $statusLine = '';

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
        $this->statusLine = '';

        $json = $this->downloadJson($lang);
        $carriers = $this->getFromJson($json);

        $this->validateCarrierData($carriers, $json);
        self::removeFeedCopy();

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

        // The shared opening sentence blames the download, so this message does not use it.
        return new DownloadException(
            JText::_('PLG_VMSHIPMENT_PACKETERY_CARRIER_DOWNLOADER_SAVE_ERROR') . self::feedCopyNote($stored),
            0,
            new \Exception($detail . self::feedCopyDetail($stored), 0, $cause)
        );
    }

    /**
     * Writes a technical detail into a Packeta log file, which VirtueMart shows under Logs
     *
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
        $message = htmlspecialchars(self::flattenForLog($detail, false), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');

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
        } catch (\Exception $e) {
            // A failed log must not replace the message with an error page.
        } catch (\Throwable $e) {
            // The same for PHP 7 errors.
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
                self::carrierError('PLG_VMSHIPMENT_PACKETERY_CARRIER_DOWNLOADER_URLFOPEN_ERROR')
            );
        }

        set_error_handler(
            function ($severity, $message) {
                // A deprecation would read as the cause of a failed download.
                if ($severity !== E_WARNING) {
                    return false;
                }

                // Returning true also keeps it out of the host's PHP error log.
                $this->fetchWarnings[] = $message;

                return true;
            }
        );

        $ctx = null;

        try {
            if (function_exists('stream_context_create')) {
                $ctx = stream_context_create(
                    array(
                        'http' => array(
                            'timeout' => 20,
                            'ignore_errors' => true, //to get API response although headers are not 200
                        )
                    )
                );
            }

            // fopen() gives the status line without $http_response_header, which PHP 8.4 deprecates.
            $handle = $ctx === null ? fopen($url, 'r') : fopen($url, 'r', false, $ctx);

            if ($handle === false) {
                return false;
            }

            $meta = stream_get_meta_data($handle);
            $this->statusLine = isset($meta['wrapper_data'][0]) ? $meta['wrapper_data'][0] : '';
            $response = stream_get_contents($handle);
            fclose($handle);
        } finally {
            restore_error_handler();
        }

        return $response;
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

        return $this->failure(
            self::carrierError($key, self::feedCopyNote($stored)),
            $detail . self::feedCopyDetail($stored)
        );
    }

    /**
     * @param string $key
     * @param string $note
     *
     * @return string
     */
    private static function carrierError($key, $note = '')
    {
        return self::carrierErrorMessage(JText::_($key), $note);
    }

    /**
     * @param string $sentence
     * @param string $note
     *
     * @return string
     */
    private static function carrierErrorMessage($sentence, $note = '')
    {
        return JText::_('PLG_VMSHIPMENT_PACKETERY_CARRIER_DOWNLOADER_FAILED') . ' ' . $sentence . $note;
    }

    /**
     * @return string
     */
    private function statusNote()
    {
        return $this->statusLine === '' ? '' : ' (' . $this->statusLine . ')';
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
                self::carrierError('PLG_VMSHIPMENT_PACKETERY_CARRIER_DOWNLOADER_API_KEY_MISSING'),
                'API key is not set'
            );
        }

        $url = sprintf(self::API_URL, $this->apiKey, $language);
        $response = $this->fetch($url);

        if ($response === false) {
            throw $this->failure(
                self::carrierError('PLG_VMSHIPMENT_PACKETERY_CARRIER_DOWNLOADER_DOWNLOAD_ERROR', self::logNote()),
                'download failed' . $this->statusNote()
            );
        }

        if (trim($response) === '') {
            throw $this->failure(
                self::carrierError('PLG_VMSHIPMENT_PACKETERY_CARRIER_DOWNLOADER_DOWNLOAD_ERROR', self::logNote()),
                'empty response body' . $this->statusNote()
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
                $this->describeJsonFailure($json, $carriersData),
                $json
            );
        }

        // array_key_exists(), not isset(): a null error would read as a feed format change.
        if (array_key_exists('error', $carriersData)) {
            if (!is_string($carriersData['error'])) {
                throw $this->failure(
                    self::carrierError(
                        'PLG_VMSHIPMENT_PACKETERY_CARRIER_DOWNLOADER_API_ERROR',
                        self::logNoteOrSupport()
                    ),
                    sprintf('error field is %s, expected string', gettype($carriersData['error']))
                );
            }

            $apiError = trim($carriersData['error']);

            if ($apiError === '') {
                throw $this->failure(
                    self::carrierError(
                        'PLG_VMSHIPMENT_PACKETERY_CARRIER_DOWNLOADER_API_ERROR',
                        self::logNoteOrSupport()
                    ),
                    'error field is empty'
                );
            }

            // Flattened before the cap, so the log keeps characters of text, not of markup.
            $flattened = self::flattenForLog($apiError, false);
            $detail = self::truncateForLog($flattened, self::MAX_LOG_TEXT_LENGTH);

            // Substring, not equality: the API wraps the phrase in a whole sentence.
            if (stripos($apiError, self::API_ERROR_INVALID_KEY) !== false) {
                throw $this->failure(
                    self::carrierError('PLG_VMSHIPMENT_PACKETERY_CARRIER_DOWNLOADER_API_KEY_INVALID'),
                    $detail
                );
            }

            throw $this->failure(
                self::carrierErrorMessage(
                    JText::sprintf(
                        'PLG_VMSHIPMENT_PACKETERY_CARRIER_DOWNLOADER_API_ERROR_REPORTED',
                        htmlspecialchars(
                            self::truncateForLog($flattened, self::MAX_SCREEN_TEXT_LENGTH, '…'),
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
     * @param mixed $decoded Result of json_decode() on $json.
     *
     * @return string
     */
    private function describeJsonFailure($json, $decoded)
    {
        $preview = self::truncateForLog(self::flattenForLog($json), self::MAX_LOG_TEXT_LENGTH);

        $reason = json_last_error() === JSON_ERROR_NONE
            ? sprintf('decoded as %s, expected array', gettype($decoded))
            : json_last_error_msg();

        return sprintf('%s%s. Data: %s', $reason, $this->statusNote(), $preview);
    }

    /**
     * Validates data from API.
     *
     * @param array<int, mixed> $carriers Data retrieved from API, each item validated as a carrier.
     * @param string $json Raw feed body, kept for support when the data is damaged.
     *
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
            $badTypes = array();

            foreach (self::REQUIRED_CARRIER_FIELDS as $field) {
                if (!is_array($carrier) || !array_key_exists($field, $carrier)) {
                    $missing[] = $field;
                } elseif ($carrier[$field] === null) {
                    $nulled[] = $field;
                } elseif (!is_scalar($carrier[$field])) {
                    // An array in `id` would end the request with a TypeError in unset($ids[$carrier['id']]).
                    $badTypes[] = $field;
                }
            }

            if ($missing || $nulled || $badTypes) {
                $invalidCarriers[] = array(
                    'id' => isset($carrier['id']) && is_scalar($carrier['id']) ? $carrier['id'] : '?',
                    'missing' => $missing,
                    'nulled' => $nulled,
                    'badTypes' => $badTypes,
                );
            }
        }

        if (!$invalidCarriers) {
            return;
        }

        // Listed apart, because that difference tells a feed format change from damaged data.
        $detail = sprintf(
            'carrier %s has missing fields: %s; fields with no value: %s;'
                . ' fields of an unusable type: %s (%d of %d carriers are invalid)',
            $invalidCarriers[0]['id'],
            self::formatFieldList($invalidCarriers[0]['missing']),
            self::formatFieldList($invalidCarriers[0]['nulled']),
            self::formatFieldList($invalidCarriers[0]['badTypes']),
            count($invalidCarriers),
            count($carriers)
        );

        if (self::isFeedFormatChange($invalidCarriers, count($carriers))) {
            // Named here too: if the module is already current, support needs the feed body.
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
     *
     * @return string
     */
    private static function formatFieldList(array $fields)
    {
        return $fields ? implode(', ', $fields) : 'none';
    }

    /**
     * @param bool $stored Return value of storeFeedCopy().
     *
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
     * @param bool $stored Return value of storeFeedCopy().
     *
     * @return string
     */
    private static function feedCopyDetail($stored)
    {
        return $stored
            ? '; a copy of the feed was stored in ' . self::FEED_COPY_FILE
            : '; the feed copy could not be stored in ' . self::logPath(self::FEED_COPY_FILE);
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
     *
     * @param array<int, array{id: scalar, missing: string[], nulled: string[], badTypes: string[]}> $invalidCarriers
     * @param int $carrierCount
     *
     * @return bool
     */
    private static function isFeedFormatChange(array $invalidCarriers, $carrierCount)
    {
        if (count($invalidCarriers) !== $carrierCount) {
            return false;
        }

        foreach ($invalidCarriers as $carrier) {
            // Null or an unusable type keeps the old shape, so an update of the module would not help.
            if ($carrier['nulled'] || $carrier['badTypes']) {
                return false;
            }

            if ($carrier['missing'] !== $invalidCarriers[0]['missing']) {
                return false;
            }
        }

        // No required field at all proves a changed shape even for one carrier, e.g. a {"carriers": [...]} wrapper.
        if (count($invalidCarriers[0]['missing']) === count(self::REQUIRED_CARRIER_FIELDS)) {
            return true;
        }

        return $carrierCount >= 2;
    }

    /**
     * Overwrites the copy on every failure, because appended bodies would no longer parse as JSON
     *
     * @param string $json
     *
     * @return bool Whether the copy is on disk.
     */
    private static function storeFeedCopy($json)
    {
        $path = self::logPath(self::FEED_COPY_FILE);
        $folder = dirname($path);

        if (!is_dir($folder) && !@mkdir($folder, 0755, true) && !is_dir($folder)) {
            return false;
        }

        // The date tells support which failure the copy belongs to.
        $header = sprintf("#<?php die('Forbidden.'); ?>\n# Downloaded %s\n", gmdate('Y-m-d H:i:s') . ' UTC');

        // The log view echoes a text/plain file unescaped, and a JSON parser reads < back as `<`.
        $content = $header . str_replace('<', '\\u003C', $json);
        $written = @file_put_contents($path, $content);

        // A full disk returns the bytes that fit, not false.
        if ($written !== strlen($content)) {
            @unlink($path);

            return false;
        }

        return true;
    }

    /**
     * Drops the copy of an earlier failure, so support never analyses a feed that later downloaded fine
     *
     * @return void
     */
    private static function removeFeedCopy()
    {
        $path = self::logPath(self::FEED_COPY_FILE);

        if (file_exists($path)) {
            @unlink($path);
        }
    }

    /**
     * Uses the default of FormattedtextLogger::initFile(), so our files land next to the Joomla ones
     *
     * @param string $file
     *
     * @return string
     */
    private static function logPath($file)
    {
        return Factory::getApplication()->get('log_path', JPATH_ADMINISTRATOR . '/logs') . '/' . $file;
    }

    /**
     * Checked up front, because Joomla 5 ignores a failed write and Joomla 4 throws
     *
     * @param string $category
     *
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
     * VirtueMart links a log file only when finfo reports it as text/plain, so the entry must stay plain text
     *
     * @param string $message
     * @param bool $stripMarkup False for text the feed reported, where `<` is not markup.
     *
     * @return string
     */
    private static function flattenForLog($message, $stripMarkup = true)
    {
        if (strlen($message) > self::MAX_FLATTEN_LENGTH) {
            $message = substr($message, 0, self::MAX_FLATTEN_LENGTH);
        }

        // strip_tags() keeps the contents of <style> and <script>, which would fill the preview with CSS.
        $withoutHeads = preg_replace('#<(script|style)\b[^>]*>.*?</\1>#is', ' ', $message);

        if ($withoutHeads !== null) {
            $message = $withoutHeads;
        }

        if ($stripMarkup) {
            $message = strip_tags($message);
        } else {
            // strip_tags() would cut "weight <5 kg not allowed" at the `<`, so only a real tag goes.
            $withoutTags = preg_replace('#<!--.*?-->|</?[a-zA-Z][^>]*>#s', ' ', $message);

            if ($withoutTags !== null) {
                $message = $withoutTags;
            }
        }

        $message = preg_replace('/\s+/', ' ', $message);

        // One control byte makes finfo read the append-only log as octet-stream, and VirtueMart stops linking it.
        return trim(preg_replace('/[[:cntrl:]]/', '', $message));
    }

    /**
     * Without mbstring the cut can damage one multibyte character instead of causing a fatal error
     *
     * @param string $text
     * @param int $length
     * @param string $marker Appended only when the text is shortened.
     *
     * @return string
     */
    private static function truncateForLog($text, $length, $marker = '')
    {
        if (function_exists('mb_substr')) {
            $cut = mb_substr($text, 0, $length, 'UTF-8');
        } else {
            $cut = substr($text, 0, $length);
        }

        if ($marker !== '' && $cut !== $text) {
            $cut .= $marker;
        }

        return $cut;
    }

}
