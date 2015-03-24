<?php

namespace PM\PrettyLog;

class JSONFormatter {

    private $keyPrefix = '';
    private $keySuffix = '';
    private $scalarPrefix = '';
    private $scalarSuffix = '';
    private $stringPrefix = '';
    private $stringSuffix = '';

    /** @var int */
    private $nestedIndent;

    /** @var int */
    private $basePadding;

    public function __construct($indent = 0, $basePadding = 0)
    {
        $this->nestedIndent = $indent;
        $this->basePadding = $basePadding;
    }


    public function setKeyColor($keyPrefix, $keySuffix)
    {
        $this->keyPrefix = $keyPrefix;
        $this->keySuffix = $keySuffix;
        return $this;
    }

    public function setScalarColor($scalarPrefix, $scalarSuffix)
    {
        $this->scalarPrefix = $scalarPrefix;
        $this->scalarSuffix = $scalarSuffix;
        return $this;
    }

    public function setStringColor($stringPrefix, $stringSuffix)
    {
        $this->stringPrefix = $stringPrefix;
        $this->stringSuffix = $stringSuffix;
        return $this;
    }

    public function formatJSONString($json_string)
    {
        $data = json_decode($json_string, true);
        if ($data === null) {
            // decoding error, leave string as is
            return $json_string;
        } else {
            return $this->formatData(null, $data, $this->basePadding);
        }
    }

    public function formatJSON($data)
    {
        return $this->formatData(null, $data, $this->basePadding);
    }

    private function formatData($key, $value, $padding)
    {
        $out = str_repeat(' ', $padding);
        if (!is_null($key)) {
            $out .= $this->keyPrefix.'"'.$key.'"'.$this->keySuffix.($padding ? ': ' : ':');
        }
        if (is_array($value)) {
            if (empty($value)) {
                $out .= '[]';
            } else {
                $parts = array();
                if (array_keys($value) !== range(0, count($value) - 1)) {
                    // associative
                    $braceLeft  = '{';
                    $braceRight = '}';
                    foreach ($value as $key2 => $value2) {
                        $parts[] = $this->formatData($key2, $value2, $padding + $this->nestedIndent);
                    }
                } else {
                    // indexed
                    $braceLeft  = '[';
                    $braceRight = ']';
                    foreach ($value as $value2) {
                        $parts[] = $this->formatData(null, $value2, $padding + $this->nestedIndent);
                    }
                }
                if ($padding) {
                    $out .= $braceLeft.PHP_EOL.implode(','.PHP_EOL, $parts).PHP_EOL.str_repeat(' ', $padding).$braceRight;
                } else {
                    $out .= $braceLeft.implode(',', $parts).$braceRight;
                }
            }
        } elseif (is_string($value)) {
            $out .= $this->stringPrefix.'"'.str_replace('"', '\\"', $value).'"'.$this->stringSuffix;
        } elseif (is_null($value)) {
            $out .= 'null';
        } else {
            if (is_bool($value)) {
                $value = $value ? 'true' : 'false';
            }
            $out .= $this->scalarPrefix.$value.$this->scalarSuffix;
        }
        return $out;
    }

}
