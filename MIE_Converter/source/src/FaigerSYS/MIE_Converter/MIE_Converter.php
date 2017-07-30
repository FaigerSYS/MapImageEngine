<?php
namespace FaigerSYS\MIE_Converter;

use pocketmine\plugin\PluginBase;

use pocketmine\utils\TextFormat as CLR;

use pocketmine\command\Command;
use pocketmine\command\CommandSender;
use pocketmine\command\ConsoleCommandSender;

use pocketmine\event\Listener;

class MIE_Converter extends PluginBase implements Listener {
	
	const PREFIX = CLR::BOLD . CLR::GOLD . '[' . CLR::RESET . CLR::GREEN . 'MIE' . CLR::BOLD . CLR::GOLD . ']' . CLR::RESET . CLR::GRAY . ' ';
	
	public function onEnable() {
		$this->getLogger()->info(CLR::GOLD . 'MIE_Converter enabling...');
		
		if (!extension_loaded('gd')) {
			$this->getLogger()->warning('Your PHP binary does not contains the GD library required for image parsing');
			$this->getLogger()->warning('Install GD, or use the online converter (website provided in the MapImageEngine instructions)');
			$this->setEnabled(false);
			return;
		}
		
		@mkdir($path = $this->getDataFolder());
		@mkdir($path . 'to_convert');
		@mkdir($path . 'converted');
		
		$this->getLogger()->info(CLR::GOLD . 'MIE_Converter enabled! MIE API: ' . MapImageUtils::CURRENT_API);
	}
	
	public function onCommand(CommandSender $sender, Command $command, $label, array $args) {
		$x = array_shift($args);
		$y = array_shift($args);
		$path = implode(' ', $args);
		
		if (!is_numeric($x) || !is_numeric($y) || !strlen($path)) {
			$sender->sendMessage(self::PREFIX . 'Usage: /' . $label . ' <blocks by width> <blocks by height> <image filename>');
			$sender->sendMessage(CLR::GRAY . 'Notice: place image into the "to_convert" folder. Image name - full file name');
			return;
		}
		
		$x = (int) $x;
		$y = (int) $y;
		
		if ($x < 0 || $y < 0) {
			$sender->sendMessage(self::PREFIX . 'The count of blocks must be greater than 0!');
			return;
		}
		
		if ($path[0] !== '/') {
			$path = $this->getDataFolder() . 'to_convert/' . $path;
		}
		
		$data = @file_get_contents($path);
		if ($data === false) {
			$sender->sendMessage(self::PREFIX . 'File not found!');
			return;
		}
		
		$image = @imagecreatefromstring($data);
		if (!is_resource($image)) {
			$sender->sendMessage(self::PREFIX . 'File is not an image, or image has unsupported by your binary format! Convert image to another format (e.g. PNG) and try again, or use online converter');
			return;
		}
		
		$sender->sendMessage(self::PREFIX . 'Converting image...');
		
		$data = MapImageUtils::generateImageData($image, $x, $y);
		
		$filename = pathinfo($path)['filename'] . '_' . $x . 'x' . $y . '.mie';
		file_put_contents($this->getDataFolder() . 'converted/' . $filename, $data);
		
		$sender->sendMessage(self::PREFIX . 'Done! Image location: ' . CLR::WHITE . 'PLUGIN_FOLDER/converted/' . $filename);
	}
	
}

