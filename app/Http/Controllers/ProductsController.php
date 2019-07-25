<?php 
namespace App\Http\Controllers;

use App\Helpers\ErrorCodes;
use App\Models\User;
use App\Models\Product;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Validator;
use App\Services\ProductService;
use App\Services\BaseService;
use App\Models\Category;
use Carbon\Carbon;
use Illuminate\Support\Facades\File;

class ProductsController extends Controller
{ 
	private $productsService;
    private $BaseService;

    public function __construct()
    {
        parent::__construct();

        $this->productsService = new ProductService();

        $this->BaseService = new BaseService();
    }

    public function getAll(Request $request)
    {
        try{

        	$pagParams = $this->getPaginationParams($request);

        	$product = Product::select("products.*","categories.name as category")->join("categories", "products.category_id" , "=", "categories.id")->where('products.id', '!=', null);

        	$paginationData = $this->getPaginationData($product, $pagParams['page'], $pagParams['limit']);

        	$product = $product->offset($pagParams['offset'])->limit($pagParams['limit'])->get();

        	return($product ? $this->returnSuccess($product, $paginationData) : $text = "There are no products");

        }catch (\Exception $e) {

            return $this->returnError($e->getMessage(), ErrorCodes::FRAMEWORK_ERROR);
            
        }
    }

    public function get(Request $request,$id)
    {
        try{

        	$pagParams = $this->getPaginationParams($request);

        	$product = Product::where('category_id', $id);

        	$paginationData = $this->getPaginationData($product, $pagParams['page'], $pagParams['limit']);

        	$product = $product->offset($pagParams['offset'])->limit($pagParams['limit'])->get();

        	return($product ? $this->returnSuccess($product, $paginationData) : $text = "There are no products");

        }catch (\Exception $e) {

            return $this->returnError($e->getMessage(), ErrorCodes::FRAMEWORK_ERROR);

        }

    }


    public function create(Request $request)
    {
        try {
            /** @var \Illuminate\Validation\Validator $validator */
            $user = Auth::user();

	    	if($user->type != 1){

	    		return (json_encode("You do not have permission"));

	    	}

            $validator = $this->productsService ->validateCreateRequest($request);

            if (!$validator->passes()) {

                return $this->returnError($validator->messages(), ErrorCodes::REQUEST_ERROR);

            }

            $count = count(Product::where("Category_ID", '=', $request->get('category_id'))->get());

            $quantity = $request->get('quantity');

            $fullprice = $request->get('full_price');

            $category_id = $request->get('category_id');
            $updated = $this->productsService->update_sale_price($category_id , $count , false);

			$salesPrice=$this->productsService->getSalePrice($count, $quantity, $fullprice);

            $path = storage_path('image')."/";
            $img = $this->BaseService->processImage($path , $request->image);
            if(!$img){
                return json_encode("Invalid image format");
            }

            $category = Product::create([
							            'name' => $request->get('name'),
							            'description' =>$request->get('description'),
							            'quantity' => $request->get('quantity'),
							            'full_price' => $fullprice,
							            'sale_price' => $salesPrice,
							            'category_id' => $request->get('category_id'),
							            'photo'=> $img,
            							]);

            return $this->returnSuccess($category);

        } catch (\Exception $e) {

            return $this->returnError($e->getMessage(), ErrorCodes::FRAMEWORK_ERROR);

        }
    }

    public function update(Request $request,$id)
    {
    	try{
    		$user = Auth::user();

    		if($user->type != 1){
    			return (json_encode("You do not have permission"));
    			die();
    		}

    		$validator = $this->productsService->validateUpdateRequest($request, $id);

            if (!$validator->passes()) {

                return $this->returnError($validator->messages(), ErrorCodes::REQUEST_ERROR);

            }

    		$count_products_in_category = count(Product::where("Category_ID", '=', $request->get('category_id'))->get());

            $category_id = $request->get('category_id');

            $updated = $this->productsService->update_sale_price($category_id, $count_products_in_category , $id);



            $quantity = $request->get('quantity');
            $fullprice = $request->get('full_price');
            $count_products_in_category= $count_products_in_category + 1 ;

            $salesPrice=$this->productsService->getSalePrice($count_products_in_category, $quantity, $fullprice);

			$product = Product::where('id', $id)->first();

            if($request->image){

                $path = storage_path('image')."/".$product->photo;
                File::delete($path);

                $path = storage_path('image')."/";
                $img = $this->BaseService->processImage($path , $request->image);

                $product->photo = $img;

            }

			$product->name = $request->get('name');
			$product->description = $request->get('description');
			$product->quantity = $quantity;
			$product->sale_price = $salesPrice;

            if($product->category_id !== ""){

			 $product->category_id = $request->get('category_id');

            }

			$product->save();

			return $this->returnSuccess();

    	}catch(\Exception $e){

    		return $this->returnError($e->getMessage(), ErrorCodes::FRAMEWORK_ERROR);

    	}
    }

    public function delete($id)
    {
    	try{
    		$user = Auth::user();

    		if($user->type != 1){
    			return (json_encode("You do not have permission"));
    			die();
    		}

    		$product = Product::findOrFail($id);

    		$category_id = $product ->category_id;

    		$count_products_in_category = count(Product::where("Category_ID", '=', $category_id)->get());

    		$updated = $this->productsService->update_sale_price($category_id, $count_products_in_category , $id);

            $img = $product->photo;

            $path = storage_path('image')."/".$img;
            File::delete($path);

    		$product->delete();

    		return $this->returnSuccess();

    	}catch(\Exception $e){

    		return $this->returnError($e->getMessage(), ErrorCodes::FRAMEWORK_ERROR);

    	}    	
    }

    public function deleteAllProductsFromACategory($id)
    {
        try{
            $user = Auth::user();

            if($user->type != 1){
                return (json_encode("You do not have permission"));
                die();
            }

            $product = Product::where("category_id", $id);
            $products = $product->get();

            foreach ($products as $img) {

                $path = storage_path('image')."/".$img->photo;
                File::delete($path);

            }

            $product = $product->delete();

            if($product){

                Category::where("id", $id)->delete();

            }
            return $this->returnSuccess();

        }catch(\Exception $e){

            return $this->returnError($e->getMessage(), ErrorCodes::FRAMEWORK_ERROR);

        }               
    }

}
?>