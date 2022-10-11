<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Exception;
use Illuminate\Support\Facades\Validator;
use App\Traits\ApiResponser;
use Illuminate\Support\Facades\Config;
use InvalidArgumentException;
use Symfony\Component\HttpFoundation\Response;
use App\Services\ChannelService;
use App\Models\Users;
use Illuminate\Support\Facades\Log;
use App\Repositories\ChannelRepository;
use App\Models\CustomerPayment;
use App\AppValidator\MakePaymentValidator;
use App\AppValidator\PaymentStatusValidator;
use App\AppValidator\AgentWalletPaymentValidator;
use App\AppValidator\AgentPaymentStatusValidator;
use App\Jobs\TestingEmailJob;
use Razorpay\Api\Api;
use Razorpay\Api\Errors\SignatureVerificationError;
Use hash_hmac;
use JWTAuth;

class ChannelController extends Controller
{
    use ApiResponser;
    protected $channelService;
    protected $channelRepository;  
    protected $customerPayment;
    protected $makePaymentValidator;
    protected $paymentStatusValidator;
    protected $agentWalletPaymentValidator;
    protected $agentPaymentStatusValidator;
  
    public function __construct(ChannelService $channelService,ChannelRepository $channelRepository,CustomerPayment $customerPayment,AgentWalletPaymentValidator $agentWalletPaymentValidator,AgentPaymentStatusValidator $agentPaymentStatusValidator,MakePaymentValidator $makePaymentValidator,PaymentStatusValidator $paymentStatusValidator)
        {
            $this->channelService = $channelService;
            $this->channelRepository = $channelRepository;  
            $this->customerPayment = $agentWalletPaymentValidator;
            $this->agentWalletPaymentValidator = $agentWalletPaymentValidator;
            $this->agentPaymentStatusValidator = $agentPaymentStatusValidator;
            $this->makePaymentValidator = $makePaymentValidator;
            $this->paymentStatusValidator = $paymentStatusValidator;
        }

    public function testingEmail(Request $request) {

            $to = $request['email'];
            $name = $request['name'];
    
            $res= TestingEmailJob::dispatch($to, $name);

            return $this->successResponse($res,Config::get('constants.RECORD_ADDED'),Response::HTTP_CREATED);
 
          }

    public function storeGWInfo(Request $request)
        {
          try {
           $response = $this->channelService->storeGWInfo($request); 
            return $this->successResponse($response,Config::get('constants.RECORD_ADDED'),Response::HTTP_CREATED);
        }
          catch (Exception $e) {
            return $this->errorResponse($e->getMessage(),Response::HTTP_NOT_FOUND);
        }       
    }
    public function sendSms(Request $request)
    {
          try {
           $response = $this->channelService->sendSms($request); 
            return $this->successResponse($response,Config::get('constants.RECORD_ADDED'),Response::HTTP_CREATED);
        }
        catch (Exception $e) {
            return $this->errorResponse($e->getMessage(),Response::HTTP_NOT_FOUND);
          }       
    }
    public function sendSmsTicket(Request $request)
    {
          try {
           $response = $this->channelService->sendSmsTicket($request); 
            return $this->successResponse($response,Config::get('constants.RECORD_ADDED'),Response::HTTP_CREATED);
        }
        catch (Exception $e) {
            return $this->errorResponse($e->getMessage(),Response::HTTP_NOT_FOUND);
          }       
    }
    public function smsDeliveryStatus(Request $request)
    {
          try {
           $response = $this->channelService->smsDeliveryStatus($request); 
            return $this->successResponse($response,Config::get('constants.SMS_DELIVERED'),Response::HTTP_OK);
        }
        catch (Exception $e) {
            return $this->errorResponse($e->getMessage(),Response::HTTP_NOT_FOUND);
          }       
    }

    public function sendEmail(Request $request)
    {
        try {
            $response = $this->channelService->sendEmail($request); 
             return $this->successResponse($response,Config::get('constants.RECORD_ADDED'),Response::HTTP_CREATED);
         }
         catch (Exception $e) {
             return $this->errorResponse($e->getMessage(),Response::HTTP_NOT_FOUND);
           }   
    }

    public function sendEmailTicket(Request $request)
    {
        try {
            $response = $this->channelService->sendEmailTicket($request); 
             return $this->successResponse($response,Config::get('constants.RECORD_ADDED'),Response::HTTP_CREATED);
         }
         catch (Exception $e) {
             return $this->errorResponse($e->getMessage(),Response::HTTP_NOT_FOUND);
           }   
    }
    
/**
 * @OA\Post(
 *     path="/api/MakePayment",
 *     tags={"MakePayment API"},
 *     description="generating razorpay order Id",
 *     summary="generating razorpay order Id",
 *     @OA\Parameter(
 *          name="busId",
 *          description="BusId",
 *          required=true,
 *          in="query",
 *          @OA\Schema(
 *              type="integer",
 *              example="254"
 *          )
 *      ),  
 *     @OA\Parameter(
 *          name="sourceId",
 *          description="sourceId",
 *          required=true,
 *          in="query",
 *          @OA\Schema(
 *              type="integer",
 *              example="82"
 *          )
 *      ), 
 *     @OA\Parameter(
 *          name="destinationId",
 *          description="destinationId",
 *          required=true,
 *          in="query",
 *          @OA\Schema(
 *              type="integer",
 *              example="53"
 *          )
 *      ),        
 *     @OA\Parameter(
 *          name="transaction_id",
 *          description="customer transaction id against booking",
 *          required=true,
 *          in="query",
 *          @OA\Schema(
 *              type="string",
 *              example="20220404141311561229"
 *          )
 *      ),
 *     @OA\Parameter(
 *          name="seatIds[]",
 *          description="Seat ids",
 *          required=true,
 *          in="query",
 *          @OA\Schema(
 *          type="array",
 *          @OA\Items(
 *              type="integer",
 *              format="3972",
 *              example=3972,
 *              )
 *          )
 *      ),
 *     @OA\Parameter(
 *          name="entry_date",
 *          description="journey date",
 *          required=true,
 *          in="query",
 *          @OA\Schema(
 *              type="string",
 *              example="06-04-2022"
 *          )
 *      ),
 *     @OA\Parameter(
 *          name="customer_gst_status",
 *          description="customer gst required or not",
 *          required=false,
 *          in="query",
 *          @OA\Schema(
 *              type="boolean",
 *              example="true"
 *          )
 *      ),
 *     @OA\Parameter(
 *          name="customer_gst_number",
 *          description="customer gst number",
 *          required=false,
 *          in="query",
 *          @OA\Schema(
 *              type="string",
 *              example="2323232323"
 *          )
 *      ),
 *     @OA\Parameter(
 *          name="customer_gst_business_name",
 *          description="customer gst business name",
 *          required=false,
 *          in="query",
 *          @OA\Schema(
 *              type="string",
 *              example="Bob"
 *          )
 *      ),
 *     @OA\Parameter(
 *          name="customer_gst_business_email",
 *          description="customer gst business email",
 *          required=false,
 *          in="query",
 *          @OA\Schema(
 *              type="string",
 *              example="example@gmail.com"
 *          )
 *      ),
 *     @OA\Parameter(
 *          name="customer_gst_business_address",
 *          description="customer gst business address",
 *          required=false,
 *          in="query",
 *          @OA\Schema(
 *              type="string",
 *              example="example"
 *          )
 *      ),
 *  @OA\Response(response="201", description="Order Id generated Successfully"),
 *  @OA\Response(response=206, description="validation error"),
 *  @OA\Response(response=400, description="Bad request"),
 *  @OA\Response(response=401, description="Unauthorized access"),
 *  @OA\Response(response=404, description="No record found"),
 *  @OA\Response(response="406", description="Seats already booked"),
 *  @OA\Response(response=500, description="Internal server error"),
 *  @OA\Response(response=502, description="Bad gateway"),
 *  @OA\Response(response=503, description="Service unavailable"),
 *  @OA\Response(response=504, description="Gateway timeout"),
 *     security={{ "apiAuth": {} }}
 * )
 * 
 */

      public function makePayment(Request $request)
    {   
        $data = $request->all();
        $token = JWTAuth::getToken();
        $user = JWTAuth::toUser($token); 
        $clientRole = $user->role_id;

        $makePaymentValidation = $this->makePaymentValidator->validate($data);
  
        if ($makePaymentValidation->fails()) {
        $errors = $makePaymentValidation->errors();
        return $this->errorResponse($errors->toJson(),Response::HTTP_PARTIAL_CONTENT);
        } 
        try {
            $response = $this->channelService->makePayment($request,$clientRole);
           // Log::info($response);

            if(isset($response['razorpay_order_id'])){

                return $this->successResponse($response,Config::get('constants.ORDERID_CREATED'),Response::HTTP_CREATED);  
    
             }elseif($response=='BUS_SEIZED'){
    
                return $this->errorResponse(Config::get('constants.BUS_SEIZED'),Response::HTTP_OK);
    
             }elseif($response=='SEAT UN-AVAIL'){
    
                return $this->successResponse($response,Config::get('constants.HOLD'),Response::HTTP_OK);
                
             }elseif($response=='BUS_CANCELLED'){
    
                return $this->errorResponse(Config::get('constants.BUS_CANCELLED'),Response::HTTP_OK); 
                
            }elseif($response=='SEAT_BLOCKED'){
    
                return $this->errorResponse(Config::get('constants.SEAT_BLOCKED'),Response::HTTP_OK); 
                
            }else{
                return $this->errorResponse($response,Response::HTTP_OK); 
            }          
        }
        catch (Exception $e) {
             return $this->errorResponse($e->getMessage(),Response::HTTP_NOT_FOUND);
        }        
    }

    

    public function BookDolphinSeat(Request $request){

        $token = JWTAuth::getToken();
        $user = JWTAuth::toUser($token); 
        $clientRole = $user->role_id;

        try {
            $response = $this->channelService->BookDolphinSeat($request,$clientRole); 
           
            return $this->successResponse($response,Config::get('constants.RECORD_FETCHED'),Response::HTTP_OK);
           
         }
         catch (Exception $e) {
             return $this->errorResponse($e->getMessage(),Response::HTTP_NOT_FOUND);
           }  

    }

    public function BlockDolphinSeat(Request $request){

        $token = JWTAuth::getToken();
        $user = JWTAuth::toUser($token); 
        $clientRole = $user->role_id;

        try {
            $response = $this->channelService->BlockDolphinSeat($request,$clientRole); 
           
            return $this->successResponse($response,Config::get('constants.RECORD_FETCHED'),Response::HTTP_OK);
           
         }
         catch (Exception $e) {
             return $this->errorResponse($e->getMessage(),Response::HTTP_NOT_FOUND);
           }  

    }

    public function checkSeatStatus(Request $request)
    {   
        $token = JWTAuth::getToken();
        $user = JWTAuth::toUser($token); 
        $clientRole = $user->role_id;
        $clientId = $user->id;
        try {
            $response = $this->channelService->checkSeatStatus($request,$clientRole,$clientId); 
            if($response == 'SEAT UN-AVAIL'){
                return $this->successResponse($response,Config::get('constants.HOLD'),Response::HTTP_OK);
            }
            else{
                return $this->successResponse($response,Config::get('constants.ORDERID_CREATED'),Response::HTTP_CREATED);
            }
         }
         catch (Exception $e) {
            Log::info($e->getMessage());
             return $this->errorResponse($e->getMessage(),Response::HTTP_NOT_FOUND);
           }  
    }


    
/**
 * @OA\POST(
 *     path="/api/PaymentStatus",
 *     tags={"PaymentStatus Success/Failure API"},
 *     summary="payment status success or failure check and on success send sms/email Ticket to customer,CMO,Admin",
 *     @OA\Parameter(
 *          name="transaction_id",
 *          description="customer transaction id against booking",
 *          required=true,
 *          in="query",
 *          @OA\Schema(
 *              type="string",
 *              example="20220404141311561229"
 *          )
 *      ),
 *     @OA\Parameter(
 *          name="razorpay_payment_id",
 *          description="razorpay payment id",
 *          required=true,
 *          in="query",
 *          @OA\Schema(
 *              type="string",
 *              example="pay_JT3MO4UrPGw9aQ"
 *          )
 *      ),
 *     @OA\Parameter(
 *          name="razorpay_order_id",
 *          description="razorpay order id",
 *          required=true,
 *          in="query",
 *          @OA\Schema(
 *              type="string",
 *              example="order_JT3MJLjsb3Lj2K"
 *          )
 *      ),
 *     @OA\Parameter(
 *          name="razorpay_signature",
 *          description="razorpay signature",
 *          required=true,
 *          in="query",
 *          @OA\Schema(
 *              type="string",
 *              example="04e6ca7c037d5f6bb8fefb8f87a20ae4f5461fabd2d86232a8656378dce8daf8"
 *          )
 *      ),
 *  @OA\Response(response="200", description="Payment successfully done"),
 *  @OA\Response(response=206, description="validation error"),
 *  @OA\Response(response=400, description="Bad request"),
 *  @OA\Response(response=401, description="Unauthorized access"),
 *  @OA\Response(response="402", description="Payment required"),
 *  @OA\Response(response=404, description="No record found"),
 *  @OA\Response(response=500, description="Internal server error"),
 *  @OA\Response(response=502, description="Bad gateway"),
 *  @OA\Response(response=503, description="Service unavailable"),
 *  @OA\Response(response=504, description="Gateway timeout"),
 *     security={{ "apiAuth": {} }}
 * )
 */
public function pay(Request $request){

    $data = $request->all();
    $paymentStatusValidation = $this->paymentStatusValidator->validate($data);

    if ($paymentStatusValidation->fails()) {
     
    $errors = $paymentStatusValidation->errors();
    return $this->errorResponse($errors->toJson(),Response::HTTP_PARTIAL_CONTENT);
    }  
        try{
            $response = $this->channelService->pay($request);
            //return $response; 
            If($response == 'Payment Done'){
                return $this->successResponse(Config::get('constants.PAYMENT_DONE'),Response::HTTP_OK);
            }
            else{
                return $this->errorResponse(Config::get('constants.PAYMENT_FAILED'),Response::HTTP_PAYMENT_REQUIRED);
            }  
         }
        catch (Exception $e) {
            Log::info($e->getMessage());
            return $this->errorResponse($e->getMessage(),Response::HTTP_NOT_FOUND);
          }     
}

 public function UpdateAdjustStatus(Request $request){
    $data = $request->all();
    $paymentStatusValidation = $this->paymentStatusValidator->validate($data);

    if ($paymentStatusValidation->fails()) {
     
    $errors = $paymentStatusValidation->errors();
    return $this->errorResponse($errors->toJson(),Response::HTTP_PARTIAL_CONTENT);
    }  
        try{
            $response = $this->channelService->UpdateAdjustStatus($request);
            //return $response; 
            If($response == 'Payment Done'){
                return $this->successResponse(Config::get('constants.PAYMENT_DONE'),Response::HTTP_OK);
            }
            else{
                return $this->errorResponse(Config::get('constants.PAYMENT_FAILED'),Response::HTTP_PAYMENT_REQUIRED);
            }  
         }
        catch (Exception $e) {
            Log::info($e->getMessage());
            return $this->errorResponse($e->getMessage(),Response::HTTP_NOT_FOUND);
          }     


 }   
  
  public function RazorpayWebhook(){
    
    $post = file_get_contents('php://input');
    $res = json_decode($post);
    $response=$res->payload->payment->entity; 
    
    //$myfile = fopen("razorpaywebhook.txt", "a");
   // fwrite($myfile, '\n'.$response->status."--".$response->id."--".$response->order_id."--".$response->error_description.'\n');
    //fwrite($myfile, '\n Event - '.$res->event);
    //fwrite($myfile, '\n Account ID - '.$res->account_id.'\n');
   // fclose($myfile);
    
    $razorpay_status_updated_at= date("Y-m-d H:i:s");
    $this->customerPayment->where('order_id', $response->order_id)
                          ->update(['razorpay_status' => $response->status,
                                    'razorpay_status_updated_at' => $razorpay_status_updated_at, 'failed_reason' => $response->error_description]);  
  }
/**
 * @OA\Post(
 *     path="/api/AgentWalletPayment",
 *     tags={"AgentWalletPayment API"},
 *     description="Agent Wallet Payment to book tickets for customer",
 *     summary="Agent Wallet Payment to book tickets for customer",
 *     @OA\Parameter(
 *          name="user_id",
 *          description="user id",
 *          required=true,
 *          in="query",
 *          @OA\Schema(
 *              type="integer"
 *          )
 *      ), 
 *     @OA\Parameter(
 *          name="user_name",
 *          description="user name",
 *          required=true,
 *          in="query",
 *          @OA\Schema(
 *              type="string"
 *          )
 *      ), 
 *     @OA\Parameter(
 *          name="busId",
 *          description="BusId",
 *          required=true,
 *          in="query",
 *          @OA\Schema(
 *              type="integer"
 *          )
 *      ),  
 *     @OA\Parameter(
 *          name="sourceId",
 *          description="sourceId",
 *          required=true,
 *          in="query",
 *          @OA\Schema(
 *              type="integer"
 *          )
 *      ), 
 *     @OA\Parameter(
 *          name="destinationId",
 *          description="destinationId",
 *          required=true,
 *          in="query",
 *          @OA\Schema(
 *              type="integer"
 *          )
 *      ),        
 *     @OA\Parameter(
 *          name="transaction_id",
 *          description="customer transaction id against booking",
 *          required=true,
 *          in="query",
 *          @OA\Schema(
 *              type="string"
 *          )
 *      ),
 *     @OA\Parameter(
 *          name="seatIds[]",
 *          description="Seat ids",
 *          required=true,
 *          in="query",
 *          @OA\Schema(
 *          type="array",
 *          @OA\Items(
 *              type="integer",
 *              format="37",
 *              example=37,
 *              )
 *          )
 *      ),
 *     @OA\Parameter(
 *          name="entry_date",
 *          description="journey date",
 *          required=true,
 *          in="query",
 *          @OA\Schema(
 *              type="string",
 *          )
 *      ),
 *     @OA\Parameter(
 *          name="applied_comission",
 *          description="applied comission",
 *          required=true,
 *          in="query",
 *          @OA\Schema(
 *              type="number"
 *          )
 *      ),
 *  @OA\Response(response="201", description="Wallet Payment Successful"),
 *  @OA\Response(response=206, description="validation error"),
 *  @OA\Response(response=400, description="Bad request"),
 *  @OA\Response(response=401, description="Unauthorized access"),
 *  @OA\Response(response=404, description="No record found"),
 *  @OA\Response(response="406", description="seats already booked"),
 *  @OA\Response(response=500, description="Internal server error"),
 *  @OA\Response(response=502, description="Bad gateway"),
 *  @OA\Response(response=503, description="Service unavailable"),
 *  @OA\Response(response=504, description="Gateway timeout"),
 *     security={{ "apiAuth": {} }}
 * )
 * 
 */

  public function walletPayment(Request $request)
  {   
      $data = $request->all();
    //   $token = JWTAuth::getToken();
    //   $user = JWTAuth::toUser($token); 
    //   $clientRole = $user->role_id;
      $walletPaymentValidation = $this->agentWalletPaymentValidator->validate($data);

      if ($walletPaymentValidation->fails()) {
      $errors = $walletPaymentValidation->errors();
      return $this->errorResponse($errors->toJson(),Response::HTTP_PARTIAL_CONTENT);
      }  
      try {
         // $response = $this->channelService->walletPayment($request,$clientRole); 
         $response = $this->channelService->walletPayment($request); 

         if(isset($response['notifications'])){

            return $this->successResponse($response,Config::get('constants.WALLET_PAYMENT_SUCESS'),Response::HTTP_CREATED);  

         }elseif($response=='BUS_SEIZED'){

            return $this->errorResponse(Config::get('constants.BUS_SEIZED'),Response::HTTP_OK);

         }elseif($response=='SEAT UN-AVAIL'){

            return $this->successResponse($response,Config::get('constants.HOLD'),Response::HTTP_OK);
            
         }elseif($response=='BUS_CANCELLED'){

            return $this->errorResponse(Config::get('constants.BUS_CANCELLED'),Response::HTTP_OK); 
            
        }elseif($response=='SEAT_BLOCKED'){

            return $this->errorResponse(Config::get('constants.SEAT_BLOCKED'),Response::HTTP_OK); 
            
        }else{
            return $this->errorResponse($response,Response::HTTP_OK); 
        }
          
             
        }
        catch (Exception $e) {
             return $this->errorResponse($e->getMessage(),Response::HTTP_NOT_FOUND);
        }

  }
 /**
 * @OA\POST(
 *     path="/api/AgentPaymentStatus",
 *     tags={"Agent PaymentStatus Success/Failure API"},
 *     summary="payment status success or failure check and on success send sms/email Ticket to customer,CMO,Admin",
 *     @OA\Parameter(
 *          name="transaction_id",
 *          description="customer transaction id against booking",
 *          required=true,
 *          in="query",
 *          @OA\Schema(
 *              type="string",
 *              example="20220404141311561229"
 *          )
 *      ),
 *     @OA\Parameter(
 *          name="customer_comission",
 *          description="customer comission",
 *          required=false,
 *          in="query",
 *          @OA\Schema(
 *              type="number",
 *              example="10"
 *          )
 *      ),
 *  @OA\Response(response="200", description="Payment successfully done"),
 *  @OA\Response(response=206, description="validation error"),
 *  @OA\Response(response=400, description="Bad request"),
 *  @OA\Response(response=401, description="Unauthorized access"),
 *  @OA\Response(response="402", description="Payment required"),
 *  @OA\Response(response=404, description="No record found"),
 *  @OA\Response(response=500, description="Internal server error"),
 *  @OA\Response(response=502, description="Bad gateway"),
 *  @OA\Response(response=503, description="Service unavailable"),
 *  @OA\Response(response=504, description="Gateway timeout"),
 *     security={{ "apiAuth": {} }}
 * )
 */

  public function agentPaymentStatus(Request $request){

    $data = $request->all();
    $paymentStatusValidation = $this->agentPaymentStatusValidator->validate($data);

    if ($paymentStatusValidation->fails()) {
    $errors = $paymentStatusValidation->errors();
    return $this->errorResponse($errors->toJson(),Response::HTTP_PARTIAL_CONTENT);
    }  
    try{  
        $response = $this->channelService->agentPaymentStatus($request); 
         If($response == 'Payment Done'){
             return $this->successResponse(Config::get('constants.PAYMENT_DONE'),Response::HTTP_OK);
         }
         else{
            return $this->errorResponse(Config::get('constants.PAYMENT_FAILED'),Response::HTTP_PAYMENT_REQUIRED);
        }  
     }
    catch (Exception $e) {
        return $this->errorResponse($e->getMessage(),Response::HTTP_NOT_FOUND);
      }     
}
///////////////generateFailedTicket///////////////////////////////

public function generateFailedTicket(Request $request)
    { 

        try {

            $response = $this->channelService->generateFailedTicket($request); 


            if($response == 'payment_not_done'){
                return $this->errorResponse(Config::get('constants.PAYMENT_NOT_DONE'),Response::HTTP_OK);
            }
            else{
                return $this->successResponse($response,Config::get('constants.TICKET_REGENERATED'),Response::HTTP_CREATED);
            }
         }
        catch (Exception $e) {
         
             return $this->errorResponse($e->getMessage(),Response::HTTP_NOT_FOUND);
        }     
    }

    public function testing(){
        

        $key = $this->channelRepository->getRazorpayKey();
        $secretKey = $this->channelRepository->getRazorpaySecret();

        $api = new Api($key, $secretKey); 

        $res= $api->order->fetch("order_JP4kBCFQ3CYcpI")->payments();
        $payment = $api->payment->fetch($res->items[0]->id);

        //Log::info($res->items[0]->email);
        
        $paymentStatus = $payment->status;

        //Log::info($paymentStatus);

        //return $payment;
    }

}
