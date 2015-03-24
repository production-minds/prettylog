<?php

namespace PM\PrettyLog;

use PM\PrettyLog\Parser\AbstractLogParser;
use PM\PrettyLog\Parser\MonologLineParser;
use PM\PrettyLog\Parser\PhpFpmLogParser;
use PM\PrettyLog\Parser\SyslogParser;
use Symfony\Component\Console\Command\Command;
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
        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $this->timezone = new \DateTimeZone(date_default_timezone_get());

        $now = time();
        $midnight = $now - ($now % (24*60*60));

        $fileName = $input->getArgument('file-name');
        $minLevel = $input->getOption('min-level');
        $noDots = $input->getOption('no-dots');
        $noGaps = $input->getOption('no-gaps');

        /** @var AbstractLogParser[] $parsers */
        $parsers = array();
        $jsonFormatter = new JSONFormatter(4, 10);
        $jsonFormatter
            ->setKeyColor('<fg=cyan>', '</fg=cyan>')
            ->setStringColor('<fg=green>', '</fg=green>')
            ->setScalarColor('<fg=green>', '</fg=green>');
        $parsers[] = new MonologLineParser($jsonFormatter);
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
                        $output->writeln("<fg=yellow>===== first entry ".self::prettyDate($timestamp, $now)." =====</fg=yellow>");
                    } else {
                        // show separators at time jumps
                        $timeGap = abs($timestamp - $previousTimestamp);
                        if ($timeGap > 60) {
                            $gapSec = $timeGap % 60;
                            $gapMin = ($timeGap - $gapSec) / 60;
                            $output->writeln("<fg=yellow>========== time-gap ${gapMin}m ${gapSec}s ==========</fg=yellow>");
                        } elseif ($timeGap > 30) {
                            $output->writeln("<fg=yellow>---------- time-gap ${timeGap}s ----------</fg=yellow>");
                        }
                    }
                    $previousTimestamp = $timestamp;
                }

                $dateTime = date($timestamp < $midnight ? 'Y-m-d H:i:s' : 'H:i:s', $timestamp);
                $output->writeln("[$dateTime] ".$parser->colorizeLine());
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