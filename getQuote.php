// getQuote.php
<?php
header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $carCategory = $_POST['carCategory'];
    $infoArray = json_decode($_POST['infoArray'], true);

    function getQuote($carCategory, $infoArray) {
        $xmlRequestEuro = "<?xml version=\"1.0\" encoding=\"UTF-8\"?>
            <message>
                <serviceRequest serviceCode=\"getQuote\">
                    <serviceParameters>
                        <reservation carCategory=\"$carCategory\" rateId=\"RATE_ID\">
                            <checkout stationID=\"$infoArray[pickupEuro]\" date=\"$infoArray[pickUpDateEuro]\" time=\"$infoArray[pickUpTimeEuro]\"/>
                            <checkin stationID=\"$infoArray[dropOffEuro]\" date=\"$infoArray[dropOffDateEuro]\" time=\"$infoArray[dropOffTimeEuro]\"/>
                        </reservation>
                        <driver countryOfResidence=\"AU\"/>
                    </serviceParameters>
                </serviceRequest>
            </message>";

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, 'https://applications-ptn.europcar.com/xrs/resxml');
        curl_setopt($ch, CURLOPT_POST, 1);
        $postFields = http_build_query([
            'XML-Request' => $xmlRequestEuro,
            'callerCode' => '1132097',
            'password' => '02092024',
        ]);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $postFields);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Content-Type: application/x-www-form-urlencoded',
            'Accept: text/xml'
        ]);

        $response = curl_exec($ch);
        curl_close($ch);
        return $response;
    }

    echo json_encode(getQuote($carCategory, $infoArray));
} else {
    echo json_encode(["error" => "Invalid request"]);
}
?>
