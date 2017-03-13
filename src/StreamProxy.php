<?php


namespace SamIT\Streams;


class StreamProxy
{
    private static $protocol = 'StreamProxy';
    private static $streams = [];
    private static $registered = false;

    private $stream;

    public static function register()
    {
        if (!self::$registered) {
            if (!stream_register_wrapper(self::$protocol, __CLASS__, STREAM_IS_URL)) {
                throw new \Exception("Failed to register protocol.");
            }
            self::$registered = true;
        }
    }

    public static function to_uri($stream) {
        self::register();
        self::$streams[] = $stream;
        return "StreamProxy://" . (count(self::$streams) - 1);
    }

    /* Properties */
    public $context;

    /* Methods */
    public function __construct()
    {

    }

    public function stream_open($path, $mode, $options, &$opened_path)
    {
        $url = parse_url($path);
        if (isset(self::$streams[$url['host']])) {
            $this->stream = self::$streams[$url['host']];
        }

        return isset($this->stream);
    }

    public function stream_eof() {
        return feof($this->stream);
    }

//    public function stream_metadata($path, $option, $value) {
//        var_dump($path, $option, $value);
//        die();
//    }
//    public function dir_closedir ( void )
//    public function dir_opendir ( string $path , int $options )
//    public function dir_readdir ( void )
//    public function dir_rewinddir ( void )
//    public function mkdir ( string $path , int $mode , int $options )
//    public function rename ( string $path_from , string $path_to )
//    public function rmdir ( string $path , int $options )
//    public function stream_cast ( int $cast_as )
//    public function stream_close ( void )
//    public function stream_eof ( void )
//    public function stream_flush ( void )
//    public function stream_lock ( int $operation )
//    public function stream_metadata ( string $path , int $option , mixed $value )
//    public function stream_open ( string $path , string $mode , int $options , string &$opened_path )

//    public function stream_seek ( int $offset , int $whence = SEEK_SET )
//    public function stream_set_option ( int $option , int $arg1 , int $arg2 )
//    public function array stream_stat ( void )
//    public function stream_tell ( void )
//    public function stream_truncate ( int $new_size )
//    public function stream_write ( string $data )
//    public function unlink ( string $path )

    public function url_stat ($path, $flags)
    {
        $url = parse_url($path);

        if (false === $result = fstat(self::$streams[$url['host']])) {
            $result = [];


        }
        return $result;

    }

    public function stream_seek($offset, $whence)
    {
        return fseek($this->stream, $offset, $whence);
    }

    public function stream_read($count)
    {
        return fread($this->stream, $count);
    }

}