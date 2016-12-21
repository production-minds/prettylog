<?php

namespace PM\PrettyLog;

use PM\PrettyLog\Parser\AbstractLogParser;
use PM\PrettyLog\Parser\MonologJSONParser;
use PM\PrettyLog\Parser\MonologLineParser;
use PM\PrettyLog\Parser\PhpFpmLogParser;
use PM\PrettyLog\Parser\SyslogParser;
use Psr\Log\LogLevel;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Formatter\OutputFormatterStyle;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

class PrettyLogCommand extends Command
{

    protected function configure()
    {
        parent::configure();
        $levelOptions = strtolower(implode('|', AbstractLogParser::$LEVELS_ORDER));
        $levelDefault = reset(AbstractLogParser::$LEVELS_ORDER);
        $this
            ->setName('pm:pretty-log')
            ->setAliases(array('prettylog'))
            ->setDescription('Prettify and colorize monolog output.')
            ->addArgument('file-name',    InputArgument::OPTIONAL, "Path to the log file to dump.", "STDIN")
            ->addOption('min-level', 'l', InputOption::VALUE_OPTIONAL, "Output only messages at least this level ($levelOptions).", $levelDefault)
            ->addOption('no-dots',  null, InputOption::VALUE_NONE, "Suppress printing dots when log messages are omitted (see --min-level).")
            ->addOption('no-gaps',  null, InputOption::VALUE_NONE, "Suppress printing time gaps.")
            ->addOption('hilite',    'H', InputOption::VALUE_OPTIONAL, "Text to highlight in output (ANSI mode only). Use /…/ for regular expression.")
        ;
    }

    protected function initialize(InputInterface $input, OutputInterface $output)
    {
        $formatter = $output->getFormatter();
        $formatter->setStyle('message', new OutputFormatterStyle(null, null, array('bold')));
        $formatter->setStyle('separator', new OutputFormatterStyle('yellow'));
        $formatter->setStyle('hilite', new OutputFormatterStyle('black', 'yellow', array('blink')));
        $formatter->setStyle('json-key', new OutputFormatterStyle('cyan'));
        $formatter->setStyle('json-string', new OutputFormatterStyle('green'));
        $formatter->setStyle('json-scalar', new OutputFormatterStyle('green'));
        // define styles for all log levels
        $formatter->setStyle(LogLevel::DEBUG,      new OutputFormatterStyle('blue'));
        $formatter->setStyle(LogLevel::INFO,       new OutputFormatterStyle('white'));
        $formatter->setStyle(LogLevel::NOTICE,     new OutputFormatterStyle('magenta'));
        $formatter->setStyle(LogLevel::WARNING,    new OutputFormatterStyle('yellow', 'blue'));
        $formatter->setStyle(LogLevel::ERROR,      new OutputFormatterStyle('white', 'red'));
        $formatter->setStyle(LogLevel::CRITICAL,   new OutputFormatterStyle('yellow', 'red'));
        $formatter->setStyle(LogLevel::ALERT,      new OutputFormatterStyle('yellow', 'red', array('bold')));
        $formatter->setStyle(LogLevel::EMERGENCY,  new OutputFormatterStyle('yellow', 'red', array('blink')));
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $now = time();
        $midnight = $now - ($now % (24*60*60));

        $fileName = $input->getArgument('file-name');
        $minLevel = $input->getOption('min-level');
        $noDots = $input->getOption('no-dots');
        $noGaps = $input->getOption('no-gaps');

        $highlightText = $input->getOption('hilite');
        if ($highlightText) {
            if (preg_match('#^/.*/i?$#', $highlightText)) {
                // use highlight string as regular expression
                $highlightRegEx = $highlightText;
            } else {
                // use highlight string as plain text
                $highlightRegEx = '/'.preg_quote($highlightText, '/').'/';
            }
        } else {
            $highlightRegEx = null;
        }

        /** @var AbstractLogParser[] $parsers */
        $parsers = array();
        $jsonFormatter = new JSONFormatter(4, 10);
        $jsonFormatter
            ->setKeyColor('<json-key>', '</json-key>')
            ->setStringColor('<json-string>', '</json-string>')
            ->setScalarColor('<json-scalar>', '</json-scalar>');
        $parsers[] = new MonologLineParser($jsonFormatter);
        $parsers[] = new MonologJSONParser($jsonFormatter);
        $parsers[] = new SyslogParser();
        $parsers[] = new PhpFpmLogParser();

        $previousTimestamp = 0;
        $haveDots = 0;

        if ($fileName === 'STDIN') {
            $fileName = 'php://STDIN';
            if ($output->getVerbosity() > OutputInterface::VERBOSITY_NORMAL) {
                $output->writeln('Reading STDIN...');
            }
        }
        $f = fopen($fileName, 'r');
        if (!is_resource($f)) {
            throw new \RuntimeException("Failed to open file for reading: $fileName");
        }

        while (!feof($f)) {
            $line = trim(fgets($f));

            // try current line with all known parsers
            $parser = null;
            foreach ($parsers as $parser) {
                if ($parser->parseLine($line)) {
                    break;
                }
                $parser = null;
            }

            if (empty($parser)) {
                if ($haveDots) {
                    $output->writeln('');
                    $haveDots = 0;
                }

                // line not recognized by any parser, print as is...
                $output->writeln($line);

            } elseif ($parser->isAtOrAboveLevel($minLevel)) {
                if ($haveDots) {
                    $output->writeln('');
                    $haveDots = 0;
                }

                // print normalized date/time
                $timestamp = $parser->getTimestamp();

                if (!$noGaps) {
                    if ($previousTimestamp === 0) {
                        $output->writeln("<separator>===== first entry ".self::prettyDate($timestamp, $now)." =====</separator>");
                    } else {
                        // show separators at time jumps
                        $timeGap = abs($timestamp - $previousTimestamp);
                        if ($timeGap > 60) {
                            $gapSec = $timeGap % 60;
                            $gapMin = ($timeGap - $gapSec) / 60;
                            $output->writeln("<separator>========== time-gap ${gapMin}m ${gapSec}s ==========</separator>");
                        } elseif ($timeGap > 30) {
                            $output->writeln("<separator>---------- time-gap ${timeGap}s ----------</separator>");
                        }
                    }
                    $previousTimestamp = $timestamp;
                }

                $dateTime = date($timestamp < $midnight ? 'Y-m-d H:i:s' : 'H:i:s', $timestamp);
                $colorful = $parser->colorizeLine();
                if ($highlightRegEx) {
                    $colorful = preg_replace($highlightRegEx, '<hilite>$0</>', $colorful);
                }
                try {
                    $output->writeln("[$dateTime] $colorful");
                } catch (\Symfony\Component\Console\Exception\InvalidArgumentException $exc) {
                    // OutputFormatterStyleStack::pop() tends to throw this when the
                    // message contains incorrect styling (may happen due to highlights).
                    $output->write("[$dateTime] <error>{$exc->getMessage()}</error> ");
                    $output->writeln($colorful, OutputInterface::OUTPUT_RAW);
                }
            } elseif (!$noDots) {
                $output->write(".");
                $haveDots++;
            }
        }
        fclose($f);
    }

    /**
     * @param \DateTime|int $date
     * @param \DateTime|int $now
     * @return string like "2 days ago"
     */
    protected static function prettyDate($date, $now = null)
    {
        $ts = is_int($date) ? $date : $date->getTimestamp();
        if ($now == null) {
            $now = time();
        } elseif ($now instanceof \DateTime) {
            $now = $now->getTimestamp();
        }
        $seconds = $now - $ts;
        $in = $seconds >= 0 ? '' : 'in ';
        $ago = $seconds >= 0 ? ' ago' : '';
        if ($seconds < 0) {
            $seconds = abs($seconds);
        }
        if ($seconds < 60) {
            return $seconds <= 1 ? "just now" : "${in}${seconds} seconds${ago}";
        }
        $minutes = floor($seconds / 60);
        if ($minutes < 60) {
            return $minutes <= 1 ? "${in}a minute${ago}" : "${in}${minutes} minutes${ago}";
        }
        $hours = floor($minutes / 60);
        if ($hours < 24) {
            return $hours <= 1 ? "${in}an hour${ago}" : "${in}${hours} hours${ago}";
        }
        $days = floor($hours / 24);
        if ($days < 45) {
            return $days <= 1 ? "${in}a day${ago}" : "${in}${days} days${ago}";
        }
        $months = floor($days / (365/12));
        if ($months < 24) {
            return $months <= 1 ? "${in}a month${ago}" : "${in}${months} months${ago}";
        }
        $years = floor($months / 12);
        return "${in}${years} years${ago}";
    }
}