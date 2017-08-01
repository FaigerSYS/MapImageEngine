<?php

$class = '\pocketmine\command\Command';

$ref = new ReflectionClass($class);
$method = $ref->getMethod('execute');
$params = $method->getParameters();
$label_param = $params[1];
$type = (string) $label_param->getType();

eval('
declare(strict_types=1);

namespace pocketmine_backtrace;

abstract class Command extends ' . $class . ' {
	
	public function execute(\pocketmine\command\CommandSender $sender, ' . $type . ' $label, array $args) {
		$this->onExecute($sender, (string) $label, $args);
	}
	
	abstract public function onExecute(\pocketmine\command\CommandSender $sender, string $label, array $args);
	
}

');
