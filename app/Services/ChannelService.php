<?php

namespace App\Services;
use App\Models\CustomerNotification;
use App\Repositories\ChannelRepository;
use App\Models\CustomerPayment;
use App\Models\TicketPrice;
use App\Models\BusCancelled;
use App\Models\BusSeats;
use App\Models\Location;
use Exception;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Log;
use App\Services\ViewSeatsService;
use InvalidArgumentException;
use App\Repositories\CommonRepository;
use Illuminate\Support\Arr;
use App\Transformers\DolphinTransformer;
use Carbon\Carbon;



class ChannelService
{
    protected $channelRepository; 
    protected $viewSeatsService; 
    protected $commonRepository;
    protected $dolphinTransformer;


    public function __construct(ChannelRepository $channelRepository,ViewSeatsService $viewSeatsService,CommonRepository $commonRepository,DolphinTransformer $dolphinTransformer)
    {
        $this->viewSeatsService = $viewSeatsService;
        $this->channelRepository = $channelRepository;
        $this->commonRepository = $commonRepository;
        $this->dolphinTransformer = $dolphinTransformer;

    }
    public function storeGWInfo($data)
    {
        try {
            $gwInfo = $this->channelRepository->storeGWInfo($data);

        } catch (Exception $e) {
            Log::info($e->getMessage());
            throw new InvalidArgumentException(Config::get('constants.INVALID_ARGUMENT_PASSED'));
        }
        return $gwInfo;
    }   
    public function sendSms($data)
    {
        try {
            $sendSms = $this->channelRepository->sendSms($data);

        } catch (Exception $e) {
            Log::info($e->getMessage());
            throw new InvalidArgumentException(Config::get('constants.INVALID_ARGUMENT_PASSED'));
        }
        return $sendSms;
    }   
    public function sendSmsTicket($data)
    {
        try {
            $sendSmsTicket = $this->channelRepository->sendSmsTicket($data);

        } catch (Exception $e) {
            Log::info($e->getMessage());
            throw new InvalidArgumentException(Config::get('constants.INVALID_ARGUMENT_PASSED'));
        }
        return $sendSmsTicket;
    }   
    public function smsDeliveryStatus($data)
    {
        try {
            $deliveryStatus = $this->channelRepository->smsDeliveryStatus($data);

        } catch (Exception $e) {
            Log::info($e->getMessage());
            throw new InvalidArgumentException(Config::get('constants.INVALID_ARGUMENT_PASSED'));
        }
        return $deliveryStatus;
    }   

    public function sendEmail($data)
    {
        try {
            $sendEmail = $this->channelRepository->sendEmail($data);

        } catch (Exception $e) {
            Log::info($e->getMessage());
            throw new InvalidArgumentException(Config::get('constants.INVALID_ARGUMENT_PASSED'));
        }
        return $sendEmail;
    }   
    
    public function sendEmailTicket($data)
    {
        try {
            $sendEmail = $this->channelRepository->sendEmailTicket($data);

        } catch (Exception $e) {
            Log::info($e->getMessage());
            throw new InvalidArgumentException(Config::get('constants.INVALID_ARGUMENT_PASSED'));
        }
        return $sendEmail;
    } 
    
    ///////////////////////////////////////////////////////////////////
    public function makePayment($request)
    {
        try {
                $seatHold = Config::get('constants.SEAT_HOLD_STATUS');
                $busId = $request['busId']; 
                $sourceId = $request['sourceId'];
                $destinationId = $request['destinationId'];  
                $transationId = $request['transaction_id']; 
                $seatIds = $request['seatIds'];
                $entry_date = $request['entry_date'];
                $entry_date = date("Y-m-d", strtotime($entry_date));


               $records = $this->channelRepository->getBookingRecord($transationId);

               $origin=$records[0]->origin;

             
               if($records[0]->payable_amount == 0.00){
                $amount = $records[0]->total_fare;
                }else{
                    $amount = $records[0]->payable_amount;
                }


              if($origin=='ODBUS') {

                 ///////////////////////cancelled bus recheck////////////////////////
                    $routeDetails = TicketPrice::where('source_id', $sourceId)
                    ->where('destination_id', $destinationId)
                    ->where('bus_id', $busId)
                    ->where('status','1')
                    ->get(); 

                     /////////////seize time recheck////////////////////////
            
                    //$CurrentDateTime = "2022-09-09 07:46:35";
                    $CurrentDateTime = Carbon::now();//->toDateTimeString();
                    if(isset($routeDetails[0])){
                    $seizedTime = $routeDetails[0]->seize_booking_minute;
                    $depTime = date("H:i:s", strtotime($routeDetails[0]->dep_time)); 
                    
                    $depDateTime = Carbon::createFromFormat('Y-m-d H:i:s', $entry_date.' '.$depTime);
                    $diff_in_minutes = $depDateTime->diffInMinutes($CurrentDateTime);
                        if($depDateTime>=$CurrentDateTime){
                            $diff_in_minutes = $depDateTime->diffInMinutes($CurrentDateTime);
                        }else{
                            $diff_in_minutes = 0;
                        }
                        if($seizedTime > $diff_in_minutes){
                            return "BUS_SEIZED";
                        }
                    }

                    $startJDay = $routeDetails[0]->start_j_days;
                    $ticketPriceId = $routeDetails[0]->id;

                    switch($startJDay){
                    case(1):
                        $new_date = $entry_date;
                        break;
                    case(2):
                        $new_date = date('Y-m-d', strtotime('-1 day', strtotime($entry_date)));
                        break;
                    case(3):
                        $new_date = date('Y-m-d', strtotime('-2 day', strtotime($entry_date)));
                        break;
                    }   
                    $cancelledBus = BusCancelled::where('bus_id', $busId)
                                            ->where('status', '1')
                                            ->with(['busCancelledDate' => function ($bcd) use ($new_date){
                                            $bcd->where('cancelled_date',$new_date);
                                            }])->get(); 

                    $busCancel = $cancelledBus->pluck('busCancelledDate')->flatten();

                    if(isset($busCancel) && $busCancel->isNotEmpty()){
                    return "BUS_CANCELLED";
                    }
                    /////////////////seat block recheck////////////////////////
                    $blockSeats = BusSeats::where('operation_date', $entry_date)
                                        ->where('type',2)
                                        ->where('bus_id',$busId)
                                        ->where('status',1)
                                        ->where('ticket_price_id',$ticketPriceId)
                                        ->whereIn('seats_id',$seatIds)
                                        ->get();
                                                        
                    if(isset($blockSeats) && $blockSeats->isNotEmpty()){
                    return "SEAT_BLOCKED";
                    }
                    $bookedHoldSeats = $this->viewSeatsService->checkBlockedSeats($request);

                    $intersect = collect($bookedHoldSeats)->intersect($seatIds);                   

              } 

              else if($origin=='DOLPHIN') {

                $intersect=[];

                $res= $this->dolphinTransformer->BlockSeat($records);
                if($res['Status']!=1){
                    return  $res['Message'];
                }
              }
              
        if($origin=='ODBUS' || ($origin=='DOLPHIN' && $res['Status']==1)) {

                       

            /////////////// calculate customer GST  (customet gst = (owner fare + service charge) - Coupon discount)

            $masterSetting=$this->commonRepository->getCommonSettings('1'); // 1 stands for ODBSU is from user table to get maste setting data

            if($request['customer_gst_status']==true || $request['customer_gst_status']=='true'){
    
                $update_customer_gst['customer_gst_status']=1;
                $update_customer_gst['customer_gst_number']=$request['customer_gst_number'];
                $update_customer_gst['customer_gst_business_name']=$request['customer_gst_business_name'];
                $update_customer_gst['customer_gst_business_email']=$request['customer_gst_business_email'];
                $update_customer_gst['customer_gst_business_address']=$request['customer_gst_business_address'];
    
                $update_customer_gst['customer_gst_percent']=$masterSetting[0]->customer_gst;
    
                $customer_gst_amount= round((( ($records[0]->owner_fare+$records[0]->odbus_charges) - $records[0]->coupon_discount ) *$masterSetting[0]->customer_gst)/100,2);
    
                $amount = round($amount+$customer_gst_amount,2);
                $update_customer_gst['payable_amount']=$amount;
                        
                $update_customer_gst['customer_gst_amount']=$customer_gst_amount;
    
                }else{
    
                $amount = round($amount - $records[0]->customer_gst_amount,2);
    
                $update_customer_gst['customer_gst_status']=0;
                $update_customer_gst['customer_gst_number']=null;
                $update_customer_gst['customer_gst_business_name']=null;
                $update_customer_gst['customer_gst_business_email']=null;
                $update_customer_gst['customer_gst_business_address']=null;
                $update_customer_gst['customer_gst_percent']=0;                    
                $update_customer_gst['customer_gst_amount']=0;
                $update_customer_gst['payable_amount']=$amount;    
                }
    
                $this->channelRepository->updateCustomerGST($update_customer_gst,$transationId);
    
    
                if($records && $records[0]->status == $seatHold){
                    $key= $this->channelRepository->getRazorpayKey();
    
                    $bookingId = $records[0]->id;   
                    $name = $records[0]->users->name;
                    $receiptId = 'rcpt_'.$transationId;
    
                    $GetOrderId=$this->channelRepository->UpdateCustomPayment($receiptId, $amount ,$name, $bookingId);
                        
                    $data = array(
                        'name' => $records[0]->users->name,
                        'amount' => $amount,
                        'key' => $key,
                        'razorpay_order_id' => $GetOrderId   
                    );
                        return $data;
                }elseif(count($intersect)){
                    return "SEAT UN-AVAIL";
                }else{
                    //Update Booking Ticket Status in booking Change status to 4(Seat on hold)  
                    $bookingId = $records[0]->id;    
                    $this->channelRepository->UpdateStatus($bookingId, $seatHold);
                    $name = $records[0]->users->name;
                    $receiptId = 'rcpt_'.$transationId;
    
                    $key= $this->channelRepository->getRazorpayKey();
    
                    $GetOrderId=$this->channelRepository->CreateCustomPayment($receiptId, $amount ,$name, $bookingId);
                        
    
                    $data = array(
                        'name' => $name,
                        'amount' => $amount,
                        'key' => $key,
                        'razorpay_order_id' => $GetOrderId   
                    );
                    return $data;
                }           

           }

        } catch (Exception $e) {
            Log::info($e->getMessage());
            throw new InvalidArgumentException(Config::get('constants.INVALID_ARGUMENT_PASSED'));
        }


      
    }



    public function BlockDolphinSeat($request,$clientRole){

        try {            
            $transationId = $request['transaction_id']; 

           $records = $this->channelRepository->getBookingRecord($transationId);

           $origin=$records[0]->origin;

           $res=  $this->dolphinTransformer->BlockSeat($records);

           return $res;
            // if($res['Status']==1){
            //      return $this->dolphinTransformer->BookSeat($records);
            // }else{
            //     return $res;
            // }

    } catch (Exception $e) {
        Log::info($e->getMessage());
        throw new InvalidArgumentException(Config::get('constants.INVALID_ARGUMENT_PASSED'));
    }



    }
    
    
    public function checkSeatStatus($request,$clientRole,$clientId) // this method is for Admin API usage (adjust ticket)
    {
        try {

            //$payment = $this->channelRepository->makePayment($data);
                $seatHold = Config::get('constants.SEAT_HOLD_STATUS');
                $busId = $request['busId'];  
                $seatIds = $request['seatIds'];
                $sourceId = $request['sourceId'];
                $destinationId = $request['destinationId'];  
                $entry_date = $request['entry_date'];
                $entry_date = date("Y-m-d", strtotime($entry_date));

            ///////////////////////cancelled bus recheck////////////////////////
            $routeDetails = TicketPrice::where('source_id', $sourceId)
                            ->where('destination_id', $destinationId)
                            ->where('bus_id', $busId)
                            ->where('status','1')
                            ->get(); 
           
            $startJDay = $routeDetails[0]->start_j_days;
            $ticketPriceId = $routeDetails[0]->id;

            switch($startJDay){
                case(1):
                    $new_date = $entry_date;
                    break;
                case(2):
                    $new_date = date('Y-m-d', strtotime('-1 day', strtotime($entry_date)));
                    break;
                case(3):
                    $new_date = date('Y-m-d', strtotime('-2 day', strtotime($entry_date)));
                    break;
            }   
            $cancelledBus = BusCancelled::where('bus_id', $busId)
                                        ->where('status', '1')
                                        ->with(['busCancelledDate' => function ($bcd) use ($new_date){
                                        $bcd->where('cancelled_date',$new_date);
                                        }])->get(); 
           
            $busCancel = $cancelledBus->pluck('busCancelledDate')->flatten();

            if(isset($busCancel) && $busCancel->isNotEmpty()){
                return "BUS_CANCELLED";
            }
          /////////////////seat block recheck////////////////////////
            $blockSeats = BusSeats::where('operation_date', $entry_date)
                                    ->where('type',2)
                                    ->where('bus_id',$busId)
                                    ->where('status',1)
                                    ->where('ticket_price_id',$ticketPriceId)
                                    ->whereIn('seats_id',$seatIds)
                                    ->get();
                                                    
            if(isset($blockSeats) && $blockSeats->isNotEmpty()){
                return "SEAT_BLOCKED";
            }

                $seatStatus = $this->viewSeatsService->getAllViewSeats($request,$clientRole,$clientId); 
                if(isset($seatStatus['lower_berth'])){
                    $lb = collect($seatStatus['lower_berth']);
                    $collection= $lb;
                }
                if(isset($seatStatus['upper_berth'])){
                    $ub = collect($seatStatus['upper_berth']);
                    $collection= $ub;
                }
                if(isset($lb) && isset($ub)){
                    $collection= $lb->merge($ub);
                }
                if(isset($collection)){

                    $checkBookedSeat = $collection->whereIn('id', $seatIds)->pluck('Gender');     //Select the Gender where bus_id matches
                    $filtered = $checkBookedSeat->reject(function ($value, $key) {    //remove the null value
                        return $value == null;
                    });

                    if(sizeof($filtered->all())==0){
                    return "SEAT AVAIL";
                    }
                    else{
                        return "SEAT UN-AVAIL";
                    }

                } else{

                    return "SEAT AVAIL";

                }
               
               
                

        } catch (Exception $e) {
            Log::info($e->getMessage());
            throw new InvalidArgumentException(Config::get('constants.INVALID_ARGUMENT_PASSED'));
        }
       
    } 
    
    public function pay($request)
    {
        try {
          
            $booked = Config::get('constants.BOOKED_STATUS');
            $paymentDone = Config::get('constants.PAYMENT_DONE');
            $bookedStatusFailed = Config::get('constants.BOOKED_STATUS_FAILED');
            $data = $request->all();
            
            $customerId = $this->channelRepository->GetCustomerPaymentId($data['razorpay_order_id']);
            $customerId = $customerId[0];
            //$busId = $request['bus_id'];
            $seatIds = $request['seat_id'];
            $razorpay_signature = $data['razorpay_signature'];
            $razorpay_payment_id = $data['razorpay_payment_id'];
            $razorpay_order_id = $data['razorpay_order_id'];
            $transationId = $data['transaction_id'];
        
            //$bookingRecord = $this->channelRepository->getBookingData($busId,$transationId);
            
            $records = $this->channelRepository->getBookingRecord($transationId);
            $origin=$records[0]->origin;

            if($origin=='DOLPHIN') {

                $res= $this->dolphinTransformer->BookSeat($records);

                $bookingRecord= $records;

                if($res['Status']==1 && $res['PNRNO']){

                   $updateApiData['api_pnr']=$res['PNRNO'];
                   $updateApiData['bus_name']="DOLPHIN TOURS & TRAVELS";
                   $this->channelRepository->UpdateAPIPnr($transationId,$updateApiData);


                   $bustype = 'NA';
                    $busTypeName = 'NA';
                    $sittingType = 'NA'; 
                    $conductor_number = 'NA';                  
                
                    $seat_no = $bookingRecord[0]->bookingDetail->pluck('seat_name');                               
                    $busname = "DOLPHIN TOURS & TRAVELS";
                    $busNumber = '';
                    $busId= $bookingRecord[0]->bus_id;
                    $cancellationslabs = $this->dolphinTransformer->GetCancellationPolicy();

                }else{
                    return 'Failed';
                }

            }if($origin=='ODBUS') {
                    $bookingRecord = $this->channelRepository->getBookingData($transationId);

                                       
                    $bustype = $bookingRecord[0]->bus->BusType->busClass->class_name;
                    $busTypeName = $bookingRecord[0]->bus->BusType->name;
                    $sittingType = $bookingRecord[0]->bus->BusSitting->name; 
                    $conductor_number = $bookingRecord[0]->bus->busContacts->phone;                  
                
                    $busSeatsIds = $bookingRecord[0]->bookingDetail->pluck('bus_seats_id');
                    $busSeatsDetails = BusSeats::whereIn('id',$busSeatsIds)->with('seats')->get();
                    $seat_no = $busSeatsDetails->pluck('seats.seatText');                    
                    $busname = $bookingRecord[0]->bus->name;
                    $busNumber = $bookingRecord[0]->bus->bus_number;
                    $busId= $bookingRecord[0]->bus->id;
                    $cancellationslabs = $bookingRecord[0]->bus->cancellationslabs->cancellationSlabInfo;

            }

            $pnr = $bookingRecord[0]->pnr; 
            $passengerDetails = $bookingRecord[0]->bookingDetail;
            $bookingId = $bookingRecord[0]->id;                   
            $phone = $bookingRecord[0]->users->phone;
            $email = $bookingRecord[0]->users->email;
            $name = $bookingRecord[0]->users->name;
            $journeydate = $bookingRecord[0]->journey_dt;
            $source = Location::where('id',$bookingRecord[0]->source_id)->first()->name;
            $destination = Location::where('id',$bookingRecord[0]->destination_id)->first()->name;
            $routedetails = $source.'-'.$destination;
            $boarding_point = $bookingRecord[0]->boarding_point;
            $departureTime = $bookingRecord[0]->boarding_time;
            $dropping_point = $bookingRecord[0]->dropping_point;
            $arrivalTime = $bookingRecord[0]->dropping_time;
            $departureTime = date("H:i:s",strtotime($departureTime));
            $bookingdate = $bookingRecord[0]->created_at;
            $bookingdate = date("d-m-Y", strtotime($bookingdate));

            if($bookingRecord[0]->payable_amount == 0.00){
                $payable_amount = $bookingRecord[0]->total_fare;
                }else{
                $payable_amount = $bookingRecord[0]->payable_amount;
                } 
        
                $totalfare = $bookingRecord[0]->total_fare;
                $discount = $bookingRecord[0]->coupon_discount;
                
                $odbus_charges = $bookingRecord[0]->odbus_charges;
                $odbus_gst = $bookingRecord[0]->odbus_gst_charges;
                $owner_fare = $bookingRecord[0]->owner_fare;
        
                $transactionFee=$bookingRecord[0]->transactionFee;
                $customer_gst_status=$bookingRecord[0]->customer_gst_status;
                $customer_gst_number=$bookingRecord[0]->customer_gst_number;
                $customer_gst_business_name=$bookingRecord[0]->customer_gst_business_name;
                $customer_gst_business_email=$bookingRecord[0]->customer_gst_business_email;
                $customer_gst_business_address=$bookingRecord[0]->customer_gst_business_address;
                $customer_gst_percent=$bookingRecord[0]->customer_gst_percent;
                $customer_gst_amount=$bookingRecord[0]->customer_gst_amount;
                $coupon_discount=$bookingRecord[0]->coupon_discount;
               
            
                $smsData = array(
                "seat_no" => $seat_no,
                "passengerDetails" => $passengerDetails, 
                "busname" => $busname,
                "busNumber" => $busNumber,
                "phone" => $phone,
                "journeydate" => $journeydate,
                "routedetails" => $routedetails,
                "departureTime" => $departureTime,
                "conductor_number" => $conductor_number,
                );
                $emailData = array(
                "pnr" => $pnr,
                "seat_no" => $seat_no,
                "passengerDetails" => $passengerDetails, 
                "busname" => $busname,
                "busNumber" => $busNumber,
                "phone" => $phone,
                "name" => $name,
                "email" => $email,
                "journeydate" => $journeydate,
                "bookingdate" => $bookingdate,
                "boarding_point" => $boarding_point,
                "arrivalTime" => $arrivalTime,
                "dropping_point" => $dropping_point,
                "routedetails" => $routedetails,
                "departureTime" => $departureTime,
                "conductor_number" => $conductor_number,
                "source" => $source,
                "destination" => $destination,
                "bustype" => $bustype,
                "busTypeName" => $busTypeName,
                "sittingType" => $sittingType,
                );
                
            
                return $this->channelRepository->UpdateCutsomerPaymentInfo($razorpay_order_id,$razorpay_signature,$razorpay_payment_id,$customerId,$paymentDone
                ,$totalfare,$discount,$payable_amount,$odbus_charges,$odbus_gst,$owner_fare,$request,$bookingId,$booked,$bookedStatusFailed,$transationId,$pnr,$busId,$cancellationslabs,$transactionFee,$customer_gst_status,$customer_gst_number,$customer_gst_business_name,$customer_gst_business_email,$customer_gst_business_address,$customer_gst_percent,$customer_gst_amount,$coupon_discount,$smsData,$email,$emailData,$origin);

           
          
            

        } catch (Exception $e) {
            Log::info($e->getMessage());
            throw new InvalidArgumentException(Config::get('constants.INVALID_ARGUMENT_PASSED'));
        }    
    } 
    
    public function walletPayment($request)
    {
        try {

                $seatHold = Config::get('constants.SEAT_HOLD_STATUS');
                $busId = $request['busId']; 
                $sourceId = $request['sourceId'];
                $destinationId = $request['destinationId'];  
                $transactionId = $request['transaction_id']; 
                $seatIds = $request['seatIds'];
                $entry_date = $request['entry_date'];
                $entry_date = date("Y-m-d", strtotime($entry_date));
                $agentId = $request['user_id'];
                $agentName = $request['user_name'];
                $appliedComission = $request['applied_comission'];
                $booked = Config::get('constants.BOOKED_STATUS');

                $records = $this->channelRepository->getBookingRecord($transactionId);

                $origin=$records[0]->origin;

                if($records[0]->payable_amount == 0.00){
                    $amount = $records[0]->total_fare;
                }else{
                    $amount = $records[0]->payable_amount;
                }

               

                if($origin=='ODBUS') {

                

                  ///////////////////////cancelled bus recheck////////////////////////
                  $routeDetails = TicketPrice::where('source_id', $sourceId)
                  ->where('destination_id', $destinationId)
                  ->where('bus_id', $busId)
                  ->where('status','1')
                  ->get(); 
  
                   /////////////seize time recheck////////////////////////
          
                  //$CurrentDateTime = "2022-09-09 07:46:35";
                  $CurrentDateTime = Carbon::now();//->toDateTimeString();
                  if(isset($routeDetails[0])){
                  $seizedTime = $routeDetails[0]->seize_booking_minute;
                  $depTime = date("H:i:s", strtotime($routeDetails[0]->dep_time)); 
                  
                  $depDateTime = Carbon::createFromFormat('Y-m-d H:i:s', $entry_date.' '.$depTime);
                  $diff_in_minutes = $depDateTime->diffInMinutes($CurrentDateTime);
                      if($depDateTime>=$CurrentDateTime){
                          $diff_in_minutes = $depDateTime->diffInMinutes($CurrentDateTime);
                      }else{
                          $diff_in_minutes = 0;
                      }
                      if($seizedTime > $diff_in_minutes){
                          return "BUS_SEIZED";
                      }
                  }
  
                  $startJDay = $routeDetails[0]->start_j_days;
                  $ticketPriceId = $routeDetails[0]->id;
  
                  switch($startJDay){
                  case(1):
                      $new_date = $entry_date;
                      break;
                  case(2):
                      $new_date = date('Y-m-d', strtotime('-1 day', strtotime($entry_date)));
                      break;
                  case(3):
                      $new_date = date('Y-m-d', strtotime('-2 day', strtotime($entry_date)));
                      break;
                  }   
                  $cancelledBus = BusCancelled::where('bus_id', $busId)
                                          ->where('status', '1')
                                          ->with(['busCancelledDate' => function ($bcd) use ($new_date){
                                          $bcd->where('cancelled_date',$new_date);
                                          }])->get(); 
  
                  $busCancel = $cancelledBus->pluck('busCancelledDate')->flatten();
  
                  if(isset($busCancel) && $busCancel->isNotEmpty()){
                  return "BUS_CANCELLED";
                  }
                  /////////////////seat block recheck////////////////////////
                  $blockSeats = BusSeats::where('operation_date', $entry_date)
                                      ->where('type',2)
                                      ->where('bus_id',$busId)
                                      ->where('status',1)
                                      ->where('ticket_price_id',$ticketPriceId)
                                      ->whereIn('seats_id',$seatIds)
                                      ->get();
                                                      
                  if(isset($blockSeats) && $blockSeats->isNotEmpty()){
                  return "SEAT_BLOCKED";
                  }
                  $bookedHoldSeats = $this->viewSeatsService->checkBlockedSeats($request);
  
                  $intersect = collect($bookedHoldSeats)->intersect($seatIds);      
                  
                  
                if(count($intersect)){
                  return "SEAT UN-AVAIL";
                }
  
            } 
  
            else if($origin=='DOLPHIN') {

                $intersect=[];

                $res= $this->dolphinTransformer->BlockSeat($records);

                if($res['Status']!=1){
                    return  $res['Message'];
                }
            }

            if($origin=='ODBUS' || ($origin=='DOLPHIN' && $res['Status']==1)) {
                   
                    $bookingId = $records[0]->id;
                    $pnr = $records[0]->pnr; 
                    
                    $name = $records[0]->users->name;
                   
                    $details=$this->channelRepository->CreateAgentPayment($agentId,$agentName,$amount ,$name, $bookingId,$transactionId,$pnr);   

                    $totalSeatsBookedByAgent = $this->channelRepository->FetchAgentBookedSeats($agentId,$agentName,$seatIds,$bookingId,$booked,$appliedComission,$pnr);
                    
                    $data = array(
                        'notifications' => $totalSeatsBookedByAgent->notification_heading,
                    );
                    return $data;
            }
               

        } catch (Exception $e) {
            Log::info($e->getMessage());
            throw new InvalidArgumentException(Config::get('constants.INVALID_ARGUMENT_PASSED'));
        }  
    }
    

    public function agentPaymentStatus($request)
    {
        try {
            $booked = Config::get('constants.BOOKED_STATUS');
            $paymentDone = Config::get('constants.PAYMENT_DONE');
            $bookedStatusFailed = Config::get('constants.BOOKED_STATUS_FAILED');
            $data = $request->all();
            //$busId = $request['bus_id'];
            //$seatIds = $request['seat_id'];
            $transationId = $data['transaction_id'];

            //Log::info($transationId);


            $records = $this->channelRepository->getBookingRecord($transationId);
            $origin=$records[0]->origin;
    
            if($origin=='DOLPHIN') {
    
                $res= $this->dolphinTransformer->BookSeat($records);

               // Log::info($res);
    
                $bookingRecord= $records;
    
                if($res['Status']==1 && $res['PNRNO']){
    
                   $updateApiData['api_pnr']=$res['PNRNO'];
                   $updateApiData['bus_name']="DOLPHIN TOURS & TRAVELS";
                   $this->channelRepository->UpdateAPIPnr($transationId,$updateApiData);
    
    
                   $bustype = 'NA';
                    $busTypeName = 'NA';
                    $sittingType = 'NA'; 
                    $conductor_number = 'NA';                  
                
                    $seat_no = $bookingRecord[0]->bookingDetail->pluck('seat_name');                               
                    $busname = "DOLPHIN TOURS & TRAVELS";
                    $busNumber = '';
                    $busId= $bookingRecord[0]->bus_id;
                    $cancellationslabs = $this->dolphinTransformer->GetCancellationPolicy();
    
                }else{
                    return 'Failed';
                }
    
            }if($origin=='ODBUS') {
                    $bookingRecord = $this->channelRepository->getBookingData($transationId);

                    $bustype = $bookingRecord[0]->bus->BusType->busClass->class_name;
                    $busTypeName = $bookingRecord[0]->bus->BusType->name;
                    $sittingType = $bookingRecord[0]->bus->BusSitting->name; 
                    $conductor_number = $bookingRecord[0]->bus->busContacts->phone;                  
                
                    $busSeatsIds = $bookingRecord[0]->bookingDetail->pluck('bus_seats_id');
                    $busSeatsDetails = BusSeats::whereIn('id',$busSeatsIds)->with('seats')->get();
                    $seat_no = $busSeatsDetails->pluck('seats.seatText');                    
                    $busname = $bookingRecord[0]->bus->name;
                    $busNumber = $bookingRecord[0]->bus->bus_number;
                    $busId= $bookingRecord[0]->bus->id;
                    $cancellationslabs = $bookingRecord[0]->bus->cancellationslabs->cancellationSlabInfo;

            }

           // Log::info($bookingRecord);

            $pnr = $bookingRecord[0]->pnr; 
            $passengerDetails = $bookingRecord[0]->bookingDetail;
            $bookingId = $bookingRecord[0]->id;                   
            $phone = $bookingRecord[0]->users->phone;
            $email = $bookingRecord[0]->users->email;
            $name = $bookingRecord[0]->users->name;
            $journeydate = $bookingRecord[0]->journey_dt;
            $source = Location::where('id',$bookingRecord[0]->source_id)->first()->name;
            $destination = Location::where('id',$bookingRecord[0]->destination_id)->first()->name;
            $routedetails = $source.'-'.$destination;
            $boarding_point = $bookingRecord[0]->boarding_point;
            $departureTime = $bookingRecord[0]->boarding_time;
            $dropping_point = $bookingRecord[0]->dropping_point;
            $arrivalTime = $bookingRecord[0]->dropping_time;
            $departureTime = date("H:i:s",strtotime($departureTime));
            $bookingdate = $bookingRecord[0]->created_at;
            $bookingdate = date("d-m-Y", strtotime($bookingdate));
        
            if($bookingRecord[0]->payable_amount == 0.00){
              $payable_amount = $bookingRecord[0]->total_fare;
            }else{
              $payable_amount = $bookingRecord[0]->payable_amount;
            }
     
            $customer_comission = $bookingRecord[0]->customer_comission;

            //Log::info($customer_comission);

            $totalfare = $bookingRecord[0]->total_fare;
            $discount = $bookingRecord[0]->coupon_discount;
             
            $odbus_charges = $bookingRecord[0]->odbus_charges;
            $odbus_gst = $bookingRecord[0]->odbus_gst_charges;
            $owner_fare = $bookingRecord[0]->owner_fare;
     
            $transactionFee=$bookingRecord[0]->transactionFee;
            $customer_gst_status=$bookingRecord[0]->customer_gst_status;
            $customer_gst_number=$bookingRecord[0]->customer_gst_number;
            $customer_gst_business_name=$bookingRecord[0]->customer_gst_business_name;
            $customer_gst_business_email=$bookingRecord[0]->customer_gst_business_email;
            $customer_gst_business_address=$bookingRecord[0]->customer_gst_business_address;
            $customer_gst_percent=$bookingRecord[0]->customer_gst_percent;
            $customer_gst_amount=$bookingRecord[0]->customer_gst_amount;
            $coupon_discount=$bookingRecord[0]->coupon_discount;
          
            $smsData = array(
              "seat_no" => $seat_no,
              "passengerDetails" => $passengerDetails, 
              "busname" => $busname,
              "busNumber" => $busNumber,
              "phone" => $phone,
              "journeydate" => $journeydate,
              "routedetails" => $routedetails,
              "departureTime" => $departureTime,
              "conductor_number" => $conductor_number,
              "customer_comission" => $customer_comission
            );
            $emailData = array(
                "pnr" => $pnr,
                "seat_no" => $seat_no,
                "passengerDetails" => $passengerDetails, 
                "busname" => $busname,
                "busNumber" => $busNumber,
                "phone" => $phone,
                "name" => $name,
                "email" => $email,
                "journeydate" => $journeydate,
                "bookingdate" => $bookingdate,
                "boarding_point" => $boarding_point,
                "arrivalTime" => $arrivalTime,
                "dropping_point" => $dropping_point,
                "routedetails" => $routedetails,
                "departureTime" => $departureTime,
                "conductor_number" => $conductor_number,
                "source" => $source,
                "destination" => $destination,
                "bustype" => $bustype,
                "busTypeName" => $busTypeName,
                "sittingType" => $sittingType,
                "customer_comission" => $customer_comission
            );
            return $this->channelRepository->UpdateAgentPaymentInfo($paymentDone,$totalfare,$discount,$payable_amount,$odbus_charges,$odbus_gst,$owner_fare,$request,$bookingId,$bookedStatusFailed,$transationId,$pnr,$busId,$booked,$cancellationslabs,$transactionFee,$customer_gst_status,$customer_gst_number,$customer_gst_business_name,$customer_gst_business_email,$customer_gst_business_address,$customer_gst_percent,$customer_gst_amount,$coupon_discount,$smsData,$email,$emailData,$origin);
            
        } catch (Exception $e) {
            Log::info($e->getMessage());
            throw new InvalidArgumentException(Config::get('constants.INVALID_ARGUMENT_PASSED'));
        }
        
    }   
//////////// generateFailedTicket///////////////////////////
    public function generateFailedTicket($request)
    {
        try {
            $resend = $this->channelRepository->generateFailedTicket($request);

        } catch (Exception $e) {
            Log::info($e->getMessage());
            throw new InvalidArgumentException(Config::get('constants.INVALID_ARGUMENT_PASSED'));
        }
        return $resend;
    }


    public function UpdateAdjustStatus($request)
    {
        try {
          
            $booked = Config::get('constants.BOOKED_STATUS');
            $paymentDone = Config::get('constants.PAYMENT_DONE');
            $bookedStatusFailed = Config::get('constants.BOOKED_STATUS_FAILED');
            $data = $request->all();

            
            $customerId = $this->channelRepository->GetCustomerPaymentId($data['razorpay_order_id']);
            $customerId = $customerId[0];
           
            //$busId = $request['bus_id'];
            //$seatIds = $request['seat_id'];
            $razorpay_signature = $data['razorpay_signature'];
            $razorpay_payment_id = $data['razorpay_payment_id'];
            $razorpay_order_id = $data['razorpay_order_id'];
            $transationId = $data['transaction_id'];
        
            //$bookingRecord = $this->channelRepository->getBookingData($busId,$transationId);
           // $bookingRecord = $this->channelRepository->getBookingData($transationId);

            $records = $this->channelRepository->getBookingRecord($transationId);

            $origin=$records[0]->origin;

           if($origin=='DOLPHIN') {

            $res= $this->dolphinTransformer->BookSeat($records);

            $bookingRecord= $records;

            if($res['Status']==1 && $res['PNRNO']){

                $updateApiData['api_pnr']=$res['PNRNO'];
                $updateApiData['bus_name']="DOLPHIN TOURS & TRAVELS";
                $this->channelRepository->UpdateAPIPnr($transationId,$updateApiData);
 
                 $bustype = 'NA';
                 $busTypeName = 'NA';
                 $sittingType = 'NA'; 
                 $conductor_number = 'NA';                  
             
                 $seat_no = $bookingRecord[0]->bookingDetail->pluck('seat_name');                               
                 $busname = "DOLPHIN TOURS & TRAVELS";
                 $busNumber = '';
                 $busId= $bookingRecord[0]->bus_id;
                 $cancellationslabs = $this->dolphinTransformer->GetCancellationPolicy();
 
             }else{
                 return 'Failed';
             }

           }

          if($origin=='ODBUS') {

            $bookingRecord = $this->channelRepository->getBookingData($transationId);

                                   
                $bustype = $bookingRecord[0]->bus->BusType->busClass->class_name;
                $busTypeName = $bookingRecord[0]->bus->BusType->name;
                $sittingType = $bookingRecord[0]->bus->BusSitting->name; 
                $conductor_number = $bookingRecord[0]->bus->busContacts->phone;                  
            
                $busSeatsIds = $bookingRecord[0]->bookingDetail->pluck('bus_seats_id');
                $busSeatsDetails = BusSeats::whereIn('id',$busSeatsIds)->with('seats')->get();
                $seat_no = $busSeatsDetails->pluck('seats.seatText');                    
                $busname = $bookingRecord[0]->bus->name;
                $busNumber = $bookingRecord[0]->bus->bus_number;
                $busId= $bookingRecord[0]->bus->id;
                $cancellationslabs = $bookingRecord[0]->bus->cancellationslabs->cancellationSlabInfo;

          }


          $pnr = $bookingRecord[0]->pnr; 
          $passengerDetails = $bookingRecord[0]->bookingDetail;
          $bookingId = $bookingRecord[0]->id;                   
          $phone = $bookingRecord[0]->users->phone;
          $email = $bookingRecord[0]->users->email;
          $name = $bookingRecord[0]->users->name;
          $journeydate = $bookingRecord[0]->journey_dt;
          $source = Location::where('id',$bookingRecord[0]->source_id)->first()->name;
          $destination = Location::where('id',$bookingRecord[0]->destination_id)->first()->name;
          $routedetails = $source.'-'.$destination;
          $boarding_point = $bookingRecord[0]->boarding_point;
          $departureTime = $bookingRecord[0]->boarding_time;
          $dropping_point = $bookingRecord[0]->dropping_point;
          $arrivalTime = $bookingRecord[0]->dropping_time;
          $departureTime = date("H:i:s",strtotime($departureTime));
          $bookingdate = $bookingRecord[0]->created_at;
          $bookingdate = date("d-m-Y", strtotime($bookingdate));
  
            if($bookingRecord[0]->payable_amount == 0.00){
              $payable_amount = $bookingRecord[0]->total_fare;
            }else{
              $payable_amount = $bookingRecord[0]->payable_amount;
            } 
      
              $totalfare = $bookingRecord[0]->total_fare;
              $discount = $bookingRecord[0]->coupon_discount;
              
              $odbus_charges = $bookingRecord[0]->odbus_charges;
              $odbus_gst = $bookingRecord[0]->odbus_gst_charges;
              $owner_fare = $bookingRecord[0]->owner_fare;
      
              $transactionFee=$bookingRecord[0]->transactionFee;
              $customer_gst_status=$bookingRecord[0]->customer_gst_status;
              $customer_gst_number=$bookingRecord[0]->customer_gst_number;
              $customer_gst_business_name=$bookingRecord[0]->customer_gst_business_name;
              $customer_gst_business_email=$bookingRecord[0]->customer_gst_business_email;
              $customer_gst_business_address=$bookingRecord[0]->customer_gst_business_address;
              $customer_gst_percent=$bookingRecord[0]->customer_gst_percent;
              $customer_gst_amount=$bookingRecord[0]->customer_gst_amount;
              $coupon_discount=$bookingRecord[0]->coupon_discount;

              $smsData = array(
                "seat_no" => $seat_no,
                "passengerDetails" => $passengerDetails, 
                "busname" => $busname,
                "busNumber" => $busNumber,
                "phone" => $phone,
                "journeydate" => $journeydate,
                "routedetails" => $routedetails,
                "departureTime" => $departureTime,
                "conductor_number" => $conductor_number,
                );
                $emailData = array(
                "pnr" => $pnr,
                "seat_no" => $seat_no,
                "passengerDetails" => $passengerDetails, 
                "busname" => $busname,
                "busNumber" => $busNumber,
                "phone" => $phone,
                "name" => $name,
                "email" => $email,
                "journeydate" => $journeydate,
                "bookingdate" => $bookingdate,
                "boarding_point" => $boarding_point,
                "arrivalTime" => $arrivalTime,
                "dropping_point" => $dropping_point,
                "routedetails" => $routedetails,
                "departureTime" => $departureTime,
                "conductor_number" => $conductor_number,
                "source" => $source,
                "destination" => $destination,
                "bustype" => $bustype,
                "busTypeName" => $busTypeName,
                "sittingType" => $sittingType,
                );            
        
            return $this->channelRepository->UpdateAdjustStatus($razorpay_order_id,$razorpay_signature,$razorpay_payment_id,$customerId,$paymentDone
            ,$totalfare,$discount,$payable_amount,$odbus_charges,$odbus_gst,$owner_fare,$request,$bookingId,$booked,$bookedStatusFailed,$transationId,$pnr,$busId,$cancellationslabs,$transactionFee,$customer_gst_status,$customer_gst_number,$customer_gst_business_name,$customer_gst_business_email,$customer_gst_business_address,$customer_gst_percent,$customer_gst_amount,$coupon_discount,$smsData,$email,$emailData,$origin);

        } catch (Exception $e) {
            Log::info($e->getMessage());
            throw new InvalidArgumentException(Config::get('constants.INVALID_ARGUMENT_PASSED'));
        }    
    } 
   
}