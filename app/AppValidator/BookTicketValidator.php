<?php
namespace App\AppValidator;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class BookTicketValidator 
{   

    public function validate($data) { 
        
        $rules = [
            'customerInfo.phone' => 'required||digits:10',
            'customerInfo.name' => 'required|max:50',
            'bookingInfo.bus_id' => 'required|numeric',
            'bookingInfo.source_id' => 'required|numeric',
            'bookingInfo.destination_id' => 'required|numeric',
            'bookingInfo.journey_date' => 'required|date_format:Y-m-d',
            'bookingInfo.boarding_point' => 'required',
            'bookingInfo.dropping_point' => 'required',
            'bookingInfo.boarding_time' => 'required',
            'bookingInfo.dropping_time' => 'required',
            'bookingInfo.app_type' => ["required" , "in:WEB,MOB,ANDROID"],  
            'bookingInfo.typ_id' => 'required',
            //'bookingInfo.total_fare' => 'required|numeric',
            //'bookingInfo.owner_fare' => 'required|numeric',
            //'bookingInfo.odbus_service_Charges' => 'required|numeric',
            'bookingInfo.created_by' => 'required',
            'bookingInfo.bookingDetail' => [
                                            'bus_seats_id' => 'required',
                                            'passenger_name' => 'required',
                                            'passenger_gender' => 'required',
                                            'passenger_age' => 'required',
                                            'created_by' => 'required',
                                           ]
        ];      
      
        $bookTicketValidation = Validator::make($data, $rules);
        return $bookTicketValidation;
    }

}