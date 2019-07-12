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
class ProductsController extends Controller
{ 
	private $productsService;
    public function __construct()
    {
        parent::__construct();

        $this->productsService = new ProductService();
    }

    public function getAll(Request $request)
    {

    	$pagParams = $this->getPaginationParams($request);

    	$product = Product::where('id', '!=', null);

    	$paginationData = $this->getPaginationData($product, $pagParams['page'], $pagParams['limit']);

    	$product = $product->offset($pagParams['offset'])->limit($pagParams['limit'])->get();

    	return($product ? $this->returnSuccess($product, $paginationData) : $text = "There are no products");
    }

    public function get($id)
    {
    	$product = Product::where('id', $id)->first();
    	return($product ? json_encode($product) : $text = "The product do not exist");
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

			$salesPrice=$this->productsService->getSalePrice($count, $quantity, $fullprice);

            $category = Product::create([
							            'name' => $request->get('name'),
							            'description' =>$request->get('description'),
							            'quantity' => $request->get('quantity'),
							            'full_price' => $fullprice,
							            'sale_price' => $salesPrice,
							            'category_id' => $request->get('category_id'),
							            'photo'=> 'sad1'
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
            $quantity = $request->get('quantity');
            $fullprice = $request->get('full_price');

            $salesPrice=$this->productsService->getSalePrice($count_products_in_category, $quantity, $fullprice);

			$product = Product::where('id', $id)->first();

			$product->name = $request->get('name');
			$product->description = $request->get('description');
			$product->quantity = $quantity;
			$product->sale_price = $salesPrice;
			$product->category_id = $request->get('category_id');

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
    		$product->delete();

    		return $this->returnSuccess();

    	}catch(\Exception $e){
    		return $this->returnError($e->getMessage(), ErrorCodes::FRAMEWORK_ERROR);
    	}    	
    }

}
?>