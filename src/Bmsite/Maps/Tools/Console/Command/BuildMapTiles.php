<?php

declare(strict_types=1);

/**
 * Ryzom Map Tiles
 *
 * @author Meelis Mägi <nimetu@gmail.com>
 * @copyright (c) 2014 Meelis Mägi
 * @license http://opensource.org/licenses/LGPL-3.0
 */

namespace Bmsite\Maps\Tools\Console\Command;

use Bmsite\Maps\MapProjection;
use Bmsite\Maps\Tools\Console\Helper\ResourceHelper;
use Bmsite\Maps\Tools\LabelGenerator;
use Bmsite\Maps\Tools\TileGenerator;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Class BuildWorldTiles
 */
class BuildMapTiles extends Command
{
    /** @var array */
    protected $config;

    /** @var  MapProjection */
    protected $proj;

    /** @var ResourceHelper */
    protected $helper;

    /** @var string */
    protected $mapdir;

    /** @var string */
    protected $mapmode;

    /** @var string */
    protected $mapname;

    /** @var bool */
    protected $useRegionForce;

    /** @var \Bmsite\Maps\Tiles\FileTileStorage */
    protected $tileStorage;

    /** @var string[] */
    protected $zones = array();

    protected function configure()
    {
        $this
            ->setName('bmmaps:tiles')
            ->setDescription('Build map tiles')
            ->addOption(
                'mapmode',
                null,
                InputOption::VALUE_REQUIRED,
                'Select <comment>world</comment> or <comment>server</comment> coordinates for tiles',
                'world',
            )
            ->addOption('mapname', null, InputOption::VALUE_REQUIRED, 'Select name for output tiles', 'atys')
            ->addOption(
                'mapdir',
                null,
                InputOption::VALUE_REQUIRED,
                'Path to input map names (world.jpg, newbieland.jpg, etc)',
                'app/resources/maps/atys',
            )
            ->addOption(
                'zones',
                null,
                InputOption::VALUE_REQUIRED,
                'Comma separated list of zones to generate',
                join(',', $this->zones),
            )
            ->addOption(
                'lang',
                null,
                InputOption::VALUE_REQUIRED,
                'Comma separated list of language tiles to generate (en,fr,de,es,ru)',
                '',
            )
            ->addOption('with-map', null, InputOption::VALUE_NONE, 'Generate map tiles')
            ->addOption('with-city', null, InputOption::VALUE_NONE, 'Generate city tiles')
            ->addOption('with-region-color', null, InputOption::VALUE_NONE, 'Use region force as region color');
    }

    /**
     * @param InputInterface $input
     * @param OutputInterface $output
     *
     * @throws \InvalidArgumentException
     * @return int|null|void
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $this->helper = $this->getHelper('resource');

        $mapmode = strtolower($input->getOption('mapmode'));
        if (!in_array($mapmode, array('world', 'server'), true)) {
            throw new \InvalidArgumentException("--mapmode must be 'world' or 'server'");
        }

        $mapname = $input->getOption('mapname');

        $this->mapdir = $input->getOption('mapdir');
        $lang = $input->getOption('lang');

        if ($this->mapdir[0] !== '/') {
            $this->mapdir = $this->helper->get('app.path') . '/' . $this->mapdir;
        }

        $withMap = $input->hasParameterOption('--with-map');
        $withCity = $input->hasParameterOption('--with-city');
        $withRegionColors = $input->hasParameterOption('--with-region-color');

        $output->writeln('=======================');
        $output->writeln("mode = <info>$mapmode</info>");
        $output->writeln("mapdir = <info>$this->mapdir</info>");

        if (!$withMap && !$withCity && empty($lang)) {
            throw new \InvalidArgumentException("Use --with-map, --with-city, --lang options\n");
        }

        $this->config = $this->helper->get('map-config');

        $this->proj = new MapProjection();
        $this->proj->setServerZones($this->helper->get('server.json.array'));

        $maps = $this->config['maps'];
        if ($mapmode === 'world') {
            $this->proj->setWorldZones($this->helper->get('world.json.array'));
        } else {
            // include individual zone map
            $maps = array_merge($maps, $this->config['zones']);
            unset($maps['world']);

            $this->proj->setWorldZones(array('grid' => array(array(0, 47520), array(108000, 0))));
        }

        $this->mapmode = $mapmode;
        $this->mapname = $mapname;

        $this->tileStorage = $this->helper->get('tilestorage');

        $zones = $input->getOption('zones');
        if (!empty($zones)) {
            $this->zones = explode(',', $zones);
        }

        // generate tiles for world map zone placement
        if ($withMap) {
            // map tiles
            $minMapZoom = $this->config['map-zoom']['min'];
            $maxMapZoom = $this->config['map-zoom']['max'];
            $this->doMaps($maps, $minMapZoom, $maxMapZoom, $output);
        }
        if ($withCity) {
            // city map on world image
            $minCityZoom = $this->config['city-zoom']['min'];
            $maxCityZoom = $this->config['city-zoom']['max'];
            $this->doMaps($this->config['cities'], $minCityZoom, $maxCityZoom, $output);
        }

        if (!empty($lang)) {
            // text tiles
            $minTextZoom = $this->config['text-zoom']['min'];
            $maxTextZoom = $this->config['text-zoom']['max'];
            $languages = explode(',', $lang);
            foreach ($languages as $l) {
                $this->doTextTiles($l, $withRegionColors, $minTextZoom, $maxTextZoom, $output);
            }
        }
    }

    /**
     * @param string[] $maps
     * @param int $minZoom
     * @param int $maxZoom
     * @param OutputInterface $output
     */
    protected function doMaps($maps, $minZoom, $maxZoom, OutputInterface $output)
    {
        $output->writeln('maps = <info>' . join('</info>, <info>', $maps) . '</info>');

        $this->tileStorage->setMapMode($this->mapmode);
        $this->tileStorage->setMapName($this->mapname);
        $this->tileStorage->setImageExt('jpg');

        $gen = new TileGenerator($this->mapdir, $this->proj);
        $gen->setTileStorage($this->tileStorage);
        $gen->generate(array($minZoom, $maxZoom), $maps);
    }

    /**
     * @param string $lang
     * @param bool $withRegionColors
     * @param int $minZoom
     * @param int $maxZoom
     * @param OutputInterface $output
     */
    protected function doTextTiles($lang, $withRegionColors, $minZoom, $maxZoom, OutputInterface $output)
    {
        $mapname = "lang_{$lang}";

        $output->writeln("lang = <info>$lang</info>");

        $resources = $this->helper->get('app.path') . '/resources';

        $this->tileStorage->setMapMode($this->mapmode);
        $this->tileStorage->setMapName($mapname);
        $this->tileStorage->setImageExt('png');

        $gen = new LabelGenerator($this->proj, $resources);
        $gen->setTileStorage($this->tileStorage);

        $filter = $this->config['labels'];
        if ($this->mapmode === 'server') {
            $filter = isset($this->config['server-labels']) ? $this->config['server-labels'] : array();
        }
        $gen->loadLabels($this->helper->get('labels.json.array'), $filter);

        $gen->setLanguage($lang);
        $gen->setUseRegionForce($withRegionColors);
        $gen->generate(array($minZoom, $maxZoom));
    }
}
