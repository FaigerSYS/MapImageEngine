<?php
namespace FaigerSYS\Toolkit;

use pocketmine\network\mcpe\protocol\DataPacket as DataPacket_1;
use pocketmine\network\protocol\DataPacket as DataPacket_2;

use pocketmine\network\mcpe\protocol\BatchPacket as BatchPacket_1;
use pocketmine\network\protocol\BatchPacket as BatchPacket_2;

use pocketmine\utils\Binary;

class PacketCompressor {
	
	/** @var int */
	protected $level;
	
	/** @var DataPacket_1[]|DataPacket_2[] */
	protected $packets = [];
	
	/**
	 * @param int $compression_level
	 */
	public function __construct(int $compression_level) {
		$this->setCompressionLevel($compression_level);
	}
	
	/**
	 * Adds new packet to compress
	 *
	 * @param DataPacket_1[]|DataPacket_2[] ...$packets
	 */
	public function addPacket(...$packets) {
		foreach ($packets as $packet) {
			if ($packet instanceof DataPacket_1 || $packet instanceof DataPacket_2) {
				$this->packets[] = $packet;
			}
		}
		return $this;
	}
	
	/**
	 * Sets zlib compression level
	 *
	 * @param int $level
	 */
	public function setCompressionLevel(int $level) {
		$this->level = max(0, min(9, $level));
		return $this;
	}
	
	/**
	 * Returns zlib compression level
	 *
	 * @return int
	 */
	public function getCompressionLevel() : int {
		return $this->level;
	}
	
	/**
	 * Makes a compressed packet
	 *
	 * @return BatchPacket_1|BatchPacket_2
	 */
	public function getCompressed() {
		try {
			$batch = new BatchPacket_1;
		} catch (\Throwable $e) {
			$batch = new BatchPacket_2;
		}
		
		foreach ($this->packets as $pk) {
			if (method_exists($batch, 'addPacket')) {
				$batch->addPacket($pk);
			} else {
				if (!isset($pk->isEncoded) || !$pk->isEncoded) {
					$pk->encode();
				}
				
				$batch->payload .= Binary::writeUnsignedVarInt(strlen($pk->buffer)) . $pk->buffer;
			}
		}
		
		if (method_exists($batch, 'compress')) {
			$batch->compress($this->level);
		} elseif (method_exists($batch, 'setCompressionLevel')) {
			$batch->setCompressionLevel($this->level);
		} else {
			$batch->payload = zlib_encode($batch->payload, ZLIB_ENCODING_DEFLATE, $this->level);
		}
		
		return $batch;
	}
	
	/**
	 * Makes a compressed packet instance
	 *
	 * @param int                           $compression_level
	 * @param DataPacket_1[]|DataPacket_2[] ...$packets
	 *
	 * @return BatchPacket_1|BatchPacket_2
	 */
	public static function compressInstance(int $compression_level, ...$packets) {
		return (new PacketCompressor($compression_level))->addPacket(...$packets)->getCompressed();
	}
	
}
