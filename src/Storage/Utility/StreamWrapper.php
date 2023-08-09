<?php
// phpcs:ignoreFile

/**
 * This file has been taken from guzzle/psr7 so that the library can be used
 * without the guzzle dependency.
 *
 * @copyright Copyright (c) 2015 Michael Dowling
 * @author    Michael Dowling <mtdowling@gmail.com>
 * @link      https://github.com/mtdowling
 * @license   https://opensource.org/licenses/MIT MIT License
 */

declare(strict_types=1);

namespace FileStorage\Storage\Utility;

use InvalidArgumentException;
use Psr\Http\Message\StreamInterface;

use function \FileStorage\Storage\fopen;

/**
 * Converts Guzzle streams into PHP stream resources.
 */
class StreamWrapper
{
    /** @var resource */
    public $context;

    /** @var StreamInterface */
    private StreamInterface $stream;

    /** @var string r, r+, or w */
    private string $mode;

    /**
     * Returns a resource representing the stream.
     *
     * @param StreamInterface $stream The stream to get a resource for
     *
     * @return resource
     * @throws \InvalidArgumentException if stream is not readable or writable
     */
    public static function getResource(StreamInterface $stream)
    {
        self::register();

        if ($stream->isReadable()) {
            $mode = $stream->isWritable() ? 'r+' : 'r';
        } elseif ($stream->isWritable()) {
            $mode = 'w';
        } else {
            throw new InvalidArgumentException(
                'The stream must be readable, writable, or both.'
            );
        }

        $result = fopen('guzzle://stream', $mode, false, self::createStreamContext($stream));

        return $result;
    }

    /**
     * Creates a stream context that can be used to open a stream as a php stream resource.
     *
     * @param StreamInterface $stream
     *
     * @return resource
     */
    public static function createStreamContext(StreamInterface $stream)
    {
        return \stream_context_create([
            'guzzle' => ['stream' => $stream]
        ]);
    }

    /**
     * Registers the stream wrapper if needed
     *
     * @return void
     */
    public static function register()
    {
        if (!in_array('guzzle', stream_get_wrappers(), true)) {
            \stream_wrapper_register('guzzle', __CLASS__);
        }
    }

    /**
     * @param string $path
     * @param string $mode
     * @param array $options
     * @param string $opened_path
     *
     * @return bool
     */
    public function stream_open($path, $mode, $options, &$opened_path)
    {
        $options = stream_context_get_options($this->context);

        if (!isset($options['guzzle']['stream'])) {
            return false;
        }

        $this->mode = $mode;
        $this->stream = $options['guzzle']['stream'];

        return true;
    }

    /**
     * @param int $count
     *
     * @return string
     */
    public function stream_read($count)
    {
        return $this->stream->read($count);
    }

    /**
     * @param string $data
     *
     * @return int
     */
    public function stream_write($data)
    {
        return (int) $this->stream->write($data);
    }

    /**
     * @return int
     */
    public function stream_tell()
    {
        return $this->stream->tell();
    }

    /**
     * @return bool
     */
    public function stream_eof()
    {
        return $this->stream->eof();
    }

    /**
     * @param int $offset
     * @param int $whence
     *
     * @return bool
     */
    public function stream_seek($offset, $whence)
    {
        $this->stream->seek($offset, $whence);

        return true;
    }

    /**
     * @param int $cast_as
     *
     * @return resource|null
     */
    public function stream_cast($cast_as)
    {
        $stream = clone($this->stream);

        return $stream->detach();
    }

    /**
     * @return array
     */
    public function stream_stat()
    {
        static $modeMap = [
            'r'  => 33060,
            'rb' => 33060,
            'r+' => 33206,
            'w'  => 33188,
            'wb' => 33188
        ];

        return [
            'dev'     => 0,
            'ino'     => 0,
            'mode'    => $modeMap[$this->mode],
            'nlink'   => 0,
            'uid'     => 0,
            'gid'     => 0,
            'rdev'    => 0,
            'size'    => $this->stream->getSize() ?: 0,
            'atime'   => 0,
            'mtime'   => 0,
            'ctime'   => 0,
            'blksize' => 0,
            'blocks'  => 0
        ];
    }

    /**
     * @param string $path
     * @param int $flags
     *
     * @return int[]
     */
    public function url_stat($path, $flags)
    {
        return [
            'dev'     => 0,
            'ino'     => 0,
            'mode'    => 0,
            'nlink'   => 0,
            'uid'     => 0,
            'gid'     => 0,
            'rdev'    => 0,
            'size'    => 0,
            'atime'   => 0,
            'mtime'   => 0,
            'ctime'   => 0,
            'blksize' => 0,
            'blocks'  => 0
        ];
    }
}
