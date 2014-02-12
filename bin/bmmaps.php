<?php
/**
 * Created by JetBrains PhpStorm.
 * User: meelis
 * Date: 7/24/13
 * Time: 10:44 AM
 * To change this template use File | Settings | File Templates.
 */

use Bmsite\Maps\ResourceLoader;
use Bmsite\Maps\Tools\Console\Helper\ResourceHelper;

require_once dirname(__DIR__).'/vendor/autoload.php';

$appRoot = dirname(__DIR__);

$config = include $appRoot.'/resources/map-config.php';

$loader = new ResourceLoader();
$serverZonesFile = $loader->getFilePath('server.json');
$worldZonesFile = $loader->getFilePath('world.json');
$labelsFile = $loader->getFilePath('labels.json');

//$db = new SQLite3(__DIR__ . '/../app/maptiles.sqlite3');
//if (!$db->querySingle('SELECT 1 FROM sqlite_master WHERE type = "table" and name = "map"')) {
//    $sql = file_get_contents(__DIR__ . '/../app/maptiles-schema.sql');
//    if (!$db->exec($sql)) {
//        $err = $db->lastErrorMsg();
//        throw new \RuntimeException("Failed to create maptiles.sqlite3 database ({$err})");
//    }
//}
//$tilestorage = new \Bmsite\Maps\TileStorage($db);

$tilestorage = new \Bmsite\Maps\Tiles\FileTileStorage($appRoot.'/tiles');

$resources = new ResourceHelper();
$resources->set('app.path', $appRoot);

$resources->set('map-config', $config);
$resources->set('server.json.array', $loader->loadJson('server.json'), true);
$resources->set('world.json.array', $loader->loadJson('world.json'), true);
$resources->set('labels.json.array', $loader->loadJson('labels.json'), true);
$resources->set('tilestorage', $tilestorage);

$helperSet = new \Symfony\Component\Console\Helper\HelperSet();
$helperSet->set($resources);
$helperSet->set(new \Bmsite\Maps\Tools\Console\Helper\TranslateHelper());
$helperSet->set(new \Bmsite\Maps\Tools\Console\Helper\RegionsHelper());

$cli = new \Symfony\Component\Console\Application('Bmsite Ryzom Map Tiles', '0.1');
$cli->setCatchExceptions(true);
$cli->setHelperSet($helperSet);
$cli->addCommands(
    array(
        new \Bmsite\Maps\Tools\Console\Command\BuildMapTiles(),
    )
);
$cli->run();

