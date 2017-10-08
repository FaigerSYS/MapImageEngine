<?php
namespace FaigerSYS\MapImageEngine;

use pocketmine\plugin\PluginBase;

use pocketmine\utils\TextFormat as CLR;

use pocketmine\item\Item;
use pocketmine\item\ItemFactory;
use FaigerSYS\MapImageEngine\item\FilledMap as FilledMapItem;

use pocketmine\tile\ItemFrame;

use pocketmine\event\Listener;
use pocketmine\event\server\DataPacketReceiveEvent;
use pocketmine\event\level\ChunkLoadEvent;

use pocketmine\network\mcpe\protocol\PacketPool;

use FaigerSYS\MapImageEngine\pocketmine_bc\MapInfoRequestPacket;
use FaigerSYS\MapImageEngine\pocketmine_bc\ClientboundMapItemDataPacket;

use FaigerSYS\MapImageEngine\TranslateStrings as TS;

use FaigerSYS\MapImageEngine\storage\ImageStorage;
use FaigerSYS\MapImageEngine\storage\MapImage;
use FaigerSYS\MapImageEngine\storage\OldFormatConverter;

use FaigerSYS\MapImageEngine\command\MapImageEngineCommand;

class MapImageEngine extends PluginBase implements Listener {
	
	const UNSUPPORTED_SOFTWARE = [
		'PocketMine-Steadfast' => 'Steadfast',
	];
	
	/** @var MapImageEngine */
	private static $instance;
	
	/** @var ImageStorage */
	private $storage;
	
	public function onEnable() {
		if ($name = self::UNSUPPORTED_SOFTWARE[$this->getServer()->getName()] ?? null) {
			$this->getLogger()->error($name . ' is not supported! Try to use another server software');
		}
		
		$old_plugin = self::$instance;
		self::$instance = $this;
		$is_reload = $old_plugin instanceof MapImageEngine;
		
		TS::init();
		
		$this->getLogger()->info(CLR::GOLD . TS::translate($is_reload ? 'plugin-loader.reloading' : 'plugin-loader.loading'));
		$this->getLogger()->info(CLR::AQUA . TS::translate('plugin-loader.info-instruction'));
		$this->getLogger()->info(CLR::AQUA . TS::translate('plugin-loader.info-long-loading'));
		$this->getLogger()->info(CLR::AQUA . TS::translate('plugin-loader.info-1.1-update'));
		
		if ($is_reload) {
			$this->storage = $old_plugin->storage;
		}
		
		$this->getServer()->getPluginManager()->registerEvents($this, $this);
		
		$this->registerItems();
		$this->registerPackets();
		$this->registerCommands();
		
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
		
		$this->loadImages($is_reload);
		
		$this->getLogger()->info(CLR::GOLD . TS::translate($is_reload ? 'plugin-loader.reloaded' : 'plugin-loader.loaded'));
	}
	
	private function registerCommands() {
		$this->getServer()->getCommandMap()->register('mapimageengine', new MapImageEngineCommand);
	}
	
	private function registerItems() {
		try {
			if (class_exists(ItemFactory::class)) {
				$class = ItemFactory::class;
			} else {
				throw \Exception;
			}
		} catch (\Throwable $e) {
			$class = Item::class;
		}
		
		if (method_exists($class, 'registerItem')) {
			$class::registerItem(new FilledMapItem, true);
		} else {
			foreach ($class::$list as $item) {
				if ($item !== null) {
					if (is_array($item)) {
						$class::$list[Item::FILLED_MAP ?? 358][0] = is_string(reset($item)) ? FilledMapItem::class : new FilledMapItem;
					} else {
						$class::$list[Item::FILLED_MAP ?? 358] = is_string($item) ? FilledMapItem::class : new FilledMapItem;
					}
					break;
				}
			}
		}
	}
	
	private function registerPackets() {
		try {
			if (class_exists(PacketPool::class)) {
				PacketPool::registerPacket(new MapInfoRequestPacket);
				PacketPool::registerPacket(new ClientboundMapItemDataPacket);
			} else {
				throw \Exception;
			}
		} catch (\Throwable $e) {
			$this->getServer()->getNetwork()->registerPacket(MapInfoRequestPacket::NETWORK_ID, MapInfoRequestPacket::class);
			$this->getServer()->getNetwork()->registerPacket(ClientboundMapItemDataPacket::NETWORK_ID, ClientboundMapItemDataPacket::class);
		}
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
	
	public function getImageStorage() {
		return $this->storage;
	}
	
	/**
	 * @priority LOW
	 * @ignoreCancelled true
	 */
	public function onRequest(DataPacketReceiveEvent $e) {
		if ($e->getPacket() instanceof MapInfoRequestPacket) {
			$this->getImageStorage()->sendImage($e->getPacket()->mapId, $e->getPlayer());
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
				if ($item instanceof FilledMapItem) {
					$frame->setItem($item);
				}
			}
		}
	}
	
	public static function getInstance() {
		return self::$instance;
	}
	
}
