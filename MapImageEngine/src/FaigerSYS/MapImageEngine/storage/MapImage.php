<?php
namespace FaigerSYS\MapImageEngine\storage;

use pocketmine\utils\BinaryStream;
use pocketmine\utils\UUID;

use pocketmine\network\mcpe\protocol\BatchPacket;

class MapImage {
	
	/**
	 * Image parsed succesfully
	 */
	const R_OK = 1;
	/**
	 * Image corrupted or has unsupported format
	 */
	const R_CORRUPTED = 2;
	/**
	 * Image has unsupported API version
	 */
	const R_UNSUPPORTED_VERSIONS = 3;
	
	/**
	 * Curren version of MIE image binary
	 */
	const CURRENT_VERSION = 2;
	/**
	 * List of supported versions of MIE image binary
	 */
	const SUPPORTED_VERSIONS = [2];
	
	/** @var int */
	private $blocks_width;
	private $blocks_height;
	
	/** @var int */
	private $default_chunk_width;
	private $default_chunk_height;
	
	/** @var MapImageChunk[][] */
	private $chunks = [];
	
	/** @var UUID */
	private $uuid;
	
	/**
	 * @param int               $blocks_width
	 * @param int               $blocks_height
	 * @param MapImageChunk[][] $chunks
	 * @param UUID              $uuid
	 * @param int               $default_chunk_width
	 * @param int               $default_chunk_height
	 */
	public function __construct(int $blocks_width, int $blocks_height, array $chunks = [], UUID $uuid = null, int $default_chunk_width = 128, int $default_chunk_height = 128) {
		if ($blocks_width < 0 || $blocks_height < 0) {
			throw new \InvalidArgumentException('Blocks width/height must be greater than 0');
		}
		
		$this->blocks_width = $blocks_width;
		$this->blocks_height = $blocks_height;
		$this->uuid = $uuid ?? UUID::fromRandom();
		$this->default_chunk_width = $default_chunk_width;
		$this->default_chunk_height = $default_chunk_height;
		$this->setChunks($chunks);
	}
	
	/**
	 * Returns image blocks width
	 *
	 * @return int
	 */
	public function getBlocksWidth() : int {
		return $this->blocks_width;
	}
	
	/**
	 * Returns image blocks height
	 *
	 * @return int
	 */
	public function getBlocksHeight() : int {
		return $this->blocks_height;
	}
	
	/**
	 * Sets image blocks width
	 *
	 * @param int $blocks_width
	 */
	public function setBlocksWidth(int $blocks_width) {
		if ($blocks_width < 0) {
			throw new \InvalidArgumentException('Blocks width must be greater than 0');
		}
		$this->blocks_width = $blocks_width;
		$this->checkChunks();
	}
	
	/**
	 * Sets image blocks height
	 *
	 * @param int $blocks_width
	 */
	public function setBlocksHeight(int $blocks_height) {
		if ($blocks_height < 0) {
			throw new \InvalidArgumentException('Blocks height must be greater than 0');
		}
		$this->blocks_height = $blocks_height;
		$this->checkChunks();
	}
	
	/**
	 * Returns the image chunk at specified position
	 *
	 * @param int $block_x
	 * @param int $block_y
	 *
	 * @return MapImageChunk|null
	 */
	public function getChunk(int $block_x, int $block_y) {
		return $this->chunks[$block_y][$block_x] ?? null;
	}
	
	/**
	 * Returns all image chunks
	 *
	 * @return MapImageChunk|null
	 */
	public function getChunks() : array {
		return $this->chunks;
	}
	
	/**
	 * Sets the image chunk at specified position
	 *
	 * @param int           $block_x
	 * @param int           $block_y
	 * @param MapImageChunk $chunk
	 */
	public function setChunk(int $block_x, int $block_y, MapImageChunk $chunk) {
		if ($block_x < 0 || $block_y < 0) {
			throw new \InvalidArgumentException('Block X/Y must be greater than 0');
		}
		if ($block_x >= $this->blocks_width) {
			throw new \InvalidArgumentException('Block X cannot be greater than width');
		}
		if ($block_y >= $this->blocks_height) {
			throw new \InvalidArgumentException('Block Y cannot be greater than height');
		}
		
		$this->chunks[$block_y][$block_x] = $chunk;
	}
	
	/**
	 * Rewrites all image chunks
	 *
	 * @param MapImageChunk[][] $chunks
	 */
	public function setChunks(array $chunks) {
		$this->chunks = $chunks;
		$this->checkChunks();
	}
	
	/**
	 * Generates bathed packet for all of image chunks
	 *
	 * @param int $compression_level
	 *
	 * @return BatchPacket
	 */
	public function generateBatchedMapImagesPacket(int $compression_level = 6) {
		$pk = new BatchPacket();
		$pk->setCompressionLevel($compression_level);
		foreach ($this->chunks as $chunk) {
			$pk->addPacket($chunk->generateMapImagePacket());
		}
		return $pk;
	}
	
	/**
	 * Generates bathed packet for all of image chunks
	 *
	 * @param int $compression_level
	 *
	 * @return BatchPacket
	 */
	public function generateBatchedCustomMapImagesPacket(int $compression_level = 6) {
		$pk = new BatchPacket();
		$pk->setCompressionLevel($compression_level);
		foreach ($this->chunks as $chunk) {
			$pk->addPacket($chunk->generateCustomMapImagePacket());
		}
		return $pk;
	}
	
	/**
	 * Returns the image UUID
	 *
	 * @return UUID
	 */
	public function getUUID() : UUID {
		return $this->uuid;
	}
	
	/**
	 * Returns the image UUID hash
	 *
	 * @return string
	 */
	public function getHashedUUID() : string {
		return hash('sha1', $this->uuid->toBinary());
	}
	
	/**
	 * Creates new MapImage object from MIE image binary
	 *
	 * @param stirng $buffer
	 * @param int    &$state
	 *
	 * @return MapImage|null
	 */
	public static function fromBinary(string $buffer, &$state = null) {
		try {
			$buffer = new BinaryStream($buffer);
			
			$header = $buffer->get(4);
			if ($header !== 'MIEI') {
				$state = self::R_CORRUPTED;
				return;
			}
			
			$api = $buffer->getInt();
			if (!in_array($api, self::SUPPORTED_VERSIONS)) {
				$state = self::R_UNSUPPORTED_VERSIONS;
				return;
			}
			
			$is_compressed = $buffer->getByte();
			if ($is_compressed) {
				$buffer = $buffer->get(true);
				$buffer = @zlib_decode($buffer);
				if ($buffer === false) {
					$state = self::R_CORRUPTED;
					return;
				}
				
				$buffer = new BinaryStream($buffer);
			}
			
			$uuid = UUID::fromBinary($buffer->get(16), 4);
			
			$blocks_width = $buffer->getInt();
			$blocks_height = $buffer->getInt();
			
			$chunks = [];
			for ($block_y = 0; $block_y < $blocks_height; $block_y++) {
				for ($block_x = 0; $block_x < $blocks_width; $block_x++) {
					$chunk_width = $buffer->getInt();
					$chunk_height = $buffer->getInt();
					$chunk_data = $buffer->get($chunk_width * $chunk_height * 4);
					
					$chunks[$block_y][$block_x] = new MapImageChunk($chunk_width, $chunk_height, $chunk_data);
				}
			}
			
			$state = self::R_OK;
			return new MapImage($blocks_width, $blocks_height, $chunks, $uuid);
		} catch (\Throwable $e) {
			$state = self::R_CORRUPTED;
		}
	}
	
	private function checkChunks() {
		$chunks = $this->chunks;
		$this->chunks = [];
		for ($y = 0; $y < $this->blocks_height; $y++) {
			for ($x = 0; $x < $this->blocks_width; $x++) {
				$this->chunks[$y][$x] = ($chunks[$y][$x] ?? null) instanceof MapImageChunk ? $chunks[$y][$x] : MapImageChunk::generateImageChunk($this->default_chunk_width, $this->default_chunk_height);
			}
		}
	}
	
	public function __clone() {
		for ($y = 0; $y < $this->blocks_height; $y++) {
			for ($x = 0; $x < $this->blocks_width; $x++) {
				$this->chunks[$y][$x] = clone $this->chunks[$y][$x];
			}
		}
		$this->uuid = UUID::fromRandom();
	}
	
}
