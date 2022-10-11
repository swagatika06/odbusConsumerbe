<?php

namespace App\Services;
use Illuminate\Http\Request;
use App\Models\Coupon;
use App\Models\Seats;
use App\Repositories\BookTicketRepository;
use App\Repositories\OfferRepository;
use App\Services\ListingService;
use App\Services\ViewSeatsService;
use App\Models\Location;
use Exception;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Log;
use InvalidArgumentException;
use Illuminate\Support\Arr;
use App\Transformers\DolphinTransformer;


class BookTicketService
{
    
    protected $bookTicketRepository; 
    protected $listingService; 
    protected $viewSeatsService; 
    protected $dolphinTransformer;

    public function __construct(BookTicketRepository $bookTicketRepository,OfferRepository $offerRepository,ListingService $listingService,ViewSeatsService $viewSeatsService,DolphinTransformer $dolphinTransformer)
    {
        $this->bookTicketRepository = $bookTicketRepository;
        $this->offerRepository = $offerRepository;
        $this->listingService = $listingService;
        $this->viewSeatsService = $viewSeatsService;
        $this->dolphinTransformer = $dolphinTransformer;

    }
    public function bookTicket($request,$clientRole,$clientId)
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
            
            $needGstBill = Config::get('constants.NEED_GST_BILL');
            $customerInfo = $request['customerInfo'];

            $existingUser = $this->bookTicketRepository->CheckExistingUser($customerInfo['phone']); 
                if($existingUser==true){
                    $userId = $this->bookTicketRepository->GetUserId($customerInfo['phone']);

                    $this->bookTicketRepository->UpdateInfo($userId,$customerInfo);       
                }
                else{
                    $userId = $this->bookTicketRepository->CreateUser($request['customerInfo']);   
                }
                
                $bookingInfo = $request['bookingInfo'];
                ////////////////////////busId validation////////////////////////////////////
                $sourceID = $bookingInfo['source_id'];
                $destinationID = $bookingInfo['destination_id'];
                $source = Location::where('id',$sourceID)->first()->name;
                $destination = Location::where('id',$destinationID)->first()->name;

                if($origin == 'ODBUS'){
                    $reqInfo= array(
                        "source" => $source,
                        "destination" => $destination,
                        "entry_date" => $bookingInfo['journey_date'],
                        "bus_operator_id" => Null,
                        "user_id" => Null
                    ); 
    
                    $busRecords = $this->listingService->getAll($reqInfo,$clientRole,$clientId);
                
                    if($busRecords){
                    $busId = $bookingInfo['bus_id'];
                    $busRecords->pluck('busId');
                    $validBus = $busRecords->pluck('busId')->contains($busId);
                    }
                    if(!$validBus){
                        return "Bus_not_running";
                    }
                }
                /////////////////////price related changes from(ODBUS)//////////////////
                if($origin == 'ODBUS'){
                    $bookingDetail = $request['bookingInfo']['bookingDetail'];//in request passing seats_id with key as bus_seats_id
                    $seatIds = Arr::pluck($bookingDetail, 'bus_seats_id');
                    $seater = Seats::whereIn('id',$seatIds)->where('berthType',1)->pluck('id');
                    $sleeper = Seats::whereIn('id',$seatIds)->where('berthType',2)->pluck('id');
                    $entry_date = $bookingInfo['journey_date'];
                    $busId = $bookingInfo['bus_id'];
                    $sourceId = $bookingInfo['source_id'];
                    $destinationId =  $bookingInfo['destination_id'];
                    
                    $data = array(
                        'busId' => $busId,
                        'sourceId' => $sourceId,
                        'destinationId' => $destinationId,
                        'seater' => $seater,
                        'sleeper' => $sleeper,
                        'entry_date' => $entry_date,
                    );
                   
                    $priceDetails = $this->viewSeatsService->getPriceCalculationOdbus($data,$clientId);
                }

                /////////// get seat price for dolphin


                if($origin=='DOLPHIN'){
                    $bookingDetail = $request['bookingInfo']['bookingDetail'];//in request passing seats_id with key as bus_seats_id
                    $seatIds = Arr::pluck($bookingDetail, 'bus_seats_id');

                    $entry_date = $bookingInfo['journey_date'];
                    $busId = $bookingInfo['bus_id'];
                    $sourceId = $bookingInfo['source_id'];
                    $destinationId =  $bookingInfo['destination_id'];

                    $seatTypArr=$this->dolphinTransformer->GetSeatType($ReferenceNumber,$seatIds,$clientRole,$clientId);
                    
                    $data = array(
                        'busId' => $busId,
                        'sourceId' => $sourceId,
                        'destinationId' => $destinationId,
                        'seater' => $seatTypArr['seater'],
                        'sleeper' => $seatTypArr['sleeper'],
                        'entry_date' => $entry_date,
                        'ReferenceNumber' => $ReferenceNumber,
                        'origin' => $origin,
                    );

                    $priceDetails= $this->viewSeatsService->getPriceOnSeatsSelection($data,$clientRole,$clientId);

                    Log::info($priceDetails);
                }
                ///////////////////////////////////////////////////////////////
                //Save Booking 
                $booking = $this->bookTicketRepository->SaveBooking($bookingInfo,$userId,$needGstBill,$priceDetails,$clientRole,$clientId);   
               
                /////////////auto apply coupon//////////

            if($bookingInfo['origin'] == 'ODBUS'){ 

                $bcollection = collect($bookingInfo);
                $bcollection->put('transaction_id', $booking['transaction_id']);
                $couponDetails = Coupon::where('coupon_code',$bookingInfo['coupon_code'])
                                 ->where('bus_id',$bookingInfo['bus_id'])
                                 ->where('status','1')
                                 ->get();
                if(isset($couponDetails[0]) && ($couponDetails[0]->auto_apply==1)){
                    
                    $coupon = $this->offerRepository->coupons($bcollection);
        
                    if(collect($coupon)->has("totalAmount")){
                        return collect($booking)->merge(collect($coupon));
                    }else{
                        return collect($booking)->put('couponStatus', $coupon);
                    }     
                } 
            }      
                return $booking; 

        } catch (Exception $e) {

            Log::info($e->getMessage());
            throw new InvalidArgumentException(Config::get('constants.INVALID_ARGUMENT_PASSED'));
        }
    }   
   
}