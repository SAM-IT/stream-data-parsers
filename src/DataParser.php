<?php
namespace SamIT\Streams;


class DataParser {
    use NormalizeTrait;
    use XmlParserTrait;
    use CsvParserTrait;

    private $maps = [];
    private $files = [];

    /**
     * @var \Closure
     */
    public $logCallback;

    /**
     * @var \Closure
     */
    public $callback;

    /**
     * @var callable
     */
    public $progressCallback;
    /**
     *
     * @var \Iterator
     */
    private $iterator;

    public function __construct($config = [])
    {
        $this->logCallback = function($text) {
            echo $text;
        };

        foreach($config as $key => $value) {
            $this->$key = $value;
        }

        // This approach will keep working if the parsers' callback function is changed.
        $this->progressCallback = new ProgressPrinter(function($text) {
            $this->log($text);
        });
        $this->init();
    }

    public function init() {

        foreach($this->maps as $map => &$config) {
            $config['name'] = isset($config['postfix']) ? $map . $config['postfix'] : $map;
            // Init source map.
            $config['sourceMap'] = [];
            foreach($config['map'] as $target => &$fieldConfig) {
                // Normalize config.
                if (isset($fieldConfig[0]) && !isset($fieldConfig['source'])) {
                    $fieldConfig = [
                        'source' => $fieldConfig[0],
                        'type' => $fieldConfig[1],
                    ];
                }
                // Map source to targets.
                if (!isset($fieldConfig['source'])) {
                    throw new \UnexpectedValueException("Field config must contain a `source` key");
                }
                // Defaults.
                $fieldConfig = array_merge([
                    'multiple' => 'array'
                ], $fieldConfig);

                $config['sourceMap'][$fieldConfig['source']][] = $target;
            }
        }
        if (is_array($this->files)) {
            $this->iterator = new \ArrayIterator($this->files);
        } elseif (is_string($this->files)) {
            $this->iterator = new \GlobIterator($this->files);
        }
    }

    protected function log(string $text)
    {
        $callback = $this->logCallback;
        return $callback($text);
    }

    protected function progress(float $progress, string $text)
    {
        $callback = $this->progressCallback;
        return $callback($progress, $text);
    }

    /**
     * Hashes a record by md5 hashing all fields in alphabetical order glued with '.'.
     */
    protected function hash(array $record)
    {
        ksort($record);
        $values = [];
        foreach ($record as $value) {
            if (is_bool($value)) {
                $values[] = $value ? 1 : 0;
            } elseif (is_int($value)) {
                $values[] = $value;
            } elseif (is_string($value)) {
                $values[] = $value;
            } else {
                throw new \Exception("Do not know how to handle $value for hashing.");
            }


        }
        return md5(implode('.', array_values($record)));
    }

    protected function add(array $map, array $record, int $lineNr = null, $raw = '') {
        if (isset($map['hash'])) {
            $record['hash'] = $this->hash($record);
        }
        if (!isset($map['validator']) || call_user_func($map['validator'], $record, $lineNr, $raw)) {
            $this->callback($map['name'], $record);
        }
    }

    /**
     * For each map, check if the fileExpression matches the filename. If so do import
     * @param $fileName
     * @return int|string
     */
    protected function getMapName($fileName)
    {
        foreach ($this->maps as $mapName => $config) {
            if (isset($config['fileExpression']) && preg_match($config['fileExpression'], $fileName)) {
                return $mapName;
            }
        }
    }
    
    
    

    protected function importFile($fileName, $stream, $extension = null) {
        if (!stream_is_local($stream)) {
            stream_set_blocking($stream, 0);
        }
        $ext = strtolower(isset($extension) ? $extension : substr($fileName, strrpos($fileName, '.') + 1));
        if ($ext === 'zip') {
            // A zip file is a directory, it does not use a map.
            return $this->importZipFile($fileName);
        }
        $this->log("Importing $fileName...");
        // Get the map.
        $mapName = $this->getMapName($fileName);
        if (!isset($mapName)) {
            $this->log(" No map; skipping.\n");
            return;
        } else {
            $this->log("\n");
        }


        $map = $this->maps[$mapName];

        // Use switch for better readability.
        switch($ext) {
            case 'xml':
                $this->importXmlFile($fileName, $stream, $map);
                break;
            case 'csv':
            case 'txt':
                $this->importCsvFile($fileName, $stream, $map);
                break;
            default:
                $func = "import" . ucfirst($ext) . 'File';
                if (!method_exists($this, $func)) {
                    throw new \Exception("Unsupported extension: $ext");
                };
                $this->$func($fileName, $stream);
        }
        $this->log(" OK\n");
    }


    protected function importZipFile($fileName) {
        $zip = new \ZipArchive();
        $zip->open($fileName);
        $count = $zip->numFiles;
        $base = basename($fileName);
        for ($i = 0; $i < $count; $i++) {
            $this->progress($i / $count, $base);
            $fileName = $zip->getNameIndex($i);
            $stream = $zip->getStream($fileName);
            $this->importFile($fileName, $stream);

        }
        $this->progress(1, $base);
    }

    public function run() {
        foreach($this->iterator as $entry) {
            if (is_string($entry)) {
                $this->importFile($entry, fopen($entry, 'rb'));
            } elseif ($entry instanceof \SplFileInfo) {
                $this->importFile($entry->getPathname(), fopen($entry->getPathname(), 'rb'));
            } elseif (is_array($entry)) {
                $this->importFile($entry['fileName'], fopen($entry['fileName'], 'rb'), ArrayHelper::getValue($entry, 'extension'));
            }
        }
    }

    protected function callback($map, $record)
    {
        call_user_func($this->callback, $map, $record);
    }
}
