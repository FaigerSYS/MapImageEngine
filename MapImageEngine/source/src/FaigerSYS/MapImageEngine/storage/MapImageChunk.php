<?php
namespace FaigerSYS\MapImageEngine\storage;

use pocketmine\utils\Binary;
use pocketmine\utils\BinaryStream;

use pocketmine\entity\Entity;

use FaigerSYS\MapImageEngine\MapImageEngine;
use FaigerSYS\MapImageEngine\pocketmine_bc\ClientboundMapItemDataPacket;

use FaigerSYS\Toolkit\PacketCompressor;

class MapImageChunk {
	
	/**
	 * Current cache API version
	 */
	const CACHE_API = 3;
	
	/** @var int */
	private $width;
	private $height;
	
	/** @var int */
	private $map_id;
	
	/** @var BinaryStream */
	public $data;
	
	/**
	 * @param int    $width
	 * @param int    $height
	 * @param string $data
	 */
	public function __construct(int $width, int $height, string $data) {
		if ($width < 0 || $height < 0) {
			throw new \InvalidArgumentException('Width/height must be greater than 0');
		}
		if ($width * $height * 4 !== strlen($data)) {
			throw new \InvalidArgumentException('Given data does not match with given width and height');
		}
		
		$this->width = $width;
		$this->height = $height;
		$this->map_id = Entity::$entityCount++;
		$this->data = new BinaryStream($data);
	}
	
	/**
	 * Returns map image chunk map ID
	 *
	 * @return int
	 */
	public function getMapId() : int {
		return $this->map_id;
	}
	
	/**
	 * Returns map image chunk width
	 *
	 * @return int
	 */
	public function getWidth() : int {
		return $this->width;
	}
	
	/**
	 * Returns map image chunk height
	 *
	 * @return int
	 */
	public function getHeight() : int {
		return $this->height;
	}
	
	/**
	 * Returns RGBA color at specified position
	 *
	 * @return int
	 */
	public function getRGBA(int $x, int $y) : int {
		$this->data->offset = $this->getStartOffset($x, $y);
		return $this->data->getInt();
	}
	
	/**
	 * Sets RBGA color at specified position
	 *
	 * @param int $x
	 * @param int $y
	 * @param int $color
	 */
	public function setRGBA(int $x, int $y, int $color) {
		$pos = $this->getStartOffset($x, $y);
		$this->data->buffer{$pos++} = chr($color       & 0xff);
		$this->data->buffer{$pos++} = chr($color >> 8  & 0xff);
		$this->data->buffer{$pos++} = chr($color >> 16 & 0xff);
		$this->data->buffer{$pos++} = chr($color >> 24 & 0xff);
	}
	
	/**
	 * Returns ABGR color at selected position
	 *
	 * @return int
	 */
	public function getABGR(int $x, int $y) : int {
		$this->data->offset = $this->getStartOffset($x, $y);
		return $this->data->getLInt() & 0xffffffff;
	}
	
	/**
	 * Sets ABGR color at selected position
	 *
	 * @param int $x
	 * @param int $y
	 * @param int $color
	 */
	public function setABGR(int $x, int $y, int $color) {
		$pos = $this->getStartOffset($x, $y);
		$this->data->buffer{$pos++} = chr($color >> 24 & 0xff);
		$this->data->buffer{$pos++} = chr($color >> 16 & 0xff);
		$this->data->buffer{$pos++} = chr($color >> 8  & 0xff);
		$this->data->buffer{$pos++} = chr($color       & 0xff);
	}
	
	/**
	 * Returns RGBA colors array
	 *
	 * @return array
	 */
	public function toArrayRGBA() : array {
		$colors = [];
		$this->data->offset = 0;
		for ($y = 0; $y < $this->height; $y++) {
			for ($x = 0; $x < $this->width; $x++) {
				$colors[$y][$x] = $this->data->getInt();
			}
		}
		
		return $colors;
	}
	
	/**
	 * Returns pretty RGBA colors array
	 *
	 * @return array
	 */
	public function toArrayPrettyRGBA() : array {
		$colors = [];
		$this->data->offset = 0;
		for ($y = 0; $y < $this->height; $y++) {
			for ($x = 0; $x < $this->width; $x++) {
				$colors[$y][$x] = [
					'r' => $this->data->getByte(),
					'g' => $this->data->getByte(),
					'b' => $this->data->getByte(),
					'a' => $this->data->getByte()
				];
			}
		}
		
		return $colors;
	}
	
	/**
	 * Returns ABGR colors array
	 *
	 * @return array
	 */
	public function toArrayABGR() : array {
		$colors = [];
		$this->data->offset = 0;
		for ($y = 0; $y < $this->height; $y++) {
			for ($x = 0; $x < $this->width; $x++) {
				$colors[$y][$x] = $this->data->getLInt() & 0xffffffff;
			}
		}
		
		return $colors;
	}
	
	/**
	 * Returns RGBA colors binary
	 *
	 * @return string
	 */
	public function toBinaryRGBA() : string {
		return $this->data->buffer;
	}
	
	/**
	 * Generates map image packet
	 *
	 * @param int  $compression_level
	 * @param int  $map_id
	 * @param bool $use_cache
	 *
	 * @return ClientboundMapItemDataPacket
	 */
	public function generateMapImagePacket(int $map_id = null, bool $use_cache = true) : ClientboundMapItemDataPacket {
		$pk = new ClientboundMapItemDataPacket;
		$pk->mapId = $map_id ?? $this->map_id;
		$pk->scale = 0;
		$pk->width = $this->width;
		$pk->height = $this->height;
		
		$colors = null;
		$generate_cache = false;
		
		if ($use_cache) {
			$cache_hash = hash('md5', $this->width . '.' . $this->height . '.' . hash('md5', $this->data->buffer));
			$cache_path = MapImageEngine::getInstance()->getDataFolder() . 'cache/' . $cache_hash;
			$generate_cache = true;
			if (file_exists($cache_path) && is_file($cache_path)) {
				$cache_buffer = new BinaryStream(file_get_contents($cache_path));
				
				$cache_api = $cache_buffer->getInt();
				if ($cache_api === self::CACHE_API) {
					$colors = $cache_buffer->get(true);
					if (strlen($colors) === $this->width * $this->height * 4) {
						$generate_cache = false;
					}
				}
			}
		}
		
		if (!$colors) {
			$colors = ClientboundMapItemDataPacket::prepareColors($this->toArrayABGR(), $this->width, $this->height);
		}
		
		if ($generate_cache) {
			$cache_buffer = new BinaryStream;
			$cache_buffer->putInt(self::CACHE_API);
			$cache_buffer->put($colors);
			
			file_put_contents($cache_path, $cache_buffer->buffer);
		}
		
		$pk->colors = $colors;
		
		return $pk;
	}
	
	/**
	 * Generates batched map image packet
	 *
	 * @param int  $compression_level
	 * @param int  $map_id
	 * @param bool $use_cache
	 *
	 * @return \pocketmine\network\mcpe\protocol\BatchPacket|\pocketmine\network\protocol\BatchPacket
	 */
	public function generateBatchedMapImagePacket(int $compression_level = 6, int $map_id = null, bool $use_cache = true) {
		return PacketCompressor::compressInstance($compression_level, $this->generateMapImagePacket($map_id, $use_cache));
	}
	
	/**
	 * Creates a new map image chunk from the RGBA color array
	 *
	 * @param int   $width
	 * @param int   $height
	 * @param array $colors
	 *
	 * @return MapImageChunk
	 */
	public static function fromArrayRGBA(int $width, int $height, array $colors) {
		if ($width < 0 || $height < 0) {
			throw new \InvalidArgumentException('Width/height must be greater than 0');
		}
		
		$data = new BinaryStream;
		
		for ($y = 0; $y < $height; $y++) {
			for ($x = 0; $x < $width; $x++) {
				if (!is_int($colors[$y][$x] ?? null)) {
					throw new \InvalidArgumentException('Color is corrupted on [X: ' . $x . ', Y: ' . $y . ']');
				}
				
				$data->putInt($colors[$y][$x]);
			}
		}
		
		return new MapImageChunk($width, $height, $data->buffer);
	}
	
	/**
	 * Creates a new map image chunk from the ABGR colors array
	 *
	 * @param int   $width
	 * @param int   $height
	 * @param array $colors
	 *
	 * @return MapImageChunk
	 */
	public static function fromArrayABGR(int $width, int $height, array $colors) {
		if ($width < 0 || $height < 0) {
			throw new \InvalidArgumentException('Width/height must be greater than 0');
		}
		
		$data = new BinaryStream;
		
		for ($y = 0; $y < $height; $y++) {
			for ($x = 0; $x < $width; $x++) {
				if (!is_int($colors[$y][$x] ?? null)) {
					throw new \InvalidArgumentException('Color is corrupted on [X: ' . $x . ', Y: ' . $y . ']');
				}
				
				$data->putLInt($colors[$y][$x]);
			}
		}
		
		return new MapImageChunk($width, $height, $data->buffer);
	}
	
	
	/**
	 * Creates a new map image chunk with the specified color
	 *
	 * @param int $width
	 * @param int $height
	 * @param int $fill_color
	 *
	 * @return MapImageChunk
	 */
	public static function generateImageChunk(int $width, int $height, int $fill_color = 0) {
		if ($width < 0 || $height < 0) {
			throw new \InvalidArgumentException('Width/height must be greater than 0');
		}
		
		return new MapImageChunk($width, $height, str_repeat(Binary::writeInt($fill_color), $width * $height));
	}
	
	private function getStartOffset(int $x, int $y) : int {
		if ($x < 0 || $y < 0) {
			throw new \InvalidArgumentException('X/Y must be greater than 0');
		}
		if ($x >= $this->width) {
			throw new \InvalidArgumentException('X cannot be greater than width');
		}
		if ($y >= $this->height) {
			throw new \InvalidArgumentException('Y cannot be greater than height');
		}
		
		return ($y * $this->width) + $x;
	}
	
	public function __clone() {
		$this->map_id = Entity::$entityCount++;
	}
	
}
