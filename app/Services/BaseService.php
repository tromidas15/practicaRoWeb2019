<?php

namespace App\Services;

use Illuminate\Support\Facades\File;
use Intervention\Image\Facades\Image;
use Illuminate\Support\Facades\URL;
/**
 * Class BaseService
 *
 * @package App\Services
 */
class BaseService
{
    public function validate_image($type)
    {
        if(preg_match("/\.(gif|png|jpg)$/", $type)){
            return true;
        }else{
            return false;
        }

    }

	public function get_image_name($image)
	{
        $pos  = strpos($image, ';');
        $type = explode('/', substr($image, 0, $pos))[1];
        $name = $type ? time().'.'.$type : false;
        return $name;
	}


    public function processImage($path, $image)
    {
    	$name = $this->get_image_name($image);

        if($name){
            $pictureData = [];

       		$originalMaxImage = Image::make($image);

            File::makeDirectory($path, 0777, true, true);

            $originalMaxImage->save($path . $name);

            return $name;
        }
        return false ;

    }
}
