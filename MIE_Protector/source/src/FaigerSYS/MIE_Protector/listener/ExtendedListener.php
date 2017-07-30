<?php
namespace FaigerSYS\MIE_Protector\listener;

use pocketmine\event\Listener;
use pocketmine\event\block\ItemFrameDropItemEvent;

use FaigerSYS\MapImageEngine\item\FilledMap;

class ExtendedListener implements Listener {
	
	/**
	 * @priority LOWEST
	 */
	public function onFrameDestroy(ItemFrameDropItemEvent $e) {
		if (!$e->isCancelled() && $e->getItem() instanceof FilledMap && !$e->getPlayer()->hasPermission('mapimageengine.bypassprotect')) {
			$e->setCancelled(true);
		}
	}
	
}
