<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Document</title>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js" integrity="sha384-YvpcrYf0tY3lHB60NNkmXc5s9fDVZLESaAA55NDzOxhy9GkcIdslK1eN7N6jIeHz" crossorigin="anonymous"></script>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet" integrity="sha384-QWTKZyjpPEjISv5WaRU9OFeRpok6YctnYmDr5pNlyT2bRjXh0JMhjY6hW+ALEwIH" crossorigin="anonymous">
    <script src="https://kit.fontawesome.com/74e6741759.js" crossorigin="anonymous"></script>
    <link rel="stylesheet"
            href="https://code.jquery.com/ui/1.14.0/themes/base/jquery-ui.css">
    <script src="https://code.jquery.com/jquery-3.7.1.js"></script>
    <script src="https://code.jquery.com/ui/1.14.0/jquery-ui.js"></script>
    <link rel="stylesheet"
        href="//cdnjs.cloudflare.com/ajax/libs/timepicker/1.3.5/jquery.timepicker.min.css">
        <script src="//cdnjs.cloudflare.com/ajax/libs/timepicker/1.3.5/jquery.timepicker.min.js"></script>
    <link rel="stylesheet" href="style.css">
</head>
<body>
<?php
    require 'dbconn.php';
    $sql = "SELECT * FROM filter_locations";
    // $sql = "SELECT * FROM airport_list";
    $result = $conn->query($sql);
    function makeApiCall($vendorID, $pickup, $dropOff, $pickUpDateTime, $dropOffDateTime) {
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
    function euroCall($pickupEuro, $dropOffEuro, $pickUpDateTimeEuro, $dropOffDateTimeEuro) {
        $xmlRequestEuro = "<?xml version=\"1.0\" encoding=\"UTF-8\"?>
        <message>
        <serviceRequest serviceCode=\"getCarCategories\">
            <serviceParameters>
            <reservation>
                <checkout stationID=\"$pickupEuro\" date=\"$pickUpDateTimeEuro\"/>
                <checkin stationID=\"$dropOffEuro\" date=\"$dropOffDateTimeEuro\"/>
            </reservation>
            </serviceParameters>
        </serviceRequest>
        </message>
        ";
        $ch = curl_init();

        // Set cURL options
        curl_setopt($ch, CURLOPT_URL, 'https://applications-ptn.europcar.com/xrs/resxml');
        curl_setopt($ch, CURLOPT_POST, 1);

        // URL-encode the parameters (XML Request, callerCode, and password)
        $postFields = http_build_query([
        'XML-Request' => $xmlRequestEuro,
        'callerCode' => '1132097',
        'password' => '02092024',
        ]);

        // Set the POST fields and headers
        curl_setopt($ch, CURLOPT_POSTFIELDS, $postFields);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Content-Type: application/x-www-form-urlencoded',
        'Accept: text/xml'
        ]);

        // Execute the cURL request
        $response = curl_exec($ch);
        return $response;
    }

    if($_SERVER['REQUEST_METHOD']=="POST" && isset($_POST['search_btn'])){
        $pickup = $_POST['pickCityCode'];
        $dropOff = $_POST['dropCityCode'];
        $pickDate = $_POST['pickDate'];
        $pickTime = $_POST['pickTime'];
        $dropDate = $_POST['dropDate'];
        $dropTime = $_POST['dropTime'];
        // Euro details
        $pickupEuro = $_POST['pickCityCodeEuro'];
        $dropOffEuro = $_POST['dropCityCodeEuro'];
        
         // Function to convert 12-hour time to 24-hour format
        function convertTo24HourFormat($time12Hour) {
            $date = DateTime::createFromFormat('h:i A', $time12Hour);
            return $date->format('H:i:s');
        }
        function convertDateEuro($date_str) {
            // Convert the string date into a DateTime object using 'm/d/Y' format
            $date_obj = DateTime::createFromFormat('m/d/Y', $date_str);
            
            // Format the DateTime object into the desired format 'Ymd'
            if ($date_obj) {
                return $date_obj->format('Ymd');
            } else {
                return "Invalid date format!";
            }
        }
        // Function to convert date format
        function convertDateFormat($date, $fromFormat, $toFormat) {
            $dateTime = DateTime::createFromFormat($fromFormat, $date);
            return $dateTime->format($toFormat);
        }

        // Convert the date formats
        $fromFormat = 'm/d/Y'; // Original format
        $toFormat = 'Y-m-d';   // Desired format

        $convertedPickDate = convertDateFormat($pickDate, $fromFormat, $toFormat);
        $convertedDropDate = convertDateFormat($dropDate, $fromFormat, $toFormat);
        // Euro date format
        $pickUpDateTimeEuro = convertDateEuro($pickDate);
        $dropOffDateTimeEuro = convertDateEuro($dropDate);
        // Convert the time formats
        $formatPickTime = convertTo24HourFormat($pickTime);
        $formatDropTime = convertTo24HourFormat($dropTime);

        // Combine date and time for XML
        $pickUpDateTime = $convertedPickDate . 'T' . $formatPickTime;
        $dropOffDateTime = $convertedDropDate . 'T' . $formatDropTime;

        // Make the API calls for ZR and ZT
        $responseZE = makeApiCall('ZE', $pickup, $dropOff, $pickUpDateTime, $dropOffDateTime);
        $responseZR = makeApiCall('ZR', $pickup, $dropOff, $pickUpDateTime, $dropOffDateTime);
        $responseZT = makeApiCall('ZT', $pickup, $dropOff, $pickUpDateTime, $dropOffDateTime);
        $responseEuro = euroCall($pickupEuro, $dropOffEuro, $pickUpDateTimeEuro, $dropOffDateTimeEuro);
        // Combine the responses
        if (strlen($responseZE) > 500 ||strlen($responseZR) > 500 || strlen($responseZT) > 500 || strlen($responseEuro) > 500) {
            $_SESSION['responseZE'] = $responseZE;
            $_SESSION['responseZR'] = $responseZR;
            $_SESSION['responseZT'] = $responseZT;
            $_SESSION['responseEuro'] = $responseEuro;
            $dataarray = [
                'pickUpDateTime' => $pickUpDateTime,
                'dropOffDateTime' => $dropOffDateTime,
                'pickLocation' => $pickup,
                'dropLocation' => $dropOff,
            ];
            $requiredeuroBooking = [
                "pickup" => $pickupEuro,
                "dropOff" =>$dropOffEuro,
                "pickDate" => $pickUpDateTimeEuro,
                "dropDate" => $dropOffDateTimeEuro,
                "pickTime" => $pickTime,
                "dropTime" => $dropTime
            ];
            $_SESSION['requiredeuroBooking'] = $requiredeuroBooking;
            $_SESSION['dataarray'] = $dataarray;
            echo "<script>window.location.href='results.php'</script>";
        } else {
            echo "<script>alert('Vehicles are not available')</script>";
        }

    }
?>
<!-- for desktop -->
<div id="searchbox" class="d-none d-md-block bg-white p-3 rounded-3 mt-3">
        <form action="" method="post">
            <div class="d-flex justify-content-start">
                <h2>SEARCH FOR CAR PRICES</h2>
            </div>
            <div class="row">
                <div class="col-6 d-grid position-relative">
                    <label for="pick">PICK UP LOCATION:</label>
                    <input type="text" name="pick" id="pickInput" class="form-control" placeholder="Enter city or airport code" autocomplete="off">
                    <input type="hidden" name="pickCityCode" id="pickCityCode"> <!-- Hidden input to store pick-up city code -->
                    <input type="hidden" name="pickCityCodeEuro" id="pickCityCodeEuro"> <!-- Hidden input to store pick-up staion code -->
                    <!-- Suggestion box -->
                    <div id="suggestionBox" class="suggestion-box bg-white border rounded position-absolute" style="display:none;"></div>
                </div>
                <div class="col-6 d-grid position-relative">
                    <label for="drop">DROP OFF LOCATION:</label>
                    <input type="text" name="drop" id="dropInput" class="form-control" placeholder="Enter city or airport code" autocomplete="off">
                    <input type="hidden" name="dropCityCode" id="dropCityCode"> <!-- Hidden input to store drop-off city code -->
                    <input type="hidden" name="dropCityCodeEuro" id="dropCityCodeEuro"> <!-- Hidden input to store pick-up Station code -->
                    <!-- Suggestion box for drop-off locations -->
                    <div id="dropSuggestionBox" class="suggestion-box bg-white border rounded position-absolute" style="display:none;"></div>
                </div>
            </div>
            <div class="row">
                <div class="col-6 d-flex justify-content-between gap-3 mt-3">
                    <div class="w-50 d-grid outer_div">
                        <label for="pickDate">PICK UP DATE:</label>
                        <input type="text" class="input_div" name="pickDate" id="pickDate">
                    </div>
                    <div class="w-50 d-grid outer_div">
                        <label for="pickTime">PICK UP TIME:</label>
                        <input type="text" class="input_div" name="pickTime" id="pickTime">
                    </div>
                </div>
                <div class="col-6 d-flex justify-content-between gap-3 mt-3">
                    <div class="w-50 d-grid outer_div">
                        <label for="dropDate">DROP OFF DATE:</label>
                        <input type="text" class="input_div" name="dropDate" id="dropDate">
                    </div>
                    <div class="w-50 d-grid outer_div">
                        <label for="dropTime">DROP OFF TIME:</label>
                        <input type="text" class="input_div" name="dropTime" id="dropTime">
                    </div>
                </div>
            </div>
            <div class="row mt-3">
                <div class="d-flex justify-content-end">
                    <button type="submit" name="search_btn" class="btn btn-primary rounded-2">SEARCH <i class="fa-solid fa-arrow-right fa-sm" style="color: #ffffff;"></i></button>
                </div>
            </div>
        </form>
    </div>
    <!-- for mobile -->
    <div class="d-md-none bg-white p-3 rounded-3 mt-3">
        <form action="" method="post">
            <div class="d-flex justify-content-start mb-3">
                <h2>SEARCH FOR CAR PRICES</h2>
            </div>
            <div class="row">
                <div class="col-md-6 mb-3">
                    <label for="mobile_pick">Pick Up Location:</label>
                    <input type="text" name="pick" id="mobile_pick" list="airport_name" placeholder="CITY OR AIRPORT CODE" autocomplete="off" class="form-control">
                    <input type="hidden" name="pickCityCode" id="mobile_pickCityCode">
                    <input type="hidden" name="pickCityCodeEuro" id="mobile_pickCityCodeEuro">
                    <div id="suggestionBox-mobile" class="suggestionBox-mobile bg-white border rounded position-absolute" style="display:none;"></div>
                </div>
                <div class="col-md-6 mb-3">
                    <label for="mobile_drop">Drop Off Location:</label>
                    <input type="text" name="drop" id="mobile_drop" list="airport_name" placeholder="CITY OR AIRPORT CODE" autocomplete="off" class="form-control">
                    <input type="hidden" name="dropCityCode" id="mobile_dropCityCode">
                    <input type="hidden" name="dropCityCodeEuro" id="mobile_dropCityCodeEuro">
                    <div id="drop-suggestionBox-mobile" class="drop-suggestionBox-mobile bg-white border rounded position-absolute" style="display:none;"></div>
                </div>
            </div>
            <div class="row">
                <div class="col-md-6 mb-3">
                    <label for="mobile_pickDate">Pick Up Date:</label>
                    <input type="text" name="pickDate" id="mobile_pickDate" class="form-control">
                </div>
                <div class="col-md-6 mb-3">
                    <label for="mobile_pickTime">Pick Up Time:</label>
                    <input type="text" name="pickTime" id="mobile_pickTime" class="form-control">
                </div>
            </div>
            <div class="row">
                <div class="col-md-6 mb-3">
                    <label for="mobile_dropDate">Drop Off Date:</label>
                    <input type="text" name="dropDate" id="mobile_dropDate" class="form-control">
                </div>
                <div class="col-md-6 mb-3">
                    <label for="mobile_dropTime">Drop Off Time:</label>
                    <input type="text" name="dropTime" id="mobile_dropTime" class="form-control">
                </div>
            </div>
            <div>
                <button type="submit" name="search_btn" class="btn btn-primary rounded-2">SEARCH <i class="fa-solid fa-arrow-right fa-sm" style="color: #ffffff;"></i></button>
            </div>
        </form>
    </div>
<script>
    // Function to handle the autocomplete logic
    $(document).ready(function() {
        // Event listener for input on pick-up location input field
        $('#pickInput').on('input', function() {
            var inputValue = $(this).val().trim();

            if (inputValue.length >= 3) {
                $.ajax({
                    url: 'getStations.php',
                    method: 'POST',
                    data: { searchTerm: inputValue },
                    success: function(response) {
                        var stations = JSON.parse(response);
                        
                        $('#suggestionBox').empty().show();

                        if (stations.message) {
                            $('#suggestionBox').append(`
                                <div class="no-data-message" style="padding: 10px; color: red;">
                                    ${stations.message}
                                </div>
                            `);
                        } else {
                            // Group stations by city and filter to include only airport and an "All Locations" option
                            var groupedStations = stations.reduce(function(grouped, station) {
                                var city = station.city;
                                if (!grouped[city]) {
                                    grouped[city] = { airport: null, allLocations: null };
                                }

                                // Only add entries if both "airport" and "allLocations" are not yet set
                                if (!grouped[city].airport && station.stationName.toLowerCase().includes('airport')) {
                                    grouped[city].airport = station;
                                } else if (!grouped[city].allLocations) {
                                    grouped[city].allLocations = { ...station, stationName: "All Locations" };
                                }

                                // If both entries are found, skip further additions for this city
                                if (grouped[city].airport && grouped[city].allLocations) {
                                    return grouped;
                                }

                                return grouped;
                            }, {});

                            // Render only the airport and "All Locations" items
                            for (const city in groupedStations) {
                                const cityStations = groupedStations[city];
                                // console.log(cityStations);
                                $('#suggestionBox').append(`
                                    <div class="city-header" style="font-weight: bold; padding: 10px 5px; background-color: #f5f5f5;">
                                        ${city} (2 Matches)
                                    </div>
                                `);

                                if (cityStations.airport) {
                                    $('#suggestionBox').append(`
                                        <div class="suggestion-item" data-code="${cityStations.airport.stationCode}" data-station="${cityStations.airport.stationCodeEuro}">
                                            <i class="fas fa-plane-departure"></i> ${cityStations.airport.stationName} (Airport)
                                        </div>
                                    `);
                                }

                                if (cityStations.allLocations) {
                                    $('#suggestionBox').append(`
                                        <div class="suggestion-item" data-code="${cityStations.allLocations.stationCode}" data-station="${cityStations.allLocations.stationCodeEuro}">
                                            <i class="fas fa-map-marker-alt"></i>${city} ${cityStations.allLocations.stationName}
                                        </div>
                                    `);
                                }
                                // Break out of the loop after adding one city
                                break;
                            }

                            // Handle click on suggestion items
                            $('.suggestion-item').on('click', function() {
                                var stationName = $(this).text().trim();
                                var stationCode = $(this).data('code');
                                var stationCodeEuro = $(this).data('station');

                                $('#pickInput').val(stationName);
                                $('#pickCityCode').val(stationCode);
                                $('#pickCityCodeEuro').val(stationCodeEuro);
                                $('#dropInput').val(stationName);
                                $('#dropCityCode').val(stationCode);
                                $('#dropCityCodeEuro').val(stationCodeEuro);
                                $('#suggestionBox').hide();
                            });
                        }
                    },
                    error: function(jqXHR, textStatus, errorThrown) {
                        console.error('Error during AJAX request:', textStatus, errorThrown);
                    }
                });
            } else {
                $('#suggestionBox').hide();
            }
        });
        // Event listener for input on pick-up location input field for mobile
        $('#mobile_pick').on('input', function() {
            var inputValue = $(this).val().trim();

            if (inputValue.length >= 3) {
                $.ajax({
                    url: 'getStations.php',
                    method: 'POST',
                    data: { searchTerm: inputValue },
                    success: function(response) {
                        var stations = JSON.parse(response);

                        // Clear the mobile suggestion box
                        $('#suggestionBox-mobile').empty().show();

                        if (stations.message) {
                            $('#suggestionBox-mobile').append(`
                                <div class="no-data-message" style="padding: 10px; color: red;">
                                    ${stations.message}
                                </div>
                            `);
                        } else {
                            // Group stations by city and filter to include only airport and an "All Locations" option
                            var groupedStations = stations.reduce(function(grouped, station) {
                                var city = station.city;
                                if (!grouped[city]) {
                                    grouped[city] = { airport: null, allLocations: null };
                                }

                                // Only add entries if both "airport" and "allLocations" are not yet set
                                if (!grouped[city].airport && station.stationName.toLowerCase().includes('airport')) {
                                    grouped[city].airport = station;
                                } else if (!grouped[city].allLocations) {
                                    grouped[city].allLocations = { ...station, stationName: "All Locations" };
                                }

                                // If both entries are found, skip further additions for this city
                                if (grouped[city].airport && grouped[city].allLocations) {
                                    return grouped;
                                }

                                return grouped;
                            }, {});

                            // Render only the airport and "All Locations" items
                            for (const city in groupedStations) {
                                const cityStations = groupedStations[city];

                                $('#suggestionBox-mobile').append(`
                                    <div class="city-header" style="font-weight: bold; padding: 10px 5px; background-color: #f5f5f5;">
                                        ${city} (2 Matches)
                                    </div>
                                `);

                                if (cityStations.airport) {
                                    $('#suggestionBox-mobile').append(`
                                        <div class="suggestion-item-mobile" data-code="${cityStations.airport.stationCode}" data-station="${cityStations.airport.stationCodeEuro}">
                                            <i class="fas fa-plane-departure"></i> ${cityStations.airport.stationName} (Airport)
                                        </div>
                                    `);
                                }

                                if (cityStations.allLocations) {
                                    $('#suggestionBox-mobile').append(`
                                        <div class="suggestion-item-mobile" data-code="${cityStations.allLocations.stationCode}" data-station="${cityStations.allLocations.stationCodeEuro}">
                                            <i class="fas fa-map-marker-alt"></i> ${city} ${cityStations.allLocations.stationName}
                                        </div>
                                    `);
                                }
                                // Break out of the loop after adding one city
                                break;
                            }

                            // Handle click on mobile suggestion items
                            $('.suggestion-item-mobile').on('click', function() {
                                var stationName = $(this).text().trim();
                                var stationCode = $(this).data('code');
                                var stationCodeEuro = $(this).data('station');
                                $('#mobile_pick').val(stationName);
                                $('#mobile_pickCityCode').val(stationCode);
                                $('#mobile_pickCityCodeEuro').val(stationCodeEuro);
                                $('#mobile_drop').val(stationName);
                                $('#mobile_dropCityCode').val(stationCode);
                                $('#mobile_dropCityCodeEuro').val(stationCodeEuro);
                                $('#suggestionBox-mobile').hide();
                            });
                        }
                    },
                    error: function(jqXHR, textStatus, errorThrown) {
                        console.error('Error during AJAX request:', textStatus, errorThrown);
                    }
                });
            } else {
                $('#suggestionBox-mobile').hide();
            }
        });
        // Event listener for drop-off location
        $('#dropInput').on('input', function() {
            var inputValue = $(this).val().trim();

            if (inputValue.length >= 3) {
                $.ajax({
                    url: 'getStations.php',
                    method: 'POST',
                    data: { searchTerm: inputValue },
                    success: function(response) {
                        var stations = JSON.parse(response);

                        $('#dropSuggestionBox').empty().show();

                        if (stations.message) {
                            $('#dropSuggestionBox').append(`
                                <div class="no-data-message" style="padding: 10px; color: red;">
                                    ${stations.message}
                                </div>
                            `);
                        } else {
                            // Group stations by city and filter to include only airport and an "All Locations" option
                            var groupedStations = stations.reduce(function(grouped, station) {
                                var city = station.city;
                                if (!grouped[city]) {
                                    grouped[city] = { airport: null, allLocations: null };
                                }

                                // Only add entries if both "airport" and "allLocations" are not yet set
                                if (!grouped[city].airport && station.stationName.toLowerCase().includes('airport')) {
                                    grouped[city].airport = station;
                                } else if (!grouped[city].allLocations) {
                                    grouped[city].allLocations = { ...station, stationName: "All Locations" };
                                }

                                // If both entries are found, skip further additions for this city
                                if (grouped[city].airport && grouped[city].allLocations) {
                                    return grouped;
                                }

                                return grouped;
                            }, {});

                            // Render only the airport and "All Locations" items
                            for (const city in groupedStations) {
                                const cityStations = groupedStations[city];

                                $('#dropSuggestionBox').append(`
                                    <div class="city-header" style="font-weight: bold; padding: 10px 5px; background-color: #f5f5f5;">
                                        ${city} (2 Matches)
                                    </div>
                                `);

                                if (cityStations.airport) {
                                    $('#dropSuggestionBox').append(`
                                        <div class="suggestion-item" data-code="${cityStations.airport.stationCode}" data-station="${cityStations.airport.stationCodeEuro}">
                                            <i class="fas fa-plane-departure"></i> ${cityStations.airport.stationName} (Airport)
                                        </div>
                                    `);
                                }

                                if (cityStations.allLocations) {
                                    $('#dropSuggestionBox').append(`
                                        <div class="suggestion-item" data-code="${cityStations.allLocations.stationCode}" data-station="${cityStations.allLocations.stationCodeEuro}">
                                            <i class="fas fa-map-marker-alt"></i>${city} ${cityStations.allLocations.stationName}
                                        </div>
                                    `);
                                }
                                // Break out of the loop after adding one city
                                break;
                            }

                            // Handle click on suggestion items
                            $('.suggestion-item').on('click', function() {
                                var stationName = $(this).text().trim();
                                var stationCode = $(this).data('code');
                                var stationCodeEuro = $(this).data('station');

                                $('#dropInput').val(stationName);
                                $('#dropCityCode').val(stationCode);
                                $('#dropCityCodeEuro').val(stationCodeEuro);
                                $('#dropSuggestionBox').hide();
                            });
                        }
                    },
                    error: function(jqXHR, textStatus, errorThrown) {
                        console.error('Error during AJAX request:', textStatus, errorThrown);
                    }
                });
            } else {
                $('#dropSuggestionBox').hide();
            }
        });
        // Similar logic for mobile drop-off location
        $('#mobile_drop').on('input', function() {
            var inputValue = $(this).val().trim();

            if (inputValue.length >= 3) {
                $.ajax({
                    url: 'getStations.php',
                    method: 'POST',
                    data: { searchTerm: inputValue },
                    success: function(response) {
                        var stations = JSON.parse(response);

                        $('#drop-suggestionBox-mobile').empty().show();

                        if (stations.message) {
                            $('#drop-suggestionBox-mobile').append(`
                                <div class="no-data-message" style="padding: 10px; color: red;">
                                    ${stations.message}
                                </div>
                            `);
                        } else {
                            // Group stations by city and filter to include only airport and an "All Locations" option
                            var groupedStations = stations.reduce(function(grouped, station) {
                                var city = station.city;
                                if (!grouped[city]) {
                                    grouped[city] = { airport: null, allLocations: null };
                                }

                                // Only add entries if both "airport" and "allLocations" are not yet set
                                if (!grouped[city].airport && station.stationName.toLowerCase().includes('airport')) {
                                    grouped[city].airport = station;
                                } else if (!grouped[city].allLocations) {
                                    grouped[city].allLocations = { ...station, stationName: "All Locations" };
                                }

                                // If both entries are found, skip further additions for this city
                                if (grouped[city].airport && grouped[city].allLocations) {
                                    return grouped;
                                }

                                return grouped;
                            }, {});

                            // Render only the airport and "All Locations" items
                            for (const city in groupedStations) {
                                const cityStations = groupedStations[city];

                                $('#drop-suggestionBox-mobile').append(`
                                    <div class="city-header" style="font-weight: bold; padding: 10px 5px; background-color: #f5f5f5;">
                                        ${city} (2 Matches)
                                    </div>
                                `);

                                if (cityStations.airport) {
                                    $('#drop-suggestionBox-mobile').append(`
                                        <div class="drop-suggestion-item-mobile" data-code="${cityStations.airport.stationCode}" data-station="${cityStations.airport.stationCodeEuro}">
                                            <i class="fas fa-plane-departure"></i> ${cityStations.airport.stationName} (Airport)
                                        </div>
                                    `);
                                }

                                if (cityStations.allLocations) {
                                    $('#drop-suggestionBox-mobile').append(`
                                        <div class="drop-suggestion-item-mobile" data-code="${cityStations.allLocations.stationCode}" data-station="${cityStations.allLocations.stationCodeEuro}">
                                            <i class="fas fa-map-marker-alt"></i> ${city} ${cityStations.allLocations.stationName}
                                        </div>
                                    `);
                                }
                                // Break out of the loop after adding one city
                                break;
                            }

                            // Handle click on suggestion items
                            $('.drop-suggestion-item-mobile').on('click', function() {
                                var stationName = $(this).text().trim();
                                var stationCode = $(this).data('code');
                                var stationCodeEuro = $(this).data('station');
                                $('#mobile_drop').val(stationName);
                                $('#mobile_dropCityCode').val(stationCode);
                                $('#mobile_dropCityCodeEuro').val(stationCodeEuro);
                                $('#drop-suggestionBox-mobile').hide();
                            });
                        }
                    },
                    error: function(jqXHR, textStatus, errorThrown) {
                        console.error('Error during AJAX request:', textStatus, errorThrown);
                    }
                });
            } else {
                $('#drop-suggestionBox-mobile').hide();
            }
        });
    });

    $( function() {
    $( "#pickDate" ).datepicker();
    } );
    $(document).ready(function(){
        $('#pickTime').timepicker({});
    });
    $( function() {
    $( "#dropDate" ).datepicker();
    } );
    $(document).ready(function(){
        $('#dropTime').timepicker({});
    });
    // mobile
    $( function() {
    $( "#mobile_pickDate" ).datepicker();
    } );
    $(document).ready(function(){
        $('#mobile_pickTime').timepicker({});
    });
    $( function() {
    $( "#mobile_dropDate" ).datepicker();
    } );
    $(document).ready(function(){
        $('#mobile_dropTime').timepicker({});
    });
</script>
</body>
</html>