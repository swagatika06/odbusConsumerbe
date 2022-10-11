<?php

namespace App\Services;
use Illuminate\Http\Request;
use App\Models\Coupon;
use App\Models\Location;
use App\Models\Users;
use App\Repositories\BookingManageRepository;
use App\Models\TicketPrice;
use App\Repositories\CancelTicketRepository;
use Exception;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Log;
use InvalidArgumentException;
use Carbon\Carbon;
use DateTime;
use Illuminate\Support\Arr;
use App\Models\User;
use App\Repositories\ChannelRepository;
use App\Models\BusContacts;
use App\Transformers\DolphinTransformer;



class BookingManageService
{
    
    protected $bookingManageRepository;    
    protected $user;  
    protected $channelRepository; 
    protected $dolphinTransformer;


    public function __construct(BookingManageRepository $bookingManageRepository,CancelTicketRepository $cancelTicketRepository,User $user,ChannelRepository $channelRepository,DolphinTransformer $dolphinTransformer)
    {
        $this->bookingManageRepository = $bookingManageRepository;
        $this->cancelTicketRepository = $cancelTicketRepository;
        $this->channelRepository = $channelRepository;
        $this->user = $user;
        $this->dolphinTransformer = $dolphinTransformer;

    }
    public function getJourneyDetails($request)
    {
        try {          
            $pnr = $request['pnr'];
            $mobile = $request['mobile'];
    
            $journey_detail = $this->bookingManageRepository->getJourneyDetails($mobile,$pnr);
    
            if($journey_detail){            
    
                if(isset($journey_detail[0]->booking[0]) && !empty($journey_detail[0]->booking[0])){
                     $journey_detail[0]->booking['source']=$this->bookingManageRepository->GetLocationName($journey_detail[0]->booking[0]->source_id);
                     $journey_detail[0]->booking['destination']=$this->bookingManageRepository->GetLocationName($journey_detail[0]->booking[0]->source_id);
                }    
                else{                
                    return "PNR_NOT_MATCH";                
               }
           }            
           else{            
               return "MOBILE_NOT_MATCH";            
           }
    
            return $journey_detail;


        } catch (Exception $e) {
            //Log::info($e->getMessage());
            throw new InvalidArgumentException(Config::get('constants.INVALID_ARGUMENT_PASSED'));
        }
        
    }   

    public function getPassengerDetails($request)
    {
        try {           
            $pnr = $request['pnr'];
            $mobile = $request['mobile'];
    
            $passenger_detail = $this->bookingManageRepository->getPassengerDetails($mobile,$pnr);
    
            if(isset($passenger_detail[0])){ 
                if(isset($passenger_detail[0]->booking[0]) && !empty($passenger_detail[0]->booking[0])){                  
                   return $passenger_detail;                  
                }                
                else{                
                     return "PNR_NOT_MATCH";                
                }
            }            
            else{            
                return "MOBILE_NOT_MATCH";            
            }


        } catch (Exception $e) {
            //Log::info($e->getMessage());
            throw new InvalidArgumentException(Config::get('constants.INVALID_ARGUMENT_PASSED'));
        }
        
    }  

    public function getBookingDetails($request)
    {
        try {
           
            $pnr = $request['pnr'];
            $mobile = $request['mobile'];
    
            $pnr_dt = $this->bookingManageRepository->getPnrInfo($pnr); 

            if($pnr_dt->origin=='DOLPHIN'){

                $booking_detail = $this->bookingManageRepository->getDolphinBookingDetails($mobile,$pnr); 

                if(isset($booking_detail[0])){ 
                    if(isset($booking_detail[0]->booking[0]) && !empty($booking_detail[0]->booking[0])){ 
                      
                        $departureTime = $booking_detail[0]->booking[0]->boarding_time;
                        $arrivalTime = $booking_detail[0]->booking[0]->dropping_time;
                        $depTime = date("h:i A",strtotime($departureTime));
                        $arrTime = date("h:i A",strtotime($arrivalTime)); 
    
                        $jdays=0;

                        if(stripos($depTime,'AM') > -1 && stripos($arrTime,'PM') > -1){
                            $jdays = 1;                           
                            $departureTime =date("Y-m-d ".$departureTime);
                            $arrivalTime =date("Y-m-d ".$arrivalTime);
                        }
    
                        if(stripos($depTime,'PM') > -1 && stripos($arrTime,'AM') > -1){
                            $jdays = 2;
                            $tomorrow = date("Y-m-d", strtotime("+1 day"));
                            $departureTime =date("Y-m-d ".$departureTime);
                            $arrivalTime =$tomorrow." ".$arrivalTime; 
                        }

                        $j_endDate = $booking_detail[0]->booking[0]->journey_dt;

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
                         $booking_detail[0]->booking[0]['created_date'] = date('Y-m-d',strtotime($booking_detail[0]->booking[0]['created_at']));           
                         $booking_detail[0]->booking[0]['updated_date'] =   date('Y-m-d',strtotime($booking_detail[0]->booking[0]['updated_at']));                    
                         
                        return $booking_detail;                  
                    }                
                    else{                
                         return "PNR_NOT_MATCH";                
                    }
                }            
                else{            
                    return "MOBILE_NOT_MATCH";            
                }

            }else{

            $booking_detail = $this->bookingManageRepository->getBookingDetails($mobile,$pnr); 

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
                     $booking_detail[0]->booking[0]['created_date'] = date('Y-m-d',strtotime($booking_detail[0]->booking[0]['created_at']));           
                     $booking_detail[0]->booking[0]['updated_date'] =   date('Y-m-d',strtotime($booking_detail[0]->booking[0]['updated_at']));                    
                     
                    return $booking_detail;                  
                }                
                else{                
                     return "PNR_NOT_MATCH";                
                }
            }            
            else{            
                return "MOBILE_NOT_MATCH";            
            }
           }
            
        } catch (Exception $e) {
            Log::info($e->getMessage());
            throw new InvalidArgumentException(Config::get('constants.INVALID_ARGUMENT_PASSED'));
        }
       
    }  


    public function emailSms($request)
    {
        try {
            $pnr = $request['pnr'];
            $mobile = $request['mobile'];

            $pnr_dt = $this->bookingManageRepository->getPnrInfo($pnr); 

            if($pnr_dt->origin=='DOLPHIN'){


                $b= $this->bookingManageRepository->getDolphinBookingDetails($mobile,$pnr);

                    if($b && isset($b[0])){
                        $b=$b[0];  

                        $source_data= $this->bookingManageRepository->GetLocationName($b->booking[0]->source_id);
                       $dest_data= $this->bookingManageRepository->GetLocationName($b->booking[0]->destination_id);
                       
                            $seat_arr=[];
                            $seat_no='';                           
                            foreach($b->booking[0]->bookingDetail as $bd){
                                array_push($seat_arr,$bd->bus_seats['seats']['seatText']);              
                            }  

                       
                        $body = [
                                'name' => $b->name,
                                'phone' => $b->phone,
                                'email' => $b->email,
                                'pnr' => $b->booking[0]->pnr,
                                'bookingdate'=> $b->booking[0]->created_at,
                                'journeydate' => $b->booking[0]->journey_dt ,
                                'boarding_point'=> $b->booking[0]->boarding_point,
                                'dropping_point' => $b->booking[0]->dropping_point,
                                'departureTime'=> $b->booking[0]->boarding_time,
                                'arrivalTime'=> $b->booking[0]->dropping_time,
                                'seat_no' => $seat_arr,
                                'busname'=> $b->booking[0]->bus['name'],
                                'source'=> $source_data[0]->name,
                                'destination'=> $dest_data[0]->name,
                                'busNumber'=> $b->booking[0]->bus['bus_number'],
                                'bustype' => $b->booking[0]->bus['bus_type']['name'],
                                'busTypeName' => $b->booking[0]->bus['bus_type']['bus_class']['class_name'],
                                'sittingType' => $b->booking[0]->bus['bus_sitting']['name'], 
                                'conductor_number'=> $b->booking[0]->bus['bus_contacts']['phone'],
                                'passengerDetails' => $b->booking[0]->bookingDetail ,
                                'totalfare'=> $b->booking[0]->total_fare,
                                'discount'=> $b->booking[0]->coupon_discount,
                                'payable_amount'=> $b->booking[0]->payable_amount,
                                'odbus_gst'=> $b->booking[0]->odbus_gst_amount,
                                'odbus_charges'=> $b->booking[0]->odbus_charges,
                                'owner_fare'=> $b->booking[0]->owner_fare,
                                'routedetails' => $source_data[0]->name."-".$dest_data[0]->name 
                            ];

                          //  return $body;

                          $cancellation_slab_info=[];

                          if($b->booking[0]->bus['cancellationslabs']['cancellation_slab_info']){
                            foreach($b->booking[0]->bus['cancellationslabs']['cancellation_slab_info'] as $c){
                                $c_ar['duration']=$c['duration'];
                                $c_ar['deduction']=$c['deduction'];    
                                $cancellation_slab_info[]=(object) $c_ar;                                
                              }
                          }                         
                                     
                            $cancellationslabs =$cancellation_slab_info;
            
                            $transactionFee=$b->booking[0]->transactionFee;
                
                            $customer_gst_status=$b->booking[0]->customer_gst_status;
                            $customer_gst_number=$b->booking[0]->customer_gst_number;
                            $customer_gst_business_name=$b->booking[0]->customer_gst_business_name;
                            $customer_gst_business_email=$b->booking[0]->customer_gst_business_email;
                            $customer_gst_business_address=$b->booking[0]->customer_gst_business_address;
                            $customer_gst_percent=$b->booking[0]->customer_gst_percent;
                            $customer_gst_amount=$b->booking[0]->customer_gst_amount;
                            $coupon_discount=$b->booking[0]->coupon_discount;
                            $totalfare=$b->booking[0]->total_fare;
                            $discount=$b->booking[0]->coupon_discount;
                            $payable_amount=$b->booking[0]->payable_amount;
                            $odbus_charges = $b->booking[0]->odbus_charges;
                            $odbus_gst = $b->booking[0]->odbus_gst_charges;
                            $owner_fare = $b->booking[0]->owner_fare;
            
            
            
                            if($b->booking[0]->user_id !=0 && $b->booking[0]->user_id != null){
                                $agent_number= $this->user->where('id',$b->booking[0]->user_id)->get();
                                if(isset($agent_number[0])){
                                    $body['agent_number'] = $agent_number[0]->phone;
                                    $body['customer_comission'] = $b->booking[0]->customer_comission;
                                }   
                            }
                            if($b->email != ''){
                                $sendEmailTicket = $this->bookingManageRepository->sendEmailTicket($totalfare,$discount,$payable_amount,$odbus_charges,$odbus_gst,$owner_fare,$body,$b->booking[0]->pnr,$cancellationslabs,$transactionFee,$customer_gst_status,$customer_gst_number,$customer_gst_business_name,$customer_gst_business_email,$customer_gst_business_address,$customer_gst_percent,$customer_gst_amount,$coupon_discount); 
                            }
                            if($b->phone != ''){
                                $sendEmailTicket = $this->bookingManageRepository->sendSmsTicket($body,$b->booking[0]->pnr); 
                            }
                            return "Email & SMS has been sent to ".$b->email." & ".$b->phone;
                        }else{
                            return "Invalid request";   
                        }

        
            } else{
           
             $b= $this->bookingManageRepository->getBookingDetails($mobile,$pnr);

            if($b && isset($b[0])){
                $b=$b[0];
                $seat_arr=[];
                $seat_no='';
                foreach($b->booking[0]->bookingDetail as $bd){
                    array_push($seat_arr,$bd->busSeats->seats->seatText);              
                }  
               $source_data= $this->bookingManageRepository->GetLocationName($b->booking[0]->source_id);
               $dest_data= $this->bookingManageRepository->GetLocationName($b->booking[0]->destination_id);
               
               $body = [
                    'name' => $b->name,
                    'phone' => $b->phone,
                    'email' => $b->email,
                    'pnr' => $b->booking[0]->pnr,
                    'bookingdate'=> $b->booking[0]->created_at,
                    'journeydate' => $b->booking[0]->journey_dt ,
                    'boarding_point'=> $b->booking[0]->boarding_point,
                    'dropping_point' => $b->booking[0]->dropping_point,
                    'departureTime'=> $b->booking[0]->boarding_time,
                    'arrivalTime'=> $b->booking[0]->dropping_time,
                    'seat_no' => $seat_arr,
                    'busname'=> $b->booking[0]->bus->name,
                    'source'=> $source_data[0]->name,
                    'destination'=> $dest_data[0]->name,
                    'busNumber'=> $b->booking[0]->bus->bus_number,
                    'bustype' => $b->booking[0]->bus->busType->name,
                    'busTypeName' => $b->booking[0]->bus->busType->busClass->class_name,
                    'sittingType' => $b->booking[0]->bus->busSitting->name, 
                    'conductor_number'=> $b->booking[0]->bus->busContacts->phone,
                    'passengerDetails' => $b->booking[0]->bookingDetail ,
                    'totalfare'=> $b->booking[0]->total_fare,
                    'discount'=> $b->booking[0]->coupon_discount,
                    'payable_amount'=> $b->booking[0]->payable_amount,
                    'odbus_gst'=> $b->booking[0]->odbus_gst_amount,
                    'odbus_charges'=> $b->booking[0]->odbus_charges,
                    'owner_fare'=> $b->booking[0]->owner_fare,
                    'routedetails' => $source_data[0]->name."-".$dest_data[0]->name 
                ];

                $cancellationslabs = $b->booking[0]->bus->cancellationslabs->cancellationSlabInfo;

                $transactionFee=$b->booking[0]->transactionFee;
    
                $customer_gst_status=$b->booking[0]->customer_gst_status;
                $customer_gst_number=$b->booking[0]->customer_gst_number;
                $customer_gst_business_name=$b->booking[0]->customer_gst_business_name;
                $customer_gst_business_email=$b->booking[0]->customer_gst_business_email;
                $customer_gst_business_address=$b->booking[0]->customer_gst_business_address;
                $customer_gst_percent=$b->booking[0]->customer_gst_percent;
                $customer_gst_amount=$b->booking[0]->customer_gst_amount;
                $coupon_discount=$b->booking[0]->coupon_discount;
                $totalfare=$b->booking[0]->total_fare;
                $discount=$b->booking[0]->coupon_discount;
                $payable_amount=$b->booking[0]->payable_amount;
                $odbus_charges = $b->booking[0]->odbus_charges;
                $odbus_gst = $b->booking[0]->odbus_gst_charges;
                $owner_fare = $b->booking[0]->owner_fare;



                if($b->booking[0]->user_id !=0 && $b->booking[0]->user_id != null){
                    $agent_number= $this->user->where('id',$b->booking[0]->user_id)->get();
                    if(isset($agent_number[0])){
                        $body['agent_number'] = $agent_number[0]->phone;
                        $body['customer_comission'] = $b->booking[0]->customer_comission;
                    }   
                }
                if($b->email != ''){
                    $sendEmailTicket = $this->bookingManageRepository->sendEmailTicket($totalfare,$discount,$payable_amount,$odbus_charges,$odbus_gst,$owner_fare,$body,$b->booking[0]->pnr,$cancellationslabs,$transactionFee,$customer_gst_status,$customer_gst_number,$customer_gst_business_name,$customer_gst_business_email,$customer_gst_business_address,$customer_gst_percent,$customer_gst_amount,$coupon_discount); 
                }
                if($b->phone != ''){
                    $sendEmailTicket = $this->bookingManageRepository->sendSmsTicket($body,$b->booking[0]->pnr); 
                }
                return "Email & SMS has been sent to ".$b->email." & ".$b->phone;
            }else{
                return "Invalid request";   
            }
        } 
        }  catch (Exception $e) {
            Log::info($e->getMessage());
            throw new InvalidArgumentException(Config::get('constants.INVALID_ARGUMENT_PASSED'));
        }
        //return $emailSms;
    } 

    public function cancelTicketInfo($request)
    {
        try {

        $pnr = $request['pnr'];
        $mobile = $request['mobile'];

        $pnr_dt = $this->bookingManageRepository->getPnrInfo($pnr); 

        if($pnr_dt->origin=='DOLPHIN'){

            $booking_detail= $this->bookingManageRepository->DolphinCancelTicketInfo($mobile,$pnr);

             if(isset($booking_detail[0])){ 
                if(isset($booking_detail[0]->booking[0]) && !empty($booking_detail[0]->booking[0])){                     

                    $departureTime = $booking_detail[0]->booking[0]->boarding_time;
                        $arrivalTime = $booking_detail[0]->booking[0]->dropping_time;
                        $depTime = date("h:i A",strtotime($departureTime));
                        $arrTime = date("h:i A",strtotime($arrivalTime)); 
    
                        $jdays=0;

                        if(stripos($depTime,'AM') > -1 && stripos($arrTime,'PM') > -1){
                            $jdays = 1;                           
                            $departureTime =date("Y-m-d ".$departureTime);
                            $arrivalTime =date("Y-m-d ".$arrivalTime);
                        }
    
                        if(stripos($depTime,'PM') > -1 && stripos($arrTime,'AM') > -1){
                            $jdays = 2;
                            $tomorrow = date("Y-m-d", strtotime("+1 day"));
                            $departureTime =date("Y-m-d ".$departureTime);
                            $arrivalTime =$tomorrow." ".$arrivalTime; 
                        }

                        $j_endDate = $booking_detail[0]->booking[0]->journey_dt;

                        $arr_time = new DateTime($arrivalTime);
                        $dep_time = new DateTime($departureTime);
                        $totalTravelTime = $dep_time->diff($arr_time);
                        $totalJourneyTime = ($totalTravelTime->format("%a") * 24) + $totalTravelTime->format(" %h"). "h". $totalTravelTime->format(" %im");

                    if(strpos('AM',$depTime) > -1 && strpos('PM',$arrTime) > -1){
                        $jdays = 1;
                    }

                    if(strpos('PM',$depTime) > -1 && strpos('AM',$arrTime) > -1){
                        $jdays = 2;
                    }

                    $j_endDate = $booking_detail[0]->booking[0]->journey_dt;
                    
                
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
                    $emailData['journey_end_dt'] = $j_endDate;   

                    $jDate =$booking_detail[0]->booking[0]->journey_dt;
                    $jDate = date("d-m-Y", strtotime($jDate));
                    $boardTime =$booking_detail[0]->booking[0]->boarding_time; 
                    $baseFare = $booking_detail[0]->booking[0]->total_fare;

                    $combinedDT = date('Y-m-d H:i:s', strtotime("$jDate $boardTime"));
                    $current_date_time = Carbon::now()->toDateTimeString(); 

                    $bookingDate = new DateTime($combinedDT);
                    $cancelDate = new DateTime($current_date_time);                   

                    $srcId = $booking_detail[0]->booking[0]->source_id;
                    $desId = $booking_detail[0]->booking[0]->destination_id;
                    $sourceName = Location::where('id',$srcId)->first()->name;
                    $destinationName = Location::where('id',$desId)->first()->name;
                    $emailData['source'] = $sourceName;
                    $emailData['destination'] = $destinationName;
                    $emailData['bookingDetails'] = $booking_detail;

                    if($booking_detail[0]->booking[0]->status==2){
                        $emailData['cancel_status'] = false;
                    }else{
                        $emailData['cancel_status'] = true;                        

                    }
                
                    if($booking_detail[0]->booking[0]->customerPayment != null){
                        $dolphin_cancel_det= $this->dolphinTransformer->cancelTicketInfo($pnr_dt->api_pnr);                      
                        if($dolphin_cancel_det['RefundAmount']==0 && $dolphin_cancel_det['TotalFare']==0){
                            return 'Ticket_already_cancelled';
                         }

                            // $emailData['refundAmount'] = $dolphin_cancel_det['RefundAmount'];
                            // $emailData['deductAmount'] =$deductAmount=$dolphin_cancel_det['TotalFare'] - $dolphin_cancel_det['RefundAmount'];   
                            
                            // $emailData['totalfare'] = $totalfare = $dolphin_cancel_det['TotalFare'];    

                            $emailData['refundAmount'] = $dolphin_cancel_det['RefundAmount'];
                            $emailData['deductAmount'] =$deductAmount = $booking_detail[0]->booking[0]->total_fare - $dolphin_cancel_det['RefundAmount'];   
                            
                            $emailData['totalfare'] = $totalfare =  $booking_detail[0]->booking[0]->total_fare;   

                            $emailData['deductionPercentage'] = round((($deductAmount / $totalfare) * 100),1).'%';
                            return $emailData;

                    }else{
                        $emailData['refundAmount'] = 0;
                        $emailData['deductionPercentage'] = "100%";
                        $emailData['deductAmount'] =$booking_detail[0]->booking[0]->total_fare;
                        $emailData['totalfare'] = $booking_detail[0]->booking[0]->total_fare;
                        return $emailData;
                    }                          
                }    
                else{                
                    return "PNR_NOT_MATCH";                
                }
            }  
            else{            
                return "MOBILE_NOT_MATCH";            
            }
        }        
        else{
           $booking_detail  = $this->bookingManageRepository->cancelTicketInfo($mobile,$pnr);  
           //return $booking_detail;
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
                    $emailData['journey_end_dt'] = $j_endDate;   

                    $jDate =$booking_detail[0]->booking[0]->journey_dt;
                    $jDate = date("d-m-Y", strtotime($jDate));
                    $boardTime =$booking_detail[0]->booking[0]->boarding_time; 
                    $ownerFare = $booking_detail[0]->booking[0]->owner_fare;
                    $odbusCharges = $booking_detail[0]->booking[0]->odbus_charges;
                    $baseFare = $ownerFare + $odbusCharges;

                    $combinedDT = date('Y-m-d H:i:s', strtotime("$jDate $boardTime"));
                    $current_date_time = Carbon::now()->toDateTimeString(); 

                    $bookingDate = new DateTime($combinedDT);
                    $cancelDate = new DateTime($current_date_time);
                    /////// 30 mins before booking time no deduction//////////
                    // $bookingInitiatedDate = $booking_detail[0]->booking[0]->updated_at; 
                    // $difference = $bookingInitiatedDate->diff($current_date_time);
                    // $difference = ($difference->format("%a") * 24) + $difference->format(" %i");
                    
                    // if($difference < 30){
                    //     $emailData['refundAmount'] = $booking_detail[0]->booking[0]->total_fare;
                    //     $emailData['deductionPercentage'] = "0%";
                    //     $emailData['deductAmount'] = 0;
                    //     $emailData['totalfare'] = $booking_detail[0]->booking[0]->total_fare;
                    //     return $emailData;
                    // }
                    //////////
                    $interval = $bookingDate->diff($cancelDate);
                    $interval = ($interval->format("%a") * 24) + $interval->format(" %h");
                    
                    if($cancelDate >= $bookingDate || $interval < 12)
                    {
                        return "CANCEL_NOT_ALLOWED";
                    }

                    $srcId = $booking_detail[0]->booking[0]->source_id;
                    $desId = $booking_detail[0]->booking[0]->destination_id;
                    $sourceName = Location::where('id',$srcId)->first()->name;
                    $destinationName = Location::where('id',$desId)->first()->name;
                    $emailData['source'] = $sourceName;
                    $emailData['destination'] = $destinationName;
                    $emailData['bookingDetails'] = $booking_detail;

                    if($booking_detail[0]->booking[0]->status==2){
                        $emailData['cancel_status'] = false;
                    }else{
                        $emailData['cancel_status'] = true;
                    }
                
                    if($booking_detail[0]->booking[0]->customerPayment != null){

                        $razorpay_payment_id=$booking_detail[0]->booking[0]->customerPayment->razorpay_id;

                        $cancelPolicies = $booking_detail[0]->booking[0]->bus->cancellationslabs->cancellationSlabInfo;
                    
                        foreach($cancelPolicies as $cancelPolicy){
                        $duration = $cancelPolicy->duration;
                        $deduction = $cancelPolicy->deduction;
                        $duration = explode("-", $duration, 2);
                        $max= $duration[1];
                        $min= $duration[0];
    
                        if( $interval > 999){
                            
                            $deduction = 10;//minimum deduction
                            $refund =  $this->bookingManageRepository->refundPolicy($deduction,$razorpay_payment_id,$baseFare);
                            //$refundAmt =  round($refund['refundAmount']/100,2);
                            $refundAmt =  round($refund['refundAmount'],2);
                            $paidAmt =  ($refund['paidAmount']/100);
    
                            $emailData['refundAmount'] = $refundAmt;
                            $emailData['deductionPercentage'] = $deduction."%";
                            $emailData['deductAmount'] =round($paidAmt-$refundAmt,2);
                            $emailData['totalfare'] = $paidAmt;
                                
                            return $emailData;
        
                        }elseif($min <= $interval && $interval <= $max){ 
    
                            $refund =  $this->bookingManageRepository->refundPolicy($deduction,$razorpay_payment_id,$baseFare);
                            
                            //$refundAmt =  round(($refund['refundAmount']/100),2);
                            $refundAmt =  round($refund['refundAmount'],2);
                            $paidAmt =  ($refund['paidAmount']/100);
    
                            $emailData['refundAmount'] = $refundAmt;
                            $emailData['deductionPercentage'] = $deduction."%";
                            $emailData['deductAmount'] =round($paidAmt-$refundAmt,2);
                            $emailData['totalfare'] = $paidAmt;                         
                            return $emailData;   
                        }
                    } 
                    }else{
                        $emailData['refundAmount'] = 0;
                        $emailData['deductionPercentage'] = "100%";
                        $emailData['deductAmount'] =$booking_detail[0]->booking[0]->total_fare;
                        $emailData['totalfare'] = $booking_detail[0]->booking[0]->total_fare;
                        return $emailData;
                    }                          
                }    
                else{                
                    return "PNR_NOT_MATCH";                
                }
            }  
            else{            
                return "MOBILE_NOT_MATCH";            
            }
        }

        return $booking_detail;

    } catch (Exception $e) {
        Log::info($e->getMessage());
        throw new InvalidArgumentException(Config::get('constants.INVALID_ARGUMENT_PASSED'));
    }
        //return $cancelTicketInfo;
    } 
    
    public function agentcancelTicketOTP($request)
    {
        try {
        $pnr = $request['pnr'];
        $phone = $request['mobile'];
        $booked = Config::get('constants.BOOKED_STATUS');

        $pnr_dt = $this->bookingManageRepository->getPnrInfo($pnr); 

        if($pnr_dt->origin=='DOLPHIN'){

            $booking_detail= $this->bookingManageRepository->DolphinCancelTicketInfo($phone,$pnr);

            if(isset($booking_detail[0])){ 
                if(isset($booking_detail[0]->booking[0]) && !empty($booking_detail[0]->booking[0])){   

                    $dolphin_cancel_det= $this->dolphinTransformer->cancelTicketInfo($pnr_dt->api_pnr);                      
                    if($dolphin_cancel_det['RefundAmount']==0 && $dolphin_cancel_det['TotalFare']==0){
                        return 'Ticket_already_cancelled';
                    }


                    $otp = rand(10000, 99999);
                    $sendOTP = $this->bookingManageRepository->OTP($phone,$pnr,$otp,$booking_detail[0]->booking[0]->id); 

                    // $emailData['refundAmount'] = $dolphin_cancel_det['RefundAmount'];
                    // $emailData['deductAmount'] =$deductAmount=$dolphin_cancel_det['TotalFare'] - $dolphin_cancel_det['RefundAmount'];  
                    
                    // $emailData['totalfare'] = $totalfare = $dolphin_cancel_det['TotalFare'];   


                    $emailData['refundAmount'] = $dolphin_cancel_det['RefundAmount'];
                    $emailData['deductAmount'] =$deductAmount = $booking_detail[0]->booking[0]->total_fare - $dolphin_cancel_det['RefundAmount'];   
                    
                    $emailData['totalfare'] = $totalfare =  $booking_detail[0]->booking[0]->total_fare;
                    
                    $emailData['deductionPercentage'] = round((($deductAmount / $totalfare) * 100),1).'%';
                    return $emailData;
                }else{
                    return "PNR_NOT_MATCH";  
                }
            }else{
                return "MOBILE_NOT_MATCH"; 
            }
                     
        }

        else{

            $booking_detail = $this->bookingManageRepository->agentCancelTicket($phone,$pnr,$booked); 
            //Booking exists for the PNR
                if(isset($booking_detail[0])){ 
                    if(isset($booking_detail[0]->booking[0]) && !empty($booking_detail[0]->booking[0])){

                        $jDate =$booking_detail[0]->booking[0]->journey_dt;
                        $jDate = date("d-m-Y", strtotime($jDate));
                        $boardTime =$booking_detail[0]->booking[0]->boarding_time; 

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
                        // if($interval < 12) {
                        //     return 'CANCEL_NOT_ALLOWED';                    
                        // }
                        $paidAmount = $booking_detail[0]->booking[0]->payable_amount; 
                        $customer_comission = $booking_detail[0]->booking[0]->customer_comission; 
                        
                        $otp = rand(10000, 99999);
                        $sendOTP = $this->bookingManageRepository->OTP($phone,$pnr,$otp,$booking_detail[0]->booking[0]->id);      
                
                        $cancelPolicies = $booking_detail[0]->booking[0]->bus->cancellationslabs->cancellationSlabInfo; 
                    
                        foreach($cancelPolicies as $cancelPolicy){
                        $duration = $cancelPolicy->duration;
                        $deduction = $cancelPolicy->deduction;
                        $duration = explode("-", $duration, 2);
                        $max= $duration[1];
                        $min= $duration[0];

                        if( $interval > 999){
                            $deduction = 10;//minimum deduction
                            $refundAmt = round($paidAmount * ((100-$deduction) / 100),2);
                            $emailData['refundAmount'] = $refundAmt;
                            $emailData['deductionPercentage'] = $deduction."%";
                            $emailData['deductAmount'] =round($paidAmount-$refundAmt,2);
                            $emailData['totalfare'] = $paidAmount + $customer_comission ;
                                
                            return $emailData;
        
                        }elseif($min <= $interval && $interval <= $max){ 
                                $refundAmt = round($paidAmount * ((100-$deduction) / 100),2);
                            $emailData['refundAmount'] = $refundAmt;
                            $emailData['deductionPercentage'] = $deduction."%";
                            $emailData['deductAmount'] =round($paidAmount-$refundAmt,2);
                            $emailData['totalfare'] = $paidAmount + $customer_comission  ;                          
                            return $emailData;   
                        }
                    } 
                    } 
                    else{                
                        return "PNR_NOT_MATCH";                
                }
            } 
            else{            
                return "MOBILE_NOT_MATCH";            
            }
        }
       
        } catch (Exception $e) {
            Log::info($e->getMessage());
            throw new InvalidArgumentException(Config::get('constants.INVALID_ARGUMENT_PASSED'));
        }   
    } 

    public function agentcancelTicket($request)
    {
        try {
        $pnr = $request['pnr'];
        $phone = $request['mobile'];
        $recvOTP = $request['otp'];
        $booked = Config::get('constants.BOOKED_STATUS');

        $pnr_dt = $this->bookingManageRepository->getPnrInfo($pnr); 

        if($pnr_dt->origin=='DOLPHIN'){
            $booking_detail= $this->bookingManageRepository->DolphinAgentCancelTicket($phone,$pnr,$booked);

            if(isset($booking_detail[0])){ 
                if(isset($booking_detail[0]->booking[0]) && !empty($booking_detail[0]->booking[0])){
                    $dbOTP = $booking_detail[0]->booking[0]->cancel_otp;
                    if($dbOTP == $recvOTP){

                        $jDate =$booking_detail[0]->booking[0]->journey_dt;
                        $jDate = date("d-m-Y", strtotime($jDate));
                        $boardTime =$booking_detail[0]->booking[0]->boarding_time;
                        $seat_arr=[];
                        foreach($booking_detail[0]->booking[0]->bookingDetail as $bd){                            
                           $seat_arr = Arr::prepend($seat_arr, $bd->bus_seats['seats']['seatText']);
                        }
                        $busName = $booking_detail[0]->booking[0]->bus['name'];
                        $busNumber = $booking_detail[0]->booking[0]->bus['bus_number'];
                        $busId = $booking_detail[0]->booking[0]->bus_id;
                        $sourceName = $this->cancelTicketRepository->GetLocationName($booking_detail[0]->booking[0]->source_id);                   
                         $destinationName =$this->cancelTicketRepository->GetLocationName($booking_detail[0]->booking[0]->destination_id);
                          $route = $sourceName .'-'. $destinationName;
                        $userMailId =$booking_detail[0]->email;
                        $bookingId =$booking_detail[0]->booking[0]->id;
                        $booking = $this->cancelTicketRepository->GetBooking($bookingId);
                        
                        $current_date_time = Carbon::now()->toDateTimeString(); 
     
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


                        $userId = $booking_detail[0]->booking[0]->user_id;
                        $bookingId = $booking_detail[0]->booking[0]->id;
                        $srcId = $booking_detail[0]->booking[0]->source_id;
                        $desId = $booking_detail[0]->booking[0]->destination_id;
                        $paidAmount = $booking_detail[0]->booking[0]->payable_amount;
                        $customer_comission = $booking_detail[0]->booking[0]->customer_comission; 
                        $sourceName = Location::where('id',$srcId)->first()->name;
                        $destinationName = Location::where('id',$desId)->first()->name;
                        $data['source'] = $sourceName;
                        $data['destination'] = $destinationName;
                        $data['bookingDetails'] = $booking_detail;

                        if($booking_detail[0]->booking[0]->status==2){
                            $data['cancel_status'] = false;
                        }else{
                            $data['cancel_status'] = true;
                        }


                        $dolphin_cancel_det= $this->dolphinTransformer->ConfirmCancellation($pnr_dt->api_pnr);                      
                        if($dolphin_cancel_det['Status']==0){
                            return 'Ticket_already_cancelled';
                         }


                        //  $data['refundAmount'] = $refundAmt=$dolphin_cancel_det['RefundAmount'];
                        //  $data['deductAmount'] =$deductAmount = $dolphin_cancel_det['TotalFare'] - $dolphin_cancel_det['RefundAmount'];

                         $data['refundAmount'] = $refundAmt=$dolphin_cancel_det['RefundAmount'];
                         $data['deductAmount'] =$deductAmount = $booking_detail[0]->booking[0]->total_fare - $dolphin_cancel_det['RefundAmount'];   
                    
                         $data['totalfare'] = $totalfare =  $booking_detail[0]->booking[0]->total_fare;

                    
                         $data['deductionPercentage'] = $deduction=round((($deductAmount / $totalfare) * 100),1)."%";

                         $agentWallet = $this->bookingManageRepository->updateCancelTicket($bookingId,$userId,$refundAmt, $deduction,$pnr); 

                         $smsData['refundAmount'] = $refundAmt; 

                         $emailData['deductionPercentage'] = $deduction;
                         $emailData['refundAmount'] = $refundAmt;
                         $emailData['totalfare'] = $totalfare;
                 
                         $sendsms = $this->cancelTicketRepository->sendSmsTicketCancel($smsData);
                        if($emailData['email'] != ''){
                            $sendEmailTicketCancel = $this->cancelTicketRepository->sendEmailTicketCancel($emailData);  
                        } 

                        $this->cancelTicketRepository->sendAdminEmailTicketCancel($emailData);



                    }else{
                        return "INVALID_OTP";   
                    }
                }else{
                    return "PNR_NOT_MATCH";   
                }
            }else{
                return "MOBILE_NOT_MATCH";   
            }
        }else{

        $booking_detail  = $this->bookingManageRepository->agentCancelTicket($phone,$pnr,$booked);  
            //return $booking_detail;
                if(isset($booking_detail[0])){ 
                    if(isset($booking_detail[0]->booking[0]) && !empty($booking_detail[0]->booking[0])){
                        $dbOTP = $booking_detail[0]->booking[0]->cancel_otp;
                        if($dbOTP == $recvOTP){
                            $jDate =$booking_detail[0]->booking[0]->journey_dt;
                            $jDate = date("d-m-Y", strtotime($jDate));
                            $boardTime =$booking_detail[0]->booking[0]->boarding_time; 
                            $seat_arr=[];
                            foreach($booking_detail[0]->booking[0]->bookingDetail as $bd){
                                
                            $seat_arr = Arr::prepend($seat_arr, $bd->busSeats->seats->seatText);
                            }
                            $busName = $booking_detail[0]->booking[0]->bus->name;
                            $busId = $booking_detail[0]->booking[0]->bus_id;
                            $busNumber = $booking_detail[0]->booking[0]->bus->bus_number;
                            $sourceName = $this->cancelTicketRepository->GetLocationName($booking_detail[0]->booking[0]->source_id);                   
                            $destinationName =$this->cancelTicketRepository->GetLocationName($booking_detail[0]->booking[0]->destination_id);
                            $route = $sourceName .'-'. $destinationName;
                            $userMailId =$booking_detail[0]->email;

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
                            // if($interval < 12) {
                            //     return 'CANCEL_NOT_ALLOWED';                    
                            // }
                            $userId = $booking_detail[0]->booking[0]->user_id;
                            $bookingId = $booking_detail[0]->booking[0]->id;
                            $srcId = $booking_detail[0]->booking[0]->source_id;
                            $desId = $booking_detail[0]->booking[0]->destination_id;
                            $paidAmount = $booking_detail[0]->booking[0]->payable_amount;
                            $customer_comission = $booking_detail[0]->booking[0]->customer_comission; 
                            $sourceName = Location::where('id',$srcId)->first()->name;
                            $destinationName = Location::where('id',$desId)->first()->name;
                            $data['source'] = $sourceName;
                            $data['destination'] = $destinationName;
                            $data['bookingDetails'] = $booking_detail;

                            if($booking_detail[0]->booking[0]->status==2){
                                $data['cancel_status'] = false;
                            }else{
                                $data['cancel_status'] = true;
                            }

                            $cancelPolicies = $booking_detail[0]->booking[0]->bus->cancellationslabs->cancellationSlabInfo;
                    
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
                                $data['deductAmount'] =round($paidAmount-$refundAmt,2);
                                $data['totalfare'] = $paidAmount + $customer_comission;
                                $agentWallet = $this->bookingManageRepository->updateCancelTicket($bookingId,$userId,$refundAmt, $deduction,$pnr); 

                                $smsData['refundAmount'] = $refundAmt;     
                                $emailData['deductionPercentage'] = $deduction;
                                $emailData['refundAmount'] = $refundAmt;
                                $emailData['totalfare'] = $paidAmount + $customer_comission;
                        
                                $sendsms = $this->cancelTicketRepository->sendSmsTicketCancel($smsData);
                                    if($emailData['email'] != ''){
                                        $sendEmailTicketCancel = $this->cancelTicketRepository->sendEmailTicketCancel($emailData);  
                                    } 

                                    $this->cancelTicketRepository->sendAdminEmailTicketCancel($emailData);


                                    ////////////////////////////CMO SMS SEND ON TICKET CANCEL/////////////////////////////////
                                    $busContactDetails = BusContacts::where('bus_id',$busId)
                                    ->where('status','1')
                                    ->where('cancel_sms_send','1')
                                    ->get('phone');
                                    if($busContactDetails->isNotEmpty()){
                                        $contact_number = collect($busContactDetails)->implode('phone',',');
                                        $this->channelRepository->sendSmsTicketCancelCMO($smsData,$contact_number);
                                    }


                                return $data;
            
                            }elseif($min <= $interval && $interval <= $max){ 
                            
                                $refundAmt = round($paidAmount * ((100-$deduction) / 100),2);
                                $data['refundAmount'] = $refundAmt;
                                $data['deductionPercentage'] = $deduction."%";
                                $data['deductAmount'] =round($paidAmount-$refundAmt,2);
                                $data['totalfare'] = $paidAmount + $customer_comission;                        
                                
                                $agentWallet = $this->bookingManageRepository->updateCancelTicket($bookingId,$userId,$refundAmt,$deduction,$pnr);
                                
                                $smsData['refundAmount'] = $refundAmt; 
                                $emailData['deductionPercentage'] = $deduction;
                                $emailData['refundAmount'] = $refundAmt;
                                $emailData['totalfare'] = $paidAmount + $customer_comission;;
                            
                                $sendsms = $this->cancelTicketRepository->sendSmsTicketCancel($smsData);
                                    if($emailData['email'] != ''){
                                        $sendEmailTicketCancel = $this->cancelTicketRepository->sendEmailTicketCancel($emailData);  
                                    }  
                                    
                                    $this->cancelTicketRepository->sendAdminEmailTicketCancel($emailData); 


                                    ////////////////////////////CMO SMS SEND ON TICKET CANCEL/////////////////////////////////
                                    $busContactDetails = BusContacts::where('bus_id',$busId)
                                    ->where('status','1')
                                    ->where('cancel_sms_send','1')
                                    ->get('phone');
                                    if($busContactDetails->isNotEmpty()){
                                        $contact_number = collect($busContactDetails)->implode('phone',',');
                                        $this->channelRepository->sendSmsTicketCancelCMO($smsData,$contact_number);
                                    }
                                    

                                return $data;   
                            }
                        } 
                        }else{                
                            return "INVALID_OTP";                
                        }                         
                    } 
                    else{                
                        return "PNR_NOT_MATCH";                
                }
            } 
            else{            
                return "MOBILE_NOT_MATCH";            
            }
        }
      
        } catch (Exception $e) {
            Log::info($e->getMessage());
            throw new InvalidArgumentException(Config::get('constants.INVALID_ARGUMENT_PASSED'));
        }
    } 
    
    public function getPnrDetails($pnr){

        try {  
            $pnrdetail = $this->bookingManageRepository->getPnrDetails($pnr);
    
            if(isset($pnrdetail[0])){ 
                    
                    $ticketPriceRecords = TicketPrice::where('bus_id', $pnrdetail[0]->bus_id)
                    ->where('source_id', $pnrdetail[0]->source_id)
                    ->where('destination_id', $pnrdetail[0]->destination_id)
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
                            $j_endDate = $pnrdetail[0]->journey_dt;
                            break;
                        case(2):
                            $j_endDate = date('Y-m-d', strtotime('+1 day', strtotime($pnrdetail[0]->journey_dt)));
                            break;
                        case(3):
                            $j_endDate = date('Y-m-d', strtotime('+2 day', strtotime($pnrdetail[0]->journey_dt)));
                            break;
                    }


                     $pnrdetail[0]['source']=$this->bookingManageRepository->GetLocationName($pnrdetail[0]->source_id);
                     $pnrdetail[0]['destination']=$this->bookingManageRepository->GetLocationName($pnrdetail[0]->destination_id);  
                     $pnrdetail[0]['journeyDuration'] =  $totalJourneyTime;
                     $pnrdetail[0]['journey_end_dt'] =  $j_endDate;           
                     
                    return $pnrdetail;    
               
            }            
            else{            
                return "INVALID_PNR";            
            }


        } catch (Exception $e) {
            Log::info($e->getMessage());
            throw new InvalidArgumentException(Config::get('constants.INVALID_ARGUMENT_PASSED'));
        }

    }
}