<?php

namespace Osmancode\Thumbonthefly;
use Intervention\Image\Exception\InvalidArgumentException;
use Intervention\Image\ImageManager;

class Thumbonthefly
{
    private static $uploadsDir;
    public $sizesWhiteList = ['70x70'];
    public $maxWidth = 2000;
    public $maxHeight = 2000;
    private $_w;
    private $_h;
    private $_img;
    private $_srcImg;
    private $_distImg;

    public static function init($uploadsDir)
    {
        self::$uploadsDir = $uploadsDir;

        $uri = $_SERVER['REQUEST_URI'];
        $currentDir = dirname($_SERVER['SCRIPT_NAME']);
        $uri = ltrim($uri, $currentDir);

        preg_match('/(\d+)\/(\d+)\/(.*)?/', $uri, $matches);

        if (count($matches) == 4) {
            new self($matches[1], $matches[2], $matches[3]);
        }

        throw new InvalidArgumentException();
    }

    private function __construct($w, $h, $img)
    {
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

            $this->_distImg = self::$uploadsDir . "/.thumbnails/{$distFileName}";
        }
        return $this->_distImg;
    }

    private function renderImage($img)
    {
        $imgModificationTime = filemtime($img);

        $imgModificationTimeAsString = date('D, d M Y H:i:s ', $imgModificationTime) . 'GMT';
        $etag = hash('md4', $imgModificationTime);

        $ifModifiedSince = isset($_SERVER['HTTP_IF_MODIFIED_SINCE']) ? strtotime($_SERVER['HTTP_IF_MODIFIED_SINCE']) : false;

        $ifNoneMatch = isset($_SERVER['HTTP_IF_NONE_MATCH']) ? $_SERVER['HTTP_IF_NONE_MATCH'] : false;

        if (
            (($ifNoneMatch && $ifNoneMatch == $etag) || (!$ifNoneMatch)) &&
            ($ifModifiedSince !== false && $ifModifiedSince == $imgModificationTime)
        ) {
            header('HTTP/1.1 304 Not Modified');
            exit();
        } else {
            header('Last-Modified: ' . gmdate('D, d M Y H:i:s \G\M\T', $imgModificationTime));
            header("ETag: $etag");

            $imgInfo = getimagesize($img);


            header("Content-type: {$imgInfo['mime']}");
            header('Content-Length: ' . filesize($img));
            readfile($img);
            exit();
        }
    }
}
