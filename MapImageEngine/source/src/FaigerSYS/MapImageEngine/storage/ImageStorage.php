<?php
namespace FaigerSYS\MapImageEngine\storage;

use pocketmine\Player;

class ImageStorage {
	
	const R_OK = 0;
	const R_ALREADY_REGISTERED = 1;
	const R_UUID_EXISTS = 2;
	const R_INVALID_NAME = 3;
	const R_NAME_EXISTS = 4;
	
	/** @var MapImage[] */
	private $images = [];
	
	/** @var string[] */
	private $hashes = [];
	
	/** @var string[] */
	private $names = [];
	
	/** @var \pocketmine\network\mcpe\protocol\BatchPacket[]|\pocketmine\network\protocol\BatchPacket[] */
	private $packet_cache = [];
	
	/**
	 * Registers/adds image to storage
	 *
	 * @param MapImage $image
	 * @param bool     $cache_packets
	 * @param string   $name
	 *
	 * @return int
	 */
	public function registerImage(MapImage $image, bool $cache_packets = true, string $name = null) : int {
		$spl_hash = spl_object_hash($image);
		if (isset($this->images[$spl_hash])) {
			return self::R_ALREADY_REGISTERED;
		}
		
		$hash = $image->getHashedUUID();
		if (isset($this->hashes[$hash])) {
			return self::R_UUID_EXISTS;
		}
		
		if ($name !== null) {
			$name = strtr($name, ' ', '_');
			if (!strlen($name)) {
				return self::R_INVALID_NAME;
			}
			if (isset($this->names[$name])) {
				return self::R_NAME_EXISTS;
			}
			$this->names[$name] = $hash;
		}
		
		$this->images[$spl_hash] = $image;
		$this->hashes[$hash] = $spl_hash;
		
		if ($cache_packets) {
			$this->regeneratePacketsCache($image);
		}
		
		return self::R_OK;
	}
	
	/**
	 * Unregisters/removes image from storage
	 *
	 * @param MapImage $image
	 */
	public function unregisterImage(MapImage $image) {
		$spl_hash = spl_object_hash($image);
		if (!isset($this->images[$spl_hash])) {
			return;
		}
		$hash = $image->getHashedUUID();
		
		$this->removePacketsCache($image);
		
		foreach ($this->names as $name => $o_spl_hash) {
			if ($spl_hash === $o_spl_hash) {
				unset($this->names[$name]);
			}
		}
		foreach ($this->names as $name => $o_spl_hash) {
			if ($spl_hash === $o_spl_hash) {
				unset($this->names[$name]);
			}
		}
		unset($this->hashes[$hash]);
		unset($this->images[$spl_hash]);
	}
	
	/**
	 * Regenerates map image packets cache
	 *
	 * @param MapImage $image
	 * @param int      $chunk_x
	 * @param int      $chunk_y
	 */
	public function regeneratePacketsCache(MapImage $image = null, int $chunk_x = null, int $chunk_y = null) {
		if ($image === null) {
			$this->cache = [];
			foreach ($this->images as $image) {
				foreach ($image->getChunks() as $chunks) {
					foreach ($chunks as $chunk) {
						$this->packet_cache[$chunk->getMapId()] = $chunk->generateBatchedMapImagePacket(7);
					}
				}
			}
		} else {
			if (!isset($this->images[spl_object_hash($image)])) {
				return;
			}
			
			if ($chunk_x === null && $chunk_y === null) {
				foreach ($image->getChunks() as $chunks) {
					foreach ($chunks as $chunk) {
						$this->packet_cache[$chunk->getMapId()] = $chunk->generateBatchedMapImagePacket(7);
					}
				}
			} else {
				$chunk = $image->getChunk($chunk_x, $chunk_y);
				if ($chunk !== null) {
					$this->packet_cache[$chunk->getMapId()] = $chunk->generateBatchedMapImagePacket(7);
				}
			}
		}
	}
	
	/**
	 * Removes map image packets from cache
	 *
	 * @param MapImage $image
	 * @param int      $chunk_x
	 * @param int      $chunk_y
	 */
	public function removePacketsCache(MapImage $image, int $chunk_x = null, int $chunk_y = null) {
		if (!isset($this->images[spl_object_hash($image)])) {
			return;
		}
		
		if ($chunk_x === null && $chunk_y === null) {
			foreach ($image->getChunks() as $chunks) {
				foreach ($chunks as $chunk) {
					unset($this->packet_cache[$chunk->getMapId()]);
				}
			}
		} else {
			$chunk = $image->getChunk($chunk_x, $chunk_y);
			if ($chunk !== null) {
				unset($this->packet_cache[$chunk->getMapId()]);
			}
		}
	}
	
	/**
	 * Removes map image packet with specified map ID
	 *
	 * @param int $map_id
	 */
	public function removePacketCache(int $map_id) {
		unset($this->packet_cache[$map_id]);
	}
	
	/**
	 * Returns map image with specified UUID hash
	 *
	 * @param string $uuid_hash
	 *
	 * @return MapImage|null
	 */
	public function getImage(string $uuid_hash) {
		return $this->images[$this->hashes[$uuid_hash] ?? null] ?? null;
	}
	
	/**
	 * Returns map image with specified name
	 *
	 * @param string $name
	 *
	 * @return MapImage|null
	 */
	public function getImageByName(string $name) {
		return $this->getImage($this->names[strtr($name, ' ', '_')] ?? '');
	}
	
	/**
	 * Returns all of map images
	 *
	 * @return MapImage[]
	 */
	public function getImages() : array {
		return $this->images;
	}
	
	/**
	 * Returns all of map images that have name
	 *
	 * @return MapImage[]
	 */
	public function getNamedImages() : array {
		return array_map(
			function ($hash) {
				return $this->getImage($hash);
			},
			$this->names
		);
	}
	
	/**
	 * Returns cached batched map image packet
	 *
	 * @param int $map_id
	 * 
	 * @return \pocketmine\network\mcpe\protocol\BatchPacket|\pocketmine\network\protocol\BatchPacket
	 */
	public function getPacket(int $map_id) {
		if (isset($this->packet_cache[$map_id])) {
			return clone $this->packet_cache[$map_id];
		}
	}
	
	/**
	 * Sends cached batched map image packet to players
	 *
	 * @param int      $map_id
	 * @param Player[] ...$players
	 */
	public function sendImage(int $map_id, Player ...$players) {
		$packet = $this->getPacket($map_id);
		if (!$packet) {
			return;
		}
		
		foreach ($players as $player) {
			$player->dataPacket($packet);
		}
	}
	
}
