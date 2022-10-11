<?php

namespace App\Repositories;
use Illuminate\Http\Request;
use App\Models\Bus;
use App\Models\Location;
use App\Models\Users;
use Illuminate\Support\Facades\Log;
use App\Models\Booking;
use App\Models\BookingDetail;
use App\Models\BusSeats;
use App\Models\TicketPrice;
use App\Models\Credentials;
use App\Models\CustomerPayment;
use App\Models\CancellationSlab;
use App\Models\CancellationSlabInfo;
use App\Repositories\ChannelRepository;
use App\Models\BusContacts;
use Carbon\Carbon;
use Razorpay\Api\Api;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Config;
use DateTime;
use App\Transformers\DolphinTransformer;


class CancelTicketRepository
{
    protected $bus;
    protected $ticketPrice;
    protected $location;
    protected $users;
    protected $booking;
    protected $busSeats;
    protected $bookingDetail;
    protected $credentials;
    protected $customerPayment;
    protected $cancellationSlab;
    protected $cancellationSlabInfo;
    protected $channelRepository; 
    protected $dolphinTransformer;


    public function __construct(Bus $bus,TicketPrice $ticketPrice,Location $location,Users $users,BusSeats $busSeats,Booking $booking,BookingDetail $bookingDetail,ChannelRepository $channelRepository,Credentials $credentials,CustomerPayment $customerPayment,CancellationSlab $cancellationSlab,CancellationSlabInfo $cancellationSlabInfo,DolphinTransformer $dolphinTransformer)
    {
        $this->bus = $bus;
        $this->ticketPrice = $ticketPrice;
        $this->location = $location;
        $this->users = $users;
        $this->busSeats = $busSeats;
        $this->booking = $booking;
        $this->bookingDetail = $bookingDetail;
        $this->channelRepository = $channelRepository; 
        $this->credentials = $credentials;
        $this->customerPayment = $customerPayment;
        $this->cancellationSlab = $cancellationSlab;
        $this->cancellationSlabInfo = $cancellationSlabInfo;
        $this->dolphinTransformer = $dolphinTransformer;

    } 
    
    public function GetLocationName($location_id){
        return $this->location->where('id',$location_id)->first()->name;
    }

    public function sendSmsTicketCancel($smsData){
        return $this->channelRepository->sendSmsTicketCancel($smsData);
    }

    public function sendEmailTicketCancel($emailData){
       return $this->channelRepository->sendEmailTicketCancel($emailData);
    }

    public function sendAdminEmailTicketCancel($emailData){
        return $this->channelRepository->sendAdminEmailTicketCancel($emailData);
     }

    

    public function GetBooking($bookingId){
        return $this->booking->find($bookingId);
    }

    public function getPnrInfo($pnr){

        return $this->booking->where("pnr",$pnr)->first();
        
    }

    public function DolphinCancelTicket($phone,$pnr,$booked){

        $ar= $this->users->where('phone',$phone)->with(["booking" => function($u) use($pnr,$booked){
            $u->where([
                ['booking.pnr', '=', $pnr],
                ['status', '=', $booked],
            ]);           
            $u->with(["customerPayment" => function($b){
                $b->where('payment_done',1);
            }]);        
              $u->with('bookingDetail'); 
        }])->get();

        $bus["bus_number"]=$ar[0]->booking[0]->bus_number;      
        $bus["bus_description"]="";      
        $bus["name"]=$ar[0]->booking[0]->bus_name; 


        $cancellationslabs=$this->dolphinTransformer->GetCancellationPolicy();

        $cancellationslabsInfo=[];

        if($cancellationslabs){
            foreach($cancellationslabs as $p){
   
                $plc["duration"]=$p->duration;
                $plc["deduction"]=(int)$p->deduction;

                $cancellationslabsInfo[]=$plc;       
            }         
           } 

        $bus["cancellationslabs"]["cancellation_slab_info"]=$cancellationslabsInfo;
        $bus["bus_type"]["name"]='';
        $bus["bus_type"]["bus_class"]=[
            "class_name" => ""
        ];

        $bus["bus_sitting"]["name"]=""; 
        $bus["bus_contacts"]["phone"]=""; 

        $ar[0]->booking[0]['bus']= $bus;

        $bookingDetail=$ar[0]->booking[0]->bookingDetail;

        foreach($bookingDetail as $k => $bd){
            
            $st["seatText"]=$bd->seat_name;  
            $stx["seats"]= $st;            
            $ar[0]->booking[0]['bookingDetail'][$k]["bus_seats"]=$stx;
            
        }

        return $ar;

     }


     public function   updateCancelTicketDolphin($arr,$id){
        $this->booking->where('id', $id)->update($arr);  
     }
    
    public function cancelTicket($phone,$pnr,$booked)
    { 
        return $this->users->where('phone',$phone)->with(["booking" => function($u) use($pnr,$booked){
            $u->where([
                ['booking.pnr', '=', $pnr],
                ['status', '=', $booked],
            ]);
            //$u->where('booking.pnr', '=', $pnr); 
            $u->with(["customerPayment" => function($b){
                $b->where('payment_done',1);
            }]);           
            $u->with(["bus" => function($bs){
                $bs->with('cancellationslabs.cancellationSlabInfo');
              }]);          
            $u->with(["bookingDetail" => function($b){
                $b->with(["busSeats" => function($s){
                    $s->with("seats");
                  }]);
            }]);    
        }])->get();
    }


    public function cancel($bookingId,$booking,$smsData,$emailData,$busId){

        $bookingCancelled = Config::get('constants.BOOKED_CANCELLED');

        $emailData['refundAmount'] = 0;
        $emailData['deductionPercentage'] = 100;
        $emailData['totalfare'] = $booking->total_fare;
 
        $this->booking->where('id', $bookingId)->update(['status' => $bookingCancelled, 'refund_amount' => 0, 'deduction_percent' => 100]);      
                
        $booking->bookingDetail()->where('booking_id', $bookingId)->update(array('status' => $bookingCancelled));   
        
        $smsData['refundAmount'] = 0;

        $sendsms = $this->channelRepository->sendSmsTicketCancel($smsData);
              
        if($emailData['email'] != ''){
            $sendEmailTicketCancel = $this->channelRepository->sendEmailTicketCancel($emailData);  
        } 
        ////////////// send to admin /////////////

        $this->channelRepository->sendAdminEmailTicketCancel($emailData);

        ////////////////////////////CMO SMS SEND ON TICKET CANCEL/////////////////////////////////
        $busContactDetails = BusContacts::where('bus_id',$busId)
                                        ->where('status','1')
                                        ->where('cancel_sms_send','1')
                                        ->get('phone');
        if($busContactDetails->isNotEmpty()){
            $contact_number = collect($busContactDetails)->implode('phone',',');
            $this->channelRepository->sendSmsTicketCancelCMO($smsData,$contact_number);
        }

        $data = array(
             'refundAmount' => 0,
             'paidAmount' => $booking->total_fare,
        );
        return $data;
    }
    public function cancelBfrThirtyMinutes($bookingId,$booking,$smsData,$emailData,$busId){

        $bookingCancelled = Config::get('constants.BOOKED_CANCELLED');

        $emailData['refundAmount'] = $booking->total_fare;
        $emailData['deductionPercentage'] = 0;
        $emailData['totalfare'] = $booking->total_fare;
 
        $this->booking->where('id', $bookingId)->update(['status' => $bookingCancelled, 'refund_amount' => $booking->total_fare, 'deduction_percent' => 0]);      
                
        $booking->bookingDetail()->where('booking_id', $bookingId)->update(array('status' => $bookingCancelled));   
        
        $smsData['refundAmount'] = $booking->total_fare;

        $sendsms = $this->channelRepository->sendSmsTicketCancel($smsData);
              
        if($emailData['email'] != ''){
            $sendEmailTicketCancel = $this->channelRepository->sendEmailTicketCancel($emailData);  
        } 
        ////////////// send to admin /////////////

        $this->channelRepository->sendAdminEmailTicketCancel($emailData);

        ////////////////////////////CMO SMS SEND ON TICKET CANCEL/////////////////////////////////
        $busContactDetails = BusContacts::where('bus_id',$busId)
                                        ->where('status','1')
                                        ->where('cancel_sms_send','1')
                                        ->get('phone');
        if($busContactDetails->isNotEmpty()){
            $contact_number = collect($busContactDetails)->implode('phone',',');
            $this->channelRepository->sendSmsTicketCancelCMO($smsData,$contact_number);
        }

        $data = array(
             'refundAmount' => $booking->total_fare,
             'paidAmount' => $booking->total_fare,
        );
        return $data;
    }

    public function DolphinCancelUpdate($percentage,$razorpay_payment_id,$bookingId,$booking,$smsData,$emailData,$busId,$refundAmount){

        $bookingCancelled = Config::get('constants.BOOKED_CANCELLED');
        $refunded = Config::get('constants.REFUNDED');
       
        $key = $this->credentials->first()->razorpay_key;
        $secretKey = $this->credentials->first()->razorpay_secret;
       
        $api = new Api($key, $secretKey);
        $payment = $api->payment->fetch($razorpay_payment_id);
       
        $paidAmount = $this->booking->where('id', $bookingId)->first()->total_fare; //$payment->amount;
        $paymentStatus = $payment->status;
        $refundStatus = $payment->refund_status;
        $ownerFare = $this->booking->where('id', $bookingId)->first()->owner_fare;
        $odbusCharges = $this->booking->where('id', $bookingId)->first()->odbus_charges;
        $baseFare = $ownerFare + $odbusCharges; 
      
       // if($paymentStatus == 'captured'){
            if($refundStatus != null){
                return 'refunded';
            }
            else{
                $refundAmount =$refundAmount; //round($baseFare * ((100-$percentage) / 100),2);
                $data = array(
                     'refundAmount' => $refundAmount,
                     'paidAmount' => $paidAmount/100,
                );              
                $refundAmt = $refundAmount;
                $paidAmount = $paidAmount/100;
                $smsData['refundAmount'] = $refundAmt;  
                $this->booking->where('id', $bookingId)->update(['status' => $bookingCancelled, 'refund_amount' => $refundAmt, 'deduction_percent' => $percentage]);
                $booking->bookingDetail()->where('booking_id', $bookingId)->update(array('status' => $bookingCancelled));
                $this->customerPayment->where('razorpay_id', $razorpay_payment_id)->update(['payment_done' => $refunded]);
                return $data;
            } 
       }

    public function refundPolicy($percentage,$razorpay_payment_id,$bookingId,$booking,$smsData,$emailData,$busId){

        $bookingCancelled = Config::get('constants.BOOKED_CANCELLED');
        $refunded = Config::get('constants.REFUNDED');
       
        $key = $this->credentials->first()->razorpay_key;
        $secretKey = $this->credentials->first()->razorpay_secret;
       
        $api = new Api($key, $secretKey);
        $payment = $api->payment->fetch($razorpay_payment_id);
       
        $paidAmount = $payment->amount;
        $paymentStatus = $payment->status;
        //$refundStatus = $payment['refund_status'];
        $refundStatus = $payment->refund_status;
        $ownerFare = $this->booking->where('id', $bookingId)->first()->owner_fare;
        $odbusCharges = $this->booking->where('id', $bookingId)->first()->odbus_charges;
        $baseFare = $ownerFare + $odbusCharges; 
      
       // if($paymentStatus == 'captured'){
            if($refundStatus != null){
                return 'refunded';
            }
            else{

                $refundAmount = round($baseFare * ((100-$percentage) / 100),2);
                //$refundAmount = round($paidAmount * ((100-$percentage) / 100),2);

                //$refund = $api->refund->create(array('payment_id' => $razorpay_payment_id, 'amount'=> $refundAmount));
                
                //$refundId = $refund->id;
                //$refundStatus = $refund->status;
                //$refundAmount = $refund->amount;

                // $this->customerPayment->where('razorpay_id', $razorpay_payment_id)->update(['payment_done' => $refunded,'refund_id' => $refundId]);

                $data = array(
                    //  'refundStatus' => $refundStatus,
                    //  'refund_id' => $refundId,
                     'refundAmount' => $refundAmount,
                     'paidAmount' => $paidAmount/100,
                );
                //$refundAmt = round(($refundAmount),2);
                $refundAmt = $refundAmount;
                $paidAmount = $paidAmount/100;
                $smsData['refundAmount'] = $refundAmt;
  
                $this->booking->where('id', $bookingId)->update(['status' => $bookingCancelled, 'refund_amount' => $refundAmt, 'deduction_percent' => $percentage]);      
                
                $booking->bookingDetail()->where('booking_id', $bookingId)->update(array('status' => $bookingCancelled));

                $this->customerPayment->where('razorpay_id', $razorpay_payment_id)->update(['payment_done' => $refunded]);

                // $sendsms = $this->channelRepository->sendSmsTicketCancel($smsData);
              
                // $emailData['refundAmount'] = $refundAmt;
                // $emailData['deductionPercentage'] = $percentage;
                // $emailData['totalfare'] = $paidAmount;
                // if($emailData['email'] != ''){
                //     $sendEmailTicketCancel = $this->channelRepository->sendEmailTicketCancel($emailData);  
                // } 
////////////////////////////CMO SMS SEND ON TICKET CANCEL/////////////////////////////////
        $busContactDetails = BusContacts::where('bus_id',$busId)
                                        ->where('status','1')
                                        ->where('cancel_sms_send','1')
                                        ->get('phone');
        if($busContactDetails->isNotEmpty()){
            $contact_number = collect($busContactDetails)->implode('phone',',');
            //$this->channelRepository->sendSmsTicketCancelCMO($smsData,$contact_number);
        }

////////////////////////////////////////////////////////////////////////////////////////////////
                return $data;
            } 
       }
}