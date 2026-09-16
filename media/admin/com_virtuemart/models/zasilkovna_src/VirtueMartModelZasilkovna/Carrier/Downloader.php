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

    // A real feed is hundreds of kB; the cap only stops a runaway body from filling the disk.
    const MAX_FEED_COPY_LENGTH = 5242880;

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

    // Warnings raised by the download, kept until its outcome decides how to log them.
    private static $fetchWarnings = array();

    // Categories whose write already failed, so no message promises a file that is not there.
    private static $logFailed = array();

    // Status line of the last response, kept so the detail in the log can name it.
    private static $statusLine = '';

    // Raw body of the last valid feed, kept for saveError(): support needs the data that failed.
    private static $lastFeed = '';

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

        $this->validateCarrierData($carriers, $json);
        self::removeFeedCopy();
        self::$lastFeed = $json;

        return $carriers;
    }

    /**
     * Turns a failure of the database write into a message. The cause can be the data as well as
     * the database itself, so the sentence names neither; the first line of the log says which.
     *
     * @param string $detail Technical cause, for the log only.
     * @return string
     */
    public static function saveError($detail)
    {
        self::logDetail('saving carriers failed: ' . $detail);

        // The shared opening sentence blames the download, so this message stands on its own.
        // The copy is stored even when the database is at fault: telling the two apart would mean
        // keeping a table of SQL error codes, and the log line right above says which it was.
        return JText::_('PLG_VMSHIPMENT_PACKETERY_CARRIER_DOWNLOADER_SAVE_ERROR')
            . self::feedCopyNote(self::storeFeedCopy(self::$lastFeed));
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

        self::$fetchWarnings = array();
        self::$statusLine = '';

        set_error_handler(
            function ($severity, $message) {
                // Only warnings describe a failed download; a deprecation would read as its cause.
                if ($severity !== E_WARNING) {
                    return false;
                }

                // Returning true also keeps it out of the host's PHP error log.
                self::$fetchWarnings[] = $message;

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

            // fopen(), not file_get_contents(): only this way is the status line available
            // ($http_response_header warns at compile time in PHP 8.4, on every request).
            $handle = $ctx === null ? fopen($url, 'r') : fopen($url, 'r', false, $ctx);

            if ($handle === false) {
                return false;
            }

            $meta = stream_get_meta_data($handle);
            self::$statusLine = isset($meta['wrapper_data'][0]) ? $meta['wrapper_data'][0] : '';
            $response = stream_get_contents($handle);
            fclose($handle);
        } finally {
            restore_error_handler();
        }

        return $response;
    }

    /**
     * Composes a message: the shared opening sentence, the cause, and the note pointing at
     * the log or the stored copy.
     *
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
     * The same, for an already composed sentence - one carrying the text the feed reported.
     *
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
     * The status line of the last response, ready to be appended to a log detail.
     *
     * @return string
     */
    private static function statusNote()
    {
        return self::$statusLine === '' ? '' : ' (' . self::$statusLine . ')';
    }

    /**
     * Logs the warnings held back during the download: as errors when it failed, else as warnings.
     *
     * @param bool $failed
     *
     * @return void
     */
    private static function flushFetchWarnings($failed)
    {
        foreach (self::$fetchWarnings as $warning) {
            self::logDetail($warning, $failed ? Log::ERROR : Log::WARNING);
        }

        self::$fetchWarnings = array();
    }

    /**
     * @param string $language
     * @return string
     * @throws DownloadException
     */
    private function downloadJson($language)
    {
        if (!$this->apiKey) {
            self::logDetail('API key is not set');

            throw new DownloadException(
                self::carrierError('PLG_VMSHIPMENT_PACKETERY_CARRIER_DOWNLOADER_API_KEY_MISSING')
            );
        }

        $url = sprintf(self::API_URL, $this->apiKey, $language);
        $response = $this->fetch($url);

        // An empty body is a failed download too, so its warning belongs in the file the
        // message names.
        self::flushFetchWarnings($response === false || trim($response) === '');

        if ($response === false) {
            self::logDetail('download failed' . self::statusNote());

            throw new DownloadException(
                self::carrierError('PLG_VMSHIPMENT_PACKETERY_CARRIER_DOWNLOADER_DOWNLOAD_ERROR', self::logNote())
            );
        }

        if (trim($response) === '') {
            self::logDetail('empty response body' . self::statusNote());

            throw new DownloadException(
                self::carrierError('PLG_VMSHIPMENT_PACKETERY_CARRIER_DOWNLOADER_DOWNLOAD_ERROR', self::logNote())
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
            self::logDetail(self::describeJsonFailure($json, $carriersData));
            $note = self::feedCopyNote(self::storeFeedCopy($json));

            throw new DownloadException(
                self::carrierError('PLG_VMSHIPMENT_PACKETERY_CARRIER_DOWNLOADER_JSON_ERROR', $note)
            );
        }

        // array_key_exists(), not isset(): a null error would fall through and read as a feed
        // format change, telling the client to update in vain.
        if (array_key_exists('error', $carriersData)) {
            if (!is_string($carriersData['error'])) {
                self::logDetail(sprintf('error field is %s, expected string', gettype($carriersData['error'])));

                throw new DownloadException(
                    self::carrierError(
                        'PLG_VMSHIPMENT_PACKETERY_CARRIER_DOWNLOADER_API_ERROR',
                        self::logNoteOrSupport()
                    )
                );
            }

            $apiError = trim($carriersData['error']);

            if ($apiError === '') {
                self::logDetail('error field is empty');

                throw new DownloadException(
                    self::carrierError(
                        'PLG_VMSHIPMENT_PACKETERY_CARRIER_DOWNLOADER_API_ERROR',
                        self::logNoteOrSupport()
                    )
                );
            }

            // Flattened before the cap, so the log keeps characters of text, not of markup.
            // Text mode both times - logDetail() flattens again and would cut at the first `<`.
            $flattened = self::flattenForLog($apiError, false);
            self::logDetail(
                self::truncateForLog($flattened, self::MAX_LOG_TEXT_LENGTH),
                Log::ERROR,
                false
            );

            // Substring, not equality: the API wraps the phrase in a whole sentence
            // ("Invalid API key. Please verify and try again").
            if (stripos($apiError, self::API_ERROR_INVALID_KEY) !== false) {
                throw new DownloadException(
                    self::carrierError('PLG_VMSHIPMENT_PACKETERY_CARRIER_DOWNLOADER_API_KEY_INVALID')
                );
            }

            // Every other cause is named only by the feed, so its text goes on screen as it came.
            throw new DownloadException(
                self::carrierErrorMessage(
                    JText::sprintf(
                        'PLG_VMSHIPMENT_PACKETERY_CARRIER_DOWNLOADER_API_ERROR_REPORTED',
                        htmlspecialchars(
                            self::truncateForLog($flattened, self::MAX_SCREEN_TEXT_LENGTH, '…'),
                            // Without ENT_SUBSTITUTE invalid UTF-8 returns an empty string, and
                            // the mbstring-less truncateForLog() fallback cuts mid-character.
                            ENT_QUOTES | ENT_SUBSTITUTE,
                            'UTF-8'
                        )
                    ),
                    self::logNoteOrSupport()
                )
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
    private static function describeJsonFailure($json, $decoded)
    {
        // Flattened before the cap, as with the API error above.
        $preview = self::truncateForLog(self::flattenForLog($json), self::MAX_LOG_TEXT_LENGTH);

        $reason = json_last_error() === JSON_ERROR_NONE
            ? sprintf('decoded as %s, expected array', gettype($decoded))
            : json_last_error_msg();

        return sprintf('%s%s. Data: %s', $reason, self::statusNote(), $preview);
    }

    /**
     * Validates data from API. One carrier missing one field invalidates the whole batch.
     *
     * @param array $carriers Data retrieved from API.
     * @param string $json Raw feed body, kept for support when the data is damaged.
     *
     * @return void
     * @throws DownloadException
     */
    private function validateCarrierData(array $carriers, $json)
    {
        if (empty($carriers)) {
            self::logDetail('feed returned no carriers');
            $note = self::feedCopyNote(self::storeFeedCopy($json));

            throw new DownloadException(
                self::carrierError('PLG_VMSHIPMENT_PACKETERY_CARRIER_DOWNLOADER_FEED_DATA_ERROR', $note)
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
                    // Present but unusable; told apart from missing, because the shape holds.
                    $nulled[] = $field;
                } elseif (!is_scalar($carrier[$field])) {
                    // Downstream treats these as strings - an array in `id` reaches
                    // unset($ids[$carrier['id']]) and kills the request with a TypeError.
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

        // Listed apart: that difference decides between "the feed changed" and "data damaged".
        self::logDetail(
            sprintf(
                'carrier %s has missing fields: %s; fields with no value: %s;'
                    . ' fields of an unusable type: %s (%d of %d carriers are invalid)',
                $invalidCarriers[0]['id'],
                self::formatFieldList($invalidCarriers[0]['missing']),
                self::formatFieldList($invalidCarriers[0]['nulled']),
                self::formatFieldList($invalidCarriers[0]['badTypes']),
                count($invalidCarriers),
                count($carriers)
            )
        );

        $note = self::feedCopyNote(self::storeFeedCopy($json));

        if (self::isFeedFormatChange($invalidCarriers, count($carriers))) {
            // Named here too: if the module is already current, support needs the feed body.
            throw new DownloadException(
                self::carrierError('PLG_VMSHIPMENT_PACKETERY_CARRIER_DOWNLOADER_FEED_VERSION_ERROR', $note)
            );
        }

        throw new DownloadException(
            self::carrierError('PLG_VMSHIPMENT_PACKETERY_CARRIER_DOWNLOADER_FEED_DATA_ERROR', $note)
        );
    }

    /**
     * @param array $fields
     *
     * @return string
     */
    private static function formatFieldList(array $fields)
    {
        return $fields ? implode(', ', $fields) : 'none';
    }

    /**
     * Points the client at the stored feed copy. When there is none, the log note - or support,
     * when even that is missing - takes its place, so we never name a file that is not there.
     *
     * @param bool $stored Return value of storeFeedCopy().
     *
     * @return string
     */
    private static function feedCopyNote($stored)
    {
        if (!$stored) {
            // No copy to send, so the log takes its place - and support when neither exists.
            return self::logNoteOrSupport();
        }

        $location = self::feedCopyLocation();

        if (!self::isLogViewLinkable(self::logPath(self::FEED_COPY_FILE))) {
            return ' ' . JText::sprintf(
                'PLG_VMSHIPMENT_PACKETERY_CARRIER_DOWNLOADER_FEED_COPY_STORED_NO_LINK',
                htmlspecialchars($location, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8')
            );
        }

        return ' ' . JText::sprintf(
            'PLG_VMSHIPMENT_PACKETERY_CARRIER_DOWNLOADER_FEED_COPY_STORED',
            htmlspecialchars($location, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'),
            self::logViewLinkOpen(),
            '</a>'
        );
    }

    /**
     * Named relative to the shop root: an absolute server path must not reach the screen. A log
     * folder outside the shop leaves only the file name, which the log view lists regardless.
     *
     * @return string
     */
    private static function feedCopyLocation()
    {
        $path = self::logPath(self::FEED_COPY_FILE);
        // A symlinked docroot or a relative log_path would otherwise not match the site root.
        $real = realpath(dirname($path));

        if (is_string($real)) {
            $path = $real . '/' . basename($path);
        }

        $path = str_replace('\\', '/', $path);
        $rootReal = realpath(JPATH_ROOT);
        $root = rtrim(str_replace('\\', '/', is_string($rootReal) ? $rootReal : JPATH_ROOT), '/') . '/';

        if (strpos($path, $root) === 0) {
            return substr($path, strlen($root));
        }

        return self::FEED_COPY_FILE;
    }

    /**
     * Whether the log listing will link the file: VirtueMart links only what fileinfo reports as
     * text/plain. A copy it cannot open is still named, just without the link.
     *
     * @param string $path
     *
     * @return bool
     */
    private static function isLogViewLinkable($path)
    {
        if (!class_exists('finfo')) {
            return true;
        }

        try {
            $finfo = new \finfo(FILEINFO_MIME);
            $mime = @$finfo->file($path);
        } catch (\Exception $e) {
            // A missing or broken MIME database: VirtueMart links the file, so the message does.
            return true;
        }

        if (!is_string($mime)) {
            return true;
        }

        return strpos($mime, 'text/plain') === 0;
    }

    /**
     * Points the client at the log with the technical cause. Empty when the write did not go
     * through - isLogWritable() before it, $logFailed after it, because Joomla 5 fails silently.
     *
     * @return string
     */
    private static function logNote()
    {
        if (!self::isLogWritable(self::LOG_CATEGORY_ERRORS) || isset(self::$logFailed[self::LOG_CATEGORY_ERRORS])) {
            return '';
        }

        return ' ' . JText::sprintf(
            'PLG_VMSHIPMENT_PACKETERY_CARRIER_DOWNLOADER_LOG_DETAIL_STORED',
            self::logViewLinkOpen(),
            '</a>'
        );
    }

    /**
     * The same, but never empty: the client has to be sent somewhere even with no log written.
     *
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
     * Opening tag of the link to VirtueMart -> Tools -> Log files. Both output paths render
     * HTML, so the same markup works in both.
     *
     * @return string
     */
    private static function logViewLinkOpen()
    {
        return '<a href="' . JUri::root() . self::LOG_VIEW_PATH . '">';
    }

    /**
     * Tells a new feed format from a damaged batch: the same fields missing for every carrier
     * mean Packeta changed the feed, missing only for some of them mean damaged data.
     *
     * @param array $invalidCarriers
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
            // Present but null, or of an unusable type, keeps the old shape - so this is
            // damaged data, and telling the client to update would not help.
            if ($carrier['nulled'] || $carrier['badTypes']) {
                return false;
            }

            if ($carrier['missing'] !== $invalidCarriers[0]['missing']) {
                return false;
            }
        }

        // Nothing the feed should contain is there, so the shape changed rather than the data -
        // what a single element can prove, e.g. a {"carriers":[...]} wrapper around the list.
        if (count($invalidCarriers[0]['missing']) === count(self::REQUIRED_CARRIER_FIELDS)) {
            return true;
        }

        return $carrierCount >= 2;
    }

    /**
     * Stores the downloaded feed next to the log files, so support can analyse it.
     * The file is overwritten on every failure, because appending would make it unreadable.
     *
     * @param string $json
     *
     * @return bool Whether the copy is on disk; the client is only told about it if it is.
     */
    private static function storeFeedCopy($json)
    {
        $path = self::logPath(self::FEED_COPY_FILE);
        $folder = dirname($path);

        // The folder normally exists because logDetail() has just made the logger create it.
        if (!is_dir($folder) && !@mkdir($folder, 0755, true) && !is_dir($folder)) {
            self::logDetail('feed copy folder could not be created: ' . $folder);

            return false;
        }

        // The date tells support which failure the copy belongs to.
        $header = sprintf("#<?php die('Forbidden.'); ?>\n# Downloaded %s\n", gmdate('Y-m-d H:i:s') . ' UTC');
        $length = strlen($json);

        if ($length > self::MAX_FEED_COPY_LENGTH) {
            $json = substr($json, 0, self::MAX_FEED_COPY_LENGTH);
            // Marked, otherwise support reads our own cut as a feed that arrived incomplete.
            $header .= sprintf(
                "# Truncated by the module to %d of %d bytes\n",
                self::MAX_FEED_COPY_LENGTH,
                $length
            );
        }

        // The `<` below is swapped for its JSON escape, so a parser reads the same string back
        // but nothing in the file can open a tag - and the log view echoes the lines of a file
        // it detects as text/plain unescaped.
        $content = $header . str_replace('<', '\\u003C', $json);
        $written = @file_put_contents($path, $content);

        // A full disk returns the bytes that fit, not false, so a half written copy would
        // otherwise be announced as complete.
        if ($written !== strlen($content)) {
            self::logDetail('feed copy could not be written to ' . $path);
            @unlink($path);

            return false;
        }

        // Logged too, so the log says which feed body belongs to the failure.
        self::logDetail('a copy of the feed was stored in ' . self::FEED_COPY_FILE);

        return true;
    }

    /**
     * Drops the copy left by an earlier failure, so support never analyses a feed
     * that the module has since downloaded successfully.
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
     * Builds a path in the Joomla log folder, with the same default as the Joomla logger
     * (FormattedtextLogger::initFile), so our files land next to the Joomla ones.
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
     * Writes the technical cause into a Packeta log file, which VirtueMart can show without FTP
     * access. Everything the logger can throw is swallowed - a failed log must not replace the
     * message with an error page.
     *
     * @param string $message
     * @param int $severity One of the Log severity constants.
     * @param bool $stripMarkup False for text the feed reported, where `<` is not markup.
     *
     * @return void
     */
    private static function logDetail($message, $severity = Log::ERROR, $stripMarkup = true)
    {
        $category = $severity === Log::WARNING ? self::LOG_CATEGORY_WARNINGS : self::LOG_CATEGORY_ERRORS;
        $message = self::flattenForLog($message, $stripMarkup);

        // The log view echoes every line into <li> unescaped, so a `<` that survived
        // flattenForLog() would be markup; ENT_SUBSTITUTE keeps invalid UTF-8 from dropping it.
        $message = htmlspecialchars($message, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');

        if (!self::isLogWritable($category)) {
            self::reportLogFailure($category, $message, 'the log file is not writable');

            return;
        }

        $writeError = '';
        set_error_handler(
            function ($severity, $message) use (&$writeError) {
                // A failed write is an E_WARNING; anything else says nothing about the file.
                if ($severity !== E_WARNING) {
                    return false;
                }

                $writeError = $message;

                return true;
            }
        );

        try {
            // Without the format the logger writes {CLIENTIP} into every line of a file the
            // client is asked to send to support.
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
            self::reportLogFailure($category, $message, $e->getMessage());
        } catch (\Throwable $e) {
            self::reportLogFailure($category, $message, $e->getMessage());
        } finally {
            restore_error_handler();
        }

        // Joomla 5 ignores the false that File::write() returns on a failed append, so the PHP
        // warning is the only sign the line is not in the file the message points at.
        if ($writeError !== '') {
            self::reportLogFailure($category, $message, $writeError);
        }
    }

    /**
     * Whether the Joomla logger can be expected to write the category file. Checked up front
     * because Joomla 5 swallows a failed write; Joomla 4 throws instead.
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
     * Last resort when the Joomla log is unavailable: the cause goes to the PHP error log.
     *
     * @param string $category
     * @param string $message The detail that was meant for the Joomla log.
     * @param string $reason
     *
     * @return void
     */
    private static function reportLogFailure($category, $message, $reason)
    {
        self::$logFailed[$category] = true;

        error_log(
            sprintf(
                'Packeta carrier downloader could not write to %s.php (%s): %s',
                $category,
                $reason,
                $message
            )
        );
    }

    /**
     * Strips markup and collapses whitespace so the log file stays plain text: VirtueMart's log
     * browser only links a file whose finfo MIME is text/plain.
     *
     * @param string $message
     * @param bool $stripMarkup False for text the feed reported, where `<` is not markup.
     *
     * @return string
     */
    private static function flattenForLog($message, $stripMarkup = true)
    {
        // Cut first: strip_tags() over a whole response body would hit the memory limit. Well
        // past MAX_LOG_TEXT_LENGTH, so the title of an HTML error page still fits.
        if (strlen($message) > self::MAX_FLATTEN_LENGTH) {
            $message = substr($message, 0, self::MAX_FLATTEN_LENGTH);
        }

        // strip_tags() keeps the contents of <style> and <script>, which would fill the preview
        // with a stylesheet. A null return means the pattern gave up; the original is better.
        $withoutHeads = preg_replace('#<(script|style)\b[^>]*>.*?</\1>#is', ' ', $message);

        if ($withoutHeads !== null) {
            $message = $withoutHeads;
        }

        if ($stripMarkup) {
            $message = strip_tags($message);
        } else {
            // Text mode: strip_tags() would eat everything from a `<` to the next `>`, so
            // "weight <5 kg not allowed" arrives as "weight ". Only a real tag goes.
            $withoutTags = preg_replace('#<!--.*?-->|</?[a-zA-Z][^>]*>#s', ' ', $message);

            if ($withoutTags !== null) {
                $message = $withoutTags;
            }
        }

        // Whitespace first: the class below covers tabs and newlines as well and would delete
        // them instead of letting the collapse turn them into a space. No /u modifier, it would
        // return null on invalid UTF-8. One control byte left in makes the whole log file read
        // as octet-stream and VirtueMart stops linking it for good, the log being append-only.
        $message = preg_replace('/\s+/', ' ', $message);

        return trim(preg_replace('/[[:cntrl:]]/', '', $message));
    }

    /**
     * Cuts a text down for the log. mb_substr() keeps a multibyte character whole; mbstring is
     * not required, so the fallback costs one damaged character instead of a fatal error.
     * $marker is appended only when the text was actually shortened, so the client can tell
     * a cut-off message from one that simply ends there.
     *
     * @param string $text
     * @param int $length
     * @param string $marker
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
