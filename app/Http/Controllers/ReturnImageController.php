<?php
namespace App\Http\Controllers;

use Intervention\Image\Facades\Image;

class ReturnImageController extends Controller{

	public function return_image($file){
		return Image::make(storage_path('image/' . $file))->response();
	}

}
?>