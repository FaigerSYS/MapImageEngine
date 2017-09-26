<?php

/*
 *
 *  ____            _        _   __  __ _                  __  __ ____
 * |  _ \ ___   ___| | _____| |_|  \/  (_)_ __   ___      |  \/  |  _ \
 * | |_) / _ \ / __| |/ / _ \ __| |\/| | | '_ \ / _ \_____| |\/| | |_) |
 * |  __/ (_) | (__|   <  __/ |_| |  | | | | | |  __/_____| |  | |  __/
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

$class_exists = function (string $class) : bool {
	try {
		$exists = class_exists($class);
		return ($exists ?: interface_exists($class));
	} catch (\Throwable $e) {
		return false;
	}
};

if ($class_exists('\pocketmine\network\mcpe\protocol\DataPacket')) {
	$dp_class = '\pocketmine\network\mcpe\protocol\DataPacket';
} else {
	$dp_class = '\pocketmine\network\protocol\DataPacket';
}

if ($class_exists('\pocketmine\network\mcpe\protocol\ProtocolInfo')) {
	$info_class = '\pocketmine\network\mcpe\protocol\ProtocolInfo';
} else {
	$info_class = '\pocketmine\network\protocol\Info';
}

$ns_class = null;
if (method_exists($dp_class, 'handle')) {
	if ($class_exists('\pocketmine\network\mcpe\NetworkSession')) {
		$ns_class = '\pocketmine\network\mcpe\NetworkSession';
	} elseif ($class_exists('\pocketmine\network\NetworkSession')) {
		$ns_class = '\pocketmine\network\NetworkSession';
	}
}

$protocol = $info_class::CURRENT_PROTOCOL;
$id = $info_class::CLIENTBOUND_MAP_ITEM_DATA_PACKET ?? null;
if ($id === null) {
	if ($protocol >= 105) {
		$id = 0x43;
	} elseif ($protocol >= 92) {
		$id = 0x42;
	} elseif ($protocol >= 90) {
		$id = 0x41;
	} else {
		$id = 0x3e;
	}
}

if (method_exists($dp_class, 'putEntityUniqueId')) {
	$f1 = 'putEntityUniqueId';
} elseif (method_exists($dp_class, 'putVarLong')) {
	$f1 = 'putVarLong';
} else {
	$f1 = 'putVarInt';
}

eval('
declare(strict_types=1);

namespace FaigerSYS\MapImageEngine\pocketmine_bc;

use pocketmine\utils\BinaryStream;

class ClientboundMapItemDataPacket extends ' . $dp_class . '{
	
	const NETWORK_ID = ' . $id . ';
	
	const BITFLAG_TEXTURE_UPDATE = 0x02;
	const BITFLAG_DECORATION_UPDATE = 0x04;
	
	public $mapId;
	
	public $scale;
	
	public $width;
	public $height;
	public $xOffset = 0;
	public $yOffset = 0;
	
	public $colors = "";
	
	public function decode(){
		// Do not need this one
	}
	
	public function ' . (method_exists($dp_class, 'encodePayload') ? 'encodePayload(){' : 'encode(){ $this->reset();') . '
		
		$this->' . $f1 . '($this->mapId);
		
		$type = self::BITFLAG_TEXTURE_UPDATE;
		$this->putUnsignedVarInt($type);
		
		' . ($protocol >= 135 ? '$this->putByte(0);' : '') . '
		
		$this->putByte($this->scale);
		
		$this->putVarInt($this->width);
		$this->putVarInt($this->height);
		$this->putVarInt($this->xOffset);
		$this->putVarInt($this->yOffset);
		
		' . ($protocol >= 135 ? '$this->putUnsignedVarInt($this->width * $this->height);' : '') . '
		$this->put($this->colors);
		
		$this->isEncoded = true;
	}
	
	public static function prepareColors(array $colors, int $width, int $height) {
		$buffer = new BinaryStream;
		
		for($y = 0; $y < $height; ++$y){
			for($x = 0; $x < $width; ++$x){
				$buffer->putUnsignedVarInt($colors[$y][$x]);
			}
		}
		
		return $buffer->buffer;
	}
	
	' . ($ns_class ? 'public function handle(' . $ns_class . ' $session) : bool { return true; }' : '') . '
}

');
