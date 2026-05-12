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
use Bmsite\Maps\Tiles\TileStorageInterface;
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
    /**
     * @var array{
     *   map-zoom: array{min:int,max:int},
     *   city-zoom: array{min:int,max:int},
     *   text-zoom: array{min:int,max:int},
     *   maps_path: string,
     *   maps: array<string,string>,
     *   labels: array<string,array<string,bool>>|bool,
     *   server-labels: array<string,bool|array<string,bool>>,
     *   zones: array<string,string>,
     *   cities: array<string,string>,
     * }
     */
    protected array $config;

    protected MapProjection $proj;

    protected ResourceHelper $helper;

    protected string $mapdir;

    protected string $mapmode;

    protected string $mapname;

    protected bool $useRegionForce;

    protected TileStorageInterface $tileStorage;

    /** @var string[] */
    protected array $zones = [];

    protected function configure()
    {
        $this
            ->setName('bmmaps:tiles')
            ->setDescription('Build map tiles')
            ->addOption(
                name: 'mapmode',
                mode: InputOption::VALUE_REQUIRED,
                description: 'Select <comment>world</comment> or <comment>server</comment> coordinates for tiles',
                default: 'world',
            )
            ->addOption(
                name: 'mapname',
                mode: InputOption::VALUE_REQUIRED,
                description: 'Select name for output tiles',
                default: 'atys',
            )
            ->addOption(
                name: 'mapdir',
                mode: InputOption::VALUE_REQUIRED,
                description: 'Path to input map names (world.jpg, newbieland.jpg, etc)',
                default: 'app/resources/maps/atys',
            )
            ->addOption(
                name: 'zones',
                mode: InputOption::VALUE_REQUIRED,
                description: 'Comma separated list of zones to generate',
                default: join(',', $this->zones),
            )
            ->addOption(
                name: 'lang',
                mode: InputOption::VALUE_REQUIRED,
                description: 'Comma separated list of language tiles to generate (en,fr,de,es,ru)',
                default: '',
            )
            ->addOption(name: 'with-map', mode: InputOption::VALUE_NONE, description: 'Generate map tiles')
            ->addOption(name: 'with-city', mode: InputOption::VALUE_NONE, description: 'Generate city tiles')
            ->addOption(
                name: 'with-region-color',
                mode: InputOption::VALUE_NONE,
                description: 'Use region force as region color',
            );
    }

    /**
     * @param InputInterface $input
     * @param OutputInterface $output
     *
     * @throws \InvalidArgumentException
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        /** @var ResourceHelper */
        $helper = $this->getHelper('resource');
        $this->helper = $helper;

        /** @var string */
        $mapmode = $input->getOption('mapmode');
        if (!in_array($mapmode, ['world', 'server'], true)) {
            throw new \InvalidArgumentException("--mapmode must be 'world' or 'server'");
        }

        /** @var string */
        $mapname = $input->getOption('mapname');

        /** @var string */
        $mapdir = $input->getOption('mapdir');
        if ($mapdir[0] !== '/') {
            /** @var string */
            $apppath = $this->helper->get('app.path');
            $mapdir = $apppath . '/' . $mapdir;
        }
        $this->mapdir = $mapdir;

        /** @var string */
        $lang = $input->getOption('lang');

        $withMap = $input->hasParameterOption('--with-map');
        $withCity = $input->hasParameterOption('--with-city');
        $withRegionColors = $input->hasParameterOption('--with-region-color');

        $output->writeln('=======================');
        $output->writeln("mode = <info>{$mapmode}</info>");
        $output->writeln("mapdir = <info>{$mapdir}</info>");

        if (!$withMap && !$withCity && empty($lang)) {
            throw new \InvalidArgumentException("One of --with-map, --with-city, --lang option must be set.\n");
        }

        // @mago-expect analysis:mixed-property-type-coercion
        $this->config = $this->helper->get('map-config');

        $this->proj = new MapProjection();
        // @mago-expect analysis:mixed-argument
        $this->proj->setServerZones($this->helper->get('server.json.array'));

        $maps = $this->config['maps'];
        if ($mapmode === 'world') {
            // @mago-expect analysis:mixed-argument
            $this->proj->setWorldZones($this->helper->get('world.json.array'));
        } else {
            // include individual zone map
            $maps = array_merge($maps, $this->config['zones']);
            unset($maps['world']);

            $this->proj->setWorldZones(['grid' => [[0, 47520], [108000, 0]]]);
        }

        $this->mapmode = $mapmode;
        $this->mapname = $mapname;

        /** @var TileStorageInterface */
        $ts = $this->helper->get('tilestorage');
        $this->tileStorage = $ts;

        /** @var string */
        $zones = $input->getOption('zones');
        if (!empty($zones)) {
            $this->zones = explode(',', $zones);
        }

        // generate tiles for world map zone placement
        if ($withMap) {
            // map tiles
            $minMapZoom = (int) $this->config['map-zoom']['min'];
            $maxMapZoom = (int) $this->config['map-zoom']['max'];
            $this->doMaps($maps, $minMapZoom, $maxMapZoom, $output);
        }
        if ($withCity) {
            // city map on world image
            $minCityZoom = (int) $this->config['city-zoom']['min'];
            $maxCityZoom = (int) $this->config['city-zoom']['max'];
            $this->doMaps($this->config['cities'], $minCityZoom, $maxCityZoom, $output);
        }

        if (!empty($lang)) {
            // text tiles
            $minTextZoom = (int) $this->config['text-zoom']['min'];
            $maxTextZoom = (int) $this->config['text-zoom']['max'];
            $languages = explode(',', $lang);
            foreach ($languages as $l) {
                $this->doTextTiles($l, $withRegionColors, $minTextZoom, $maxTextZoom, $output);
            }
        }

        return 0;
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
        // @mago-expect analysis:less-specific-argument
        $gen->generate([$minZoom, $maxZoom], $maps);
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

        $output->writeln("lang = <info>{$lang}</info>");

        // @mago-expect analysis:mixed-operand
        $resources = $this->helper->get('app.path') . '/resources';

        $this->tileStorage->setMapMode($this->mapmode);
        $this->tileStorage->setMapName($mapname);
        $this->tileStorage->setImageExt('png');

        $gen = new LabelGenerator($this->proj, $resources);
        $gen->setTileStorage($this->tileStorage);

        /** @var array<string,array<string,bool>|bool> $filter */
        $filter = $this->config['labels'];
        if ($this->mapmode === 'server') {
            $filter = isset($this->config['server-labels']) ? $this->config['server-labels'] : [];
        }

        // @mago-expect analysis:mixed-argument
        $gen->loadLabels($this->helper->get('labels.json.array'), $filter);

        $gen->setLanguage($lang);
        $gen->setUseRegionForce($withRegionColors);
        $gen->generate([$minZoom, $maxZoom]);
    }
}
