/**
 *------
 * BGA framework: © Gregory Isabelli <gisabelli@boardgamearena.com> & Emmanuel Colin <ecolin@boardgamearena.com>
 * Fleet implementation : © Dan Marcus <bga.marcuda@gmail.com>
 *
 * This code has been produced on the BGA studio platform for use on http://boardgamearena.com.
 * See http://en.boardgamearena.com/#!doc/Studio for more information.
 * -----
 *
 * fleet.js
 *
 * fleet user interface script
 * 
 * In this file, you are describing the logic of your user interface, in Javascript language.
 *
 */

define([
    "dojo","dojo/_base/declare",
    "ebg/core/gamegui",
    "ebg/counter",
    "ebg/stock",
    "ebg/zone"
],
function (dojo, declare) {
    return declare("bgagame.fleet", ebg.core.gamegui, {
        constructor: function(){
            this.debug = false; 

            if (this.debug) console.log('fleet constructor');
              
            
            this.auction = {bids:[]};    
            this.player_hand = null;     
            this.boat_width = 100;       
            this.boat_height = 143;      
            this.boat_row_size = 7;      
            this.license_width = 175;    
            this.license_height = 122;   
            this.license_row_size = 5;   
            this.fish_cube_size = 30;    
            this.license_counter = null; 
            this.boat_counter = null;    
            this.discard_counter = null; 
            this.fish_counter = null;    
            this.coin_counter = null;    
            this.hand_counters = [];     
            this.client_state_args = {}; 
            this.card_infos = null;      
            this.player_coins = 0;       
            this.player_licenses = [];   
            this.player_boats = [];      
            this.possible_moves = null;  
            this.fish_zones = [];        
            this.player_fish = [];       
            this.discount = 0;           
            this.gone_fishing = false;   
            this.constants = null;       
            this.in_client_state = false;
        },
        
        /*
            setup:
            
            This method must set up the game user interface according to current game situation specified
            in parameters.
            
            The method is called each time the game interface is displayed to a player, ie:
            _ when the game starts
            _ when a player refreshes the game page (F5)
            
            "gamedatas" argument contains all datas retrieved by your "getAllDatas" PHP method.
        */
        
        setup: function( gamedatas )
        {
            if (this.debug) console.log( "Starting game setup" );
            if (this.debug) console.log(gamedatas);

            
            this.card_infos = gamedatas.card_infos;
            this.player_coins = parseInt(gamedatas.coins);
            this.auction.high_bid = parseInt(gamedatas.auction_bid);
            this.auction.winner = parseInt(gamedatas.auction_winner);
            this.possible_moves = gamedatas.moves;
            this.discount = parseInt(gamedatas.discount);
            this.gone_fishing = gamedatas.gone_fishing;
            this.constants = gamedatas.constants;

            
            for( var player_id in gamedatas.players )
            {
                
                var player = gamedatas.players[player_id];
                player.url = g_gamethemeurl;
                var player_board_div = $('player_board_' + player_id);
                dojo.place(this.format_block('jstpl_player_board', player), player_board_div);
                this.addTooltip('handcount_p' + player_id, _('Number of cards in hand'), '');
                this.addTooltip('handcount_icon_p' + player_id, _('Number of cards in hand'), '');
                this.addTooltip('first_player_p' + player_id, _('Starting player'), '');

                this.hand_counters[player_id] = new ebg.counter();
                this.hand_counters[player_id].create('handcount_p' + player_id);
                this.hand_counters[player_id].setValue(gamedatas.hand_cards[player_id] || 0);
                         
                
                
                this.player_licenses[player_id] = [];
                for (var i = 0; i < 9; i++) {
                    var zone = new ebg.zone();
                    zone.create(this, 'license_' + player_id + '_' + i, this.license_width, this.license_height);
                    zone.setPattern('diagonal');
                    zone.autowidth = true;
                    zone.item_margin = 10;
                    this.addTooltipHtml('license_' + player_id + '_' + i, this.getCardTooltip(i));
                    this.player_licenses[player_id][i] = zone;
                }
                
                var licenses = gamedatas.licenses[player_id];
                for (var i in licenses) {
                    var card = licenses[i];
                    this.addPlayerLicense(player_id, card.type_arg, card.id, null);
                }

                
                this.player_fish[player_id] = new ebg.zone();
                this.player_fish[player_id].create(this, 'playerfish_' + player_id,
                    this.fish_cube_size, this.fish_cube_size);
                this.player_fish[player_id].setPattern('horizontalfit');
                for (var i = 0; i < parseInt(gamedatas.processed_fish[player_id]); i++) {
                    this.processFishCube(null, player_id);
                }
                this.addTooltip('playerfish_' + player_id, _('Processed fish crates: $1 ea.'), '');

                
                this.player_boats[player_id] = this.createStockBoat('playerboats_' + player_id, false);
                var boats = gamedatas.boats[player_id];
                for (var i in boats) {
                    var card = boats[i];
                    this.player_boats[player_id].addToStockWithId(card.type_arg, card.id);
                    if (parseInt(card.has_captain)) {
                        dojo.style('captain_' + card.id, 'display', 'block');
                    }

                    
                    this.createFishZone(card.id);
                    for (var j = 0; j < parseInt(card.fish); j++) {
                        this.addFishCube(card.id, player_id);
                    }
                }
                dojo.connect(this.player_boats[player_id], 'onChangeSelection', this, 'onPlayerBoatsSelectionChanged');

                
                var bid = parseInt(player.bid);
                if (parseInt(player.done)) {
                    dojo.addClass('playerbid_' + player_id + '_wrap', 'flt_auction_done');
                } else if (parseInt(player.pass)) {
                    bid = 'pass';
                }
                this.auction.bids[player_id] = bid;
            }

            
            dojo.addClass('first_player_p' + gamedatas.first_player, 'flt_first_player');

            
            if (!this.isSpectator) { 
                this.coin_counter = new ebg.counter();
                this.coin_counter.create('coincount_p' + this.player_id);
                this.coin_counter.setValue(this.player_coins - this.discount);
                if (this.discount > 0) {
                    dojo.byId('discount_p' + this.player_id).textContent = '+' + this.discount;
                }
                this.addTooltip('coincount_icon_p' + this.player_id, _('Available money (+ any Shrimp bonus)'), '');
                this.addTooltip('coincount_p' + this.player_id, _('Available money (+ any Shrimp bonus)'), '');
                this.addTooltip('discount_p' + this.player_id, _('Available money (+ any Shrimp bonus)'), '');
            }

            
            this.auction.card_id = parseInt(gamedatas.auction_card);
            this.auction.table = this.createStockLicense('auctiontable');
            this.auction.table.centerItems = true;
            for (var i in gamedatas.auction) {
                var card = gamedatas.auction[i];
                this.auction.table.addToStockWithId(card.type_arg, card.id);
            }
            dojo.connect(this.auction.table, 'onChangeSelection', this, 'onAuctionSelectionChanged');
            if (gamedatas.gamestate.name.indexOf('auction') == -1) {
                
                
                dojo.place('auction', 'auction_bottom');
            }

            
            
            this.license_counter = new ebg.counter();
            this.license_counter.create('licensecount');
            this.setCounterValue(this.license_counter, gamedatas.cards['licenses'] || 0);
            this.addTooltip('licenseicon', _('Number license cards remaining'), '');
            this.addTooltip('licensecount', _('Number license cards remaining'), '');
            
            this.boat_counter = new ebg.counter();
            this.boat_counter.create('boatcount');
            this.setCounterValue(this.boat_counter, gamedatas.cards['deck'] || 0);
            this.discard_counter = new ebg.counter();
            this.discard_counter.create('discardcount');
            this.setCounterValue(this.discard_counter, gamedatas.cards['discard'] || 0);
            this.addTooltip('boaticon', _('Number boat cards in deck / discard pile (automatically reshuffled)'), '');
            this.addTooltip('boatcount', _('Number boat cards in deck / discard pile (automatically reshuffled)'), '');
            this.addTooltip('discardcount', _('Number boat cards in deck / discard pile (automatically reshuffled)'), '');
            
            this.fish_counter = new ebg.counter();
            this.fish_counter.create('fishcount');
            this.setCounterValue(this.fish_counter, gamedatas.fish_cubes);
            this.addTooltip('fishicon', _('Number fish crates remaining'), '');
            this.addTooltip('fishcount', _('Number fish crates remaining'), '');

            
            if (!gamedatas.cards['licenses']) {
                
                dojo.style('licenseicon', {'opacity': '0.5', 'border': 'none'});
                dojo.style('licensecount', {'color': 'red', 'font-weight': 'bold'});
            }
            
            
            if (!this.isSpectator) { 
                this.player_hand = this.createStockBoat('myhand', true);
                this.player_hand.vertical_overlap = 0; 
                for (var i in gamedatas.hand) {
                    var card = gamedatas.hand[i];
                    this.player_hand.addToStockWithId(card.type_arg, card.id);
                }
                for (var i in gamedatas.draw) { 
                    var card = gamedatas.draw[i];
                    this.player_hand.addToStockWithId(card.type_arg, card.id);
                }
                dojo.connect(this.player_hand, 'onChangeSelection', this, 'onPlayerHandSelectionChanged');
            } else {
                
                dojo.style('myhand_wrap', 'display', 'none');
            }
 
            
            this.setupNotifications();

            if (this.debug) console.log( "Ending game setup" );
        },
       

        
        
        adjustLaunchHireState : function( stateName, args )
        {            
            if (!this.isSpectator && stateName == "launchHire") {
                if (args.reset_client_player_id && args.reset_client_player_id == this.player_id) {     
                    this.in_client_state = false;
                }
                stateName = "launch";
                if (args.players[this.player_id].launch_hire_phase == "1")  {   
                    stateName = "hire";
                }
                if (this.in_client_state && stateName == "launch") {        
                    stateName = "client_launchPay";
                }
                this.possible_moves = args.players[this.player_id].possible_moves;
            }
            return stateName;
        },

        
        
        
        onEnteringState: function( stateName, args )
        {
            if (this.debug) console.log( 'Entering state: '+stateName );
            if (this.debug) console.log(this.gamedatas.gamestate);

            stateName = this.adjustLaunchHireState(stateName, args.args);
            
            this.showPossibleMoves();
            this.hideAuction(stateName);

            switch( stateName )
            {
                case 'auction':
                    
                    
                    if (this.debug) {
                        var obj = {
                            winner: this.auction.winner,
                            player: this.player_id,
                            card: this.auction.card_id,
                            bid: this.auction.high_bid
                        }
                        console.log(obj);
                    }

                    this.client_state_args = {};
                    if (this.auction.winner) {
                        
                        if (this.isCurrentPlayerActive()) {
                            
                            this.client_state_args.fish_crates = 0;
                            this.client_state_args.cost = this.auction.high_bid - this.discount;
                            if (this.client_state_args.cost <= 0) {
                                
                                this.buyAction('buyLicense');
                            } else {
                                
                                var desc = _('${you} must discard cards to pay');
                                desc += ' 0/' + this.client_state_args.cost
                                this.setClientState('client_auctionWin', {
                                    descriptionmyturn: desc
                                });
                            }
                        } else {
                            
                            var desc = _('${actplayer} must discard to pay');
                            desc += ' ' + this.auction.high_bid;
                            this.gamedatas.gamestate.description = desc;
                            this.updatePageTitle();
                        }
                    } else if (this.auction.card_id) {
                        
                        
                        var card = this.auction.table.getItemById(this.auction.card_id);
                        var card_info = this.card_infos[card.type];
                        if (this.debug) console.log(card);
                        if (this.debug) console.log(card_info);
                        var desc = _(card_info.name) + ': ' + _('${you} must bid or pass');
                        this.setClientState('client_auctionBid', {
                            descriptionmyturn: desc,
                            args: card_info
                        });
                    } else {
                        
                        
                        this.setClientState('client_auctionSelect', {
                            descriptionmyturn: _('${you} may select a license to bid on')
                        });
                    }
                    break;
                case 'client_auctionSelect':
                    
                    this.showActiveAuction();
                    this.auction.table.setSelectionMode(1);
                    break;
                case 'client_auctionBid':
                    
                    this.showActiveAuction();
                    break;
                case 'client_auctionWin':
                    
                    this.showActiveAuction();
                    this.safeSetSelectionMode(this.player_hand, 2);
                    this.client_state_args.fish_crates = 0;
                    break;
                case 'launch':
                    if (this.isCurrentPlayerActive()) {
                        
                        this.client_state_args = {};
                        this.safeSetSelectionMode(this.player_hand, 1);
                    }
                    break;
                case 'client_launchPay':
                    if (!this.in_client_state)  {
                        
                        this.safeSetSelectionMode(this.player_hand, 2);
                        this.client_state_args.fish_crates = 0;
                    } else {    
                        this.updateBuy();
                    }
                    break;
                case 'hire':
                    if (this.isCurrentPlayerActive()) {
                        
                        this.client_state_args = {};
                        this.safeSetSelectionMode(this.player_hand, 1);
                        this.safeSetSelectionMode(this.player_boats[this.player_id], 1);
                        this.gamedatas.gamestate.descriptionmyturn = _("${you} may hire a captain");        
                        this.updatePageTitle();
                    }
                    break;
                case 'processing':
                    
                    this.client_state_args = {fish_ids:[]};
                    break;
                case 'client_trading':
                    
                    
                    break;
                case 'draw':
                    
                    
                    break;
                case 'gameLaunchHireFinish':
                    this.in_client_state = false;       
                    break;
            }
        },

        
        
        
        onLeavingState: function( stateName )
        {
            if (this.debug) console.log( 'Leaving state: '+stateName );
            if (this.in_client_state)       
                return;

            
            this.auction.table.setSelectionMode(0);
            this.safeSetSelectionMode(this.player_hand, 0);
            this.safeSetSelectionMode(this.player_boats[this.player_id], 0);

            
            dojo.style('auctionbids', 'display', 'none');
            dojo.query('.flt_disabled').removeClass('flt_disabled');
            dojo.query('.flt_fish_selected').removeClass('flt_fish_selected');
            dojo.query('.flt_fish_selectable').removeClass('flt_fish_selectable');
            dojo.query('.flt_selectable').removeClass('flt_selectable');
            dojo.query('.flt_icon_fish').style('cursor', 'default');

            switch( stateName )
            {
                default:
                    
                    break;
            }               
        }, 

        
        
        
        onUpdateActionButtons: function( stateName, args )
        {
            if (this.debug) console.log( 'onUpdateActionButtons: '+stateName );
            if (this.debug) console.log(args);

            stateName = this.adjustLaunchHireState(stateName, args);
            if( this.isCurrentPlayerActive() )
            {            
                switch( stateName )
                {
                    case 'client_auctionSelect':
                        
                        if (this.gone_fishing) {
                            this.addActionButton('button_1', _("Go fishin' (pass)"), 'onPass');
                        } else {
                            this.addActionButton('button_1', _('Pass'), 'onPass');
                        }
                        break;
                    case 'client_auctionBid':
                        
                        this.client_state_args.bid = this.auction.high_bid + 1; 
                        if (this.debug) console.log(this.client_state_args);
                        if (this.debug) console.log(this.auction.high_bid);
                        if (this.debug) console.log(this.player_coins);
                        if (this.player_coins >= this.client_state_args.bid) {
                            
                            this.addActionButton('button_1', '-1', 'onMinusOne', null, false, 'gray');
                            var color = this.player_coins == this.client_state_args.bid ? 'gray' : 'blue';
                            this.addActionButton('button_2', '+1', 'onPlusOne', null, false, color);
                            this.addActionButton('button_3', _('Bid') + ': ' + this.client_state_args.bid, 'onBid');
                        }
                        this.addActionButton('button_4', _('Pass'), 'onPass');
                        break;
                    case 'client_auctionWin':
                        
                        this.addActionButton('button_1', _('Discard selected'), 'onBuy', null, false, 'gray');
                        break;
                    case 'launch':
                        
                        this.addActionButton('button_1', _('Pass'), 'onPass');
                        break;
                    case 'client_launchPay':
                        
                        this.addActionButton('button_1', _('Discard selected'), 'onBuy', null, false, 'gray');
                        this.addActionButton('button_2', _('Cancel'), 'onCancel', null, false, 'red');
                        break;
                    case 'hire':
                        
                        this.addActionButton('button_1', _('Pass'), 'onPass');
                        break;
                    case 'processing':
                        
                        this.possible_moves = args.moves;
                        this.showPossibleMoves();

                        if (args.trade) {
                            
                            this.setClientState('client_trading', {
                                descriptionmyturn: _('${you} may trade a fish crate'),
                                args: args,
                            });
                        } else {
                            
                            this.addActionButton('button_1', _('Pass'), 'onProcess');
                            this.addActionButton('button_2', _('Cancel'), 'onCancel', null, false, 'red');
                        }
                        break;
                    case 'client_trading':
                        
                        this.addActionButton('button_1', _('Trade'), 'onTrade');
                        this.addActionButton('button_2', _('Pass'), 'onPass');
                        break;
                    case 'draw':
                        

                        
                        
                        
                        this.showPossibleMoves();
                        this.safeSetSelectionMode(this.player_hand, 1);
                        break;
                }
            }
        },        

        
    
        safeSetSelectionMode: function(stock, mode)
        {
            if (!this.isSpectator) {
                stock.setSelectionMode(mode);
            }
        },

        setCounterValue: function(counter, val)
        {
            if (val < 0) {
                val = 0;
            }
            counter.setValue(val);
        },

        incCounterValue: function(counter, inc)
        {
            var val = counter.incValue(inc);
            if (val < 0) {
                counter.setValue(0);
            }
            return val <= 0;
        },

        showFinalScore: function(args)
        {
            
            var players = [];
            for (var player_id in this.gamedatas.players) {
                var player = this.gamedatas.players[player_id];
                player.name
                player.color
                players[player_id] = '<!--PNS--><span class="playername" style="color:#'+player.color+';">'+player.name+'</span><!--PNE-->';
            }

            
            this.buildScoreRow('players', '', 'header', players)
            this.buildScoreRow('boat', _('Boats'), 'cell', args.boat)
            this.buildScoreRow('license', _('Licenses'), 'cell', args.license)
            this.buildScoreRow('fish', _('Fish'), 'cell', args.fish)
            this.buildScoreRow('bonus', _('Bonus'), 'cell', args.bonus)
            this.buildScoreRow('total', _('TOTAL'), 'header', args.total)

            
            dojo.style('final_score', 'display', 'block');
        },

        buildScoreRow: function(row, label, jstpl, data)
        {
            var cells = '';
            for (var player_id in this.gamedatas.players) {
                
                cells += this.format_block('jstpl_table_' + jstpl, {content: data[player_id]});
            }

            
            var html = this.format_block('jstpl_table_row', {label: label, content: cells});
            dojo.byId('score_table_' + row).innerHTML = html;
        },

        addPlayerLicense: function(player_id, card_type, card_id, src)
        {
            var zone_div = 'license_' + player_id + '_' + card_type;
            var license_div = zone_div + '_' + card_id;

            
            dojo.style(zone_div, 'display', 'inline-block');

            
            dojo.place(this.format_block('jstpl_license_zone', {
                player_id: player_id,
                card_type: card_type,
                card_id: card_id,
                x: this.license_width * (card_type % this.license_row_size),
                y: this.license_height * Math.floor(card_type / this.license_row_size),
            }), zone_div);

            if (src !== null) {
                
                this.placeOnObject(license_div, src);
            }

            
            this.player_licenses[player_id][card_type].placeInZone(license_div);

            
            var nbr_lic = this.player_licenses[player_id][card_type].getItemNumber();
            if (nbr_lic > 1) {
                dojo.query('div[id^="' + zone_div + '_"] > div').forEach(function(node) {
                    dojo.style(node, 'display', 'block');
                    node.textContent = '(' + nbr_lic + ')';
                });
            }
        },

        showPossibleMoves: function()
        {
            
            if (!this.isCurrentPlayerActive() || this.possible_moves.length == 0)
                return;

            if (this.debug) console.log("POSSIBLE MOVES");
            if (this.debug) console.log(this.possible_moves);
            if (this.debug) console.log(this.gamedatas.gamestate.name);
            let modifiedStateName = this.adjustLaunchHireState(this.gamedatas.gamestate.name, this.gamedatas.gamestate.args);
            
            
            switch(modifiedStateName)
            {
                case 'client_auctionSelect':
                    
                    this.updateSelectableCards(this.auction.table, true);
                    break;
                case 'client_auctionWin':
                    
                    this.updateSelectableCards(this.player_hand, false);
                    this.updateSelectableFish(this.player_id + '_fish_');
                    break;
                case 'launch':
                    
                    this.updateSelectableCards(this.player_hand, true);
                    break;
                case 'client_launchPay':
                    
                    this.updateSelectableCards(this.player_hand, false);
                    this.updateSelectableFish(this.player_id + '_fish_');
                    break;
                case 'hire':
                    
                    this.updateSelectableCards(this.player_hand, true);
                    this.updateSelectableCards(this.player_boats[this.player_id], true);
                    break;
                case 'processing':
                    
                    this.updateSelectableFish('fish_' + this.player_id + '_');
                    break;
                case 'client_trading':
                    
                    this.updateSelectableFish(this.player_id + '_fish_');
                    break;
                case 'draw':
                    
                    this.updateSelectableCards(this.player_hand, true);
                    break;
            }
        },

        updateSelectableCards: function(stock, validate)
        {
            
            var items = stock.getSelectedItems();
            for (var i in items) {
                var div = stock.getItemDivId(items[i].id);
                dojo.removeClass(div, 'flt_selectable');
            }

            
            items = stock.getUnselectedItems();
            for (var i in items) {
                var card = this.possible_moves[items[i].id];
                if (validate) {
                    
                    if (card === undefined)
                        continue
                    if (card.hasOwnProperty('can_play') && !card.can_play)
                        continue
                }

                
                var div = stock.getItemDivId(items[i].id);
                dojo.addClass(div, 'flt_selectable');
            }
        },

  
        updateSelectableFish: function(prefix)
        {
            
            dojo.query('div[id^="' + prefix + '"]').forEach(function(node) {
                if (this.debug) console.log(node);
                if (!dojo.hasClass(node, 'flt_fish_selected')) {
                    
                    
                    dojo.addClass(node, 'flt_fish_selectable');
                    dojo.style(node, 'cursor', 'pointer');
                }
            });
        },

        createFishZone: function(id)
        {
            if (this.debug) console.log('CREATE FISH: ' + id);
            if (this.debug) console.log($('fish_' + id));
            var zone = new ebg.zone();
            zone.create(this, 'fish_' + id, this.fish_cube_size, this.fish_cube_size);
            zone.setPattern('horizontalfit');
            this.fish_zones[id] = zone;
        },

        addFishCube: function(card_id, player_id)
        {
            if (!this.fish_zones[card_id]) {
                
                this.createFishZone(card_id);
            }

            
            var nbr_fish = this.fish_zones[card_id].getItemNumber();
            if (nbr_fish == 4) {
                
                this.showMessage('ERROR: Boat fish crates maxed out', 'error');
                return;
            }

            
            var fish_div = 'fish_' + player_id + '_' + card_id + '_' + nbr_fish;
            dojo.place(this.format_block('jstpl_fish',
                {player_id:player_id, card_id:card_id, fish_id:nbr_fish}), 'fish_' + card_id);
            this.placeOnObject(fish_div, 'fishicon');
            this.fish_zones[card_id].placeInZone(fish_div);

            if (player_id == this.player_id) {
                
                dojo.connect($(fish_div), 'onclick', this, 'onClickFishCube');
            }

            this.addTooltip(fish_div, _('Fish crate: +1VP'), '');
        },

        processFishCube: function(card_id, player_id)
        {
            if (card_id !== null) {
                
                var card_fish = this.fish_zones[card_id].getItemNumber() - 1;
                if (card_fish < 0) {
                    
                    this.showMessage('ERROR: No fish crates to process', 'error');
                    return;
                }

                
                var src = 'fish_' + player_id + '_' + card_id + '_' + card_fish;
            } else {
                
                var src = 'fishicon';
            }

            
            var nbr_fish = this.player_fish[player_id].getItemNumber();
            var dest = player_id + '_fish_' + nbr_fish;

            
            dojo.place(this.format_block('jstpl_pfish',
                {player_id:player_id, card_id:card_id, fish_id:nbr_fish}), 'playerfish_' + player_id);
            this.placeOnObject(dest, src);
            this.player_fish[player_id].placeInZone(dest);

            if (card_id !== null) {
                
                this.fish_zones[card_id].removeFromZone(src, true);
            }

            if (player_id == this.player_id) {
                
                dojo.connect($(dest), 'onclick', this, 'onClickFishCube');
            }

            
            
            playSound('move');
        },

        removeFishCube: function(player_id)
        {
            
            var zone = this.player_fish[player_id];
            var nbr_fish = zone.getItemNumber() - 1;
            if (nbr_fish < 0) {
                
                this.showMessage("ERROR: No fish cubes to trade", 'error');
                return;
            }

            
            var fish_div = player_id + '_fish_' + nbr_fish;
            zone.removeFromZone(fish_div, true, 'site-logo');
        },

        createStockLicense: function (div_id)
        {
            var stock = new ebg.stock();
            stock.create(this, $(div_id), this.license_width, this.license_height);
            stock.image_items_per_row = this.license_row_size;
            for (var i = 0; i < 10; i++) {
                stock.addItemType(i, i, '', i);
            }
            stock.setSelectionMode(0);
            stock.onItemCreate = dojo.hitch(this, 'setupLicenseDiv');
            stock.apparenceBorderWidth = '3px';
            return stock;
        },

        createStockBoat: function (div_id, is_hand)
        {
            var stock = new ebg.stock();
            stock.create(this, $(div_id), this.boat_width, this.boat_height);
            stock.image_items_per_row = this.card_art_row_size;
            var type, pos;
            for (type = 9, pos = 0; pos < 7; type++, pos++) {
                
                stock.addItemType(type, type, '', pos);
            }
            stock.setSelectionMode(0);
            stock.apparenceBorderWidth = '3px';

            
            if (is_hand) {
                stock.onItemCreate = dojo.hitch(this, 'setupBoatDivHand');
            } else {
                stock.onItemCreate = dojo.hitch(this, 'setupBoatDivTable');
            }

            
            stock.vertical_overlap = -15;
            stock.use_vertical_overlap_as_offset = false;

            return stock;
        },

        setupLicenseDiv: function(card_div, card_type_id, card_id)
        {
            this.addTooltipHtml(card_div.id, this.getCardTooltip(card_type_id));
            dojo.place(this.format_block('jstpl_license_stock', {
                x: this.license_width * (card_type_id % this.license_row_size),
                y: this.license_height * Math.floor(card_type_id / this.license_row_size),
            }), card_div.id);
        },

        setupBoatDivHand: function(card_div, card_type_id, card_id)
        {
            this.setupBoatDiv(card_div, card_type_id, card_id, true);
        },

        setupBoatDivTable: function(card_div, card_type_id, card_id)
        {
            this.setupBoatDiv(card_div, card_type_id, card_id, false);
        },

        setupBoatDiv: function(card_div, card_type_id, card_id, is_hand)
        {
            if (is_hand) {
                this.addTooltipHtml(card_div.id, this.getCardTooltip(card_type_id, is_hand));
            } else {
                
                var card = this.card_infos[card_type_id];
                this.addTooltip(card_div.id, _(card.name) + ': +' + card.points + _('VP'), '');
            }

            var player_id = parseInt(card_div.id.split('_')[1]);
            var id = card_id.split('_');
            id = id[id.length - 1];
            dojo.place(this.format_block('jstpl_boat', {
                id: id,
                x: this.boat_width * (card_type_id - 9),
                y: 0,
            }), card_div.id);
        },

        getCardTooltip: function (card_type_id, is_hand)
        {
            
            var card = dojo.clone(this.card_infos[card_type_id]);
            card.name = _(card.name); 

            
            var txt = '';
            if (card.type == 'boat') {
                txt += "<p><b>" + _("Cost") + ":</b> $" + card.cost + "</p>";
                txt += "<p><b>" + _("Launch") + "</b> => " + card.points + _("VP") + "</p>";
                txt += "<p><b>" + _("Discard") + "</b> => $" + card.coins + "</p>";

                card.x = 2 * this.boat_width * (card_type_id - 9);
                card.y = 0;
            } else if (card.type == 'license') {
                txt += "<p><b>" + _("Min Cost") + ":</b> $" + card.cost + "</p>";
                txt += "<p>+" + card.points + _("VP") + "</p>";

                card.x = 2 * this.license_width * (card_type_id % this.license_row_size);
                card.y = 2 * this.license_height * Math.floor(card_type_id / this.license_row_size);
            } else if (card.type == 'bonus') {
                txt += "<p><b>" + _("Discard") + "</b> => $" + card.coins + "</p>";
                card.type = 'boat'; 
                card.x = 2 * this.boat_width * (card_type_id - 9);
                card.y = 0;
            }

            
            txt +=  _(card.text);
            card.text = txt;

            return this.format_block("jstpl_card_tooltip", card);
        },

        ajaxAction: function (action, args)
        {
            if (!args) {
                args = [];
            }
            if (!args.hasOwnProperty('lock')) {
                args.lock = true;
            }
            var name = this.game_name;
            this.ajaxcall('/' + name + '/' + name + '/' + action + '.html',
                          args, this, function (result) {});
        },

        showActiveAuction: function ()
        {
            
            var node = $('auction').parentNode.id;
            if (node != 'auction_top') {
                
                dojo.place('auction', 'auction_top');
                this.placeOnObject('auction', 'auction_bottom');
                this.slideToObject('auction', 'auction_top').play();
                this.resetAuction();
            }

            
            
            for (var player in this.player_licenses) {
                var txt = this.auction.bids[player];
                if (!txt) {
                    txt = '-';
                }
                $('playerbid_' + player).textContent = txt;
            }

            
            dojo.style('auctionbids', 'display', 'block');

            if (this.auction.card_id) {
                
                dojo.query('#auctiontable > .stockitem').addClass('flt_disabled');
                dojo.removeClass(this.auction.table.getItemDivId(this.auction.card_id), 'flt_disabled');
                this.auction.table.selectItem(this.auction.card_id);
            }
        },

        hideAuction: function(state)
        {
            if (state.indexOf('auction') != -1 || state == 'nextPlayer')  {
                
                return;
            }

            
            var node = $('auction').parentNode.id;
            if (node != 'auction_bottom') {
                
                var _this = this;
                setTimeout(function() {
                    
                    dojo.place('auction', 'auction_bottom');
                    _this.placeOnObject('auction', 'auction_top');
                    _this.slideToObject('auction', 'auction_bottom').play();
                }, 1000);

                
                dojo.query('.flt_auction_done').removeClass('flt_auction_done');
                this.resetAuction();
            }
        },

        resetAuction: function(player_id)
        {
            if (player_id !== undefined) {
                
                dojo.addClass('playerbid_' + player_id + '_wrap', 'flt_auction_done');
            }

            
            this.auction.bids = [];
            this.auction.high_bid = 0;
            this.auction.card_id = 0;
            this.auction.winner = 0;
            this.auction.table.unselectAll();
        },

        onAuctionSelectionChanged: function()
        {
            
            this.showPossibleMoves();
            var items = this.auction.table.getSelectedItems();

            if (items.length > 0) {
                if (this.checkAction('bid')) {
                    

                    
                    if (this.gamedatas.gamestate.name != 'client_auctionSelect') {
                        this.showMessage("ERROR: Invalid game state for bidding", 'error');
                        return;
                    }

                    
                    if (!this.possible_moves[items[0].id]) {
                        this.showMessage(_('You cannot afford the minimum cost for this license'), 'error');
                        this.auction.table.unselectAll();
                        return;
                    }

                    
                    var card_info = this.card_infos[items[0].type];
                    var card_name = card_info['name'];
                    this.auction.card_id = items[0].id;
                    this.client_state_args.card_id = this.auction.card_id;
                    this.client_state_args.bid = card_info['cost'];

                    
                    
                    this.auction.high_bid = card_info['cost'] - 1;

                    
                    
                    this.gamedatas.gamestate.descriptionmyturn = _(card_name) + ': ' + _('${you} may open the bidding at') + ' ' + this.client_state_args.bid;
                    this.updatePageTitle();
                    this.removeActionButtons();
                    this.addActionButton('button_1', '-1', 'onMinusOne', null, false, 'gray');
                    this.addActionButton('button_2', '+1', 'onPlusOne');
                    this.addActionButton('button_3', _('Bid') + ': ' + this.client_state_args.bid, 'onBid');
                    if (this.gone_fishing) {
                        this.addActionButton('button_4', _("Go fishin' (pass)"), 'onPass');
                    } else {
                        this.addActionButton('button_4', _('Pass'), 'onPass');
                    }
                } else {
                    
                    this.auction.table.unselectAll();
                }
            } else if (this.checkAction('bid', true)) {
                
                this.gamedatas.gamestate.descriptionmyturn = _('${you} may select a license to bid on'),
                this.updatePageTitle();
                this.removeActionButtons();
                if (this.gone_fishing) {
                    this.addActionButton('button_1', _("Go fishin' (pass)"), 'onPass');
                } else {
                    this.addActionButton('button_1', _('Pass'), 'onPass');
                }
            }
        },

        discardAction: function(card)
        {
            if (!this.checkAction('discard'))
                return;

            
            this.client_state_args.card_id = card.id;
            this.ajaxAction('discard', this.client_state_args);
        },

        updateBuy: function()
        {
            var items = this.player_hand.getSelectedItems();
            if (this.debug) console.log('UPDATE BUY');
            if (this.debug) console.log(items);

            
            var coins = 0;
            for (var i in items) {
                var card = items[i];
                coins += this.card_infos[card.type]['coins'];
            }
            coins += this.client_state_args.fish_crates;

            
            this.gamedatas.gamestate.descriptionmyturn = _('${you} must discard cards to pay') + ' ' + coins + '/' + this.client_state_args.cost;
            this.updatePageTitle();
            this.removeActionButtons();
            var color = coins >= this.client_state_args.cost ? 'blue' : 'gray';
            this.addActionButton('button_1', _('Discard selected'), 'onBuy', null, false, color);
            this.addActionButton('button_2', _('Cancel'), 'onCancel', null, false, 'red');
        },

        onPlayerHandSelectionChanged: function()
        {
            
            this.showPossibleMoves();
            var items = this.player_hand.getSelectedItems();

            if (this.debug) console.log('hand select ' + this.gamedatas.gamestate.name);
            if (this.debug) console.log(items);
            let modifiedStateName = this.adjustLaunchHireState(this.gamedatas.gamestate.name, this.gamedatas.gamestate.args);
            
            switch(modifiedStateName)
            {
                case 'client_auctionWin':
                    
                    this.updateBuy();
                    break;
                case 'launch':
                    
                    if (this.checkAction('launchBoat') && items.length > 0) {
                        
                        var card = items[0];
                        if (this.debug) console.log('launch select');
                        if (this.debug) console.log(card);
                        if (!this.possible_moves[card.id].can_play) {
                            
                            this.showMessage(this.possible_moves[card.id].error, 'error');
                        } else {
                            
                            var card_info = dojo.clone(this.card_infos[card.type]);
                            if (this.debug) console.log(card_info);
                            card_info.cost -= this.discount; 
                            if (this.debug) console.log(card_info);

                            
                            this.client_state_args.boat_id = card.id;
                            this.client_state_args.cost = card_info.cost;
                            this.client_state_args.boat_type = card.type;

                            
                            this.coin_counter.incValue(-card_info.coins);

                            
                            this.player_boats[this.player_id].addToStockWithId(
                                card.type,
                                card.id,
                                this.player_hand.getItemDivId(card.id)
                            );
                            this.player_hand.removeFromStockById(card.id);

                            if (card_info.cost <= 0) {
                                
                                this.buyAction('launchBoat');
                            } else {
                                
                                playSound('move');

                                
                                var desc = _(card_info.name) + ': ' + _('${you} must discard cards to pay') + ' 0/${cost}';
                                this.setClientState('client_launchPay', {
                                    descriptionmyturn: desc,
                                    args: card_info
                                });
                                this.in_client_state = true;
                            }
                        }
                    }

                    
                    this.player_hand.unselectAll();
                    break;
                case 'client_launchPay':
                    
                    this.updateBuy();
                    break;
                case 'hire':
                    
                    if (items.length > 0 && this.checkAction('hireCaptain')) {
                        
                        if (!this.possible_moves[items[0].id]) {
                            this.showMessage(_('That card cannot be used to captain'), 'error');
                            this.player_hand.unselectAll();
                            break;
                        }

                        
                        this.client_state_args.card_id = items[0].id;

                        if (this.client_state_args.boat_id) {
                            
                            this.hireCaptain();
                        }
                    } else {
                        
                        delete this.client_state_args.card_id;
                    }
                    break;
                case 'draw':
                    
                    if (items.length > 0) {
                        if (!this.possible_moves[items[0].id]) {
                            this.showMessage(_('You must discard one of the two cards just drawn'), 'error');
                        } else {
                            this.discardAction(items[0]);
                        }
                    }
                    this.player_hand.unselectAll();
                    break;
                default:
                    
                    this.player_hand.unselectAll();
                    break;
            }
        },

        onPlayerBoatsSelectionChanged: function()
        {
            
            this.showPossibleMoves();
            var items = this.player_boats[this.player_id].getSelectedItems();

            if (items.length > 0 && this.checkAction('hireCaptain')) {
                
                if (!this.possible_moves[items[0].id]) {
                    this.showMessage(_('That boat already has a captain'), 'error');
                    this.player_boats[this.player_id].unselectAll();
                    return;
                }

                
                this.client_state_args.boat_id = items[0].id;

                if (this.client_state_args.card_id) {
                    
                    this.hireCaptain();
                }
            } else {
                
                delete this.client_state_args.boat_id;
            }
        },

        onClickFishCube: function(evt)
        {
            
            var div = evt.target.id;
            var is_processed = div.split('_')[0] == 'fish' ? false : true;

            var state = this.gamedatas.gamestate.name;
            if (this.debug) console.log('PROC FISH: ' + state);
            if (state == 'processing') { 
                if (!this.checkAction('processFish', true)) {
                    
                    return;
                }

                
                dojo.stopEvent(evt);

                if (is_processed) {
                    
                    this.showMessage(_('You may only process fish crates from boats'), 'error');
                    return;
                }

                
                var cube = evt.currentTarget.id;
                var boat_id = cube.split('_')[2];
                if (this.client_state_args.fish_ids[boat_id]) {
                    this.showMessage(_('You may only process one fish crate per boat'), 'error');
                    return;
                }

                
                this.client_state_args.fish_ids[boat_id] = true;
                this.processFishCube(boat_id, this.player_id);
                this.coin_counter.incValue(1);

                
                dojo.query('div[id^="fish_' + this.player_id + '_' + boat_id + '_"]').removeClass('flt_fish_selectable');

                if (dojo.query('.flt_fish_selectable').length == 0) {
                    
                    this.onProcess();
                }
            } else if (state == 'client_trading') { 
                if (!is_processed) {
                    
                    this.showMessage(_('You may only trade processed fish crates'), 'error');
                    return;
                }

                
                this.onTrade(evt);
            } else if (state == 'client_auctionWin' || state == 'client_launchPay') {
                
                if (!this.checkAction('buyLicense', true) && !this.checkAction('launchBoat', true))
                    return;

                dojo.stopEvent(evt);

                if (!is_processed) {
                    
                    this.showMessage(_('You may only trade processed fish crates'), 'error');
                    return;
                }

                
                dojo.toggleClass(evt.currentTarget, 'flt_fish_selectable');
                dojo.toggleClass(evt.currentTarget, 'flt_fish_selected');

                
                this.client_state_args.fish_crates = dojo.query('.flt_fish_selected').length;
                this.updateBuy();
            }
        },

        onPass: function(evt)
        {
            dojo.stopEvent(evt);
            if (!this.checkAction('pass'))
                return;

            if (this.gamedatas.gamestate.name == 'client_auctionSelect') {
                
                this.resetAuction(this.player_id);
            }

            
            this.client_state_args = {};
            this.ajaxAction('pass');
        },

        onPlusOne: function(evt)
        {
            dojo.stopEvent(evt);

            
            this.client_state_args.bid += 1;

            if (this.debug) console.log('PLUS ONE: ' + this.client_state_args.bid);

            
            var max_bid = this.player_coins;
            if (this.client_state_args.bid > max_bid) {
                this.showMessage(_('You cannot bid more than ') + max_bid, 'error');
                this.client_state_args.bid = max_bid;
            }

            
            if (this.client_state_args.bid == max_bid) {
                
                dojo.removeClass('button_2', 'bgabutton_blue');
                dojo.addClass('button_2', 'bgabutton_gray');
            }
            if (this.client_state_args.bid < max_bid) {
                
                dojo.removeClass('button_1', 'bgabutton_gray');
                dojo.addClass('button_1', 'bgabutton_blue');
            }

            
            $('button_3').textContent = _('Bid') + ': ' + this.client_state_args.bid;
        },

        onMinusOne: function(evt)
        {
            dojo.stopEvent(evt);

            
            this.client_state_args.bid -= 1;

            if (this.debug) console.log('MINUS ONE: ' + this.client_state_args.bid);

            
            var min_bid = this.auction.high_bid + 1;
            if (this.client_state_args.bid < min_bid) {
                this.showMessage(_('You must bid at least ') + min_bid, 'error');
                this.client_state_args.bid = min_bid;
            }

            
            if (this.client_state_args.bid == min_bid) {
                
                dojo.removeClass('button_1', 'bgabutton_blue');
                dojo.addClass('button_1', 'bgabutton_gray');
            }
            if (this.client_state_args.bid < this.player_coins) {
                
                dojo.removeClass('button_2', 'bgabutton_gray');
                dojo.addClass('button_2', 'bgabutton_blue');
            }

            
            $('button_3').textContent = _('Bid') + ': ' + this.client_state_args.bid;
        },

        onBid: function(evt)
        {
            dojo.stopEvent(evt);
            if (!this.checkAction('bid'))
                return;

            
            this.ajaxAction('bid', this.client_state_args);
        },

        onBuy: function(evt)
        {
            dojo.stopEvent(evt);

            
            var state = this.gamedatas.gamestate.name;
            if (state == 'client_auctionWin') {
                var action = 'buyLicense';
            } else if (state == 'client_launchPay' || state == 'launchHire') {      
                var action = 'launchBoat';
            } else {
                
                this.showMessage('ERROR: Impossible buy action', 'error');
                return;
            }

            
            this.buyAction(action);
        },

        buyAction: function(action)
        {
            if (!this.checkAction(action))
                return;

            
            var items = this.player_hand.getSelectedItems();
            this.client_state_args.card_ids = '';
            for (var i in items) {
                this.client_state_args.card_ids += items[i].id + ';';
            }

            
            
            dojo.query('.flt_fish_selected').removeClass('flt_fish_selected')

            
            this.ajaxAction(action, this.client_state_args);
        },

        hireCaptain: function()
        {
            if (!this.checkAction('hireCaptain'))
                return;

            if (this.debug) console.log(this.client_state_args);

            
            this.ajaxAction('hireCaptain', this.client_state_args);
        },

        onProcess: function(evt)
        {
            if (!this.checkAction('processFish'))
                return;

            
            this.client_state_args.card_ids = '';
            for (var id in this.client_state_args.fish_ids) {
                this.client_state_args.card_ids += id + ';';
            }

            
            this.ajaxAction('processFish', this.client_state_args);

            
            this.setClientState('client_trading', {
                descriptionmyturn: _('${you} may trade a fish crate'),
                args: {'moves': [true]},
            });
        },

        onTrade: function(evt)
        {
            if (!this.checkAction('tradeFish', true)) {
                
                return;
            }

            
            dojo.stopEvent(evt);

            
            this.ajaxAction('tradeFish', null);
        },

        onCancel: function(evt)
        {
            dojo.stopEvent(evt);

            
            var state = this.gamedatas.gamestate.name;
            if (this.debug) console.log('CANCEL: ' + state);

            if (state == 'client_launchPay') {
                
                
                var card_id = this.client_state_args.boat_id
                this.player_hand.addToStockWithId(
                    this.client_state_args.boat_type,
                    this.client_state_args.boat_id,
                    this.player_boats[this.player_id].getItemDivId(this.client_state_args.boat_id)
                );
                this.player_boats[this.player_id].removeFromStockById(this.client_state_args.boat_id);
                this.coin_counter.incValue(this.card_infos[this.client_state_args.boat_type].coins);
                this.in_client_state = false;
                delete this.client_state_args.boat_id; 
            } else if (state == 'processing') {
                if (this.debug) console.log('UNDO PROC');
                
                
                
                
                for (var card_id in this.client_state_args.fish_ids) {
                    if (this.debug) console.log('READD FISH ' + card_id);
                    this.removeFishCube(this.player_id);
                    this.addFishCube(card_id, this.player_id);
                    this.coin_counter.incValue(-1);
                }
            }

            
            dojo.query('.flt_fish_selected').removeClass('flt_fish_selected')

            
            playSound('move');

            
            this.restoreServerGameState();
        },
        
        
        

        /*
            setupNotifications:
            
            In this method, you associate each of your game notifications with your local method to handle it.
            
            Note: game notification names correspond to "notifyAllPlayers" and "notifyPlayer" calls in
                  your fleet.game.php file.
        
        */
        setupNotifications: function()
        {
            if (this.debug) console.log( 'notifications subscriptions setup' );
            
            dojo.subscribe('firstPlayer', this, 'notif_firstPlayer');
            this.notifqueue.setSynchronous('firstPlayer', 1000);
            dojo.subscribe('pass', this, 'notif_pass');
            dojo.subscribe('possibleMoves', this, 'notif_possibleMoves');
            dojo.subscribe('auctionSelect', this, 'notif_auctionSelect');
            dojo.subscribe('auctionBid', this, 'notif_auctionBid');
            dojo.subscribe('auctionWin', this, 'notif_auctionWin');
            dojo.subscribe('buyLicense', this, 'notif_buyLicense');
            this.notifqueue.setSynchronous('buyLicense', 1000);
            dojo.subscribe('drawLicenses', this, 'notif_drawLicenses');
            this.notifqueue.setSynchronous('drawLicense', 500);
            dojo.subscribe('launchBoat', this, 'notif_launchBoat');
            dojo.subscribe('hireCaptain', this, 'notif_hireCaptain');
            this.notifqueue.setSynchronous('hireCaptain', 750);
            dojo.subscribe('fishing', this, 'notif_fishing');
            this.notifqueue.setSynchronous('fishing', 1000);
            dojo.subscribe('processFish', this, 'notif_processFish');
            dojo.subscribe('tradeFish', this, 'notif_tradeFish');
            dojo.subscribe('drawLog', this, 'notif_drawLog');
            dojo.subscribe('draw', this, 'notif_draw');
            this.notifqueue.setSynchronous('draw', 1000);
            dojo.subscribe('discardLog', this, 'notif_discardLog');
            dojo.subscribe('discard', this, 'notif_discard');
            this.notifqueue.setSynchronous('discard', 500);
            dojo.subscribe('finalRound', this, 'notif_finalRound');
            this.notifqueue.setSynchronous('finalRound', 1500);
            dojo.subscribe('bonusScore', this, 'notif_bonusScore');
            dojo.subscribe('finalScore', this, 'notif_finalScore');
        },  

        notif_firstPlayer: function(notif)
        {
            if (this.debug) console.log('notify_firstPlayer');
            if (this.debug) console.log(notif);

            var durration = 1000; 

            
            dojo.query('.flt_first_player').removeClass('flt_first_player');
            var curr = 'first_anchor_p' + notif.args.current_player_id;
            var next = 'first_anchor_p' + notif.args.next_player_id;

            
            var tmp = '<div id="tmp_first_token" style="z-index:99" class="flt_icon_first flt_first_player"></div>';
            this.slideTemporaryObject(tmp, 'overall_player_board_' + notif.args.current_player_id, curr, next, durration, 0);

            
            setTimeout(function() {
                dojo.addClass('first_player_p' + notif.args.next_player_id, 'flt_first_player');
            }, durration);
        },

        notif_pass: function (notif)
        {
            if (this.debug) console.log('notify_pass');
            if (this.debug) console.log(notif);

            if (notif.args.in_auction) {
                
                this.auction.bids[parseInt(notif.args.player_id)] = 'pass';
                if (notif.args.auction_done) {
                    
                    this.resetAuction(notif.args.player_id);
                    if (notif.args.card) {
                        
                        this.hand_counters[notif.args.player_id].incValue(1);
                        if (notif.args.player_id == this.player_id) {
                            this.player_hand.addToStockWithId(notif.args.card.type_arg, notif.args.card.id, 'boatcount');
                            this.coin_counter.incValue(2);
                        }
                    }
                }
            }
        },

        notif_possibleMoves: function (notif)
        {
            if (this.debug) console.log('notify_possibleMoves');
            if (this.debug) console.log(notif);
            this.possible_moves = notif.args.moves;
            this.player_coins = parseInt(notif.args.coins);
            this.coin_counter.setValue(this.player_coins - this.discount);
        },

        notif_auctionSelect: function (notif)
        {
            if (this.debug) console.log('notify_auctionSelect');
            if (this.debug) console.log(notif);
            this.auction.card_id = parseInt(notif.args.card_id);
        },

        notif_auctionBid: function (notif)
        {
            if (this.debug) console.log('notify_auctionBid');
            if (this.debug) console.log(notif);
            this.auction.bids[parseInt(notif.args.player_id)] = parseInt(notif.args.bid);
            this.auction.high_bid = parseInt(notif.args.bid);
        },

        notif_auctionWin: function (notif)
        {
            if (this.debug) console.log('notify_auctionWin');
            if (this.debug) console.log(notif);
            this.auction.winner = parseInt(notif.args.player_id);
            this.auction.bids[this.auction.winner] = parseInt(notif.args.bid);
            this.auction.high_bid = parseInt(notif.args.bid);
        },

        notif_buyLicense: function (notif)
        {
            if (this.debug) console.log('notify_buyLicense');
            if (this.debug) console.log(notif);

            if (notif.args.player_id == this.player_id) {
                
                for (var i in notif.args.card_ids) {
                    
                    var card = this.player_hand.getItemById(notif.args.card_ids[i]);
                    this.coin_counter.incValue(-this.card_infos[card.type].coins);
                    
                    this.player_hand.removeFromStockById(notif.args.card_ids[i], 'boatcount', true);
                }
                this.player_hand.updateDisplay(); 

                
                this.coin_counter.incValue(-notif.args.nbr_fish);

                
                if (notif.args.license_type == this.constants.shrimp) {
                    
                    this.discount += 1;
                    dojo.byId('discount_p' + this.player_id).textContent = '+' + this.discount;
                }
            } else {
                
                this.slideTemporaryObject(this.format_block('jstpl_captain', {id:999}),
                    'flt_counters', 'player_board_' + notif.args.player_id, 'boaticon');
            }

            
            for (var i = 0; i < parseInt(notif.args.nbr_fish); i++) {
                this.removeFishCube(notif.args.player_id);
            }

            
            this.hand_counters[notif.args.player_id].incValue(-notif.args.card_ids.length);
            this.discard_counter.incValue(notif.args.discards - this.discard_counter.getValue());

            
            var src = this.auction.table.getItemDivId(notif.args.license_id);
            this.addPlayerLicense(notif.args.player_id, notif.args.license_type, notif.args.license_id, src)
            this.auction.table.removeFromStockById(notif.args.license_id);

            
            this.scoreCtrl[notif.args.player_id].incValue(notif.args.points);

            
            this.resetAuction(notif.args.player_id);
        },

        notif_drawLicenses: function (notif)
        {
            if (this.debug) console.log('notify_drawLicenses');
            if (this.debug) console.log(notif);

            if (notif.args.discard) {
                
                this.auction.table.removeAllTo('site-logo');
            }

            
            for (var i in notif.args.cards) {
                var card = notif.args.cards[i];
                this.auction.table.addToStockWithId(card.type_arg, card.id, 'licensecount');
                if (this.incCounterValue(this.license_counter, -1)) {
                    
                    dojo.style('licenseicon', {'opacity': '0.5', 'border': 'none'});
                    dojo.style('licensecount', {'color': 'red', 'font-weight': 'bold'});
                }
            }
        },

        notif_launchBoat: function (notif)
        {
            if (this.debug) console.log('notify_launchBoat');
            if (this.debug) console.log(notif);

            if (notif.args.player_id == this.player_id) {
                
                if (!$(this.player_boats[this.player_id].getItemDivId(notif.args.boat_id))) {
                    this.player_boats[this.player_id].addToStockWithId(
                        notif.args.boat_type,
                        notif.args.boat_id,
                        this.player_hand.getItemDivId(notif.args.boat_id)
                    );

                    this.player_hand.removeFromStockById(notif.args.boat_id);
                }

                
                this.coin_counter.incValue(-notif.args.nbr_fish);

                
                for (var i in notif.args.card_ids) {
                    
                    var card = this.player_hand.getItemById(notif.args.card_ids[i]);
                    this.coin_counter.incValue(-this.card_infos[card.type].coins);

                    
                    this.player_hand.removeFromStockById(notif.args.card_ids[i], 'boatcount', true);
                }
                this.player_hand.updateDisplay(); 
            } else {
                
                
                this.player_boats[notif.args.player_id].addToStockWithId(
                    notif.args.boat_type,
                    notif.args.boat_id,
                    'overall_player_board_' + notif.args.player_id
                );
                
                if (notif.args.nbr_cards != 0) {
                    this.slideTemporaryObject(this.format_block('jstpl_captain', {id:999}),
                        'flt_counters', 'player_board_' + notif.args.player_id, 'boaticon');
                }
            }

            
            this.hand_counters[notif.args.player_id].incValue(-notif.args.card_ids.length-1);
            this.discard_counter.incValue(notif.args.discards - this.discard_counter.getValue());

            
            this.scoreCtrl[notif.args.player_id].incValue(notif.args.points);

            
            for (var i = 0; i < parseInt(notif.args.nbr_fish); i++) {
                this.removeFishCube(notif.args.player_id);
            }
        },

        notif_hireCaptain: function (notif)
        {
            if (this.debug) console.log('notify_hireCaptain');
            if (this.debug) console.log(notif);

            
            
            dojo.style('captain_' + notif.args.boat_id, 'opacity', '0');
            dojo.style('captain_' + notif.args.boat_id, 'display', 'block');

            if (notif.args.player_id == this.player_id) {
                
                var card = this.player_hand.getItemById(notif.args.card_id);
                this.coin_counter.incValue(-this.card_infos[card.type].coins);

                
                var div = this.player_hand.getItemDivId(notif.args.card_id);
                var node = dojo.query('#' + div + ' > .flt_boat_wrap')[0];
                node.style['transform'] = 'rotateY(180deg)';

                
                var _this = this;
                setTimeout(function() {
                    _this.player_hand.removeFromStockById(notif.args.card_id, 'captain_' + notif.args.boat_id);
                }, 500);

                var delay = 950;
            } else {
                
                
                dojo.place(this.format_block('jstpl_captain', {id:999}), 'player_board_' + notif.args.player_id);
                this.placeOnObject('tmp_captain_999', 'player_board_' + notif.args.player_id);
                this.slideToObjectAndDestroy('tmp_captain_999', 'captain_' + notif.args.boat_id, 500, 0);
                var delay = 500;
            }

            
            setTimeout(function() {
                dojo.style('captain_' + notif.args.boat_id, 'opacity', '1');
            }, delay);

            
            this.hand_counters[notif.args.player_id].incValue(-1);
        },

        notif_fishing: function (notif)
        {
            if (this.debug) console.log('notify_fishing');
            if (this.debug) console.log(notif);

            
            for (var i in notif.args.card_ids) {
                this.addFishCube(notif.args.card_ids[i], notif.args.player_id);
            }

            
            if (this.incCounterValue(this.fish_counter, -notif.args.nbr_fish)) {
                
                dojo.style('fishicon', 'opacity', '0.5');
                dojo.style('fishcount', {'color': 'red', 'font-weight': 'bold'});
            }

            
            this.scoreCtrl[notif.args.player_id].incValue(notif.args.nbr_fish);
        },

        notif_processFish: function (notif)
        {
            if (this.debug) console.log('notify_processFish');
            if (this.debug) console.log(notif);

            if (this.client_state_args.fish_ids == undefined ||
                this.client_state_args.fish_ids[notif.args.card_ids[0]] == undefined)
            {
                
                
                for (var i in notif.args.card_ids) {
                    this.processFishCube(notif.args.card_ids[i], notif.args.player_id);
                }
            }

            
            this.scoreCtrl[notif.args.player_id].incValue(-notif.args.nbr_fish);
        },

        notif_tradeFish: function (notif)
        {
            if (this.debug) console.log('notify_tradeFish');
            if (this.debug) console.log(notif);

            this.removeFishCube(notif.args.player_id);
            if (notif.args.player_id == this.player_id) {
                this.coin_counter.incValue(-1);
            }
            
        },

        notif_draw: function (notif)
        {
            if (this.debug) console.log('notify_draw');
            if (this.debug) console.log(notif);

            
            for (var i in notif.args.cards) {
                var card = notif.args.cards[i];
                this.player_hand.addToStockWithId(card.type_arg, card.id, 'boatcount');
                this.coin_counter.incValue(this.card_infos[card.type_arg].coins);
            }
        },

        notif_drawLog: function (notif)
        {
            if (this.debug) console.log('notify_drawLog');
            if (this.debug) console.log(notif);

            
            if (notif.args.shuffle) {
                this.boat_counter.setValue(notif.args.deck_nbr);
                this.discard_counter.setValue(0);
            } else {
                this.boat_counter.incValue(-notif.args.nbr);
            }

            
            this.hand_counters[notif.args.player_id].incValue(notif.args.nbr);

            if (notif.args.player_id != this.player_id) {
                
                this.slideTemporaryObject(this.format_block('jstpl_captain', {id:999}),
                    'flt_counters', 'boaticon', 'player_board_' + notif.args.player_id);
            }
        },

        notif_discardLog: function (notif)
        {
            if (this.debug) console.log('notify_discardLog');
            if (this.debug) console.log(notif);

            
            this.hand_counters[notif.args.player_id].incValue(-1);
            this.discard_counter.incValue(1);
            
        },

        notif_discard: function (notif)
        {
            if (this.debug) console.log('notify_discard');
            if (this.debug) console.log(notif);

            
            var card = this.player_hand.getItemById(notif.args.discard.id);
            this.player_hand.removeFromStockById(notif.args.discard.id, 'boaticon');
            this.coin_counter.incValue(-this.card_infos[notif.args.discard.type_arg].coins);

            
            
            dojo.query('.flt_selectable').removeClass('flt_selectable');
        },

        notif_finalRound: function(notif)
        {
            if (this.debug) console.log('notif_finalRound');
            if (this.debug) console.log(notif);

            
            this.showMessage(_('This is the last round!'), 'info');

            
            dojo.style('licenseicon', 'opacity', '0.5');
            dojo.style('licensecount', {'color': 'red', 'font-weight': 'bold'});
        },

        notif_bonusScore: function(notif)
        {
            if (this.debug) console.log('notif_bonusScore');
            if (this.debug) console.log(notif);
            
            this.scoreCtrl[notif.args.player_id].incValue(notif.args.points);
        },

        notif_finalScore: function(notif)
        {
            if (this.debug) console.log('notif_finalScore');
            if (this.debug) console.log(notif);

            
            this.showFinalScore(notif.args);
        },
   });             
});
