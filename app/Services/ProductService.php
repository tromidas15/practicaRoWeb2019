<?php


namespace App\Services;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use App\Models\Product;
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
            'image' => 'required',
        ];

        $messages = [
            'name.required' => 'errors.name.required'
        ];

        return Validator::make($request->all(), $rules, $messages);
    }

    public function validateUpdateRequest(Request $request, $id)
    {
        $rules = [
            'name' => 'required|unique:products,name,'.$id.',id',
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

            }elseif($quantity <= 100 && $count <= 2){

                $salesPrice = $fullprice;
                return($salesPrice);

            }
    }

    public function update_last_category($category_id , $product_id)
    {
        $allProductsOfThisCategory = Product::where("category_id", '=', $category_id)->get();

        $count = count($allProductsOfThisCategory)-1;
        foreach($allProductsOfThisCategory as $product){

            $quantity = $product->quantity;
            $fullprice = $product->full_price;
            $id = $product->id;
            $sale_price = $product->sale_price;

            $salesPrice=$this->getSalePrice($count, $quantity, $fullprice);

            if($salesPrice !== $sale_price)
            {
                $product = Product::where('id', $id)->where("sale_price", '!=' , $salesPrice)->first();

                $product->sale_price = $salesPrice;

                $product->save(); 
            }
        }
        return($salesPrice);
       
    }




    public function update_sale_price($id, $count , $product)
    {


        $productsToUpdate = Product::where("category_id", '=', $id)->get();


        if($product){

                $product_id = $product;
                $get_product_details = Product::where("id" , $product)->first();

                $last_category = $get_product_details->category_id;

                $this->update_last_category($last_category, $product_id);


            }
        
            $count++;
        foreach ($productsToUpdate as $product) {
                    
            $quantity = $product->quantity;
            $fullprice = $product->full_price;
            $id = $product->id;
            $sale_price = $product->sale_price;

            $salesPrice=$this->getSalePrice($count, $quantity, $fullprice);

            
            if($salesPrice != $sale_price){
                $product = Product::where('id', $id)->where("sale_price", '!=' , $salesPrice)->first();

                $product->sale_price = $salesPrice;

                $product->save(); 

            }


        }

    }
}
