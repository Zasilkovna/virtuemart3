<?php
namespace VirtueMartModelZasilkovna;

class FlashMessage {
    // message types taken from administrator/templates/isis/html/layouts/joomla/system/message.php
    const TYPE_ERROR = 'error';
    const TYPE_MESSAGE = 'message';
    const TYPE_NOTICE = 'notice';
    const TYPE_WARNING = 'warning';
    const AVAILABLE_TYPES = [
        self::TYPE_ERROR,
        self::TYPE_MESSAGE,
        self::TYPE_NOTICE,
        self::TYPE_WARNING,
    ];

    protected $message;
    protected $type;

    /**
     * @param string $message
     * @param string $type
     * @throws \InvalidArgumentException
     */
    public function __construct($message, $type = self::TYPE_MESSAGE) {
        $this->setMessage($message);
        $this->setType($type);
    }

    /**
     * @param string $type
     * @return void
     * @throws \InvalidArgumentException
     */
    public function setType($type) {
        if (!in_array($type, self::AVAILABLE_TYPES)) {
            throw new \InvalidArgumentException('Invalid message type');
        }
        $this->type = $type;
    }

    /**
     * @return string
     */
    public function getType()
    {
        return $this->type;
    }

    /**
     * @return string
     */
    public function getMessage()
    {
        return $this->message;
    }

    /**
     * @param string $message
     * @throws \InvalidArgumentException
     */
    public function setMessage($message)
    {
        if (empty($message)) {
            throw new \InvalidArgumentException('Message cannot be empty');
        }
        $this->message = $message;
    }

}
