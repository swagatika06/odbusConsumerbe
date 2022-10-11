<!DOCTYPE html>
<html xmlns="http://www.w3.org/1999/xhtml">
<!-- Head -->
<head>
<meta charset="utf-8" />
<title>BUS TICKET</title>

<style type="text/css">
  body {
    margin: 0;
    font-family: -apple-system,BlinkMacSystemFont,"Segoe UI",Roboto,"Helvetica Neue",Arial,sans-serif,"Apple Color Emoji","Segoe UI Emoji","Segoe UI Symbol";
    font-size: 1rem;
    font-weight: 400;
    line-height: 1.5;
    color: #212529;
    text-align: left;
    background-color: #fff;
}
   .container {
       max-width:1320px !important;
       margin:0 auto ;
   }

   .h1, .h2, .h3, .h4, .h5, .h6, h1, h2, h3, h4, h5, h6 {
    margin-bottom: 0.5rem;
    font-family: inherit;
    font-weight: 500;
    line-height: 1.2;
    color: inherit;
}
h1, h2, h3, h4, h5, h6 {
    margin-top: 0;
    margin-bottom: 0.5rem;
}

   .row {
    display: flex;
    /*flex-wrap: wrap;*/
    margin-right: -15px;
    margin-left: -15px;
}

.col-md, .col-md-1, .col-md-10, .col-md-11, .col-md-12, .col-md-2, .col-md-3, .col-md-4, .col-md-5, .col-md-6, .col-md-7, .col-md-8, .col-md-9, .col-md-auto{
    position: relative;
    width: 100%;
    min-height: 1px;
    padding-right: 15px;
    padding-left: 15px;
}

.col-md-1, .col-md-2, .col-md-3, .col-md-4, .col-md-5, .col-md-6, .col-md-7, .col-md-8, .col-md-9, .col-md-10, .col-md-11, .col-md-12 {
    float: left;
  }
  .col-md-12 {
    width: 100%;
    

  }
  .col-md-11 {
    width: 91.66666667%;
  }
  .col-md-10 {
    width: 83.33333333%;
  }
  .col-md-9 {
    width: 75%;
  }
  .col-md-8 {
    width: 66.66666667%;
  }
  .col-md-7 {
    width: 58.33333333%;
  }
  .col-md-6 {
    width: 50%;
  }
  .col-md-5 {
    width: 41.66666667%;
  }
  .col-md-4 {
    width: 33.33333333%;
  }
  .col-md-3 {
    width: 25%;
  }
  .col-md-2 {
    width: 16.66666667%;
  }
  .col-md-1 {
    width: 8.33333333%;
  }
 
table {
    border-collapse: collapse;
}

.table {
width: 100%;
margin-bottom: 1rem;
color: #212529;
}

.table th,
.table td {
padding: 0.75rem;
vertical-align: top;
border-top: 1px solid #dee2e6;
}

.table thead th {
vertical-align: bottom;
border-bottom: 2px solid #dee2e6;
}

.table tbody + tbody {
border-top: 2px solid #dee2e6;
}

.table-sm th,
.table-sm td {
padding: 0.3rem;
}

.table-bordered {
border: 1px solid #dee2e6;
}

.table-bordered th,
.table-bordered td {
border: 1px solid #dee2e6;
}

.table-bordered thead th,
.table-bordered thead td {
border-bottom-width: 2px;
}

.table-borderless th,
.table-borderless td,
.table-borderless thead th,
.table-borderless tbody + tbody {
border: 0;
}

.table-striped tbody tr:nth-of-type(odd) {
background-color: rgba(0, 0, 0, 0.05);
}

.table-hover tbody tr:hover {
color: #212529;
background-color: rgba(0, 0, 0, 0.075);
}

.table-primary,
.table-primary > th,
.table-primary > td {
background-color: #b8daff;
}

.table-primary th,
.table-primary td,
.table-primary thead th,
.table-primary tbody + tbody {
border-color: #7abaff;
}

.table-hover .table-primary:hover {
background-color: #9fcdff;
}

.table-hover .table-primary:hover > td,
.table-hover .table-primary:hover > th {
background-color: #9fcdff;
}

.table-secondary,
.table-secondary > th,
.table-secondary > td {
background-color: #d6d8db;
}

.table-secondary th,
.table-secondary td,
.table-secondary thead th,
.table-secondary tbody + tbody {
border-color: #b3b7bb;
}

.table-hover .table-secondary:hover {
background-color: #c8cbcf;
}

.table-hover .table-secondary:hover > td,
.table-hover .table-secondary:hover > th {
background-color: #c8cbcf;
}

.table-success,
.table-success > th,
.table-success > td {
background-color: #c3e6cb;
}

.table-success th,
.table-success td,
.table-success thead th,
.table-success tbody + tbody {
border-color: #8fd19e;
}

.table-hover .table-success:hover {
background-color: #b1dfbb;
}

.table-hover .table-success:hover > td,
.table-hover .table-success:hover > th {
background-color: #b1dfbb;
}

.table-info,
.table-info > th,
.table-info > td {
background-color: #bee5eb;
}

.table-info th,
.table-info td,
.table-info thead th,
.table-info tbody + tbody {
border-color: #86cfda;
}

.table-hover .table-info:hover {
background-color: #abdde5;
}

.table-hover .table-info:hover > td,
.table-hover .table-info:hover > th {
background-color: #abdde5;
}

.table-warning,
.table-warning > th,
.table-warning > td {
background-color: #ffeeba;
}

.table-warning th,
.table-warning td,
.table-warning thead th,
.table-warning tbody + tbody {
border-color: #ffdf7e;
}

.table-hover .table-warning:hover {
background-color: #ffe8a1;
}

.table-hover .table-warning:hover > td,
.table-hover .table-warning:hover > th {
background-color: #ffe8a1;
}

.table-danger,
.table-danger > th,
.table-danger > td {
background-color: #f5c6cb;
}

.table-danger th,
.table-danger td,
.table-danger thead th,
.table-danger tbody + tbody {
border-color: #ed969e;
}

.table-hover .table-danger:hover {
background-color: #f1b0b7;
}

.table-hover .table-danger:hover > td,
.table-hover .table-danger:hover > th {
background-color: #f1b0b7;
}

.table-light,
.table-light > th,
.table-light > td {
background-color: #fdfdfe;
}

.table-light th,
.table-light td,
.table-light thead th,
.table-light tbody + tbody {
border-color: #fbfcfc;
}

.table-hover .table-light:hover {
background-color: #ececf6;
}

.table-hover .table-light:hover > td,
.table-hover .table-light:hover > th {
background-color: #ececf6;
}

.table-dark,
.table-dark > th,
.table-dark > td {
background-color: #c6c8ca;
}

.table-dark th,
.table-dark td,
.table-dark thead th,
.table-dark tbody + tbody {
border-color: #95999c;
}

.table-hover .table-dark:hover {
background-color: #b9bbbe;
}

.table-hover .table-dark:hover > td,
.table-hover .table-dark:hover > th {
background-color: #b9bbbe;
}

.table-active,
.table-active > th,
.table-active > td {
background-color: rgba(0, 0, 0, 0.075);
}

.table-hover .table-active:hover {
background-color: rgba(0, 0, 0, 0.075);
}

.table-hover .table-active:hover > td,
.table-hover .table-active:hover > th {
background-color: rgba(0, 0, 0, 0.075);
}

.table .thead-dark th {
color: #fff;
background-color: #343a40;
border-color: #454d55;
}

.table .thead-light th {
color: #495057;
background-color: #e9ecef;
border-color: #dee2e6;
}

.table-dark {
color: #fff;
background-color: #343a40;
}

.table-dark th,
.table-dark td,
.table-dark thead th {
border-color: #454d55;
}

.table-dark.table-bordered {
border: 0;
}

.table-dark.table-striped tbody tr:nth-of-type(odd) {
background-color: rgba(255, 255, 255, 0.05);
}

.table-dark.table-hover tbody tr:hover {
color: #fff;
background-color: rgba(255, 255, 255, 0.075);
}

@media (max-width: 575.98px) {
.table-responsive-sm {
display: block;
width: 100%;
overflow-x: auto;
-webkit-overflow-scrolling: touch;
}
.table-responsive-sm > .table-bordered {
border: 0;
}
}

@media (max-width: 767.98px) {
.table-responsive-md {
display: block;
width: 100%;
overflow-x: auto;
-webkit-overflow-scrolling: touch;
}
.table-responsive-md > .table-bordered {
border: 0;
}
}

@media (max-width: 991.98px) {
.table-responsive-lg {
display: block;
width: 100%;
overflow-x: auto;
-webkit-overflow-scrolling: touch;
}
.table-responsive-lg > .table-bordered {
border: 0;
}
}

@media (max-width: 1199.98px) {
.table-responsive-xl {
display: block;
width: 100%;
overflow-x: auto;
-webkit-overflow-scrolling: touch;
}
.table-responsive-xl > .table-bordered {
border: 0;
}
}

.table-responsive {
display: block;
width: 100%;
overflow-x: auto;
-webkit-overflow-scrolling: touch;
}

.table-responsive > .table-bordered {
border: 0;
}

.table-responsive {
display: block;
width: 100%;
overflow-x: auto;
-webkit-overflow-scrolling: touch;
}

.table-responsive > .table-bordered {
border: 0;
}

   .od-body{
       border:3px solid #323232;
       padding: 25px;
       margin-top: 20px;
   }

   

  .od-logo{
      height: 80px;
  }

  .odtext24 h3{
      font-size:20px;
      text-align: left;
      line-height:26px;
      font-weight:600;
      color: #323232;
  }

  .odtext32{
      font-size:28px;
      text-align: center;
      line-height:34px;
      font-weight:600;
      padding-top: 8px;
      color: #043c5d;
  }

  .odtext32 span{
      font-weight: 800;
  }

  .od-bktext{
      font-size:18px;
      text-align: center;
      line-height: 22px;
      color: #000000;
      border:1px solid #c4c4c4 ;
      padding:8px ;
      font-weight: 600;
  }
  .od-banner{
      width: 100%;
  }
  .od-qrcode{
      background:#fff;
      padding: 15px;
      margin-top: 34px;
  }
  .od-qrcode img{
    width: 100%;
  }
  .mt30{
    margin-top: 35px;
  }
  .mb25{
      
      margin-bottom: 35px;
  }

  .mb40{
      margin-bottom: 40px;
  }

  .odbox1{
    border: 1px solid #c4c4c4;
    padding:25px 15px;
  }

  .odbox1 ol{
    margin-left: -20px;
  }

  .odbox2{
    border: 1px solid #000000;
    padding:15px 32px;
  }

  .odbox2 p{
    font-size:18px;
    color: #000000;
    font-weight: 600;
    margin-bottom: 6px;
    margin-top: 2px;
  }

  .odbox3 p{
    font-size:18px;
    color: #000000;
    font-weight: 600;
    margin-bottom: 8px;
    margin-top: 2px;
    text-align: right;
  }

  .od-body thead{
    text-align: left;
    font-weight: 600;
    font-size:17px;
    color: #072c6b;
   }

   .od-body tbody{
    text-align: left;
    font-size: 15px;
    font-weight:600;
   }

   .od-body ol{
    margin:0px;
    padding-left:0px;
   }

   .od-body ol li {
    margin-bottom: 8px;
    font-size: 15px;
   }

   .odbox2 {
    border: 1px solid #000000;
    padding: 15px 15px;
   }

   @media screen and (min-device-width: 320px) and (max-device-width:520px) {
.od-body {
   background: #ffffff;
}
.col-md-1, .col-md-10, .col-md-11, .col-md-12, .col-md-2, .col-md-3, .col-md-4, .col-md-5, .col-md-6, .col-md-7, .col-md-8, .col-md-9{
    position: relative;
    width: 100%;
    min-height: 1px;
    padding-right: 15px;
    padding-left: 15px;
}

.row {
    display: flex;
    flex-wrap: wrap;
    margin-right: -15px;
    margin-left: -15px;
}

.container {
    max-width: 100% !important;
    margin: 0 auto;
}

.odtext32 {
  font-size: 21px;
  margin-top: 15px;
  margin-bottom: 15px;
  line-height: 25px;
}

.odtext24{
  overflow-x: scroll;
}

.mid-10{
  text-align: center;
}

.od-logo{
  height: 50px;
}
.od-body thead {
    font-size: 12px;
}

.od-body tbody {
    font-size: 12px;
}

}

</style>

</head>
<!-- /Head -->


<body>
    <div class="container">
        <div class="od-body mb25">
           <div class="row mb40">
               <div class="col-md-3 mid-10"><img src="{{url('public/template/logo.png')}}" class="od-logo"/></div>
               <div class="col-md-6 odtext32"><span>ODBUS</span> e-Ticketing Service<br/> Electronic Reservation Slip</div>
               <div class="col-md-3">
                   <div class="od-bktext">Booking Date</div>
                   <div class="od-bktext">{{$bookingdate}}</div>
               </div>
           </div>
           <div class="row  mt30 mb25">
            <div class="col-md-9 odtext24">
                <h3>JOURNEY DETAILS:</h3>
                <table class="table table-bordered">
                    <thead>
                      <tr>
                        <th scope="col"><b>PNR No:</b>  {{$pnr}}</th>
                        <th scope="col"><b>Bus Name/Number:</b> {{$busname}}-{{$busNumber}}</th>
                       
                      </tr>
                    </thead>
                    <tbody>
                      <tr>
                        <td><b>Journey Date:</b> {{$journeydate}}</td>
                        <td><b>Bus Route:</b> {{$source}}-{{$destination}}</td>
                      </tr>
                     
                      <tr>
                        <td><b>From:</b> {{$source}}</td>
                        <td><b>To:</b> {{$destination}}</td>
                      </tr>
                      <tr>
                        <td><b>Boarding At :</b> {{$source}} ({{$boarding_point}})</td>
                        <td><b>Droping At:</b> {{$destination}} ({{$dropping_point}})</td>
                      </tr>

                      <tr>
                        <td><b>Deparature Time:</b>  {{$departureTime}} </td>
                        <td><b>Arrival Time:</b> {{$arrivalTime}}</td>
                      </tr>

                      <tr>
                        <td><b>Passenger Mobile No:</b> {{$customer_number}}</td>
                        <td><b>Conductor Mobile No:</b> {{$conductor_number}}</td>
                      </tr>

                      @if($agent_number != '') 

                      <tr>
                        <td><b>Agent Mobile No:</b> {{$agent_number}}</td>
                        <td>Seat({{$total_seats}})- {{$seat_names}}</td>
                      </tr>

                      @else
                                
                      @endif

                      @if($agent_number == '') 

                      <tr>
                        <td>Seat({{$total_seats}})- {{$seat_names}}</td>
                        <td>GST Invoive : {{ ($customer_gst_status == 1) ? 'Yes' : 'No' }} </td>
                      </tr>

                      @else
                                
                      @endif

                      @if($customer_gst_status == 1)
                    
                    <tr>
                      <td>GSTIN : {{$customer_gst_number}}</td>
                      <td>Business Name : {{$customer_gst_business_name}}</td>
                      </tr>

                      <tr>
                      <td>Business Email : {{$customer_gst_business_email}}</td>
                      <td>Business Address : {{$customer_gst_business_address}}</td>
                      </tr>

                      @else
                    @endif

                    </tbody>
                  </table>
                
            </div>

            <div class="col-md-3">
              <div class="od-qrcode">
              <img src="{{$qrcode_image_path}}"/>
              </div>
            </div>
        </div>

        <div class="row mt30 mb25">
            <div class="col-md-12 odtext24">
                <h3>PASSENGER DETAILS:</h3>
                <table class="table table-bordered">
                    <thead>
                      <tr>
                        <th scope="col">Sl No.</th>
                        <th scope="col">Name</th>
                        <th scope="col">Age</th>
                        <th scope="col">Gender</th>
                        <th scope="col">Seat</th>
                      </tr>
                    </thead>
                    <tbody>
                    
                    @foreach($passengerDetails as $passenger) 

                      <tr>
                        <th scope="row">{{$loop->iteration}}</th>
                        <td>{{$passenger['passenger_name']}} </td>
                        <td>{{$passenger['passenger_age']}}</td>
                        <td>{{$passenger['passenger_gender']}}</td>
                        <td>{{$seat_no[$loop->index]}}</td>
                      </tr>

                      @endforeach                    
                    </tbody>
                  </table>
            </div>
            
        </div>

        <!-- <div class="row mt30 mb25">
            <div class="col-md-12"><img src="{{url('public/template/banner-01.png')}}" class="od-banner"/></div>
        </div> -->

        <div class="row">  

        <div class="col-md-6 odtext24">

           <h3>FARE BREAKUP</h3>

            <table style="width: 100%; margin-top: 20px;">
              <tr>
                            
               
                <table class="table table-bordered" cellpadding="0" cellspacing="0" border="1" style="width: 100%;" >

                  <tbody>
                    <tr>
                      <td class="text-left">Base Fare</td>
                      <td class="text-right"><b>₹{{$owner_fare + $odbus_charges}}</b></td>
                    </tr>	

                    @if($coupon_discount > 0)
                    
                    <tr>
                      <td class="text-left">Coupon Discount</td>
                      <td class="text-right"><b>- ₹{{$coupon_discount}}</b></td>
                      </tr>

                      @else
                    @endif


                    @if($customer_gst_status == 1)
                    
                    <tr>
                      <td class="text-left">GST ({{$customer_gst_percent}}%)</td>
                      <td class="text-right"><b>+ ₹{{$customer_gst_amount}}</b></td>
                      </tr>

                      @else
                    @endif

                    
                    <tr>
                      <td class="text-left">Transaction Fee</td>
                      <td class="text-right"><b>+₹{{$transactionFee}}</b></td>
                    </tr>

                    @if($customer_comission > 0)
                    
                    <tr>
                      <td class="text-left">Commission</td>
                      <td class="text-right"><b>+₹{{$customer_comission}}</b></td>
                      </tr>

                      @else
                    @endif
                  
                    <tr>
                      <td class="text-left"><strong>Total Fare</strong></td>
                      <td class="text-right"><strong>₹{{$payable_amount + $customer_comission}}</strong></td>
                    </tr>
                    </tbody>
                  
                </table>

        </div> 

        <div class="col-md-6 odtext24">  
               
                <h3>CANCELLATION POLICY</h3>					  


                <table class="table table-bordered odbus-journey" cellpadding="0" cellspacing="0" border="1" style="width: 100%;">
                <thead>
                  <tr style="color: black !important;background-color: #fff !important;">
                    <th scope="col">Cancellation Time</th>
                    <th scope="col">Cancellation Charges</th>
                  </tr>
                </thead>
                <tbody>
                @foreach($cancelation_policy as $can) 
                <tr>
                    <td>{{ $can->duration }} hr</td>
                    <td>{{ $can->deduction }}%</td>
                  </tr>
                  @endforeach                 
                </tbody>
              </table>

        </div> 
</div>  

        <div class="row mt30">
            <div class="col-md-12 odtext24"><h3>TERMS & CONDITIONS</h3></div>
          </div>
        <div class="row">
            <div class="col-md-12 ">
              <div class="odbox1">
              <div class="row" style="font-size: 12px;">
                          <div class="col-md-12">

                            <table width="100%">
                              <tr> <td style="width: 50%;">
                            <h6> ARE NOT ODBUS RESPONSIBILITIES</h6>
                            <ol>
                             
                              <li>	Any incorrect data provided by customer while booking the ticket.</li>
                              <li>	Any Bus delays, breakdowns, service cancellations and accidents.</li>
                              <li>	Any misbehavior by the bus partner staff or co-passenger.</li>
                              <li>	Loss/damage of baggage.</li>
                              <li>	Late arrival by customer at the boarding point.</li>
                              <li>	Customer phone not reachable, bus Conductor did not call at the boarding point and left without picking the customer.</li>
                              <li>	Customer waiting at wrong boarding point.</li>
                              <li>	Any change of seat numbers done by the bus Conductor manually.</li>
                              <li>	Bus Partner changing the boarding point for any reason.</li>
                              <li>	Bus Conductor arranging a different vehicle for pickup at boarding point and boarding the actual vehicle at a different place.</li>
                              <li>	Bus Conductor objecting on carrying pets.</li>
                              <li>	Improper booking done by agent, please contact the agent directly.</li>
                              <li>	Bus owners not providing In-bus amenities/facilities mentioned while booking.</li>
                              <li>	Change of bus type by Bus owner for any reason.</li>
                              <li>	Bus fares are not controlled by ODBUS, so any change in fares is not the responsibility of ODBUS.</li>
                              <li>	The fare might vary based on the seat selected. So the fare shown in the “Search Page” and “Seat Selection Page” might vary</li>
                              
                            </ol>
                            </td>
                           
                            <td style="width: 50%;">
                            <h6> CANCELLATION POLICY</h6>
                            <ol>
                             
                              <p>The tickets booked through ODBUS Can be cancelled and note that Cancel Can be possible before 12 hours of the departure of the said bus. </p>
                              <li>Partial cancellation not allowed in ODBUS.</li>
                              <li>	In respect of refunds due to cancellation of service by Operator , passenger is required to send an e-mail to support@odbus.in mentioning PNR of the ticket. We will verify and refund the amount to the concerned Credit card / Internet banking / UPI.</li>
                              <li>	Refunds for Ticket cancellations / Failed Transactions to passengers will be given normally in 10 - 12 Bank working days by ODBUS, after the cancellation of ticket or receipt of e-mail. If refunds are delayed, passengers may contact ODBUS Executive at 9583918888 ( 09:00 AM to 07:00 PM )</li>
                              <li>	Customer can't claim any cancellation refund on rescheduled tickets.</li>
                              <li>	The cancellation terms are different for each Operator which is set by Bus Owners itself. These terms are shown while booking as well as on the ticket printout/Email confirmation.</li>
                              <li>	Tickets booked through online should be cancelled only through online.</li>
                              <li>	Transaction Fee / GST are non-refundable for Ticket Cancellation / Service Cancellation.</li>
                              <li>	Cancellation charges are applicable on Base fare not on the discounted .</li>
                              
                            </ol>
                            </td>
                            </tr>
                            </table>
                          </div>
                         
                        </div>
            </div>
            </div>
          </div>
            <div class="row">
            <div class="col-md-12 mt30">
              <div class="odbox2">
                <div class="row ">
                  <div class="col-md-12">
                    <p>Contact Information</p>
                    <p>ODBUS Helpline (07:00 AM To 11:00 PM):9583-918-888 (For Online Booking issue or Online Cancellation issue.) </p>
                    <p>ODBUS Customer Support Email (Response time 3 working days): support@odbus.in</p>
                  </div>
                </div>
              </div>
            </div>
          </div>
            <div class="row">
            <div class="col-md-12 mt30">
              <div class="odbox3">
                <div class="row ">
                  <div class="col-md-12">
                    <p>Thankyou</p>
                    <p>Team ODBUS</p>
                  </div>
                </div>
              </div>
            </div>
          </div>
        </div>
    </div>
</body>
</html>