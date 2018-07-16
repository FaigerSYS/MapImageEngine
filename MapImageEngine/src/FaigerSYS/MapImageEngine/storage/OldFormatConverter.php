<?php
namespace FaigerSYS\MapImageEngine\storage;

use pocketmine\utils\BinaryStream;
use pocketmine\utils\UUID;

use FaigerSYS\MapImageEngine\MapImageEngine;

class OldFormatConverter {
	
	public static function tryConvert(string $data) {
		try {
			$buffer = new BinaryStream;
			$buffer->put('MIEI');
			$buffer->putInt(MapImage::CURRENT_VERSION);
			$buffer->putByte(0);
			$buffer->put(UUID::fromRandom()->toBinary());
			
			$image = @gzinflate($data);
			if ($image) {
				$image = json_decode($image, true);
				if (!is_array($image)) {
					return;
				}
				
				$b_height = count($image['blocks']);
				$b_width = count($image['blocks'][0]);
				
				$buffer->putInt($b_width);
				$buffer->putInt($b_height);
				
				for ($b_y = 0; $b_y < $b_height; $b_y++) {
					for ($b_x = 0; $b_x < $b_width; $b_x++) {
						$chunk = json_decode(gzinflate(base64_decode($image['blocks'][$b_y][$b_x])));
						if (!is_array($chunk)) {
							return;
						}
						
						$chunk = MapImageChunk::fromArrayABGR(128, 128, $chunk)->toBinaryRGBA();
						
						$buffer->putInt(128);
						$buffer->putInt(128);
						$buffer->put($chunk);
					}
				}
			} else {
				return;
			}
			
			return $buffer->buffer;
		} catch (\Throwable $e) {
			return;
		}
	}
	
}
