<?php
namespace FaigerSYS\MIE_Converter;

use pocketmine\plugin\PluginBase;

use pocketmine\utils\TextFormat as CLR;

class MIE_Converter extends PluginBase {
	
	public function onEnable() {
		$this->getLogger()->info(CLR::GOLD . 'MIE_Converter enabling...');
		
		if (!extension_loaded('gd')) {
			$this->getLogger()->warning('Your PHP binary does not contains the GD library required for image parsing');
			$this->getLogger()->warning('Install GD, or use the online converter (website provided in the MapImageEngine\'s instructions)');
			
			$this->setEnabled(false);
			return;
		}
		
		@mkdir($path = $this->getDataFolder());
		@mkdir($path . 'to_convert');
		@mkdir($path . 'converted');
		
		$this->getServer()->getCommandMap()->register('mapimageengine', new ConverterCommand($this));
		
		$this->getLogger()->info(CLR::GOLD . 'MIE_Converter enabled! MIE API: ' . MapImageUtils::CURRENT_API);
	}
	
}

