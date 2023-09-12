<?php
namespace FaigerSYS\MIE_Converter;

use pocketmine\utils\UUID;
use pocketmine\utils\BinaryStream;

class MapImageUtils {
	
	const MAP_WIDTH = 128;
	const MAP_HEIGTH = 128;
	
	const CURRENT_VERSION = 2;
	
	public static function generateImageData($image, int $blocks_width, int $blocks_height, int $compression_level = 0, int $resize_type = IMG_NEAREST_NEIGHBOUR, int $chunk_width = self::MAP_WIDTH, int $chunk_height = self::MAP_HEIGTH) {
		if ($image === false || $blocks_width < 0 || $blocks_height < 0 || $chunk_width < 0 || $chunk_height < 0) {
			return;
		}
		
		$old_image = $image;
		$old_width = imagesx($old_image);
		$old_heigth = imagesy($old_image);
		
		$width = $chunk_width * $blocks_width;
		$height = $chunk_height * $blocks_height;
		
		$image = imagescale($image, $width, $height, $resize_type);
		
		$data = new BinaryStream();
		$data->put('MIEI');
		$data->putInt(self::CURRENT_VERSION);
		$data->putByte((int) ($compression_level > 0));
		
		$buffer = new BinaryStream();
		$buffer->put(UUID::fromRandom()->toBinary());
		$buffer->putInt($blocks_width);
		$buffer->putInt($blocks_height);
		
		for ($y_b = 0; $y_b < $blocks_height; $y_b++) {
			for ($x_b = 0; $x_b < $blocks_width; $x_b++) {
				$buffer->putInt($chunk_width);
				$buffer->putInt($chunk_height);
				
				for ($y = 0; $y < $chunk_width; $y++) {
					for ($x = 0; $x < $chunk_height; $x++) {
						$color = imagecolorsforindex($image, imagecolorat($image, $x + ($x_b * $chunk_width), $y + ($y_b * $chunk_height)));
						$color = chr($color['red']) . chr($color['green']) . chr($color['blue']) . chr($color['alpha'] === 0 ? 255 : ~$color['alpha'] << 1 & 0xff);
						
						$buffer->put($color);
					}
				}
			}
		}
		
		imagedestroy($image);
		
		$buffer = $buffer->buffer;
		if ($compression_level > 0) {
			$buffer = zlib_encode($buffer, ZLIB_ENCODING_DEFLATE, min($compression_level, 9));
		}
		$data->put($buffer);
		
		return $data->buffer;
	}
}
