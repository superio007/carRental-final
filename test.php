<?php
header('Content-Type: application/json');

// Disable error reporting to prevent unintended output
error_reporting(0);

// Handle POST requests
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Get the raw input data
    $rawData = file_get_contents('php://input');

    // Decode the JSON payload
    $data = json_decode($rawData, true);

    // Check for errors in JSON decoding
    if (json_last_error() === JSON_ERROR_NONE) {
        // Extract variables from the payload
        $carCategory = $data['carCategory'] ?? '';
        $pickup = $data['pickup'] ?? [];
        $dropoff = $data['dropoff'] ?? [];
        $pickEuro = $pickup['euro'] ?? '';
        $dropEuro = $dropoff['euro'] ?? '';
        $pickHertz = $pickup['hertz'] ?? '';
        $dropHertz = $dropoff['hertz'] ?? '';

        // Function to send XML request and get a quote
        function getQuote($carCategory, $pickEuro, $dropEuro) {
            $xmlRequestEuro = "<?xml version=\"1.0\" encoding=\"UTF-8\"?>
                <message>
                    <serviceRequest serviceCode=\"getQuote\">
                        <serviceParameters>
                            <reservation carCategory=\"$carCategory\" rateId=\"RATE_ID\">
                                <checkout stationID=\"$pickEuro\" date=\"20241118\" time=\"0900\"/>
                                <checkin stationID=\"$dropEuro\" date=\"20241119\" time=\"0900\"/>
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
            $error = curl_error($ch);
            curl_close($ch);

            if ($error) {
                return ['error' => 'cURL Error: ' . $error];
            }

            return ['response' => $response];
        }

        // Call the function and get the response
        $response = getQuote($carCategory, $pickEuro, $dropEuro);

        // Parse XML response
        $rate = 0;
        $currency = 'USD';

        if (isset($response['response']) && !empty($response['response'])) {
            $xml = simplexml_load_string($response['response']);

            if ($xml !== false && isset($xml->serviceResponse->reservation->quote)) {
                $rate = (float) $xml->serviceResponse->reservation->quote['basePrice'];
                $currency = (string) $xml->serviceResponse->reservation->quote['currency'];
            }
        }

        // Prepare final response
        $finalResponse = [
            'carCategory' => $carCategory,
            'pickup' => $pickup,
            'dropoff' => $dropoff,
            'quote' => [
                'rate' => $rate,
                'currency' => $currency
            ]
        ];

        // Return the response as JSON
        echo json_encode($finalResponse);
    } else {
        // Respond with an error
        echo json_encode(['error' => 'Invalid JSON payload']);
    }
} else {
    // Respond with an error if not POST
    echo json_encode(['error' => 'Invalid request method']);
}
?>
