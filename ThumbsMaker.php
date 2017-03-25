<?php



namespace osmancode\thumbonthefly;
require(__DIR__ . "/../../autoload.php");


use Intervention\Image\Exception\InvalidArgumentException;
use Intervention\Image\ImageManager;

class ThumbsMaker
{

    public $uploadsDir;
    public $sizesWhiteList = ['70x70'];
    public $maxWidth = 2000;
    public $maxHeight = 2000;


    private $_w;
    private $_h;
    private $_img;

    private $_srcImg;
    private $_distImg;

    public function initAndRun()
    {
        $uri = $_SERVER['REQUEST_URI'];
        $currentDir = dirname($_SERVER['SCRIPT_NAME']);
        $uri = ltrim($uri, $currentDir);

        preg_match('/(\d+)\/(\d+)\/(.*)?/', $uri, $matches);

        if (count($matches) == 4) {
            $this->run($matches[1], $matches[2], $matches[3]);
        }

        throw new InvalidArgumentException();
    }


    public function run($w, $h, $img)
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
            $this->_srcImg = "{$this->uploadsDir}/{$this->_img}";

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

            $this->_distImg = "{$this->uploadsDir}/.thumbnails/{$distFileName}";
        }
        return $this->_distImg;
    }

    private function renderImage($img)
    {
//        return \Yii::$app->response->xSendFile($img, $this->_img, ['inline' => true,]);
        return \Yii::$app->response->sendFile($img, $this->_img, ['inline' => true,]);
    }

}