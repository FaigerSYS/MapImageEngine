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

// Custom packet is needed for cache support

declare(strict_types=1);


namespace FaigerSYS\MapImageEngine\packet;

use pocketmine\network\mcpe\NetworkSession;
use pocketmine\network\mcpe\protocol\types\DimensionIds;

use pocketmine\utils\Color;
use pocketmine\utils\Binary;
use pocketmine\utils\BinaryStream;

use pocketmine\network\mcpe\protocol\DataPacket;
use pocketmine\network\mcpe\protocol\ProtocolInfo;
use pocketmine\network\mcpe\protocol\ClientboundMapItemDataPacket;

class CustomClientboundMapItemDataPacket extends DataPacket {
	
	public const NETWORK_ID = ProtocolInfo::CLIENTBOUND_MAP_ITEM_DATA_PACKET;
	
	public const BITFLAG_TEXTURE_UPDATE = 0x02;
	public const BITFLAG_DECORATION_UPDATE = 0x04;
	
	/** @var int */
	public $mapId;
	
	/** @var int */
	public $type;
	
	/** @var int */
	public $dimensionId = DimensionIds::OVERWORLD;
	
	/** @var int[] */
	public $eids = [];
	
	/** @var int */
	public $scale;
	
	/** @var int[] */
	public $decorationEntityUniqueIds = [];
	
	/** @var array */
	public $decorations = [];

	/** @var int */
	public $width;
	
	/** @var int */
	public $height;
	
	/** @var int */
	public $xOffset = 0;
	
	/** @var int */
	public $yOffset = 0;
	
	/** @var string */
	public $colors;
	
	protected function decodePayload() : void {
		$this->mapId = $this->getEntityUniqueId();
		$this->type = $this->getUnsignedVarInt();
		$this->dimensionId = $this->getByte();
		
		if (($this->type & 0x08) !== 0) {
			$count = $this->getUnsignedVarInt();
			for ($i = 0; $i < $count; ++$i) {
				$this->eids[] = $this->getEntityUniqueId();
			}
		}
		
		if (($this->type & (0x08 | self::BITFLAG_DECORATION_UPDATE | self::BITFLAG_TEXTURE_UPDATE)) !== 0) {
			$this->scale = $this->getByte();
		}
		
		if (($this->type & self::BITFLAG_DECORATION_UPDATE) !== 0) {
			for ($i = 0, $count = $this->getUnsignedVarInt(); $i < $count; ++$i) {
				$this->decorationEntityUniqueIds[] = $this->getEntityUniqueId();
			}
			
			for ($i = 0, $count = $this->getUnsignedVarInt(); $i < $count; ++$i) {
				$this->decorations[$i]["rot"] = $this->getByte();
				$this->decorations[$i]["img"] = $this->getByte();
				$this->decorations[$i]["xOffset"] = $this->getByte();
				$this->decorations[$i]["yOffset"] = $this->getByte();
				$this->decorations[$i]["label"] = $this->getString();

				$this->decorations[$i]["color"] = $this->getUnsignedVarInt();
			}
		}
		
		if (($this->type & self::BITFLAG_TEXTURE_UPDATE) !== 0) {
			$this->width = $this->getVarInt();
			$this->height = $this->getVarInt();
			$this->xOffset = $this->getVarInt();
			$this->yOffset = $this->getVarInt();
			
			$count = $this->getUnsignedVarInt();
			assert($count === $this->width * $this->height);
			
			$this->colors = $this->get($count);
		}
	}
	
	protected function encodePayload() : void {
		$this->putEntityUniqueId($this->mapId);
		
		$type = 0;
		if (($eidsCount = count($this->eids)) > 0) {
			$type |= 0x08;
		}
		if (($decorationCount = count($this->decorations)) > 0) {
			$type |= self::BITFLAG_DECORATION_UPDATE;
		}
		if (!empty($this->colors)) {
			$type |= self::BITFLAG_TEXTURE_UPDATE;
		}
		
		$this->putUnsignedVarInt($type);
		$this->putByte($this->dimensionId);
		
		if (($type & 0x08) !== 0) {
			$this->putUnsignedVarInt($eidsCount);
			foreach ($this->eids as $eid) {
				$this->putEntityUniqueId($eid);
			}
		}
		
		if (($type & (0x08 | self::BITFLAG_TEXTURE_UPDATE | self::BITFLAG_DECORATION_UPDATE)) !== 0) {
			$this->putByte($this->scale);
		}
		
		if (($type & self::BITFLAG_DECORATION_UPDATE) !== 0) {
			$this->putUnsignedVarInt(count($this->decorationEntityUniqueIds));
			foreach ($this->decorationEntityUniqueIds as $id) {
				$this->putEntityUniqueId($id);
			}
			
			$this->putUnsignedVarInt($decorationCount);
			foreach ($this->decorations as $decoration) {
				$this->putByte($decoration["rot"]);
				$this->putByte($decoration["img"]);
				$this->putByte($decoration["xOffset"]);
				$this->putByte($decoration["yOffset"]);
				$this->putString($decoration["label"]);
				
				$this->putUnsignedVarInt($decoration["color"]);
			}
		}
		
		if (($type & self::BITFLAG_TEXTURE_UPDATE) !== 0) {
			$this->putVarInt($this->width);
			$this->putVarInt($this->height);
			$this->putVarInt($this->xOffset);
			$this->putVarInt($this->yOffset);
			
			$this->putUnsignedVarInt($this->width * $this->height);
			
			$this->put($this->colors);
		}
	}
	
	public function handle(NetworkSession $session) : bool {
		return true;
	}
	
	public static function prepareColors(array $colors, int $width, int $height) {
		$buffer = new BinaryStream;
		for ($y = 0; $y < $height; $y++) {
			for ($x = 0; $x < $width; $x++) {
				$buffer->putUnsignedVarInt($colors[$y][$x]);
			}
		}
		return $buffer->buffer;
	}
	
	public static function checkCompatiblity() : bool {
		$original = new ClientboundMapItemDataPacket();
		$custom = new CustomClientboundMapItemDataPacket();
		
		$original->mapId = $custom->mapId = 1;
		$original->dimensionId = $custom->dimensionId = DimensionIds::OVERWORLD;
		$original->eids = $custom->eids = [];
		$original->scale = $custom->scale = 0;
		$original->decorationEntityUniqueIds = $custom->decorationEntityUniqueIds = [];
		$original->decorations = $custom->decorations = [];
		$original->width = $custom->width = 128;
		$original->height = $custom->height = 128;
		$original->xOffset = $custom->xOffset = 0;
		$original->yOffset = $custom->yOffset = 0;
		
		$color = new Color(0xff, 0xee, 0xdd);
		$original->colors = array_fill(0, 128, array_fill(0, 128, $color));
		$custom->colors = str_repeat(Binary::writeUnsignedVarInt($color->toABGR()), 128 * 128);
		
		$original->encode();
		$custom->encode();
		return $original->buffer === $custom->buffer;
	}
	
}
