<?php
namespace FaigerSYS\MapImageEngine;

use pocketmine\Server;

use pocketmine\command\CommandSender;

use FaigerSYS\MapImageEngine\MapImageEngine;

class TranslateStrings {
	
	const DEFAULT_LANG = 'eng';
	
	/** @var string[] */
	private static $strings = [];
	
	public static function init() {
		$lang = Server::getInstance()->getLanguage()->getLang();
		$owner = MapImageEngine::getInstance();
		
		if (!($strings = $owner->getResource('strings/' . $lang . '.ini'))) {
			$strings = $owner->getResource('strings/' . self::DEFAULT_LANG . '.ini');
		}
		$strings = parse_ini_string(stream_get_contents($strings));
		
		self::$strings = $strings;
	}
	
	public static function translate(string $str, ...$args) {
		return sprintf(self::$strings[$str] ?? $str, ...$args);
	}
	
}
