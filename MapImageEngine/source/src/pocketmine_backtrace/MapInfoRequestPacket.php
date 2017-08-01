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
		$ns_class = '\pocketmine\network\NetworkSession'; // I have not seen this yet, but everything can be in future :P
	}
}

$pk_id = $info_class::MAP_INFO_REQUEST_PACKET ?? null;
if ($pk_id === null) {
	$protocol = $info_class::CURRENT_PROTOCOL;
	
	if ($protocol >= 105) {
		$pk_id = 0x44;
	} elseif ($protocol >= 92) {
		$pk_id = 0x43;
	} elseif ($protocol >= 90) {
		$pk_id = 0x42;
	} else {
		$pk_id = 0x3c;
	}
}

if (method_exists($dp_class, 'getVarLong')) {
	$f1 = 'getVarLong';
} else {
	$f1 = 'getVarInt';
}

eval('
declare(strict_types=1);

namespace pocketmine_backtrace;

class MapInfoRequestPacket extends ' . $dp_class . '{
	const NETWORK_ID = ' . $pk_id . ';

	public $mapId;

	public function decode(){
		$this->mapId = $this->' . $f1 . '(); // putEntityUniqueId
	}

	public function encode(){
		
	}

	' . ($ns_class ? 'public function handle(' . $ns_class . ' $session) : bool { return true; }' : '') . '
}

');
