<?php
namespace FaigerSYS\MapImageEngine\command;

use pocketmine\utils\TextFormat as CLR;

use pocketmine\Player;
use pocketmine\command\CommandSender;
use pocketmine\command\ConsoleCommandSender;

use pocketmine\command\Command;
use pocketmine\command\PluginIdentifiableCommand;

use pocketmine\plugin\Plugin;

use pocketmine\event\Listener;
use pocketmine\event\player\PlayerInteractEvent;
use pocketmine\event\player\PlayerQuitEvent;

use pocketmine\math\Vector3;

use pocketmine\tile\ItemFrame;

use FaigerSYS\MapImageEngine\MapImageEngine;
use FaigerSYS\MapImageEngine\item\FilledMap;
use FaigerSYS\MapImageEngine\TranslateStrings as TS;

class MapImageEngineCommand extends Command implements PluginIdentifiableCommand, Listener {
	
	const MSG_PREFIX = CLR::BOLD . CLR::GOLD . '[' . CLR::RESET . CLR::GREEN . 'MIE' . CLR::BOLD . CLR::GOLD . ']' . CLR::RESET . CLR::GRAY . ' ';
	
	/** @var array */
	private $cache = [];
	
	public function __construct() {
		$this->getPlugin()->getServer()->getPluginManager()->registerEvents($this, $this->getPlugin());
		
		parent::__construct('mapimageengine', TS::translate('command.desc'), null, ['mie']);
		$this->setPermission('mapimageengine');
	}
	
	public function execute(CommandSender $sender, string $label, array $args) {
		if (!$this->testPermission($sender)) {
			return;
		}
		
		if (!$sender instanceof Player) {
			$sender->sendMessage(self::MSG_PREFIX . TS::translate('command.in-game'));
			return;
		}
		
		$cmd = array_shift($args);
		switch ($cmd) {
			case 'list':
				$list = $this->getPlugin()->getImageStorage()->getNamedImages();
				if (empty($list)) {
					$sender->sendMessage(self::MSG_PREFIX . TS::translate('command.list.no-images'));
				} else {
					$new_list = [];
					foreach ($list as $name => $image) {
						$w = $image->getBlocksWidth();
						$h = $image->getBlocksHeight();
						
						$new_list[] = $name . CLR::RESET . ' ' . CLR::AQUA  . '(' . CLR::DARK_GREEN . $w . CLR::AQUA . 'x'.  CLR::DARK_GREEN . $h . CLR::AQUA . ')';
					}
					
					$list = CLR::WHITE . CLR::ITALIC . implode(CLR::GRAY . ', ' . CLR::WHITE . CLR::ITALIC, $new_list) . CLR::GRAY;
					$sender->sendMessage(self::MSG_PREFIX . TS::translate('command.list') . $list);
				}
				break;
			
			case 'place':
				$image_name = (string) array_shift($args);
				if (!strlen($image_name)) {
					$sender->sendMessage(self::MSG_PREFIX . TS::translate('command.usage') . '/' . $label . ' place ' . TS::translate('command.place.usage'));
					$sender->sendMessage(CLR::GRAY . TS::translate('command.place.usage.flags'));
					$sender->sendMessage(CLR::GRAY . '  pretty - ' . TS::translate('command.place.usage.flags.pretty'));
					$sender->sendMessage(CLR::GRAY . '  auto - ' . TS::translate('command.place.usage.flags.auto'));
				} else {
					$image = $this->getPlugin()->getImageStorage()->getImageByName($image_name);
					if (!$image) {
						$sender->sendMessage(self::MSG_PREFIX . TS::translate('command.place.not-found', $image_name));
					} else {
						$this->cache[$sender->getName()] = [
							'image_hash' => $image->getHashedUUID(),
							'pretty'     => in_array('pretty', $args),
							'auto'       => in_array('auto', $args),
							'placed'     => 0,
							'x_count'    => $image->getBlocksWidth(),
							'y_count'    => $image->getBlocksHeight()
						];
						
						$this->processPlaceMessage($sender);
					}
				}
				break;
			
			case 'exit':
				$name = $sender->getName();
				if (isset($this->cache[$name])) {
					unset($this->cache[$name]);
					$sender->sendMessage(self::MSG_PREFIX . TS::translate('command.exit'));
				} else {
					$sender->sendMessage(self::MSG_PREFIX . TS::translate('command.exit.not-allowed'));
				}
				break;
			
			default:
				$sender->sendMessage(self::MSG_PREFIX . TS::translate('command.usage'));
				$sender->sendMessage(CLR::GRAY . '  /' . $label . ' list - ' . TS::translate('command.desc.list'));
				$sender->sendMessage(CLR::GRAY . '  /' . $label . ' place - ' . TS::translate('command.desc.place'));
				$sender->sendMessage(CLR::GRAY . '  /' . $label . ' exit - ' . TS::translate('command.desc.exit'));
		}
	}
	
	/**
	 * @priority LOW
	 * @ignoreCancelled true
	 */
	public function onTouch(PlayerInteractEvent $e) {
		if ($e->getAction() !== PlayerInteractEvent::RIGHT_CLICK_BLOCK) {
			return;
		}
		
		$player = $e->getPlayer();
		$name = $player->getName();
		
		if (isset($this->cache[$name])) {
			$block = $e->getBlock();
			$level = $block->getLevel();
			
			$frame = $level->getTile($block);
			if (!($frame instanceof ItemFrame)) {
				$player->sendMessage(self::MSG_PREFIX . TS::translate('command.place.not-frame'));
			} else {
				$data = &$this->cache[$name];
				
				if ($data['auto']) {
					if (!isset($data['p1'])) {
						$data['p1'] = $block;
						$this->processPlaceMessage($player);
					} else {
						$p1 = $data['p1'];
						$p2 = $block;
						
						$x1 = $p1->getX();
						$y1 = $p1->getY();
						$z1 = $p1->getZ();
						$x2 = $p2->getX();
						$y2 = $p2->getY();
						$z2 = $p2->getZ();
						
						if ($y1 < $y2) {
							$player->sendMessage(self::MSG_PREFIX . TS::translate('command.place.invalid-upper-corner'));
						} else if ($y1 - $y2 + 1 !== $data['y_count']) {
							$player->sendMessage(self::MSG_PREFIX . TS::translate('command.place.height-not-match'));
						} else {
							$x = $x1;
							$z = $z1;
							$a = null;
							if ($x1 === $x2) {
								$a = &$z;
								$from = $z1;
								$to = $z2;
							} else if ($z1 === $z2) {
								$a = &$x;
								$from = $x1;
								$to = $x2;
							} else {
								$player->sendMessage(self::MSG_PREFIX . TS::translate('command.place.not-flat'));
							}
							
							if (abs($to - $from) + 1 !== $data['x_count']) {
								$player->sendMessage(self::MSG_PREFIX . TS::translate('command.place.width-not-match'));
							} else if ($a !== null) {
								$x_b = -1;
								for ($a = $from; $from < $to ? $a <= $to : $a >= $to; $from < $to ? $a++ : $a--) {
									$y_b = -1;
									$x_b++;
									for ($y = $y1; $y >= $y2; $y--) {
										$y_b++;
										
										$frame = $level->getTile(new Vector3($x, $y, $z));
										if (!($frame instanceof ItemFrame)) {
											$player->sendMessage(self::MSG_PREFIX . TS::translate('command.place.no-frames'));
											break 2;
										}
										
										$map = new FilledMap();
										$map->setImageData($data['image_hash'], $x_b, $y_b);
										
										$frame->setItem($map);
									}
								}
								$player->sendMessage(self::MSG_PREFIX . TS::translate('command.place.success'));
							}
						}
						
						unset($this->cache[$name]);
					}
				} else {
					$x = $data['placed'] % $data['x_count'];
					$y = floor($data['placed'] / $data['x_count']);
					
					$map = new FilledMap();
					$map->setImageData($data['image_hash'], $x, $y);
					
					$frame->setItem($map);
					
					if (++$data['placed'] === ($data['x_count'] * $data['y_count'])) {
						$player->sendMessage(self::MSG_PREFIX . TS::translate('command.place.success'));
						unset($this->cache[$name]);
					} else {
						$this->processPlaceMessage($player);
					}
				}
			}
			
			$e->setCancelled(true);
		}
	}
	
	public function onQuit(PlayerQuitEvent $e) {
		unset($this->cache[$e->getPlayer()->getName()]);
	}
	
	private function processPlaceMessage(Player $player) {
		$name = $player->getName();
		$data = &$this->cache[$name];
		
		$player->sendMessage('');
		$player->sendMessage(self::MSG_PREFIX . TS::translate('command.place.placing'));
		
		if ($data['auto']) {
			if (!isset($data['p1'])) {
				$player->sendMessage(CLR::GRAY . TS::translate('command.place.click-top-left'));
			} else {
				$player->sendMessage(CLR::GRAY . TS::translate('command.place.click-bottom-right'));
			}
		} else {
			$player->sendMessage(CLR::GRAY . TS::translate('command.place.placing-info'));
			
			$x = (int) $data['placed'] % $data['x_count'];
			$y = (int) ($data['placed'] / $data['x_count']);
			
			if ($data['pretty']) {
				$block = "\xe2\xac\x9b";
				
				for ($y_b = 0; $y_b < $data['y_count']; $y_b++) {
					$line = CLR::WHITE;
					for ($x_b = 0; $x_b < $data['x_count']; $x_b++) {
						$line .= ($x_b === $x && $y_b === $y) ? CLR::GREEN . $block . CLR::WHITE : $block;
					}
					
					$player->sendMessage($line);
				}
			}
			
			$player->sendMessage(CLR::GRAY . TS::translate('command.place.click', $x + 1, $y + 1));
		}
	}
	
	public function getPlugin() : Plugin {
		return MapImageEngine::getInstance();
	}
	
}
