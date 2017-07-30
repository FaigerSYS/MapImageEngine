<?php
namespace FaigerSYS\MapImageEngine\storage;

use pocketmine\Player;

use pocketmine\entity\Entity;

use pocketmine\utils\Binary;
use pocketmine\network\mcpe\protocol\BatchPacket as BatchPacket_1;
use pocketmine\network\protocol\BatchPacket as BatchPacket_2;

use pocketmine_backtrace\ClientboundMapItemDataPacket;

use FaigerSYS\MapImageEngine\MapImageEngine;

class ImageStorage {
	
	const CURRENT_API = 1;
	const SUPPORTED_API = [1];
	
	const CURRENT_CACHE_API = 2;
	const SUPPORTED_CACHE_API = [2];
	
	const STATE_OK = 0;
	const STATE_CORRUPTED = 1;
	const STATE_UNSUPPORTED_API = 2;
	const STATE_IMAGE_EXISTS = 3;
	const STATE_NAME_EXISTS = 4;
	const STATE_NOT_EXISTS = 5;
	const STATE_FILE_ERROR = 6;
	
	/** @var BatchPacket_1|BatchPacket_2 */
	private $batch_class;
	
	/** @var string[] */
	private $images = [];
	
	/** @var array */
	private $images_data = [];
	
	/** @var BatchPacket_1[]|BatchPacket_2[] */
	private $packets = [];
	
	public function __construct() {
		try {
			$this->batch_class = new BatchPacket_1;
		} catch (\Throwable $e) {
			$this->batch_class = new BatchPacket_2;
		} 
	}
	
	public function addImage(string $image, string $name, bool $is_path = false) : int {
		$name = strtr(trim($name), ' ', '_');
		if (empty($name) || isset($this->images[$name])) {
			return self::STATE_NAME_EXISTS;
		}
		
		if ($is_path) {
			$image = @file_get_contents($image);
			if ($image === false) {
				return self::STATE_FILE_ERROR;
			}
		}
		
		$hash = hash('md5', $image);
		if (isset($this->images_data[$hash])) {
			return self::STATE_IMAGE_EXISTS;
		}
		
		$image = json_decode(gzinflate($image), true);
		if (!$image) {
			return self::STATE_CORRUPTED;
		}
		
		if (!in_array($image['api'] ?? -1, self::SUPPORTED_API)) {
			return self::STATE_UNSUPPORTED_API;
		}
		
		$x_blocks = count($image['blocks'][0]);
		$y_blocks = count($image['blocks']);
		
		$encoder = new ClientboundMapItemDataPacket;
		$encoder->type = ClientboundMapItemDataPacket::BITFLAG_TEXTURE_UPDATE;
		$encoder->scale = 0;
		$encoder->width = 128;
		$encoder->height = 128;
		
		$image_data = [
			'x_blocks' => $x_blocks,
			'y_blocks' => $y_blocks,
			'blocks' => []
		];
		$packets = [];
		
		$cache_folder = MapImageEngine::getInstance()->getDataFolder() .  'cache/';
		
		$m1 = microtime(true);
		for ($y = 0; $y < $y_blocks; $y++) {
			for ($x = 0; $x < $x_blocks; $x++) {
				$m2 = microtime(true);
				
				$cache_hash = hash('md5', $image['blocks'][$y][$x]);
				$cache_path = $cache_folder . $cache_hash;
				$cache_data = json_decode(@file_get_contents($cache_path), true);
				
				if (in_array($cache_data['api'] ?? -1, self::SUPPORTED_CACHE_API)) {
					$colors = base64_decode($cache_data['colors']);
				} else {
					$colors = json_decode(gzinflate(base64_decode($image['blocks'][$y][$x])), true);
					if (!$colors) {
						return self::STATE_CORRUPTED;
					}
					
					$colors = ClientboundMapItemDataPacket::prepareColors($colors, 128, 128);
					
					$cache_data = [
						'api' => self::CURRENT_CACHE_API,
						'colors' => base64_encode($colors)
					];
					$cache_data = json_encode($cache_data);
					file_put_contents($cache_path, $cache_data);
				}
				
				$map_id = Entity::$entityCount++;
				
				$encoder->mapId = $map_id;
				$encoder->colors = $colors;
				$encoder->encode();
				
				$batch = clone $this->batch_class;
				
				if (method_exists($batch, 'addPacket')) {
					$batch->addPacket($encoder);
				} else {
					$batch->payload = Binary::writeUnsignedVarInt(strlen($encoder->buffer)) . $encoder->buffer;
				}
				if (method_exists($batch, 'compress')) {
					$batch->compress(9);
				} elseif (method_exists($batch, 'setCompressionLevel')) {
					$batch->setCompressionLevel(9);
				} else {
					$batch->payload = zlib_encode($batch->payload, ZLIB_ENCODING_DEFLATE, 9);
				}
				
				$image_data['blocks'][$y][$x] = $map_id;
				$packets[$map_id] = $batch;
			}
		}
		
		$this->images[$name] = $hash;
		$this->images_data[$hash] = $image_data;
		$this->packets += $packets;
		
		return self::STATE_OK;
	}
	
	public function getImages() {
		return $this->images;
	}
	
	public function getImageHashByName(string $name) {
		$name = strtr(trim($name), ' ', '_');
		return $this->images[$name] ?? null;
	}
	
	public function getBlocksCountByX(string $image_hash) {
		return $this->images_data[$image_hash]['x_blocks'] ?? null;
	}
	
	public function getBlocksCountByY(string $image_hash) {
		return $this->images_data[$image_hash]['y_blocks'] ?? null;
	}
	
	public function getMapId(string $image_hash, int $x_block, int $y_block) {
		return $this->images_data[$image_hash]['blocks'][$y_block][$x_block] ?? null;
	}
	
	public function sendImage(int $map_id, Player ...$players) {
		$batch = $this->packets[$map_id] ?? null;
		if (!$batch) {
			return self::STATE_NOT_EXISTS;
		}
		
		foreach ($players as $player) {
			$player->dataPacket($batch);
		}
		
		return self::STATE_OK;
	}
	
}
