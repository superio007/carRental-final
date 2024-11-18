<?php
  function makeApiCall($vendorID, $pickup, $dropOff, $pickUpDateTime, $dropOffDateTime)
  {
    $xmlRequest = <<<XML
    <?xml version="1.0" encoding="UTF-8" ?>
    <OTA_VehAvailRateRQ xmlns="http://www.opentravel.org/OTA/2003/05" Version="1.008">
        <POS>
            <Source ISOCountry="AU" AgentDutyCode="T17R16L5D11">
                <RequestorID Type="4" ID="X975">
                    <CompanyName Code="CP" CodeContext="4PH5"></CompanyName>
                </RequestorID>
            </Source>
            <Source>
                <RequestorID Type="8" ID="$vendorID"/>
            </Source>
        </POS>
        <VehAvailRQCore Status="Available">
            <VehRentalCore PickUpDateTime="$pickUpDateTime" ReturnDateTime="$dropOffDateTime">
                <PickUpLocation LocationCode="$pickup" CodeContext="IATA"/>
                <ReturnLocation LocationCode="$dropOff" CodeContext="IATA"/>
            </VehRentalCore>
        </VehAvailRQCore>
    </OTA_VehAvailRateRQ>
    XML;

    $apiUrl = 'https://vv.xqual.hertz.com/DirectLinkWEB/handlers/DirectLinkHandler?id=ota2007a';
    $ch = curl_init($apiUrl);

    // Set cURL options
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
      'Content-Type: application/xml',
      'Content-Length: ' . strlen($xmlRequest)
    ]);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $xmlRequest);

    // Execute the cURL request and capture the response
    $response = curl_exec($ch);

    // Check for errors
    if (curl_errno($ch)) {
      echo 'cURL Error: ' . curl_error($ch);
    }

    curl_close($ch);

    return $response;
  }
  $pickup = "MELE";
  $dropOff = "MELE";
  $pickUpDateTime = "2024-11-19T10:00:00-06:00";
  $dropOffDateTime = "2024-11-20T10:00:00-06:00";
  $responseZE = makeApiCall('ZE', $pickup, $dropOff, $pickUpDateTime, $dropOffDateTime);
  var_dump($responseZE);
?>