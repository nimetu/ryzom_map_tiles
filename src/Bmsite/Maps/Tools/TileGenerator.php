<?php
/**
 * Ryzom Map Tiles
 *
 * @author Meelis Mägi <nimetu@gmail.com>
 * @copyright (c) 2014 Meelis Mägi
 * @license http://opensource.org/licenses/LGPL-3.0
 */

namespace Bmsite\Maps\Tools;

use Bmsite\Maps\BaseTypes\Bounds;
use Bmsite\Maps\MapProjection;
use Bmsite\Maps\Tiles\TileStorageInterface;


/**
 * Class TileGenerator
 */
class TileGenerator extends BaseTileGenerator
{
    /** @var MapProjection */
    protected $projWorld;

    /** @var string */
    protected $mapsDirectory;

    /**
     * @param string $mapsDirectory
     * @param MapProjection $proj
     */
    public function __construct($mapsDirectory, $proj)
    {
        $this->mapsDirectory = $mapsDirectory;
        $this->projWorld = $proj;
    }

    /**
     * Generate map tiles
     *
     * @param array $maps [ id => map.png ]
     * @param array $zoomRange [ min, max]
     */
    public function generate(array $maps, array $zoomRange)
    {
        foreach ($maps as $id => $mapImage) {
            // load map file
            $mapFilename = $this->mapsDirectory.'/'.$mapImage;
            if (!file_exists($mapFilename)) {
                $this->info("- map file '${id}:${mapFilename}' not found, skip\n");
                continue;
            }

            $this->info("+ loading map {$mapFilename}\n");
            $mapImage = $this->loadImage($mapFilename);
            if (!$mapImage) {
                $this->info("error: map '$id' image '${mapImage}' not found\n");
                continue;
            }

            // find out map coordinates for base zoom level
            try {
                $zoneBounds = $this->projWorld->getZoneBounds($id);

                $mapImageWidth = imagesx($mapImage);
                $mapImageHeight = imagesy($mapImage);

                // map image coords at base zoom
                $zoneLeft = $zoneBounds->left;
                $zoneBottom = $zoneBounds->bottom;
                $zoneRight = $zoneBounds->right;
                $zoneTop = $zoneBounds->top;

                $this->info(
                    ">> map size ($mapImageWidth, $mapImageHeight), position ($zoneLeft, $zoneTop, $zoneRight, $zoneBottom)\n"
                );

                $mapImageWidth = imagesx($mapImage);
                $mapImageHeight = imagesy($mapImage);

                for ($zoom = $zoomRange[0]; $zoom <= $zoomRange[1]; $zoom++) {
                    $this->mapCutter($mapImage, $zoom, $mapImageWidth, $mapImageHeight, $zoneBounds);
                }

                imagedestroy($mapImage);
            } catch(\InvalidArgumentException $ex) {
                $this->info("exception on map ({$id}): {$ex->getMessage()}\n");
            }
        }
    }

    /**
     * @param resource $mapImage
     * @param int $zoom
     * @param int $mapImageWidth
     * @param int $mapImageHeight
     * @param Bounds $zoneBounds
     */
    protected function mapCutter($mapImage, $zoom, $mapImageWidth, $mapImageHeight, $zoneBounds)
    {
        // map image coords at base zoom
        $zoneLeft = $zoneBounds->left;
        $zoneBottom = $zoneBounds->bottom;
        $zoneRight = $zoneBounds->right;
        $zoneTop = $zoneBounds->top;

        $this->info("\033[K");
        $zoomScale = $this->projWorld->scale($zoom);

        // coords at current zoom level
        $left = $zoneLeft * $zoomScale;
        $bottom = $zoneBottom * $zoomScale;
        $right = $zoneRight * $zoomScale;
        $top = $zoneTop * $zoomScale;
        $width = ($right - $left);
        $height = ($bottom - $top);

        $scaleWidth = $mapImageWidth / $width;
        $scaleHeight = $mapImageHeight / $height;
        $this->debug("($zoom) map size ($width, $height), scaled (%.3f, %.3f)\n", $scaleWidth, $scaleHeight);

        // tiles this image lands
        $leftTile = floor($left / TileStorageInterface::TILE_SIZE);
        $topTile = floor($top / TileStorageInterface::TILE_SIZE);
        // right/bottom are +1 in value
        // (map on 0,0 tile has 1,1 as right/bottom) as we need top-left coords for that tile
        $rightTile = ceil($right / TileStorageInterface::TILE_SIZE);
        $bottomTile = ceil($bottom / TileStorageInterface::TILE_SIZE);

        $xTiles = $rightTile - $leftTile;
        $yTiles = $bottomTile - $topTile;
        $this->debug(
            "    num tiles ($xTiles, $yTiles), map at ($left, $top):($right, $bottom) on tiles($leftTile, $topTile, %d, %d)\n",
            $rightTile - 1,
            $bottomTile - 1
        );
        $x1 = 0;
        $cw = 0;
        for ($xTile = 0; $xTile < $xTiles; $xTile++) {
            $y1 = 0;
            $ch = 0;
            for ($yTile = 0; $yTile < $yTiles; $yTile++) {
                $mem = memory_get_usage();
                $this->info(
                    "[$mem] - zoom % 2d (% 5dx% 5d @ %8d grid, mul=%.5f), tile % 3dx% 3d\033[K\r",
                    $zoom,
                    $width,
                    $height,
                    pow(2, $zoom) * TileStorageInterface::TILE_SIZE,
                    $zoomScale,
                    $xTile,
                    $yTile
                );
                // tile coords within grid
                $tx1 = ($leftTile + $xTile) * TileStorageInterface::TILE_SIZE;
                $ty1 = ($topTile + $yTile) * TileStorageInterface::TILE_SIZE;
                $tx2 = ($leftTile + $xTile) * TileStorageInterface::TILE_SIZE + TileStorageInterface::TILE_SIZE;
                $ty2 = ($topTile + $yTile) * TileStorageInterface::TILE_SIZE + TileStorageInterface::TILE_SIZE;

                // image padding in tile
                $pad_l = 0;
                $pad_t = 0;
                $pad_r = 0;
                $pad_b = 0;
                if ($tx1 < $left) {
                    $pad_l = floor($left - $tx1);
                }
                if ($ty1 < $top) {
                    $pad_t = floor($top - $ty1);
                }
                if ($tx2 > $right) {
                    $pad_r = ceil($tx2 - $right);
                }
                if ($ty2 > $bottom) {
                    $pad_b = ceil($ty2 - $bottom);
                }
                $this->debug(
                    ">> (xTile:$xTile, yTile:$yTile): (tx1:$tx1,ty1:$ty1):(tx2:$tx2,ty2:$ty2), padding ($pad_l, $pad_t):($pad_r, $pad_b)\n"
                );

                $dstWidth = TileStorageInterface::TILE_SIZE - $pad_r - $pad_l;
                $dstHeight = TileStorageInterface::TILE_SIZE - $pad_b - $pad_t;

                $cw = $dstWidth * $scaleWidth;
                $ch = $dstHeight * $scaleHeight;

                $this->debug(
                    ">> copy from (%d, %d):(%d,%d) to tile (%d, %d):(%d,%d)\n",
                    $x1,
                    $y1,
                    $cw,
                    $ch,
                    $pad_l,
                    $pad_t,
                    $dstWidth,
                    $dstHeight
                );

                $out = $this->loadTileImage($zoom, $leftTile + $xTile, $topTile + $yTile);

                imagecopyresampled(
                    $out,
                    $mapImage,
                    /*dst*/
                    $pad_l,
                    $pad_t,
                    /*src*/
                    $x1,
                    $y1,
                    /*dst*/
                    $dstWidth,
                    $dstHeight,
                    /*src*/
                    $cw,
                    $ch
                );
                if ($this->debug) {
                    $c = imagecolorallocatealpha($out, 255, 0, 0, 50);
                    imagerectangle($out, $pad_l, $pad_t, $pad_l + $dstWidth, $pad_t + $dstHeight, $c);
                }

                $this->saveTileImage($zoom, $leftTile + $xTile, $topTile + $yTile, $out);

                $y1 += $ch;
            }
            $x1 += $cw;
        }
    }

}
