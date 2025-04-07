<?php
 /**
  *------
  * BGA framework: © Gregory Isabelli <gisabelli@boardgamearena.com> & Emmanuel Colin <ecolin@boardgamearena.com>
  * Fleet implementation : © Dan Marcus <bga.marcuda@gmail.com>
  * 
  * This code has been produced on the BGA studio platform for use on http://boardgamearena.com.
  * See http://en.boardgamearena.com/#!doc/Studio for more information.
  * -----
  * 
  * fleet.game.php
  *
  * This is the main file for your game logic.
  *
  * In this PHP file, you are going to defines the rules of the game.
  *
  */


require_once( APP_GAMEMODULE_PATH.'module/table/table.game.php' );


class fleet extends Table
{
    function __construct( )
    {
        
        
        
        
        
        
        parent::__construct();
        
        self::initGameStateLabels( array( 
            'fish_cubes' => 10,              
            'auction_card' => 11,            
            'current_phase' => 12,           
            'first_player' => 13,            
            'final_round' => 14,             
            'auction_winner' => 15,          
            'current_player_launches' => 16, 
            'current_player_hires' => 17,    
            'init_launch_hire_phase' => 18,  

            
            'gone_fishing' => 100,
            'fast_passing' => 101,
            'simultaneous_launch_hire' => 102
        ) );

        
        $this->cards = self::getNew("module.common.deck");
        $this->cards->init("card");

        
        
        $this->phases = array(
            PHASE_AUCTION,
            PHASE_LAUNCH,
            PHASE_HIRE,
            PHASE_FISHING,
            PHASE_PROCESSING,
            PHASE_TRADING,
            PHASE_DRAW
        );
        $this->nbr_phases = count($this->phases);
    }
        
    protected function getGameName( )
    {
        
        return "fleet";
    }   

    /*
        setupNewGame:
        
        This method is called only once, when a new game is launched.
        In this method, you must setup the game according to the game rules, so that
        the game is ready to be played.
    */
    protected function setupNewGame( $players, $options = array() )
    {    
        
        
        
        $gameinfos = self::getGameinfos();
        $default_colors = $gameinfos['player_colors'];
 
        
        
        $sql = "INSERT INTO player (player_id, player_color, player_canal, player_name, player_avatar) VALUES ";
        $values = array();
        foreach( $players as $player_id => $player )
        {
            $color = array_shift( $default_colors );
            $values[] = "('".$player_id."','$color','".$player['player_canal']."','".addslashes( $player['player_name'] )."','".addslashes( $player['player_avatar'] )."')";
        }
        $sql .= implode( $values, ',' );
        self::DbQuery( $sql );
        self::reattributeColorsBasedOnPreferences( $players, $gameinfos['player_colors'] );
        self::reloadPlayersBasicInfos();
        
        /************ Start the game initialization *****/

        
        self::setGameStateInitialValue('auction_card', 0);
        self::setGameStateInitialValue('auction_winner', 0);
        self::setGameStateInitialValue("current_phase", 0);
        self::setGameStateInitialValue("final_round", 0);
        self::setGameStateInitialValue("current_player_launches", 0);
        self::setGameStateInitialValue("current_player_hires", 0);
        self::setGameStateInitialValue("init_launch_hire_phase", 0);
        
        
        self::initStat('table', 'rounds_number', 1); 
        self::initStat('player', 'vp_total', 0);
        self::initStat('player', 'vp_boats', 0);
        self::initStat('player', 'vp_licenses', 0);
        self::initStat('player', 'vp_fish', 0);
        self::initStat('player', 'vp_bonus', 0);
        self::initStat('player', 'auctions_passed', 0);
        self::initStat('player', 'licenses_bought', 0);
        self::initStat('player', 'boats_launched', 0);
        self::initStat('player', 'captains_hired', 0);
        self::initStat('player', 'fish_gained', 0);
        self::initStat('player', 'fish_processed', 0);
        self::initStat('player', 'fish_traded', 0);
        self::initStat('player', 'cards_drawn', 0);
        self::initStat('player', 'overpaid', 0);

        
        $cards = array();
        foreach ($this->card_types as $idx => $card) {
            $cards[] = array(
                'type' => $card['type'],
                'type_arg' => $idx,
                'nbr' => $card['nbr']
            );
        }
        $this->cards->createCards($cards);

        
        if ($this->optGoneFishing()) {
            $loc = 'gonefishing';
        } else {
            $loc = 'box';
        }
        $cards = $this->cards->getCardsOfType(CARD_BONUS);
        $this->cards->moveCards(array_column($cards, 'id'), $loc);

        
        $licenses = $this->cards->getCardsOfType(CARD_LICENSE);
        $this->cards->moveCards(array_column($licenses, 'id'), 'licenses');

        
        
        $nbr_players = count($players);
        foreach ($this->premium_license_types as $type_arg) {
            $cards = $this->cards->getCardsOfType(CARD_LICENSE, $type_arg);
            $this->cards->moveCards(array_column($cards, 'id'), 'setup_premium');
        }
        $nbr_common = $nbr_players == 2 ? 10 : 8;
        $this->cards->pickCardsForLocation($nbr_common, 'licenses', 'setup_common');
        $this->cards->shuffle('setup_premium');
        $this->cards->shuffle('setup_common');

        
        if ($nbr_players == 2) {
            $this->cards->pickCardsForLocation(3, 'setup_premium', 'box');
            $this->cards->pickCardsForLocation(6, 'setup_common', 'box');
        } else if ($nbr_players == 3) {
            $this->cards->pickCardsForLocation(2, 'setup_premium', 'box');
            $this->cards->pickCardsForLocation(2, 'setup_common', 'box');
        }

        
        $this->cards->moveAllCardsInLocation('setup_premium', 'licenses');
        $this->cards->shuffle('licenses');
        foreach ($this->cards->getCardsInLocation('setup_common') as $card) {
            $this->cards->insertCardOnExtremePosition($card['id'], 'licenses', true);
        }

        
        $this->cards->pickCardsForLocation($nbr_players, 'licenses', 'auction');

        
        foreach ($this->boat_types as $type_arg) {
            $cards = $this->cards->getCardsOfType(CARD_BOAT, $type_arg);
            foreach ($players as $player_id => $player) {
                $this->cards->moveCard(array_shift($cards)['id'], 'hand', $player_id);
            }
        }

        
        $this->cards->shuffle('deck');
        //$this->cards->autoreshuffle = true; //XXX not working?! see drawCards

        
        self::setGameStateInitialValue("fish_cubes", $nbr_players * 25);

        
        $player_id = $this->activeNextPlayer();
        self::setGameStateInitialValue('first_player', $player_id);

        /************ End of the game initialization *****/
    }

    /*
        getAllDatas: 
        
        Gather all informations about current game situation (visible by the current player).
        
        The method is called each time the game interface is displayed to a player, ie:
        _ when the game starts
        _ when a player refreshes the game page (F5)
    */
    protected function getAllDatas()
    {
        $result = array();
    
        $current_player_id = self::getCurrentPlayerId();    
    
        
        
        $sql = "SELECT player_id id, player_score score, auction_bid bid, auction_pass pass, passed done FROM player ";
        $result['players'] = self::getCollectionFromDb( $sql );
        $result['first_player'] = self::getGameStateValue('first_player');

        
        $players = self::loadPlayersBasicInfos();
        $boats = array();
        $licenses = array();
        $fish = array();
        $hands = array();
        foreach ($players as $player_id => $player) {
            $boats[$player_id] = $this->getBoats($player_id);
            $licenses[$player_id] = $this->getLicenses($player_id);
            $fish[$player_id] = $this->getFishCrates($player_id);
            $hands[$player_id] = count($this->cards->getPlayerHand($player_id));
        }
        $result['boats'] = $boats;
        $result['licenses'] = $licenses;
        $result['processed_fish'] = $fish;
        $result['hand_cards'] = $hands;
        $result['draw'] = $this->cards->getCardsInLocation('draw', $current_player_id);

        
        $result['hand'] = $this->cards->getPlayerHand($current_player_id);
        $result['coins'] = $this->getCoins($current_player_id);
        $result['moves'] = $this->possibleMoves($current_player_id, $this->getCurrentPhase());

        
        $result['discount'] = count($this->getLicenses($current_player_id, LICENSE_SHRIMP));

        
        $result['cards'] = $this->cards->countCardsInLocations();

        
        $result['auction'] = $this->cards->getCardsInLocation('auction');
        $result['auction_card'] = self::getGameStateValue('auction_card');
        $result['auction_winner'] = self::getGameStateValue('auction_winner');
        $result['auction_bid'] = $this->getHighBid();

        
        $result['fish_cubes'] = self::getGameStateValue('fish_cubes');

        
        $result['card_infos'] = $this->card_types;
        $result['constants'] = array(
            'shrimp' => LICENSE_SHRIMP,
            'tuna' => LICENSE_TUNA,
        );

        
        $result['gone_fishing'] = $this->optGoneFishing();
  
        return $result;
    }

    /*
        getGameProgression:
        
        Compute and return the current game progression.
        The number returned must be an integer beween 0 (=the game just started) and
        100 (= the game is finished or almost finished).
    
        This method is called each time we are in a game state with the "updateGameProgression" property set to true 
        (see states.inc.php)
    */
    function getGameProgression()
    {
        
        

        
        $nbr_players = self::getPlayersNumber();
        if ($nbr_players == 2) {
            $nbr_lic = 17;
            $nbr_fish = 50;
        } else if ($nbr_players == 3) {
            $nbr_lic = 17;
            $nbr_fish = 75;
        } else {
            $nbr_lic = 26;
            $nbr_fish = 100;
        }

        
        $lic_prog = $this->cards->countCardInLocation('licenses') + $this->cards->countCardInLocation('auction');
        $lic_prog = $lic_prog / $nbr_lic;

        
        $fish_prog = self::getGameStateValue('fish_cubes') / $nbr_fish;

        
        return 100 * (1 - min($lic_prog, $fish_prog));
    }


//////////////////////////////////////////////////////////////////////////////
//////////
//////////

    /*
     * Return an array of players in natural turn order starting
     * with the current player. This is used to build the player
     * tables in the same order as the player boards.
     */
    function getPlayersInOrder()
    {
        $result = array();

        $players = self::loadPlayersBasicInfos();
        $next_player = self::getNextPlayerTable();
        $player_id = self::getCurrentPlayerId();

        
        if (!key_exists($player_id, $players)) {
            $player_id = $next_player[0];
        }

        
        for ($i=0; $i<count($players); $i++) {
            $result[] = $player_id;
            $player_id = $next_player[$player_id];
        }

        return $result;
    }

    function getAdjustedPhase($phase)
    {
        if ( $this->optSimultaneousLaunchHire() && ( $phase == PHASE_LAUNCH || $phase == PHASE_HIRE ) ) {            
            $phase = PHASE_LAUNCH_HIRE;
            if ( self::getGameStateValue("init_launch_hire_phase") == 0 ) {   //first time we want to transition to "GAME_launch_hire" and not launch_hire
                $phase = PHASE_GAME_LAUNCH_HIRE;
            }
        }
        return $phase;
    }

    function nextPhase()
    {
        self::DbQuery("UPDATE player SET passed = 0"); 
        $phase = self::incGameStateValue('current_phase', 1) % $this->nbr_phases;
        return $this->getAdjustedPhase($this->phases[$phase]);
    }

    /*
     * Decrements the current phase counter and return name of new phase
     * This allows one player to complete multiple phases (e.g. launch boats and
     * hire captains) and then return to the correct phase for the next player
     */
    function prevPhase()
    {
        self::DbQuery("UPDATE player SET passed = 0"); 
        $phase = self::incGameStateValue('current_phase', -1) % $this->nbr_phases;
        return $this->getAdjustedPhase($this->phases[$phase]);
    }

    /*
     * Returns the name of the current phase
     */
    function getCurrentPhase()
    {
        $phase = self::getGameStateValue('current_phase') % $this->nbr_phases;
        $phase = $this->getAdjustedPhase($this->phases[$phase]);
        if ( $phase == PHASE_LAUNCH_HIRE ) {      //if phase is LAUNCH_HIRE then switch it to HIRE or LAUNCH based on current player
            $player_id = self::getCurrentPlayerId();
            $launch_hire_phase = self::getUniqueValueFromDB("SELECT launch_hire_phase FROM player WHERE player_id = {$player_id}");
            $phase = $launch_hire_phase == 0 ? PHASE_LAUNCH : PHASE_HIRE;
        }
        return $phase;
    }

    function getPlayerIdForAction()
    {
        $player_id = self::getActivePlayerId();
        if ( $this->optSimultaneousLaunchHire() ) {
            $player_id = self::getCurrentPlayerId();
        }
        return $player_id;
    }


    function getCardInfo($card)
    {
        return $this->card_types[$card['type_arg']];
    }

    function getCardInfoById($card_id)
    {
        $card = $this->cards->getCard($card_id);
        return $this->getCardInfo($card);
    }

    function getCardName($card)
    {
        return $this->getCardInfo($card)['name'];
    }

    function canBid($player_id)
    {
        $sql = "SELECT (auction_pass + passed) AS passed FROM player WHERE player_id = $player_id";
        return self::getUniqueValueFromDB($sql) == 0;
    }

    function hasPassed($player_id)
    {
        $sql = "SELECT passed FROM player WHERE player_id = $player_id";
        return self::getUniqueValueFromDB($sql) == 1;
    }

    function getBoats($player_id)
    {
        $sql = "SELECT";
        
        foreach (array('id', 'type', 'type_arg', 'location', 'location_arg') as $col) {
            $sql .= " card_$col AS $col,";
        }
        
        $sql .= ' nbr_fish AS fish, has_captain FROM card';
        $sql .= " WHERE card_location = 'table' AND card_location_arg = $player_id AND card_type = '" . CARD_BOAT . "'";
        return self::getCollectionFromDB($sql);
    }

    function getCoins($player_id)
    {
        $coins = 0;        
        $cards = $this->cards->getPlayerHand($player_id);
        foreach ($cards as $card) {
            $card_info = $this->getCardInfo($card);
            $coins += $card_info['coins'];
        }

        
        $cards = $this->cards->getCardsInLocation('draw', $player_id);
        foreach ($cards as $card) {
            $card_info = $this->getCardInfo($card);
            $coins += $card_info['coins'];
        }

        
        $coins += $this->getFishCrates($player_id);

        
        
        $shrimp = $this->getLicenses($player_id, LICENSE_SHRIMP);
        $coins += count($shrimp);

        return $coins;
    }

    function getLicenses($player_id, $type_arg=null)
    {
        return $this->cards->getCardsOfTypeInLocation(CARD_LICENSE, $type_arg, 'table', $player_id);
    }

    function getFishCrates($player_id)
    {
        return self::getUniqueValueFromDB("SELECT fish_crates FROM player WHERE player_id = $player_id");
    }

    function incFishCrates($player_id, $inc)
    {
        if ($inc == 0) {
            return;
        }
        self::DbQuery("UPDATE player SET fish_crates = fish_crates + '$inc' WHERE player_id = $player_id");
    }

    function getHighBid()
    {
        return self::getUniqueValueFromDB("SELECT MAX(auction_bid) AS high_bid FROM player");
    }

    function getNumberOfLaunches( $player_id )
    {
        $result = 0;
        if ( $this->optSimultaneousLaunchHire() ) {
            $result = self::getUniqueValueFromDB( "SELECT nbr_launch_hire FROM player WHERE player_id = {$player_id}" );
        } else {
            $result = self::getGameStateValue('current_player_launches');
        }
        return $result;
    }

    function getNumberOfHires( $player_id )
    {
        $result = 0;
        if ( $this->optSimultaneousLaunchHire() ) {
            $result = self::getUniqueValueFromDB( "SELECT nbr_launch_hire FROM player WHERE player_id = {$player_id}" );
        } else {
            $result = self::getGameStateValue('current_player_hires');
        }
        return $result;
    }

    function possibleMoves($player_id, $phase)
    {
        $moves = array();
        if ($phase == PHASE_AUCTION) {
            

            if (self::getGameStateValue('auction_winner')) {
                
                
                $moves = $this->cards->getPlayerHand($player_id);
            } else if (!self::getGameStateValue('auction_card')) {
                
                
                $coins = $this->getCoins($player_id);
                $cards = $this->cards->getCardsInLocation('auction');
                foreach ($cards as $card_id => $card) {
                    $card_info = $this->getCardInfo($card);
                    if ($coins >= $card_info['cost']) {
                        $moves[$card_id] = true;
                    }
                }
            } else {
                if ($this->getCoins($player_id) > $this->getHighBid()) {
                    $moves[] = true;
                }
            }
        } else if ($phase == PHASE_LAUNCH) {
            

            $coins = $this->getCoins($player_id);
            $cards = $this->cards->getPlayerHand($player_id);
            $licenses = array_column($this->getLicenses($player_id), 'type_arg');

            foreach ($cards as $card_id => $card) {
                $move = array('can_play' => false);
                $card_info = $this->getCardInfo($card);

                
                if ($card['type'] == CARD_BONUS) {
                    
                    $move['error'] = clienttranslate("That's not a boat!");
                } else if (!$this->isLicenseInList($card['type_arg'], $card_info['license'], $licenses)) {
                    
                    $move['error'] = clienttranslate('You do not have the required license');
                } else if (($coins - $card_info['coins']) < $card_info['cost']) {
                    
                    $move['error'] = clienttranslate('You cannot afford this boat');
                } else {
                    
                    $move['can_play'] = true;
                }

                $moves[$card_id] = $move;
            }
        } else if ($phase == PHASE_HIRE) {
            
            

            
            $cards = $this->cards->getPlayerHand($player_id);
            foreach ($cards as $card_id => $card) {
                if ($card['type'] == CARD_BOAT) {
                    $moves[$card_id] = true;
                    $moves['has_boat'] = true;
                }
            }

            
            $boats = $this->getBoats($player_id);
            foreach ($boats as $card_id => $boat) {
                if (!$boat['has_captain']) {
                    $moves[$card_id] = true;
                    $moves['has_captain'] = true;
                }
            }
        } else if ($phase == PHASE_PROCESSING) {
            if ($this->hasPassed($player_id)) {
                
                $moves[] = true;
            } else {
                
                $boats = $this->getBoats($player_id);
                foreach ($boats as $card_id => $boat) {
                    if ($boat['fish'] > 0) {
                        $moves[$card_id] = true;
                    }
                }
            }
        } else if ($phase == PHASE_TRADING) {
            $moves[] = true; 
        } else if ($phase == PHASE_DRAW) {
            
            $cards = $this->cards->getCardsInLocation('draw', $player_id);
            if (count($cards) == 0) {
                
                $cards = $this->cards->getPlayerHand($player_id);
            }
            foreach ($cards as $card_id => $card) {
                $moves[$card_id] = true;
            }
        }

        return $moves;
    }

    function isLicenseInList($card_type, $license_type, $licenses)
    {
        if ($card_type == BOAT_CRAB) {
            
            foreach ($license_type as $crab_type) {
                if (in_array($crab_type, $licenses)) {
                    return true;
                }
            }
            return false;
        } else {
            return in_array($license_type, $licenses);
        }
    }

    function incScore($player_id, $inc)
    {
        if ($inc == 0) {
            return;
        }
        self::DbQuery("UPDATE player SET player_score = player_score + '$inc' WHERE player_id = $player_id");
    }

    function optGoneFishing()
    {
       return $this->gamestate->table_globals[100] == 1;
    }

    function optFastPassing()
    {
       return $this->gamestate->table_globals[101] == 2;
    }

    function optSimultaneousLaunchHire()
    {
        return $this->gamestate->table_globals[102] == 2;
    }


//////////////////////////////////////////////////////////////////////////////
//////////
//////////

    function pass()
    {
        self::checkAction('pass');

        $state = $this->gamestate->state();
        if ($state['type'] == 'multipleactiveplayer') {
            $player_id = self::getCurrentPlayerId();
        } else {
            $player_id = self::getActivePlayerId();
        }

        $this->passCheckAndNotify($player_id);

        if ($state['type'] == 'multipleactiveplayer') {
            $this->gamestate->setPlayerNonMultiactive($player_id, '');
        } else {
            $this->gamestate->nextState();
        }
    }

    function passCheckAndNotify($player_id)
    {
        $in_auction = false;
        $auction_done = false;
        $msg = clienttranslate('${player_name} passes');
        $card = null;

        if ($this->getCurrentPhase() == PHASE_AUCTION) {
            
            $in_auction = true;

            
            $sql = 'UPDATE player SET auction_bid = 0, auction_pass = 1';

            if (self::getGameStateValue('auction_card') == 0) {
                
                $sql .= ', passed = 1';
                $auction_done = true; 

                if ($this->optGoneFishing()) {
                    
                    $card = $this->cards->pickCard('gonefishing', $player_id);
                    if ($card != null) {
                        //TODO: what if none left?
                        $msg = clienttranslate('${player_name} skips the auction to go fishing');
                    }
                }

                self::incStat(1, 'auctions_passed', $player_id);
            }

            $sql .= " WHERE player_id = $player_id";
            self::DbQuery($sql);
        } else {
            
            
            self::DbQuery("UPDATE player SET passed = 1 WHERE player_id = $player_id");
        }

        $players = self::loadPlayersBasicInfos(); 
        self::notifyAllPlayers('pass', $msg, array(
            'player_name' => $players[$player_id]['player_name'],
            'player_id' => $player_id,
            'in_auction' => $in_auction,
            'auction_done' => $auction_done,
            'card' => $card,
        ));
    }

    function bid($current_bid, $card_id=-1)
    {
        self::checkAction('bid');

        $player_id = self::getActivePlayerId();

        
        if (!$this->canBid($player_id)) {
            throw new feException("Player is not active in this auction");
        }

        if ($card_id > 0) {
            
            
            if (self::getGameStateValue('auction_card') > 0) {
                throw new feException("Impossible bid state");
            }

            
            $card = $this->cards->getCard($card_id);
            if ($card == null || $card['location'] != 'auction') {
                throw new feException("Impossible bid action");
            }

            
            $card_info = $this->getCardInfo($card);
            if ($current_bid < $card_info['cost']) {
                $cost = $card_info['cost'];
                throw new BgaUserException(self::_("You must bid at least {$cost}"));
            }

            
            self::setGameStateValue('auction_card', $card_id);
        } else {
            
            
            if (self::getGameStateValue('auction_card') == 0) {
                throw new feException("Impossible bid without license");
            }
        }

        
        $high_bid = $this->getHighBid();
        if ($current_bid <= $high_bid) {
            $min_bid = $high_bid + 1;
            throw new BgaUserException(self::_("You must bid at least {$min_bid}"));
        }

        
        $coins = $this->getCoins($player_id);
        if ($coins < $current_bid) {
            throw new BgaUserException(self::_("You cannot afford that bid"));
        }

        
        $sql = "UPDATE player SET auction_bid = $current_bid WHERE player_id = $player_id";
        self::DbQuery($sql);

        
        if ($card_id > 0) {
            
            $msg = clienttranslate('${player_name} selects ${card_name} for auction');
            self::notifyAllPlayers('auctionSelect', $msg, array(
                'i18n' => array('card_name'),
                'player_name' => self::getActivePlayerName(),
                'card_name' => $this->getCardName($card),
                'card_id' => $card_id,
            ));
        }
        $msg = clienttranslate('${player_name} bids ${bid}');
        self::notifyAllPlayers('auctionBid', $msg, array(
            'player_name' => self::getActivePlayerName(),
            'bid' => $current_bid,
            'player_id' => $player_id,
        ));

        $this->gamestate->nextState();
    }

    function buyLicense($card_ids, $fish=0)
    {
        self::checkAction('buyLicense');

        $player_id = self::getActivePlayerId();
        $coins = $fish; 

        

        
        if ($player_id != self::getGameStateValue('auction_winner')) {
            throw new feException("Impossible buy: not winner");
        }

        
        $license_id = self::getGameStateValue('auction_card');
        if ($license_id == 0) {
            throw new feException("Impossible buy: no license");
        }
        $license = $this->cards->getCard($license_id);
        if ($license == null || $license['location'] != 'auction') {
            throw new feException("Impossible buy: non-auction license");
        }
        $license_info = $this->getCardInfo($license);

        
        foreach ($card_ids as $card_id) {
            $card = $this->cards->getCard($card_id);
            if ($card == null || $card['location'] != 'hand' || $card['location_arg'] != $player_id) {
                throw new feException("Invalid card id for purchase: $card_id");
            }

            $card_info = $this->getCardInfo($card);
            $coins += $card_info['coins'];
        }

        if ($fish > 0) {
            
            if ($this->getFishCrates($player_id) < $fish) {
                throw new feException("Impossible buy: too many fish");
            }
        }

        
        $discount = count($this->getLicenses($player_id, LICENSE_SHRIMP));

        
        $bid = $this->getHighBid();
        if ($bid < $license_info['cost']) {
            throw new feException("Impossible buy: low bid");
        }

        
        if ($coins < ($bid - $discount)) {
            throw new feException("Impossible buy: not enough");
        }

        

        
        foreach ($card_ids as $card_id) {
            $card = $this->cards->getCard($card_id);
            if ($card['type'] == CARD_BONUS) {
                $this->cards->moveCard($card_id, 'gonefishing');
            } else {
                $this->cards->playCard($card_id);
            }
        }
        if ($fish > 0) {
            $this->incFishCrates($player_id, -$fish);
        }

        
        $this->cards->moveCard($license_id, 'table', $player_id);
        $this->incScore($player_id, $license_info['points']);

        
        self::incStat(1, 'licenses_bought', $player_id);
        self::incStat($license_info['points'], 'vp_licenses', $player_id);
        self::incStat($license_info['points'], 'vp_total', $player_id);
        $overpay = $coins + $discount - $bid;
        if ($overpay > 0 && $coins > 0) {
            self::incStat($overpay, 'overpaid', $player_id);
        }

        
        self::setGameStateValue('auction_card', 0);
        self::setGameStateValue('auction_winner', 0);
        $sql = 'UPDATE player SET auction_bid = 0, auction_pass = 1, passed = 1';
        $sql .= " WHERE player_id = $player_id";
        self::DbQuery($sql);

        
        if ($fish > 0) {
            $msg = clienttranslate('${player_name} discards ${nbr_cards} card(s) and ${nbr_fish} fish crate(s) for $${coins}');
        } else {
            $msg = clienttranslate('${player_name} discards ${nbr_cards} card(s) for $${coins}');
        }
        if ($discount > 0) {
            $msg .= ' (' . clienttranslate("$$discount Shrimp License discount") . ')';
        }
        self::notifyAllPlayers('buyLicense', $msg, array(
            'player_name' => self::getActivePlayerName(),
            'nbr_cards' => count($card_ids),
            'nbr_fish' => $fish,
            'coins' => $coins,
            'player_id' => $player_id,
            'card_ids' => $card_ids,
            'license_id' => $license_id,
            'license_type' => $license['type_arg'],
            'points' => $license_info['points'],
            'discards' => $this->cards->countCardsInLocation('discard'),
        ));

        $this->gamestate->nextState();
    }

    function launchBoat($boat_id, $card_ids, $fish=0)
    {
        self::checkAction('launchBoat');

        $player_id = $this->getPlayerIdForAction();
        $coins = $fish; 

        $boat = $this->cards->getCard($boat_id);
        if ($boat == null || $boat['location'] != 'hand' || $boat['location_arg'] != $player_id) {
            throw new feException("Impossible launch: invalid boat");
        }
        $boat_info = $this->getCardInfo($boat);

        
        if ($boat['type_arg'] == BOAT_CRAB) {
            
            $licenses1 = $this->getLicenses($player_id, LICENSE_CRAB_C);
            $licenses2 = $this->getLicenses($player_id, LICENSE_CRAB_F);
            $licenses3 = $this->getLicenses($player_id, LICENSE_CRAB_L);
            $licenses = array_merge($licenses1, $licenses2, $licenses3);
        } else {
            $licenses = $this->getLicenses($player_id, $boat_info['license']);
        }
        if (count($licenses) == 0) {
            throw new feException("Impossible launch: missing license");
        }

        
        foreach ($card_ids as $card_id) {
            $card = $this->cards->getCard($card_id);
            if ($card == null || $card['location'] != 'hand' || $card['location_arg'] != $player_id) {
                throw new feException("Invalid card id for purchase: $card_id");
            }

            $card_info = $this->getCardInfo($card);
            $coins += $card_info['coins'];
        }

        if ($fish > 0) {
            
            if ($this->getFishCrates($player_id) < $fish) {
                throw new feException("Impossible launch: too many fish");
            }
        }

        
        $discount = count($this->getLicenses($player_id, LICENSE_SHRIMP));

        
        if ($coins < ($boat_info['cost'] - $discount)) {
            throw new feException("Impossible launch: not enough");
        }

        

        
        foreach ($card_ids as $card_id) {
            $card = $this->cards->getCard($card_id);
            if ($card['type'] == CARD_BONUS) {
                $this->cards->moveCard($card_id, 'gonefishing');
            } else {
                $this->cards->playCard($card_id);
            }
        }
        if ($fish > 0) {
            $this->incFishCrates($player_id, -$fish);
        }

        
        $this->cards->moveCard($boat_id, 'table', $player_id);
        $this->incScore($player_id, $boat_info['points']);
        $nbr_launches = self::incGameStateValue('current_player_launches', 1);
        if ( $this->optSimultaneousLaunchHire() ) {
            self::DbQuery( "UPDATE player SET nbr_launch_hire = nbr_launch_hire + 1 WHERE player_id = {$player_id}" );
        }

        
        self::incStat(1, 'boats_launched', $player_id);
        self::incStat($boat_info['points'], 'vp_boats', $player_id);
        self::incStat($boat_info['points'], 'vp_total', $player_id);
        $overpay = $coins + $discount - $boat_info['cost'];
        if ($overpay > 0 && $coins > 0) {
            self::incStat($overpay, 'overpaid', $player_id);
        }

        
        if ($fish > 0) {
            $msg = clienttranslate('${player_name} launches a ${card_name} and discards ${nbr_cards} card(s) and ${nbr_fish} fish crate(s) for $${coins}');
        } else {
            $msg = clienttranslate('${player_name} launches a ${card_name} and discards ${nbr_cards} card(s) for $${coins}');
        }
        if ($discount > 0) {
            $msg .= ' (' . clienttranslate("$$discount Shrimp License discount") . ')';
        }
        if ($nbr_launches == 2) {
            $msg = '${bonus}: ' . $msg;
        }
        self::notifyAllPlayers('launchBoat', $msg, array(
            'i18n' => array('card_name', 'bonus'),
            'player_name' => self::getActivePlayerName(),
            'nbr_cards' => count($card_ids),
            'nbr_fish' => $fish,
            'coins' => $coins,
            'card_name' => $boat_info['name'],
            'bonus' => $this->card_types[LICENSE_COD]['name'],
            'player_id' => $player_id,
            'boat_id' => $boat_id,
            'boat_type' => $boat['type_arg'],
            'card_ids' => $card_ids,
            'points' => $boat_info['points'],
            'discards' => $this->cards->countCardsInLocation('discard'),
        ));

        $this->gamestate->nextState();
    }

    function hireCaptain($boat_id, $card_id)
    {
        self::checkAction('hireCaptain');

        $player_id = $this->getPlayerIdForAction();
        $card = $this->cards->getCard($card_id);
        if ($card == null || $card['location'] != 'hand' ||
            $card['location_arg'] != $player_id || $card['type'] != CARD_BOAT)
        {
            throw new feException("Impossible hire: invalid card");
        }        
        $boat = $this->cards->getCard($boat_id);
        if ($boat == null || $boat['location'] != 'table' || $boat['location_arg'] != $player_id) {
            throw new feException("Impossible hire: invalid boat");
        }

        $sql = "SELECT has_captain FROM card WHERE card_id = $boat_id";
        if (self::getUniqueValueFromDB($sql)) {
            throw new feException("Impossible hire: already captained");
        }

        

        
        $this->cards->moveCard($card_id, 'captain', $boat_id);
        self::DbQuery("UPDATE card SET has_captain = 1 WHERE card_id = $boat_id");
        $nbr_hires = self::incGameStateValue('current_player_hires', 1);        
        if ( $this->optSimultaneousLaunchHire() ) {
            self::DbQuery( "UPDATE player SET nbr_launch_hire = nbr_launch_hire + 1 WHERE player_id = {$player_id}" );
        }
        self::incStat(1, 'captains_hired', $player_id);

        
        $msg = clienttranslate('${player_name} hires a captain for their ${card_name}');
        if ($nbr_hires == 2) {
            $msg = '${bonus}: ' . $msg;
        }
        self::notifyAllPlayers('hireCaptain', $msg, array(
            'i18n' => array('card_name', 'bonus'),
            'player_name' => self::getActivePlayerName(),
            'card_name' => $this->getCardName($boat),
            'bonus' => $this->card_types[LICENSE_LOBSTER]['name'],
            'player_id' => $player_id,
            'boat_id' => $boat_id,
            'card_id' => $card_id,
        ));

        $this->gamestate->nextState();
    }

    function processFish($card_ids)
    {
        self::checkAction('processFish');

        $player_id = self::getCurrentPlayerId(); 
        $nbr_fish = count($card_ids);
        $license = $this->getLicenses($player_id, LICENSE_PROCESSING);
        if (count($license) == 0) {
            throw new feException("Impossible process: no license");
        }

        
        $boats = $this->getBoats($player_id);
        foreach ($card_ids as $card_id) {
            $boat = $boats[$card_id];
            if ($boat == null) { 
                throw new feException("Impossible process: invalid card $card_id");
            }

            if (!$boat['has_captain'] || $boat['fish'] == 0) {
                throw new feException("Impossible process: no fish");
            }

            
            
            self::DbQuery("UPDATE card SET nbr_fish = nbr_fish - 1 WHERE card_id = $card_id");
        }

        
        $this->incFishCrates($player_id, $nbr_fish);
        $this->incScore($player_id, -$nbr_fish);

        
        self::incStat($nbr_fish, 'fish_processed', $player_id);
        self::incStat(-$nbr_fish, 'vp_fish', $player_id);
        self::incStat(-$nbr_fish, 'vp_total', $player_id);

        
        $msg = clienttranslate('${player_name} processes ${nbr_fish} fish crate(s)');
        self::notifyAllPlayers('processFish', $msg, array(
            'player_name' => self::getCurrentPlayerName(), 
            'nbr_fish' => $nbr_fish,
            'card_ids' => $card_ids,
            'player_id' => $player_id,
        ));

        if ($this->skipPlayer($player_id, PHASE_TRADING)) {
            
            $this->gamestate->setPlayerNonMultiactive($player_id, '');
        } else {
            
            self::DbQuery("UPDATE player SET passed = 1 WHERE player_id = $player_id");
        }
    }

    function tradeFish()
    {
        self::checkAction('tradeFish');
        $player_id = self::getCurrentPlayerId(); 

        

        
        $license = $this->getLicenses($player_id, LICENSE_PROCESSING);
        $nbr_license = count($license);
        if ($nbr_license == 0) {
            throw new feException("Impossible trading: no license");
        }
        $fish = $this->getFishCrates($player_id);
        if ($fish == 0) {
            throw new feException("Impossible trading: no fish");
        }

        
        $this->incFishCrates($player_id, -1);
        self::incStat(1, 'fish_traded', $player_id);

        
        $msg = clienttranslate('${player_name} trades a fish crate');
        self::notifyAllPlayers('tradeFish', $msg, array(
            'player_name' => self::getCurrentPlayerName(), 
            'nbr_cards' => $nbr_license,
            'player_id' => $player_id,
        ));

        
        
        $this->drawCards($player_id, $nbr_license, 'hand', $this->card_types[LICENSE_PROCESSING]['name']);

        
        $this->gamestate->setPlayerNonMultiactive($player_id, '');
    }

    function discard($card_id)
    {
        self::checkAction('discard');

        $player_id = self::getCurrentPlayerId(); 

        
        $bonus = count($this->getLicenses($player_id, LICENSE_TUNA));
        $loc = $bonus > 0 ? 'hand' : 'draw';

        
        $card = $this->cards->getCard($card_id);
        if ($card == null || $card['location'] != $loc || $card['location_arg'] != $player_id) {
            throw new feException("Impossible discard: invalid card $card_id");
        }

        
        $this->cards->playCard($card_id);
        $this->cards->moveAllCardsInLocation('draw', 'hand', $player_id, $player_id);

        
        self::notifyAllPlayers('discardLog', clienttranslate('${player_name} discards a card'), array(
            'player_name' => self::getCurrentPlayerName(), 
            'player_id' => $player_id,
        ));
        self::notifyPlayer($player_id, 'discard', '', array(
            'discard' => $card,
        ));

        
        $this->gamestate->setPlayerNonMultiactive($player_id, '');
    }

    
//////////////////////////////////////////////////////////////////////////////
//////////
////////////

    function argsLaunchHire()
    {
        $player_sub_phases = self::getCollectionFromDB( "SELECT player_id, launch_hire_phase FROM player" );        
        foreach( $player_sub_phases as $player_id => &$player ) {
            if ( $player["launch_hire_phase"] == 0 )  {
                $player["possible_moves"] = $this->possibleMoves( $player_id, PHASE_LAUNCH );
            } else {
                $player["possible_moves"] = $this->possibleMoves( $player_id, PHASE_HIRE );
            }
        }
        return array(
            'reset_client_player_id' => self::getCurrentPlayerId(),
            'players' => $player_sub_phases,
        );
    }

    function argsProcessing() {
        $player_id = self::getCurrentPlayerId();
        return array(
            'moves' => $this->possibleMoves($player_id, PHASE_PROCESSING),
            'trade' => $this->hasPassed($player_id), 
        );
    }

//////////////////////////////////////////////////////////////////////////////
//////////
////////////
    function stNextPlayer()
    {
        
        $player_and_state = $this->activeNextPlayerPhase();
        $player_id = $player_and_state[0];
        $next_state = $player_and_state[1];
        $extra_time = $player_and_state[2];

        
        if ($next_state == PHASE_FISHING) {
            
            $next_state = $this->doFishing();
        }

        if ($next_state == PHASE_GAME_LAUNCH_HIRE_FINISH) {  //noop, let the state machine manage transition
            return;
        }
        
        else if ($next_state == PHASE_PROCESSING || $next_state == PHASE_DRAW || $next_state == PHASE_GAME_LAUNCH_HIRE || $next_state == PHASE_LAUNCH_HIRE) {
            $this->gamestate->nextState($next_state);
            return;
        }

        if ($this->skipPlayer($player_id, $next_state)) {
            
            if ($this->optFastPassing()) {
                
                
                if ($next_state == PHASE_AUCTION || $next_state == PHASE_LAUNCH) {
                    $this->passCheckAndNotify($player_id);
                }
            }
            $next_state = 'cantPlay';
        } else {
            
            self::notifyPlayer($player_id, 'possibleMoves', '', array(
                'moves' => $this->possibleMoves($player_id, $next_state),
                'coins' => $this->getCoins($player_id),
            ));

            
            if ($extra_time) {
                self::giveExtraTime($player_id);
            }
        }

        $this->gamestate->nextState($next_state);
    }

    function stGameLaunchHire()
    {        
        $players = self::loadPlayersBasicInfos();
        $active_players = array();
        foreach ($players as $player_id => $player) {
            $can_launch = !$this->skipPlayer( $player_id, PHASE_LAUNCH );
            $can_hire = !$this->skipPlayer( $player_id, PHASE_HIRE );
            if ( $can_launch || $can_hire ) {
                if ( !$can_launch ) {
                    self::DbQuery("UPDATE player SET launch_hire_phase = 1 WHERE player_id = {$player_id}");        //jump straight to hire phase for this specific player
                }
                $active_players[] = $player_id;
            }
        }
        self::setGameStateValue("init_launch_hire_phase", 1);
        $this->gamestate->setPlayersMultiactive($active_players, "no_players", true);
        if ( sizeof( $active_players ) > 0 ) {  //transition if there are players
            $this->gamestate->nextState("players");
        }
    }

    function stGameLaunchHireFinish()
    {                        
        $this->nextPhase();     
        $this->nextPhase();     
        self::DbQuery("UPDATE player SET nbr_launch_hire = 0, launch_hire_phase = 0");     //reset launch_hire count
        self::setGameStateValue("init_launch_hire_phase", 0);   //reset launch hire init flag                
        $this->gamestate->nextState("");
    }

    function stProcessing()
    {
        $players = self::loadPlayersBasicInfos();
        $active_players = array();
        foreach ($players as $player_id => $player) {
            if (!$this->skipPlayer($player_id, PHASE_PROCESSING) ||
                !$this->skipPlayer($player_id, PHASE_TRADING))
            {
                $active_players[] = $player_id;
            }
        }

        
        $this->gamestate->setPlayersMultiactive($active_players, '', true);
    }

    function stDraw()
    {
        
        $player_id = self::getGameStateValue('first_player');
        $next_player = self::getNextPlayerTable();
        $active_players = array();

        for ($i = 0; $i < self::getPlayersNumber(); $i++) {
            
            $this->drawPhase($player_id);

            if (!$this->skipPlayer($player_id, PHASE_DRAW)) {
                
                $active_players[] = $player_id;
                self::giveExtraTime($player_id);

                
                self::notifyPlayer($player_id, 'possibleMoves', '', array(
                    'moves' => $this->possibleMoves($player_id, PHASE_DRAW),
                    'coins' => $this->getCoins($player_id),
                ));
            }

            
            $player_id = $next_player[$player_id];
        }

        
        $this->gamestate->setPlayersMultiactive($active_players, '', true);
    }
    function activeNextPlayerPhase()
    {
        $current_player = self::getActivePlayerId();
        $next_player = $current_player;

        $current_phase = $this->getCurrentPhase();
        $next_phase = $current_phase;

        $extra_time = true;

        
        
        if ($current_phase == PHASE_AUCTION) {
            
            return $this->nextAuction();
        } else if ($current_phase == PHASE_LAUNCH) {
            
            if (!$this->nextLaunch() && $this->optSimultaneousLaunchHire() == false) {
                
                $next_phase = $this->nextPhase();
            } else if (!$this->nextLaunch() && $this->optSimultaneousLaunchHire()) {
                $current_player_id = self::getCurrentPlayerId();
                self::DbQuery("UPDATE player SET launch_hire_phase = 1, nbr_launch_hire = 0 WHERE player_id = {$current_player_id}" );      //mark launch phase done, and reset nbr of
                if ( $this->skipPlayer( $current_player_id, PHASE_HIRE ) ) {    //if hire is not possible mark player as inactive             
                    $transition = $this->gamestate->setPlayerNonMultiactive( $current_player_id, PHASE_GAME_LAUNCH_HIRE_FINISH );
                    $next_phase = $transition ? PHASE_GAME_LAUNCH_HIRE_FINISH : PHASE_LAUNCH_HIRE;
                } else {
                    $next_phase = PHASE_LAUNCH_HIRE;
                }
            }
            $extra_time = false; 
        } else if ($current_phase == PHASE_HIRE) {
            
            
            
            $result = $this->nextHire();
            $next_player = $result[0];
            $next_phase = $result[1];
            $extra_time = $result[2];
            if ($this->optSimultaneousLaunchHire()) {   
                if ($next_phase != PHASE_HIRE) {    //no more to hire, set player as inactive
                    $transition = $this->gamestate->setPlayerNonMultiactive( self::getCurrentPlayerId(), PHASE_GAME_LAUNCH_HIRE_FINISH );
                    $next_phase = $transition ? PHASE_GAME_LAUNCH_HIRE_FINISH : PHASE_LAUNCH_HIRE;
                } else {
                    $next_phase = PHASE_LAUNCH_HIRE;
                }
            }

        } else if ($current_phase == PHASE_PROCESSING) {
            
            
            $next_phase = $this->nextPhase(); 
            $next_phase = $this->nextPhase(); 
        } else if ($current_phase == PHASE_DRAW) {
            
            
            $next_phase = $this->nextPhase();
            $next_player = $this->rotateFirstPlayer();
            self::incStat(1, 'rounds_number');
        }

        return array($next_player, $next_phase, $extra_time);
    }

    function nextAuction()
    {
        $next_state = PHASE_AUCTION;
        if (self::getGameStateValue('auction_card')) {
            
            
            $sql = "SELECT COUNT(player_id) AS passed FROM player WHERE auction_pass = 1";
            $num_pass = self::getUniqueValueFromDB($sql);
            if ($num_pass == (self::getPlayersNumber() - 1)) {
                
                $sql = "SELECT player_id FROM player WHERE auction_pass = 0";
                $player_id = self::getUniqueValueFromDB($sql);
                self::setGameStateValue('auction_winner', $player_id);

                
                $players = self::loadPlayersBasicInfos();
                $msg = clienttranslate('${player_name} wins the auction');
                self::notifyAllPlayers('auctionWin', $msg, array(
                    'player_name' => $players[$player_id]['player_name'],
                    'player_id' => $player_id,
                    'bid' => $this->getHighBid(),
                    'card_id' => self::getGameStateValue('auction_card'),
                ));
            } else {
                
                
                $current_player = self::getActivePlayerId();
                $next_player = self::getNextPlayerTable();
                $player_id = $next_player[$current_player];
                while (!$this->canBid($player_id)) {
                    $player_id = $next_player[$player_id];
                }
            }
        } else {
            
            
            self::DbQuery('UPDATE player SET auction_bid = 0');
            self::DbQuery('UPDATE player SET auction_pass = 0 WHERE passed = 0');

            
            $first_player = self::getGameStateValue('first_player');
            if (!$this->canBid($first_player)) {
                
                $next_player = self::getNextPlayerTable();
                $player_id = $next_player[$first_player];
                while ($player_id != $first_player) {
                    if (!$this->canBid($player_id)) {
                        $player_id = $next_player[$player_id];
                        continue;
                    }

                    break;
                }

                if ($player_id == $first_player) {
                    
                    
                    $this->drawLicenses();
                    self::DbQuery('UPDATE player SET auction_bid = 0, auction_pass = 0, passed = 0');
                    $next_state = $this->nextPhase();
                }
            } else {
                $player_id = $first_player;
            }
        }

        $this->gamestate->changeActivePlayer($player_id);
        return array($player_id, $next_state, true);
    }

    function nextLaunch()
    {
        
        
        $player_id = $this->getPlayerIdForAction();
        $nbr_license = count($this->getLicenses($player_id, LICENSE_COD));
        $nbr_launches = $this->getNumberOfLaunches($player_id);
        if ($nbr_license > 0 && 
            $nbr_launches < 2 && 
            !$this->skipPlayer($player_id, PHASE_LAUNCH) && 
            !$this->hasPassed($player_id)) 
        {
            
            return true;
        } else {
            
            if ($nbr_license > 0 && self::getGameStateValue('current_player_launches') > 0) {
                
                $this->drawCards($player_id, $nbr_license, 'hand',
                    $this->card_types[LICENSE_COD]['name']);
            }
        }

        
        self::setGameStateValue('current_player_launches', 0);
        return false;
    }

    function nextHire()
    {
        
        
        $player_id = $this->getPlayerIdForAction();
        $nbr_license = count($this->getLicenses($player_id, LICENSE_LOBSTER));
        $nbr_hires = $this->getNumberOfHires( $player_id );
        if ($nbr_license > 0 && 
            $nbr_hires < 2 && 
            !$this->skipPlayer($player_id, PHASE_HIRE) && 
            !$this->hasPassed($player_id)) 
        {
            
            $has_bonus = true;
        } else {
            
            $has_bonus = false;
            if ($nbr_license > 0) {
                
                $sql = "SELECT SUM(has_captain) FROM card WHERE card_location = 'table' ";
                $sql .= "AND card_location_arg = $player_id AND card_type = '" . CARD_BOAT ."'";
                $nbr_captain = self::getUniqueValueFromDB($sql);

                
                
                if ($nbr_captain > 0) {
                    if ($nbr_license == 1) {
                        $nbr_cards = $nbr_captain < 4 ? 1 : 2;
                    } else {
                        if ($nbr_captain < 3) {
                            $nbr_cards = 1;
                        } else if ($nbr_captain < 7) {
                            $nbr_cards = 2;
                        } else  {
                            $nbr_cards = 3;
                        }
                    }
                } else {
                    $nbr_cards = 0;
                }

                $this->drawCards($player_id, $nbr_cards, 'hand',
                    $this->card_types[LICENSE_LOBSTER]['name']);
            }
        }

        
        if ($has_bonus) {
            
            $next_state = PHASE_HIRE;
        } else if ($this->optSimultaneousLaunchHire() == false) {
            
            self::setGameStateValue('current_player_hires', 0);
            $player_id = self::activeNextPlayer();
            if ($player_id == self::getGameStateValue('first_player')) {
                
                $next_state = $this->nextPhase();
            } else {
                
                $next_state = $this->prevPhase();
            }
        } else if ($this->optSimultaneousLaunchHire()) {
            $next_state = PHASE_FISHING;
        }

        return array($player_id, $next_state, !$has_bonus);
    }

    function doFishing()
    {
        
        $fish = self::getGameStateValue('fish_cubes');
        $players = self::loadPlayersBasicInfos();
        foreach ($players as $player_id => $player) {
            
            $boats = $this->getBoats($player_id);
            $boat_ids = array();
            foreach ($boats as $card_id => $boat) {
                if ($boat['has_captain'] && $boat['fish'] < 4) {
                    
                    $sql = "UPDATE card SET nbr_fish = nbr_fish + 1 WHERE card_id = $card_id";
                    self::DbQuery($sql);
                    $fish = self::incGameStateValue('fish_cubes', -1);
                    $boat_ids[] = $card_id;
                }
            }

            
            $nbr_fish = count($boat_ids);
            if ($nbr_fish > 0) {
                $this->incScore($player_id, $nbr_fish);
                self::incStat($nbr_fish, 'fish_gained', $player_id);
                self::incStat($nbr_fish, 'vp_fish', $player_id);
                self::incStat($nbr_fish, 'vp_total', $player_id);
            }

            
            $msg = clienttranslate('${player_name} gains ${nbr_fish} fish crate(s)');
            self::notifyAllPlayers('fishing', $msg, array(
                'player_name' => $player['player_name'],
                'nbr_fish' => $nbr_fish,
                'player_id' => $player_id,
                'card_ids' => $boat_ids
            ));
        }

        if ($fish <= 0 || self::getGameStateValue('final_round')) {
            
            self::notifyAllPlayers('log', clienttranslate('Fish crate supply exhausted, game is over!'), array());
            return 'finalScore';
        } else {
            
            return $this->nextPhase();
        }
    }


    function drawPhase($player_id)
    {
        
        $bonus = count($this->getLicenses($player_id, LICENSE_TUNA));
        if ($bonus == 0) {
            
            $dest = 'draw';
            $nbr = 2;
        } else {
            
            $dest = 'hand';
            if ($bonus < 3) {
                
                $nbr = $bonus + 1;
            } else {
                
                $nbr = $bonus;
            }
        }

        
        $this->drawCards($player_id, $nbr, $dest,
            $bonus == 0 ? null : $this->card_types[LICENSE_TUNA]['name']);
    }

    function drawCards($player_id, $nbr, $dest, $bonus=null)
    {
        if ($nbr > 0) {
            
            $cards = $this->cards->pickCardsForLocation($nbr, 'deck', $dest, $player_id);

            $shfl = false;
            $deck_nbr = 0;
            if (count($cards) < $nbr) {
                
                $this->cards->moveAllCardsInLocation('discard', 'deck');
                $this->cards->shuffle('deck');
                $more_cards = $this->cards->pickCardsForLocation($nbr - count($cards), 'deck', $dest, $player_id);
                $cards = array_merge($cards, $more_cards);

                
                $shfl = true;
                $deck_nbr = $this->cards->countCardInLocation('deck');
                $msg = clienttranslate('Shuffling discard pile into new deck...');
                self::notifyAllPlayers('log', $msg, array());
            }

            self::incStat($nbr, 'cards_drawn', $player_id);

            $players = self::loadPlayersBasicInfos(); 

            
            
            $msg = '';
            if ($bonus != null) {
                $msg = '${bonus}: ';
            }
            $msg .= clienttranslate('${player_name} draws ${nbr} card(s)');
            self::notifyAllPlayers('drawLog', $msg, array(
                'i18n' => array('bonus'),
                'bonus' => $bonus,
                'player_name' => $players[$player_id]['player_name'], 
                'player_id' => $player_id,
                'nbr' => $nbr,
                'shuffle' => $shfl,
                'deck_nbr' => $deck_nbr,
            ));
            self::notifyPlayer($player_id, 'draw', '', array(
                'cards' => $cards,
            ));
        }
    }

    function skipPlayer($player_id, $phase)
    {
        
        
        switch ($phase) {
            case PHASE_AUCTION:
                if ($this->optFastPassing()) {
                    $skip = count($this->possibleMoves($player_id, PHASE_AUCTION)) == 0;
                } else {
                    $skip = false;
                }
                break;
            case PHASE_LAUNCH:
                if ($this->optFastPassing()) {
                    
                    $moves = $this->possibleMoves($player_id, $phase);
                    $can_play = false;
                    foreach ($moves as $move) {
                        $can_play = $can_play || $move['can_play'];
                    }
                    $skip = !$can_play;
                } else {
                    
                    $skip = $this->cards->countCardInLocation('hand', $player_id) == 0;
                }
                break;
            case PHASE_HIRE:
                if ($this->optFastPassing()) {
                    
                    $moves = $this->possibleMoves($player_id, $phase);
                    $skip = count($moves) < 2 || !array_key_exists('has_boat', $moves) || !array_key_exists('has_captain', $moves);
                } else {
                    
                    $hand = $this->cards->countCardInLocation('hand', $player_id);
                    $sql = "SELECT COUNT(*) FROM card WHERE card_location = 'table' ";
                    $sql .= "AND card_location_arg = $player_id AND card_type = '";
                    $sql .= CARD_BOAT . "' AND has_captain = 0";
                    $skip = $hand == 0 || self::getUniqueValueFromDB($sql) == 0;
                }
                break;
            case PHASE_PROCESSING:
                
                $license = $this->getLicenses($player_id, LICENSE_PROCESSING);
                $sql = "SELECT SUM(nbr_fish) FROM card WHERE card_location = 'table' ";
                $sql .= "AND card_location_arg = $player_id AND card_type = '" . CARD_BOAT ."'";
                $skip = count($license) == 0 || self::getUniqueValueFromDB($sql) == 0;
                break;
            case PHASE_TRADING:
                
                $sql = "SELECT fish_crates FROM player WHERE player_id = $player_id";
                $skip = self::getUniqueValueFromDB($sql) == 0;
                break;
            case PHASE_DRAW:
                
                $bonus = count($this->getLicenses($player_id, LICENSE_TUNA));
                $skip = $bonus == 1 || $bonus == 3;
                break;
            default:
                $skip = false;
                break;
        }

        return $skip;
    }

    function rotateFirstPlayer()
    {
        $player_id = self::getGameStateValue('first_player');
        $next_player = self::getNextPlayerTable();
        $first_player = $next_player[$player_id];
        self::setGameStateValue('first_player', $first_player);

        
        self::notifyAllPlayers('firstPlayer', '', array(
            'current_player_id' => $player_id,
            'next_player_id' => $first_player,
        ));

        
        $this->gamestate->changeActivePlayer($first_player);
        return $first_player;
    }

    function drawLicenses()
    {
        
        $nbr_players = self::getPlayersNumber();
        $nbr_left = $this->cards->countCardInLocation('auction');
        $nbr_draw = $nbr_players - $nbr_left;
        $discard = false;
        if ($nbr_draw == 0) {
            
            $this->cards->moveAllCardsInLocation('auction', 'box');
            $nbr_draw = $nbr_players;
            $discard = true;
        }

        
        $cards = $this->cards->pickCardsForLocation($nbr_draw, 'licenses', 'auction', 0, true);
        self::notifyAllPlayers('drawLicenses', '', array('cards' => $cards, 'discard' => $discard));

        if (count($cards) < $nbr_draw) {
            
            
            self::setGameStateValue('final_round', 1);
            self::notifyAllPlayers('finalRound', clienttranslate('No more licenses: this is the last round!'), array());
        }
    }

    function stFinalScore()
    {
        $players = self::loadPlayersBasicInfos();

        
        $crab = $this->cards->getCardsOfType(CARD_LICENSE, LICENSE_CRAB_C);
        $card = array_shift($crab);
        if ($card['location'] == 'table') {
            
            $player_id = $card['location_arg'];
            $boats = $this->getBoats($player_id);
            $captains = array_sum(array_column($boats, 'has_captain'));

            
            $points = min($captains, 10);
            $this->incScore($player_id, $points);
            self::incStat($points, 'vp_bonus', $player_id);
            self::incStat($points, 'vp_total', $player_id);

            
            $msg = clienttranslate('${card_name}: ${player_name} scores ${points} points for ${nbr} captains');
            self::notifyAllPlayers('bonusScore', $msg, array(
                'i18n' => array('card_name'),
                'card_name' => $this->getCardName($card),
                'player_name' => $players[$player_id]['player_name'],
                'points' => $points,
                'nbr' => $captains,
                'player_id' => $player_id,
            ));
        }

        
        $crab = $this->cards->getCardsOfType(CARD_LICENSE, LICENSE_CRAB_F);
        $card = array_shift($crab);
        if ($card['location'] == 'table') {
            
            $player_id = $card['location_arg'];
            $boats = $this->getBoats($player_id);
            $fish = array_sum(array_column($boats, 'fish'));

            
            $points = min(intdiv($fish, 3), 10);
            $this->incScore($player_id, $points);
            self::incStat($points, 'vp_bonus', $player_id);
            self::incStat($points, 'vp_total', $player_id);

            
            $msg = clienttranslate('${card_name}: ${player_name} scores ${points} points for ${nbr} fish crates');
            self::notifyAllPlayers('bonusScore', $msg, array(
                'i18n' => array('card_name'),
                'card_name' => $this->getCardName($card),
                'player_name' => $players[$player_id]['player_name'],
                'points' => $points,
                'nbr' => $fish,
                'player_id' => $player_id,
            ));
        }

        
        $crab = $this->cards->getCardsOfType(CARD_LICENSE, LICENSE_CRAB_L);
        $card = array_shift($crab);
        if ($card['location'] == 'table') {
            
            $player_id = $card['location_arg'];
            $licenses = array_column($this->getLicenses($player_id), 'type_arg');
            $unique = count(array_unique($licenses));
            
            if (in_array(LICENSE_CRAB_F, $licenses)) {
                $unique -= 1;
            }
            if (in_array(LICENSE_CRAB_C, $licenses)) {
                $unique -= 1;
            }

            
            if ($unique == 1) {
                $points = 0;
            } else if ($unique == 2) {
                $points = 2;
            } else if ($unique == 3) {
                $points = 4;
            } else if ($unique == 4) {
                $points = 5;
            } else if ($unique == 5) {
                $points = 6;
            } else if ($unique == 6) {
                $points = 8;
            } else if ($unique == 7) {
                $points = 10;
            }
            $this->incScore($player_id, $points);
            self::incStat($points, 'vp_bonus', $player_id);
            self::incStat($points, 'vp_total', $player_id);

            
            $msg = clienttranslate('${card_name}: ${player_name} scores ${points} points for ${nbr} different licenses');
            self::notifyAllPlayers('bonusScore', $msg, array(
                'i18n' => array('card_name'),
                'card_name' => $this->getCardName($card),
                'player_name' => $players[$player_id]['player_name'],
                'points' => $points,
                'nbr' => $unique,
                'player_id' => $player_id,
            ));
        }

        
        if ($this->optGoneFishing()) {
            foreach ($players as $player_id => $player) {
                
                $cards = $this->cards->getPlayerHand($player_id);
                $nbr_cards = 0;
                foreach ($cards as $card_id => $card) {
                    if ($card['type_arg'] == GONE_FISHING) {
                        $nbr_cards += 1;
                    }
                }
                $points = $nbr_cards * $this->card_types[GONE_FISHING]['points'];

                
                $this->incScore($player_id, $points);
                self::incStat($points, 'vp_bonus', $player_id);
                self::incStat($points, 'vp_total', $player_id);

                
                $msg = clienttranslate('Gone Fishin\': ${player_name} scores ${points} points');
                self::notifyAllPlayers('bonusScore', $msg, array(
                    'player_name' => $players[$player_id]['player_name'],
                    'points' => $points,
                    'player_id' => $player_id,
                ));
            }
        }

        
        
        
        foreach ($players as $player_id => $player) {
            $boats = $this->getBoats($player_id);
            $fish = array_sum(array_column($boats, 'fish'));
            $score = (count($boats) * 100) + $fish;
            self::DbQuery("UPDATE player SET player_score_aux = $score WHERE player_id = $player_id");
        }

        
        $scores_boat = array();
        $scores_license = array();
        $scores_fish = array();
        $scores_bonus = array();
        $scores_total = array();
        foreach ($players as $player_id => $player) {
            $scores_boat[$player_id] = self::getStat('vp_boats', $player_id);
            $scores_license[$player_id] = self::getStat('vp_licenses', $player_id);
            $scores_fish[$player_id] = self::getStat('vp_fish', $player_id);
            $scores_bonus[$player_id] = self::getStat('vp_bonus', $player_id);
            $scores_total[$player_id] = self::getStat('vp_total', $player_id);
        }
        self::notifyAllPlayers('finalScore', '', array(
            'boat' => $scores_boat,
            'license' => $scores_license,
            'fish' => $scores_fish,
            'bonus' => $scores_bonus,
            'total' => $scores_total,
        ));

        $this->gamestate->nextState();
    }

//////////////////////////////////////////////////////////////////////////////
//////////
////////////

    /*
        zombieTurn:
        
        This method is called each time it is the turn of a player who has quit the game (= "zombie" player).
        You can do whatever you want in order to make sure the turn of this player ends appropriately
        (ex: pass).
        
        Important: your zombie code will be called when the player leaves the game. This action is triggered
        from the main site and propagated to the gameserver from a server, not from a browser.
        As a consequence, there is no current player associated to this action. In your zombieTurn function,
        you must _never_ use getCurrentPlayerId() or getCurrentPlayerName(), otherwise it will fail with a "Not logged" error message. 
    */

    function zombieTurn( $state, $active_player )
    {
        $statename = $state['name'];
        
        if ($state['type'] === "activeplayer") {
            switch ($statename) {
                case PHASE_AUCTION:
                    
                    if ($active_player == self::getGameStateValue('auction_winner')) {
                        
                        
                        $license_id = self::getGameStateValue('auction_card');
                        $license = $this->cards->getCard($license_id);
                        $this->cards->moveCard($license_id, 'box');

                        self::setGameStateValue('auction_card', 0);
                        self::setGameStateValue('auction_winner', 0);

                        
                        self::notifyAllPlayers('buyLicense', '', array(
                            'nbr_cards' => 0,
                            'nbr_fish' => 0,
                            'player_id' => $active_player,
                            'card_ids' => array(),
                            'license_id' => $license_id,
                            'license_type' => $license['type_arg'],
                            'points' => 0,
                        ));
                    } else {
                        
                        self::notifyAllPlayers('pass', '', array(
                            'player_id' => $active_player,
                            'in_auction' => true,
                            'auction_done' => self::getGameStateValue('auction_card') == 0,
                        ));
                    }
                default:
                    
                    $sql = 'UPDATE player SET auction_bid = 0, auction_pass = 1, passed = 1';
                    $sql .= " WHERE player_id = $active_player";
                    self::DbQuery($sql);
                    $this->gamestate->nextState();
                    break;
            }

            return;
        }

        if ($state['type'] === "multipleactiveplayer") {
            
            
            $bonus = count($this->getLicenses($active_player, LICENSE_TUNA));
            $loc = $bonus > 0 ? 'hand' : 'draw';
            if ($bonus != 1 && $bonus != 3) {
                $cards = $this->cards->getCardsInLocation($loc, $active_player);
                $card = array_shift($cards);
                $this->cards->playCard($card['id']);
            }

            
            $this->gamestate->setPlayerNonMultiactive($active_player, '');
            return;
        }

        throw new feException( "Zombie mode not supported at this game state: ".$statename );
    }
    
///////////////////////////////////////////////////////////////////////////////////:
////////
//////////

    /*
        upgradeTableDb:
        
        You don't have to care about this until your game has been published on BGA.
        Once your game is on BGA, this method is called everytime the system detects a game running with your old
        Database scheme.
        In this case, if you change your Database scheme, you just have to apply the needed changes in order to
        update the game database and allow the game to continue to run with your new version.
    
    */
    
    function upgradeTableDb( $from_version )
    {
    }    
}
