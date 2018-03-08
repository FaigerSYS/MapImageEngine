<?php
namespace FaigerSYS\MapImageEngine;

use pocketmine\Server;

class TranslateStrings {
	
	const DEFAULT_LANG = 'eng';
	
	/** @var string[] */
	private static $strings = [];
	
	public static function init() {
		$lang = Server::getInstance()->getLanguage()->getLang();
		$owner = MapImageEngine::getInstance();
		
		$default_strings = parse_ini_string(stream_get_contents($owner->getResource('strings/' . self::DEFAULT_LANG . '.ini')));
		if ($strings = $owner->getResource('strings/' . $lang . '.ini')) {
			$strings = parse_ini_string(stream_get_contents($strings)) + $default_strings;
		} else {
			$strings = $default_strings;
		}
		
		self::$strings = $strings;
	}
	
	public static function translate(string $str, ...$args) {
		return sprintf(self::$strings[$str] ?? $str, ...$args);
	}
	
}
