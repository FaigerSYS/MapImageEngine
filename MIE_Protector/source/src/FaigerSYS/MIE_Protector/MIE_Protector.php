<?php
namespace FaigerSYS\MIE_Protector;

use pocketmine\plugin\PluginBase;

use pocketmine\utils\TextFormat as CLR;

use FaigerSYS\MIE_Protector\listener\DefaultListener;
use FaigerSYS\MIE_Protector\listener\ExtendedListener;

class MIE_Protector extends PluginBase {
	
	public function onEnable() {
		$this->getLogger()->info(CLR::GOLD . 'MIE_Protector enabling...');
		
		$this->getServer()->getPluginManager()->registerEvents(new DefaultListener, $this);
		try {
			if (class_exists('\pocketmine\event\block\ItemFrameDropItemEvent')) {
				$this->getServer()->getPluginManager()->registerEvents(new ExtendedListener, $this);
			}
		} catch (\Throwable $e) {}
		
		$this->getLogger()->info(CLR::GOLD . 'MIE_Protector enabled!');
	}
	
}
