<?php

namespace App\Services;
use Illuminate\Http\Request;
use App\Repositories\ClientBookingRepository;
use App\Services\ViewSeatsService;
use App\Repositories\ChannelRepository;
use App\Repositories\CancelTicketRepository;
use App\Repositories\BookingManageRepository;
use App\Repositories\CommonRepository;
use App\Models\TicketPrice;
use App\Models\BusCancelled;
use App\Models\Booking;
use App\Models\BusSeats;
use App\Models\ClientFeeSlab;
use App\Models\Location;
use App\Models\BusContacts;
use Carbon\Carbon;
use DateTime;
use Exception;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Config;

use Illuminate\Support\Facades\Log;
use InvalidArgumentException;

class ClientBookingService
{
    
    protected $clientBookingRepository;  
    protected $viewSeatsService; 
    protected $channelRepository; 
    protected $commonRepository;
    protected $cancelTicketRepository;
    protected $bookingManageRepository;    

    public function __construct(ClientBookingRepository $clientBookingRepository,ViewSeatsService $viewSeatsService,ChannelRepository $channelRepository,CommonRepository $commonRepository,CancelTicketRepository $cancelTicketRepository,BookingManageRepository $bookingManageRepository)
    {
        $this->clientBookingRepository = $clientBookingRepository;
        $this->viewSeatsService = $viewSeatsService;
        $this->channelRepository = $channelRepository;
        $this->commonRepository = $commonRepository;
        $this->cancelTicketRepository = $cancelTicketRepository;
        $this->bookingManageRepository = $bookingManageRepository;
    }
    public function clientBooking($request,$clientRole,$clientId)
    {
        try {

            $ReferenceNumber = (isset($request['bookingInfo']['ReferenceNumber'])) ? $request['bookingInfo']['ReferenceNumber'] : '';
            $origin = (isset($request['bookingInfo']['origin'])) ? $request['bookingInfo']['origin'] : 'ODBUS';
    
              
    
                if($origin !='DOLPHIN' && $origin != 'ODBUS' ){
                    return 'Invalid Origin';
                }else if($origin=='DOLPHIN'){
    
                    if($ReferenceNumber ==''){    
                        return 'ReferenceNumber_empty';
    
                    }
                }

            $bookTicket = $this->clientBookingRepository->clientBooking($request,$clientRole,$clientId);
            return $bookTicket;

        } catch (Exception $e) {
            throw new InvalidArgumentException(Config::get('constants.INVALID_ARGUMENT_PASSED'));
        }
       
    }   

    public function seatBlock($request,$clientRole)
    {
        try {
           
                $seatHold = Config::get('constants.SEAT_HOLD_STATUS');  
                $transationId = $request['transaction_id']; 

                $bookingDetails = Booking::where('transaction_id', $transationId)
                                        ->with(["bookingDetail" => function($b){
                                            $b->with(["busSeats" => function($bs){
                                                $bs->with(["seats" => function($s){ 
                                                }]);
                                            }]);    
                                        }])
                                        ->get();
                
                $busId = $bookingDetails[0]->bus_id; 
                $sourceId = $bookingDetails[0]->source_id;
                $destinationId = $bookingDetails[0]->destination_id;
                $entry_date = $bookingDetails[0]->journey_dt;
                
                $seatIds = [];
                foreach($bookingDetails[0]->bookingDetail as $bd){
                    array_push($seatIds,$bd->busSeats->seats->id);              
                }  
                $data = array(
                    'busId' => $busId,
                    'sourceId' =>  $sourceId,
                    'destinationId' => $destinationId,
                    'entry_date' => $entry_date,
                    'seatIds' => $seatIds,
                ); 
            
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
            ///////////////////////cancelled bus recheck////////////////////////            
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
        
            $bookedHoldSeats = $this->viewSeatsService->checkBlockedSeats($data);
            
            $intersect = collect($bookedHoldSeats)->intersect($seatIds);
            
            $records = $this->channelRepository->getBookingRecord($transationId);

            $amount = $records[0]->total_fare;
               
            /////////////// calculate customer GST  (customet gst = (owner fare + service charge) - Coupon discount)

            $masterSetting=$this->commonRepository->getCommonSettings('1'); // 1 stands for ODBSU is from user table to get maste setting data

            if($request['customer_gst_status']==true || $request['customer_gst_status']=='true'){

                    $update_customer_gst['customer_gst_status']=1;
                    $update_customer_gst['customer_gst_number']=$request['customer_gst_number'];
                    $update_customer_gst['customer_gst_business_name']=$request['customer_gst_business_name'];
                    $update_customer_gst['customer_gst_business_email']=$request['customer_gst_business_email'];
                    $update_customer_gst['customer_gst_business_address']=$request['customer_gst_business_address'];

                    $update_customer_gst['customer_gst_percent']=$masterSetting[0]->customer_gst;

                    $customer_gst_amount= round((( ($records[0]->owner_fare+$records[0]->odbus_charges) ) *$masterSetting[0]->customer_gst)/100,2);

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

                if(count($intersect)){
                    return "SEAT UN-AVAIL";
                }else{
                    $bookingId = $records[0]->id;   
                    $name = $records[0]->users->name; 
                    //Update Booking Ticket Status in booking Change status to 4(Seat on hold)   
                    $this->channelRepository->UpdateStatus($bookingId, $seatHold);

                    $data = array(
                        'customer_name' => $name,
                        'amount' => $amount,
                    );
                    return $data;         
                }             
        } catch (Exception $e) {
            Log::info($e);
            throw new InvalidArgumentException(Config::get('constants.INVALID_ARGUMENT_PASSED'));
        }   
    } 
 
    public function ticketConfirmation($request)
    {
        try {
            $bookTicket = $this->clientBookingRepository->ticketConfirmation($request);
            return $bookTicket;

        } catch (Exception $e) {
            throw new InvalidArgumentException(Config::get('constants.INVALID_ARGUMENT_PASSED'));
        }
       
    }  
    
    public function clientCancelTicket($request)////////admin panel use
    {
        try {        
            $pnr = $request['pnr'];
            $clientId = $request['user_id'];
            $booked = Config::get('constants.BOOKED_STATUS');

            //$booking_detail = $this->clientBookingRepository->clientCancelTicket($phone,$pnr,$booked);
            $booking_detail = $this->clientBookingRepository->clientCancelTicket($clientId,$pnr,$booked);
           
            if(isset($booking_detail[0])){ 
               
                //if(isset($booking_detail[0]->booking[0]) && !empty($booking_detail[0]->booking[0])){

                       $jDate =$booking_detail[0]->journey_dt;
                       $jDate = date("d-m-Y", strtotime($jDate));
                       $boardTime =$booking_detail[0]->boarding_time; 
                       $seat_arr=[];
                       foreach($booking_detail[0]->bookingDetail as $bd){
                       
                          $seat_arr = Arr::prepend($seat_arr, $bd->busSeats->seats->seatText);
                       }
                       $busName = $booking_detail[0]->bus->name;
                       $busNumber = $booking_detail[0]->bus->bus_number;
                       $sourceName = $this->cancelTicketRepository->GetLocationName($booking_detail[0]->source_id);                   
                       $destinationName =$this->cancelTicketRepository->GetLocationName($booking_detail[0]->destination_id);
                       $route = $sourceName .'-'. $destinationName;
                       $userMailId = $booking_detail[0]->users->email;
                       $phone = $booking_detail[0]->users->phone;
                       $combinedDT = date('Y-m-d H:i:s', strtotime("$jDate $boardTime"));
                       $current_date_time = Carbon::now()->toDateTimeString(); 
                       $bookingDate = new DateTime($combinedDT);
                       $cancelDate = new DateTime($current_date_time);
                       $interval = $bookingDate->diff($cancelDate);
                       $interval = ($interval->format("%a") * 24) + $interval->format(" %h");
                       
                       $smsData = array(
                           'phone' => $phone,
                           'PNR' => $pnr,
                           'busdetails' => $busName.'-'.$busNumber,
                           'doj' => $jDate, 
                           'route' => $route,
                           'seat' => $seat_arr
                       );
                       $emailData = array(
                           'email' => $userMailId,
                           'contactNo' => $phone,
                           'pnr' => $pnr,
                           'journeydate' => $jDate, 
                           'route' => $route,
                           'seat_no' => $seat_arr,
                           'cancellationDateTime' => $current_date_time
                       );
                       
                       if($cancelDate >= $bookingDate || $interval < 12)
                       {
                       return "CANCEL_NOT_ALLOWED";
                       }
                       $userId = $booking_detail[0]->user_id;
                       $bookingId = $booking_detail[0]->id;
                       $srcId = $booking_detail[0]->source_id;
                       $desId = $booking_detail[0]->destination_id;
                       //$paidAmount = $booking_detail[0]->booking[0]->payable_amount;
                       $paidAmount = $booking_detail[0]->total_fare;
                      
                       //$customer_comission = $booking_detail[0]->booking[0]->customer_comission; 
                       $sourceName = Location::where('id',$srcId)->first()->name;
                       $destinationName = Location::where('id',$desId)->first()->name;
                       
                       $data['source'] = $sourceName;
                       $data['destination'] = $destinationName;
                       $data['bookingDetails'] = $booking_detail;
   
                       if($booking_detail[0]->status==2){
                           $data['cancel_status'] = false;
                       }else{
                           $data['cancel_status'] = true;
                       }
                       
                       $cancelPolicies = $booking_detail[0]->bus->cancellationslabs->cancellationSlabInfo;
                      
                       foreach($cancelPolicies as $cancelPolicy){
                          $duration = $cancelPolicy->duration;
                          $deduction = $cancelPolicy->deduction;
                          $duration = explode("-", $duration, 2);
                          $max= $duration[1];
                          $min= $duration[0];
       
                          if( $interval > 999){
                            
                              $deduction = 10;//minimum deduction 
                              $refundAmt = round($paidAmount * ((100-$deduction) / 100),2);
                              $data['refundAmount'] = $refundAmt;
                              $data['Percentage'] = $deduction;
                              $data['deductionPercentage'] = $deduction."%"; 
                              $deductAmt = round($paidAmount-$refundAmt,2);
                              $data['deductAmount'] = $deductAmt;
                              $data['totalfare'] = $paidAmount;
                              $cancelComCal = $this->cancelCommission($userId,$deductAmt);
                              $data['OdbusCancelCommission'] = $cancelComCal['OdbusCancelProfit']; 
                              $data['ClientCancelCommission'] = $cancelComCal['clientCancelProfit'];
                              
                              $clientWallet = $this->clientBookingRepository->updateClientCancelTicket($bookingId,$userId,$data); 
                             
                              $smsData['refundAmount'] = $refundAmt;     
                              $emailData['deductionPercentage'] = $deduction;
                              $emailData['refundAmount'] = $refundAmt;
                              $emailData['totalfare'] = $paidAmount;
                            
                              //$sendsms = $this->cancelTicketRepository->sendSmsTicketCancel($smsData);
                               if($emailData['email'] != ''){
                              // $sendEmailTicketCancel = $this->cancelTicketRepository->sendEmailTicketCancel($emailData);  
                               }   
                              return $data;
          
                          }elseif($min <= $interval && $interval <= $max){ 
                           
                              $refundAmt = round($paidAmount * ((100-$deduction) / 100),2);
                              $data['refundAmount'] = $refundAmt;
                              $data['Percentage'] = $deduction;
                              $data['deductionPercentage'] = $deduction."%";
                              $deductAmt = round($paidAmount-$refundAmt,2);
                              $data['deductAmount'] = $deductAmt;
                              $data['totalfare'] = $paidAmount;
                              $cancelComCal = $this->cancelCommission($userId,$deductAmt);
                              $data['OdbusCancelCommission'] = $cancelComCal['OdbusCancelProfit']; 
                              $data['ClientCancelCommission'] = $cancelComCal['clientCancelProfit'];            
                            
                              $clientWallet = $this->clientBookingRepository->updateClientCancelTicket($bookingId,$userId,$data); 

                              $smsData['refundAmount'] = $refundAmt; 
                              $emailData['deductionPercentage'] = $deduction;
                              $emailData['refundAmount'] = $refundAmt;
                              $emailData['totalfare'] = $paidAmount;
                          
                              //$sendsms = $this->cancelTicketRepository->sendSmsTicketCancel($smsData);
                               if($emailData['email'] != ''){
                              //$sendEmailTicketCancel = $this->cancelTicketRepository->sendEmailTicketCancel($emailData);  
                               }    
                              return $data;   
                          }
                      }                          
          } 
          else{            
              return "INV_CLIENT";            
          }
        } catch (Exception $e) {
            Log::info($e->getMessage());
            throw new InvalidArgumentException(Config::get('constants.INVALID_ARGUMENT_PASSED'));
        }    
    }   

    public function clientCancelTicketInfo($request)////admin panel use
    {
        try {        
            $pnr = $request['pnr'];
            $clientId = $request['user_id'];
            $booked = Config::get('constants.BOOKED_STATUS');

            $booking_detail = $this->clientBookingRepository->clientCancelTicket($clientId,$pnr,$booked);
            if(isset($booking_detail[0])){ 
               
                       $jDate =$booking_detail[0]->journey_dt;
                       $jDate = date("d-m-Y", strtotime($jDate));
                       $boardTime =$booking_detail[0]->boarding_time; 
                    
                       $combinedDT = date('Y-m-d H:i:s', strtotime("$jDate $boardTime"));
                       $current_date_time = Carbon::now()->toDateTimeString(); 
                       $bookingDate = new DateTime($combinedDT);
                       $cancelDate = new DateTime($current_date_time);
                       $interval = $bookingDate->diff($cancelDate);
                       $interval = ($interval->format("%a") * 24) + $interval->format(" %h");
                      
                       if($cancelDate >= $bookingDate || $interval < 12)
                       {
                       return "CANCEL_NOT_ALLOWED";
                       }

                       $userId = $booking_detail[0]->user_id;
                       $paidAmount = $booking_detail[0]->total_fare;
                       $data['bookingDetails'] = $booking_detail;
                       
                       if($booking_detail[0]->status==2){
                           $data['cancel_status'] = false;
                       }else{
                           $data['cancel_status'] = true;
                       }
                       
                       $cancelPolicies = $booking_detail[0]->bus->cancellationslabs->cancellationSlabInfo;
                      
                       foreach($cancelPolicies as $cancelPolicy){
                          $duration = $cancelPolicy->duration;
                          $deduction = $cancelPolicy->deduction;
                          $duration = explode("-", $duration, 2);
                          $max= $duration[1];
                          $min= $duration[0];
       
                          if( $interval > 999){
                            
                              $deduction = 10;//minimum deduction 
                              $refundAmt = round($paidAmount * ((100-$deduction) / 100),2);
                              $data['refundAmount'] = $refundAmt;
                              $data['deductionPercentage'] = $deduction."%"; 
                              $deductAmt = round($paidAmount-$refundAmt,2);
                              $data['deductAmount'] = $deductAmt;
                              $data['totalfare'] = $paidAmount;
                              $cancelComCal = $this->cancelCommission($userId,$deductAmt);
                              $data['OdbusCancelCommission'] = $cancelComCal['OdbusCancelProfit']; 
                              $data['ClientCancelCommission'] = $cancelComCal['clientCancelProfit']; 
                            
                              return $data;
          
                          }elseif($min <= $interval && $interval <= $max){ 
                           
                              $refundAmt = round($paidAmount * ((100-$deduction) / 100),2);
                              $data['refundAmount'] = $refundAmt;
                              $data['deductionPercentage'] = $deduction."%";
                              $deductAmt = round($paidAmount-$refundAmt,2);
                              $data['deductAmount'] = $deductAmt;
                              $data['totalfare'] = $paidAmount;
                              $cancelComCal = $this->cancelCommission($userId,$deductAmt);
                              $data['OdbusCancelCommission'] = $cancelComCal['OdbusCancelProfit']; 
                              $data['ClientCancelCommission'] = $cancelComCal['clientCancelProfit'];          
                           
                              return $data;   
                          }
                      }                          
          }else{         
              return "INV_CLIENT";            
          }
        } catch (Exception $e) {
            Log::info($e->getMessage());
            throw new InvalidArgumentException(Config::get('constants.INVALID_ARGUMENT_PASSED'));
        }    
    }  
    
    public function clientCancelTicketInfos($request)////client panel use
    {
        try {        
            $pnr = $request['pnr'];
            $clientId = $request['user_id'];
            $booked = Config::get('constants.BOOKED_STATUS');

            $booking_detail = $this->clientBookingRepository->clientCancelTicket($clientId,$pnr,$booked);
            if(isset($booking_detail[0])){ 
               
                       $jDate =$booking_detail[0]->journey_dt;
                       $jDate = date("d-m-Y", strtotime($jDate));
                       $boardTime =$booking_detail[0]->boarding_time; 
                    
                       $combinedDT = date('Y-m-d H:i:s', strtotime("$jDate $boardTime"));
                       $current_date_time = Carbon::now()->toDateTimeString(); 
                       $bookingDate = new DateTime($combinedDT);
                       $cancelDate = new DateTime($current_date_time);
                       $interval = $bookingDate->diff($cancelDate);
                       $interval = ($interval->format("%a") * 24) + $interval->format(" %h");
                      
                       if($cancelDate >= $bookingDate || $interval < 12)
                       {
                       return "CANCEL_NOT_ALLOWED";
                       }
                      
                       $paidAmount = $booking_detail[0]->total_fare;
                       $userId = $booking_detail[0]->user_id;
   
                       if($booking_detail[0]->status==2){
                           $data['cancel_status'] = false;
                       }else{
                           $data['cancel_status'] = true;
                       }
                       
                       $cancelPolicies = $booking_detail[0]->bus->cancellationslabs->cancellationSlabInfo;
                      
                       foreach($cancelPolicies as $cancelPolicy){
                          $duration = $cancelPolicy->duration;
                          $deduction = $cancelPolicy->deduction;
                          $duration = explode("-", $duration, 2);
                          $max= $duration[1];
                          $min= $duration[0];
       
                          if( $interval > 999){
                            
                              $deduction = 10;//minimum deduction 
                              $refundAmt = round($paidAmount * ((100-$deduction) / 100),2);
                              $data['refundAmount'] = $refundAmt;
                              $data['deductionPercentage'] = $deduction."%"; 
                              $deductAmt = round($paidAmount-$refundAmt,2);
                              $data['deductAmount'] = $deductAmt;
                              $data['totalfare'] = $paidAmount;
                              //$data['clientCancelCommission'] = $deductAmt/2; 
                              //$data['odbusCancelCommission'] = $deductAmt/2; 
                              $cancelComCal = $this->cancelCommission($userId,$deductAmt);
                              $data['OdbusCancelCommission'] = $cancelComCal['OdbusCancelProfit']; 
                              $data['ClientCancelCommission'] = $cancelComCal['clientCancelProfit'];
                            
                              return $data;
          
                          }elseif($min <= $interval && $interval <= $max){ 
                           
                              $refundAmt = round($paidAmount * ((100-$deduction) / 100),2);
                              $data['refundAmount'] = $refundAmt;
                              $data['deductionPercentage'] = $deduction."%";
                              $deductAmt = round($paidAmount-$refundAmt,2);
                              $data['deductAmount'] = $deductAmt;
                              $data['totalfare'] = $paidAmount;
                              $cancelComCal = $this->cancelCommission($userId,$deductAmt);
                              $data['OdbusCancelCommission'] = $cancelComCal['OdbusCancelProfit']; 
                              $data['ClientCancelCommission'] = $cancelComCal['clientCancelProfit'];          
                           
                              return $data;   
                          }
                      }                          
          }else{         
              return "INV_CLIENT";            
          }
        } catch (Exception $e) {
            Log::info($e->getMessage());
            throw new InvalidArgumentException(Config::get('constants.INVALID_ARGUMENT_PASSED'));
        }    
    }   
    
    public function cancelCommission($userId,$deductAmt){
        $clientCancelComPer =0;
        $clientCancelComPer = ClientFeeSlab::where('user_id',$userId)->first()->cancellation_commission;
        if($clientCancelComPer == 0){
            $OdbusCancelProfit = $deductAmt;
            $clientCancelProfit = 0; 
        }else{
          $OdbusCancelProfit = round($deductAmt * ((100 - $clientCancelComPer))/100,2); 
          $clientCancelProfit = round($deductAmt - $OdbusCancelProfit,2);
        }
        $cancelCom = array(
                            "OdbusCancelProfit" => $OdbusCancelProfit, 
                            "clientCancelProfit" => $clientCancelProfit
                    );
        return $cancelCom;
    }

    public function clientTicketCancel($request)////////client panel use
    {
        try {        
            $pnr = $request['pnr'];
            $clientId = $request['user_id'];
            $booked = Config::get('constants.BOOKED_STATUS');
            $booking_detail = $this->clientBookingRepository->clientCancelTicket($clientId,$pnr,$booked);
            if(isset($booking_detail[0])){ 
               
                       $jDate =$booking_detail[0]->journey_dt;
                       $jDate = date("d-m-Y", strtotime($jDate));
                       $boardTime =$booking_detail[0]->boarding_time; 
                       $seat_arr=[];
                       foreach($booking_detail[0]->bookingDetail as $bd){
                       
                          $seat_arr = Arr::prepend($seat_arr, $bd->busSeats->seats->seatText);
                       }
                       $busId = $booking_detail[0]->bus_id;
                       $busName = $booking_detail[0]->bus->name;
                       $busNumber = $booking_detail[0]->bus->bus_number;
                       $sourceName = $this->cancelTicketRepository->GetLocationName($booking_detail[0]->source_id);                   
                       $destinationName =$this->cancelTicketRepository->GetLocationName($booking_detail[0]->destination_id);
                       $route = $sourceName .'-'. $destinationName;
                       $userMailId = $booking_detail[0]->users->email;
                       $phone = $booking_detail[0]->users->phone;
                       $combinedDT = date('Y-m-d H:i:s', strtotime("$jDate $boardTime"));
                       $current_date_time = Carbon::now()->toDateTimeString(); 
                       $bookingDate = new DateTime($combinedDT);
                       $cancelDate = new DateTime($current_date_time);
                       $interval = $bookingDate->diff($cancelDate);
                       $interval = ($interval->format("%a") * 24) + $interval->format(" %h");
                       
                       $smsData = array(
                           'phone' => $phone,
                           'PNR' => $pnr,
                           'busdetails' => $busName.'-'.$busNumber,
                           'doj' => $jDate, 
                           'route' => $route,
                           'seat' => $seat_arr
                       );
                       $emailData = array(
                           'email' => $userMailId,
                           'contactNo' => $phone,
                           'pnr' => $pnr,
                           'journeydate' => $jDate, 
                           'route' => $route,
                           'seat_no' => $seat_arr,
                           'cancellationDateTime' => $current_date_time
                       );
                       
                       if($cancelDate >= $bookingDate || $interval < 12)
                       {
                       return "CANCEL_NOT_ALLOWED";
                       }
                       $userId = $booking_detail[0]->user_id;
                       $bookingId = $booking_detail[0]->id;
                       $srcId = $booking_detail[0]->source_id;
                       $desId = $booking_detail[0]->destination_id;
                       $paidAmount = $booking_detail[0]->total_fare;
                       $sourceName = Location::where('id',$srcId)->first()->name;
                       $destinationName = Location::where('id',$desId)->first()->name;
                       
                       $data['source'] = $sourceName;
                       $data['destination'] = $destinationName;
                       $data['bookingDetails'] = $booking_detail;
   
                       if($booking_detail[0]->status==2){
                           $data['cancel_status'] = false;
                       }else{
                           $data['cancel_status'] = true;
                       }
                       
                       $cancelPolicies = $booking_detail[0]->bus->cancellationslabs->cancellationSlabInfo;
                       foreach($cancelPolicies as $cancelPolicy){
                          $duration = $cancelPolicy->duration;
                          $deduction = $cancelPolicy->deduction;
                          $duration = explode("-", $duration, 2);
                          $max= $duration[1];
                          $min= $duration[0];
       
                          if( $interval > 999){
                            
                              $deduction = 10;//minimum deduction 
                              $refundAmt = round($paidAmount * ((100-$deduction) / 100),2);
                              $data['refundAmount'] = $refundAmt;
                              $data['Percentage'] = $deduction;
                              $data['deductionPercentage'] = $deduction."%"; 
                              $deductAmt = round($paidAmount-$refundAmt,2);
                              $data['deductAmount'] = $deductAmt;
                              $data['totalfare'] = $paidAmount;
                              $cancelComCal = $this->cancelCommission($userId,$deductAmt);
                              $data['OdbusCancelCommission'] = $cancelComCal['OdbusCancelProfit']; 
                              $data['ClientCancelCommission'] = $cancelComCal['clientCancelProfit'];

                              $clientWallet = $this->clientBookingRepository->updateClientCancelTicket($bookingId,$userId,$data); 
                             
                              $smsData['refundAmount'] = $refundAmt;     
                              $emailData['deductionPercentage'] = $deduction;
                              $emailData['refundAmount'] = $refundAmt;
                              $emailData['totalfare'] = $paidAmount;
                            
                              //$sendsms = $this->cancelTicketRepository->sendSmsTicketCancel($smsData);
                              if($emailData['email'] != ''){
                              // $sendEmailTicketCancel = $this->cancelTicketRepository->sendEmailTicketCancel($emailData);  
                               }
                              $this->cancelTicketRepository->sendAdminEmailTicketCancel($emailData); 

                              ////////////////////////////CMO SMS SEND ON TICKET CANCEL////////////////
                             $busContactDetails = BusContacts::where('bus_id',$busId)
                             ->where('status','1')
                             ->where('cancel_sms_send','1')
                             ->get('phone');
                            if($busContactDetails->isNotEmpty()){
                            $contact_number = collect($busContactDetails)->implode('phone',',');
                            $this->channelRepository->sendSmsTicketCancelCMO($smsData,$contact_number);
                            }  
                            unset($data['bookingDetails'][0]->bus->cancellationslabs); 
                            unset($data['bookingDetails'][0]->bus->cancellationslabs_id);   
                              return $data;
          
                          }elseif($min <= $interval && $interval <= $max){ 
                           
                              $refundAmt = round($paidAmount * ((100-$deduction) / 100),2);
                              $data['refundAmount'] = $refundAmt;
                              $data['Percentage'] = $deduction;
                              $data['deductionPercentage'] = $deduction."%";
                              $deductAmt = round($paidAmount-$refundAmt,2);
                              $data['deductAmount'] = $deductAmt;
                              $data['totalfare'] = $paidAmount;
                              //$data['cancelCommission'] = $deductAmt/2;   
                                     
                              $cancelComCal = $this->cancelCommission($userId,$deductAmt);
                              $data['OdbusCancelCommission'] = $cancelComCal['OdbusCancelProfit']; 
                              $data['ClientCancelCommission'] = $cancelComCal['clientCancelProfit'];
                            
                              $clientWallet = $this->clientBookingRepository->updateClientCancelTicket($bookingId,$userId,$data); 
                              
                              $smsData['refundAmount'] = $refundAmt; 
                              $emailData['deductionPercentage'] = $deduction;
                              $emailData['refundAmount'] = $refundAmt;
                              $emailData['totalfare'] = $paidAmount;
                          
                              //$sendsms = $this->cancelTicketRepository->sendSmsTicketCancel($smsData);
                               if($emailData['email'] != ''){
                              //$sendEmailTicketCancel = $this->cancelTicketRepository->sendEmailTicketCancel($emailData);  
                               } 

                               //$this->cancelTicketRepository->sendAdminEmailTicketCancel($emailData); 

                              ////////////////////////////CMO SMS SEND ON TICKET CANCEL////////////////
                             $busContactDetails = BusContacts::where('bus_id',$busId)
                                                                ->where('status','1')
                                                                ->where('cancel_sms_send','1')
                                                                ->get('phone');
                             if($busContactDetails->isNotEmpty()){
                              $contact_number = collect($busContactDetails)->implode('phone',',');
                              //$this->channelRepository->sendSmsTicketCancelCMO($smsData,$contact_number);
                             }
                              unset($data['bookingDetails'][0]->bus->cancellationslabs); 
                              unset($data['bookingDetails'][0]->bus->cancellationslabs_id);  
                              unset($data['Percentage']);  
                              return $data;   
                          }
                      }                          
            } 
          else{            
              return "INV_CLIENT";            
          }
        } catch (Exception $e) {
            Log::info($e->getMessage());
            throw new InvalidArgumentException(Config::get('constants.INVALID_ARGUMENT_PASSED'));
        }    
    }
    ////////ticketDetails(client use)//////////
    public function ticketDetails($request)
    {
        try {
            $pnr = $request['pnr'];
            $mobile = $request['mobile'];
            $booking_detail = $this->clientBookingRepository->bookingDetails($mobile,$pnr); 

            if(isset($booking_detail[0])){ 
                if(isset($booking_detail[0]->booking[0]) && !empty($booking_detail[0]->booking[0])){ 
                    
                    $ticketPriceRecords = TicketPrice::where('bus_id', $booking_detail[0]->booking[0]->bus_id)
                    ->where('source_id', $booking_detail[0]->booking[0]->source_id)
                    ->where('destination_id', $booking_detail[0]->booking[0]->destination_id)
                    ->get(); 
    
                    $departureTime = $ticketPriceRecords[0]->dep_time;
                    $arrivalTime = $ticketPriceRecords[0]->arr_time;
                    $depTime = date("H:i",strtotime($departureTime));
                    $arrTime = date("H:i",strtotime($arrivalTime)); 
                    $jdays = $ticketPriceRecords[0]->j_day;
                    $arr_time = new DateTime($arrivalTime);
                    $dep_time = new DateTime($departureTime);
                    $totalTravelTime = $dep_time->diff($arr_time);
                    $totalJourneyTime = ($totalTravelTime->format("%a") * 24) + $totalTravelTime->format(" %h"). "h". $totalTravelTime->format(" %im");

                    switch($jdays)
                    {
                        case(1):
                            $j_endDate = $booking_detail[0]->booking[0]->journey_dt;
                            break;
                        case(2):
                            $j_endDate = date('Y-m-d', strtotime('+1 day', strtotime($booking_detail[0]->booking[0]->journey_dt)));
                            break;
                        case(3):
                            $j_endDate = date('Y-m-d', strtotime('+2 day', strtotime($booking_detail[0]->booking[0]->journey_dt)));
                            break;
                    }

                     $booking_detail[0]->booking[0]['source']=$this->bookingManageRepository->GetLocationName($booking_detail[0]->booking[0]->source_id);
                     $booking_detail[0]->booking[0]['destination']=$this->bookingManageRepository->GetLocationName($booking_detail[0]->booking[0]->destination_id);  
                     $booking_detail[0]->booking[0]['journeyDuration'] =  $totalJourneyTime;
                     $booking_detail[0]->booking[0]['journey_end_dt'] =  $j_endDate;           
                     //$booking_detail[0]->booking[0]['created_date'] = date('Y-m-d',strtotime($booking_detail[0]->booking[0]['created_at']));           
                     //$booking_detail[0]->booking[0]['updated_date'] =   date('Y-m-d',strtotime($booking_detail[0]->booking[0]['updated_at']));                    
                     
                    return $booking_detail;                  
                }                
                else{                
                     return "PNR_NOT_MATCH";                
                }
            }            
            else{            
                return "MOBILE_NOT_MATCH";            
            }
            
        } catch (Exception $e) {
            Log::info($e->getMessage());
            throw new InvalidArgumentException(Config::get('constants.INVALID_ARGUMENT_PASSED'));
        }
       
    }  

   
}