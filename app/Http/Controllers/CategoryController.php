<?php

namespace App\Http\Controllers;

use App\Helpers\ErrorCodes;
use App\Models\Category;
use App\Models\Product;
use App\Services\CategoryService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\File;

/**
 * Class ApiController
 *
 * @package App\Http\Controllers
 */
class CategoryController extends Controller
{
    /** @var CategoryService */
    protected $categoryService;

    /**
     * CategoryController constructor.
     */
    public function __construct()
    {
        parent::__construct();

        $this->categoryService = new CategoryService();
    }

    /**
     * Create a category
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function create(Request $request)
    {
        try {
            /** @var \Illuminate\Validation\Validator $validator */
            $validator = $this->categoryService->validateCreateRequest($request);
            if (!$validator->passes()) {
                return $this->returnError($validator->messages(), ErrorCodes::REQUEST_ERROR);
            }

            $category = new Category();

            if ($request->has('parent_id')) {
                $parentCategory = Category::where('id', $request->get('parent_id'))
                    ->where('parent_id', Category::MAIN_CATEGORY)
                    ->first();

                if (!$parentCategory) {
                    return $this->returnError('errors.parent_id.invalid', ErrorCodes::REQUEST_ERROR);
                }

                $category->parent_id = $parentCategory->id;
            } else {
                $category->parent_id = Category::MAIN_CATEGORY;
            }

            $category->name = $request->get('name');

            $category->save();

            return $this->returnSuccess($category);
        } catch (\Exception $e) {
            return $this->returnError($e->getMessage(), ErrorCodes::FRAMEWORK_ERROR);
        }
    }

    public function subCategories()
    {
        try {
            $categories = Category::with('subCategories')
                ->where('parent_id', Category::MAIN_CATEGORY)
                ->get();

            return $this->returnSuccess($categories);
        } catch (\Exception $e) {
            return $this->returnError($e->getMessage(), ErrorCodes::FRAMEWORK_ERROR);
        }
    }

    /**
     * Get all categories
     *
     * @return \Illuminate\Http\JsonResponse
     */

    public function getAll(Request $request)
    {
        try {
            switch($request){
                case "GetParrents":

                        $parents = Category::where('parent_id', 0)->get();

                        return $this->returnSuccess($parents);

                break;
                default: 
                $pagParams = $this->getPaginationParams($request);

                $categories = Category::where('id', '!=', null);

                $paginationData = $this->getPaginationData($categories, $pagParams['page'], $pagParams['limit']);

                $categories = $categories->offset($pagParams['offset'])->limit($pagParams['limit'])->get();

                return $this->returnSuccess($categories, $paginationData);
            }

        } catch (\Exception $e) {
            return $this->returnError($e->getMessage(), ErrorCodes::FRAMEWORK_ERROR);
        }
    }

    /**
     * Get one category
     *
     * @param $id
     * @return \Illuminate\Http\JsonResponse
     */
    public function get(Request $request ,$id)
    {
        try {
        	
                $pagParams = $this->getPaginationParams($request);

                $category = Category::where('parent_id', $id);

                $paginationData = $this->getPaginationData($category, $pagParams['page'], $pagParams['limit']);

                $categories = $category->offset($pagParams['offset'])->limit($pagParams['limit'])->get();
            
                return $this->returnSuccess($categories, $paginationData);

            if (!$category) {
                return $this->returnError('errors.category.not_found', ErrorCodes::NOT_FOUND_ERROR);
            }

            return $this->returnSuccess($category);

        } catch (\Exception $e) {
            return $this->returnError($e->getMessage(), ErrorCodes::FRAMEWORK_ERROR);
        }
    }

    /**
     * Update a category
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function update($id, Request $request)
    {
        try {
            
            $category = Category::where('id', $id)->first();

            if (!$category) {
                return $this->returnError('errors.category.not_found', ErrorCodes::NOT_FOUND_ERROR);
            }

            /** @var \Illuminate\Validation\Validator $validator */
            $validator = $this->categoryService->validateUpdateRequest($request);

            if (!$validator->passes()) {

                return $this->returnError($validator->messages(), ErrorCodes::REQUEST_ERROR);

            }

            if ($request->has('parent_id')) {

                $parentCategory = Category::where('id', $request->get('parent_id'))
                    ->where('parent_id', Category::MAIN_CATEGORY)
                    ->first();

                if (!$parentCategory) {

                    return $this->returnError('errors.parent_id.invalid', ErrorCodes::REQUEST_ERROR);

                }

                $category->parent_id = $parentCategory->id;

            }else{

                $category->parent_id = Category::MAIN_CATEGORY;

            }

            $category->name = $request->get('name');

            $category->save();

            return $this->returnSuccess($category);

        } catch (\Exception $e) {

            return $this->returnError($e->getMessage(), ErrorCodes::FRAMEWORK_ERROR);

        }
    }

    /**
     * Delete a category
     *
     * @param $id
     * @return \Illuminate\Http\JsonResponse
     */
    public function delete($id)
    {
        try {

        $check_if_main = Category::findOrFail($id);
        if($check_if_main->parent_id !== 0)
        {
            Category::find($id)->delete();
            return $this->returnSuccess();
        }else{
            $check_if_has_sub = Category::select('*')
                                ->where("parent_id", "=", $id)
                                ->get();
            if(count($check_if_has_sub) > 0)
            {
                $main_category = $check_if_main->id;
                return $this->returnError("Has Children", ErrorCodes::HAS_CHIELDS_CATEGORIES);
            }else{
                Category::find($id)->delete();
                return $this->returnSuccess();
            }
        }


        } catch (\Exception $e) {
            return $this->returnError($e->getMessage(), ErrorCodes::FRAMEWORK_ERROR);
        }
    }

    public function deleteAllSubsAndMain($id)
    {
        try {
            $allsubs = Category::where('parent_id', $id)->get();
            if($allsubs){
                foreach($allsubs as $sub){
                    $idDel = $sub->id;
                    $products = Product::where('category_id', $idDel);

                    $productsAll = $products->get();

                    foreach ($productsAll  as $img) {

                        $path = storage_path('image')."/".$img->photo;
                        File::delete($path);

                    }

                    $products ->delete();

                    Category::find($idDel)->delete();
                }
            }
                $products = Product::where('category_id', $id);
                $productsAll = $products->get();
                    foreach ($productsAll as $img) {

                        $path = storage_path('image')."/".$img->photo;
                        File::delete($path);
                        return $path;
                        break;
                    }
                    

                $products->delete();

                Category::where('parent_id', $id)->delete();

                Category::find($id)->delete();
            
            return $this->returnSuccess();
        } catch (\Exception $e) {
            return $this->returnError($e->getMessage(), ErrorCodes::FRAMEWORK_ERROR);
        }
    }
}
