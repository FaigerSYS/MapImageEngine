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

$ns_class = null;

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

if (method_exists($dp_class, 'handle')) {
	if ($class_exists('\pocketmine\network\mcpe\NetworkSession')) {
		$ns_class = '\pocketmine\network\mcpe\NetworkSession';
	} elseif ($class_exists('\pocketmine\network\NetworkSession')) {
		$ns_class = '\pocketmine\network\NetworkSession'; // I have not seen this yet, but everything can be :P
	}
}

$pk_id = $info_class::CLIENTBOUND_MAP_ITEM_DATA_PACKET ?? null;
if ($pk_id === null) {
	$protocol = $info_class::CURRENT_PROTOCOL;
	
	if ($protocol >= 105) {
		$pk_id = 0x43;
	} elseif ($protocol >= 92) {
		$pk_id = 0x42;
	} elseif ($protocol >= 90) {
		$pk_id = 0x41;
	} else {
		$pk_id = 0x3e;
	}
}

if (method_exists($dp_class, 'putVarLong')) {
	$f1 = 'putVarLong';
} else {
	$f1 = 'putVarInt';
}

eval('
declare(strict_types=1);

namespace pocketmine_backtrace;

use pocketmine\utils\BinaryStream;

class ClientboundMapItemDataPacket extends ' . $dp_class . '{
	const NETWORK_ID = ' . $pk_id . ';

	const BITFLAG_TEXTURE_UPDATE = 0x02;    // Image
	const BITFLAG_DECORATION_UPDATE = 0x04; // Arrows...?

	public $mapId;
	public $type;

	public $eids = [];
	public $scale;
	public $decorations = [];

	public $width;
	public $height;
	public $xOffset = 0;
	public $yOffset = 0;
	
	public $colors = "";

	public function decode(){
		// Do not need this one
	}

	public function encode(){
		$this->reset();
		$this->' . $f1 . '($this->mapId); // putEntityUniqueId

		$type = 0;
		if(($eidsCount = count($this->eids)) > 0){
			$type |= 0x08;
		}
		if(($decorationCount = count($this->decorations)) > 0){
			$type |= self::BITFLAG_DECORATION_UPDATE;
		}
		if(count($this->colors) > 0){
			$type |= self::BITFLAG_TEXTURE_UPDATE;
		}

		$this->putUnsignedVarInt($type);

		if(($type & 0x08) !== 0){ //TODO: find out what these are for
			$this->putUnsignedVarInt($eidsCount);
			foreach($this->eids as $eid){
				$this->' . $f1 . '($eid); // putEntityUniqueId
			}
		}

		if(($type & (self::BITFLAG_TEXTURE_UPDATE | self::BITFLAG_DECORATION_UPDATE)) !== 0){
			$this->putByte($this->scale);
		}

		if(($type & self::BITFLAG_DECORATION_UPDATE) !== 0){
			$this->putUnsignedVarInt($decorationCount);
			foreach($this->decorations as $decoration){
				$this->putVarInt(($decoration["rot"] & 0x0f) | ($decoration["img"] << 4));
				$this->putByte($decoration["xOffset"]);
				$this->putByte($decoration["yOffset"]);
				$this->putString($decoration["label"]);
				$this->putLInt($decoration["color"]);
			}
		}

		if(($type & self::BITFLAG_TEXTURE_UPDATE) !== 0){
			$this->putVarInt($this->width);
			$this->putVarInt($this->height);
			$this->putVarInt($this->xOffset);
			$this->putVarInt($this->yOffset);
			$this->put($this->colors);
		}
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
