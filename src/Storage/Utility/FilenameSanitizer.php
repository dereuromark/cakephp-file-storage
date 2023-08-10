<?php

declare(strict_types = 1);

namespace FileStorage\Storage\Utility;

/**
 * Filename Sanitizer
 *
 * @link https://stackoverflow.com/questions/2021624/string-sanitizer-for-filename
 */
class FilenameSanitizer implements FilenameSanitizerInterface
{
    /**
     * @var array
     */
    protected array $config = [];

    /**
     * @var array
     */
    protected array $defaultConfig = [
        'lowercase' => false,
        'removeAllNonAlphaNumerical' => false,
        'beautify' => true,
        'enforceMaxLength' => true,
        'maxLength' => 255,
        'removeControlChars' => true,
        'removeNonPrintingChars' => true,
        'removeUriReservedChars' => false,
        'urlSafe' => false,
    ];

    /**
     * File system reserved characters
     *
     * @link https://en.wikipedia.org/wiki/Filename#Reserved_characters_and_words
     * @var string
     */
    protected string $filesystemReservedChars = '[<>:"/\\|?*]';

    /**
     * URL unsafe characters
     *
     * @link https://www.ietf.org/rfc/rfc1738.txt
     * @var string
     */
    protected string $urlUnsafeChars = '[{}^\~`]';

    /**
     * @link https://tools.ietf.org/html/rfc3986#section-2.2
     * @var string
     */
    protected string $uriReservedChars = '[#\[\]@!$&\'()+,;=]';

    /**
     * Non-printing characters DEL, NO-BREAK SPACE, SOFT HYPHEN
     *
     * @var string
     */
    protected string $nonPrintingChars = '[\x7F\xA0\xAD]';

    /**
     * Control Characters
     *
     * @link http://msdn.microsoft.com/en-us/library/windows/desktop/aa365247%28v=vs.85%29.aspx
     * @var string
     */
    protected string $controlChars = '[\x00-\x1F]';

    /**
     * @param array $config Config array
     */
    public function __construct(array $config = [])
    {
        $this->config = $config + $this->defaultConfig;
    }

    /**
     * @param string $filename Filename
     * @param string $replacement Replacement character
     *
     * @return string
     */
    protected function replaceCharacters(string $filename, string $replacement = '-'): string
    {
        $regex = [];
        $regex[] = $this->filesystemReservedChars;
        $regex[] = $this->config['urlSafe'] === true ? $this->urlUnsafeChars : '';
        $regex[] = $this->config['removeUriReservedChars'] === true ? $this->uriReservedChars : '';
        $regex[] = $this->config['removeNonPrintingChars'] === true ? $this->nonPrintingChars : '';
        $regex[] = $this->config['removeControlChars'] === true ? $this->controlChars : '';
        $regex = '~' . implode('|', array_filter($regex)) . '~x';

        return (string)preg_replace($regex, $replacement, $filename);
    }

    /**
     * @param string $string String
     *
     * @return string
     */
    public function sanitize(string $string): string
    {
        $string = $this->replaceCharacters($string);

        if ($this->config['lowercase'] === true) {
            $string = $this->stringToLowerCase($string);
        }

        if ($this->config['removeAllNonAlphaNumerical']) {
            $string = $this->removeAllNonAlphaNumerical($string);
        }

        if ($this->config['beautify'] === true) {
            $string = $this->beautify($string);
        }

        if ($this->config['enforceMaxLength'] === true) {
            $string = $this->enforceMaxLength($string, $this->config['maxLength']);
        }

        return $string;
    }

    /**
     * Enforces the max length of a filename
     *
     * @link http://en.wikipedia.org/wiki/Comparison_of_file_systems#Limits
     * @link http://serverfault.com/a/9548/44086
     *
     * @param string $filename Filename
     * @param int $maxLength Max length, 255 by default
     *
     * @return string
     */
    protected function enforceMaxLength(string $filename, int $maxLength = 255): string
    {
        $ext = pathinfo($filename, PATHINFO_EXTENSION);
        $length = $maxLength - ($ext ? strlen($ext) + 1 : 0);

        $filename = mb_strcut(
            pathinfo($filename, PATHINFO_FILENAME),
            0,
            $length,
            mb_detect_encoding($filename),
        );

        return $filename . ($ext ? '.' . $ext : '');
    }

    /**
     * Beautifies a filename to make it better to read
     *
     * "file name.zip" becomes "file-name.zip"
     * "file___name.zip" becomes "file-name.zip"
     * "file---name.zip" becomes "file-name.zip"
     * "file--.--.-.--name.zip" becomes "file.name.zip"
     * "file...name..zip" becomes "file.name.zip"
     * ".file-name.-" becomes "file-name"
     *
     * @link https://stackoverflow.com/questions/2021624/string-sanitizer-for-filename
     *
     * @param string $filename Filename
     *
     * @return string
     */
    public function beautify(string $filename): string
    {
        // reduce consecutive characters
        $filename = (string)preg_replace([
            // "file   name.zip" becomes "file-name.zip"
            '/ +/',
            // "file___name.zip" becomes "file-name.zip"
            '/_+/',
            // "file---name.zip" becomes "file-name.zip"
            '/-+/',
        ], '-', $filename);

        $filename = (string)preg_replace([
            // "file--.--.-.--name.zip" becomes "file.name.zip"
            '/-*\.-*/',
            // "file...name..zip" becomes "file.name.zip"
            '/\.{2,}/',
        ], '.', $filename);

        // lowercase for windows/unix interoperability http://support.microsoft.com/kb/100625
        $filename = mb_strtolower($filename, mb_detect_encoding($filename));

        // ".file-name.-" becomes "file-name"
        $filename = trim($filename, '.-');

        return $filename;
    }

    /**
     * @param string $string String
     *
     * @return string
     */
    protected function removeAllNonAlphaNumerical(string $string): string
    {
        $pathInfo = PathInfo::for($string);
        $string = (string)preg_replace('/[^a-zA-Z0-9]/', '', $pathInfo->filename());

        if (!$pathInfo->hasExtension()) {
            return $string;
        }

        return $string . '.' . $pathInfo->extension();
    }

    /**
     * @param string $string String
     * @param string $encoding Encoding
     *
     * @return string
     */
    protected function stringToLowerCase(
        string $string,
        string $encoding = 'UTF-8'
    ): string {
        return ((function_exists('mb_strtolower')) ?
            mb_strtolower($string, $encoding) :
            strtolower($string)
        );
    }
}
