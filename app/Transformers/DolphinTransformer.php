<?php

namespace App\Transformers;
use Illuminate\Http\Request;
use Exception;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Log;
use InvalidArgumentException;
use App\Services\DolphinService;
use App\Repositories\ListingRepository;
use App\Models\IncomingApiCompany;
use App\Models\Bus;
use App\Models\Location;
use App\Models\Users;
use App\Models\Booking;
use App\Models\BookingDetail;
use App\Jobs\SendEmailTicketJob;
use App\Models\Credentials;
use App\Models\ClientFeeSlab;



use DateTime;


class DolphinTransformer
{

    protected $listingRepository; 
    protected $DolphinService;
    protected $booking;
    protected $credentials;


   
    public function __construct(ListingRepository $listingRepository,DolphinService $DolphinService,Booking $booking,Credentials $credentials)
    {

        $this->listingRepository = $listingRepository;
        $this->DolphinService = $DolphinService;
        $this->booking = $booking;
        $this->credentials = $credentials;


        
    }
    
    public function BusList($request,$clientRole,$clientId){
         $srcResult= $this->listingRepository->getLocationID($request['source']);
        $destResult= $this->listingRepository->getLocationID($request['destination']);

        $dolphinresult=[];

        if($srcResult[0]->is_dolphin==1 && $destResult[0]->is_dolphin==1){

            $dolphin_source=$srcResult[0]->dolphin_id;
            $dolphin_dest=$destResult[0]->dolphin_id;

            $data= $this->DolphinService->GetAvailableRoutes($dolphin_source,$dolphin_dest,$request['entry_date']);

            $dolphinresult= $this->BusListProcess($data,$srcResult[0]->id,$destResult[0]->id,$clientRole,$clientId);

        }

        return $dolphinresult;


    }

    public function Filter($request,$clientRole,$clientId){

        $sourceID = $request['sourceID'];      
        $destinationID = $request['destinationID'];
        $entry_date = $request['entry_date'];

        $srcResult= $this->listingRepository->getLocationResult($sourceID);
        $destResult= $this->listingRepository->getLocationResult($destinationID);

        $dolphinresult=[];

        if($srcResult[0]->is_dolphin==1 && $destResult[0]->is_dolphin==1){

            $dolphin_source=$srcResult[0]->dolphin_id;
            $dolphin_dest=$destResult[0]->dolphin_id;
            $data= $this->DolphinService->GetAvailableRoutes($dolphin_source,$dolphin_dest,$entry_date);

            $dolphinresult= $this->BusListProcess($data,$sourceID,$destinationID,$clientRole,$clientId);

        } 

        return $dolphinresult;

    }

    public function BusListProcess($data,$src_id,$dest_id,$clientRole,$clientId){

        
        $policy= $this->GetCancellationPolicy();

        $cancellationDuration=[];
        $cancellationDuduction=[];

        if($policy){
         foreach($policy as $p){

             $cancellationDuration[]=$p->duration;
             $cancellationDuduction[]=$p->deduction;

         }         
        }


        $dolphinresult['regular'] = [];
        $dolphinresult['soldout'] = [];

        if (!empty($data)){

           if (count($data) == count($data, COUNT_RECURSIVE)){
            
            if(strpos('AM',$data['ArrivalTime']) == 0 && strpos('PM',$data['RouteTime']) ==0){
              $booking_date= date('Y-m-d',strtotime($data['BookingDate']));        
              $arrival_date= date('Y-m-d',strtotime($data['BookingDate']. ' +1 day'));         
     
            }else{
     
               $arrival_date=$booking_date= date('Y-m-d',strtotime($data['BookingDate']));  
     
             }
     
             $booking_date_time= $booking_date.' '.$data['RouteTime'];
             $arrival_date_time= $arrival_date.' '.$data['ArrivalTime'];
     
             
     
             $d1 = new DateTime($booking_date_time);
             $d2 = new DateTime($arrival_date_time);
             $interval = $d2->diff($d1);
     
             $duration= $interval->format('%hh %im');
     
     
     
             // $duration=  $arrival_date_time - $booking_date_time;

             $seatsList=$this->seatLayout($data['ReferenceNumber'],$clientRole,$clientId);

             $seat_price=0;

             $seat_price = ($data['BusType'] == 0) ? $data['AcSeatRate'] : $data['NonAcSeatRate'];

            //  $dolphin_gstdata= IncomingApiCompany::where("name","DOLPHIN")->first();

            //  if($dolphin_gstdata){

            //     $seat_price += round(($seat_price * $dolphin_gstdata->gst)/100);

            // }

            $clientRoleId = Config::get('constants.CLIENT_ROLE_ID');

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
                        if($seat_price >= $startFare && $seat_price <= $uptoFare){
                            $addCharge = $clientCom->dolphinaddationalCharges;
                            break;
                        }  
                    }   
                } 
                $client_service_charges = ($addCharge/100 * $seat_price);
                $seat_price = $seat_price + $client_service_charges;

            }

            
             $arr=[
                 "origin"=> "DOLPHIN",
                 "srcId"=> $src_id,//$data['FromCityId'],
                 "destId"=> $dest_id, //$data['ToCityId'],
                 "display"=> "show",
                 "CompanyID"=> (int)$data['CompanyID'],
                 "ReferenceNumber"=>$data['ReferenceNumber'],
                 "BoardingPoints"=>$data['BoardingPoints'],
                 "DroppingPoints"=>$data['DroppingPoints'],
                 "busId"=> (int) $data['RouteID'],
                 "RouteTimeID"=> (int)$data['RouteTimeID'],
                 "busName"=> $data['CompanyName'],
                 "via"=> "",
                 "popularity"=> null,
                 "busNumber"=> "",
                 "maxSeatBook"=> 6,
                 "conductor_number"=> "",
                 "couponCode"=> [],
                 "couponDetails"=> [],
                 "operatorId"=> 0,
                 "operatorUrl"=> "",
                 "operatorName"=> $data['CompanyName'],
                 "sittingType"=>  $data['ArrangementName'],
                 "bus_description"=>($data['BusType'] == 0) ? 'AC' : 'NON AC',
                 "busType"=> ($data['BusType'] == 0) ? 'AC' : 'NON AC', //1 - non ac
                 "busTypeName"=> $data['ArrangementName'],
                 "totalSeats"=>$seatsList['emptySeat'],
                 "seaters"=> '',
                 "sleepers"=> '',
                 "startingFromPrice"=> $seat_price ,  // NonAcSeatRate,NonAcSleeperRate,AcSeatRate,AcSleeperRate
                 "departureTime"=> date("H:i",strtotime($data['CityTime'])),
                 "arrivalTime"=> date("H:i",strtotime($data['ArrivalTime'])),
                 "totalJourneyTime"=> $duration, 
                 "amenity"=> [],
                 "safety"=> [],
                 "busPhotos"=> [],
                 "cancellationDuration"=>  $cancellationDuration,
                 "cancellationDuduction"=> $cancellationDuduction,
                 "cancellationPolicyContent"=> null,
                 "TravelPolicyContent"=> null,
                 "Totalrating"=> 0,
                 "Totalrating_5star"=> 0,
                 "Totalrating_4star"=> 0,
                 "Totalrating_3star"=> 0,
                 "Totalrating_2star"=> 0,
                 "Totalrating_1star"=> 0,
                 "reviews"=> []
                ];  
                
                
                if($seatsList['emptySeat']>0){
                    $dolphinresult['regular'][] = $arr;
                }else{
                    $dolphinresult['soldout'][] = $arr;
                }
         

           }           
           else{
     
             foreach($data as $v){
     
               if(strpos('AM',$v['ArrivalTime']) == 0 && strpos('PM',$v['RouteTime']) ==0){
                 $booking_date= date('Y-m-d',strtotime($v['BookingDate']));        
                 $arrival_date= date('Y-m-d',strtotime($v['BookingDate']. ' +1 day'));         
        
                }else{
        
                  $arrival_date=$booking_date= date('Y-m-d',strtotime($v['BookingDate']));  
        
                }
        
                $booking_date_time= $booking_date.' '.$v['RouteTime'];
                $arrival_date_time= $arrival_date.' '.$v['ArrivalTime'];
        
                
        
                $d1 = new DateTime($booking_date_time);
                $d2 = new DateTime($arrival_date_time);
                $interval = $d2->diff($d1);
        
                $duration= $interval->format('%hh %im');

                $seatsList=$this->seatLayout($v['ReferenceNumber'],$clientRole,$clientId);


                $seat_price=0;

                $seat_price = ($v['BusType'] == 0) ? $v['AcSeatRate'] : $v['NonAcSeatRate'];

                // $dolphin_gstdata= IncomingApiCompany::where("name","DOLPHIN")->first();

                // if($dolphin_gstdata){

                //     $seat_price += round(($seat_price * $dolphin_gstdata->gst)/100);

                // }

                $clientRoleId = Config::get('constants.CLIENT_ROLE_ID');

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
                            if($seat_price >= $startFare && $seat_price <= $uptoFare){
                                $addCharge = $clientCom->addationalcharges;
                                break;
                            }  
                        }   
                    } 
                    $client_service_charges = ($addCharge/100 * $seat_price);
                    $seat_price = $seat_price + $client_service_charges;
    
                }
                

               $arr=[
                 "origin"=> "DOLPHIN",
                 "srcId"=> $src_id,//$v['FromCityId'],
                 "destId"=> $dest_id,//$v['ToCityId'],
                 "display"=> "show",
                 "CompanyID"=>(int) $v['CompanyID'],
                 "ReferenceNumber"=>$v['ReferenceNumber'],
                 "BoardingPoints"=>$v['BoardingPoints'],
                 "DroppingPoints"=>$v['DroppingPoints'],
                 "busId"=> (int) $v['RouteID'],
                 "RouteTimeID"=>(int) $v['RouteTimeID'],
                 "busName"=> $v['CompanyName'],
                 "via"=> "",
                 "popularity"=> null,
                 "busNumber"=> "",
                 "maxSeatBook"=> 6,
                 "conductor_number"=> "",
                 "couponCode"=> [],
                 "couponDetails"=> [],
                 "operatorId"=> 0,
                 "operatorUrl"=> "",
                 "operatorName"=> $v['CompanyName'],
                 "sittingType"=>  $v['ArrangementName'],
                 "bus_description"=>($v['BusType'] == 0) ? 'AC' : 'NON AC',
                 "busType"=> ($v['BusType'] == 0) ? 'AC' : 'NON AC', //1 - non ac
                 "busTypeName"=> $v['ArrangementName'],
                 "totalSeats"=> $seatsList['emptySeat'],
                 "seaters"=> '',
                 "sleepers"=> '',
                 "startingFromPrice"=> $seat_price ,  // NonAcSeatRate,NonAcSleeperRate,AcSeatRate,AcSleeperRate
                 "departureTime"=> date("H:i",strtotime($v['CityTime'])),
                 "arrivalTime"=> date("H:i",strtotime($v['ArrivalTime'])),
                 "totalJourneyTime"=> $duration, 
                 "amenity"=> [],
                 "safety"=> [],
                 "busPhotos"=> [],
                 "cancellationDuration"=>  $cancellationDuration,
                 "cancellationDuduction"=> $cancellationDuduction,
                 "cancellationPolicyContent"=> null,
                 "TravelPolicyContent"=> null,
                 "Totalrating"=> 0,
                 "Totalrating_5star"=> 0,
                 "Totalrating_4star"=> 0,
                 "Totalrating_3star"=> 0,
                 "Totalrating_2star"=> 0,
                 "Totalrating_1star"=> 0,
                 "reviews"=> []
                 
             ]; 
             
             
             if($seatsList['emptySeat']>0){
                $dolphinresult['regular'][] = $arr;
            }else{
                $dolphinresult['soldout'][] = $arr;
            }
     
             }
           }
        }

           return $dolphinresult;

    }

    public function GetseatLayoutName($ReferenceNumber,$id,$clientRole,$clientId){
      $res= $this->seatLayout($ReferenceNumber,$clientRole,$clientId);

      if($key = array_search($id, array_column($res['lower_berth'], 'id'))){
        return $res['lower_berth'][$key]['seatText'];
      }

      if($key = array_search($id, array_column($res['upper_berth'], 'id'))){
        return $res['upper_berth'][$key]['seatText'];
      }
       

    }

    public function seatLayout($ReferenceNumber,$clientRole,$clientId)
    {

      $dolphinSeatresult= $this->DolphinService->GetSeatArrangementDetails($ReferenceNumber);

      $dolphin_gstdata= IncomingApiCompany::where("name","DOLPHIN")->first();


              $Rows= max(array_column($dolphinSeatresult, 'Column'));
              $Cols= max(array_column($dolphinSeatresult, 'Row'));

              $sleeper=[];
              $seater=[];
              $seater_blank=[];
              $sleeper_blank=[];
              $emptySeat=0;

              
              $viewSeat['bus'][]=[ 
                "id"=> 0,
                "name"=> "DOLPHIN TOURS & TRAVELS",
                "bus_seat_layout_id"=> 0
                ];

               // for($i=1;$i<=$Rows;$i++){
                    for($i=$Rows;$i>=1;$i--){
                    for($j=1;$j<=$Cols;$j++){
                        foreach($dolphinSeatresult as $d){

                            if($d['Column']== $i && $d['Row']== $j){
                                if($d['UpLowBerth']=='UB'){
                                    if($d['BlockType']==3){
                                        //$sleeper_blank[$d['Column']]=$d;
                                    }else{
                                        $sleeper[$d['Column']][]=$d;
                                    }
                                    
                                }else{

                                    if($d['BlockType']==3){
                                        //$seater_blank[$d['Column']]=$d;
                                    }else{
                                        $seater[$d['Column']][]=$d;
                                    }
                                }
                               
                            }
                            
                        }
                    }
                }

                $sleeper=array_values($sleeper);
                $seater=array_values($seater);
               
                $viewSeat['upperBerth_totalRows']= $row1=($sleeper) ? count($sleeper) : 0;       
                $viewSeat['upperBerth_totalColumns']= ($sleeper) ? count($sleeper[0]) : 0;
                $viewSeat['lowerBerth_totalRows']= count($seater);
                $viewSeat['lowerBerth_totalColumns']= count($seater[0]);


                $UpperberthArr=[];
                $LowerberthArr=[];

               
                     $blank_row_flag=false;
                     $blankcount=0;

                     $st_id=1;  

                    if($sleeper){ 
                     
                      foreach($sleeper as $i => $dd){ 
                            foreach($dd as $k => $d){
                                $seat_class_id ='';

                                if($d['RowSpan']==2 && $d['ColumnSpan']==0){
                                    $seat_class_id = 2;
                                }

                                else if($d['RowSpan']==0 && $d['ColumnSpan']==2){
                                    $seat_class_id = 3;  
                                }
                                
                                if($d['BlockType'] ==3){
                                    $seat_class_id = 4;
                                }

                                if($i==2 && count($dd) > 2){

                                    $blank_row_flag=true;

                                    $viewSeat['upperBerth_totalRows']=$row1+1;

                                   // for($j=0;$j<count($sleeper[0])-1;$j++){

                                if($blankcount<=4){
                                    for($j=0;$j<5;$j++){

                                        $blank=[
                                            "id"=> $st_id,
                                            "bus_seat_layout_id"=> 0,
                                            "seat_class_id"=> 4,
                                            "berthType"=> 1,
                                            "seatText"=> '',
                                            "rowNumber"=> $i,
                                            "colNumber"=> $j                           
                                        ]; 

                                        $blankcount++;
            
                                       array_push($UpperberthArr,$blank); 

                                       $st_id++; 
                                    } 
                                }


                                    $ar=[
                                        "id"=>$st_id,// $d['SeatNo'],
                                        "bus_seat_layout_id"=> 0,
                                        "seat_class_id"=> $seat_class_id,
                                        "berthType"=> 2,
                                        "seatText"=> $d['SeatNo'],
                                        "rowNumber"=> $i+1,
                                        "colNumber"=> $k                           
                                    ];
                                }


                                else if(count($dd)==2){

                                    if($blankcount<=4){  

                                    //for($j=0;$j<count($sleeper[0])-1;$j++){
                                    for($j=0;$j<5;$j++){

                                        $blank=[
                                            "id"=>$st_id,//'',
                                            "bus_seat_layout_id"=>0,
                                            "seat_class_id"=> 4,
                                            "berthType"=> 1,
                                            "seatText"=> '',
                                            "rowNumber"=> $i,
                                            "colNumber"=> $j                           
                                        ]; 

                                        $blankcount++;
            
                                    array_push($UpperberthArr,$blank); 

                                    $st_id++;  
                                    }
                                }


                                    $ar=[
                                        "id"=>$st_id,// $d['SeatNo'],
                                        "bus_seat_layout_id"=> 0,
                                        "seat_class_id"=> $seat_class_id,
                                        "berthType"=> 2,
                                        "seatText"=> ($seat_class_id==4) ? '' : $d['SeatNo'],
                                        "rowNumber"=> $i,
                                        "colNumber"=> count($sleeper[0])-1                           
                                    ];                                 
        
                                }else{

                                    if($blank_row_flag){
                                        $i= $i+1;
                                        $blank_row_flag=false;
                                    }


                                    $ar=[
                                        "id"=>$st_id,// $d['SeatNo'],
                                        "bus_seat_layout_id"=>0,
                                        "seat_class_id"=> $seat_class_id,
                                        "berthType"=> 2, //Upper Berth
                                        "seatText"=> ($seat_class_id==4) ? '' : $d['SeatNo'],
                                        "rowNumber"=>  ($seat_class_id==3) ? 2 : $i,
                                        "colNumber"=> ($seat_class_id==3) ? 4 : $k,                             
                                    ];
                                    
                                }

                                if($d['IsLadiesSeat']=='N' && $d['Available'] =='N'){

                                    $ar["Gender"]= "M";
                                
                                } 

                                if($d['IsLadiesSeat']=='Y' && $d['Available'] =='N'){

                                    $ar["Gender"]= "F";
                                
                                } 

                                if($d['Available'] =='Y'){

                                    $emptySeat++;

                                    $seat_price= $d['SeatRate'];

                                    $clientRoleId = Config::get('constants.CLIENT_ROLE_ID');

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
                                                    if($seat_price >= $startFare && $seat_price <= $uptoFare){
                                                        $addCharge = $clientCom->dolphinaddationalCharges;
                                                        break;
                                                    }  
                                                }   
                                            } 
                                            $client_service_charges = ($addCharge/100 * $seat_price);
                                            $seat_price = $seat_price + $client_service_charges;

                                        }


                                    // if($dolphin_gstdata){

                                    //     $seat_price += round(($d['SeatRate'] * $dolphin_gstdata->gst)/100);
                                     
                                    //  }

                                    $ar["bus_seats"]= [
                                            "ticket_price_id"=> 0,
                                            "seats_id"=>$st_id, //$d['SeatNo'],
                                            "new_fare"=> $seat_price
                                    ];
                                }

                                array_push($UpperberthArr,$ar);

                                $st_id++;  
                            }
                           
                        }

                       
                    }

                   
                 $st_id2=   $st_id;
               ////////// lower berth 

                if($seater){
                    foreach($seater as $i => $dd){  

                        foreach($dd as $k => $d){

                            $seat_class_id ='';

                            if($d['RowSpan']==0 && $d['ColumnSpan']==0){
                                $seat_class_id = 1;
                            }
                            if($d['RowSpan']==2 && $d['ColumnSpan']==0){
                                $seat_class_id = 2;
                            }
    
                            else if($d['RowSpan']==0 && $d['ColumnSpan']==2){
                                $seat_class_id = 1; // for seater there is no seat class id 3 (Vertical Sleeper)
                            }
    
                            if($d['BlockType'] ==3){
                                $seat_class_id = 4;
                            }


                            if(count($dd)==1){

                                for($j=0;$j<count($seater[0])-1;$j++){

                                    $blank=[
                                        "id"=>$st_id2,// '',
                                        "bus_seat_layout_id"=>0,
                                        "seat_class_id"=> 4,
                                        "berthType"=> 1,
                                        "seatText"=> '',
                                        "rowNumber"=> $i,
                                        "colNumber"=> $j                           
                                    ]; 
        
                                   array_push($LowerberthArr,$blank); 

                                   $st_id2++;  
                                }

                                $ar=[
                                    "id"=>$st_id2,// $d['SeatNo'],
                                    "bus_seat_layout_id"=> 0,
                                    "seat_class_id"=> $seat_class_id,
                                    "berthType"=> 1,
                                    "seatText"=> ($seat_class_id==4) ? '' : $d['SeatNo'],
                                    "rowNumber"=> $i,
                                    "colNumber"=> count($seater[0])-1                           
                                ]; 


                                
    
                            }else{

                                $ar=[
                                    "id"=>$st_id2,//$d['SeatNo'],
                                    "bus_seat_layout_id"=> 0,
                                    "seat_class_id"=> $seat_class_id,
                                    "berthType"=> 1,
                                    "seatText"=> ($seat_class_id==4) ? '' : $d['SeatNo'],
                                    "rowNumber"=> $i,
                                    "colNumber"=> $k                           
                                ]; 

                            }
    
                           
    
                            if($d['IsLadiesSeat']=='N' && $d['Available'] =='N'){
    
                                $ar["Gender"]= "M";
                               
                            } 
    
                            if($d['IsLadiesSeat']=='Y' && $d['Available'] =='N'){
    
                                $ar["Gender"]= "F";
                               
                            } 
    
                            if($d['Available'] =='Y'){

                                $emptySeat++;

                                $seat_price= $d['SeatRate'];

                                $clientRoleId = Config::get('constants.CLIENT_ROLE_ID');

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
                                            if($seat_price >= $startFare && $seat_price <= $uptoFare){
                                                $addCharge = $clientCom->dolphinaddationalCharges;
                                                break;
                                            }  
                                        }   
                                    } 
                                    $client_service_charges = ($addCharge/100 * $seat_price);
                                    $seat_price = $seat_price + $client_service_charges;

                                }

                                    // if($dolphin_gstdata){

                                    //     $seat_price += round(($d['SeatRate'] * $dolphin_gstdata->gst)/100);
                                     
                                    //  }
    
                                $ar["bus_seats"]=[
                                        "ticket_price_id"=> 0,
                                        "seats_id"=> $st_id2,//$d['SeatNo'],
                                        "new_fare"=>  $seat_price
                                ];
    
                            }

                            array_push($LowerberthArr,$ar);
                            $st_id2++;  
                        }

                    }
                }

                $viewSeat['lower_berth']=$LowerberthArr;
                $viewSeat['upper_berth']=$UpperberthArr;
                $viewSeat['emptySeat']=$emptySeat;
                return $viewSeat;
               
    }  
    
    public function BlockSeat($records){

        $nm_ar=[];
        $TotalPassengers=0;

        $amount = $records[0]->owner_fare; 

        // if($records[0]->payable_amount == 0.00){
        //     $amount = $records[0]->total_fare;
        //     }else{
        //         $amount = $records[0]->payable_amount;
        //     }

        if(!empty($records[0]->bookingDetail)){
            foreach($records[0]->bookingDetail as $bdt){
                $st_nm= $bdt->seat_name.','.$bdt->passenger_gender;
                $nm_ar[]=$st_nm;
                $TotalPassengers++;
            }

        }

        $arr['ReferenceNumber']= $records[0]->ReferenceNumber;
        $arr['PassengerName']=$records[0]->users->name;
        $arr['SeatNames']=implode('|',$nm_ar);
        $arr['Email']=$records[0]->users->email;
        $arr['Phone']=$records[0]->users->phone;
        $arr['PickupID']=$records[0]->PickupID;
        $arr['PayableAmount']=$amount;
        $arr['TotalPassengers']=$TotalPassengers;         

        $res= $this->DolphinService->BlockSeat($arr);

       return $res;


       

    }


    public function BookSeat($records){

        $nm_ar=[];
        $TotalPassengers=0;

        // if($records[0]->payable_amount == 0.00){
        //     $amount = $records[0]->total_fare;
        //     }else{
        //         $amount = $records[0]->payable_amount;
        //     }

        $amount = $records[0]->owner_fare; 

        if(!empty($records[0]->bookingDetail)){
            foreach($records[0]->bookingDetail as $bdt){
                $st_nm= $bdt->seat_name.','.$bdt->passenger_gender;
                $nm_ar[]=$st_nm;
                $TotalPassengers++;
            }

        }

        $arr['ReferenceNumber']= $records[0]->ReferenceNumber;
        $arr['PassengerName']=$records[0]->users->name;
        $arr['SeatNames']=implode('|',$nm_ar);
        $arr['Email']=$records[0]->users->email;
        $arr['Phone']=$records[0]->users->phone;
        $arr['PickupID']=$records[0]->PickupID;
        $arr['PayableAmount']=$amount;
        $arr['TotalPassengers']=$TotalPassengers; 
       
        $res= $this->DolphinService->BookSeat($arr);

        Log::info($res);

       return $res;
       

    }

    public function sendsms($dd)
    {
        
            //Environment Variables
            //$apiKey = config('services.sms.textlocal.key');
            $apiKey = $this->credentials->first()->sms_textlocal_key;
            $textLocalUrl = config('services.sms.textlocal.url_send');
            $sender = config('services.sms.textlocal.senderid');
            $message = config('services.sms.textlocal.dolphinTkt');
            $apiKey = urlencode( $apiKey);
            $receiver = urlencode($dd['phone']);
            $name = $dd['name'];
            $pnr = $dd['pnr'];
            $busdetails = $dd['busdetails'];
            $dtl = $dd['dtl'];
            $depttime = $dd['depttime'];
            $rpttime = $dd['rpttime'];

            $message = str_replace("<name>",$name,$message);
            $message = str_replace("<PNR>",$pnr,$message);
            $message = str_replace("<busdetails>",$busdetails,$message);
            $message = str_replace("<PickUpAddress1><PickUpAddress2><PickUpAddress3>",$dtl,$message);
            $message = str_replace("<depttime>",$depttime,$message);
            $message = str_replace("<rpttime>",$rpttime,$message);
            $message = rawurlencode($message);
            $response_type = "json"; 
            $data = array('apikey' => $apiKey, 'numbers' => $receiver, "sender" => $sender, "message" => $message);

            // Log::info($data);
            // Log::info($textLocalUrl);

            
            $ch = curl_init($textLocalUrl);   
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 0);
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, 0);
            $response = curl_exec($ch);
            curl_close($ch);
            $response = json_decode($response);
           // Log::info($response);
           // $msgId = $response->messages[0]->id;  // Store msg id in DB
           // session(['msgId'=> $msgId]);

    }
   

    public function FetchTicketPrintData(){

        // if($pnr!=''){
        //     $list=$this->booking->where('pnr',$pnr)->first();

        //     if(!empty($list)){
        //         if($list->api_pnr!=null){


        //             $res= $this->DolphinService->FetchTicketPrintData($list->api_pnr);
        //             if($res){
        //                 $ar['DOLPHIN_PNRNO']=$res['PNRNO'];
        //                 $ar['CoachNo']=(isset($res['CoachNo'])) ? $res['CoachNo'] : '';
        //                 $ar['PickUpName']=(isset($res['PickUpName'])) ? $res['PickUpName'] : ''; 
        //                 $ar['MainTime']=(isset($res['MainTime'])) ? $res['MainTime'] : ''; 
        //                 $ar['ReportingTime']=(isset($res['ReportingTime'])) ? $res['ReportingTime'] : ''; 
        //                return $ar;                    
        //             }

        //         }
               
        //     }            

        // }else{
            

            $list=$this->booking->with('users')->where('origin','DOLPHIN')->where('api_pnr','!=',null)->orderBy('id','DESC')->get();

            if($list){
                //Log::info("dolphin bus number cron job");
                $main=[];
                foreach($list as $l){
                    $res= $this->DolphinService->FetchTicketPrintData($l->api_pnr); 
                   // Log::info($res);   
                    if($res){
                        $ar['DOLPHIN_PNRNO']=$res['PNRNO'];
                        $ar['CoachNo']=(isset($res['CoachNo'])) ? $res['CoachNo'] : '';
                        $ar['PickUpName']=(isset($res['PickUpName'])) ? $res['PickUpName'] : ''; 
                        $ar['MainTime']=(isset($res['MainTime'])) ? $res['MainTime'] : ''; 
                        $ar['ReportingTime']=(isset($res['ReportingTime'])) ? $res['ReportingTime'] : ''; 
                        $main[]=$ar; 

                        if(isset($res['CoachNo']) && $l->dolphin_sms_email==0){

                            /////////// send dolphin sms 
                            Log::info($l->users->phone);
                            $data['phone']=$l->users->phone;
                            $data['name']=$l->users->name;
                            $data['pnr']=$l->pnr;
                            $data['busdetails']=$res['CoachNo'];
                            $data['dtl']=$res['PickUpName'];
                            $data['depttime']=$res['MainTime'];
                            $data['rpttime']=$res['ReportingTime'];
                
                            $this->sendsms($data);

                          ////// update the sms status in booking table to 1

                         // $dolphin_sms_email_arr['dolphin_sms_email' => 1,'bus_number'=>$res['CoachNo'],'pickup_details'=>$res['PickUpName'] ] ;

                          $this->booking->where('id', $l->id)->update(['dolphin_sms_email' => 1,'bus_number'=>$res['CoachNo'],'pickup_details'=>$res['PickUpName'] ] ); 


                        }

                        

                    }
                }

                return $main;
            }

       //}

       

    }

    public function GetBusNo($arr,$pnr){

        $option['PNRNo']=$pnr;
        $option['CompanyID']=$arr[0]->CompanyID;
        $option['RouteID']=$arr[0]->bus_id;
        $option['RouteTimeID']=$arr[0]->RouteTimeID;
        $srcResult= $this->listingRepository->getLocationResult($arr[0]->source_id);
        $option['FromID']=$srcResult[0]->dolphin_id;
        $option['JourneyDate']=date('d-m-Y',strtotime($arr[0]->journey_dt));

       // Log::info($option);
     
        $res= $this->DolphinService->GetBusNo($option);

        //Log::info($res);

       return $res;
      

    }

    public function GetCancellationPolicy(){

        $arr=[];
        $res= $this->DolphinService->GetCancellationPolicy();
        if($res){
            foreach($res->Policy->PolicyDetails as $p){

                $ar['duration']=($p->FromMinutes/60)."-".($p->ToMinutes/60);
                $ar['deduction']=$p->DeductPercent;

                $arr[]=(object)$ar;

            }
        }
        return $arr;

    }


    public function cancelTicketInfo($pnr){
        return $this->DolphinService->CancelDetails($pnr);
    }

    public function ConfirmCancellation($pnr){
        return $this->DolphinService->ConfirmCancellation($pnr);
    }

    public function BusDetails($request,$clientRole, $clientId){

        $busId = $request['bus_id'];
        $sourceId = $request['source_id'];
        $destinationId = $request['destination_id'];
        $journey_date = $request['journey_date'];
        $ReferenceNumber=$request['ReferenceNumber'];


        $arr=[
            "sourceID"=>$sourceId,
            "destinationID"=>$destinationId,
            "entry_date"=>$journey_date
        ];
         $dolphinBusList= $this->Filter($arr,$clientRole, $clientId);

         $dolphinBusList=(isset($dolphinBusList['regular'])) ? $dolphinBusList['regular'] : [];
         $key = array_search($ReferenceNumber, array_column($dolphinBusList, 'ReferenceNumber'));         
         $CompanyID= $dolphinBusList[$key]['CompanyID'];
         $RouteID= $dolphinBusList[$key]['busId'];
         $RouteTimeID= $dolphinBusList[$key]['RouteTimeID'];
         $bus_description= $dolphinBusList[$key]['bus_description'];
         $busName= $dolphinBusList[$key]['busName'];        

        $amenity= $this->DolphinService->GetAmenities($CompanyID);  

        $bus_amenity=[];

         if($key = array_search($RouteTimeID, array_column($amenity, 'RouteTimeID'))){

          $list= explode('#',$amenity[$key]["Amenities"]);

          if($list){
            foreach($list as $a){
                $ar=explode('|',$a);
                $bus_amenity[]= [
                    "id"=> 0,
                    "bus_id"=> $RouteID,
                    "amenities_id"=> $ar[0],
                    "created_at"=> "",
                    "updated_at"=> "",
                    "created_by"=> "",
                    "status"=> 1,
                    "amenities"=> [
                      "id"=> $ar[0],
                      "name"=> $ar[1].'-'.$ar[1],
                      "android_image"=> ""
                    ]
                ];

            }
          }

         }        


       

        $cancelpolicy=[];

        $cancelpolicyres= $this->DolphinService->GetCancellationPolicy();
        if($cancelpolicyres){
            foreach($cancelpolicyres->Policy->PolicyDetails as $p){
                $ar['id']=0;
                $ar['cancellation_slab_id']=0;
                $ar['duration']=($p->FromMinutes/60)."-".($p->ToMinutes/60);
                $ar['deduction']=$p->DeductPercent;
                $ar['status']=1;
                $ar['created_at']="";
                $ar['updated_at']="";
                $ar['created_by']="";

                $cancelpolicy[]=(object)$ar;

            }
        }


        $b_ar=explode("#",$dolphinBusList[$key]['BoardingPoints']);
        if($dolphinBusList[$key]['DroppingPoints']){

          $d_ar=explode("#",$dolphinBusList[$key]['DroppingPoints']);

        }

        $boardingArray=[];
        $droppingArray=[];
       

       if($b_ar){
          foreach($b_ar as $b){
              $b_ar2=explode("|",$b);
               $boardingArray[]=[
                "id"=> $b_ar2[0],
                "bus_id"=> $RouteID,
                "location_id"=> $sourceId,
                "boarding_droping_id"=> $b_ar2[0],
                "stoppage_name"=> $b_ar2[1],
                "stoppage_time"=> $b_ar2[2],
                "created_at"=> "",
                "updated_at"=> "",
                "created_by"=> "",
                "status"=> 1
               ];

          }                    
       }

       if(isset($d_ar)){
          foreach($d_ar as $d){
              $d_ar2=explode("|",$d);            

               $droppingArray[]= [
                "id"=> $d_ar2[0],
                "bus_id"=> $RouteID,
                "location_id"=> $sourceId,
                "boarding_droping_id"=>$d_ar2[0],
                "stoppage_name"=> $d_ar2[1],
                "stoppage_time"=>$d_ar2[2],
                "created_at"=> "",
                "updated_at"=> "",
                "created_by"=> "",
                "status"=> 1
               ];

          }                    
       }

      if(!empty($boardingArray)){

          if(empty($droppingArray)){

            $droppingArray[]= [
                "id"=> 0,
                "bus_id"=> $RouteID,
                "location_id"=> $sourceId,
                "boarding_droping_id"=>0,
                "stoppage_name"=> $destination_name,
                "stoppage_time"=>$dolphinBusList[$key]['arrivalTime'],
                "created_at"=> "",
                "updated_at"=> "",
                "created_by"=> "",
                "status"=> 1
            ];
          }
      }

      $bs_dt= [          
           [ "id"=> $RouteID,
            "user_id"=> 0,
            "bus_operator_id"=> 0,
            "name"=> $busName,
            "via"=> "",
            "bus_number"=> "",
            "bus_description"=> $bus_description,
            "bus_type_id"=> 9,
            "bus_sitting_id"=> 12,
            "bus_seat_layout_id"=> 23,
            "cancellationslabs_id"=> 7,
            "running_cycle"=> 2,
            "popularity"=> null,
            "type"=> 2,
            "admin_notes"=> null,
            "has_return_bus"=> 0,
            "return_bus_id"=> null,
            "cancelation_points"=> null,
            "created_at"=> "",
            "updated_at"=> "",
            "created_by"=> "MD HUSSEN",
            "status"=> 1,
            "sequence"=> 1000,
            "max_seat_book"=> 6,
            "cancellation_policy_desc"=> null,
            "travel_policy_desc"=> null,
            "cancellationslabs"=> [
              "id"=> 0,
              "user_id"=> 0,
              "rule_name"=> "",
              "cancellation_policy_desc"=> "",
              "status"=> 1,
              "created_at"=> "",
              "updated_at"=> "",
              "created_by"=> "",
              "cancellation_slab_info"=>$cancelpolicy
            ],
            "bus_amenities"=>$bus_amenity,
            "bus_safety"=> [],
            "bus_gallery"=> [],
            "review"=> []
        ]
    ];

        
      $busDetails["busDetails"] = $bs_dt;
      $busDetails["boarding_point"] = $boardingArray;
      $busDetails["dropping_point"] = $droppingArray;

     return $busDetails;
    
    }

    public function GetSeatType($ReferenceNumber,$seatIds,$clientRole,$clientId){

        


        $seatResult= $this->seatLayout($ReferenceNumber,$clientRole,$clientId);

        $seater=[];
        $sleeper=[];

        foreach($seatIds as $s){

            if($key = array_search($s, array_column($seatResult['lower_berth'], 'id'))){
                $seater[]=$s;
            }

            if($key = array_search($s, array_column($seatResult['upper_berth'], 'id'))){
                $sleeper[]=$s;
            }

        }
      

        $main['seater']=$seater;
        $main['sleeper']=$sleeper;

        return $main;

       



    }
   
}