<?php

namespace App\Services;
use Illuminate\Http\Request;
use App\Repositories\AgentBookingRepository;
use Exception;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Config;
use App\Transformers\DolphinTransformer;
use App\Models\Location;
use App\Services\ListingService;

use Illuminate\Support\Facades\Log;
use InvalidArgumentException;

class AgentBookingService
{
    
    protected $agentBookingRepository;  
    protected $dolphinTransformer;
    protected $listingService; 


    public function __construct(AgentBookingRepository $agentBookingRepository,DolphinTransformer $dolphinTransformer,ListingService $listingService)
    {
        $this->agentBookingRepository = $agentBookingRepository;
        $this->dolphinTransformer = $dolphinTransformer;
        $this->listingService = $listingService;


    }
    public function agentBooking($request,$clientRole,$clientId)
    {
        try {

            $bookingInfo = $request['bookingInfo'];
            ////////////////////////busId validation////////////////////////////////////
            $sourceID = $bookingInfo['source_id'];
            $destinationID = $bookingInfo['destination_id'];
            $source = Location::where('id',$sourceID)->first()->name;
            $destination = Location::where('id',$destinationID)->first()->name;

           // Log::info($request);

            $ReferenceNumber = $request['bookingInfo']['ReferenceNumber'];
            $origin = $request['bookingInfo']['origin'];

            if($origin !='DOLPHIN' && $origin != 'ODBUS' ){
                return 'Invalid Origin';
            }else if($origin=='DOLPHIN'){
                if($ReferenceNumber ==''){
                    return 'ReferenceNumber_empty';
                }
            }

            if($origin == 'ODBUS'){

                $reqInfo= array(
                    "source" => $source,
                    "destination" => $destination,
                    "entry_date" => $bookingInfo['journey_dt'],
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

            $bookTicket = $this->agentBookingRepository->agentBooking($request,$clientRole,$clientId);
            return $bookTicket;

        } catch (Exception $e) {
            Log::info($e->getMessage());
            throw new InvalidArgumentException(Config::get('constants.INVALID_ARGUMENT_PASSED'));
        }
       
    }   
   
}