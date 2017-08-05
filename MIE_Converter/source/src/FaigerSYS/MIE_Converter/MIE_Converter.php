<?php
namespace FaigerSYS\MIE_Converter;

use pocketmine\plugin\PluginBase;

use pocketmine\utils\TextFormat as CLR;

class MIE_Converter extends PluginBase {
	
	public function onEnable() {
		$this->getLogger()->info(CLR::GOLD . 'MIE_Converter enabling...');
		
		if (!extension_loaded('gd')) {
			dl("php_gd2.dll");
		}
		
		@mkdir($path = $this->getDataFolder());
		@mkdir($path . 'to_convert');
		@mkdir($path . 'converted');
		
		$this->getServer()->getCommandMap()->register('mapimageengine', new ConverterCommand($this));
		
		$this->getLogger()->info(CLR::GOLD . 'MIE_Converter enabled! MIE API: ' . MapImageUtils::CURRENT_API);
	}
	
}
