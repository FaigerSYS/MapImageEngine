<?php
namespace FaigerSYS\MIE_Converter;

use pocketmine\plugin\PluginBase;

use pocketmine\command\Command;
use pocketmine\command\CommandSender;

use pocketmine\utils\TextFormat as CLR;

class MIE_Converter extends PluginBase {
	
	const MSG_PREFIX = CLR::BOLD . CLR::GOLD . '[' . CLR::RESET . CLR::GREEN . 'MIE' . CLR::BOLD . CLR::GOLD . ']' . CLR::RESET . CLR::GRAY . ' ';
	
	public function onEnable() {
		$this->getLogger()->info(CLR::GOLD . 'MIE_Converter enabling...');
		
		if (!extension_loaded('gd')) {
			$this->getLogger()->warning('Your PHP binary does not contains the GD library required for image parsing');
			$this->getLogger()->warning('Install GD, or use the online converter (website provided in the MapImageEngine\'s instructions)');
			
			$this->getServer()->getPluginManager()->disablePlugin($this);
			return;
		}
		
		@mkdir($path = $this->getDataFolder());
		@mkdir($path . 'to_convert');
		@mkdir($path . 'converted');
		
		$this->getLogger()->info(CLR::GOLD . 'MIE_Converter enabled! MIE API: ' . MapImageUtils::CURRENT_API);
	}
	
	public function onCommand(CommandSender $sender, Command $command, string $label, array $args) : bool {
		$x = array_shift($args);
		$y = array_shift($args);
		$path = implode(' ', $args);
		
		if (!is_numeric($x) || !is_numeric($y) || !strlen($path)) {
			$sender->sendMessage(self::MSG_PREFIX . 'Usage: /' . $label . ' <blocks by width> <blocks by height> <image filename/path>');
			$sender->sendMessage(CLR::GRAY . 'Notice: place image into the "to_convert" folder. Image name = full file name');
			return true;
		}
		
		$x = (int) $x;
		$y = (int) $y;
		
		if ($x < 0 || $y < 0) {
			$sender->sendMessage(self::MSG_PREFIX . 'The count of blocks must be greater than 0!');
			return true;
		}
		
		if ($path[0] !== '/') {
			$path = $this->getDataFolder() . 'to_convert/' . $path;
		}
		
		$data = @file_get_contents($path);
		if ($data === false) {
			$sender->sendMessage(self::MSG_PREFIX . 'File not found!');
			return true;
		}
		
		$image = @imagecreatefromstring($data);
		if (!is_resource($image)) {
			$sender->sendMessage(self::MSG_PREFIX . 'File is not an image, or image has unsupported by your GD library format! Convert image to supported format (e.g. PNG) and try again, or use online converter');
			return true;
		}
		
		$sender->sendMessage(self::MSG_PREFIX . 'Converting image...');
		
		$data = MapImageUtils::generateImageData($image, $x, $y);
		imagedestroy($image);
		
		$path = $this->getDataFolder() . 'converted/' . pathinfo($path)['filename'] . '_' . $x . 'x' . $y . '.mie';
		file_put_contents($path, $data);
		
		$sender->sendMessage(self::MSG_PREFIX . 'Done! Image location: ' . CLR::WHITE . $path);
		return true;
	}
	
}
