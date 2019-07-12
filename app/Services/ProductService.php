<?php


namespace App\Services;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

/**
 * Class CategoryService
 *
 * @package App\Services
 */
class ProductService
{
    public function validateCreateRequest(Request $request)
    {
        $rules = [
            'name' => 'required|unique:products',
            'description' => 'required',
            'category_id' => 'required|numeric|exists:categories,id',
            'full_price' => 'required|numeric',
       	 	'quantity' => 'required|numeric',	
        ];

        $messages = [
            'name.required' => 'errors.name.required'
        ];

        return Validator::make($request->all(), $rules, $messages);
    }

    public function validateUpdateRequest(Request $request, $id)
    {
        $rules = [
            'name' => 'required|unique:products,name,'.$id.'id',
            'description' => 'required',
            'category_id' => 'required|numeric|exists:categories,id',
            'full_price' => 'required|numeric',
            'quantity' => 'required|numeric',   
        ];

        $messages = [
            'name.required' => 'errors.name.required'
        ];

        return Validator::make($request->all(), $rules, $messages);
    }

    public function getSalePrice($count, $quantity, $fullprice)
    {
            if($quantity > 100 && $count <= 2){

                $salesPrice = $fullprice+$fullprice/10;
                return($salesPrice);

            }elseif ($quantity > 100 && $count > 2) {

                $salesPrice = $fullprice+($fullprice/100*5);
                return($salesPrice);

            }elseif($quantity <= 100 && $count > 2){

                $salesPrice = $fullprice-($fullprice/100*5);
                return($salesPrice);

            }else{

                $salesPrice = $fullprice;
                return($salesPrice);

            }
    }
}
