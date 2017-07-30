<?php
namespace FaigerSYS\MIE_Converter;

class MapImageUtils {
	
	const MAP_WIDTH = 128;
	const MAP_HEIGHT = 128;
	
	const CURRENT_API = 1;
	
	const TYPE_SIMPLE = 0;
	const TYPE_ANIMATED = 1;
	
	public static function prepareFrame($image, int $x_explode = 1, int $y_explode = 1, bool $destroy_image = true) {
		if (!is_resource($image) || $x_explode < 0 || $y_explode < 0) {
			return;
		}
		
		$old_image = $image;
		$old_width = imagesx($old_image);
		$old_height = imagesy($old_image);
		
		$width = self::MAP_WIDTH * $x_explode;
		$height = self::MAP_HEIGHT * $y_explode;
		
		$image = imagecreatetruecolor($width, $height);
		imagesavealpha($image, true);
		imagefill($image, 0, 0, imagecolorallocatealpha($image, 0, 0, 0, 127));
		imagecopyresampled($image, $old_image, 0, 0, 0, 0, $width, $height, $old_width, $old_height);
		
		if ($destroy_image) {
			imagedestroy($old_image);
		}
		
		$images = [];
		for ($y_block = 0; $y_block < $y_explode; $y_block++) {
			for ($x_block = 0; $x_block < $x_explode; $x_block++) {
				$colors = [];
				
				for ($y = 0; $y < self::MAP_HEIGHT; $y++) {
					for ($x = 0; $x < self::MAP_WIDTH; $x++) {
						$raw_color = imagecolorsforindex($image, imagecolorat($image, $x + ($x_block * self::MAP_WIDTH), $y + ($y_block * self::MAP_HEIGHT)));
						$r = $raw_color['red'];
						$g = $raw_color['green'];
						$b = $raw_color['blue'];
						$a = $raw_color['alpha'] === 0 ? 255 : ~$raw_color['alpha'] << 1 & 0xff;
						
						$colors[$y][$x] = ($a << 24) | ($b << 16) | ($g << 8) | $r;
					}
				}
				
				// This is needed to reduce the RAM usage for converting
				$colors = json_encode($colors);
				$colors = gzdeflate($colors, 6);
				$colors = base64_encode($colors);
				$images[$y_block][$x_block] = $colors;
			}
		}
		
		imagedestroy($image);
		
		return $images;
	}
	
	public static function generateImageData($image, int $x_explode, int $y_explode, bool $destroy_image = true) {
		$data = [];
		$data['api'] = self::CURRENT_API;
		
		if (is_resource($image) && get_resource_type($image) === 'gd') {
			$data['type'] = self::TYPE_SIMPLE;
			$data['blocks'] = self::prepareFrame($image, $x_explode, $y_explode, $destroy_image);
		} else {
			return;
		}
		
		$data = json_encode($data);
		$data = gzdeflate($data, 6);
		return $data;
	}
	
}
