<?php
/**
 * Created by PhpStorm.
 * User: UniQue
 * Date: 4/11/2018
 * Time: 1:24 PM
 */

namespace App\Services;


use App\Markdown;
use App\Markup;
use App\Vat;

class AmadeusHelper
{

    private $AmadeusConfig;

    public function __construct(){
        $this->AmadeusConfig = new AmadeusConfig();
    }

    public function lowFarePlusResponseValidator($responseArray){
        if(empty($responseArray)){
            return 0;
        }else{
            if(isset($responseArray['soap_Body']['wmLowFarePlusResponse']['OTA_AirLowFareSearchPlusRS']['Success'])){
                return 1;
            }else{
                if(isset($responseArray['soap_Body']['wmLowFarePlusResponse']['OTA_AirLowFareSearchPlusRS']['Errors']['Error'])){
                    $error = $responseArray['soap_Body']['wmLowFarePlusResponse']['OTA_AirLowFareSearchPlusRS']['Errors']['Error'];
                    return [21 , $error];
                }else{
                    return 2;
                }
            }
        }
    }

    public function lowFarePlusResponseXMLValidator($responseXML){
        $responseData = simplexml_load_string($this->AmadeusConfig->mungXMl($responseXML));
        if(empty($responseData)){
            return 0;
        }else{
            if(isset($responseData->soap_Body->wmLowFarePlusResponse->OTA_AirLowFareSearchPlusRS->Success)){
                return 1;
            }else{
                if(isset($responseData->soap_Body->wmLowFarePlusResponse->OTA_AirLowFareSearchPlusRS->Errors->Error)){
                    $error = $responseData->soap_Body->wmLowFarePlusResponse->OTA_AirLowFareSearchPlusRS->Errors->Error;
                    return [21 , $error];
                }else{
                    return 2;
                }
            }
        }
    }

    public function priceTypeCalculator($type,$value,$amount){
        if($type == 1){
            return (($value/100) * $amount);
        }if($type == 0){
            return $value;
        }
    }

    public function lowFarePlusResponseSort($responseArray){

        $sortedResponse = [];

        $itineraries = $responseArray['soap_Body']['wmLowFarePlusResponse']['OTA_AirLowFareSearchPlusRS']['PricedItineraries']['PricedItinerary'];
        if(isset($itineraries[0])){
            foreach($itineraries as $itinerary_serial => $itinerary ){

                $originDestinationInfo = [];
                $defaultFareInfo = [];

                $originDestinations = $itinerary['AirItinerary']['OriginDestinationOptions']['OriginDestinationOption'];
                $fareInfoCount = 0;
                $stops = 0;
                $displayAirline = 0;
                $cabinType = 0;
                $originDestinationsCount = 0;
                if(isset($originDestinations[0])){
                    $originDestinationsCount = count($originDestinations);
                    $originDestinationPlacement = 1;
                    foreach($originDestinations as $i => $originDestination){

                        if(isset($originDestination['FlightSegment'][0])){
                            $stops = count($originDestination['FlightSegment']) - 1;
                            $displayAirline = $itinerary['AirItineraryPricingInfo']['FareInfos']['FareInfo'][0]['FilingAirline']['@attributes']['Code'];

                            $cabinType = $originDestination['FlightSegment'][0]['TPA_Extensions']['CabinType']['@attributes']['Cabin'];
                            foreach($originDestination['FlightSegment'] as $j => $flightSegment){
                                $dateDiff = intval((strtotime($flightSegment['@attributes']['ArrivalDateTime'])-strtotime($flightSegment['@attributes']['DepartureDateTime']))/60);
                                $hours = intval($dateDiff/60);
                                $minutes = $dateDiff%60;
                                $flightSegmentInfo = [
                                    'originDestinationPlacement'     =>  $originDestinationPlacement,
                                    'departureDateTime'     => $flightSegment['@attributes']['DepartureDateTime'],
                                    'arrivalDateTime'       => $flightSegment['@attributes']['ArrivalDateTime'],
                                    'flightNumber'          => $flightSegment['@attributes']['FlightNumber'],
                                    'resBookDesigCode'      => $flightSegment['@attributes']['ResBookDesigCode'],
                                    'departureAirportName'  => $flightSegment['DepartureAirport'],
                                    'arrivalAirportName'    => $flightSegment['ArrivalAirport'],
                                    'equipment'             => array_get(array_get($flightSegment['Equipment'],'',$flightSegment['Equipment']),'AirEquipType',array_get($flightSegment['Equipment'],'',$flightSegment['Equipment'])),
                                    'journeyDuration'       => $hours ." hr(s) ".$minutes." min(s)",
                                    'journeyTotalDuration'  => array_get($flightSegment['TPA_Extensions'],'JourneyTotalDuration',0),
                                    'cabin'                 => $flightSegment['TPA_Extensions']['CabinType']['@attributes']['Cabin'],
                                    'operatingAirlineName'  => $flightSegment['OperatingAirline'],
                                    'marketingAirline'      => $flightSegment['MarketingAirline'],
                                    'departureAirportCode'  => $itinerary['AirItineraryPricingInfo']['FareInfos']['FareInfo'][$fareInfoCount]['DepartureAirport']['@attributes']['LocationCode'],
                                    'arrivalAirportCode'    => $itinerary['AirItineraryPricingInfo']['FareInfos']['FareInfo'][$fareInfoCount]['ArrivalAirport']['@attributes']['LocationCode'],
                                    'filingAirlineCode'    =>  $itinerary['AirItineraryPricingInfo']['FareInfos']['FareInfo'][$fareInfoCount]['FilingAirline']['@attributes']['Code']
                                ];
                                 $fareInfoCount = $fareInfoCount +1;
                                array_push($originDestinationInfo , $flightSegmentInfo);
                            }
                        }

                        else{
                            $stops = 0;
                            if(isset($itinerary['AirItineraryPricingInfo']['FareInfos']['FareInfo'][0])){
                                $displayAirline = $itinerary['AirItineraryPricingInfo']['FareInfos']['FareInfo'][0]['FilingAirline']['@attributes']['Code'];
                            }else{
                                $displayAirline = $itinerary['AirItineraryPricingInfo']['FareInfos']['FareInfo']['FilingAirline']['@attributes']['Code'];
                            }
                            $cabinType = $originDestination['FlightSegment']['TPA_Extensions']['CabinType']['@attributes']['Cabin'];
                            $flightSegment = $originDestination['FlightSegment'];
                            $dateDiff = intval((strtotime($flightSegment['@attributes']['ArrivalDateTime'])-strtotime($flightSegment['@attributes']['DepartureDateTime']))/60);
                            $hours = intval($dateDiff/60);
                            $minutes = $dateDiff%60;
                            $flightSegmentInfo = [
                                'originDestinationPlacement'     =>  $originDestinationPlacement,
                                'departureDateTime'     => $flightSegment['@attributes']['DepartureDateTime'],
                                'arrivalDateTime'       => $flightSegment['@attributes']['ArrivalDateTime'],
                                'flightNumber'          => $flightSegment['@attributes']['FlightNumber'],
                                'resBookDesigCode'      => $flightSegment['@attributes']['ResBookDesigCode'],
                                'departureAirportName'  => $flightSegment['DepartureAirport'],
                                'arrivalAirportName'    => $flightSegment['ArrivalAirport'],
                                'equipment'             => array_get(array_get($flightSegment['Equipment'],'',$flightSegment['Equipment']),'AirEquipType',array_get($flightSegment['Equipment'],'',$flightSegment['Equipment'])),
                                'journeyDuration'       => $hours ." hr(s) ".$minutes." min(s)",
                                'journeyTotalDuration'  => array_get($flightSegment['TPA_Extensions'],'JourneyTotalDuration',0),
                                'cabin'                 => $flightSegment['TPA_Extensions']['CabinType']['@attributes']['Cabin'],
                                'operatingAirlineName'  => $flightSegment['OperatingAirline'],
                                'marketingAirline'      => $flightSegment['MarketingAirline'],
                                'departureAirportCode'  => $itinerary['AirItineraryPricingInfo']['FareInfos']['FareInfo'][$fareInfoCount]['DepartureAirport']['@attributes']['LocationCode'],
                                'arrivalAirportCode'    => $itinerary['AirItineraryPricingInfo']['FareInfos']['FareInfo'][$fareInfoCount]['ArrivalAirport']['@attributes']['LocationCode'],
                                'filingAirlineCode'    =>  $itinerary['AirItineraryPricingInfo']['FareInfos']['FareInfo'][$fareInfoCount]['FilingAirline']['@attributes']['Code']
                            ];
                            $fareInfoCount = $fareInfoCount +1;
                            array_push($originDestinationInfo , $flightSegmentInfo);
                        }

                        $originDestinationPlacement = $originDestinationPlacement + 1;
                    }
                }
                else{
                    $originDestination = $originDestinations;
                    $originDestinationsCount = 1;
                    if(isset($originDestination['FlightSegment'][0])){
                        $stops = count($originDestination['FlightSegment']) - 1;
                        $cabinType = $originDestination['FlightSegment'][0]['TPA_Extensions']['CabinType']['@attributes']['Cabin'];
                        $displayAirline = $itinerary['AirItineraryPricingInfo']['FareInfos']['FareInfo'][0]['FilingAirline']['@attributes']['Code'];
                        foreach($originDestination['FlightSegment'] as $j => $flightSegment){
                            $dateDiff = intval((strtotime($flightSegment['@attributes']['ArrivalDateTime'])-strtotime($flightSegment['@attributes']['DepartureDateTime']))/60);
                            $hours = intval($dateDiff/60);
                            $minutes = $dateDiff%60;


                            $flightSegmentInfo = [
                                'originDestinationPlacement'     =>  1,
                                'departureDateTime'     => $flightSegment['@attributes']['DepartureDateTime'],
                                'arrivalDateTime'       => $flightSegment['@attributes']['ArrivalDateTime'],
                                'flightNumber'          => $flightSegment['@attributes']['FlightNumber'],
                                'resBookDesigCode'      => $flightSegment['@attributes']['ResBookDesigCode'],
                                'departureAirportName'  => $flightSegment['DepartureAirport'],
                                'arrivalAirportName'    => $flightSegment['ArrivalAirport'],
                                'equipment'             => array_get(array_get($flightSegment['Equipment'],'',$flightSegment['Equipment']),'AirEquipType',array_get($flightSegment['Equipment'],'',$flightSegment['Equipment'])),
                                'journeyDuration'       => $hours ." hr(s) ".$minutes." min(s)",
                                'journeyTotalDuration'  => array_get($flightSegment['TPA_Extensions'],'JourneyTotalDuration',0),
                                'cabin'                 => $flightSegment['TPA_Extensions']['CabinType']['@attributes']['Cabin'],
                                'operatingAirlineName'  => $flightSegment['OperatingAirline'],
                                'marketingAirline'      => $flightSegment['MarketingAirline'],
                                'departureAirportCode'  => $itinerary['AirItineraryPricingInfo']['FareInfos']['FareInfo'][$fareInfoCount]['DepartureAirport']['@attributes']['LocationCode'],
                                'arrivalAirportCode'    => $itinerary['AirItineraryPricingInfo']['FareInfos']['FareInfo'][$fareInfoCount]['ArrivalAirport']['@attributes']['LocationCode'],
                                'filingAirlineCode'    =>  $itinerary['AirItineraryPricingInfo']['FareInfos']['FareInfo'][$fareInfoCount]['FilingAirline']['@attributes']['Code']
                            ];
                            $fareInfoCount = $fareInfoCount +1;
                            array_push($originDestinationInfo , $flightSegmentInfo);
                        }
                    }
                    else{
                        $flightSegment = $originDestination['FlightSegment'];
                        $stops = 0;
                        $cabinType = $originDestination['FlightSegment']['TPA_Extensions']['CabinType']['@attributes']['Cabin'];
                        if(isset($itinerary['AirItineraryPricingInfo']['FareInfos']['FareInfo'][0])){
                            $displayAirline = $itinerary['AirItineraryPricingInfo']['FareInfos']['FareInfo'][0]['FilingAirline']['@attributes']['Code'];
                        }else{
                            $displayAirline = $itinerary['AirItineraryPricingInfo']['FareInfos']['FareInfo']['FilingAirline']['@attributes']['Code'];
                        }
                        $dateDiff = intval((strtotime($flightSegment['@attributes']['ArrivalDateTime'])-strtotime($flightSegment['@attributes']['DepartureDateTime']))/60);
                        $hours = intval($dateDiff/60);
                        $minutes = $dateDiff%60;

                        $flightSegmentInfo = [
                            'originDestinationPlacement'     =>  1,
                            'departureDateTime'     => $flightSegment['@attributes']['DepartureDateTime'],
                            'arrivalDateTime'       => $flightSegment['@attributes']['ArrivalDateTime'],
                            'flightNumber'          => $flightSegment['@attributes']['FlightNumber'],
                            'resBookDesigCode'      => $flightSegment['@attributes']['ResBookDesigCode'],
                            'departureAirportName'  => $flightSegment['DepartureAirport'],
                            'arrivalAirportName'    => $flightSegment['ArrivalAirport'],
                            'equipment'             => array_get(array_get($flightSegment['Equipment'],'',$flightSegment['Equipment']),'AirEquipType',array_get($flightSegment['Equipment'],'',$flightSegment['Equipment'])),
                            'journeyDuration'       => $hours ." hr(s) ".$minutes." min(s)",
                            'journeyTotalDuration'  => array_get($flightSegment['TPA_Extensions'],'JourneyTotalDuration',0),
                            'cabin'                 => $flightSegment['TPA_Extensions']['CabinType']['@attributes']['Cabin'],
                            'operatingAirlineName'  => $flightSegment['OperatingAirline'],
                            'marketingAirline'      => $flightSegment['MarketingAirline'],
                            'departureAirportCode'  => $itinerary['AirItineraryPricingInfo']['FareInfos']['FareInfo'][$fareInfoCount]['DepartureAirport']['@attributes']['LocationCode'],
                            'arrivalAirportCode'    => $itinerary['AirItineraryPricingInfo']['FareInfos']['FareInfo'][$fareInfoCount]['ArrivalAirport']['@attributes']['LocationCode'],
                            'filingAirlineCode'    =>  $itinerary['AirItineraryPricingInfo']['FareInfos']['FareInfo'][$fareInfoCount]['FilingAirline']['@attributes']['Code']
                        ];
                        $fareInfoCount = $fareInfoCount +1;
                        array_push($originDestinationInfo , $flightSegmentInfo);
                    }
                }

                $fareBrakeDowns = $itinerary['AirItineraryPricingInfo']['PTC_FareBreakdowns']['PTC_FareBreakdown'];
                if(isset($fareBrakeDowns[0])){
                    foreach($fareBrakeDowns as $serial => $brakeDown){
                        $bags = [];
                        if(isset($brakeDown['PassengerFare']['FreeBagAllowance'])){
                            $baggageAllowances = $brakeDown['PassengerFare']['FreeBagAllowance'];
                            if(isset($baggageAllowaces[0])){
                                foreach($baggageAllowances as $b => $baggageAllowance){
                                    $baggageAllowanceInfo = array_get($baggageAllowance, 'Weight',array_get($baggageAllowance[''],'Quantity',0));
                                    $baggageUnit          = array_get($baggageAllowance,'Unit',array_get($baggageAllowance[''],'Type',0));
                                    $bagArray = $baggageAllowanceInfo."-".$baggageUnit;
                                    array_push($bags, $bagArray);
                                }
                            }else{
                                $baggageAllowance = $baggageAllowances;
                                    $baggageAllowanceInfo = array_get($baggageAllowance, 'Weight',array_get($baggageAllowance,'Quantity',0));
                                    $baggageUnit          = array_get($baggageAllowance,'Unit',array_get($baggageAllowance,'Type',0));
                                    $bagArray = $baggageAllowanceInfo."-".$baggageUnit;
                                    array_push($bags, $bagArray);

                            }
                        }

                      $fareBrakeDownInfo = [
                          'passengerType' => $brakeDown['PassengerTypeQuantity']['@attributes']['Code'],
                          'quantity'      => $brakeDown['PassengerTypeQuantity']['@attributes']['Quantity'],
                          'price'         => $brakeDown['PassengerFare']['TotalFare']['@attributes']['Amount'],
                          'freeBagAllowance' => $bags,
                      ];
                      array_push($defaultFareInfo, $fareBrakeDownInfo);
                    }
                }
                else{
                    $brakeDown = $fareBrakeDowns;
                    $fareBrakeDownInfo = [
                        'passengerType' => $brakeDown['PassengerTypeQuantity']['@attributes']['Code'],
                        'quantity'      => $brakeDown['PassengerTypeQuantity']['@attributes']['Quantity'],
                        'price'         => $brakeDown['PassengerFare']['TotalFare']['@attributes']['Amount'],
                    ];
                    array_push($defaultFareInfo, $fareBrakeDownInfo);
                }

                $itineraryDefaultPrice = $itinerary['AirItineraryPricingInfo']['ItinTotalFare']['TotalFare']['@attributes']['Amount'];
                $customerMarkup        = 0;
                $agentMarkup           = 0;
                $adminMarkup           = 0;
                $vat                   = 0;
                $airlineMarkdown       = 0;
                $customerTotal         = 0;
                $agentTotal            = 0;
                $adminTotal            = 0;
                $displayTotal          = 0;


                $agentMarkupInfo    = Markup::where('role_id', 2)->first();
                $customerMarkupInfo = Markup::where('role_id', 3)->first();
                $vatInfo            = Vat::where('id',1)->first();
                $markdownInfo       = Markdown::where('airline_code',$itinerary['AirItineraryPricingInfo']['@attributes']['ValidatingAirlineCode'])->first();
                if(!is_null($markdownInfo)){
                   $airlineMarkdown = $this->priceTypeCalculator($markdownInfo->type,$markdownInfo->type,$itineraryDefaultPrice);
                }
                $agentMarkup    = $this->priceTypeCalculator($agentMarkupInfo->flight_markup_type,$agentMarkupInfo->flight_markup_type,$itineraryDefaultPrice);
                $customerMarkup = $this->priceTypeCalculator($customerMarkupInfo->flight_markup_type,$customerMarkupInfo->flight_markup_type,$itineraryDefaultPrice);
                $adminMarkup    = 0;
                $vat            = $this->priceTypeCalculator($vatInfo->flight_vat_type,$vatInfo->flight_vat_type,$itineraryDefaultPrice);

                $customerTotal = ($itineraryDefaultPrice + $customerMarkup + $vat) - $airlineMarkdown;
                $agentTotal    = ($itineraryDefaultPrice + $agentMarkup + $vat) - $airlineMarkdown;
                $adminTotal    = ($itineraryDefaultPrice + $adminMarkup + $vat) - $airlineMarkdown;

                if(auth()->guest()){
                    $displayTotal = $customerTotal;
                }else{
                    if(auth()->user()->hasRole('admin')){
                        $displayTotal = $adminTotal;
                    }elseif(auth()->user()->hasRole('agent')){
                        $displayTotal = $agentTotal;
                    }elseif(auth()->user()->hasRole('customer')){
                        $displayTotal = $customerTotal;
                    }
                }

                $itineraryInformation = [
                    'directionInd'             => $itinerary['AirItinerary']['@attributes']['DirectionInd'],
                    'ticketTimeLimit'          => $itinerary['TicketingInfo']['@attributes']['TicketTimeLimit'],
                    'pricingSource'            => $itinerary['AirItineraryPricingInfo']['@attributes']['PricingSource'],
                    'validatingAirlineCode'    => $itinerary['AirItineraryPricingInfo']['@attributes']['ValidatingAirlineCode'],
                    'defaultItineraryPrice'    => $itineraryDefaultPrice,
                    'originDestinationsCount'  => $originDestinationsCount,
                    'cabinType'                => $cabinType,
                    'stops'                    => $stops,
                    'displayAirline'           => $displayAirline,
                    'adminToCustomerMarkup'    => $customerMarkup,
                    'adminToAgentMarkup'       => $agentMarkup,
                    'adminToAdminMarkup'       => $adminMarkup,
                    'vat'                      => $vat,
                    'airlineMarkdown'          => $airlineMarkdown,
                    'customerTotal'            => $customerTotal,
                    'agentTotal'               => $agentTotal,
                    'adminTotal'               => $adminTotal,
                    'displayTotal'             => $displayTotal,
                    'itineraryPassengerInfo'   => $defaultFareInfo,
                    'originDestinations'       => $originDestinationInfo
                ];

                array_push($sortedResponse,$itineraryInformation);
            }
        }else{
            $itinerary = $itineraries;



        }

        return $sortedResponse;

    }

    public function lowFarePlusResponseSortFromXML($responseXML){
        $responseData = simplexml_load_string($this->AmadeusConfig->mungXMl($responseXML));

        $sortedResponse = [];

        $itineraries = $responseData->soap_Body->wmLowFarePlusResponse->OTA_AirLowFareSearchPlusRS->PricedItineraries->PricedItinerary;
        if(isset($itineraries[0])){
            foreach($itineraries as $itinerary_serial => $itinerary ){

                $originDestinationInfo = [];
                $defaultFareInfo = [];

                $originDestinations = $itinerary->AirItinerary->OriginDestinationOptions->OriginDestinationOption;
                $fareInfoCount = 0;
                $stops = 0;
                $displayAirline = 0;
                $cabinType = 0;
                $originDestinationsCount = 0;
                if(isset($originDestinations[0])){
                    $originDestinationsCount = count($originDestinations);
                    $originDestinationPlacement = 1;
                    foreach($originDestinations as $i => $originDestination){

                        if(isset($originDestination->FlightSegment[0])){
                            $stops = count($originDestination->FlightSegment) - 1;
                            $displayAirline = $itinerary->AirItineraryPricingInfo->FareInfos->FareInfo[0]->FilingAirline->attributes()->Code[0];

                            $cabinType = $originDestination->FlightSegment[0]->TPA_Extensions->CabinType->attributes()->Cabin[0];
                            foreach($originDestination->FlightSegment as $j => $flightSegment){
                                $dateDiff = intval((strtotime($flightSegment['ArrivalDateTime'])-strtotime($flightSegment['DepartureDateTime']))/60);
                                $hours = intval($dateDiff/60);
                                $minutes = $dateDiff%60;
                                $flightSegmentInfo = [
                                    'originDestinationPlacement'     =>  $originDestinationPlacement,
                                    'departureDateTime'     => $flightSegment['DepartureDateTime'],
                                    'arrivalDateTime'       => $flightSegment['ArrivalDateTime'],
                                    'flightNumber'          => $flightSegment['FlightNumber'],
                                    'resBookDesigCode'      => $flightSegment['ResBookDesigCode'],
                                    'departureAirportName'  => $flightSegment->DepartureAirport,
                                    'arrivalAirportName'    => $flightSegment->ArrivalAirport,
                                    'equipment'             => $flightSegment->Equipment[0],
                                    'journeyDuration'       => $hours ." hr(s) ".$minutes." min(s)",
                                    'journeyTotalDuration'  => $flightSegment->TPA_Extensions->JourneyTotalDuration,
                                    'cabin'                 => $flightSegment->TPA_Extensions->CabinType->attributes()->Cabin[0],
                                    'operatingAirlineName'  => $flightSegment->OperatingAirline[0],
                                    'marketingAirline'      => $flightSegment->MarketingAirline[0],
                                    'operatingAirlineCode'  => $flightSegment->OperatingAirline->attributes()->Code[0],
                                    'marketingAirlineCode'  => $flightSegment->MarketingAirline->attributes()->Code[0],
                                    'departureAirportCode'  => $itinerary->AirItineraryPricingInfo->FareInfos->FareInfo[$fareInfoCount]->DepartureAirport->attributes()->LocationCode[0],
                                    'arrivalAirportCode'    => $itinerary->AirItineraryPricingInfo->FareInfos->FareInfo[$fareInfoCount]->ArrivalAirport->attributes()->LocationCode[0],
                                    'filingAirlineCode'    =>  $itinerary->AirItineraryPricingInfo->FareInfos->FareInfo[$fareInfoCount]->FilingAirline->attributes()->Code[0]
                                ];
                                $fareInfoCount = $fareInfoCount +1;
                                array_push($originDestinationInfo , $flightSegmentInfo);
                            }
                        }

                        else{
                            $stops = 0;
                            if(isset($itinerary->AirItineraryPricingInfo->FareInfos->FareInfo[0])){
                                $displayAirline = $itinerary->AirItineraryPricingInfo->FareInfos->FareInfo[0]->FilingAirline->attributes()->Code[0];
                            }else{
                                $displayAirline = $itinerary->AirItineraryPricingInfo->FareInfos->FareInfo->FilingAirline->attributes()->Code[0];
                            }
                            $cabinType = $originDestination->FlightSegment->TPA_Extensions->CabinType->attributes()->Cabin[0];
                            $flightSegment = $originDestination->FlightSegment;
                            $dateDiff = intval((strtotime($flightSegment['ArrivalDateTime'])-strtotime($flightSegment['DepartureDateTime']))/60);
                            $hours = intval($dateDiff/60);
                            $minutes = $dateDiff%60;
                            $flightSegmentInfo = [
                                'originDestinationPlacement'     =>  $originDestinationPlacement,
                                'departureDateTime'     => $flightSegment['DepartureDateTime'],
                                'arrivalDateTime'       => $flightSegment['ArrivalDateTime'],
                                'flightNumber'          => $flightSegment['FlightNumber'],
                                'resBookDesigCode'      => $flightSegment['ResBookDesigCode'],
                                'departureAirportName'  => $flightSegment->DepartureAirport,
                                'arrivalAirportName'    => $flightSegment->ArrivalAirport,
                                'equipment'             => $flightSegment->Equipment[0],
                                'journeyDuration'       => $hours ." hr(s) ".$minutes." min(s)",
                                'journeyTotalDuration'  => $flightSegment->TPA_Extensions->JourneyTotalDuration,
                                'cabin'                 => $flightSegment->TPA_Extensions->CabinType->attributes()->Cabin[0],
                                'operatingAirlineName'  => $flightSegment->OperatingAirline[0],
                                'marketingAirline'      => $flightSegment->MarketingAirline[0],
                                'operatingAirlineCode'  => $flightSegment->OperatingAirline->attributes()->Code[0],
                                'marketingAirlineCode'  => $flightSegment->MarketingAirline->attributes()->Code[0],
                                'departureAirportCode'  => $itinerary->AirItineraryPricingInfo->FareInfos->FareInfo[$fareInfoCount]->DepartureAirport->attributes()->LocationCode[0],
                                'arrivalAirportCode'    => $itinerary->AirItineraryPricingInfo->FareInfos->FareInfo[$fareInfoCount]->ArrivalAirport->attributes()->LocationCode[0],
                                'filingAirlineCode'    =>  $itinerary->AirItineraryPricingInfo->FareInfos->FareInfo[$fareInfoCount]->FilingAirline->attributes()->Code[0]
                            ];
                            $fareInfoCount = $fareInfoCount +1;
                            array_push($originDestinationInfo , $flightSegmentInfo);
                        }

                        $originDestinationPlacement = $originDestinationPlacement + 1;
                    }
                }
                else{
                    $originDestination = $originDestinations;
                    $originDestinationsCount = 1;
                    if(isset($originDestination->FlightSegment[0])){
                        $stops = count($originDestination->FlightSegment) - 1;
                        $cabinType = $originDestination->FlightSegment[0]->TPA_Extensions->CabinType->attributes()->Cabin[0];
                        $displayAirline = $itinerary->AirItineraryPricingInfo->FareInfos->FareInfo[0]->FilingAirline->attributes()->Code[0];
                        foreach($originDestination['FlightSegment'] as $j => $flightSegment){
                            $dateDiff = intval((strtotime($flightSegment['ArrivalDateTime'])-strtotime($flightSegment['DepartureDateTime']))/60);
                            $hours = intval($dateDiff/60);
                            $minutes = $dateDiff%60;


                            $flightSegmentInfo = [
                                'originDestinationPlacement'     =>  1,
                                'departureDateTime'     => $flightSegment['DepartureDateTime'],
                                'arrivalDateTime'       => $flightSegment['ArrivalDateTime'],
                                'flightNumber'          => $flightSegment['FlightNumber'],
                                'resBookDesigCode'      => $flightSegment['ResBookDesigCode'],
                                'departureAirportName'  => $flightSegment->DepartureAirport,
                                'arrivalAirportName'    => $flightSegment->ArrivalAirport,
                                'equipment'             => $flightSegment->Equipment[0],
                                'journeyDuration'       => $hours ." hr(s) ".$minutes." min(s)",
                                'journeyTotalDuration'  => $flightSegment->TPA_Extensions->JourneyTotalDuration,
                                'cabin'                 => $flightSegment->TPA_Extensions->CabinType->attributes()->Cabin[0],
                                'operatingAirlineName'  => $flightSegment->OperatingAirline[0],
                                'marketingAirline'      => $flightSegment->MarketingAirline[0],
                                'operatingAirlineCode'  => $flightSegment->OperatingAirline->attributes()->Code[0],
                                'marketingAirlineCode'  => $flightSegment->MarketingAirline->attributes()->Code[0],
                                'departureAirportCode'  => $itinerary->AirItineraryPricingInfo->FareInfos->FareInfo[$fareInfoCount]->DepartureAirport->attributes()->LocationCode[0],
                                'arrivalAirportCode'    => $itinerary->AirItineraryPricingInfo->FareInfos->FareInfo[$fareInfoCount]->ArrivalAirport->attributes()->LocationCode[0],
                                'filingAirlineCode'    =>  $itinerary->AirItineraryPricingInfo->FareInfos->FareInfo[$fareInfoCount]->FilingAirline->attributes()->Code[0]
                            ];
                            $fareInfoCount = $fareInfoCount +1;
                            array_push($originDestinationInfo , $flightSegmentInfo);
                        }
                    }
                    else{
                        $flightSegment = $originDestination->FlightSegment;
                        $stops = 0;
                        $cabinType = $originDestination->FlightSegment->TPA_Extensions->CabinType->attributes()->Cabin[0];
                        if(isset($itinerary->AirItineraryPricingInfo->FareInfos->FareInfo[0])){
                            $displayAirline = $itinerary->AirItineraryPricingInfo->FareInfos->FareInfo[0]->FilingAirline->attributes()->Code[0];
                        }else{
                            $displayAirline = $itinerary->AirItineraryPricingInfo->FareInfos->FareInfo->FilingAirline->attributes()->Code[0];;
                        }
                        $dateDiff = intval((strtotime($flightSegment['ArrivalDateTime'])-strtotime($flightSegment['DepartureDateTime']))/60);
                        $hours = intval($dateDiff/60);
                        $minutes = $dateDiff%60;

                        $flightSegmentInfo = [
                            'originDestinationPlacement'     =>  1,
                            'departureDateTime'     => $flightSegment['DepartureDateTime'],
                            'arrivalDateTime'       => $flightSegment['ArrivalDateTime'],
                            'flightNumber'          => $flightSegment['FlightNumber'],
                            'resBookDesigCode'      => $flightSegment['ResBookDesigCode'],
                            'departureAirportName'  => $flightSegment->DepartureAirport,
                            'arrivalAirportName'    => $flightSegment->ArrivalAirport,
                            'equipment'             => $flightSegment->Equipment[0],
                            'journeyDuration'       => $hours ." hr(s) ".$minutes." min(s)",
                            'journeyTotalDuration'  => $flightSegment->TPA_Extensions->JourneyTotalDuration,
                            'cabin'                 => $flightSegment->TPA_Extensions->CabinType->attributes()->Cabin[0],
                            'operatingAirlineName'  => $flightSegment->OperatingAirline[0],
                            'marketingAirline'      => $flightSegment->MarketingAirline[0],
                            'operatingAirlineCode'  => $flightSegment->OperatingAirline->attributes()->Code[0],
                            'marketingAirlineCode'  => $flightSegment->MarketingAirline->attributes()->Code[0],
                            'departureAirportCode'  => $itinerary->AirItineraryPricingInfo->FareInfos->FareInfo[$fareInfoCount]->DepartureAirport->attributes()->LocationCode[0],
                            'arrivalAirportCode'    => $itinerary->AirItineraryPricingInfo->FareInfos->FareInfo[$fareInfoCount]->ArrivalAirport->attributes()->LocationCode[0],
                            'filingAirlineCode'    =>  $itinerary->AirItineraryPricingInfo->FareInfos->FareInfo[$fareInfoCount]->FilingAirline->attributes()->Code[0]
                        ];
                        $fareInfoCount = $fareInfoCount +1;
                        array_push($originDestinationInfo , $flightSegmentInfo);
                    }
                }

                $fareBrakeDowns = $itinerary->AirItineraryPricingInfo->PTC_FareBreakdowns->PTC_FareBreakdown;
                if(isset($fareBrakeDowns[0])){
                    foreach($fareBrakeDowns as $serial => $brakeDown){
                        $bags = [];
                        if(isset($brakeDown->PassengerFare->FreeBagAllowance)){
                            $baggageAllowances = $brakeDown->PassengerFare->FreeBagAllowance;
                            if(isset($baggageAllowaces[0])){
                                foreach($baggageAllowances as $b => $baggageAllowance){
                                    $baggageAllowanceInfo = array_get($baggageAllowance, 'Weight',array_get($baggageAllowance[''],'Quantity',0));
                                    $baggageUnit          = array_get($baggageAllowance,'Unit',array_get($baggageAllowance[''],'Type',0));
                                    $bagArray = $baggageAllowanceInfo."-".$baggageUnit;
                                    array_push($bags, $bagArray);
                                }
                            }else{
                                $baggageAllowance = $baggageAllowances;
                                $baggageAllowanceInfo = array_get($baggageAllowance, 'Weight',array_get($baggageAllowance,'Quantity',0));
                                $baggageUnit          = array_get($baggageAllowance,'Unit',array_get($baggageAllowance,'Type',0));
                                $bagArray = $baggageAllowanceInfo."-".$baggageUnit;
                                array_push($bags, $bagArray);

                            }
                        }

                        $fareBrakeDownInfo = [
                            'passengerType' => $brakeDown->PassengerTypeQuantity->attributes()->Code[0],
                            'quantity'      => $brakeDown->PassengerTypeQuantity->attributes()->Quantity[0],
                            'price'         => $brakeDown->PassengerFare->TotalFare->attributes()->Amount[0],
                            'freeBagAllowance' => $bags,
                        ];
                        array_push($defaultFareInfo, $fareBrakeDownInfo);
                    }
                }
                else{
                    $brakeDown = $fareBrakeDowns;
                    $fareBrakeDownInfo = [
                        'passengerType' => $brakeDown->PassengerTypeQuantity->attributes()->Code[0],
                        'quantity'      => $brakeDown->PassengerTypeQuantity->attributes()->Quantity[0],
                        'price'         => $brakeDown->PassengerFare->TotalFare->attributes()->Amount[0],
                    ];
                    array_push($defaultFareInfo, $fareBrakeDownInfo);
                }

                $itineraryDefaultPrice = $itinerary->AirItineraryPricingInfo->ItinTotalFare->TotalFare->attributes()->Amount[0];
                $customerMarkup        = 0;
                $agentMarkup           = 0;
                $adminMarkup           = 0;
                $vat                   = 0;
                $airlineMarkdown       = 0;
                $customerTotal         = 0;
                $agentTotal            = 0;
                $adminTotal            = 0;
                $displayTotal          = 0;


                $agentMarkupInfo    = Markup::where('role_id', 2)->first();
                $customerMarkupInfo = Markup::where('role_id', 3)->first();
                $vatInfo            = Vat::where('id',1)->first();
                $markdownInfo       = Markdown::where('airline_code',$itinerary->AirItineraryPricingInfo->attributes()->ValidatingAirlineCode[0])->first();
                if(!is_null($markdownInfo)){
                    $airlineMarkdown = $this->priceTypeCalculator($markdownInfo->type,$markdownInfo->type,$itineraryDefaultPrice);
                }
                $agentMarkup    = $this->priceTypeCalculator($agentMarkupInfo->flight_markup_type,$agentMarkupInfo->flight_markup_type,$itineraryDefaultPrice);
                $customerMarkup = $this->priceTypeCalculator($customerMarkupInfo->flight_markup_type,$customerMarkupInfo->flight_markup_type,$itineraryDefaultPrice);
                $adminMarkup    = 0;
                $vat            = $this->priceTypeCalculator($vatInfo->flight_vat_type,$vatInfo->flight_vat_type,$itineraryDefaultPrice);

                $customerTotal = ($itineraryDefaultPrice + $customerMarkup + $vat) - $airlineMarkdown;
                $agentTotal    = ($itineraryDefaultPrice + $agentMarkup + $vat) - $airlineMarkdown;
                $adminTotal    = ($itineraryDefaultPrice + $adminMarkup + $vat) - $airlineMarkdown;

                if(auth()->guest()){
                    $displayTotal = $customerTotal;
                }else{
                    if(auth()->user()->hasRole('admin')){
                        $displayTotal = $adminTotal;
                    }elseif(auth()->user()->hasRole('agent')){
                        $displayTotal = $agentTotal;
                    }elseif(auth()->user()->hasRole('customer')){
                        $displayTotal = $customerTotal;
                    }
                }

                $itineraryInformation = [
                    'directionInd'             => $itinerary->AirItinerary->attributes()->DirectionInd[0],
                    'ticketTimeLimit'          => $itinerary->TicketingInfo->attributes()->TicketTimeLimit[0],
                    'pricingSource'            => $itinerary->AirItineraryPricingInfo->attributes()->PricingSource[0],
                    'validatingAirlineCode'    => $itinerary->AirItineraryPricingInfo->attributes()->ValidatingAirlineCode[0],
                    'defaultItineraryPrice'    => $itineraryDefaultPrice,
                    'originDestinationsCount'  => $originDestinationsCount,
                    'cabinType'                => $cabinType,
                    'stops'                    => $stops,
                    'displayAirline'           => $displayAirline,
                    'adminToCustomerMarkup'    => $customerMarkup,
                    'adminToAgentMarkup'       => $agentMarkup,
                    'adminToAdminMarkup'       => $adminMarkup,
                    'vat'                      => $vat,
                    'airlineMarkdown'          => $airlineMarkdown,
                    'customerTotal'            => $customerTotal,
                    'agentTotal'               => $agentTotal,
                    'adminTotal'               => $adminTotal,
                    'displayTotal'             => $displayTotal,
                    'itineraryPassengerInfo'   => $defaultFareInfo,
                    'originDestinations'       => $originDestinationInfo
                ];

                array_push($sortedResponse,$itineraryInformation);
            }
        }
        else{
            $itinerary = $itineraries;



        }

        return $sortedResponse;



    }



    public function lowFarePlusResponseAvailableAirline($sortedResponseArray){
        $airlines = [];
        foreach($sortedResponseArray as $serial => $response){
            array_push($airlines, $response['displayAirline']);
        }

       return array_values(array_unique($airlines));
    }

    public function lowFarePlusResponseAvailableCabin($sortedResponseArray){
        $cabins = [];
        foreach($sortedResponseArray as $serial => $response){
            array_push($cabins, $response['cabinType']);
        }

        return array_values(array_unique($cabins));
    }

    public function lowFarePlusResponseAvailableStops($sortedResponseArray){
        $stops = [];
        foreach($sortedResponseArray as $serial => $response){
            array_push($stops, $response['stops']);
        }

        return array_values(array_unique($stops));
    }

    public function lowFarePlusResponseAvailablePrice($sortedResponseArray){
        $prices = [];
        foreach($sortedResponseArray as $serial => $response){
            array_push($prices, (round($response['displayTotal']/100)));
        }

        return array_values(array_unique($prices));
    }

}