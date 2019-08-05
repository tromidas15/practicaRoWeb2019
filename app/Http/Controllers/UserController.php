<?php

namespace App\Http\Controllers;

use App\Helpers\ErrorCodes;
use App\Helpers\JWT;
use App\Models\User;
use App\Models\UserToken;
use App\Services\EmailService;
use App\Services\UserService;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Validator;
use App\Services\BaseService;
use Illuminate\Support\Facades\File;
/**
 * Class UserController
 *
 * @package App\Http\Controllers\v1
 */
class UserController extends Controller
{
    /** @var UserService */
    private $userService;
    private $BaseService;
    /**
     * UserController constructor.
     */
    public function __construct()
    {
        parent::__construct();

        $this->userService = new UserService();

        $this->BaseService = new BaseService();
    }

    /**
     * Login the user
     *
     * @param Request $request
     *
     * @return JsonResponse
     */
    public function login(Request $request)
    {
        if ($request->has('rememberToken')) {
            return $this->loginWithRememberToken($request);
        }

        try {
            /** @var Validator $validator */
            $validator = $this->userService->validateLoginRequest($request);

            if (!$validator->passes()) {
                return $this->returnError($validator->messages(), ErrorCodes::REQUEST_ERROR);
            }

            $user = $this->userService->loginUser($request->only('email', 'password'));

            if (!$user) {
                return $this->returnError('Invalid credentials!', ErrorCodes::REQUEST_ERROR);
            }

            $data = [
                'user' => $user,
                'token' => JWT::generateToken([
                    'id' => $user->id
                ])
            ];

            if ($request->has('remember')) {
                $data['rememberToken'] = $this->userService->generateRememberMeToken($user->id);
            }

            return $this->returnSuccess($data);
        } catch (\Exception $e) {
            return $this->returnError($e->getMessage(), ErrorCodes::FRAMEWORK_ERROR);
        }
    }

    /**
     * Login with remember token
     *
     * @param Request $request
     *
     * @return JsonResponse
     */
    private function loginWithRememberToken(Request $request)
    {
        try {
            /** @var \Illuminate\Validation\Validator $validator */
            $validator = $this->userService->validateTokenLoginRequest($request);

            if (!$validator->passes()) {
                return $this->returnError($validator->messages(), ErrorCodes::REQUEST_ERROR);
            }

            $rememberToken = $request->get('rememberToken');

            $user = $this->userService->loginUserWithRememberToken($rememberToken);

            if (!$user) {
                return $this->returnError('Invalid remember token!', ErrorCodes::REQUEST_ERROR);
            }

            $this->userService->updateRememberTokenValability($rememberToken);

            $data = [
                'user' => $user,
                'token' => JWT::generateToken([
                    'id' => $user->id
                ])
            ];

            return $this->returnSuccess($data);
        } catch (\Exception $e) {

            return $this->returnError($e->getMessage(), ErrorCodes::FRAMEWORK_ERROR);

        }
    }

    /**
     * Return user
     *
     * @return JsonResponse
     */
    public function getUser()
    {
        try {

            $user = Auth::user();

            return $this->returnSuccess($user);

        } catch (\Exception $e) {

            return $this->returnError($e->getMessage(), ErrorCodes::FRAMEWORK_ERROR);

        }
    }


    public function getAllUsers(Request $request)
    {
    	try {

    		$user = Auth::user();

    		if($user->type != 1){

	    		return $this->returnError("AccesDenied", ErrorCodes::FRAMEWORK_ERROR);;

	    	}

	    	$pagParams = $this->getPaginationParams($request);

	    	$usersList = User::where('id', '!=', null);

        	$paginationData = $this->getPaginationData($usersList, $pagParams['page'], $pagParams['limit']);

        	$usersList  = $usersList ->offset($pagParams['offset'])->limit($pagParams['limit'])->get();

	    	return($usersList ? $this->returnSuccess($usersList, $paginationData) : $text = "There are no products");


    	} catch(\Exception $e){

    		return $this->returnError($e->getMessage(), ErrorCodes::FRAMEWORK_ERROR);

    	}
    }


    public function createUser(Request $request)
    {
    	try{
    		$user = Auth::user();

    		switch($user->type){
    			case 0:

    				return json_encode("No permission");

    			case 1:

                    $validator = $this->userService->validate_register($request);

                    if(!$validator->passes()){
                        return $this->returnError($validator->messages(), ErrorCodes::REQUEST_ERROR);
                    }


			        $path = storage_path('image')."/";
			            
			        if($request->image){
			        	$img = $this->BaseService->processImage($path , $request->image);
			        }else{
			        	$img = null;
			        }


    				User::create([
    					'email' => $request->get('email'),
    					'password' => Hash::make($request->get('password')),
    					'name' => $request->get("name"),
    					'type' => $request->get('admin') ? User::TYPE_ADMIN : User::TYPE_NORMAL,
    					'picture' => $img,
    				]);

    				return $this->returnSuccess();
    		}
			
    	}catch(\Exception $e){
    		return $this->returnError($e->getMessage(), ErrorCodes::FRAMEWORK_ERROR);
    	}
    }


    public function updateUser(Request $request,$id)
    {
    	try{
    		$user = Auth::user();

    		$this->validate($request, [
					'email' => 'required|email|unique:users,email,'.$id.'id',
					'name'=>'required|alpha'
    			]);

    		switch($user->type){
    			case 0:
    				if($user->id == $id)
    				{
    					$user = User::where('id', $id)->first();

    					$user->email = $request ->get('email');
		    				if($request->get('password')){
		    					$user->password = Hash::make($request->get('password'));
		    				}
    					$user->name = $request->get("name");

    					$user->save();

    					return $this->returnSuccess();
    				}else{

    					return json_encode("You do not have permission to modify annother user");

    				}
    			case 1:
    				$user = User::findOrFail($id);

    				$user->email = $request ->get('email');

    				if($request->get('password')){
    					$user->password = Hash::make($request->get('password'));
    				}
    				
    				$user->name = $request->get("name");

    				if($request->image){

    					$lastImg = $user->picture;

    					$path = storage_path('image')."/".$lastImg;
            			File::delete($path);


    					$path = storage_path('image')."/";

    					$user->picture = $this->BaseService->processImage($path , $request->get('image'));

     				}

    				$user->type = $request->get('type') == 1 ? User::TYPE_ADMIN : User::TYPE_NORMAL;

    				$user->save();
    					
    				return $this->returnSuccess();
    		}
    	}catch(\Exception $e){
    		return $this->returnError($e->getMessage(), ErrorCodes::FRAMEWORK_ERROR);
    	}
    }

    public function deleteUser($id)
    {
    	try{
	    	$user = Auth::user();
	    	if($user->type == 1){
	    		$user = User::find($id);
	    		if(!$user){

					return json_encode("User do not exist");

	    		}else{

	    			 $lastImg = $user->picture;

    				$path = storage_path('image')."/".$lastImg;
            		File::delete($path);

	    			$user->delete();
	    			return $this->returnSuccess();

	    		}
	    	}else{

	    		return json_encode("You do not have permissions");
	    	}
    	}
    	catch(\Exception $e){

    		return $this->returnError($e->getMessage(), ErrorCodes::FRAMEWORK_ERROR);

    	}
    }
    /**
     * Logout the user, delete remember me token
     *
     * @param Request $request
     *
     * @return JsonResponse
     */
    public function logout(Request $request)
    {
        try {
            /** @var User $user */
            $user = Auth::user();

            if ($request->has('rememberToken')) 
            {
                UserToken::where('token', $request->get('rememberToken'))
                    ->where('user_id', $user->id)
                    ->where('type', UserToken::TYPE_REMEMBER)
                    ->delete();
            }

            return $this->returnSuccess();
        } catch (\Exception $e) {
            return $this->returnError($e->getMessage(), ErrorCodes::FRAMEWORK_ERROR);
        }
    }

    /**
     * Reset user password
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function forgotPassword(Request $request)
    {
    	$this->validate($request, [
			'email' => 'required|email',
    	]);

        try {

            $user = User::where('email', $request->get('email'))->first();
            if(!$user){
            	return json_encode("email invalid");
            }
            $user->forgot_code = str_random(6);
            $user->forgot_generated = Carbon::now()->format('Y-m-d H:i:s');

            $user->save();

            $emailService = new EmailService();

            $emailService->sendForgotPasswordCode($user);

            return $this->returnSuccess();
        } catch (\Exception $e) {
            return $this->returnError($e->getMessage(), ErrorCodes::FRAMEWORK_ERROR);
        }
    }

    /**
     * Change reset user password
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function changePassword(Request $request)
    {
    	 $this->validate($request, [
			'code' => 'required'
    	]);
        try {
           //email, code, valid, exists user, email

            $user = User::where('forgot_code', $request->get('code'))
                ->first();
            if (!$user) {
                return json_encode('Email or code is invalid');
            }

            if (Carbon::parse($user->forgot_generated)->addHour() < Carbon::now()) {
                return json_encode('Code has expired');
            }

            $user->password = Hash::make($request->get('password'));
            $user->forgot_code = '';

            $user->save();

            return $this->returnSuccess();
        } catch (\Exception $e) {
            return $this->returnError($e->getMessage(), ErrorCodes::FRAMEWORK_ERROR);
        }
    }
}
