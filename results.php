<?php
session_start();
// ini_set('display_errors', 1);
// ini_set('display_startup_errors', 1);
// error_reporting(E_ALL);
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
    <link rel="stylesheet" href="style.css">
</head>

<body>
    <?php
    if(!isset($_SESSION['jwtToken'])){
        echo "<script>window.location.href='login.php';</script>";
    }
    $responseZE = $_SESSION['responseZE'];
    // var_dump($responseZE);
    $responseZT = $_SESSION['responseZT'];
    // var_dump($responseZT);
    $responseZR = $_SESSION['responseZR'];
    // var_dump($responseZR);
    if (!empty($_SESSION['responseEuro'])) {
        $xmlresEuro = new SimpleXMLElement($_SESSION['responseEuro']);
        // echo "<pre>";
        // var_dump($xmlresEuro); // Display the XML structure
        // echo "</pre>";
    } else {
        echo "No XML response available in session.";
    }
    $dataArray = $_SESSION['dataarray'];
    var_dump($dataArray);
    require "dbconn.php";
    $pickUp = $dataArray['pickLocation'] ?? '';
    $drop = $dataArray['dropLocation'] ?? '';
    $sql = $conn->prepare("SELECT * FROM `airport_list` WHERE citycode IN ( ? , ?)");
    $sql->bind_param("ss", $pickUp, $drop);
    $sql->execute();
    $result = $sql->get_result();
    $pickupDetails = '';
    $dropoffDetails = '';

    // Loop through the results to assign pickup and drop-off airport names and cities
    while ($row = $result->fetch_assoc()) {
        if ($row['citycode'] === $pickUp) {
            $pickupDetails = $row['city'] . ' ' . $row['airpotname'];
        }
        if ($row['citycode'] === $drop) {
            $dropoffDetails = $row['city'] . ' ' . $row['airpotname'];
        }
    }
    // $conn->close();
    function formatDateAndTime($dateTimeString) {
        // Convert the date-time string to a DateTime object
        $dateTime = new DateTime($dateTimeString);
        
        // Format the date as YYYYMMDD
        $formattedDate = $dateTime->format('Ymd');
        
        // Format the time as HHMM (24-hour format)
        $formattedTime = $dateTime->format('Hi');
        
        // Return both values as an array
        return [$formattedDate, $formattedTime];
    }
    $infoArray = [
        'pickupEuro' => $dataArray['pickLocation'],
        'dropOffEuro' => $dataArray['dropLocation'],
        'pickUpDateEuro' => formatDateAndTime($dataArray['pickUpDateTime'])[0],
        'pickUpTimeEuro' => formatDateAndTime($dataArray['pickUpDateTime'])[1],
        'dropOffDateEuro' => formatDateAndTime($dataArray['dropOffDateTime'])[0],    
        'dropOffTimeEuro' => formatDateAndTime($dataArray['dropOffDateTime'])[1],
    ];
    function getQuote($carCategory , $infoArray){
        // Build XML request
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

        // Initialize cURL
        $ch = curl_init();

        // Set cURL options
        curl_setopt($ch, CURLOPT_URL, 'https://applications-ptn.europcar.com/xrs/resxml');
        curl_setopt($ch, CURLOPT_POST, 1);

        // URL-encode the parameters (XML Request, callerCode, and password)
        $postFields = http_build_query([
        'XML-Request' => $xmlRequestEuro,
        'callerCode' => '1132097', // Replace with your actual caller code
        'password' => '02092024', // Replace with your actual password
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

        // Close cURL session
        curl_close($ch);

        // Return or process the response
        return $response;
    }
    // Date formatting function
    function formatDate($dateString)
    {
        $date = new DateTime($dateString);
        return $date->format('D, d M, Y \a\t H:i');
    }

    $pickDate = formatDate($dataArray['pickUpDateTime'] ?? '');
    $dropDate = formatDate($dataArray['dropOffDateTime'] ?? '');

    $xmlresZE = new SimpleXMLElement($responseZE);
    $xmlresZR = new SimpleXMLElement($responseZR);
    $xmlresZT = new SimpleXMLElement($responseZT);
    $categories = [
        'Economy' => [3],
        'Compact' => [4],
        'Midsize' => [6],
        // 'LargeSize' => ['FDAR'],
        'LuxurySportsCar' => [37],
        'SUV' => [4],
        'StationWagon' => [8], // Not in the XML
        'VanPeopleCarrier' => [2], // Not in the XML
        '7-12PassengerVans' => [2] // Not in the XML
    ];
    $categoriesEuro = [
        'Economy' => ['CDAR','XZAR'], // Replace with actual car codes for Economy
        'Compact' => ['CFAR', 'DFAR', 'CDAR','XZAR'],
        'Midsize' => ['IDAR','ICAE','ICAR','IDAE','IFAR','XZAR'],
        'Luxury/Sports Car' => ['JDAR', 'LDAR', 'DFFR', 'SFGV','FDFE','LFAE','PZAR'],
        'SUV' => ['SFAR', 'JFAR','SFAH','SFBD','SFBR','SFDR','GFAR','FFAR','UFAD','XZAR'],
        'Station Wagon' => ['FWAR','GWAR','FWAR','XZAR'],
        'Van/People Carrier' => ['PVAR','PVAV','KMLW','KPLW','XZAR'],
        '7-12 Passenger Vans' => ['UFAD','XZAR'] // Replace with actual codes if necessary
    ];
    //function to display Euro Cars
    function extractVehicleDetailsEuro($xmlResponse, $categoriesEuro)
    {
        $vehicleDetails = [];

        if (isset($xmlResponse->serviceResponse->carCategoryList->carCategory)) {
            // Iterate over the carCategory elements
            foreach ($xmlResponse->serviceResponse->carCategoryList->carCategory as $vehicle) {
                // Extract relevant vehicle details
                $code = (string)$vehicle['carCategoryCode']; // Car category code
                $name = (string)$vehicle['carCategoryName']; // Car category name
                $seats = (int)$vehicle['carCategorySeats'];  // Number of seats
                $baggage = (int)$vehicle['carCategoryBaggageQuantity']; // Baggage capacity
                $rate = (float)$vehicle['carCategoryPowerHP']; // Example: power as a stand-in for rate (replace with actual rate data if available)
                $currency = "USD"; // Placeholder, as currency is not in the response (modify accordingly)
                $co2 = (int)$vehicle['carCategoryCO2Quantity']; // CO2 emissions
                $fuelType = (string)$vehicle['fuelTypeCode']; // Fuel type

                // Match vehicles based on the category provided in $categories (for example, carCategoryCode)
                foreach ($categoriesEuro as $category => $codes) {
                    if (in_array($code, $codes)) {
                        // Add vehicle details to the result array
                        $vehicleDetails[$category][] = [
                            'name' => $name,
                            'code' => $code,
                            'seats' => $seats,
                            'baggage' => $baggage,
                            'rate' => $rate, // Replace this with actual rate data if available
                            'currency' => $currency,
                            'co2' => $co2,
                            'fuelType' => $fuelType,
                        ];
                    }
                }
            }
        }

        return $vehicleDetails;
    }
    //function to display Hertz,doller and thrifty Cars
    function extractVehicleDetails($xmlResponse, $categories)
    {
        $vehicleDetails = [];

        if (isset($xmlResponse->VehAvailRSCore->VehVendorAvails->VehVendorAvail->VehAvails->VehAvail)) {
            foreach ($xmlResponse->VehAvailRSCore->VehVendorAvails->VehVendorAvail->VehAvails->VehAvail as $vehicle) {
                $size = (int)$vehicle->VehAvailCore->Vehicle->VehClass['Size']; // Fetch the vehicle class size
                $rate = (float)$vehicle->VehAvailCore->TotalCharge['RateTotalAmount'];
                $currency = (string)$vehicle->VehAvailCore->TotalCharge['CurrencyCode'];
                $name = (string)$vehicle->VehAvailCore->Vehicle->VehMakeModel['Name'];
                $code = (string)$vehicle['Code'];


                // Loop through the categories and match based on size
                foreach ($categories as $category => $sizes) {
                    if (in_array($size, $sizes)) {
                        $vehicleDetails[$category][] = [
                            'name' => $name,
                            'size' => $size,
                            'rate' => $rate,
                            'currency' => $currency,
                        ];
                    }
                }
            }
        }

        return $vehicleDetails;
    }
    $sql = "SELECT MarkupPrice FROM `markup_price`";
    $result = $conn->query($sql);

    if ($result->num_rows > 0) {
        // output data of each row
        while ($row = $result->fetch_assoc()) {
            $markUp = $row['MarkupPrice'];
        }
    } else {
        echo "0 results";
    }
    // echo $markUp;
    $conn->close();
    $vehicleDetailsEuro = extractVehicleDetailsEuro($xmlresEuro, $categoriesEuro);
    $vehicleDetailsZE = extractVehicleDetails($xmlresZE, $categories);
    $vehicleDetailsZT = extractVehicleDetails($xmlresZT, $categories);
    $vehicleDetailsZR = extractVehicleDetails($xmlresZR, $categories);
    // echo "<pre>";
    // print_r($xmlresZE);
    // echo "</pre>";
    function calculatePercentage($part, $total)
    {
        $og = $total;
        if ($total == 0) {
            return "Total cannot be zero"; // To avoid division by zero error
        }
        $percentage = ($total * $part) / 100;
        return $percentage + $og;
    }
    //To filter Euro car's based on filters 
    function filterVehicles($xmlresEuro, $transmission = '', $doors = '', $fuelTypes = []) {
        $vehicleDetails = []; // Array to store filtered vehicles
    
        // Loop through the car categories provided in the XML
        foreach ($xmlresEuro->serviceResponse->carCategoryList->carCategory as $vehicle) {
            $matches = true; // Flag to track if the vehicle matches all conditions
    
            // Filter by transmission (automatic or manual)
            if ($transmission === 'Automatic' && (string)$vehicle['carCategoryAutomatic'] !== "Y") {
                $matches = false;
            } elseif ($transmission === 'Manual' && (string)$vehicle['carCategoryAutomatic'] !== "N") {
                $matches = false;
            }
    
            if ($doors === '4+') {
                // Check if the vehicle has 4 or more doors
                if ((int)$vehicle['carCategoryDoors'] < 4) {
                    $matches = false;
                }
            } elseif ($doors && (string)$vehicle['carCategoryDoors'] !== $doors) {
                $matches = false;
            }
    
            // Filter by fuel type
            if (!empty($fuelTypes) && !in_array((string)$vehicle['carCategoryType'], $fuelTypes)) {
                $matches = false;
            }
    
            // If the vehicle matches all the filters, add it to the result array
            if ($matches) {
                $vehicleDetails[] = $vehicle;
            }
        }
    
        return $vehicleDetails; // Return the filtered vehicle details
    }
    if ($_SERVER["REQUEST_METHOD"] == "GET" && isset($_GET['search'])) {
        // Retrieve form data
        $transmission = isset($_GET['transmission']) ? $_GET['transmission'] : '';
        $fuelTypes = isset($_GET['fuelTypes']) ? $_GET['fuelTypes'] : [];
        $mileage = isset($_GET['mileage']) ? $_GET['mileage'] : '';
        $doors = isset($_GET['doors']) ? $_GET['doors'] : '';

        if (isset($transmission)) {
            // Function to filter XML based on the specified criteria
            $filteredVehicles = filterVehicles($xmlresEuro, $transmission, $doors, $fuelTypes); // Filter vehicles
    
            // Create the exact XML structure
            $messageXml = new SimpleXMLElement('<message></message>');
            $serviceResponse = $messageXml->addChild('serviceResponse');
            $carCategoryList = $serviceResponse->addChild('carCategoryList');
    
            foreach ($filteredVehicles as $vehicle) {
                // Clone the vehicle's structure and attributes from the original XML
                $carCategory = $carCategoryList->addChild('carCategory');
                foreach ($vehicle->attributes() as $key => $value) {
                    $carCategory->addAttribute($key, $value);
                }
            }
    
            // Output the filtered XML (keeping the original structure intact)
            // header('Content-Type: text/xml'); // Ensure correct content type is set
            // echo "<pre>";
            // var_dump($messageXml); // Display the XML structure
            // echo "</pre>";
            $xmlresEuro = $messageXml;
           
            function filter($filterContent, $transmission = '', $doors = '', $mileage = '', $fuelTypes = [])
            {
                $vehicleDetails = [];
                if (isset($filterContent->VehAvailRSCore->VehVendorAvails->VehVendorAvail->VehAvails->VehAvail)) {
                    foreach ($filterContent->VehAvailRSCore->VehVendorAvails->VehVendorAvail->VehAvails->VehAvail as $VehAvail) {
                        $Vehicle = $VehAvail->VehAvailCore->Vehicle;

                        // Check transmission if specified
                        $transmissionMatch = $transmission ? ((string)$Vehicle['TransmissionType'] === $transmission) : true;

                        // Check doors if specified
                        $doorsMatch = $doors ? ((string)$Vehicle['DoorCount'] === $doors) : true;

                        // Check mileage if specified
                        $mileageMatch = $mileage ? ((string)$VehAvail->VehAvailCore->RentalRate['DistanceIncluded'] === $mileage) : true;

                        // Check fuel type if specified
                        $fuelTypeMatch = !empty($fuelTypes) ? in_array((string)$Vehicle['FuelType'], $fuelTypes) : true;

                        // If all conditions match, add the vehicle size to the result
                        if ($transmissionMatch && $doorsMatch && $mileageMatch && $fuelTypeMatch) {
                            $vehicleDetails[] = (string)$Vehicle->VehClass['Size']; // Now filtering by size
                        }
                    }
                }
                return $vehicleDetails;
            }

            // Apply the filtering function to each XML response
            $filteredVehiclesZE = filter($xmlresZE, $transmission); // Filter ZE vehicles
            $filteredVehiclesZT = filter($xmlresZT, $transmission); // Filter ZT vehicles
            $filteredVehiclesZR = filter($xmlresZR, $transmission); // Filter ZR vehicles

            // Function to update vehicle details array based on filtered sizes
            function filterVehicleDetailsBySize($vehicleDetails, $filteredSizes)
            {
                $filteredDetails = [];
                foreach ($vehicleDetails as $category => $vehicles) {
                    foreach ($vehicles as $vehicle) {
                        if (in_array($vehicle['size'], $filteredSizes)) {
                            $filteredDetails[$category][] = $vehicle;
                        }
                    }
                }
                return $filteredDetails;
            }

            // Apply the filtering to each vehicle detail array using size
            $vehicleDetailsZE = filterVehicleDetailsBySize($vehicleDetailsZE, $filteredVehiclesZE);
            $vehicleDetailsZT = filterVehicleDetailsBySize($vehicleDetailsZT, $filteredVehiclesZT);
            $vehicleDetailsZR = filterVehicleDetailsBySize($vehicleDetailsZR, $filteredVehiclesZR);

            // Uncomment below lines if you want to see the filtered results for debugging
            // echo "<pre>";
            // print_r($vehicleDetailsZE);
            // echo "</pre>";
        }
    }

    ?>
    <?php include 'header.php'; ?>
    <div>
        <div class="modal fade bd-example-modal-lg" tabindex="-1" id="popUp" role="dialog" aria-labelledby="myLargeModalLabel" aria-hidden="true">
            <div class="modal-dialog modal-lg">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title">Search Your Next Rental</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body">
                        <?php include 'searchWidget.php'; ?>
                    </div>
                </div>
            </div>
        </div>
        <div class="row" style="background: url('./images/res_back.jpg');background-repeat: no-repeat;
            background-attachment: local;
            background-size: 100% 100%;
            height: 14rem;">
            <div class="container align-content-center">
                <h1 class="text-white text-center">
                    Explore, Discover & Save, 24,000 <br>
                    Locations & Local Support
                </h1>
            </div>
        </div>
        <div class="loader_div d-grid" style="justify-content: center; align-content: center;">
            <p style="font-size: xx-large;font-weight: 700;text-align: center">Please Be Patient</p>
            <div class="d-flex justify-content-center">
                <img src="./images/Loader.png" class="loader" alt="Loader" style="width:10rem">
            </div>
            <?php include 'footer.php'; ?>
        </div>
        <div class="results_div d-none">
            <!-- location details -->
            <div class="row py-3 p-md-0" style="background-color: rgba(35,31,32,.5)!important">
                <div class="container row">
                    <div class="col-md-10">
                        <?php //foreach($results as $res):
                        ?>
                        <p class="text-center my-3 text-white">
                            <?php echo $pickupDetails; ?>, <?php echo $pickDate; ?> -> <?php echo $dropoffDetails; ?>, <?php echo $dropDate; ?>
                        </p>
                        <?php //endforeach;
                        ?>
                    </div>
                    <div class="col-md-2 d-flex align-content-center justify-content-center">
                        <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target=".bd-example-modal-lg">MODIFY</button>
                    </div>
                </div>
            </div>
            <!-- search dropdown desktop-->
            <div style="background-color: #ced1d4; height: auto;" class="p-3 d-none d-md-block">
                <div>
                    <form class="container d-flex flex-wrap align-items-baseline" action="" method="get">
                        <details>
                            <summary class="dropdown">Transmission Types <i class="fa-solid fa-angle-down fa-lg" style="color: #000000;"></i></summary>
                            <div class="shown">
                                <input type="checkbox" name="transmission" id="automatic" value="Automatic">
                                <label for="automatic" style="font-size: 1rem;">Automatic Only</label><br>
                                <input type="checkbox" name="transmission" id="manual" value="Manual">
                                <label for="manual" style="font-size: 1rem;">Manual Only</label>
                            </div>
                        </details>
                        <details>
                            <summary class="dropdown">Fuel Type or Electric <i class="fa-solid fa-angle-down fa-lg" style="color: #000000;"></i></summary>
                            <div class="shown">
                                <input type="checkbox" id="diesel" name="fuelTypes[]" value="Diesel">
                                <label for="diesel" style="font-size: 1rem;">Diesel</label><br>
                                <input type="checkbox" id="electric" name="fuelTypes[]" value="Electric">
                                <label for="electric" style="font-size: 1rem;">Electric</label><br>
                                <input type="checkbox" id="hybrid" name="fuelTypes[]" value="Hybrid">
                                <label for="hybrid" style="font-size: 1rem;">Hybrid</label><br>
                                <input type="checkbox" id="unspecifiedFuel" name="fuelTypes[]" value="Unspecified">
                                <label for="unspecifiedFuel" style="font-size: 1rem;">Unspecified fuel/power</label>
                            </div>
                        </details>
                        <details>
                            <summary class="dropdown">Mileage <i class="fa-solid fa-angle-down fa-lg" style="color: #000000;"></i></summary>
                            <div class="shown">
                                <input type="radio" id="unlimited" name="mileage" value="Unlimited">
                                <label for="unlimited" style="font-size: 1rem;">Unlimited</label><br>
                                <input type="radio" id="limited" name="mileage" value="Limited">
                                <label for="limited" style="font-size: 1rem;">Limited</label>
                            </div>
                        </details>
                        <details>
                            <summary class="dropdown">Doors <i class="fa-solid fa-angle-down fa-lg" style="color: #000000;"></i></summary>
                            <div class="shown">
                                <input type="radio" id="doors2" name="doors" value="2">
                                <label for="doors2" style="font-size: 1rem;">2</label><br>
                                <input type="radio" id="doors4" name="doors" value="4+">
                                <label for="doors4" style="font-size: 1rem;">4+</label>
                            </div>
                        </details>
                        <div class="d-flex gap-3">
                            <button name="search" type="submit" class="btn btn-primary">Search</button>
                            <a href="<?php echo strtok($_SERVER["REQUEST_URI"], '?'); ?>" class="btn btn-danger">Reset</a>
                        </div>
                    </form>
                </div>
            </div>
            <!-- search dropdown mobile-->
            <div style="background-color: #ced1d4; height: auto;padding:1rem 0.5rem" class="d-md-none">
                <div>
                    <form action="" method="get">
                        <div class="d-flex justify-content-evenly">
                            <div>
                                <details>
                                    <summary class="dropdown" style="width: 170px;">Transmission Types <i class="fa-solid fa-angle-down fa-lg" style="color: #000000;"></i></summary>
                                    <div class="shown">
                                        <input type="checkbox" name="transmission" id="automatic-mobile" value="Automatic">
                                        <label for="automatic-mobile" style="font-size: 1rem;">Automatic Only</label><br>
                                        <input type="checkbox" name="transmission" id="manual-mobile" value="Manual">
                                        <label for="manual-mobile" style="font-size: 1rem;">Manual Only</label>
                                    </div>
                                </details>
                                <details>
                                    <summary class="dropdown" style="width: 170px;">Fuel Type or Electric <i class="fa-solid fa-angle-down fa-lg" style="color: #000000;"></i></summary>
                                    <div class="shown">
                                        <input type="checkbox" id="diesel-mobile" name="fuelTypes[]" value="Diesel">
                                        <label for="diesel-mobile" style="font-size: 1rem;">Diesel</label><br>
                                        <input type="checkbox" id="electric-mobile" name="fuelTypes[]" value="Electric">
                                        <label for="electric-mobile" style="font-size: 1rem;">Electric</label><br>
                                        <input type="checkbox" id="hybrid-mobile" name="fuelTypes[]" value="Hybrid">
                                        <label for="hybrid-mobile" style="font-size: 1rem;">Hybrid</label><br>
                                        <input type="checkbox" id="unspecifiedFuel-mobile" name="fuelTypes[]" value="Unspecified">
                                        <label for="unspecifiedFuel-mobile" style="font-size: 1rem;">Unspecified fuel/power</label>
                                    </div>
                                </details>
                            </div>
                            <div>
                                <details>
                                    <summary class="dropdown" style="width: 151px;">Mileage <i class="fa-solid fa-angle-down fa-lg" style="color: #000000;"></i></summary>
                                    <div class="shown">
                                        <input type="radio" id="unlimited-mobile" name="mileage" value="Unlimited">
                                        <label for="unlimited-mobile" style="font-size: 1rem;">Unlimited</label><br>
                                        <input type="radio" id="limited-mobile" name="mileage" value="Limited">
                                        <label for="limited-mobile" style="font-size: 1rem;">Limited</label>
                                    </div>
                                </details>
                                <details>
                                    <summary class="dropdown" style="width: 151px;">Doors <i class="fa-solid fa-angle-down fa-lg" style="color: #000000;"></i></summary>
                                    <div class="shown">
                                        <input type="radio" id="doors2-mobile" name="doors" value="2">
                                        <label for="doors2-mobile" style="font-size: 1rem;">2</label><br>
                                        <input type="radio" id="doors4-mobile" name="doors" value="4+">
                                        <label for="doors4-mobile" style="font-size: 1rem;">4+</label>
                                    </div>
                                </details>
                            </div>
                        </div>
                        <div class="d-flex justify-content-center gap-3">
                            <button name="search" type="submit" class="btn btn-primary">Search</button>
                            <a href="<?php echo strtok($_SERVER["REQUEST_URI"], '?'); ?>" class="btn btn-danger">Reset</a>
                        </div>
                    </form>
                </div>
            </div>
            <!-- price table desktop-->
            <div id="price_table" class="container d-none d-md-block">  
                <table class="table table-bordered my-4">
                    <thead>
                        <tr>
                            <th scope="col"></th>
                            <th scope="col">
                                <img src="./images/economy.jpg" alt="">
                                <p class="text-center">Economy</p>
                            </th>
                            <th scope="col">
                                <img src="./images/compact.jpg" alt="">
                                <p class="text-center">Compact </p>
                            </th>
                            <th scope="col">
                                <img src="./images/midsize.jpg" alt="">
                                <p class="text-center">Midsize</p>
                            </th>
                            <th scope="col">
                                <img src="./images/LuxurySportsCar.jpg" alt="">
                                <p class="text-center">Luxury/Sports Car </p>
                            </th>
                            <th scope="col">
                                <img src="./images/suv.jpg" alt="">
                                <p class="text-center">SUV</p>
                            </th>
                            <th scope="col">
                                <img src="./images/stationwagon.jpg" alt="">
                                <p class="text-center">Station Wagon</p>
                            </th>
                            <th scope="col">
                                <img src="./images/VanPeopleCarrier.jpg" alt="">
                                <p class="text-center">Van/People Carrier</p>
                            </th>
                            <th scope="col">
                                <img src="./images/7-12PassengerVans.jpg" alt="">
                                <p class="text-center">7-12 Passenger Vans</p>
                            </th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr id="Euro">
                            <th class="d-flex justify-content-center" id="Euro_image">
                                <img src="./images/EuroCar.svg" alt="">
                            </th>
                            <?php
                            // Loop through categories and display rates
                            foreach ($categoriesEuro as $category => $codes) {
                                $found = false; // Track if we find a vehicle in the category
                                $dataSize = implode(',', $codes); // Dynamically generate the sizes for data-size
                                echo '<td class="text-center Euro" data-size="' . $dataSize . '">';
                                    echo '<span id="Euro_Price">Not Available</span>';
                                echo '</td>';
                            }
                            ?>
                        </tr>
                        <tr id="hertz">
                            <th id="hertz_image"><img src="./images/hertz.png" alt=""></th>
                            <?php foreach ($categories as $category => $sizes): ?>
                                <td class="text-center" data-size="<?php echo implode(',', $sizes); ?>">
                                    <?php if (isset($vehicleDetailsZE[$category])): ?>
                                        <?php foreach ($vehicleDetailsZE[$category] as $details): ?>
                                            <?php
                                            // Output the rate and the code
                                            echo 'AUD ' . number_format(calculatePercentage($markUp,$details['rate']), 2);
                                            ?>
                                            <br>
                                            <!-- <input type="text" value="<?php echo $details['code']; ?>" hidden> -->
                                            <?php break; // Break to only show the first vehicle of each category 
                                            ?>
                                        <?php endforeach; ?>
                                    <?php else: ?>
                                        Not Available
                                    <?php endif; ?>
                                </td>
                            <?php endforeach; ?>
                        </tr>
                        <tr id="doller">
                            <th id="doller_image"><img src="./images/DOLLARRet.png" alt=""></th>

                            <?php foreach ($categories as $category => $sizes): ?>
                                <td class="text-center" data-size="<?php echo implode(',', $sizes); ?>">
                                    <?php if (isset($vehicleDetailsZR[$category])): ?>
                                        <?php foreach ($vehicleDetailsZR[$category] as $details): ?>
                                            <?php
                                            // Output the rate and the code
                                            echo 'AUD ' . number_format(calculatePercentage($markUp,$details['rate']), 2);
                                            ?>
                                            <br>
                                            <!-- <input type="text" value="<?php echo $details['code']; ?>" hidden> -->
                                            <?php break; // Break to only show the first vehicle of each category 
                                            ?>
                                        <?php endforeach; ?>
                                    <?php else: ?>
                                        Not Available
                                    <?php endif; ?>
                                </td>
                            <?php endforeach; ?>
                        </tr>
                        <tr id="thrifty">
                            <th id="thrifty_image"><img src="./images/thrifty.png" alt=""></th>

                            <?php foreach ($categories as $category => $sizes): ?>
                                <td class="text-center" data-size="<?php echo implode(',', $sizes); ?>">
                                    <?php if (isset($vehicleDetailsZT[$category])): ?>
                                        <?php foreach ($vehicleDetailsZT[$category] as $details): ?>
                                            <?php
                                            // Output the rate and the code
                                            echo 'AUD ' . number_format(calculatePercentage($markUp,$details['rate']), 2);
                                            ?>
                                            <br>
                                            <!-- <input type="text" value="<?php echo $details['code']; ?>" hidden> -->
                                            <?php break; // Break to only show the first vehicle of each category 
                                            ?>
                                        <?php endforeach; ?>
                                    <?php else: ?>
                                        Not Available
                                    <?php endif; ?>
                                </td>
                            <?php endforeach; ?>
                        </tr>
                    </tbody>
                </table>
            </div>
            <!-- price table mobile-->
            <div id="price_table_mobile" class="container d-md-none" style="overflow-x: scroll;">
                <div class="table-responsive-x">
                    <table class="table table-bordered my-4">
                        <thead>
                        </thead>
                        <tbody>
                            <!-- Eurocar Row -->
                            <tr id="Euro">
                                <th class="" id="Euro_image">
                                    <img src="./images/EuroCar.svg" alt="">
                                </th>
                                <?php
                                foreach ($categoriesEuro as $category => $codes) {
                                    $found = false;
                                    $dataSize = implode(',', $codes);
                                    echo '<td class="text-center mobile" data-size="' . $dataSize . '">';
                                        echo '<span id="Euro_Price">Not Available</span>';
                                    echo '</td>';
                                }
                                ?>
                            </tr>
                            <!-- Hertz Row -->
                            <tr id="hertz">
                                <th id="hertz_image"><img src="./images/hertz.png" alt=""></th>
                                <?php foreach ($categories as $category => $sizes): ?>
                                    <td class="text-center mobile" data-size="<?php echo implode(',', $sizes); ?>">
                                        <?php if (isset($vehicleDetailsZE[$category])): ?>
                                            <?php foreach ($vehicleDetailsZE[$category] as $details): ?>
                                                <?php
                                                echo 'AUD ' . number_format(calculatePercentage($markUp,$details['rate']), 2);
                                                ?>
                                                <br>
                                                <?php break; ?>
                                            <?php endforeach; ?>
                                        <?php else: ?>
                                            Not Available
                                        <?php endif; ?>
                                    </td>
                                <?php endforeach; ?>
                            </tr>
                            <!-- Dollar Row -->
                            <tr id="doller">
                                <th id="doller_image"><img src="./images/DOLLARRet.png" alt=""></th>

                                <?php foreach ($categories as $category => $sizes): ?>
                                    <td class="text-center mobile" data-size="<?php echo implode(',', $sizes); ?>">
                                        <?php if (isset($vehicleDetailsZR[$category])): ?>
                                            <?php foreach ($vehicleDetailsZR[$category] as $details): ?>
                                                <?php
                                                // Output the rate and the code
                                                echo 'AUD ' . number_format(calculatePercentage($markUp,$details['rate']), 2);
                                                ?>
                                                <br>
                                                <!-- <input type="text" value="<?php echo $details['code']; ?>" hidden> -->
                                                <?php break; // Break to only show the first vehicle of each category 
                                                ?>
                                            <?php endforeach; ?>
                                        <?php else: ?>
                                            Not Available
                                        <?php endif; ?>
                                    </td>
                                <?php endforeach; ?>
                            </tr>

                            <!-- Thrifty Row -->
                            <tr id="thrifty">
                                <th id="thrifty_image"><img src="./images/thrifty.png" alt=""></th>
                                <?php foreach ($categories as $category => $sizes): ?>
                                    <td class="text-center mobile" data-size="<?php echo implode(',', $sizes); ?>">
                                        <?php if (isset($vehicleDetailsZT[$category])): ?>
                                            <?php foreach ($vehicleDetailsZT[$category] as $details): ?>
                                                <?php
                                                echo 'AUD ' . number_format(calculatePercentage($markUp,$details['rate']), 2);
                                                ?>
                                                <br>
                                                <?php break; ?>
                                            <?php endforeach; ?>
                                        <?php else: ?>
                                            Not Available
                                        <?php endif; ?>
                                    </td>
                                <?php endforeach; ?>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
            <!-- result line desktop-->
            <div class="d-none d-md-block">
                <div style="background-color: #ced1d4; height: auto; display: none;" class="p-3" id="results-count-container">
                    <div class="container d-flex justify-content-start text-white">
                        <span id="results-count">SHOWING 0 RESULTS</span>
                    </div>
                </div>
            </div>
            <!-- result line mobile-->
            <div class="d-md-none">
                <div style="background-color: #ced1d4; height: auto; display: none;" class="p-3" id="results-count-container-mobile">
                    <div class="container d-flex justify-content-start text-white">
                        <span id="results-count-mobile">SHOWING 0 RESULTS</span>
                    </div>
                </div>
            </div>
            <!-- results cards ZE desktop-->
            <div class="d-none d-md-block">
                <div class="container">
                    <div id="vehicle-list-hertz" class="vehicle-list">
                        <?php
                        // Loop through each vehicle in the XML
                        if (isset($xmlresZE->VehAvailRSCore->VehVendorAvails->VehVendorAvail->VehAvails->VehAvail)) {
                            foreach ($xmlresZE->VehAvailRSCore->VehVendorAvails->VehVendorAvail->VehAvails->VehAvail as $vehicle) {
                                // Get the vehicle details
                                $size = (int)$vehicle->VehAvailCore->Vehicle->VehClass['Size'];
                                $name = (string)$vehicle->VehAvailCore->Vehicle->VehMakeModel['Name'];
                                $transmission = (string)$vehicle->VehAvailCore->Vehicle['TransmissionType'];
                                $passengers = (string)$vehicle->VehAvailCore->Vehicle['PassengerQuantity'];
                                $luggage = (string)$vehicle->VehAvailCore->Vehicle['BaggageQuantity'];
                                $rate = (float)$vehicle->VehAvailCore->TotalCharge['RateTotalAmount'];
                                $final = calculatePercentage($markUp, $rate);
                                $currency = (string)$vehicle->VehAvailCore->TotalCharge['CurrencyCode'];
                                $vendor = (string)$vehicle->VehAvailCore->Vehicle['VendorName'] ?? 'Vendor';
                                $image = "https://images.hertz.com/vehicles/220x128/" . (string)$vehicle->VehAvailCore->Vehicle->PictureURL;
                                $vendorLogo = "./images/hertz.png"; // Use dynamic logos if needed
                                $reference = (string)$vehicle->VehAvailCore->Reference['ID'];

                                // Output the HTML for each vehicle, hide them initially
                                echo '
                                <div class="res_card res_hertz vehicle-item" data-size="' . $size . '" style="display: none;">
                                    <div class="row">
                                        <div class="col-4 d-grid">
                                            <img style="width:20rem;" src="' . $image . '" alt="' . $name . '">
                                            <img src="' . $vendorLogo . '" alt="' . $vendor . '">
                                        </div>
                                        <div class="col-4">
                                            <strong>' . $name . '</strong>
                                            <p>OR SIMILAR | ' . strtoupper($transmission) . ' CLASS</p>
                                            <div class="d-flex gap-2 my-3">
                                                <div class="car_spec">' . ucfirst($transmission) . '</div>
                                                <div class="car_spec">
                                                    <img src="./images/door-icon.png" alt="">' . $passengers . '
                                                </div>
                                                <div class="car_spec">
                                                    <img src="./images/person-icon.png" alt="">' . $passengers . '
                                                </div>
                                                <div class="car_spec">
                                                    <img src="./images/S-luggage-icon.png" alt="">' . $luggage . '
                                                </div>
                                                <div class="car_spec">
                                                    <img src="./images/snow-icon.png" alt="">
                                                </div>
                                            </div>
                                            <div class="car_info mb-1">
                                                <img src="./images/plane-icon.png" alt="">
                                                <label for=""> On Airport</label>
                                            </div>
                                            <div class="car_info mb-1">
                                                <img src="./images/km-icon.png" alt="">
                                                <label for=""> Unlimited Kilometres</label>
                                            </div>
                                            <div class="text-primary" style="">
                                                + Terms and Conditions
                                            </div>
                                        </div>
                                        <div class="col-4">
                                            <div class="res_pay">
                                                <div>
                                                    <p>Insurances Package</p>
                                                    <p>Rates starting at ...</p>
                                                </div>
                                                <div>
                                                    <p>'."Net : " . $currency . ' ' . number_format($rate,2) . '</p>
                                                    <p>'."Markup : " . $currency . ' ' . number_format($final,2) . '</p>
                                                </div>
                                            </div>
                                            <div class="res_pay">
                                                <div class="d-flex">
                                                    <a href="book.php?reference=' . $reference . '&vdNo=ZE"; class="btn btn-primary">BOOK NOW</a>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>';
                            }
                        } else {
                            echo '';
                        }
                        ?>
                    </div>
                </div>
            </div>
            <!-- results cards ZE mobile-->
            <div class="d-md-none">
                <div>
                    <div id="vehicle-list-hertz-mobile" class="vehicle-list-mobile container">
                        <?php
                        // Loop through each vehicle in the XML
                        if (isset($xmlresZE->VehAvailRSCore->VehVendorAvails->VehVendorAvail->VehAvails->VehAvail)) {
                            foreach ($xmlresZE->VehAvailRSCore->VehVendorAvails->VehVendorAvail->VehAvails->VehAvail as $vehicle) {
                                // Get the vehicle details
                                $size = (int)$vehicle->VehAvailCore->Vehicle->VehClass['Size'];
                                $name = (string)$vehicle->VehAvailCore->Vehicle->VehMakeModel['Name'];
                                $transmission = (string)$vehicle->VehAvailCore->Vehicle['TransmissionType'];
                                $passengers = (string)$vehicle->VehAvailCore->Vehicle['PassengerQuantity'];
                                $luggage = (string)$vehicle->VehAvailCore->Vehicle['BaggageQuantity'];
                                $rate = (float)$vehicle->VehAvailCore->TotalCharge['RateTotalAmount'];
                                $final = calculatePercentage($markUp, $rate);
                                $currency = (string)$vehicle->VehAvailCore->TotalCharge['CurrencyCode'];
                                $vendor = (string)$vehicle->VehAvailCore->Vehicle['VendorName'] ?? 'Vendor';
                                $image = "https://images.hertz.com/vehicles/220x128/" . (string)$vehicle->VehAvailCore->Vehicle->PictureURL;
                                $vendorLogo = "./images/hertz.png"; // Use dynamic logos if needed
                                $reference = (string)$vehicle->VehAvailCore->Reference['ID'];

                                // Output the HTML for each vehicle, hide them initially
                                echo '
                                <div class="res_card vehicle-item-mobile" data-size="' . $size . '" style="display: none;">
                                    <div>
                                        <div class="d-grid">
                                            <img style="width:20rem;" src="' . $image . '" alt="' . $name . '">
                                            <img src="' . $vendorLogo . '" alt="' . $vendor . '">
                                        </div>
                                        <div>
                                            <strong>' . $name . '</strong>
                                            <p>OR SIMILAR | ' . strtoupper($transmission) . ' CLASS</p>
                                            <div class="d-flex gap-2 my-3">
                                                <div class="car_spec">' . ucfirst($transmission) . '</div>
                                                <div class="car_spec">
                                                    <img src="./images/door-icon.png" alt="">' . $passengers . '
                                                </div>
                                                <div class="car_spec">
                                                    <img src="./images/person-icon.png" alt="">' . $passengers . '
                                                </div>
                                                <div class="car_spec">
                                                    <img src="./images/S-luggage-icon.png" alt="">' . $luggage . '
                                                </div>
                                                <div class="car_spec">
                                                    <img src="./images/snow-icon.png" alt="">
                                                </div>
                                            </div>
                                            <div class="car_info mb-1">
                                                <img src="./images/plane-icon.png" alt="">
                                                <label for=""> On Airport</label>
                                            </div>
                                            <div class="car_info mb-1">
                                                <img src="./images/km-icon.png" alt="">
                                                <label for=""> Unlimited Kilometres</label>
                                            </div>
                                            <div class="text-primary" style="">
                                                + Terms and Conditions
                                            </div>
                                        </div>
                                        <div>
                                            <div class="res_pay">
                                                <div>
                                                    <p>Insurances Package</p>
                                                    <p>Rates starting at ...</p>
                                                </div>
                                                <div>
                                                    <p>'."Net : " . $currency . ' ' . number_format($rate,2) . '</p>
                                                    <p>'."Markup : " . $currency . ' ' . number_format($final,2) . '</p>
                                                </div>
                                            </div>
                                            <div class="res_pay">
                                                <div class="d-flex">
                                                    <a href="book.php?reference=' . $reference . '&vdNo=ZE"; class="btn btn-primary">BOOK NOW</a>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>';
                            }
                        } else {
                            echo '';
                        }
                        ?>
                    </div>
                </div>
            </div>
            <!-- results cards ZT desktop-->
            <div class="d-none d-md-block">
                <div class="container">
                    <div id="vehicle-list-thrifty" class="vehicle-list" style="display:none;">
                        <?php
                        // Loop through each vehicle in the XML
                        if (isset($xmlresZT->VehAvailRSCore->VehVendorAvails->VehVendorAvail->VehAvails->VehAvail)) {
                            foreach ($xmlresZT->VehAvailRSCore->VehVendorAvails->VehVendorAvail->VehAvails->VehAvail as $vehicle) {
                                // Get the vehicle details
                                $size = (int)$vehicle->VehAvailCore->Vehicle->VehClass['Size'];
                                $name = (string)$vehicle->VehAvailCore->Vehicle->VehMakeModel['Name'];
                                $transmission = (string)$vehicle->VehAvailCore->Vehicle['TransmissionType'];
                                $passengers = (string)$vehicle->VehAvailCore->Vehicle['PassengerQuantity'];
                                $luggage = (string)$vehicle->VehAvailCore->Vehicle['BaggageQuantity'];
                                $rate = (float)$vehicle->VehAvailCore->TotalCharge['RateTotalAmount'];
                                $final = calculatePercentage($markUp, $rate);
                                $currency = (string)$vehicle->VehAvailCore->TotalCharge['CurrencyCode'];
                                $vendor = (string)$vehicle->VehAvailCore->Vehicle['VendorName'] ?? 'Vendor';
                                $image = "https://images.hertz.com/vehicles/220x128/" . (string)$vehicle->VehAvailCore->Vehicle->PictureURL;
                                $vendorLogo = "./images/thrifty.png"; // Use dynamic logos if needed
                                $reference = (string)$vehicle->VehAvailCore->Reference['ID'];

                                // Output the HTML for each vehicle, hide them initially
                                echo '
                                <div class="res_card res_thrifty vehicle-item" data-size="' . $size . '" style="display: none;">
                                    <div class="row">
                                        <div class="col-4 d-grid">
                                            <img style="width:20rem;" src="' . $image . '" alt="' . $name . '">
                                            <img src="' . $vendorLogo . '" alt="' . $vendor . '">
                                        </div>
                                        <div class="col-4">
                                            <strong>' . $name . '</strong>
                                            <p>OR SIMILAR | ' . strtoupper($transmission) . ' CLASS</p>
                                            <div class="d-flex gap-2 my-3">
                                                <div class="car_spec">' . ucfirst($transmission) . '</div>
                                                <div class="car_spec">
                                                    <img src="./images/door-icon.png" alt="">' . $passengers . '
                                                </div>
                                                <div class="car_spec">
                                                    <img src="./images/person-icon.png" alt="">' . $passengers . '
                                                </div>
                                                <div class="car_spec">
                                                    <img src="./images/S-luggage-icon.png" alt="">' . $luggage . '
                                                </div>
                                                <div class="car_spec">
                                                    <img src="./images/snow-icon.png" alt="">
                                                </div>
                                            </div>
                                            <div class="car_info mb-1">
                                                <img src="./images/plane-icon.png" alt="">
                                                <label for=""> On Airport</label>
                                            </div>
                                            <div class="car_info mb-1">
                                                <img src="./images/km-icon.png" alt="">
                                                <label for=""> Unlimited Kilometres</label>
                                            </div>
                                            <div class="text-primary" style="">
                                                + Terms and Conditions
                                            </div>
                                        </div>
                                        <div class="col-4">
                                            <div class="res_pay">
                                                <div>
                                                    <p>Insurances Package</p>
                                                    <p>Rates starting at ...</p>
                                                </div>
                                                <div>
                                                    <p>'."Net : " . $currency . ' ' . number_format($rate,2) . '</p>
                                                    <p>'."Markup : " . $currency . ' ' . number_format($final,2) . '</p>
                                                </div>
                                            </div>
                                            <div class="res_pay">
                                                <div class="d-flex">
                                                    <a href="book.php?reference=' . $reference . '&vdNo=ZT"; class="btn btn-primary">BOOK NOW</a>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>';
                            }
                        } else {
                            echo '';
                        }
                        ?>
                    </div>
                </div>
            </div>
            <!-- results cards ZT mobile-->
            <div class="d-md-none">
                <div>
                    <div id="vehicle-list-thrifty-mobile" class="vehicle-list-mobile container">
                        <?php
                        // Loop through each vehicle in the XML
                        if (isset($xmlresZT->VehAvailRSCore->VehVendorAvails->VehVendorAvail->VehAvails->VehAvail)) {
                            foreach ($xmlresZT->VehAvailRSCore->VehVendorAvails->VehVendorAvail->VehAvails->VehAvail as $vehicle) {
                                // Get the vehicle details
                                $size = (int)$vehicle->VehAvailCore->Vehicle->VehClass['Size'];
                                $name = (string)$vehicle->VehAvailCore->Vehicle->VehMakeModel['Name'];
                                $transmission = (string)$vehicle->VehAvailCore->Vehicle['TransmissionType'];
                                $passengers = (string)$vehicle->VehAvailCore->Vehicle['PassengerQuantity'];
                                $luggage = (string)$vehicle->VehAvailCore->Vehicle['BaggageQuantity'];
                                $rate = (float)$vehicle->VehAvailCore->TotalCharge['RateTotalAmount'];
                                $final = calculatePercentage($markUp, $rate);
                                $currency = (string)$vehicle->VehAvailCore->TotalCharge['CurrencyCode'];
                                $vendor = (string)$vehicle->VehAvailCore->Vehicle['VendorName'] ?? 'Vendor';
                                $image = "https://images.hertz.com/vehicles/220x128/" . (string)$vehicle->VehAvailCore->Vehicle->PictureURL;
                                $vendorLogo = "./images/thrifty.png"; // Use dynamic logos if needed
                                $reference = (string)$vehicle->VehAvailCore->Reference['ID'];

                                // Output the HTML for each vehicle, hide them initially
                                echo '
                                <div class="res_card vehicle-item-mobile" data-size="' . $size . '" style="display: none;">
                                    <div>
                                        <div class="d-grid">
                                            <img style="width:20rem;" src="' . $image . '" alt="' . $name . '">
                                            <img src="' . $vendorLogo . '" alt="' . $vendor . '">
                                        </div>
                                        <div>
                                            <strong>' . $name . '</strong>
                                            <p>OR SIMILAR | ' . strtoupper($transmission) . ' CLASS</p>
                                            <div class="d-flex gap-2 my-3">
                                                <div class="car_spec">' . ucfirst($transmission) . '</div>
                                                <div class="car_spec">
                                                    <img src="./images/door-icon.png" alt="">' . $passengers . '
                                                </div>
                                                <div class="car_spec">
                                                    <img src="./images/person-icon.png" alt="">' . $passengers . '
                                                </div>
                                                <div class="car_spec">
                                                    <img src="./images/S-luggage-icon.png" alt="">' . $luggage . '
                                                </div>
                                                <div class="car_spec">
                                                    <img src="./images/snow-icon.png" alt="">
                                                </div>
                                            </div>
                                            <div class="car_info mb-1">
                                                <img src="./images/plane-icon.png" alt="">
                                                <label for=""> On Airport</label>
                                            </div>
                                            <div class="car_info mb-1">
                                                <img src="./images/km-icon.png" alt="">
                                                <label for=""> Unlimited Kilometres</label>
                                            </div>
                                            <div class="text-primary" style="">
                                                + Terms and Conditions
                                            </div>
                                        </div>
                                        <div>
                                            <div class="res_pay">
                                                <div>
                                                    <p>Insurances Package</p>
                                                    <p>Rates starting at ...</p>
                                                </div>
                                                <div>
                                                    <p>'."Net : " . $currency . ' ' . number_format($rate,2) . '</p>
                                                    <p>'."Markup : " . $currency . ' ' . number_format($final,2) . '</p>
                                                </div>
                                            </div>
                                            <div class="res_pay">
                                                <div class="d-flex">
                                                    <a href="book.php?reference=' . $reference . '&vdNo=ZT"; class="btn btn-primary">BOOK NOW</a>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>';
                            }
                        } else {
                            echo '';
                        }
                        ?>
                    </div>
                </div>
            </div>
            <!-- results cards ZR desktop-->
            <div class="d-none d-md-block">
                <div class="container">
                    <div id="vehicle-list-doller" class="vehicle-list" style="display:none;">
                        <?php
                        // Loop through each vehicle in the XML
                        if (isset($xmlresZR->VehAvailRSCore->VehVendorAvails->VehVendorAvail->VehAvails->VehAvail)) {
                            // Function to calculate the percentage

                            foreach ($xmlresZR->VehAvailRSCore->VehVendorAvails->VehVendorAvail->VehAvails->VehAvail as $vehicle) {
                                // Get the vehicle details
                                $size = (int)$vehicle->VehAvailCore->Vehicle->VehClass['Size'];
                                $name = (string)$vehicle->VehAvailCore->Vehicle->VehMakeModel['Name'];
                                $transmission = (string)$vehicle->VehAvailCore->Vehicle['TransmissionType'];
                                $passengers = (string)$vehicle->VehAvailCore->Vehicle['PassengerQuantity'];
                                $luggage = (string)$vehicle->VehAvailCore->Vehicle['BaggageQuantity'];
                                $rate = (float)$vehicle->VehAvailCore->TotalCharge['RateTotalAmount'];
                                $final = calculatePercentage($markUp, $rate);
                                $currency = (string)$vehicle->VehAvailCore->TotalCharge['CurrencyCode'];
                                $vendor = (string)$vehicle->VehAvailCore->Vehicle['VendorName'] ?? 'Vendor';
                                $image = "https://images.hertz.com/vehicles/220x128/" . (string)$vehicle->VehAvailCore->Vehicle->PictureURL;
                                $vendorLogo = "./images/DOLLARRet.png"; // Use dynamic logos if needed
                                $reference = (string)$vehicle->VehAvailCore->Reference['ID'];

                                // Output the HTML for each vehicle, hide them initially
                                echo '
                                <div class="res_card res_doller vehicle-item" data-size="' . $size . '" style="display: none;">
                                    <div class="row">
                                        <div class="col-4 d-grid">
                                            <img style="width:20rem;" src="' . $image . '" alt="' . $name . '">
                                            <img src="' . $vendorLogo . '" alt="' . $vendor . '">
                                        </div>
                                        <div class="col-4">
                                            <strong>' . $name . '</strong>
                                            <p>OR SIMILAR | ' . strtoupper($transmission) . ' CLASS</p>
                                            <div class="d-flex gap-2 my-3">
                                                <div class="car_spec">' . ucfirst($transmission) . '</div>
                                                <div class="car_spec">
                                                    <img src="./images/door-icon.png" alt="">' . $passengers . '
                                                </div>
                                                <div class="car_spec">
                                                    <img src="./images/person-icon.png" alt="">' . $passengers . '
                                                </div>
                                                <div class="car_spec">
                                                    <img src="./images/S-luggage-icon.png" alt="">' . $luggage . '
                                                </div>
                                                <div class="car_spec">
                                                    <img src="./images/snow-icon.png" alt="">
                                                </div>
                                            </div>
                                            <div class="car_info mb-1">
                                                <img src="./images/plane-icon.png" alt="">
                                                <label for=""> On Airport</label>
                                            </div>
                                            <div class="car_info mb-1">
                                                <img src="./images/km-icon.png" alt="">
                                                <label for=""> Unlimited Kilometres</label>
                                            </div>
                                            <div class="text-primary" style="">
                                                + Terms and Conditions
                                            </div>
                                        </div>
                                        <div class="col-4">
                                            <div class="res_pay">
                                                <div>
                                                    <p>Insurances Package</p>
                                                    <p>Rates starting at ...</p>
                                                </div>
                                                <div>
                                                    <p>'."Net : " . $currency . ' ' . number_format($rate,2) . '</p>
                                                    <p>'."Markup : " . $currency . ' ' . number_format($final,2) . '</p>
                                                </div>
                                            </div>
                                            <div class="res_pay">
                                                <div class="d-flex">
                                                    <a href="book.php?reference=' . $reference . '&vdNo=ZR"; class="btn btn-primary">BOOK NOW</a>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>';
                            }
                        } else {
                            echo '';
                        }
                        var_dump($final);
                        ?>
                    </div>
                    <div class="res_card">
                        <div class="col-4"></div>
                        <div class="col-4"></div>
                        <div class="col-4"></div>
                    </div>
                </div>
            </div>
            <!-- results cards ZR mobile-->
            <div class="d-md-none">
                <div>
                    <div id="vehicle-list-dollar-mobile" class="vehicle-list-mobile container">
                        <?php
                        // Loop through each vehicle in the XML
                        if (isset($xmlresZR->VehAvailRSCore->VehVendorAvails->VehVendorAvail->VehAvails->VehAvail)) {
                            foreach ($xmlresZR->VehAvailRSCore->VehVendorAvails->VehVendorAvail->VehAvails->VehAvail as $vehicle) {
                                // Get the vehicle details
                                $size = (int)$vehicle->VehAvailCore->Vehicle->VehClass['Size'];
                                $name = (string)$vehicle->VehAvailCore->Vehicle->VehMakeModel['Name'];
                                $transmission = (string)$vehicle->VehAvailCore->Vehicle['TransmissionType'];
                                $passengers = (string)$vehicle->VehAvailCore->Vehicle['PassengerQuantity'];
                                $luggage = (string)$vehicle->VehAvailCore->Vehicle['BaggageQuantity'];
                                $rate = (float)$vehicle->VehAvailCore->TotalCharge['RateTotalAmount'];
                                $final = calculatePercentage($markUp, $rate);
                                $currency = (string)$vehicle->VehAvailCore->TotalCharge['CurrencyCode'];
                                $vendor = (string)$vehicle->VehAvailCore->Vehicle['VendorName'] ?? 'Vendor';
                                $image = "https://images.hertz.com/vehicles/220x128/" . (string)$vehicle->VehAvailCore->Vehicle->PictureURL;
                                $vendorLogo = "./images/DOLLARRet.png"; // Use dynamic logos if needed
                                $reference = (string)$vehicle->VehAvailCore->Reference['ID'];

                                // Output the HTML for each vehicle, hide them initially
                                echo '
                                <div class="res_card vehicle-item-mobile" data-size="' . $size . '" style="display: none;">
                                    <div>
                                        <div class="d-grid">
                                            <img style="width:20rem;" src="' . $image . '" alt="' . $name . '">
                                            <img src="' . $vendorLogo . '" alt="' . $vendor . '">
                                        </div>
                                        <div>
                                            <strong>' . $name . '</strong>
                                            <p>OR SIMILAR | ' . strtoupper($transmission) . ' CLASS</p>
                                            <div class="d-flex gap-2 my-3">
                                                <div class="car_spec">' . ucfirst($transmission) . '</div>
                                                <div class="car_spec">
                                                    <img src="./images/door-icon.png" alt="">' . $passengers . '
                                                </div>
                                                <div class="car_spec">
                                                    <img src="./images/person-icon.png" alt="">' . $passengers . '
                                                </div>
                                                <div class="car_spec">
                                                    <img src="./images/S-luggage-icon.png" alt="">' . $luggage . '
                                                </div>
                                                <div class="car_spec">
                                                    <img src="./images/snow-icon.png" alt="">
                                                </div>
                                            </div>
                                            <div class="car_info mb-1">
                                                <img src="./images/plane-icon.png" alt="">
                                                <label for=""> On Airport</label>
                                            </div>
                                            <div class="car_info mb-1">
                                                <img src="./images/km-icon.png" alt="">
                                                <label for=""> Unlimited Kilometres</label>
                                            </div>
                                            <div class="text-primary" style="">
                                                + Terms and Conditions
                                            </div>
                                        </div>
                                        <div>
                                            <div class="res_pay">
                                                <div>
                                                    <p>Insurances Package</p>
                                                    <p>Rates starting at ...</p>
                                                </div>
                                                <div>
                                                    <p>'."Net : " . $currency . ' ' . number_format($rate,2) . '</p>
                                                    <p>'."Markup : " . $currency . ' ' . number_format($final,2) . '</p>
                                                </div>
                                            </div>
                                            <div class="res_pay">
                                                <div class="d-flex">
                                                    <a href="book.php?reference=' . $reference . '&vdNo=ZR"; class="btn btn-primary">BOOK NOW</a>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>';
                            }
                        } else {
                            echo '';
                        }
                        ?>
                    </div>
                </div>
            </div>
            <!-- results cards Euro desktop-->
            <div class="d-none d-md-block">
                <div class="container">
                    <div id="vehicle-list-Euro" class="vehicle-list" style="display:none;">
                        <?php
                            if (isset($xmlresEuro->serviceResponse->carCategoryList->carCategory)) {
                                // Loop through each vehicle in the carCategoryList
                                foreach ($xmlresEuro->serviceResponse->carCategoryList->carCategory as $vehicle) {
                                    // Check if $vehicle and its properties are set before accessing them
                                    if (isset($vehicle) && isset($vehicle['carCategorySample'])) {
                                        $name = (string) $vehicle['carCategorySample'];
                                    } else {
                                        $name = "Not Available"; // Default value if not set
                                    }

                                    if (isset($vehicle['carCategorySeats'])) {
                                        $passengers = (string) $vehicle['carCategorySeats'];
                                    } else {
                                        $passengers = "Not Available";
                                    }

                                    if (isset($vehicle['carCategoryBaggageQuantity'])) {
                                        $luggage = (string) $vehicle['carCategoryBaggageQuantity'];
                                    } else {
                                        $luggage = "Not Available";
                                    }

                                    if (isset($vehicle['carCategoryAutomatic'])) {
                                        $transmission = (string) $vehicle['carCategoryAutomatic'] === 'Y' ? 'Automatic' : 'Manual';
                                    } else {
                                        $transmission = "Not Specified";
                                    }

                                    // Ensure $xmldata and $xml are valid before accessing them
                                    $xmldata = getQuote($vehicle['carCategoryCode'], $infoArray);
                                    if ($xmldata && $xml = simplexml_load_string($xmldata)) {
                                        $rate = isset($xml->serviceResponse->reservation->quote['basePrice']) ? (float)$xml->serviceResponse->reservation->quote['basePrice'] : 0;
                                        $carVisualLink = isset($xml->serviceResponse->reservation->links->link['value']) ? (string)$xml->serviceResponse->reservation->links->link['value'] : './images/default-car.png';
                                        $currency = isset($xml->serviceResponse->reservation->quote['currency']) ? (string)$xml->serviceResponse->reservation->quote['currency'] : 'USD';
                                    } else {
                                        $rate = 0; // Default value if XML data isn't available
                                        $carVisualLink = './images/default-car.png'; // Fallback image
                                        $currency = 'USD'; // Default currency
                                    }

                                    // Calculate markup safely
                                    $final = is_numeric($rate) ? calculatePercentage($markUp, $rate) : "Not Available";

                                    $currency = (string)$xml->serviceResponse->reservation->quote['currency'];// Placeholder for currency
                                    $vendor = "Euro"; // Example vendor
                                    $vendorLogo = "./images/EuroCar.svg"; // Placeholder for vendor logo
                                    $reference = (string) $vehicle['carCategoryCode']; // Car category code as reference

                                    // Use the carCategoryCode (e.g., CDAR, CFAR, etc.) as the data-size value
                                    $dataSize = (string) $vehicle['carCategoryCode']; 

                                    if (is_numeric($final)) {
                                        $finalTotal = number_format((float)$final, 2);
                                    }
                                    else
                                    {
                                        $finalTotal = (float)$final;
                                    }

                                    // Output the vehicle HTML with the correct data-size
                                    echo '
                                    <div class="res_card res_Euro vehicle-item" data-size="' . $dataSize . '">
                                        <div class="row">
                                            <div class="col-4 d-grid">
                                                <img style="width:20rem;" src="' . $carVisualLink . '" alt="' . $name . '">
                                                <img src="' . $vendorLogo . '" alt="' . $vendor . '">
                                            </div>
                                            <div class="col-4">
                                                <strong>' . $name . '</strong>
                                                <p>OR SIMILAR | ' . strtoupper($transmission) . ' CLASS</p>
                                                <div class="d-flex gap-2 my-3">
                                                    <div class="car_spec">' . ucfirst($transmission) . '</div>
                                                    <div class="car_spec">
                                                        <img src="./images/door-icon.png" alt="">' . $passengers . '
                                                    </div>
                                                    <div class="car_spec">
                                                        <img src="./images/person-icon.png" alt="">' . $passengers . '
                                                    </div>
                                                    <div class="car_spec">
                                                        <img src="./images/S-luggage-icon.png" alt="">' . $luggage . '
                                                    </div>
                                                    <div class="car_spec">
                                                        <img src="./images/snow-icon.png" alt="">
                                                    </div>
                                                </div>
                                                <div class="car_info mb-1">
                                                    <img src="./images/plane-icon.png" alt="">
                                                    <label for=""> On Airport</label>
                                                </div>
                                                <div class="car_info mb-1">
                                                    <img src="./images/km-icon.png" alt="">
                                                    <label for=""> Unlimited Kilometres</label>
                                                </div>
                                                <div class="text-primary" style="">
                                                    + Terms and Conditions
                                                </div>
                                            </div>
                                            <div class="col-4">
                                                <div class="res_pay">
                                                    <div>
                                                        <p>Insurances Package</p>
                                                        <p>Rates starting at ...</p>
                                                    </div>
                                                    <div>
                                                        <p>'."Net : " . $currency . ' ' . number_format($rate,2) . '</p>
                                                        <p>'."Markup : " . '<span id="markup" data-size="' . $dataSize . '"> '. $currency . ' ' . $finalTotal .'</span> '. '</p>
                                                    </div>
                                                </div>
                                                <div class="res_pay">
                                                    <div class="d-flex">
                                                        <a href="book.php?reference=' . $reference . '&vdNo=Euro" class="btn btn-primary">BOOK NOW</a>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>';
                                }
                            } else {
                                echo '';
                            }
                        ?>
                    </div>
                </div>
            </div>
            <!-- results cards Euro mobile-->
            <div class="d-md-none">
                <div>
                    <div id="vehicle-list-Euro-mobile" class="vehicle-list-mobile container">
                        <?php
                        // Loop through each vehicle in the XML
                        if (isset($xmlresEuro->serviceResponse->carCategoryList->carCategory)) {
                            // Loop through each vehicle in the carCategoryList
                            foreach ($xmlresEuro->serviceResponse->carCategoryList->carCategory as $vehicle) {
                                // Extract details as strings or integers as necessary
                                $name = (string) $vehicle['carCategorySample']; // Vehicle sample name
                                $passengers = (string) $vehicle['carCategorySeats']; // Number of seats
                                $luggage = (string) $vehicle['carCategoryBaggageQuantity']; // Baggage capacity
                                $transmission = (string) $vehicle['carCategoryAutomatic'] === 'Y' ? 'Automatic' : 'Manual'; // Transmission type
                                $xmldata = getQuote($vehicle['carCategoryCode'], $infoArray);
                                $xml = simplexml_load_string($xmldata);
                                $rate = (float) $xml->serviceResponse->reservation->quote['basePrice'];
                                $carVisualLink = (string)$xml->serviceResponse->reservation->links->link['value'];
                                $final = calculatePercentage($markUp, $rate); // Calculate markup
                                $currency = (string)$xml->serviceResponse->reservation->quote['currency'];; // Placeholder for currency
                                $vendor = "Euro"; // Example vendor // Placeholder image
                                $vendorLogo = "./images/EuroCar.svg"; // Placeholder for vendor logo
                                $reference = (string) $vehicle['carCategoryCode']; // Car category code as reference

                                
                                // Use the carCategoryCode (e.g., CDAR, CFAR, etc.) as the data-size value
                                $dataSize = (string) $vehicle['carCategoryCode']; 

                                if (is_numeric($final)) {
                                    $finalTotal = number_format((float)$final, 2);
                                }
                                else
                                {
                                    $finalTotal = (float)$final;
                                }

                                // Output the HTML for each vehicle, hide them initially
                                echo '
                                <div class="res_card vehicle-item-mobile" data-size="' . $dataSize . '" style="display: none;">
                                    <div>
                                        <div class="d-grid">
                                            <img style="width:20rem;" src="' . $carVisualLink . '" alt="' . $name . '">
                                            <img src="' . $vendorLogo . '" alt="' . $vendor . '">
                                        </div>
                                        <div>
                                            <strong>' . $name . '</strong>
                                            <p>OR SIMILAR | ' . strtoupper($transmission) . ' CLASS</p>
                                            <div class="d-flex gap-2 my-3">
                                                <div class="car_spec">' . ucfirst($transmission) . '</div>
                                                <div class="car_spec">
                                                    <img src="./images/door-icon.png" alt="">' . $passengers . '
                                                </div>
                                                <div class="car_spec">
                                                    <img src="./images/person-icon.png" alt="">' . $passengers . '
                                                </div>
                                                <div class="car_spec">
                                                    <img src="./images/S-luggage-icon.png" alt="">' . $luggage . '
                                                </div>
                                                <div class="car_spec">
                                                    <img src="./images/snow-icon.png" alt="">
                                                </div>
                                            </div>
                                            <div class="car_info mb-1">
                                                <img src="./images/plane-icon.png" alt="">
                                                <label for=""> On Airport</label>
                                            </div>
                                            <div class="car_info mb-1">
                                                <img src="./images/km-icon.png" alt="">
                                                <label for=""> Unlimited Kilometres</label>
                                            </div>
                                            <div class="text-primary" style="">
                                                + Terms and Conditions
                                            </div>
                                        </div>
                                        <div>
                                            <div class="res_pay">
                                                <div>
                                                    <p>Insurances Package</p>
                                                    <p>Rates starting at ...</p>
                                                </div>
                                                <div>
                                                    <p>'."Net : " . $currency . ' ' . number_format($rate,2) . '</p>
                                                    <p>'."Markup : " . '<span id="markup" data-size="' . $dataSize . '"> '. $currency . ' ' . $finalTotal .'</span> '. '</p>
                                                </div>
                                            </div>
                                            <div class="res_pay">
                                                <div class="d-flex">
                                                    <a href="book.php?reference=' . $reference . '&vdNo=Euro"; class="btn btn-primary">BOOK NOW</a>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>';
                            }
                        } else {
                            echo '';
                        }
                        ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <?php include 'footer.php'; ?>
</body>
<script>
    document.addEventListener("DOMContentLoaded", function() {
        document.querySelector('.loader_div').classList.replace("d-grid", "d-none");
        document.querySelector('.results_div').classList.remove('d-none');
        
        const categoriesEuro = {
            Economy: ['CDAR', 'XZAR'],
            Compact: ['CFAR', 'DFAR', 'CDAR', 'XZAR'],
            Midsize: ['IDAR', 'ICAE', 'ICAR', 'IDAE', 'IFAR', 'XZAR'],
            LuxurySportsCar: ['JDAR', 'LDAR', 'DFFR', 'SFGV', 'FDFE', 'LFAE', 'PZAR'],
            SUV: ['SFAR', 'JFAR', 'SFAH', 'SFBD', 'SFBR', 'SFDR', 'GFAR', 'FFAR', 'UFAD', 'XZAR'],
            StationWagon: ['FWAR', 'GWAR', 'FWAR', 'XZAR'],
            VanPeopleCarrier: ['PVAR', 'PVAV', 'KMLW', 'KPLW', 'XZAR'],
            PassengerVans: ['UFAD', 'XZAR']
        };

        const processedData = [];

        // Gather processed data with data-size and prices from #markup elements
        document.querySelectorAll('#markup').forEach(function(td) {
            if (td.innerText !== "000.00" && td.hasAttribute('data-size')) { 
                const dataSize = td.getAttribute('data-size').split(',');
                const price = td.innerText.trim();
                
                for (let category in categoriesEuro) {
                    if (categoriesEuro[category].some(code => dataSize.includes(code))) {
                        processedData.push({
                            category: category,
                            dataSize: dataSize,
                            price: price
                        });
                        break;
                    }
                }
            }
        });

        console.log("Processed Data:", processedData);

        const uniqueCategoryData = processedData.reduce((accumulator, current) => {
            const existingCategory = accumulator.find(item => item.category === current.category);
            
            if (!existingCategory) {
                accumulator.push(current);
            }
            return accumulator;
        }, []);

        console.log("Unique Category Data:", uniqueCategoryData);

        // Update UI for Europcar row with unique categories
        uniqueCategoryData.forEach((uniqueItem) => {
            document.querySelectorAll('.Euro').forEach((td) => {
                if (td.hasAttribute('data-size')) {
                    const dataSize = td.getAttribute('data-size').split(',');
                    
                    // Check if any code in dataSize matches the unique item codes
                    if (dataSize.some(code => uniqueItem.dataSize.includes(code))) {
                        td.innerHTML = uniqueItem.price;
                    }
                }
            });
        });
    });
    document.querySelectorAll('td.text-center').forEach(function(td) {
        td.addEventListener('click', function() {
            var selectedSizes = td.getAttribute('data-size').split(','); // Get the list of sizes for the selected category
            var vendorRow = td.closest('tr').id; // Get the id of the closest row to identify the vendor (e.g., hertz, dollar, thrifty)

            // Hide all vehicle lists first
            document.querySelectorAll('.vehicle-list').forEach(function(list) {
                list.style.display = 'none'; // Hide all lists
            });

            // Show the specific vendor's vehicle list
            var vendorList = document.getElementById('vehicle-list-' + vendorRow);
            vendorList.style.display = 'block';

            // Hide all vehicles in this list first
            vendorList.querySelectorAll('.vehicle-item').forEach(function(vehicle) {
                vehicle.style.display = 'none';
            });

            // Show the vehicles that match the selected sizes
            var vehiclesShown = false; // Track whether we display any vehicles
            var vehicleCount = 0; // Track how many vehicles are displayed

            selectedSizes.forEach(function(size) {
                var matchingVehicles = vendorList.querySelectorAll('.vehicle-item[data-size="' + size + '"]');
                matchingVehicles.forEach(function(vehicle) {
                    vehicle.style.display = 'block';
                    vehiclesShown = true;
                    vehicleCount++; // Increment the count for each shown vehicle
                });
            });

            // If no vehicles are shown, handle the empty case
            if (!vehiclesShown) {
                alert('No matching vehicles found.');
            }

            // Update the results count dynamically
            if (vehicleCount > 0) {
                document.getElementById('results-count').innerText = 'SHOWING ' + vehicleCount + ' RESULTS';
                document.getElementById('results-count-container').style.display = 'block'; // Show the results count section
            } else {
                document.getElementById('results-count-container').style.display = 'none'; // Hide the results count if no vehicles are found
            }
        });
    });
    document.querySelectorAll('td.mobile').forEach(function(td) {
        td.addEventListener('click', function() {
            var selectedSizes = td.getAttribute('data-size').split(','); // Get the list of sizes for the selected category
            var vendorRow = td.closest('tr').id; // Get the id of the closest row to identify the vendor (e.g., hertz, dollar, thrifty)

            // Hide all vehicle lists first
            document.querySelectorAll('.vehicle-list-mobile').forEach(function(list) {
                list.style.display = 'none'; // Hide all lists
            });

            // Show the specific vendor's vehicle list
            var vendorList = document.getElementById('vehicle-list-' + vendorRow  + '-mobile');
            vendorList.style.display = 'block';

            // Hide all vehicles in this list first
            vendorList.querySelectorAll('.vehicle-item-mobile').forEach(function(vehicle) {
                vehicle.style.display = 'none';
            });

            // Show the vehicles that match the selected sizes
            var vehiclesShown = false; // Track whether we display any vehicles
            var vehicleCount = 0; // Track how many vehicles are displayed

            selectedSizes.forEach(function(size) {
                var matchingVehicles = vendorList.querySelectorAll('.vehicle-item-mobile[data-size="' + size + '"]');
                matchingVehicles.forEach(function(vehicle) {
                    vehicle.style.display = 'block';
                    vehiclesShown = true;
                    vehicleCount++; // Increment the count for each shown vehicle
                });
            });

            // If no vehicles are shown, handle the empty case
            if (!vehiclesShown) {
                alert('No matching vehicles found.');
            }

            // Update the results count dynamically
            if (vehicleCount > 0) {
                document.getElementById('results-count-mobile').innerText = 'SHOWING ' + vehicleCount + ' RESULTS';
                document.getElementById('results-count-container-mobile').style.display = 'block'; // Show the results count section
            } else {
                document.getElementById('results-count-container-mobile').style.display = 'none'; // Hide the results count if no vehicles are found
            }
        });
    });

</script>

</html>