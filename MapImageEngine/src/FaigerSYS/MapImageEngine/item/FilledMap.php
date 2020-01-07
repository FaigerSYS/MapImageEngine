<?php
namespace FaigerSYS\MapImageEngine\item;

use pocketmine\item\Item;

use pocketmine\nbt\tag\CompoundTag;
use pocketmine\nbt\tag\StringTag;

use FaigerSYS\MapImageEngine\MapImageEngine;

class FilledMap extends Item {
	
	const CURRENT_MAP_API = 3;
	const SUPPORTED_MAP_API = [3];
	
	public function __construct() {
		parent::__construct(self::FILLED_MAP, 0, 1, 'Map');
	}
	
	public function setCompoundTag($tag) : Item {
		parent::setCompoundTag($tag);
		$this->updateMapData();
		
		return $this;
	}
	
	public function setNamedTag(CompoundTag $tag) : Item {
		parent::setNamedTag($tag);
		$this->updateMapData();
		
		return $this;
	}
	
	protected function updateMapData() {
		$plugin = MapImageEngine::getInstance();
		if (!$plugin) {
			return;
		}
		
		$mie_data = $this->getImageData();
		if (!is_array($mie_data)) {
			return;
		}
		
		$map_id = 0;
		
		$api = $mie_data['api'] ?? -1;;
		if (in_array($api, self::SUPPORTED_MAP_API)) {
			$image = $plugin->getImageStorage()->getImage($mie_data['image_hash']);
			if ($image) {
				$chunk = $image->getChunk($mie_data['x_block'], $mie_data['y_block']);
				if ($chunk) {
					$map_id = $chunk->getMapId();
				}
			}
		}
		
		$tag = $this->getNamedTag();
		$tag->setLong('map_uuid', $map_id, true);
		parent::setNamedTag($tag);
	}
	
	public function setImageData(string $image_hash, int $block_x, int $block_y) {
		$tag = $this->getNamedTag();
		$tag->setString('mie_data', json_encode([
			'api'        => self::CURRENT_MAP_API,
			'image_hash' => $image_hash,
			'x_block'    => $block_x,
			'y_block'    => $block_y
		]));
		parent::setNamedTag($tag);
		
		$this->updateMapData();
	}
	
	public function getImageData() {
		$tag = $this->getNamedTag();
		if ($tag->hasTag('mie_data', StringTag::class)) {
			return json_decode($tag->getString('mie_data'), true);
		}
	}
	
	public function getImageHash() {
		return $this->getImageData()['image_hash'] ?? null;
	}
	
	public function getImageChunkX() {
		return $this->getImageData()['x_block'] ?? null;
	}
	
	public function getImageChunkY() {
		return $this->getImageData()['y_block'] ?? null;
	}
	
}
