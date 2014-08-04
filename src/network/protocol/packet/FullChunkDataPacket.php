
<!-- saved from url=(0148)https://raw.githubusercontent.com/zhuowei/PocketMine-MP/ab7d4462c92d9f95e281c7670a818b91935f0924/src/network/protocol/packet/FullChunkDataPacket.php -->
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

class FullChunkDataPacket extends RakNetDataPacket{
	public $chunkX;
	public $chunkZ;
	public $data;
	
	public function pid(){
		return ProtocolInfo::FULL_CHUNK_DATA_PACKET;
	}
	
	public function decode(){

	}
	
	public function encode(){
		$this-&gt;reset();
		$this-&gt;put($this-&gt;data);
	}

}
</pre></body></html>