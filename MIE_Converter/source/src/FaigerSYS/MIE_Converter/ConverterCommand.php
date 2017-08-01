<?php
namespace FaigerSYS\MIE_Converter;

use pocketmine\utils\TextFormat as CLR;

use pocketmine\command\CommandSender;

use pocketmine_backtrace\Command;
use pocketmine\command\PluginIdentifiableCommand;

use pocketmine\plugin\Plugin;

class ConverterCommand extends Command implements PluginIdentifiableCommand {
	
	const MSG_PREFIX = CLR::BOLD . CLR::GOLD . '[' . CLR::RESET . CLR::GREEN . 'MIE' . CLR::BOLD . CLR::GOLD . ']' . CLR::RESET . CLR::GRAY . ' ';
	
	/** @var MIE_Converter */
	private $owner;
	
	public function __construct(MIE_Converter $owner) {
		$this->owner = $owner;
		
		parent::__construct('mieconvert', 'Convert image to .mie format');
		$this->setPermission('mapimageengine.convert');
	}
	
	public function onExecute(CommandSender $sender, string $label, array $args) {
		if (!$this->testPermission($sender)) {
			return;
		}
		
		$x = array_shift($args);
		$y = array_shift($args);
		$path = implode(' ', $args);
		
		if (!is_numeric($x) || !is_numeric($y) || !strlen($path)) {
			$sender->sendMessage(self::MSG_PREFIX . 'Usage: /' . $label . ' <blocks by width> <blocks by height> <image filename/path>');
			$sender->sendMessage(CLR::GRAY . 'Notice: place image into the "to_convert" folder. Image name - full file name');
			return;
		}
		
		$x = (int) $x;
		$y = (int) $y;
		
		if ($x < 0 || $y < 0) {
			$sender->sendMessage(self::MSG_PREFIX . 'The count of blocks must be greater than 0!');
			return;
		}
		
		if ($path[0] !== '/') {
			$path = $this->getPlugin()->getDataFolder() . 'to_convert/' . $path;
		}
		
		$data = @file_get_contents($path);
		if ($data === false) {
			$sender->sendMessage(self::MSG_PREFIX . 'File not found!');
			return;
		}
		
		$image = @imagecreatefromstring($data);
		if (!is_resource($image)) {
			$sender->sendMessage(self::MSG_PREFIX . 'File is not an image, or image has unsupported by your binary format! Convert image to supported format (e.g. PNG) and try again, or use online converter');
			return;
		}
		
		$sender->sendMessage(self::MSG_PREFIX . 'Converting image...');
		
		$data = MapImageUtils::generateImageData($image, $x, $y);
		
		$filename = pathinfo($path)['filename'] . '_' . $x . 'x' . $y . '.mie';
		file_put_contents($this->getPlugin()->getDataFolder() . 'converted/' . $filename, $data);
		
		$sender->sendMessage(self::MSG_PREFIX . 'Done! Image location: ' . CLR::WHITE . 'PLUGIN_FOLDER/converted/' . $filename);
	}
	
	public function getPlugin() : Plugin {
		return $this->owner;
	}
	
}
