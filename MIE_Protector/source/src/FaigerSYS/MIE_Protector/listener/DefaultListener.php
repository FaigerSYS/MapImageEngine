<?php
namespace FaigerSYS\MIE_Protector\listener;

use pocketmine\event\Listener;
use pocketmine\event\player\PlayerInteractEvent;

use pocketmine\tile\ItemFrame;
use FaigerSYS\MapImageEngine\item\FilledMap;

class DefaultListener implements Listener {
	
	/**
	 * @priority LOWEST
	 */
	public function onClick(PlayerInteractEvent $e) {
		if (!$e->isCancelled()) {
			$block = $e->getBlock();
			$frame = $block->getLevel()->getTile($block);
			if ($frame instanceof ItemFrame && $frame->getItem() instanceof FilledMap && !$e->getPlayer()->hasPermission('mapimageengine.bypassprotect')) {
				$e->setCancelled(true);
			}
		}
	}
	
}
