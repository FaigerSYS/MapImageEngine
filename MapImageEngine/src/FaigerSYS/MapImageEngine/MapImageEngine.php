<?php
namespace FaigerSYS\MapImageEngine;

use pocketmine\plugin\PluginBase;

use pocketmine\utils\TextFormat as CLR;

use pocketmine\item\Item;
use pocketmine\item\ItemFactory;
use FaigerSYS\MapImageEngine\item\FilledMap;

use pocketmine\tile\ItemFrame;

use pocketmine\event\Listener;
use pocketmine\event\server\DataPacketReceiveEvent;
use pocketmine\event\level\ChunkLoadEvent;

use FaigerSYS\MapImageEngine\TranslateStrings as TS;

use FaigerSYS\MapImageEngine\storage\ImageStorage;
use FaigerSYS\MapImageEngine\storage\MapImage;
use FaigerSYS\MapImageEngine\storage\OldFormatConverter;

use FaigerSYS\MapImageEngine\command\MapImageEngineCommand;

use pocketmine\network\mcpe\protocol\MapInfoRequestPacket;
use FaigerSYS\MapImageEngine\packet\CustomClientboundMapItemDataPacket;

class MapImageEngine extends PluginBase implements Listener {
	
	/** @var bool */
	private static $is_custom_pk_suppoted;
	
	/** @var MapImageEngine */
	private static $instance;
	
	/** @var ImageStorage */
	private $storage;
	
	public function onEnable() {
		$old_plugin = self::$instance;
		self::$instance = $this;
		$is_reload = $old_plugin instanceof MapImageEngine;
		
		if (!$is_reload) {
			TS::init();
		}
		
		$this->getLogger()->info(CLR::GOLD . TS::translate($is_reload ? 'plugin-loader.reloading' : 'plugin-loader.loading'));
		$this->getLogger()->info(CLR::AQUA . TS::translate('plugin-loader.info-instruction'));
		$this->getLogger()->info(CLR::AQUA . TS::translate('plugin-loader.info-long-loading'));
		$this->getLogger()->info(CLR::AQUA . TS::translate('plugin-loader.info-1.1-update'));
		
		if ($is_reload) {
			$this->storage = $old_plugin->storage;
		}
		
		$this->getServer()->getPluginManager()->registerEvents($this, $this);
		
		@mkdir($path = $this->getDataFolder());
		
		@mkdir($dir = $path . 'instructions/');
		foreach (scandir($r_dir = $this->getFile() . '/resources/instructions/') as $file) {
			if ($file{0} !== '.') {
				copy($r_dir . $file, $dir . $file);
			}
		} 
		
		@mkdir($path . 'images');
		@mkdir($path . 'images/old_files');
		@mkdir($path . 'cache');
		
		if (self::$is_custom_pk_suppoted === null) {
			self::$is_custom_pk_suppoted = CustomClientboundMapItemDataPacket::checkCompatiblity();
		}
		
		$this->loadImages($is_reload);
		
		$this->getServer()->getCommandMap()->register('mapimageengine', new MapImageEngineCommand());
		
		ItemFactory::registerItem(new FilledMap(), true);
		
		$this->getLogger()->info(CLR::GOLD . TS::translate($is_reload ? 'plugin-loader.reloaded' : 'plugin-loader.loaded'));
	}
	
	private function loadImages(bool $is_reload = false) {
		$path = $this->getDataFolder() . 'images/';
		$storage = $this->storage ?? new ImageStorage;
		
		$files = array_filter(
			scandir($path),
			function ($file) use ($path) {
				return is_file($path . $file) && substr($file, -5, 5) === '.miei';
			}
		);
		
		$old_files_path = $path . 'old_files/';
		$old_files = array_filter(
			scandir($path),
			function ($file) use ($path) {
				return is_file($path . $file) && substr($file, -4, 4) === '.mie';
			}
		);
		foreach ($old_files as $old_file) {
			$new_data = OldFormatConverter::tryConvert(file_get_contents($path . $old_file));
			if ($new_data !== null) {
				$this->getLogger()->notice(TS::translate('image-loader.prefix', $old_file) . TS::translate('image-loader.converted'));
				
				$basename = pathinfo($old_file, PATHINFO_BASENAME);
				$new_path = $old_files_path . $basename;
				$i = 0;
				while (file_exists($new_path)) {
					$new_path = $old_files_path . $basename . '.' . ++$i;
				}
				rename($path . $old_file, $new_path);
				
				$filename = pathinfo($old_file, PATHINFO_FILENAME);
				$extension = '.miei';
				$new_file = $filename . $extension;
				$i = 0;
				while (file_exists($path . $new_file)) {
					$new_file = $filename . '_' . ++$i . $extension;
				}
				file_put_contents($path . $new_file, $new_data);
				
				unset($new_data);
				
				$files[] = $new_file;
			} else {
				$this->getLogger()->warning(TS::translate('image-loader.prefix', $old_file) . TS::translate('image-loader.not-converted'));
			}
		}
		
		if (!self::isCustomPacketSupported()) {
			$this->getLogger()->warning(TS::translate('image-loader.cache-not-supported'));
		}
		
		foreach ($files as $file) {
			$image = MapImage::fromBinary(file_get_contents($path . $file), $state);
			if ($image !== null) {
				$name = substr($file, 0, -5);
				$state = $storage->registerImage($image, true, $name);
				switch ($state) {
					case ImageStorage::R_OK:
						$this->getLogger()->info(CLR::GREEN . TS::translate('image-loader.prefix', $file) . TS::translate('image-loader.success'));
						break;
						
					case ImageStorage::R_UUID_EXISTS:
						!$is_reload && $this->getLogger()->info(TS::translate('image-loader.prefix', $file) . TS::translate('image-loader.err-image-exists'));
						break;
					
					case ImageStorage::R_NAME_EXISTS:
					case ImageStorage::R_INVALID_NAME:
						$this->getLogger()->warning(TS::translate('image-loader.prefix', $file) . TS::translate('image-loader.err-name-exists'));
						break;
				}
			} else {
				switch ($state) {
					case MapImage::R_CORRUPTED:
						$this->getLogger()->warning(TS::translate('image-loader.prefix', $file) . TS::translate('image-loader.err-corrupted'));
						break;
						
					case MapImage::R_UNSUPPORTED_API:
						$this->getLogger()->warning(TS::translate('image-loader.prefix', $file) . TS::translate('image-loader.err-unsupported-api'));
						break;
				}
			}
		}
		
		$this->storage = $storage;
	}
	
	public function getImageStorage() : ImageStorage {
		return $this->storage;
	}
	
	/**
	 * @ignoreCancelled true
	 */
	public function onRequest(DataPacketReceiveEvent $e) {
		if ($e->getPacket() instanceof MapInfoRequestPacket) {
			$pk = $this->getImageStorage()->getCachedPacket($e->getPacket()->mapId);
			if ($pk !== null) {
				$e->getPlayer()->dataPacket($pk);
			}
			$e->setCancelled(true);
		}
	}
	
	/**
	 * @priority LOW
	 */
	public function onChunkLoad(ChunkLoadEvent $e) {
		foreach ($e->getChunk()->getTiles() as $frame) {
			if ($frame instanceof ItemFrame) {
				$item = $frame->getItem();
				if ($item instanceof FilledMap) {
					$frame->setItem($item);
				}
			}
		}
	}
	
	public static function getInstance() : MapImageEngine {
		return self::$instance;
	}
	
	public static function isCustomPacketSupported() : bool {
		return self::$is_custom_pk_suppoted;
	}
	
}
