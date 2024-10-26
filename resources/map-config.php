<?php

// map -> image
return array(
    'map-zoom' => array('min' => 5, 'max' => 10),
    'city-zoom' => array('min' => 10, 'max' => 10),
    'text-zoom' => array('min' => 5, 'max' => 12),
    'maps_path' => __DIR__.'/maps',
    'maps' => array(
        'world' => 'world.png',
    ),
    // select whole region or exclude individual labels inside region
    'labels' => array(
        // disable all, except specifically enabled
        '*' => false,
        'fyros' => true,
        'matis' => true,
        'tryker' => true,
        'zorai' => true,
        'nexus' => true,
        'newbieland' => true,
        'kitiniere' => true,
        'sources' => true,
        'terre' => true,
        'route_gouffre' => true,
        'bagne' => true,
        // disable whole zone
        'r2_desert' => false,
        'r2_forest' => false,
        'r2_jungle' => false,
        'r2_lakes' => false,
        'r2_roots' => false,
        // enable 'matis_island', but disable specific labels inside it
        'matis_island' => array(
            // duplicate continent/region names
            'region_matis_island_1' => false,
            'place_matis_island_1' => false, //TODO: lmtype=1
            'kitiniere' => false,
            // matis_island_3
            'region_matis_island_3' => false,
            'place_welcomer_anniversary_island' => false,
            'main_stage_anniversary_island' => false,
            'memorial_anniversary_island' => false,
            'cake_anniversary_island' => false,
            // Dante's Camp
            'region_matis_island_2_cont' => false,
            'region_matis_island_2' => false,
            'place_matis_island_2' => false,
        ),
    ),
    'server-labels' => array(
        '*' => false,
        'fyros' => true,
        'matis' => true,
        'tryker' => true,
        'zorai' => true,
        'nexus' => true,
        'newbieland' => true,
        'kitiniere' => true,
        'sources' => true,
        'terre' => true,
        'route_gouffre' => true,
        'bagne' => true,
        // disable whole zone
        'r2_desert' => true,
        'r2_forest' => true,
        'r2_jungle' => true,
        'r2_lakes' => true,
        'r2_roots' => true,
        // enable 'matis_island', but disable specific labels inside it
        'matis_island' => array(
            // matis_island_3
            'region_matis_island_3' => false,
            'place_welcomer_anniversary_island' => false,
            'main_stage_anniversary_island' => false,
            'memorial_anniversary_island' => false,
            'cake_anniversary_island' => false,
        ),
		'zorai_island' => true,
    ),
    // map ingame continent to map texture for 'server' mode
    'zones' => array(
        'continent_fyros' => 'fyros_map.png',
        'continent_matis' => 'matis_map.png',
        'continent_tryker' => 'tryker_map.png',
        'continent_zorai' => 'zorai_map.png',
        'continent_nexus' => 'nexus_map.png',
        'continent_terre' => 'pr_terre_map.png',
        'continent_sources' => 'pr_source_map.png',
        'continent_route_gouffre' => 'pr_route_gouffre_map.png',
        'continent_bagne' => 'pr_bagne_map.png',
        'cont_undernexus' => 'undernexus.png',
        'cont_newbieland' => 'newbieland_map.png',
        'cont_kitiniere' => 'kitiniere_map.png',
        // almati, aelius, olkern
        'fyros_island' => '../atys_sp/fyros_island_full_map.png',
        'matis_island' => 'matis_island_full_map.png',
        'tryker_island' => '../atys_sp/tryker_island_full_map.png',
        'zorai_island' => 'zorai_island_full_map.png',
        'continent_zorai_island' => 'zorai_island_full_map.png',
        // old starter zones
        'continent_fyros_newbie' => 'fyros_newbie_map.png',
        'continent_matis_newbie' => 'matis_newbie_map.png',
        'continent_tryker_newbie' => 'tryker_newbie_map.png',
        'continent_zorai_newbie' => 'zorai_newbie_map.png',
        //
        //'gm_island' => '../gm_island_map.png',
        'indoors' => '../indoors_map.png',
        'cont_corrupted_moor' => 'corrupted_moor_map.png',
        //
        'region_fyros_island1pvp 3' => '../atys_sp/fyros_island_1_map.png',
        'region_fyros_island2' => '../atys_sp/fyros_island_2_map.png',
        'region_fyros_island3' => 'fyros_island_map.png',
		//
        'region_tryker_island1' => '../atys_sp/tryker_island_1_map.png',
        'region_tryker_island2' => '../atys_sp/tryker_island_2_map.png',
        'region_tryker_island3' => '../atys_sp/tryker_island_3_map.png',
        'region_tryker_island4' => '../atys_sp/tryker_island_map.png',
        'region_tryker_island5' => '../atys_sp/tryker_island_5_map.png',
		//
        'place_matis_island_1' => 'matis_island_map.png',
        'place_matis_island_2' => 'matis_island_2_map.png',
        'region_matis_island_3' => 'matis_island_3_map.png',
        //
        'r2_desert' => '../r2_maps/r2_desert_map.png',
        'r2_forest' => '../r2_maps/r2_forest_map.png',
        'r2_jungle' => '../r2_maps/r2_jungle_map.png',
        'r2_lakes' => '../r2_maps/r2_lakes_map.png',
        'r2_roots' => '../r2_maps/r2_roots_map.png',
    ),
    'cities' => array(
        'place_pyr' => 'fy_cit_pyr.png',
        'place_dyron' => 'fy_cit_dyron.png',
        'place_thesos' => 'fy_cit_thesos.png',
        //
        'place_avalae' => 'ma_cit_avalae.png',
        'place_davae' => 'ma_cit_davae.png',
        'place_natae' => 'ma_cit_natae.png',
        'place_yrkanis' => 'ma_cit_yrkanis.png',
        //
        'place_avendale' => 'tr_cit_avendale.png',
        'place_crystabell' => 'tr_cit_crystabell.png',
        'place_fairhaven' => 'tr_cit_fairhaven.png',
        'place_windermeer' => 'tr_cit_windermeer.png',
        //
        'place_hoi_cho' => 'zo_cit_hoi_cho.png',
        'place_jen_lai' => 'zo_cit_jen_lai.png',
        'place_min_cho' => 'zo_cit_min_cho.png',
        'place_zora' => 'zo_cit_zora.png',
        //
        'place_starting_zone_starting_city' => 'newbieland_city.png',
    )
);
