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

use Bmsite\Maps\BaseTypes\Bounds;
use Bmsite\Maps\BaseTypes\Color;
use Bmsite\Maps\BaseTypes\Label;
use Bmsite\Maps\BaseTypes\Point;
use Bmsite\Maps\MapProjection;
use Bmsite\Maps\StaticMap\Feature\Icon;
use Bmsite\Maps\Tiles\TileStorageInterface;
use GdImage;
use Ryzom\Sheets\Client\CContLandMark;

class LabelGenerator extends BaseTileGenerator
{
    protected bool $useRegionForce = false;

    protected Color $defaultColor;

    protected Icon $icon;

	protected string $lang = 'en';

	protected string $fontFamily = 'ryzom.ttf';

	protected string $fontFamilyBold = 'ryzom.ttf';

	/**
	 * Zone labels grouped by type
	 *
	 * @var array<string,list<Label>>
	 */
	protected array $labels = [];

	/** @var array<string,array<string,string>> */
	protected array $translations = [];

    /**
     * @param MapProjection $proj
     * @param string $resourcePath
     */
	public function __construct(
		protected MapProjection $proj,
		protected string $resourcePath)
    {
        $this->defaultColor = new Color(255, 255, 255);

        //$this->icon = new Icon('lm_marker');
        //$this->icon->setColor(new Color(0x1b, 0xcf, 0x34));
    }

    /**
     * @param bool $isBold
     *
     * @return string
     */
    public function getFont($isBold = false)
    {
        if ($isBold) {
            return $this->resourcePath.'/fonts/'.$this->fontFamilyBold;
        }
        return $this->resourcePath.'/fonts/'.$this->fontFamily;
    }

    public function loadLabels(array $labels, array $zones = array())
	{
		$this->translations = [];

        $this->labels = array();
        foreach ($labels as $parent => $childs) {
            $skip = isset($zones['*']) && $zones['*'] !== true;
            if (isset($zones[$parent])) {
                $skip = $zones[$parent] === false;
            }
            if ($skip) {
                echo "- skip $parent\n";
                continue;
            }
            foreach ($childs as $id => $zone) {
                if (isset($zones[$parent][$id]) && $zones[$parent][$id] === false) {
                    echo "- skip $parent/{$id}\n";
                    continue;
                }

                try {
                    $type = $zone['lmtype'];
                    $latLng = new Point($zone['pos'][0], $zone['pos'][1]);

                    $p = $this->proj->project($latLng);
                    if ($this->useRegionForce) {
                        $color = $this->getRegionForceColor($zone['regionforce']);
                    } else {
                        $color = $this->defaultColor;
                    }

                    $label = new Label();
                    $label->point = $p;
                    $label->color = $color;
                    $label->text = $id;
					$label->type = $type;

					$this->translations[$id] = $zone['text'];

					$this->labels[$parent][$type][] = $label;
                } catch (\InvalidArgumentException $e) {
                    /* unknown zone coords */
                    echo "! unknown zone ".$e->getMessage()."\n";
                }
            }
        }
    }

    /**
     * @param bool $state
     */
    public function setUseRegionForce($state)
    {
        $this->useRegionForce = $state;
    }

    /**
     * @param $lang
     */
    public function setLanguage($lang)
    {
        $this->lang = $lang;
    }

    /**
     * @param array $zoomRange
     * @param array $maps      not used, maps are read from labels property
     */
    public function generate(array $zoomRange, array $maps = array())
    {
        foreach ($this->labels as $zone => $data) {
            $this->info("+ {$zone}\033[K\n");

            $this->processMapLabels($data, $zoomRange);
        }
    }

    public function processMapLabels(array $labels, array $zoomRange)
    {
        $zIndexArray = $this->lmTypeOrder();
        for ($zoom = $zoomRange[0]; $zoom <= $zoomRange[1]; $zoom++) {
            foreach ($zIndexArray as $lmType) {
                if (!isset($labels[$lmType])) {
                    continue;
                }

                $style = $this->getFontSize($lmType, $zoom);

                // TODO: $showLabel = $this->isLabelVisible($lmType, $zoom);
                $showLabel = $style['fontSize'] > 0;
                $showIcon = $this->isIconVisible($lmType, $zoom);
                if ($showLabel || $showIcon) {
                    $this->processLabels($zoom, $labels[$lmType], $style, $showIcon);
                }
            }
        }
    }

    /**
     * Draw same type to labels (area, region, etc) into tiles
     *
     * @throws \RuntimeException
     */
    protected function processLabels(int $zoom, array $labels, array $style, bool $withIcon)
    {
        $scale = $this->proj->scale($zoom);
        $count = count($labels);
        foreach ($labels as $id => $label) {
            $mem = memory_get_usage();
            // if (!isset($label->text[$this->lang])) {
            //     throw new \RuntimeException("Missing translation ({$this->lang}), abort");
            // }
            // center point in grid
            $point = new Point($label->point->x * $scale, $label->point->y * $scale);

			$text = $this->translations[$label->text][$this->lang];

            // center tile
            $txHome = floor($point->x / TileStorageInterface::TILE_SIZE);
            $tyHome = floor($point->y / TileStorageInterface::TILE_SIZE);

            $this->info("[$mem] + ($zoom) ($count) [$txHome, $tyHome], $text, \033[K\r");
            --$count;

            // label position relative to tile
            $cx = $point->x - $txHome * TileStorageInterface::TILE_SIZE;
            $cy = $point->y - $tyHome * TileStorageInterface::TILE_SIZE;
            $this->debug("+ ($id:$text) $point ($txHome, $tyHome) -> cx/cy [$cx, $cy]\n");

            $bbox = $this->getTextDimensions($style, $text);
            $tileBounds = $this->getTileBounds($point, $bbox);

            // 1x1 tile will give 0/0 as width/height
            $xTiles = $tileBounds->getWidth() + 1;
            $yTiles = $tileBounds->getHeight() + 1;
            $tx1 = $tileBounds->left;
            $ty1 = $tileBounds->top;

            // work image
            $canvas = $this->createTile(
                intval($xTiles * TileStorageInterface::TILE_SIZE),
                intval($yTiles * TileStorageInterface::TILE_SIZE)
            );

            // load tiles that needs to be modified
            $this->loadCanvas($canvas, $zoom, (int)$tx1, (int)$ty1, (int)$xTiles, (int)$yTiles);

            // label position relative to canvas
            $p = new Point($cx + ($txHome - $tx1) * TileStorageInterface::TILE_SIZE, $cy + ($tyHome - $ty1) * TileStorageInterface::TILE_SIZE);
            if ($withIcon) {
                $this->drawIcon($canvas, $p);
            }
            if ($style['fontSize'] > 0) {
                $this->drawText($canvas, $p, $style, $text, $label->color);
            }

            // now save those tiles back
            $this->saveCanvas($canvas, $zoom, (int)$tx1, (int)$ty1, (int)$xTiles, (int)$yTiles);
        }
    }

    protected function drawIcon(GdImage $dst, Point $point)
    {
        //$this->icon->setPos($point);
        //$this->icon->draw($dst);

        $file = $this->resourcePath . '/icons/lm_continent.png';
        if (!file_exists($file)) {
            return;
        }
        $icon = imagecreatefrompng($file);
        $w = imagesx($icon);
        $h = imagesy($icon);

        $x = $point->x - $w / 2;
        $y = $point->y - $h / 2;
        imagecopy($dst, $icon, (int)$x, (int)$y, 0, 0, $w, $h);
    }

    protected function drawText(GdImage $dst, Point $point, array $style, string $text, Color $color)
    {
        $font = $this->getFont($style['bold']);
        $fontSize = $style['fontSize'];

        $bbox = $this->getTextDimensions($style, $text);

        // bottom-left corner
        $x = $point->x - ($bbox->getWidth() / 2) - $bbox->left;
        $y = $point->y + $bbox->getHeight() - $bbox->bottom;

		$s = imagecolorallocate($dst, 0x00, 0x00, 0x00);
		$c = $color->allocate($dst);
        //$c = imagecolorallocate($dst, 0xf0, 0xf0, 0xf0);

        // outline - 2px for large fonts, 1px for smaller
        $dd = $fontSize < 12 ? 1 : 2;
        for ($dx = -$dd; $dx <= $dd; $dx++) {
            for ($dy = -$dd; $dy <= $dd; $dy++) {
                imagettftext($dst, $fontSize, 0, intval($x + $dx), intval($y + $dy), $s, $font, $text);
            }
        }
        // text
        imagettftext($dst, $fontSize, 0, (int)$x, (int)$y, $c, $font, $text);
    }

    protected function loadCanvas(GdImage $canvas, int $zoom, int $xTileOffset, int $yTileOffset, int $xTiles, int $yTiles)
    {
        for ($tx = 0; $tx < $xTiles; $tx++) {
            for ($ty = 0; $ty < $yTiles; $ty++) {
                $tile = $this->loadTileImage($zoom, $tx + $xTileOffset, $ty + $yTileOffset, false);
                if ($tile) {
                    imagecopy(
                        $canvas,
                        $tile,
                        $tx * TileStorageInterface::TILE_SIZE,
                        $ty * TileStorageInterface::TILE_SIZE,
                        0,
                        0,
                        TileStorageInterface::TILE_SIZE,
                        TileStorageInterface::TILE_SIZE
                    );
                    imagedestroy($tile);
                }
            }
        }
    }

    protected function saveCanvas(GdImage $canvas, int $zoom, int $xTileOffset, int $yTileOffset, int $xTiles, int $yTiles)
    {
        for ($tx = 0; $tx < $xTiles; $tx++) {
            for ($ty = 0; $ty < $yTiles; $ty++) {
                $xTile = $tx + $xTileOffset;
                $yTile = $ty + $yTileOffset;

                $x = $tx * TileStorageInterface::TILE_SIZE;
                $y = $ty * TileStorageInterface::TILE_SIZE;

                $this->debug(">> save {$zoom}, {$xTile}, {$yTile} (canvas pos [$x, $y])\n");

                $out = $this->createTile();
                imagecopy(
                    $out,
                    $canvas,
                    0,
                    0,
                    $x,
                    $y,
                    TileStorageInterface::TILE_SIZE,
                    TileStorageInterface::TILE_SIZE
                );

                $this->saveTileImage($zoom, $xTile, $yTile, $out);
            }
        }
    }

    protected function getTextDimensions(array $style, string $text): Bounds
    {
        if ($style['fontSize'] === 0) {
            return new Bounds(0, 0, 0, 0);
        }

        $font = $this->getFont($style['bold']);
        $bbox = imagettfbbox($style['fontSize'], 0, $font, $text);

        return new Bounds($bbox[0], $bbox[1], $bbox[4], $bbox[5]);
    }

    protected function getTileBounds(Point $point, Bounds $bbox): Bounds
    {
        $hw = $bbox->getWidth() / 2;
        $hh = $bbox->getHeight();
        $px1 = $point->x - $hw;
        $py1 = $point->y - $hh;
        $px2 = $point->x + $hw;
        $py2 = $point->y + $hh;
        $this->debug(" txtbox [$px1, $py1, $px2, $py2] -> ");

        $tx1 = floor($px1 / TileStorageInterface::TILE_SIZE);
        $tx2 = floor($px2 / TileStorageInterface::TILE_SIZE);
        $ty1 = floor($py1 / TileStorageInterface::TILE_SIZE);
        $ty2 = floor($py2 / TileStorageInterface::TILE_SIZE);
        $this->debug(" tile [$tx1, $ty1], [$tx2, $ty2]");

        //$xTiles = ($tx2 - $tx1) + 1;
        //$yTiles = ($ty2 - $ty1) + 1;
        //$this->debug(" nb tiles ($xTiles, $yTiles)\n");
        //return [$tx1, $ty1, $xTiles, $yTiles];

        return new Bounds($tx1, $ty2, $tx2, $ty1);
    }

    protected function lmTypeOrder(): array
    {
        return array(6, 3, 2, 5, 1, 0, 4, -1);
    }

    protected function isIconVisible(int $type, int $zoom): bool
    {
        $iconVisibility = array(
            // unknown type
            'default' => array(99, 99),
            // continent, biggest zone
            //-1 => [99, 99],
            // region 50m/px
            //4 => [6, 99],
            // capital city - 5m/px (icon)
            //0 => [6, 10],
            // town - 4m/px (icon)
            //1 => [7, 99],
            // area - 6m/px
            //5 => [8, 99],
            // outpost - 4m/px (icon)
            2 => array(7, 99),
            // stable - 3.5m/px (icon)
            3 => array(9, 99),
            // street - 2m/px
            //6 => [10, 99],
		);
        if (!isset($iconVisibility[$type])) {
            $type = 'default';
        }

        $data = $iconVisibility[$type];
        return $zoom >= $data[0] && $zoom <= $data[1];
    }

    /**
     * Return true if label type should be visible at zoom level
     */
    protected function isLabelVisible(int $type, int $zoom): bool {
        $fMeterPerPixel = 1024 / pow(2,$zoom);
        //
        switch($type) {
            case CContLandMark::CAPITAL: print("+ CAPITAL: visible $type, $zoom\n"); return $fMeterPerPixel <= 5.0;
            case CContLandMark::VILLAGE: print("+ VILLAGE: visible $type, $zoom\n"); return $fMeterPerPixel <= 4.0;
            case CContLandMark::OUTPOST: print("+ OUTPOST: visible $type, $zoom\n"); return $fMeterPerPixel <= 4.0;
            case CContLandMark::STABLE:  print("+ STABLE: visible $type, $zoom\n");  return $fMeterPerPixel <= 3.5;
            //
            case CContLandMark::REGION:  print("+ REGION: visible $type, $zoom\n");  return $fMeterPerPixel <= 50.0;
            case CContLandMark::PLACE:   print("+ PLACE: visible $type, $zoom\n");   return $fMeterPerPixel <= 6.0;
            case CContLandMark::STREET:  print("+ STREET: visible $type, $zoom\n");  return $fMeterPerPixel <= 2.0;
            default:                     print("+ default $type, $zoom\n");          return true;
        }
        return false;
    }

	/**
	* @return array{bold:bool, fontSize:int}
*/
    protected function getFontSize(int $lmType, int $zoom): array
    {
        // # m/px - ingame setting for showing label
        // +icon  - icon in game
        $styleArray = array(
            'default' => array('zoom' => array(0, 0, 0, 0, 0, 10)),
            // continent, biggest zone
            -1 => array('zoom' => array(0, 0, 0, 0, 10, 12, 12, 12, 12, 0), 'bold' => true),
            // capital city - 5m/px (+icon)
            0 => array('zoom' => array(0, 0, 0, 0, 0, 0, 9, 10, 11, 11, 0), 'bold' => true),
            // town - 4m/px (+icon)
            1 => array('zoom' => array(0, 0, 0, 0, 0, 0, 9)),
            // region 50m/px
            4 => array('zoom' => array(0, 0, 0, 0, 0, 0, 0, 8, 12), 'bold' => true),
            // area - 6m/px
            5 => array('zoom' => array(0, 0, 0, 0, 0, 0, 0, 0, 8)),
            // outpost - 4m/px (+icon)
            2 => array('zoom' => array(0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 8, 9)),
            // stable - 3.5m/px (+icon)
            3 => array('zoom' => array(0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 8, 9)),
            // street - 2m/px
            6 => array('zoom' => array(0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 7, 7)),
        );
        if (!isset($styleArray[$lmType])) {
            $lmType = 'default';
        }

        $style = $styleArray[$lmType];

        $zoomArray = $style['zoom'];
        if (isset($zoomArray[$zoom])) {
            $fontSize = $zoomArray[$zoom];
        } else {
            // if zoom level is not defined, then it's the same as last value
            $fontSize = $zoomArray[count($zoomArray) - 1];
        }

        $bold = isset($style['bold']) ? $style['bold'] : false;

        return array('bold' => $bold, 'fontSize' => $fontSize);
    }

    protected function getRegionForceColor(int $force): Color
    {
        $force2color = array(
            20 => array(80, 200, 180),
            50 => array(110, 110, 225),
            100 => array(255, 255, 172),
            150 => array(255, 180, 100),
            200 => array(200, 50, 50),
            250 => array(150, 50, 150),
        );
        if (!isset($force2color[$force])) {
            return $this->defaultColor;
        }
        return new Color($force2color[$force][0], $force2color[$force][1], $force2color[$force][2]);
	}

	protected function getTranslation(string $id): string
	{
		return $this->translations[$id][$this->lang];
	}
}
