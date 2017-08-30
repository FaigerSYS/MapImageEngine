<?php
namespace FaigerSYS\MapImageEngine\item;

use pocketmine\item\Item;

use pocketmine\nbt\tag\CompoundTag;
use pocketmine\nbt\tag\StringTag;

use FaigerSYS\MapImageEngine\MapImageEngine;

class FilledMap extends Item {
	
	const CURRENT_MAP_API = 1;
	const SUPPORTED_MAP_API = [1];
	
	/** @var bool */
	private $mie_changing = false;
	
	public function __construct() {
		parent::__construct(Item::FILLED_MAP ?? 358, 0, 1, 'Map');
	}
	
	public function setCompoundTag($tags) {
		parent::setCompoundTag($tags);
		if (!$this->mie_changing) {
			$this->processTagChanged();
		}
		return $this;
	}
	
	public function setNamedTag(CompoundTag $tags) {
		parent::setNamedTag($tags);
		if (!$this->mie_changing) {
			$this->processTagChanged();
		}
		return $this;
	}
	
	public function processTagChanged(array $mie_data = null) {
		$owner = MapImageEngine::getInstance();
		if (!$owner) {
			return;
		}

		if (!$mie_data) {
			$tags = $this->getNamedTag();
			if (!isset($tags->mie_data)) {
				return;
			}
			
			$mie_data = json_decode((string) $tags->mie_data, true);
		}

		if (!in_array($mie_data['api'] ?? -1, self::SUPPORTED_MAP_API)) {
			$map_id = 0;
		} else {
			$map_id = (string) ($owner->getImageStorage()->getMapId($mie_data['image_hash'], $mie_data['x_block'], $mie_data['y_block']) ?: 0);
		}
		
		$tag = new CompoundTag('', [
			new StringTag('map_uuid', $map_id),
			new StringTag('mie_data', json_encode($mie_data))
		]);
		$this->mie_changing = true;
		$this->setCompoundTag($tag);
		$this->mie_changing = false;
	}
	
	public function setImageData(string $image_hash, int $x_block, int $y_block) {
		$this->processTagChanged([
			'api'        => self::CURRENT_MAP_API,
			'image_hash' => $image_hash,
			'x_block'    => $x_block,
			'y_block'    => $y_block
		]);
	}
	
	public function getMaxStackSize() : int {
		return 1;
	}
	
}
