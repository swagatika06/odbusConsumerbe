<?php

namespace App\Services;
use Illuminate\Http\Request;
use App\Models\Coupon;
use App\Models\BusSeats;
use App\Models\BookingSeized;
use App\Models\BusCancelled;
use App\Models\BusCancelledDate;
use App\Models\ClientFeeSlab;
use App\Models\ManageClientOperator;
use App\Repositories\ListingRepository;
use App\Repositories\CommonRepository;
use App\Repositories\ViewSeatsRepository;
use Exception;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use InvalidArgumentException;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Config;
use Carbon\Carbon;
use DateTime;
use App\Transformers\DolphinTransformer;

class ListingService
{
    
    protected $listingRepository; 
    protected $commonRepository;  
    protected $dolphinTransformer;
    
    public function __construct(ListingRepository $listingRepository,CommonRepository $commonRepository,ViewSeatsRepository $viewSeatsRepository,DolphinTransformer $dolphinTransformer)
    {
        $this->listingRepository = $listingRepository;
        $this->commonRepository = $commonRepository;
        $this->viewSeatsRepository = $viewSeatsRepository;
        $this->dolphinTransformer = $dolphinTransformer;
    }
    public function getAll($request,$clientRole,$clientId)
    {  
        $source = $request['source'];
        $destination = $request['destination'];
        $entry_date = $request['entry_date'];
        $busOperatorId = $request['bus_operator_id'];
        $userId = $request['user_id'];
        $entry_date = date("Y-m-d", strtotime($entry_date));
        
        $path= $this->commonRepository->getPathurls();
        $path= $path[0];

        $srcResult= $this->listingRepository->getLocationID($request['source']);
        $destResult= $this->listingRepository->getLocationID($request['destination']);

        if($srcResult->count()==0 || $destResult->count()==0)
           return "";

         $sourceID =  $srcResult[0]->id;
         $destinationID =  $destResult[0]->id;    

         $selCouponRecords = $this->listingRepository->getAllCoupon();
         $busDetails = $this->listingRepository->getticketPrice($sourceID,$destinationID,$busOperatorId,$entry_date, $userId); 
         //return $busDetails;


         $DolPhinshowRecords = [];
         $DolPhinShowSoldoutRecords =[];



         //if($clientId!=44 && $clientRole!=6){ // to stop dolphin bus in android until madhu completed work

            $dolphinresult= $this->dolphinTransformer->BusList($request,$clientRole,$clientId); // getting dolphin buslist

            $DolPhinshowRecords = (isset($dolphinresult['regular'])) ? $dolphinresult['regular'] : [];
            $DolPhinShowSoldoutRecords = (isset($dolphinresult['soldout'])) ? $dolphinresult['soldout'] : [];
        // }

       

        //$CurrentDateTime = "2022-01-11 14:48:35";
        $CurrentDateTime = Carbon::now();//->toDateTimeString();


        $common=$this->commonRepository->getCommonSettings(Config::get('constants.USER_ID'));

        $sortar=[];
        

        if($common[0]->bus_list_sequence==1){
        $sortar= ['startingFromPrice', 'asc'];
        }

        else if($common[0]->bus_list_sequence==2){
        $sortar=['departureTime', 'asc'];   
        }
        else if($common[0]->bus_list_sequence==3){
        $sortar=['totalSeats', 'desc'];
        } 

        else{
            $sortar=['departureTime', 'asc']; 
        } 

        
        $ListingRecords = array();

        if(isset($busDetails[0])){
            $records = array();
            $showBusRecords = [];
            $hideBusRecords = [];
     
            foreach($busDetails as $busDetail)
            {
                $ticketPriceId = $busDetail['id'];
                $busId = $busDetail['bus_id'];
                $startJDay = $busDetail['start_j_days'];
                $JDay =  $busDetail->j_day;
                
            ////////////////bus cancelled on specific date//////////////////////
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
                    continue;
                }
    
            /////////////////Bus Seize//////////////////////////////////////////////

            $seizedTime = $busDetail['seize_booking_minute'];
            $depTime = date("H:i:s", strtotime($busDetail['dep_time']));  
            $depDateTime = Carbon::createFromFormat('Y-m-d H:i:s', $entry_date.' '.$depTime);
            if($depDateTime>=$CurrentDateTime){
                $diff_in_minutes = $depDateTime->diffInMinutes($CurrentDateTime);
            }else{
                $diff_in_minutes = 0;
            }
            /////////////////////////day wise seize time change///////////////////
                $dayWiseSeizeTime = BookingSeized::where('ticket_price_id',$ticketPriceId)
                                              ->where('bus_id', $busId)
                                              ->where('seized_date', $entry_date)
                                              ->get('seize_booking_minute');  
                                  
                if(!$dayWiseSeizeTime->isEmpty())
                { 
                    $dWiseSeizeTime = $dayWiseSeizeTime[0]->seize_booking_minute;
                    if($dWiseSeizeTime < $diff_in_minutes){
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
                         $busEntryPresent =$this->listingRepository->checkBusentry($busId,$new_date);
                         if(isset($busEntryPresent[0]) && $busEntryPresent[0]->busScheduleDate->isNotEmpty()){ 
                            $records[] = $this->listingRepository->getBusData($busOperatorId,$busId,$userId,$entry_date);
                         } 
                    }
                    else
                    {
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
                         $busEntryPresent =$this->listingRepository->checkBusentry($busId,$new_date);
                         if(isset($busEntryPresent[0]) && $busEntryPresent[0]->busScheduleDate->isNotEmpty()){
                            
                            $hideBusRecords[] = $this->listingRepository->getBusData($busOperatorId,$busId,$userId,$entry_date);
                         }
      
                    }
                }
               elseif($seizedTime < $diff_in_minutes)
               {
                    switch($startJDay)
                    {
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
                    $busEntryPresent =$this->listingRepository->checkBusentry($busId,$new_date);
                    if(isset($busEntryPresent[0]) && $busEntryPresent[0]->busScheduleDate->isNotEmpty())
                    {
                        
                        $records[] = $this->listingRepository->getBusData($busOperatorId,$busId,$userId,$entry_date);
                       // return $records;
                    } 
                }
                   else
                   {
                    switch($startJDay)
                    {
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
                    $busEntryPresent =$this->listingRepository->checkBusentry($busId,$new_date);
                    if(isset($busEntryPresent[0]) && $busEntryPresent[0]->busScheduleDate->isNotEmpty()){
                        $hideBusRecords[] = $this->listingRepository->getBusData($busOperatorId,$busId,$userId,$entry_date);
                    }
                   }
            } 
            $showBusRecords = Arr::flatten($records);
            $hideBusRecords = Arr::flatten($hideBusRecords);
            //return $showBusRecords;
            $showRecords = $this->processBusRecords($showBusRecords,$sourceID, $destinationID,$entry_date,$path,$selCouponRecords,$busOperatorId,$busId,'show',$clientRole,$clientId);

            $ShowSoldoutRecords = (isset($showRecords['soldout'])) ? $showRecords['soldout'] : [];
            $showRecords = (isset($showRecords['regular'])) ? $showRecords['regular'] : [];
           

            if(count($hideBusRecords) > 0){
               $hideRecords =  $this->processBusRecords($hideBusRecords,$sourceID, $destinationID,$entry_date,$path,$selCouponRecords,$busOperatorId,$busId,'hide',$clientRole,$clientId);
               // $ListingRecords = collect($showRecords)->concat(collect($hideRecords));
               $HideSoldoutRecords = (isset($hideRecords['soldout'])) ? $hideRecords['soldout'] : [];
               $hideRecords = (isset($hideRecords['regular'])) ? $hideRecords['regular'] : [];

               // $ListingRecords = collect($showRecords)->concat(collect($hideRecords));
               // $showRecords = collect($showRecords)->sortBy([ $sortar]);
                $showRecords = collect($showRecords)->concat(collect($DolPhinshowRecords))->sortBy([ $sortar]);
                $hideRecords = collect($hideRecords)->sortBy([$sortar]);
                $soldoutRecords = collect($ShowSoldoutRecords)->concat(collect($HideSoldoutRecords));
                $ListingRecords = $showRecords->concat($soldoutRecords)->concat(collect($DolPhinShowSoldoutRecords));
                $ListingRecords = $ListingRecords->concat($hideRecords);
            }else{
                $ListingRecords = collect($showRecords)->concat(collect($DolPhinshowRecords))->sortBy([
                    $sortar
                 ]);

               $ListingRecords = $ListingRecords->concat(collect($ShowSoldoutRecords))->concat(collect($DolPhinShowSoldoutRecords));
            } 
         }
         
         else{
            $ListingRecords= collect($ListingRecords)->concat(collect($DolPhinshowRecords))->sortBy([
                $sortar
                ]);

             $ListingRecords = $ListingRecords->concat(collect($DolPhinShowSoldoutRecords));
        }
        return $ListingRecords;  

    }
    public function processBusRecords($records,$sourceID,$destinationID,$entry_date,$path,$selCouponRecords,$busOperatorId,$busId,$flag,$clientRole,$clientId){


        $ListingRecords['regular'] = [];
        $ListingRecords['soldout'] = [];

       
        $ListingRecords = array();
        $clientRoleId = Config::get('constants.CLIENT_ROLE_ID');

        foreach($records as $record){
            //return $record;
            $unavailbleSeats = 0;
            $busId = $record->id; 
            $user_id = $record->user_id; 
            $busName = $record->name;
            $popularity = $record->popularity;
            $busNumber = $record->bus_number;
            $via = $record->via;
            $busOperatorId = $record->bus_operator_id;


            $routeCoupon = $this->listingRepository->getrouteCoupon($sourceID,$destinationID,$busId,$entry_date);
            if(isset($routeCoupon[0]))
            {                           
               $routeCouponCode = $routeCoupon[0]->coupon_code;//route wise coupon
            }else
            {
               $routeCouponCode =[];
            }  
 
            $operatorCoupon = $this->listingRepository->getOperatorCoupon($busOperatorId,$busId,$entry_date);
            if(isset($operatorCoupon[0]))
            {                           
                $opCouponCode = $operatorCoupon[0]->coupon_code;//operator wise coupon
            }else
            {
                $opCouponCode =[];
            } 
            $opRouteCoupon = $this->listingRepository->getOpRouteCoupon($busOperatorId,$sourceID,$destinationID,$busId,$entry_date);

            if(isset($opRouteCoupon[0]))
            {                           
                $opRouteCouponCode = $opRouteCoupon[0]->coupon_code;//operatorRoute wise coupon
            }else
            {
                $opRouteCouponCode =[];
            }

            // $busCoupon = $this->listingRepository->getBusCoupon($busId);
            // if(isset($busCoupon[0]))
            // {                           
            //     $busCouponCode = $busCoupon[0]->coupon_code;//bus wise coupon
            // }else
            // {
            //     $busCouponCode =[];
            // } 

            $CouponRecords = collect([$opRouteCouponCode,$opCouponCode,$routeCouponCode]);
            
            //$CouponRecords = collect($busCouponCode);
            $CouponRecords = $CouponRecords->flatten()->unique()->values()->all();

            ///Coupon applicable for specific date range
            $appliedCoupon = collect([]);
            $CouponDetails = [];
            $date = Carbon::now();
            $bookingDate = $date->toDateString();
            //$bookingDate = "2022-06-06";
            foreach($CouponRecords as $key => $coupon){
                
                $type = $selCouponRecords->where('coupon_code',$coupon)->first()->valid_by;
                switch($type){
                    case(1):    //Coupon available on journey date
                        $dateInRange = $selCouponRecords->where('coupon_code',$coupon)
                                                        ->where('from_date', '<=', $entry_date)
                                                        ->where('to_date', '>=', $entry_date)->all();
                        if(isset($selCouponRecords)){  
                                    $CouponDetails = $selCouponRecords[0]
                                                    ->where('coupon_code',$coupon)->get();
                        } 
                        $appliedCoupon->push($coupon);                             
                        break;
                    case(2):    //Coupon available on booking date
                        $dateInRange = $selCouponRecords->where('coupon_code',$coupon)
                                                        ->where('from_date', '<=', $bookingDate)
                                                        ->where('to_date', '>=', $bookingDate)->all();
                         if(isset($selCouponRecords)){  
                                    $CouponDetails = $selCouponRecords[0]
                                                    ->where('coupon_code',$coupon)->get();
                        } 
                        $appliedCoupon->push($coupon);                                                               
                        break;      
                }
               
                // if($dateInRange){
                //     $appliedCoupon->push($coupon);
                //     if(isset($selCouponRecords)){
                //         $CouponDetails = $selCouponRecords[0]->where('coupon_code',$appliedCoupon)
                //         ->where('from_date', '<=', $bookingDate)
                //         ->where('to_date', '>=', $bookingDate)
                //         ->get(); 
                //         //return $appliedCoupon;
                //     }

                //  }
            }
            $maxSeatBook = $record->max_seat_book;
            $conductor_number ='';

            if($record->busContacts && isset($record->busContacts->phone)){
               $conductor_number = $record->busContacts->phone;
            }
            
            $operatorId = $record->busOperator->id;
            $operatorUrl = $record->busOperator->operator_url;
            $operatorName = $record->busOperator->operator_name;
            $sittingType = $record->BusSitting->name;   
            $bus_description = $record->bus_description; 
            $busType = $record->BusType->busClass->class_name;
            $busTypeName = $record->BusType->name;
            $ticketPriceDatas = $record->ticketPrice->where("status","1");
            
            $ticketPriceRecords = $ticketPriceDatas
                    ->where('source_id', $sourceID)
                    ->where('destination_id', $destinationID)
                    ->first(); 
            $ticketPriceId = $ticketPriceRecords->id;
            ////owner/special/festive fare with service charges added to base fare////////////
           
            $baseFare = $ticketPriceRecords->base_seat_fare; 
            $miscfares = $this->viewSeatsRepository->miscFares($busId,$entry_date);
            $totalMiscfares = $miscfares[0]+$miscfares[2]+$miscfares[4];
            $misBaseFare = $baseFare + $totalMiscfares; 
            $ticketFareSlabs = $this->viewSeatsRepository->ticketFareSlab($user_id);
            $odbusServiceCharges = 0;
            foreach($ticketFareSlabs as $ticketFareSlab){

                $startingFare = $ticketFareSlab->starting_fare;
                $uptoFare = $ticketFareSlab->upto_fare;
                if($startingFare <= $misBaseFare && $uptoFare >= $misBaseFare){
                    $percentage = $ticketFareSlab->odbus_commision;
                    $odbusServiceCharges = round($misBaseFare * ($percentage/100));
                    $startingFromPrice = round($misBaseFare + $odbusServiceCharges);
                }     
            }  
            //$startingFromPrice = $ticketPriceRecords->base_seat_fare;
            $departureTime = $ticketPriceRecords->dep_time;
            $arrivalTime = $ticketPriceRecords->arr_time;
            $depTime = date("H:i",strtotime($departureTime));
            $arrTime = date("H:i",strtotime($arrivalTime)); 
            $arr_time = new DateTime($arrivalTime);
            $dep_time = new DateTime($departureTime);
            $totalTravelTime = $dep_time->diff($arr_time);
            $totalJourneyTime = ($totalTravelTime->format("%a") * 24) + $totalTravelTime->format(" %h"). "h". $totalTravelTime->format(" %im");

       $extraSeatsOpen = $record->busSeats 
                               ->where('bus_id',$busId)
                               ->where('status',1)
                               ->where('ticket_price_id',$ticketPriceId)
                               ->where('duration','>',0)
                               ->pluck('seats_id'); 
       $seizedTime = $record->busSeats
                               ->where('bus_id',$busId)
                               ->where('status',1)
                               ->where('ticket_price_id',$ticketPriceId)
                               ->where('duration','>',0)
                               ->pluck('duration');

       $extraSeatsBlock = $record->busSeats->where('bus_id',$busId)
                                   ->where('status',1)
                                   ->where('ticket_price_id',$ticketPriceId)
                                   ->where('duration','=',0)
                                   ->where('operation_date',$entry_date)
                                   ->where('type',null)
                                   ->pluck('seats_id');

         ///Seats blocked prior to journey date////////                           
         $oldExtraSeatsBlock = BusSeats::where('bus_id',$busId)
                                    ->where('status',1)
                                    ->where('ticket_price_id',$ticketPriceId)
                                    ->where('duration','=',0)
                                    ->where('operation_date','<' ,$entry_date)
                                    ->where('type',null)
                                    ->pluck('seats_id');                           
                                                    
        $ActualExtraSeatsOpen = ($extraSeatsOpen->diff($extraSeatsBlock))->values();


       //$CurrentDateTime = "2022-01-05 16:48:35";
       $dep_Time = date("H:i:s", strtotime($departureTime));
       $CurrentDateTime = Carbon::now();//->toDateTimeString();
       $depDateTime = Carbon::createFromFormat('Y-m-d H:i:s', $entry_date.' '.$dep_Time);
       if($depDateTime>=$CurrentDateTime){
           $diff_in_minutes = $depDateTime->diffInMinutes($CurrentDateTime);
       }else{
           $diff_in_minutes = 0;
       }
    
       /////seat close/////
       ///////////////////////////////////
               
                $blockSeats = $record->busSeats
                                        ->where('ticket_price_id',$ticketPriceId)
                                        ->where('operation_date',$entry_date)
                                        ->where('bus_id',$busId)
                                        ->where('type',2)                              
                                        ->pluck('seats_id');
                $unavailbleSeats = $record->busSeats
                                ->where('ticket_price_id',$ticketPriceId)
                                ->where('bus_id',$busId)
                                ->where('type',1)
                                ->where('operation_date','!=',$entry_date)                              
                                ->pluck('seats_id')
                                ->unique();

                $availableSeatsOnDate = $record->busSeats
                                ->where('ticket_price_id',$ticketPriceId)
                                ->where('bus_id',$busId)
                                ->where('type',1)
                                ->where('operation_date',$entry_date)                              
                                ->pluck('seats_id')
                                ->unique();
 
                if(isset($availableSeatsOnDate) && $availableSeatsOnDate->isNotEmpty()){
                    $unavailbleSeats = collect($unavailbleSeats)->diff(collect($availableSeatsOnDate));

                }
                $moreAddedSeats = $record->busSeats->whereNull('operation_date')    
                                                    ->whereNull('type')
                                                    ->where('bus_id',$busId)
                                                    ->whereIn('seats_id',$unavailbleSeats)
                                                    ->where('status',1)
                                                    ->where('ticket_price_id',$ticketPriceId)
                                                    ->pluck('seats_id');

                ////////////////////////////seat block check for all dates//////////////////////////////
                $blockSeatsOnAllDates = BusSeats::where('type',2)
                                                ->where('bus_id',$busId)
                                                ->where('status',1)
                                                ->where('ticket_price_id',$ticketPriceId)
                                                ->pluck('seats_id');   

                $permanentSeats = BusSeats::whereNull('operation_date')
                                            ->where('ticket_price_id',$ticketPriceId)
                                            ->where('bus_id',$busId)
                                            ->where('status',1)
                                            ->pluck('seats_id'); 


                $noMoreavailableSeats = collect($blockSeatsOnAllDates)->diff(collect($permanentSeats));                   

                /////////////////////////////////////////////////////////////////////                                  

            if(isset($moreAddedSeats) && $moreAddedSeats->isNotEmpty()){
                $blockSeats = $blockSeats->concat(collect($unavailbleSeats)->diff(collect($moreAddedSeats)));
            }else{
                $blockSeats = $blockSeats->concat(collect($unavailbleSeats))->concat(collect($noMoreavailableSeats));/////need to check for other options if required
            }                    

       ////////Hide Extra Seats based on seize time///////////////
           if(!$ActualExtraSeatsOpen->isEmpty() && !$seizedTime->isEmpty()){
               if($seizedTime[0] > $diff_in_minutes){
                   $blockSeats = $blockSeats->concat(collect($ActualExtraSeatsOpen));
               }    
           }

       /////////////Blocked Extra Seats on specific date///////////
            $seatClassRecords = 0;
            $sleeperClassRecords = 0;
            $totalSeats = 0;

            if(!$extraSeatsBlock->isEmpty()){
                $blockSeats = $blockSeats->concat(collect($extraSeatsBlock));
            }
        /////////////Check existence of Extra seat closed not in  Permanet seat list/////////
            $oldExtraSeatsBlock = collect($oldExtraSeatsBlock)->diff(collect($permanentSeats));
            if(!$oldExtraSeatsBlock->isEmpty()){ 
                $blockSeats = $blockSeats->concat(collect($oldExtraSeatsBlock));   
            } 
        
            
            $totalSeats = $record->busSeats->where('ticket_price_id',$ticketPriceId)
                                           ->where('bus_id',$busId)
                                           ->where("status","1")
                                           ->whereNotIn('seats_id',$blockSeats)
                                           ->whereNotNull('seats')
                                           ->unique('seats_id')
                                           ->count('id');  
                                      
            $seatClassRecords = $record->busSeats->where('ticket_price_id',$ticketPriceId)
                                          ->where('bus_id',$busId)
                                          ->where("status","1")
                                          ->whereNotIn('seats_id',$blockSeats)
                                          ->where('seats.seat_class_id','==','1')
                                          ->unique('seats_id')
                                          ->count();
            $sleeperClassRecords = $record->busSeats->where('ticket_price_id',$ticketPriceId)
                                          ->where('bus_id',$busId)
                                          ->where("status","1")
                                          ->whereNotIn('seats_id',$blockSeats)
                                          ->whereIn('seats.seat_class_id',[2,3])
                                          ->unique('seats_id')
                                          ->count();     
            $amenityDatas = [];  

           if($record->busAmenities)
           {
               $amenityDatas = [];  
               foreach($record->busAmenities as $k =>  $a){
                   $am_dt=$a;
                   if($am_dt->amenities != NULL)
                   {
                       $amenities_image='';
                       $am_android_image='';
                       if($am_dt->amenities->amenities_image !=''){
                           $amenities_image = $path->amenity_url.$am_dt->amenities->amenities_image;   
                       }
                       if($am_dt->amenities->android_image !='')
                       {
                           $am_android_image = $path->amenity_url.$am_dt->amenities->android_image;   
                       }
                       $am_arr['id']=$am_dt->amenities->id;
                       $am_arr['name']=$am_dt->amenities->name;
                       $am_arr['amenity_image']=$amenities_image ;
                       $am_arr['amenity_android_image']=$am_android_image;
                       $amenityDatas[] = $am_arr;
                   }
               }
           }
            $safetyDatas = [];
            if($record->busSafety)
           {
               foreach($record->busSafety as $sd){
                   if($sd->safety != NULL)
                   {
                       $safety_image='';
                       $safety_android_image='';
                       if($sd->safety->safety_image !=''){
                           $safety_image = $path->safety_url.$sd->safety->safety_image;
                       }  
                       if($sd->safety->android_image != '' )
                       {
                           $safety_android_image = $path->safety_url.$sd->safety->android_image;   
                       }
                       $sf_arr['id']=$sd->safety->id;
                       $sf_arr['name']=$sd->safety->name;
                       $sf_arr['safety_image']=$safety_image ;
                       $sf_arr['safety_android_image']=$safety_android_image;
                       $safetyDatas[] = $sf_arr;
                   }
               }
           }
            $busPhotoDatas = [];

            if(count($record->busGallery)>0)
            {
                foreach($record->busGallery as  $k => $bp){
                    if($bp->bus_image_1 != null && $bp->bus_image_1!='')
                    {                        
                       $busPhotoDatas[$k]['bus_image_1'] = $path->busphoto_url.$bp->bus_image_1;                         
                    }
                    if($bp->bus_image_2 != null && $bp->bus_image_2 !='')
                    {                        
                       $busPhotoDatas[$k]['bus_image_2'] = $path->busphoto_url.$bp->bus_image_2;                        
                    }
                    if($bp->bus_image_3 != null && $bp->bus_image_3 !='')
                    {                        
                       $busPhotoDatas[$k]['bus_image_3'] = $path->busphoto_url.$bp->bus_image_3;                        
                    }
                    if($bp->bus_image_4 != null && $bp->bus_image_4 !='')
                    {                        
                       $busPhotoDatas[$k]['bus_image_4'] = $path->busphoto_url.$bp->bus_image_4;                        
                    }
                    if($bp->bus_image_5 != null && $bp->bus_image_5 !='')
                    {                        
                       $busPhotoDatas[$k]['bus_image_5'] = $path->busphoto_url.$bp->bus_image_5;                        
                    }    
                }
            } 
            $Totalrating=0;
            $Totalrating_5star=0;
            $Totalrating_4star=0;
            $Totalrating_3star=0;
            $Totalrating_2star=0;
            $Totalrating_1star=0;
            $Review_list=[];
            $i=1;
            if(count($record->review)>0){
                foreach($record->review as $k => $rv){
               if($i<=2){ // only latest 2 reviews 
                  $Totalrating += $rv->rating_overall;  
                  if($rv->rating_overall==5){
                   $Totalrating_5star ++;   
                  } 
                  if($rv->rating_overall==4){
                   $Totalrating_4star ++;   
                  } 
                  if($rv->rating_overall==3){
                   $Totalrating_3star ++;   
                  } 
                  if($rv->rating_overall==2){
                   $Totalrating_2star ++;   
                  } 
                  if($rv->rating_overall==1){
                   $Totalrating_1star ++;   
                  }  
                  $Review_list[$k]['bus_id']=$rv->bus_id;
                     $Review_list[$k]['users_id']=$rv->users_id;
                     $Review_list[$k]['title']=$rv->title;
                     $Review_list[$k]['rating_overall']=$rv->rating_overall;
                     $Review_list[$k]['comments']=$rv->comments;
                     $Review_list[$k]['name']=$rv->users->name;
                     $Review_list[$k]['profile_image']='';
                  if($rv->users && $rv->users->profile_image!='' && $rv->users->profile_image!=null){
                   $Review_list[$k]['profile_image']=$path->profile_url.$rv->users->profile_image;
                 }
               $i++;
               }
           }
                $Totalrating = number_format($Totalrating/count($record->review),1);
            }
            $reviews=  $Review_list; //$record->review;
            $cancellationPolicyContent = $record->cancellationslabs->cancellation_policy_desc;
            $TravelPolicyContent=$record->travel_policy_desc;
            $cSlabDatas = $record->cancellationslabs->cancellationSlabInfo;
            
            $cSlabDuration = $cSlabDatas->pluck('duration');
            $cSlabDeduction = $cSlabDatas->pluck('deduction');

           $bookedSeats = $this->listingRepository->getBookedSeats($sourceID,$destinationID,$entry_date,$busId);
          
           $seatClassRecords = $seatClassRecords - $bookedSeats[1];
           $sleeperClassRecords = $sleeperClassRecords - $bookedSeats[0];
           $totalSeats = $totalSeats - $bookedSeats[2];
            if($clientRole == $clientRoleId){

                /////client extra service charge added to seatfare////////////////
                $clientCommissions = ClientFeeSlab::where('user_id', $clientId)
                                                ->where('status', '1')
                                                ->get(); 
                    
                $client_service_charges = 0;
                $addCharge = 0;
                if($clientCommissions){
                    foreach($clientCommissions as $clientCom){
                        $startFare = $clientCom->starting_fare;
                        $uptoFare = $clientCom->upto_fare;
                        if($startingFromPrice >= $startFare && $startingFromPrice <= $uptoFare){
                            $addCharge = $clientCom->addationalcharges;
                            break;
                        }  
                    }   
                } 
                $client_service_charges = ($addCharge/100 * $startingFromPrice);
                $newSeatFare = $startingFromPrice + $client_service_charges;

                /////////hide buses wrt operator block////////////
                 $operatorBlockId = ManageClientOperator::where('user_id',$clientId)->pluck('bus_operator_id');
                $Contains=0;
                if(isset($operatorBlockId)){
                  $Contains = $operatorBlockId->contains($operatorId);
                }

                if($Contains==0){
           
                    $arr= array(
                        "origin" => 'ODBUS',
                        "CompanyID" => '',
                        "ReferenceNumber" => '',
                        "BoardingPoints" => '',
                        "DroppingPoints" => '',
                        "RouteTimeID" => '',
                        "srcId" => $sourceID,
                        "destId" => $destinationID,
                        "display" => $flag,
                        "busId" => $busId, 
                        "busName" => $busName,
                        "via" => $via,
                        "popularity" => $popularity,
                        "busNumber" => $busNumber,
                        "maxSeatBook" => $maxSeatBook,
                        "conductor_number" => $conductor_number,
                        "operatorId" => $operatorId,
                        "operatorUrl" => $operatorUrl,
                        "operatorName" => $operatorName,
                        "sittingType" => $sittingType,
                        "bus_description" => $bus_description,
                        "busType" => $busType,
                        "busTypeName" => $busTypeName,
                        "totalSeats" => $totalSeats,
                        "seaters" => $seatClassRecords,
                        "sleepers" => $sleeperClassRecords,
                        "startingFromPrice" => $newSeatFare,
                        "departureTime" =>$depTime,
                        "arrivalTime" =>$arrTime,
                        "totalJourneyTime" =>$totalJourneyTime, 
                        "amenity" =>$amenityDatas,
                        "safety" => $safetyDatas,
                        "cancellationDuration" => $cSlabDuration,
                        "cancellationDuduction" => $cSlabDeduction,
                        "cancellationPolicyContent" => $cancellationPolicyContent,
                        "TravelPolicyContent" => $TravelPolicyContent,
                        ); 
                    if($totalSeats>0){
                        $ListingRecords['regular'][] = $arr;
                    }else{
                        $ListingRecords['soldout'][] = $arr;
                    }
                }
            }else{
                $arr= array(
                    "origin" => 'ODBUS',
                    "CompanyID" => '',
                    "ReferenceNumber" => '',
                    "BoardingPoints" => '',
                    "DroppingPoints" => '',
                    "RouteTimeID" => '',
                    "srcId" => $sourceID,
                    "destId" => $destinationID,
                    "display" => $flag,
                    "busId" => $busId, 
                    "busName" => $busName,
                    "via" => $via,
                    "popularity" => $popularity,
                    "busNumber" => $busNumber,
                    "maxSeatBook" => $maxSeatBook,
                    "conductor_number" => $conductor_number,
                    "couponCode" => $appliedCoupon->all(),
                    "couponDetails" => $CouponDetails,
                    "operatorId" => $operatorId,
                    "operatorUrl" => $operatorUrl,
                    "operatorName" => $operatorName,
                    "sittingType" => $sittingType,
                    "bus_description" => $bus_description,
                    "busType" => $busType,
                    "busTypeName" => $busTypeName,
                    "totalSeats" => $totalSeats,
                    "seaters" => $seatClassRecords,
                    "sleepers" => $sleeperClassRecords,
                    "startingFromPrice" => $startingFromPrice,
                    "departureTime" =>$depTime,
                    "arrivalTime" =>$arrTime,
                    "totalJourneyTime" =>$totalJourneyTime, 
                    "amenity" =>$amenityDatas,
                    "safety" => $safetyDatas,
                    "busPhotos" => $busPhotoDatas,
                    "cancellationDuration" => $cSlabDuration,
                    "cancellationDuduction" => $cSlabDeduction,
                    "cancellationPolicyContent" => $cancellationPolicyContent,
                    "TravelPolicyContent" => $TravelPolicyContent,
                    "Totalrating" => $Totalrating,
                    "Totalrating_5star" => $Totalrating_5star,
                    "Totalrating_4star" => $Totalrating_4star,
                    "Totalrating_3star" => $Totalrating_3star,
                    "Totalrating_2star" => $Totalrating_2star,
                    "Totalrating_1star" => $Totalrating_1star,
                    "reviews" => $reviews
                ); 
                if($totalSeats>0){
                    $ListingRecords['regular'][] = $arr;
                }else{
                    $ListingRecords['soldout'][] = $arr;
                }
            }           
        }
        return $ListingRecords;
    }

    public function getLocation(Request $request)
    {
      return  $data= $this->listingRepository->getLocation($request['locationName']);
          
    }

    public function filter(Request $request,$clientRole,$clientId)
    {
        $booked = Config::get('constants.BOOKED_STATUS');   
        
        $sourceID = $request['sourceID'];      
        $destinationID = $request['destinationID'];
        $busOperatorId = $request['bus_operator_id']; 
        $userId = $request['user_id'];
        $entry_date = $request['entry_date']; 
        $path= $this->commonRepository->getPathurls();
        $path= $path[0];  
        if($sourceID==null ||  $destinationID==null || $entry_date==null)
            return ""; 

        $entry_date = date("Y-m-d", strtotime($entry_date));    
        $busType = $request['busType'];
        $seatType = $request['seatType'];    
        $boardingPointId = $request['boardingPointId'];
        $dropingingPointId = $request['dropingingPointId'];
        $operatorId = $request['operatorId'];
        $amenityId = $request['amenityId'];
        
        $selCouponRecords = $this->listingRepository->getAllCoupon();
        $busDetails = $this->listingRepository->getticketPrice($sourceID,$destinationID,$busOperatorId,$entry_date,$userId);  


        $dolphinresult=[];


        if( ($operatorId != null && count($operatorId)!=0 && in_array('Dolphin',$operatorId)) ||  ($operatorId != null && count($operatorId)==0) || $operatorId == null){

            $DolPhinshowRecords = [];
            $DolPhinShowSoldoutRecords =[];   
            //if($clientId!=44 && $clientRole!=6){ // to stop dolphin bus in android until madhu completed work
               $dolphinresult= $this->dolphinTransformer->Filter($request,$clientRole,$clientId); // getting dolphin buslist
            //}
        }

        
        $DolPhinshowRecords = (isset($dolphinresult['regular'])) ? $dolphinresult['regular'] : [];
        $DolPhinShowSoldoutRecords = (isset($dolphinresult['soldout'])) ? $dolphinresult['soldout'] : [];

        $common=$this->commonRepository->getCommonSettings(Config::get('constants.USER_ID'));

        $sortar=[];
        

        if($common[0]->bus_list_sequence==1){
        $sortar= ['startingFromPrice', 'asc'];
        }

        else if($common[0]->bus_list_sequence==2){
        $sortar=['departureTime', 'asc'];   
        }
        else if($common[0]->bus_list_sequence==3){
        $sortar=['totalSeats', 'desc'];
        } 

        else{
            $sortar=['departureTime', 'asc']; 
        }
        
        
        $price = $request['price'];
           
        if(isset($request['sortBy']) && $request['sortBy']!=''){
            $price =3;
            $sortBy= $request['sortBy'];

            if($sortBy=='rating'){
              $sortar= ['Totalrating', 'desc'];
            }        
            else if($sortBy=='departure'){
            $sortar=['departureTime', 'asc'];   
            }
            else if($sortBy=='seat'){
            $sortar=['totalSeats', 'desc'];
            }
 
         }


        
        //return $busDetails;
    if(isset($busDetails[0])){
            $records = array();
            $FilterRecords = array();
            $showBusRecords = [];
            $hideBusRecords = [];
            $hideRecords = [];
            $CurrentDateTime = Carbon::now();//->toDateTimeString();
        foreach($busDetails as $busDetail){
            $ticketPriceId = $busDetail['id'];
            $busId = $busDetail['bus_id'];
            $startJDay = $busDetail['start_j_days'];
            $JDay =  $busDetail->j_day;
        ////////////////bus cancelled on specific date//////////////////////
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
                continue;
            }
            // if(isset($cancelledBus[0]) && $cancelledBus[0]->busCancelledDate->isNotEmpty()){
            //     continue;
            // }
        
        /////////////////Bus Seize//////////////////////////////////////////////
            $seizedTime = $busDetail['seize_booking_minute'];
            $depTime = date("H:i:s", strtotime($busDetail['dep_time']));  
            $depDateTime = Carbon::createFromFormat('Y-m-d H:i:s', $entry_date.' '.$depTime);

            if($depDateTime>=$CurrentDateTime){
                $diff_in_minutes = $depDateTime->diffInMinutes($CurrentDateTime);
            }else{
                $diff_in_minutes = 0;
            }

            /////////////day wise seize time change////////////////////////////////
            $dayWiseSeizeTime = BookingSeized::where('ticket_price_id',$ticketPriceId)
                                          ->where('seized_date', $entry_date)
                                          ->get('seize_booking_minute');    
            if(!$dayWiseSeizeTime->isEmpty()){
                $dWiseSeizeTime = $dayWiseSeizeTime[0]->seize_booking_minute;
                if($dWiseSeizeTime < $diff_in_minutes){
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
                     $busEntryPresent =$this->listingRepository->checkBusentry($busId,$new_date);
                     if(isset($busEntryPresent[0]) && $busEntryPresent[0]->busScheduleDate->isNotEmpty()){
                        $records[] = $this->listingRepository->getFilterBusList($busOperatorId,$busId,$busType,$seatType,$boardingPointId,$dropingingPointId,$operatorId,$amenityId,$userId,$entry_date);
                     } 
                }else{

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
                     $busEntryPresent =$this->listingRepository->checkBusentry($busId,$new_date);
                     if(isset($busEntryPresent[0]) && $busEntryPresent[0]->busScheduleDate->isNotEmpty()){
                        $hideBusRecords[] = $this->listingRepository->getFilterBusList($busOperatorId,$busId,$busType,$seatType,$boardingPointId,$dropingingPointId,$operatorId,$amenityId,$userId,$entry_date);
                     } 
                }
            }
            elseif($seizedTime < $diff_in_minutes){
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
                $busEntryPresent =$this->listingRepository->checkBusentry($busId,$new_date);
                if(isset($busEntryPresent[0]) && $busEntryPresent[0]->busScheduleDate->isNotEmpty()){
                    $records[] = $this->listingRepository->getFilterBusList($busOperatorId,$busId,$busType,$seatType,$boardingPointId,$dropingingPointId,$operatorId,$amenityId,$userId,$entry_date);
                } 
            }else{
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
                    $busEntryPresent =$this->listingRepository->checkBusentry($busId,$new_date);
                    if(isset($busEntryPresent[0]) && $busEntryPresent[0]->busScheduleDate->isNotEmpty()){
                        $hideBusRecords[] = $this->listingRepository->getFilterBusList($busOperatorId,$busId,$busType,$seatType,$boardingPointId,$dropingingPointId,$operatorId,$amenityId,$userId,$entry_date);
                    } 
            }
        }
        $showBusRecords = Arr::flatten($records);
        $hideBusRecords = Arr::flatten($hideBusRecords);
        $showRecord = $this->processBusRecords($showBusRecords,$sourceID, $destinationID,$entry_date,$path,$selCouponRecords,$busOperatorId,$busId,'show',$clientRole,$clientId);

        $showRecords=[];
        $HideSoldoutRecords =[];
        $hideRecords =[];
        
        $showRecords = (isset($showRecord['regular'])) ? $showRecord['regular'] : [];
        $ShowSoldoutRecords = (isset($showRecords['soldout'])) ? $showRecords['soldout'] : [];
       
        if(count($hideBusRecords) > 0){
           $hideRecords =  $this->processBusRecords($hideBusRecords,$sourceID, $destinationID,$entry_date,$path,$selCouponRecords,$busOperatorId,$busId,'hide',$clientRole,$clientId);

           $HideSoldoutRecords = (isset($hideRecords['soldout'])) ? $hideRecords['soldout'] : [];
           $hideRecords = (isset($hideRecords['regular'])) ? $hideRecords['regular'] : [];
        } 

      
        if ($price == 0){

            $sortar= ['startingFromPrice', 'desc'];           
            
             $hideRecords = collect($hideRecords)->sortBy([$sortar]);
             $showRecords = collect($showRecords)->concat(collect($DolPhinshowRecords))->sortBy([$sortar]);
          
         }

         else if($price == 1){

            $sortar= ['startingFromPrice', 'asc'];
            $showRecords = collect($showRecords)->concat(collect($DolPhinshowRecords))->sortBy([$sortar]);
              $hideRecords = collect($hideRecords)->sortBy([$sortar]);           
         }     
       else{ 

          $showRecords = collect($showRecords)->concat(collect($DolPhinshowRecords))->sortBy([$sortar]);
          $hideRecords = collect($hideRecords)->sortBy([$sortar]);
          
        }


        $soldoutRecords = collect($ShowSoldoutRecords)->concat(collect($DolPhinShowSoldoutRecords))->concat(collect($HideSoldoutRecords));

        $ListingRecords = $showRecords->concat($soldoutRecords);
        return $ListingRecords->concat($hideRecords);

    }  

     else{

        if($price == 0){
            $sortar= ['startingFromPrice', 'desc'];  
         }
         else if($price == 1){
           $sortar= ['startingFromPrice', 'asc'];
        } 
        $ListingRecords =  collect($DolPhinshowRecords)->sortBy([$sortar]);
        return $ListingRecords->concat(collect($DolPhinShowSoldoutRecords));
    } 
    
    }

    public function getFilterOptions(Request $request,$clientRole,$clientId)
    {
        $sourceID = $request['sourceID'];
        $destinationID = $request['destinationID']; 
        $busIds = $request['busIDs']; 
        $clientRoleId = Config::get('constants.CLIENT_ROLE_ID');
        $journey_date = $request['entry_date']; 

        $busTypes =  $this->listingRepository->getbusTypes();
        $seatTypes = $this->listingRepository->getseatTypes();
        $boardingPoints = $this->listingRepository->getboardingPoints($sourceID,$busIds);
        $dropingPoints = $this->listingRepository->getdropingPoints($destinationID,$busIds);
        $busOperator = $this->listingRepository->getbusOperator($busIds);

        $operatorBlockId = ManageClientOperator::where('user_id',$clientId)->pluck('bus_operator_id');
        $amenities = $this->listingRepository->getamenities($busIds);

        /////// to get dolphin operator , calling buslist function again

        //Log::info($request);
       
         $DolphinBusList = $this->dolphinTransformer->Filter($request,$clientRole,$clientId);

         //Log::info($DolphinBusList);

         $regular=(isset($DolphinBusList['regular'])) ? $DolphinBusList['regular'] : [];
         $soldout=(isset($DolphinBusList['soldout'])) ? $DolphinBusList['soldout'] : [];

        

        if(count($regular)==0 && count($soldout)==0 ){

            $DolphinBusOperator=[];

        }else{

            $DolphinBusOperator[]=[
                "id"=>"Dolphin",
                "operator_name"=> "Dolphin",
                "organisation_name"=>"Dolphin"
            ];

            $busOperator=   collect($busOperator)->concat(collect($DolphinBusOperator));

        }

         /////////hide busOperators wrt operator block for clients////////////
         if($clientRole == $clientRoleId){
            if(isset($operatorBlockId)){
            $filteredOperators = ($busOperator->whereNotIn('id',$operatorBlockId))->flatten();
            }
            $filterOptions[] = array(
            "busTypes" => $busTypes,
            "seatTypes" => $seatTypes,  
            "boardingPoints" => $boardingPoints,
            "dropingPoints"=> $dropingPoints,
            "busOperator"=> $filteredOperators,
            "amenities"=> $amenities   
            );
        }else{
            $filterOptions[] = array(
            "busTypes" => $busTypes,
            "seatTypes" => $seatTypes,  
            "boardingPoints" => $boardingPoints,
            "dropingPoints"=> $dropingPoints,
            "busOperator"=> $busOperator,
            "amenities"=> $amenities   
            );
        }

        return  $filterOptions;
    }
    public function busDetails(Request $request,$clientRole, $clientId)
    {
        $origin=(isset($request['origin'])) ? $request['origin'] : 'ODBUS';
        $ReferenceNumber=(isset($request['ReferenceNumber'])) ? $request['ReferenceNumber'] : '';

        
        if($origin !='DOLPHIN' && $origin != 'ODBUS' ){
            return 'Invalid Origin';
        }else if($origin=='DOLPHIN'){

            if($ReferenceNumber ==''){

                return 'ReferenceNumber_empty';

            }else{
                return $dolphinBusDetails= $this->dolphinTransformer->BusDetails($request,$clientRole, $clientId);
            }
        }else if($origin=='ODBUS'){

             return $this->listingRepository->busDetails($request);
        }
    }

    public function UpdateExternalApiLocation(){
        return $this->listingRepository->UpdateExternalApiLocation();
    }
   
}