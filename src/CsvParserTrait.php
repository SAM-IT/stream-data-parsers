<?php
namespace SamIT\Streams;


trait CsvParserTrait
{

    abstract protected function add(array $map, $record, $lineNr = null, $line = '');

    protected function read($stream, $length) {
        $result = '';
        while (!feof($stream) && is_string($result) && strlen($result) < $length) {
            $result .= fread($stream, $length - strlen($result));
        }
        return $result;
    }



    public function parseRealCsvLine(array $fields, array $map) {
        $result = [];
        foreach ($map['sourceMap'] as $source => $targets) {
            $raw = $fields[$source];
            foreach($targets as $target) {
                $result[$target] = $this->normalize($raw, $map['map'][$target]['type'], $fields);
            }
        }
        return $result;
    }

    public function parseFixedCsvLine($line, $map) {
        $result = [];
        foreach ($map['sourceMap'] as $source => $targets) {
            $column = $map['csvLayout'][$source];
            $raw = substr($line, $column['start'] - 1, $column['length']);
            foreach($targets as $target) {
                try {
                    //pass null since we do not have implemented $row yes to pass to normalize
                    $result[$target] = $this->normalize($raw, $map['map'][$target]['type'], null);
                } catch (\Exception $e) {
                    throw new \Exception("Error with line: '$line'", 0, $e);
                }
            }
        }
        return $result;
    }

    protected function importFixedCsvFile($fileName, $stream, $map) {
        $lineLength = end($map['csvLayout'])['start'] + end($map['csvLayout'])['length'] + 1;
        $count = 0;
        $start = microtime(true);
        while ((false !== $line = $this->read($stream, $lineLength)) && $line != '') {
            $record = $this->parseFixedCsvLine($line, $map);
            $this->add($map, $record, $count, $line);
            $count ++;
            if ($count % 10000 == 0) {
                $speed = $count * $lineLength / 1024 / (microtime(true) - $start);
                $this->log("Read: " . number_format($count * $lineLength / 1024, 0) . "kb at " . number_format($speed, 0) .  "kb/s\n");
            }
        }
    }

    protected function importRealCsvFile($fileName, $stream, $map) {
        $count = 0;
//        $start = microtime(true);
//        mb_internal_encoding('US-ASCII');
        $fieldNames = fgetcsv($stream, null, isset($map['csvDelimiter']) ?$map['csvDelimiter'] : null);
        $lineNr = 1;
        while ((false !== $line = fgetcsv($stream, null, isset($map['csvDelimiter']) ? $map['csvDelimiter'] : null)) && is_array($line)) {
            if ($line != [null]) {
                $record = $this->parseRealCsvLine(array_combine($fieldNames, $line), $map);
                $this->add($fileName, $record, $lineNr, array_combine($fieldNames, $line));
                $count ++;
                if ($count % 10000 == 0) {
                    $this->log('.');
                }
            }
            $lineNr++;
        }
    }
    protected function importCsvFile($fileName, $stream, $map) {
        if (isset($map['csvLayout'])) {
            $this->importFixedCsvFile($fileName, $stream, $map);
        } else {
            $this->importRealCsvFile($fileName, $stream, $map);
        }
    }
}