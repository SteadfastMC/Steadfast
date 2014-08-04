
<!-- saved from url=(0116)https://raw.githubusercontent.com/zhuowei/PocketMine-MP/ab7d4462c92d9f95e281c7670a818b91935f0924/src/world/Level.php -->
<html><head><meta http-equiv="Content-Type" content="text/html; charset=UTF-8"><style type="text/css"></style></head><body><pre style="word-wrap: break-word; white-space: pre-wrap;">&lt;?php

/**
 *
 *  ____            _        _   __  __ _                  __  __ ____  
 * |  _ \ ___   ___| | _____| |_|  \/  (_)_ __   ___      |  \/  |  _ \ 
 * | |_) / _ \ / __| |/ / _ \ __| |\/| | | '_ \ / _ \_____| |\/| | |_) |
 * |  __/ (_) | (__|   &lt;  __/ |_| |  | | | | | |  __/_____| |  | |  __/
 * |_|   \___/ \___|_|\_\___|\__|_|  |_|_|_| |_|\___|     |_|  |_|_|
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Lesser General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * @author PocketMine Team
 * @link http://www.pocketmine.net/
 *
 *
*/

class Level{
	public $entities, $tiles, $blockUpdates, $nextSave, $players = array(), $level;
	private $time, $startCheck, $startTime, $server, $name, $usedChunks, $changedBlocks, $changedCount, $stopTime;

	public function __construct(PMFLevel $level, Config $entities, Config $tiles, Config $blockUpdates, $name){
		$this-&gt;server = ServerAPI::request();
		$this-&gt;level = $level;
		$this-&gt;level-&gt;level = $this;
		$this-&gt;entities = $entities;
		$this-&gt;tiles = $tiles;
		$this-&gt;blockUpdates = $blockUpdates;
		$this-&gt;startTime = $this-&gt;time = (int) $this-&gt;level-&gt;getData("time");
		$this-&gt;nextSave = $this-&gt;startCheck = microtime(true);
		$this-&gt;nextSave += 90;
		$this-&gt;stopTime = false;
		$this-&gt;server-&gt;schedule(15, array($this, "checkThings"), array(), true);
		$this-&gt;server-&gt;schedule(20 * 13, array($this, "checkTime"), array(), true);
		$this-&gt;name = $name;
		$this-&gt;usedChunks = array();
		$this-&gt;changedBlocks = array();
		$this-&gt;changedCount = array();
	}

	public function close(){
		$this-&gt;__destruct();
	}

	public function useChunk($X, $Z, Player $player){
		if(!isset($this-&gt;usedChunks[$X.".".$Z])){
			$this-&gt;usedChunks[$X.".".$Z] = array();
		}
		$this-&gt;usedChunks[$X.".".$Z][$player-&gt;CID] = true;
		if(isset($this-&gt;level)){
			$this-&gt;level-&gt;loadChunk($X, $Z);
		}
	}

	public function freeAllChunks(Player $player){
		foreach($this-&gt;usedChunks as $i =&gt; $c){
			unset($this-&gt;usedChunks[$i][$player-&gt;CID]);
		}
	}

	public function freeChunk($X, $Z, Player $player){
		unset($this-&gt;usedChunks[$X.".".$Z][$player-&gt;CID]);
	}

	public function checkTime(){
		if(!isset($this-&gt;level)){
			return false;
		}
		$now = microtime(true);
		if($this-&gt;stopTime == true){

		}else{
			$time = $this-&gt;startTime + ($now - $this-&gt;startCheck) * 20;
		}
		if($this-&gt;server-&gt;api-&gt;dhandle("time.change", array("level" =&gt; $this, "time" =&gt; $time)) !== false){
			$this-&gt;time = $time;

			$pk = new SetTimePacket;
			$pk-&gt;time = (int) $this-&gt;time;
			$pk-&gt;started = $this-&gt;stopTime == false;
			$this-&gt;server-&gt;api-&gt;player-&gt;broadcastPacket($this-&gt;players, $pk);
		}
	}

	public function checkThings(){
		if(!isset($this-&gt;level)){
			return false;
		}
		$now = microtime(true);
		$this-&gt;players = $this-&gt;server-&gt;api-&gt;player-&gt;getAll($this);

		if(count($this-&gt;changedCount) &gt; 0){
			arsort($this-&gt;changedCount);
			$resendChunks = array();
			foreach($this-&gt;changedCount as $index =&gt; $count){
				if($count &lt; 582){//Optimal value, calculated using the relation between minichunks and single packets
					break;
				}
				foreach($this-&gt;players as $p){
					unset($p-&gt;chunksLoaded[$index]);
				}
				unset($this-&gt;changedBlocks[$index]);
			}
			$this-&gt;changedCount = array();

			if(count($this-&gt;changedBlocks) &gt; 0){
				foreach($this-&gt;changedBlocks as $blocks){
					foreach($blocks as $b){
						$pk = new UpdateBlockPacket;
						$pk-&gt;x = $b-&gt;x;
						$pk-&gt;y = $b-&gt;y;
						$pk-&gt;z = $b-&gt;z;
						$pk-&gt;block = $b-&gt;getID();
						$pk-&gt;meta = $b-&gt;getMetadata();
						$this-&gt;server-&gt;api-&gt;player-&gt;broadcastPacket($this-&gt;players, $pk);
					}
				}
				$this-&gt;changedBlocks = array();
			}
		}

		if($this-&gt;nextSave &lt; $now){
			foreach($this-&gt;usedChunks as $i =&gt; $c){
				if(count($c) === 0){
					unset($this-&gt;usedChunks[$i]);
					$X = explode(".", $i);
					$Z = array_pop($X);
					$this-&gt;level-&gt;unloadChunk((int) array_pop($X), (int) $Z, $this-&gt;server-&gt;saveEnabled);
				}
			}
			$this-&gt;save(false, false);
		}
	}

	public function __destruct(){
		if(isset($this-&gt;level)){
			$this-&gt;save(false, false);
			$this-&gt;level-&gt;close();
			unset($this-&gt;level);
		}
	}

	public function save($force = false, $extra = true){
		if(!isset($this-&gt;level)){
			return false;
		}
		if($this-&gt;server-&gt;saveEnabled === false and $force === false){
			return;
		}

		if($extra !== false){
			$entities = array();
			foreach($this-&gt;server-&gt;api-&gt;entity-&gt;getAll($this) as $entity){
				if($entity-&gt;class === ENTITY_MOB){
					$entities[] = array(
						"id" =&gt; $entity-&gt;type,
						"Color" =&gt; @$entity-&gt;data["Color"],
						"Sheared" =&gt; @$entity-&gt;data["Sheared"],
						"Health" =&gt; $entity-&gt;health,
						"Pos" =&gt; array(
							0 =&gt; $entity-&gt;x,
							1 =&gt; $entity-&gt;y,
							2 =&gt; $entity-&gt;z,
						),
						"Rotation" =&gt; array(
							0 =&gt; $entity-&gt;yaw,
							1 =&gt; $entity-&gt;pitch,
						),
					);
				}elseif($entity-&gt;class === ENTITY_OBJECT){
					if($entity-&gt;type === OBJECT_PAINTING){
						$entities[] = array(
							"id" =&gt; $entity-&gt;type,
							"TileX" =&gt; $entity-&gt;x,
							"TileY" =&gt; $entity-&gt;y,
							"TileZ" =&gt; $entity-&gt;z,
							"Health" =&gt; $entity-&gt;health,
							"Motive" =&gt; $entity-&gt;data["Motive"],
							"Pos" =&gt; array(
								0 =&gt; $entity-&gt;x,
								1 =&gt; $entity-&gt;y,
								2 =&gt; $entity-&gt;z,
							),
							"Rotation" =&gt; array(
								0 =&gt; $entity-&gt;yaw,
								1 =&gt; $entity-&gt;pitch,
							),
						);
					}else{
						$entities[] = array(
							"id" =&gt; $entity-&gt;type,
							"Health" =&gt; $entity-&gt;health,
							"Pos" =&gt; array(
								0 =&gt; $entity-&gt;x,
								1 =&gt; $entity-&gt;y,
								2 =&gt; $entity-&gt;z,
							),
							"Rotation" =&gt; array(
								0 =&gt; $entity-&gt;yaw,
								1 =&gt; $entity-&gt;pitch,
							),
						);
					}
				}elseif($entity-&gt;class === ENTITY_FALLING){
					$entities[] = array(
						"id" =&gt; $entity-&gt;type,
						"Health" =&gt; $entity-&gt;health,
						"Tile" =&gt; $entity-&gt;data["Tile"],
						"Pos" =&gt; array(
							0 =&gt; $entity-&gt;x,
							1 =&gt; $entity-&gt;y,
							2 =&gt; $entity-&gt;z,
						),
						"Rotation" =&gt; array(
							0 =&gt; 0,
							1 =&gt; 0,
						),
					);
				}elseif($entity-&gt;class === ENTITY_ITEM){
					$entities[] = array(
						"id" =&gt; 64,
						"Item" =&gt; array(
							"id" =&gt; $entity-&gt;type,
							"Damage" =&gt; $entity-&gt;meta,
							"Count" =&gt; $entity-&gt;stack,
						),
						"Health" =&gt; $entity-&gt;health,
						"Pos" =&gt; array(
							0 =&gt; $entity-&gt;x,
							1 =&gt; $entity-&gt;y,
							2 =&gt; $entity-&gt;z,
						),
						"Rotation" =&gt; array(
							0 =&gt; 0,
							1 =&gt; 0,
						),
					);
				}
			}
			$this-&gt;entities-&gt;setAll($entities);
			$this-&gt;entities-&gt;save();
			$tiles = array();
			foreach($this-&gt;server-&gt;api-&gt;tile-&gt;getAll($this) as $tile){
				$tiles[] = $tile-&gt;data;
			}
			$this-&gt;tiles-&gt;setAll($tiles);
			$this-&gt;tiles-&gt;save();

			$blockUpdates = array();
			$updates = $this-&gt;server-&gt;query("SELECT x,y,z,type,delay FROM blockUpdates WHERE level = '".$this-&gt;getName()."';");
			if($updates !== false and $updates !== true){
				$timeu = microtime(true);
				while(($bupdate = $updates-&gt;fetchArray(SQLITE3_ASSOC)) !== false){
					$bupdate["delay"] = max(1, ($bupdate["delay"] - $timeu) * 20);
					$blockUpdates[] = $bupdate;
				}
			}

			$this-&gt;blockUpdates-&gt;setAll($blockUpdates);
			$this-&gt;blockUpdates-&gt;save();

		}

		$this-&gt;level-&gt;setData("time", (int) $this-&gt;time);
		$this-&gt;level-&gt;doSaveRound();
		$this-&gt;level-&gt;saveData();
		$this-&gt;nextSave = microtime(true) + 45;
	}

	public function getBlockRaw(Vector3 $pos){
		$b = $this-&gt;level-&gt;getBlock($pos-&gt;x, $pos-&gt;y, $pos-&gt;z);
		return BlockAPI::get($b[0], $b[1], new Position($pos-&gt;x, $pos-&gt;y, $pos-&gt;z, $this));
	}

	public function getBlock(Vector3 $pos){
		if(!isset($this-&gt;level) or ($pos instanceof Position) and $pos-&gt;level !== $this){
			return false;
		}
		$b = $this-&gt;level-&gt;getBlock($pos-&gt;x, $pos-&gt;y, $pos-&gt;z);
		return BlockAPI::get($b[0], $b[1], new Position($pos-&gt;x, $pos-&gt;y, $pos-&gt;z, $this));
	}

	public function setBlockRaw(Vector3 $pos, Block $block, $direct = true, $send = true){
		if(($ret = $this-&gt;level-&gt;setBlock($pos-&gt;x, $pos-&gt;y, $pos-&gt;z, $block-&gt;getID(), $block-&gt;getMetadata())) === true and $send !== false){
			if($direct === true){
				$pk = new UpdateBlockPacket;
				$pk-&gt;x = $pos-&gt;x;
				$pk-&gt;y = $pos-&gt;y;
				$pk-&gt;z = $pos-&gt;z;
				$pk-&gt;block = $block-&gt;getID();
				$pk-&gt;meta = $block-&gt;getMetadata();
				$this-&gt;server-&gt;api-&gt;player-&gt;broadcastPacket($this-&gt;players, $pk);
			}elseif($direct === false){
				if(!($pos instanceof Position)){
					$pos = new Position($pos-&gt;x, $pos-&gt;y, $pos-&gt;z, $this);
				}
				$block-&gt;position($pos);
				$i = ($pos-&gt;x &gt;&gt; 4).":".($pos-&gt;y &gt;&gt; 4).":".($pos-&gt;z &gt;&gt; 4);
				if(ADVANCED_CACHE == true){
					Cache::remove("world:{$this-&gt;name}:".($pos-&gt;x &gt;&gt; 4).":".($pos-&gt;z &gt;&gt; 4));
				}
				if(!isset($this-&gt;changedBlocks[$i])){
					$this-&gt;changedBlocks[$i] = array();
					$this-&gt;changedCount[$i] = 0;
				}
				$this-&gt;changedBlocks[$i][] = clone $block;
				++$this-&gt;changedCount[$i];
			}
		}
		return $ret;
	}

	public function setBlock(Vector3 $pos, Block $block, $update = true, $tiles = false, $direct = false){
		if(!isset($this-&gt;level) or (($pos instanceof Position) and $pos-&gt;level !== $this) or $pos-&gt;x &lt; 0 or $pos-&gt;y &lt; 0 or $pos-&gt;z &lt; 0){
			return false;
		}

		$ret = $this-&gt;level-&gt;setBlock($pos-&gt;x, $pos-&gt;y, $pos-&gt;z, $block-&gt;getID(), $block-&gt;getMetadata());
		if($ret === true){
			if(!($pos instanceof Position)){
				$pos = new Position($pos-&gt;x, $pos-&gt;y, $pos-&gt;z, $this);
			}
			$block-&gt;position($pos);

			if($direct === true){
				$pk = new UpdateBlockPacket;
				$pk-&gt;x = $pos-&gt;x;
				$pk-&gt;y = $pos-&gt;y;
				$pk-&gt;z = $pos-&gt;z;
				$pk-&gt;block = $block-&gt;getID();
				$pk-&gt;meta = $block-&gt;getMetadata();
				$this-&gt;server-&gt;api-&gt;player-&gt;broadcastPacket($this-&gt;players, $pk);
			}else{
				$i = ($pos-&gt;x &gt;&gt; 4).":".($pos-&gt;y &gt;&gt; 4).":".($pos-&gt;z &gt;&gt; 4);
				if(!isset($this-&gt;changedBlocks[$i])){
					$this-&gt;changedBlocks[$i] = array();
					$this-&gt;changedCount[$i] = 0;
				}
				if(ADVANCED_CACHE == true){
					Cache::remove("world:{$this-&gt;name}:".($pos-&gt;x &gt;&gt; 4).":".($pos-&gt;z &gt;&gt; 4));
				}
				$this-&gt;changedBlocks[$i][] = clone $block;
				++$this-&gt;changedCount[$i];
			}

			if($update === true){
				$this-&gt;server-&gt;api-&gt;block-&gt;blockUpdateAround($pos, BLOCK_UPDATE_NORMAL, 1);
				$this-&gt;server-&gt;api-&gt;entity-&gt;updateRadius($pos, 3);
			}
			if($tiles === true){
				if(($t = $this-&gt;server-&gt;api-&gt;tile-&gt;get($pos)) !== false){
					$t-&gt;close();
				}
			}
		}
		return $ret;
	}

	public function getMiniChunk($X, $Z, $Y){
		if(!isset($this-&gt;level)){
			return false;
		}
		return $this-&gt;level-&gt;getMiniChunk($X, $Z, $Y);
	}

	public function setMiniChunk($X, $Z, $Y, $data){
		if(!isset($this-&gt;level)){
			return false;
		}
		$this-&gt;changedCount[$X.":".$Y.":".$Z] = 4096;
		if(ADVANCED_CACHE == true){
			Cache::remove("world:{$this-&gt;name}:$X:$Z");
		}
		return $this-&gt;level-&gt;setMiniChunk($X, $Z, $Y, $data);
	}

	public function loadChunk($X, $Z){
		if(!isset($this-&gt;level)){
			return false;
		}
		return $this-&gt;level-&gt;loadChunk($X, $Z);
	}

	public function unloadChunk($X, $Z){
		if(!isset($this-&gt;level)){
			return false;
		}
		Cache::remove("world:{$this-&gt;name}:$X:$Z");
		return $this-&gt;level-&gt;unloadChunk($X, $Z, $this-&gt;server-&gt;saveEnabled);
	}

	public function getOrderedChunk($X, $Z, $Yndex){
		if(!isset($this-&gt;level)){
			return false;
		}
		if(ADVANCED_CACHE == true and $Yndex == 0xff){
			$identifier = "world:{$this-&gt;name}:$X:$Z";
			if(($cache = Cache::get($identifier)) !== false){
				return $cache;
			}
		}


		$raw = array();
		for($Y = 0; $Y &lt; 8; ++$Y){
			if(($Yndex &amp; (1 &lt;&lt; $Y)) &gt; 0){
				$raw[$Y] = $this-&gt;level-&gt;getMiniChunk($X, $Z, $Y);
			}
		}

		$ordered = "";
		$flag = chr($Yndex);
		for($j = 0; $j &lt; 256; ++$j){
			$ordered .= $flag;
			foreach($raw as $mini){
				$ordered .= substr($mini, $j &lt;&lt; 5, 24); //16 + 8
			}
		}
		if(ADVANCED_CACHE == true and $Yndex == 0xff){
			Cache::add($identifier, $ordered, 60);
		}
		return $ordered;
	}

	public function getOrderedFullChunk($X, $Z){
		if(!isset($this-&gt;level)){
			return false;
		}
		if(ADVANCED_CACHE == true){
			$identifier = "world:{$this-&gt;name}:$X:$Z";
			if(($cache = Cache::get($identifier)) !== false){
				return $cache;
			}
		}

		echo("Sending chunk" . $X . ":" . $Z . "\n");

		$orderedIds = str_repeat("\x2e", 16*16*128);
		$orderedData = str_repeat("\x00", 16*16*64);
		$orderedSkyLight = $orderedData;
		$orderedLight = $orderedData;
		$orderedBiomeIds = str_repeat("\x00", 16*16);
		$orderedBiomeColors = Utils::writeInt(0);
		$tileEntities = "";
		$orderedUncompressed = Utils::writeLInt($X) . Utils::writeLInt($Z) .
			$orderedIds . $orderedData . $orderedSkyLight . $orderedLight .
			$orderedBiomeIds . $orderedBiomeColors . $tileEntities;
		$ordered = zlib_encode($orderedUncompressed, ZLIB_ENCODING_DEFLATE, 1);
		if(ADVANCED_CACHE == true){
			Cache::add($identifier, $ordered, 60);
		}
		return $ordered;
	}


	public function getOrderedMiniChunk($X, $Z, $Y){
		if(!isset($this-&gt;level)){
			return false;
		}
		$raw = $this-&gt;level-&gt;getMiniChunk($X, $Z, $Y);
		$ordered = "";
		$flag = chr(1 &lt;&lt; $Y);
		for($j = 0; $j &lt; 256; ++$j){
			$ordered .= $flag . substr($raw, $j &lt;&lt; 5, 24); //16 + 8
		}
		return $ordered;
	}

	public function getSpawn(){
		if(!isset($this-&gt;level)){
			return false;
		}
		return new Position($this-&gt;level-&gt;getData("spawnX"), $this-&gt;level-&gt;getData("spawnY"), $this-&gt;level-&gt;getData("spawnZ"), $this);
	}

	public function getSafeSpawn($spawn = false){
		if($spawn === false){
			$spawn = $this-&gt;getSpawn();
		}
		if($spawn instanceof Vector3){
			$x = (int) round($spawn-&gt;x);
			$y = (int) round($spawn-&gt;y);
			$z = (int) round($spawn-&gt;z);
			for(; $y &gt; 0; --$y){
				$v = new Vector3($x, $y, $z);
				$b = $this-&gt;getBlock($v-&gt;getSide(0));
				if($b === false){
					return $spawn;
				}elseif(!($b instanceof AirBlock)){
					break;
				}
			}
			for(; $y &lt; 128; ++$y){
				$v = new Vector3($x, $y, $z);
				if($this-&gt;getBlock($v-&gt;getSide(1)) instanceof AirBlock){
					if($this-&gt;getBlock($v) instanceof AirBlock){
						return new Position($x, $y, $z, $this);
					}
				}else{
					++$y;
				}
			}
			return new Position($x, $y, $z, $this);
		}
		return false;
	}

	public function setSpawn(Vector3 $pos){
		if(!isset($this-&gt;level)){
			return false;
		}
		$this-&gt;level-&gt;setData("spawnX", $pos-&gt;x);
		$this-&gt;level-&gt;setData("spawnY", $pos-&gt;y);
		$this-&gt;level-&gt;setData("spawnZ", $pos-&gt;z);
	}

	public function getTime(){
		return (int) ($this-&gt;time);
	}

	public function getName(){
		return $this-&gt;name;//return $this-&gt;level-&gt;getData("name");
	}

	public function setTime($time){
		$this-&gt;startTime = $this-&gt;time = (int) $time;
		$this-&gt;startCheck = microtime(true);
		$this-&gt;checkTime();
	}

	public function stopTime(){
		$this-&gt;stopTime = true;
		$this-&gt;startCheck = 0;
		$this-&gt;checkTime();
	}

	public function startTime(){
		$this-&gt;stopTime = false;
		$this-&gt;startCheck = microtime(true);
		$this-&gt;checkTime();
	}

	public function getSeed(){
		if(!isset($this-&gt;level)){
			return false;
		}
		return (int) $this-&gt;level-&gt;getData("seed");
	}

	public function setSeed($seed){
		if(!isset($this-&gt;level)){
			return false;
		}
		$this-&gt;level-&gt;setData("seed", (int) $seed);
	}

	public function scheduleBlockUpdate(Position $pos, $delay, $type = BLOCK_UPDATE_SCHEDULED){
		if(!isset($this-&gt;level)){
			return false;
		}
		return $this-&gt;server-&gt;api-&gt;block-&gt;scheduleBlockUpdate($pos, $delay, $type);
	}
}
</pre></body></html>