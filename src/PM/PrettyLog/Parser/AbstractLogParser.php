<?php

namespace PM\PrettyLog\Parser;

use Psr\Log\LogLevel;

abstract class AbstractLogParser
{
    public static $LEVELS_ORDER = array(
        LogLevel::DEBUG,
        LogLevel::INFO,
        LogLevel::NOTICE,
        LogLevel::WARNING,
        LogLevel::ERROR,
        LogLevel::CRITICAL,
        LogLevel::ALERT,
        LogLevel::EMERGENCY,
    );

    /** @var array */
    protected $recognizedParts;

    /** @var string */
    protected $level = '';

    /** @var int */
    protected $timestamp = 0;

    /**
     * @return string
     */
    public function getLevel()
    {
        return $this->level;
    }

    /**
     * @param string $minLevel any of the LogLevel::* constants
     *
     * @return bool
     */
    public function isAtOrAboveLevel($minLevel)
    {
        $nMinLevel = array_search($minLevel, self::$LEVELS_ORDER, true);
        if ($nMinLevel === false) {
            throw new \InvalidArgumentException("Invalid level: $minLevel");
        }
        $nCurrentLevel = array_search($this->level, self::$LEVELS_ORDER, true);
        return $nCurrentLevel !== false && $nMinLevel <= $nCurrentLevel;
    }

    /**
     * @return int
     */
    public function getTimestamp()
    {
        return $this->timestamp;
    }

    /**
     * @param string $line
     *
     * @return bool
     */
    public function parseLine($line)
    {
        $this->recognizedParts = $this->doParseLine($line);
        return !empty($this->recognizedParts);
    }

    /**
     * @param string $line
     * @return array|null
     */
    abstract protected function doParseLine($line);

    /**
     * @return string
     */
    abstract public function colorizeLine();

}
