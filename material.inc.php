<?php
/**
 *------
 * BGA framework: © Gregory Isabelli <gisabelli@boardgamearena.com> & Emmanuel Colin <ecolin@boardgamearena.com>
 * fleet implementation : © <Your name here> <Your email address here>
 * 
 * This code has been produced on the BGA studio platform for use on http://boardgamearena.com.
 * See http://en.boardgamearena.com/#!doc/Studio for more information.
 * -----
 *
 * material.inc.php
 *
 * fleet game material description
 *
 * Here, you can describe the material of your game with PHP variables.
 *   
 * This file is loaded in your game logic class constructor, ie these variables
 * are available everywhere in your game logic code.
 *
 */

if (!defined("LICENSE_SHRIMP")) {
    define("LICENSE_SHRIMP", 0);
    define("LICENSE_COD", 1);
    define("LICENSE_LOBSTER", 2);
    define("LICENSE_TUNA", 3);
    define("LICENSE_PROCESSING", 4);
    define("LICENSE_PUB", 5);
    define("LICENSE_CRAB_C", 6);
    define("LICENSE_CRAB_F", 7);
    define("LICENSE_CRAB_L", 8);
    define("BOAT_SHRIMP", 9);
    define("BOAT_LOBSTER", 10);
    define("BOAT_PROCESSING", 11);
    define("BOAT_COD", 12);
    define("BOAT_TUNA", 13);
    define("BOAT_CRAB", 14);

    define("CARD_LICENSE", 'license');
    define("CARD_BOAT", 'boat');

    define("PHASE_AUCTION", "auction");
    define("PHASE_LAUNCH", "launch");
    define("PHASE_HIRE", "hire");
    define("PHASE_FISHING", "fishing");
    define("PHASE_PROCESSING", "processing");
    define("PHASE_TRADING", "trading");
    define("PHASE_DRAW", "draw");
}

$this->license_types = array(
    LICENSE_SHRIMP,
    LICENSE_COD,
    LICENSE_LOBSTER,
    LICENSE_TUNA,
    LICENSE_PROCESSING,
    LICENSE_PUB,
    LICENSE_CRAB_C,
    LICENSE_CRAB_F,
    LICENSE_CRAB_L,
);

$this->premium_license_types = array(
    LICENSE_PUB,
    LICENSE_CRAB_C,
    LICENSE_CRAB_F,
    LICENSE_CRAB_L,
);

$this->boat_types = array(
    BOAT_SHRIMP,
    BOAT_LOBSTER,
    BOAT_PROCESSING,
    BOAT_COD,
    BOAT_TUNA,
    BOAT_CRAB,
);


$this->card_types = array(
    LICENSE_SHRIMP => array(
        'name' => clienttranslate("Shrimp License"),
        'type' => CARD_LICENSE,
        'cost' => 4,
        'points' => 4,
        'nbr' => 4,
        'text' => clienttranslate('$1 off every transaction for each Shrimp License'),
    ),
    LICENSE_COD => array(
        'name' => clienttranslate("Cod License"),
        'type' => CARD_LICENSE,
        'cost' => 4,
        'points' => 4,
        'nbr' => 4,
        'text' => clienttranslate("May launch 2 boats per Launch Boats phase. May draw 1 card for each Cod License if player launches any boats"),
    ),
    LICENSE_LOBSTER => array(
        'name' => clienttranslate("Lobster License"),
        'type' => CARD_LICENSE,
        'cost' => 5,
        'points' => 3,
        'nbr' => 4,
        'text' => clienttranslate("May captain 2 boats per Hire Captains phase and draw bonus cards. With 1 license: +1/2 cards for 1-3/4+ captained boats. With 2+ licenses: +1/2/3 cards for 1-2/3-6/7+ captained boats."),
    ),
    LICENSE_TUNA => array(
        'name' => clienttranslate("Tuna License"),
        'type' => CARD_LICENSE,
        'cost' => 5,
        'points' => 3,
        'nbr' => 4,
        'text' => clienttranslate("Draw bonus 1 => 2/2; 2 => 3/2; 3 => 3/3; 4 => 4/3"),
    ),
    LICENSE_PROCESSING => array(
        'name' => clienttranslate("Processing Vessel License"),
        'type' => CARD_LICENSE,
        'cost' => 5,
        'points' => 3,
        'nbr' => 4,
        'text' => clienttranslate("May process 1 crate of fish from each boat onto and may trade 1 fish crate from Processing Vessel License for +1 card for each Processing Vessel License"),
    ),
    LICENSE_PUB => array(
        'name' => clienttranslate("Fisherman's Pub"),
        'type' => CARD_LICENSE,
        'cost' => 10,
        'points' => 10,
        'nbr' => 3,
        'text' => clienttranslate("+10VP (no bonus)"),
    ),
    LICENSE_CRAB_C => array(
        'name' => clienttranslate("King Crab License"),
        'type' => CARD_LICENSE,
        'cost' => 10,
        'points' => 5,
        'nbr' => 1,
        'text' => clienttranslate("+1VP for each captain (max 10VP)"),
    ),
    LICENSE_CRAB_F => array(
        'name' => clienttranslate("King Crab License"),
        'type' => CARD_LICENSE,
        'cost' => 10,
        'points' => 5,
        'nbr' => 1,
        'text' => clienttranslate("+1VP for each 3 fish crates (max 10VP)"),
    ),
    LICENSE_CRAB_L => array(
        'name' => clienttranslate("King Crab License"),
        'type' => CARD_LICENSE,
        'cost' => 10,
        'points' => 5,
        'nbr' => 1,
        'text' => clienttranslate("+2/4/5/6/8/10VP for 2/3/4/5/6/7 different licenses"),
    ),
    BOAT_SHRIMP => array(
        'name' => clienttranslate("Shrimp Boat"),
        'type' => CARD_BOAT,
        'license' => LICENSE_SHRIMP,
        'cost' => 1,
        'points' => 1,
        'coins' => 2,
        'nbr' => 20,
        'text' => clienttranslate(""),
    ),
    BOAT_LOBSTER => array(
        'name' => clienttranslate("Lobster Boat"),
        'type' => CARD_BOAT,
        'license' => LICENSE_LOBSTER,
        'cost' => 2,
        'points' => 2,
        'coins' => 2,
        'nbr' => 20,
        'text' => clienttranslate(""),
    ),
    BOAT_PROCESSING => array(
        'name' => clienttranslate("Processing Vessel"),
        'type' => CARD_BOAT,
        'license' => LICENSE_PROCESSING,
        'cost' => 2,
        'points' => 2,
        'coins' => 2,
        'nbr' => 20,
        'text' => clienttranslate(""),
    ),
    BOAT_COD => array(
        'name' => clienttranslate("Cod Boat"),
        'type' => CARD_BOAT,
        'license' => LICENSE_COD,
        'cost' => 2,
        'points' => 2,
        'coins' => 1,
        'nbr' => 12,
        'text' => clienttranslate(""),
    ),
    BOAT_TUNA => array(
        'name' => clienttranslate("Tuna Boat"),
        'type' => CARD_BOAT,
        'license' => LICENSE_TUNA,
        'cost' => 1,
        'points' => 1,
        'coins' => 3,
        'nbr' => 12,
        'text' => clienttranslate(""),
    ),
    BOAT_CRAB => array(
        'name' => clienttranslate("King Crab Boat"),
        'type' => CARD_BOAT,
        'license' => array(LICENSE_CRAB_C, LICENSE_CRAB_F, LICENSE_CRAB_L),
        'cost' => 3,
        'points' => 3,
        'coins' => 1,
        'nbr' => 12,
        'text' => clienttranslate(""),
    ),
);
