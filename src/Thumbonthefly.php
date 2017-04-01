<?php

namespace Osmancode\Thumbonthefly;

use Intervention\Image\Exception\InvalidArgumentException;
use Intervention\Image\ImageManager;

class Thumbonthefly
{
    /** @var  string Original Images Source */
    private static $uploadsDir;
    /** @var string Thumbnails Cache Directory Name */
    public $thumbnailCacheDirName = '.thumbnails';
    /** @var array Array Containg a white list of allowed sizes. */
    public $sizesWhiteList = ['70x70'];
    /** @var int Max allowed thumbnail width */
    public $maxWidth = 2000;
    /** @var int Max allowed thumbnail height */
    public $maxHeight = 2000;
    /** @var int Current thumbnail Width */
    private $_w;
    /** @var int Current thumbnail Height */
    private $_h;
    /** @var string Current image relative path */
    private $_img;
    /** @var string Source Image absolute path */
    private $_srcImg;
    /** @var string destination thumbnail absolute path */
    private $_distImg;

    public static function init($uploadsDir, array $config = array())
    {
        self::$uploadsDir = $uploadsDir;

        $uri = $_SERVER['REQUEST_URI'];
        $currentDir = dirname($_SERVER['SCRIPT_NAME']);
        $uri = ltrim($uri, $currentDir);

        preg_match('/(\d+)\/(\d+)\/(.*)?/', $uri, $matches);

        if (count($matches) == 4) {
            new self($matches[1], $matches[2], $matches[3], $config);
        }

        throw new InvalidArgumentException();
    }

    /** @var array Config list that user can override */
    protected $_config = array(
        'maxWidth',
        'maxHeight',
        'thumbnailCacheDirName',
        'sizesWhiteList'
    );

    private function __construct($w, $h, $img, array $config)
    {
        $this->mergeConfig($config);

        $this->_w = $w;
        $this->_h = $h;
        $this->_img = $img;

        $srcImg = $this->getSrcImg();
        $distImg = $this->getDistImg();

        if ($this->getSizeOk()) {
            // thumb already exists
            if (is_file($distImg)) {
                return $this->renderImage($distImg);
            }

            $manager = new ImageManager();
            $resource = $manager->make($srcImg)->fit($w, $h)->save($distImg);

            return $this->renderImage($resource->basePath());
        } else {
            return $this->renderImage($srcImg);
        }
    }

    private function mergeConfig(array $config)
    {
        foreach ($this->_config as $prop) {
            if (isset($config[$prop])) {
                $this->$prop = $config[$prop];
            }
        }
    }

    private function getSizeOk()
    {
        if (in_array("{$this->_w}x{$this->_h}", $this->sizesWhiteList)) {
            return true;
        }
        if ($this->_w <= $this->maxWidth && $this->_h <= $this->maxHeight) {
            return true;
        }
        return false;
    }

    private function getSrcImg()
    {
        if ($this->_srcImg === NULL) {
            $this->_srcImg = self::$uploadsDir . "/{$this->_img}";

            // src file not exists anymore
            if (!is_file($this->_srcImg)) {
                throw new \Intervention\Image\Exception\NotFoundException;
            }
        }
        return $this->_srcImg;
    }

    private function getDistImg()
    {
        if ($this->_distImg === NULL) {
            $srcImg = $this->getSrcImg();

            $mtime = filemtime($srcImg);
            $hashComponents = [$mtime, $srcImg, $this->_w, $this->_h];
            $hash = hash('md4', implode('#', $hashComponents));
            $distFileName = "{$hash}." . pathinfo($srcImg, PATHINFO_EXTENSION);

            $this->_distImg = self::$uploadsDir . "/{$this->thumbnailCacheDirName}/{$distFileName}";
        }
        return $this->_distImg;
    }

    private function renderImage($img)
    {
        $imgModificationTime = filemtime($img);

        $imgModificationTimeAsString = date('D, d M Y H:i:s ', $imgModificationTime) . 'GMT';

        $ifModifiedSince = isset($_SERVER['HTTP_IF_MODIFIED_SINCE']) ? strtotime($_SERVER['HTTP_IF_MODIFIED_SINCE']) : false;
        $ifNoneMatch = isset($_SERVER['HTTP_IF_NONE_MATCH']) ? $_SERVER['HTTP_IF_NONE_MATCH'] : false;

        // Always send those headers
        $oneMonthTime = (3600 * 24 * 30);
        header('Last-Modified: ' . gmdate('D, d M Y H:i:s \G\M\T', $imgModificationTime));

        if (
        ($ifModifiedSince !== false && $ifModifiedSince <= $imgModificationTime)
        ) {
            header('HTTP/1.1 304 Not Modified');
        } else {
            header('Expires: ' . gmdate('D, d M Y H:i:s \G\M\T', ($imgModificationTime + $oneMonthTime)));
            header('Cache-Control:public, max-age=' . $oneMonthTime);

            $imgInfo = getimagesize($img);


            header("Content-type: {$imgInfo['mime']}");
            header('Content-Length: ' . filesize($img));
            readfile($img);
        }

        exit();

    }
}
