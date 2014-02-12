<?php
/**
 * Ryzom Map Tiles
 *
 * @author Meelis Mägi <nimetu@gmail.com>
 * @copyright (c) 2014 Meelis Mägi
 * @license http://opensource.org/licenses/LGPL-3.0
 */

namespace Bmsite\Maps\Tools;

use Bmsite\Maps\Tiles\TileStorageInterface;

/**
 * Class BaseTileGenerator
 */
class BaseTileGenerator
{
    /** @var bool */
    protected $debug = false;

    /** @var \Bmsite\Maps\Tiles\TileStorageInterface */
    protected $tileStorage;

    /**
     * @param TileStorageInterface $tileStorage
     */
    public function setTileStorage(TileStorageInterface $tileStorage)
    {
        $this->tileStorage = $tileStorage;
    }

    /**
     * @param bool $v
     */
    public function setDebug($v)
    {
        $this->debug = (bool)$v;
    }

    /**
     * Create new empty and transparent tile
     *
     * @param int $width
     * @param int $height
     *
     * @return resource
     */
    public function createTile($width = TileStorageInterface::TILE_SIZE, $height = TileStorageInterface::TILE_SIZE)
    {
        $out = imagecreatetruecolor($width, $height);
        $tc = imagecolorallocatealpha($out, 0, 0, 0, 127);
        imagefill($out, 0, 0, $tc);
        imagesavealpha($out, true);

        return $out;
    }

    /**
     * Load image from jpg or png file
     *
     * @param string $imgFile
     *
     * @return resource
     */
    public function loadImage($imgFile)
    {
        if (!file_exists($imgFile)) {
            return false;
        }
        if (substr($imgFile, -3) == 'jpg') {
            // reuse existing tile for background
            $out = @imagecreatefromjpeg($imgFile);
        } else {
            $out = @imagecreatefrompng($imgFile);
        }
        return $out;
    }

    /**
     * @param resource $img
     * @param string $imgFile
     */
    public function saveImage($img, $imgFile)
    {
        $path = dirname($imgFile);
        if (!file_exists($path) && !mkdir($path, 0775, true)) {
            die("- unable to create destination directory ($path), abort\n");
        }

        if (substr($imgFile, -3) == 'jpg') {
            imagejpeg($img, $imgFile, 85);
        } else {
            imagepng($img, $imgFile, 9);
        }
        imagedestroy($img);
    }

    /**
     * Load tile image from file
     * If tile file is not found, then create new empty tile
     *
     * @param int $z
     * @param int $x
     * @param int $y
     * @param bool $createIfEmpty
     *
     * @return bool
     */
    public function loadTileImage($z, $x, $y, $createIfEmpty = true)
    {
        $out = $this->tileStorage->get($z, $x, $y);
        if (!$out && $createIfEmpty) {
            $out = $this->createTile();
        }

        return $out;
    }

    /**
     * Save image to image file
     *
     * @param int $z
     * @param int $x
     * @param int $y
     * @param resource $img
     */
    public function saveTileImage($z, $x, $y, $img)
    {
        $this->tileStorage->set($z, $x, $y, $img);
    }

    /**
     * @param $msg
     * @param ...
     */
    protected function info($msg)
    {
        $args = array_slice(func_get_args(), 1);
        if (!empty($args)) {
            vprintf($msg, $args);
        } else {
            echo $msg;
        }
    }

    /**
     * @param $msg
     * @param ...
     */
    protected function debug($msg)
    {
        if (!$this->debug) {
            return;
        }
        $msg = 'DBG:'.$msg;
        $args = array_slice(func_get_args(), 1);
        if (!empty($args)) {
            vprintf($msg, $args);
        } else {
            echo $msg;
        }
    }
}
