<?php

declare(strict_types=1);

/**
 * Ryzom Map Tiles
 *
 * @author Meelis Mägi <nimetu@gmail.com>
 * @copyright (c) 2014 Meelis Mägi
 * @license http://opensource.org/licenses/LGPL-3.0
 */

namespace Bmsite\Maps\Tools;

use Bmsite\Maps\Tiles\TileStorageInterface;
use GdImage;

abstract class BaseTileGenerator
{
    protected bool $debug = false;

    protected TileStorageInterface $tileStorage;

    public function setTileStorage(TileStorageInterface $tileStorage)
    {
        $this->tileStorage = $tileStorage;
    }

    public function setDebug(bool $v)
    {
        $this->debug = $v;
    }

    /**
     * Create new empty and transparent tile
     */
    public function createTile(
        int $width = TileStorageInterface::TILE_SIZE,
        int $height = TileStorageInterface::TILE_SIZE,
    ): GdImage {
        $out = imagecreatetruecolor($width, $height);
        $tc = (int) imagecolorallocatealpha($out, 0, 0, 0, 127);
        imagefill($out, 0, 0, $tc);
        imagesavealpha($out, true);

        return $out;
    }

    /**
     * Load image from jpg or png file
     */
    public function loadImage(string $imgFile): ?GdImage
    {
        if (!file_exists($imgFile)) {
            return null;
        }
        if (substr($imgFile, -3) === 'jpg') {
            // reuse existing tile for background
            $out = imagecreatefromjpeg($imgFile);
        } else {
            $out = imagecreatefrompng($imgFile);
        }
        return $out === false ? null : $out;
    }

    public function saveImage(GdImage $img, string $imgFile)
    {
        $path = dirname($imgFile);
        if (!file_exists($path) && !mkdir($path, 0o775, true)) {
            die("- unable to create destination directory ({$path}), abort\n");
        }

        if (substr($imgFile, -3) === 'jpg') {
            imagejpeg($img, $imgFile, 85);
        } else {
            imagepng($img, $imgFile, 9);
        }
    }

    /**
     * Load tile image from file
     * If tile file is not found, then create new empty tile
     */
    public function loadTileImage(int $z, int $x, int $y, bool $createIfEmpty = true): ?GdImage
    {
        $out = $this->tileStorage->get($z, $x, $y);
        if (!$out && $createIfEmpty) {
            $out = $this->createTile();
        }

        return $out;
    }

    /**
     * Save image to image file
     */
    public function saveTileImage(int $z, int $x, int $y, GdImage $img)
    {
        $this->tileStorage->set($z, $x, $y, $img);
    }

    /**
     * Generate map tiles
     *
     * @param array{int,int} $zoomRange [ min, max]
     * @param array<string,string> $maps [ id => map.png ]
     *
     * @return void
     */
    abstract public function generate(array $zoomRange, array $maps = []);

    protected function info(string $msg, string|int|float ...$args)
    {
        if (!empty($args)) {
            vprintf($msg, $args);
        } else {
            echo $msg;
        }
    }

    protected function debug(string $msg, string|int|float ...$args)
    {
        if (!$this->debug) {
            return;
        }
        $msg = 'DBG:' . $msg;
        if (!empty($args)) {
            vprintf($msg, $args);
        } else {
            echo $msg;
        }
    }
}
