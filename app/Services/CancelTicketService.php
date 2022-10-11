<?php

namespace App\Services;
use Illuminate\Http\Request;
use App\Models\Coupon;
use App\Repositories\CancelTicketRepository;
use Exception;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Config;
use Carbon\Carbon;
use DateTime;
use Illuminate\Support\Facades\Log;
use InvalidArgumentException;
use Illuminate\Support\Arr;
use App\Repositories\ChannelRepository;
use App\Models\BusContacts;
use App\Transformers\DolphinTransformer;

class CancelTicketService
{
    
    protected $cancelTicketRepository;  
    protected $channelRepository;
    protected $dolphinTransformer;


    public function __construct(CancelTicketRepository $cancelTicketRepository,ChannelRepository $channelRepository,DolphinTransformer $dolphinTransformer)
    {
        $this->cancelTicketRepository = $cancelTicketRepository;
        $this->channelRepository = $channelRepository;
        $this->dolphinTransformer = $dolphinTransformer;

    }

    public function CancelDolphinSeat($request){

        $pnr = $request['pnr'];

        $pnr_dt = $this->cancelTicketRepository->getPnrInfo($pnr); 
        $dolphin_cancel_det= $this->dolphinTransformer->ConfirmCancellation($pnr_dt->api_pnr);  
        
        if($dolphin_cancel_det['Status']==0){
            return 'failed';
        }else{

            $update['api_refund_amount'] = $dolphin_cancel_det['RefundAmount'];  
            $deductAmount=$dolphin_cancel_det['TotalFare'] - $dolphin_cancel_det['RefundAmount'];   
            $totalfare = $dolphin_cancel_det['TotalFare']; 

            $update['api_deduction_percent'] = $deduction=round((($deductAmount / $totalfare) * 100),1);            

            $this->cancelTicketRepository->updateCancelTicketDolphin($update,$pnr_dt->id);

            return 'success';
           
        }

    }

    public function cancelTicket($request)
    {
        try {          
            $pnr = $request['pnr'];
            $phone = $request['phone'];
            $booked = Config::get('constants.BOOKED_STATUS');

            $pnr_dt = $this->cancelTicketRepository->getPnrInfo($pnr); 

            if($pnr_dt->origin=='DOLPHIN'){
    
                $booking_detail= $this->cancelTicketRepository->DolphinCancelTicket($phone,$pnr,$booked);

                if(isset($booking_detail[0])){         
                    if(isset($booking_detail[0]->booking[0]) && !empty($booking_detail[0]->booking[0])){
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
    
                     if($booking_detail[0]->booking[0]->customerPayment != null){
                        $razorpay_payment_id = $booking_detail[0]->booking[0]->customerPayment->razorpay_id; 

                        $dolphin_cancel_det= $this->dolphinTransformer->ConfirmCancellation($pnr_dt->api_pnr);                      
                        if($dolphin_cancel_det['Status']==0){
                            return 'Ticket_already_cancelled';
                         }

                        // $emailData['refundAmount'] = $dolphin_cancel_det['RefundAmount'];
                        // $emailData['deductAmount'] = $deductAmount = $dolphin_cancel_det['TotalFare'] - $dolphin_cancel_det['RefundAmount'];
                        // $emailData['totalfare'] = $totalfare = $dolphin_cancel_det['TotalFare'];  

                        $emailData['refundAmount'] = $dolphin_cancel_det['RefundAmount'];
                        $emailData['deductAmount'] =$deductAmount = $booking_detail[0]->booking[0]->total_fare - $dolphin_cancel_det['RefundAmount'];   
                        
                        $emailData['totalfare'] = $totalfare =  $booking_detail[0]->booking[0]->total_fare;  

                        $emailData['deductionPercentage'] = $deduction=round((($deductAmount / $totalfare) * 100),1);
                        $smsData['refundAmount'] = $refundAmount = $dolphin_cancel_det['RefundAmount'];
                        $refund =  $this->cancelTicketRepository->DolphinCancelUpdate($deduction,$razorpay_payment_id,$bookingId,$booking,$smsData,$emailData,$busId,$refundAmount);                        

                        $sendsms = $this->cancelTicketRepository->sendSmsTicketCancel($smsData);
                        if($emailData['email'] != ''){
                           $sendEmailTicketCancel = $this->cancelTicketRepository->sendEmailTicketCancel($emailData);  
                        } 

                        $this->cancelTicketRepository->sendAdminEmailTicketCancel($emailData); 

                        return $refund;

                       
                     } else{
                        $refund = $this->cancelTicketRepository->cancel($bookingId,$booking,$smsData,$emailData,$busId)
                                ; 
                        return $refund;  
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
    
            $booking_detail  = $this->cancelTicketRepository->cancelTicket($phone,$pnr,$booked);
            if(isset($booking_detail[0])){         
                if(isset($booking_detail[0]->booking[0]) && !empty($booking_detail[0]->booking[0])){
                    $jDate =$booking_detail[0]->booking[0]->journey_dt;
                    $jDate = date("d-m-Y", strtotime($jDate));
                    $boardTime =$booking_detail[0]->booking[0]->boarding_time;
                    $seat_arr=[];
                    foreach($booking_detail[0]->booking[0]->bookingDetail as $bd){
                        
                       $seat_arr = Arr::prepend($seat_arr, $bd->busSeats->seats->seatText);
                    }
                    $busName = $booking_detail[0]->booking[0]->bus->name;
                    $busNumber = $booking_detail[0]->booking[0]->bus->bus_number;
                    $busId = $booking_detail[0]->booking[0]->bus_id;
                    $sourceName = $this->cancelTicketRepository->GetLocationName($booking_detail[0]->booking[0]->source_id);                   
                     $destinationName =$this->cancelTicketRepository->GetLocationName($booking_detail[0]->booking[0]->destination_id);
                      $route = $sourceName .'-'. $destinationName;
                    $userMailId =$booking_detail[0]->email;
                    $bookingId =$booking_detail[0]->booking[0]->id;
                    $booking = $this->cancelTicketRepository->GetBooking($bookingId);
                    
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

                    /////// 30 mins before booking time no deduction//////////
                    // $bookingInitiatedDate = $booking_detail[0]->booking[0]->updated_at; 
                    // $difference = $bookingInitiatedDate->diff($current_date_time);
                    // $difference = ($difference->format("%a") * 24) + $difference->format(" %i");
                
                    // if($difference < 30){
                    //     $refund = $this->cancelTicketRepository->cancelBfrThirtyMinutes($bookingId,$booking,$smsData,$emailData,$busId);
                    //     return $refund;        
                    // }
                    //////////
                    if($cancelDate >= $bookingDate || $interval < 12)
                    {
                        return 'Cancellation is not allowed'; 
                    }

                    $paidAmount = $booking_detail[0]->booking[0]->payable_amount;

                 if($booking_detail[0]->booking[0]->customerPayment != null){
                    $razorpay_payment_id = $booking_detail[0]->booking[0]->customerPayment->razorpay_id;   
                    $cancelPolicies = $booking_detail[0]->booking[0]->bus->cancellationslabs->cancellationSlabInfo;
                    foreach($cancelPolicies as $cancelPolicy){
                        $duration = $cancelPolicy->duration;
                        $deduction = $cancelPolicy->deduction;
                        $duration = explode("-", $duration, 2);
                        $max= $duration[1];
                        $min= $duration[0];

                        if( $interval > 999){
                            $deduction = 10;//minimum deduction
                            $refund =  $this->cancelTicketRepository->refundPolicy($deduction,$razorpay_payment_id,$bookingId,$booking,$smsData,$emailData,$busId);
                            $refundAmt =  $refund['refundAmount'];
                            $smsData['refundAmount'] = $refundAmt;

                            $emailData['deductionPercentage'] = $deduction;
                            $emailData['refundAmount'] = $refundAmt;
                            $emailData['totalfare'] = $paidAmount;
                            
                            $sendsms = $this->cancelTicketRepository->sendSmsTicketCancel($smsData);
                            if($emailData['email'] != ''){
                                $sendEmailTicketCancel = $this->cancelTicketRepository->sendEmailTicketCancel($emailData);  
                            } 

                            $this->cancelTicketRepository->sendAdminEmailTicketCancel($emailData); 

                             ////////////////////////////CMO SMS SEND ON TICKET CANCEL//////////////
                             $busContactDetails = BusContacts::where('bus_id',$busId)
                             ->where('status','1')
                             ->where('cancel_sms_send','1')
                             ->get('phone');
                             if($busContactDetails->isNotEmpty()){
                                 $contact_number = collect($busContactDetails)->implode('phone',',');
                                 $this->channelRepository->sendSmsTicketCancelCMO($smsData,$contact_number);
                             }
                            return $refund;
                        }
                        elseif($min <= $interval && $interval <= $max){ 
                           
                            $refund = $this->cancelTicketRepository->refundPolicy($deduction,$razorpay_payment_id,$bookingId,$booking,$smsData,$emailData,$busId)
                            ;
                            $refundAmt =  $refund['refundAmount'];
                            $smsData['refundAmount'] = $refundAmt;

                            $emailData['deductionPercentage'] = $deduction;
                            $emailData['refundAmount'] = $refundAmt;
                            $emailData['totalfare'] = $paidAmount;
                           
                            $sendsms = $this->cancelTicketRepository->sendSmsTicketCancel($smsData);
                            if($emailData['email'] != ''){
                                $sendEmailTicketCancel = $this->cancelTicketRepository->sendEmailTicketCancel($emailData);  
                            } 

                            $this->cancelTicketRepository->sendAdminEmailTicketCancel($emailData);  

                             ////////////////////////////CMO SMS SEND ON TICKET CANCEL////////////
                             $busContactDetails = BusContacts::where('bus_id',$busId)
                             ->where('status','1')
                             ->where('cancel_sms_send','1')
                             ->get('phone');
                             if($busContactDetails->isNotEmpty()){
                                 $contact_number = collect($busContactDetails)->implode('phone',',');
                                 $this->channelRepository->sendSmsTicketCancelCMO($smsData,$contact_number);
                             }
                            return $refund;    
                        }
                    } 
                 } else{
                    $refund = $this->cancelTicketRepository->cancel($bookingId,$booking,$smsData,$emailData,$busId)
                            ; 
                    return $refund;  
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
}