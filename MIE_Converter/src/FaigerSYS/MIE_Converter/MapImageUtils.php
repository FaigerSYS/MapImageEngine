<?php
namespace FaigerSYS\MIE_Converter;

class MapImageUtils {
	
	const MAP_WIDTH = 128;
	const MAP_HEIGHT = 128;
	
	const CURRENT_API = 1;
	
	public static function generateImageData($image, int $x_explode, int $y_explode) {
		if (is_resource($image) && get_resource_type($image) === 'gd' && $x_explode > 0 && $y_explode > 0) {
			$data = [];
			$data['api'] = self::CURRENT_API;
			$data['type'] = 0;
			
			$old_image = $image;
			$old_width = imagesx($old_image);
			$old_height = imagesy($old_image);
			
			$width = self::MAP_WIDTH * $x_explode;
			$height = self::MAP_HEIGHT * $y_explode;
			
			$image = imagescale($image, $width, $height);
			
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
					
					// This is needed to reduce the RAM usage for converting (I know what you want to say...)
					$colors = json_encode($colors);
					$colors = gzdeflate($colors, 6);
					$colors = base64_encode($colors);
					$images[$y_block][$x_block] = $colors;
				}
			}
			
			imagedestroy($image);
			
			$data['blocks'] = $images;
			
			$data = json_encode($data);
			$data = gzdeflate($data, 6);
			return $data;
		}
	}
	
}
