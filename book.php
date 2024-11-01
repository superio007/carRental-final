<?php
session_start();
$AccessId = $_SESSION['AccessId'];
$Email = null;
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

// Load Composer's autoloader
require 'vendor/autoload.php';
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
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
</head>
<?php
include 'dbconn.php';
if (!isset($_SESSION['jwtToken'])) {
    echo "<script>window.location.href='login.php';</script>";
}
if (isset($_GET['vdNo'])) {
    $vdNo = $_GET['vdNo'];
}
if (isset($_GET['reference'])) {
    $reference = $_GET['reference'];
    $carCategoryCode = $_GET['reference'];
}
if ($vdNo == "ZE") {
    $res = $_SESSION['responseZE'];
} elseif ($vdNo == "ZT") {
    $res = $_SESSION['responseZT'];
} elseif ($vdNo == "Euro") {
    $responseEuro = $_SESSION['responseEuro'];
    $vendorLogo = "images\EuroCar.svg";
} else {
    $res = $_SESSION['responseZR'];
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
function calculatePercentage($part, $total)
{
    $og = $total;
    if ($total == 0) {
        return "Total cannot be zero"; // To avoid division by zero error
    }
    $percentage = ($total * $part) / 100;
    return $percentage + $og;
}

if ($vdNo == "Euro") {
    
    $xmlres = new SimpleXMLElement($responseEuro);
    $requiredeuroBooking = $_SESSION['requiredeuroBooking'];
    $pickup = $requiredeuroBooking['pickup'];
    $dropOff = $requiredeuroBooking['dropOff'];
    $pickDate = $requiredeuroBooking['pickDate'];
    $pickTime = $requiredeuroBooking['pickTime'];
    $dropDate = $requiredeuroBooking['dropDate'];
    $dropTime = $requiredeuroBooking['dropTime'];
    function xmlToArray($xmlObject)
    {
        return json_decode(json_encode($xmlObject), true);
    }
    function filterCarCategoryByCode($xmlData, $carCategoryCode)
    {
        // Convert SimpleXMLElement to an array if necessary
        $carCategories = $xmlData->serviceResponse->carCategoryList->carCategory;

        // Initialize an empty array to hold the filtered car category
        $filteredCategory = [];

        // Loop through each carCategory in the list
        foreach ($carCategories as $carCategory) {
            // Check if the carCategoryCode matches the passed parameter
            if ((string)$carCategory['carCategoryCode'] === $carCategoryCode) {
                // Add the matching car category to the filtered array
                $filteredCategory[] = $carCategory;
            }
        }

        // Return the filtered category array
        return $filteredCategory;
    }
    function convertTo24HourFormat($time12Hour)
    {
        // Convert the time from 12-hour format to 24-hour format
        $time24Hour = date("H:i", strtotime($time12Hour));

        // Remove the colon to get the format as 1545 instead of 15:45
        return str_replace(':', '', $time24Hour);
    }
    function extractedData($xmlData,$markUp)

    {
        foreach ($xmlData as $data) {
            $name = (string)$data['carCategorySample'];
            $transmission = ($data['carCategoryAutomatic'] == 'Y') ? "Automatic" : "Manual";
            $passengers = (string)$data['carCategorySeats'];
            $luggage = (string)$data['carCategoryBaggageQuantity'];
            $currency = (string)$data['carCategoryPowerHP'];
            // Assuming these variables will be used in the HTML below
            return compact('name', 'transmission', 'passengers', 'luggage', 'currency',);
        }
    }
    $filteredData = filterCarCategoryByCode($xmlres, $carCategoryCode);
    if (!empty($filteredData)) {
        $carDetails = extractedData($filteredData, $markUp);
        $name = $carDetails['name'];
        $transmission = $carDetails['transmission'];
        $passengers = $carDetails['passengers'];
        $luggage = $carDetails['luggage'];
        $currency = $carDetails['currency'];
        $final = number_format(calculatePercentage($markUp, $currency), 2);
        $EuroPrice = $final;
    } else {
        $name = $transmission = $passengers = $luggage = $currency = "N/A";
    }

    if ($_SERVER['REQUEST_METHOD'] == 'POST') {
        $first_name = $_POST['first_name'] ?? '';
        $email = $_POST['email'] ?? '';
        $Email = $email;
        $last_name = $_POST['last_name'] ?? '';
        $foramtdropTime = convertTo24HourFormat($dropTime);
        $foramtpickTime = convertTo24HourFormat($pickTime);
        // Define the XML request payload
        $xmlRequest = '<?xml version="1.0" encoding="UTF-8"?>
        <message>
        <serviceRequest serviceCode="bookReservation">
            <serviceParameters>
            <reservation carCategory="' . $carCategoryCode . '" rateId="RATE_ID">
                <checkout stationID="' . $pickup . '" date="' . $pickDate . '" time="' . $foramtpickTime . '"/>
                <checkin stationID="' . $dropOff . '" date="' . $dropDate . '" time="' . $foramtdropTime . '"/>
            </reservation>
            <driver countryOfResidence="XX" firstName="Kiran" lastName="Dhoke"/>
            </serviceParameters>
        </serviceRequest>
        </message>
        ';

        // Prepare the cURL request
        $ch = curl_init();

        // Postman sends it as `x-www-form-urlencoded`, so we mimic that by wrapping the XML in `XML-Request`
        $postFields = http_build_query([
            'XML-Request' => $xmlRequest,
            'callerCode' => '1132097',
            'password' => '02092024'
        ]);

        // Set cURL options
        curl_setopt($ch, CURLOPT_URL, 'https://applications-ptn.europcar.com/xrs/resxml');
        curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $postFields);  // URL encode the fields
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Content-Type: application/x-www-form-urlencoded',
            'Accept: text/xml'
        ]);

        // Execute the cURL request
        $response = curl_exec($ch);
        echo "<script>alert($response)</script>";  // Check the full response from the API


        // Check for cURL errors
        if (curl_errno($ch)) {
            echo 'cURL error: ' . curl_error($ch);
        } else {
            echo "<pre>";
            echo htmlspecialchars($response);  // Escape XML characters for display
            echo "</pre>";
        }
    }
    $filteredData = filterCarCategoryByCode($xmlres, $carCategoryCode);
    extractedData($filteredData,$markUp);
    // Convert the SimpleXMLElement to array
    $xmlArrayBookingInfo = xmlToArray($requiredeuroBooking);
    $xmlArrayBookingDetails = xmlToArray($responseEuro);
} else {
    // $res = $_SESSION['results'];
    $dataarray = $_SESSION['dataarray'];
    $xmlres = new SimpleXMLElement($res);

    // Function to convert SimpleXMLElement to array
    function xmlToArray($xmlObject)
    {
        return json_decode(json_encode($xmlObject), true);
    }

    // Convert the SimpleXMLElement to array
    $xmlArray = xmlToArray($xmlres);

    function filterResultsByReference($results, $referenceType, $referenceID)
    {
        $filteredResults = [];

        // Traverse through the array to locate the vehicles and filter by Reference Type and ID
        foreach ($results['VehAvailRSCore']['VehVendorAvails']['VehVendorAvail']['VehAvails']['VehAvail'] as $vehAvail) {
            if (isset($vehAvail['VehAvailCore']['Reference']['@attributes'])) {
                $ref = $vehAvail['VehAvailCore']['Reference']['@attributes'];
                if ($ref['Type'] == $referenceType && $ref['ID'] == $referenceID) {
                    $filteredResults[] = $vehAvail;
                }
            }
        }

        return $filteredResults;
    }
    // Example usage:
    $referenceType = "16";
    $referenceID = $reference;  // Use the ID you want to filter by
    $filteredResults = filterResultsByReference($xmlArray, $referenceType, $referenceID);
    $code = $filteredResults[0]['VehAvailCore']['Vehicle']['@attributes']['Code'];
    // echo $code;
    if (!empty($filteredResults)) {
        $vehicle = $filteredResults[0]; // Assuming we want the first matching result

        // Vehicle details
        $name = $vehicle['VehAvailCore']['Vehicle']['VehMakeModel']['@attributes']['Name'];
        $transmission = $vehicle['VehAvailCore']['Vehicle']['@attributes']['TransmissionType'];
        $passengers = $vehicle['VehAvailCore']['Vehicle']['@attributes']['PassengerQuantity'];
        $luggage = $vehicle['VehAvailCore']['Vehicle']['@attributes']['BaggageQuantity'];
        $rate = $vehicle['VehAvailCore']['TotalCharge']['@attributes']['RateTotalAmount'];
        $final = number_format(calculatePercentage($markUp, $rate), 2);
        $currency = $vehicle['VehAvailCore']['TotalCharge']['@attributes']['CurrencyCode'];
        $image = $vehicle['VehAvailCore']['Vehicle']['PictureURL'];
        if ($vdNo == "ZE") {
            $vendorLogo = "images\hertz.png";
        } elseif ($vdNo == "ZT") {
            $vendorLogo = "./images/thrifty.png";
        } else {
            $vendorLogo = "images\DOLLARRet.png";
        }
    } else {
        echo "No matching vehicle found.";
        exit;
    }
    
    if ($_SERVER["REQUEST_METHOD"] == "POST") {
        // Capture driver information
        $first_name = $_POST['first_name'] ?? '';
        $last_name = $_POST['last_name'] ?? '';
        $email = $_POST['email'] ?? '';
        $Email = $email;
        $mobile_number = $_POST['phone'] ?? '';
        $mobile_country_code = $_POST['mobile_country_code'];

        // Capture billing information
        $stateCode = $_POST['State'] ?? '';
        $country = $_POST['country'] ?? '';
        $address = $_POST['address'] ?? '';
        $postal_code = $_POST['postal_code'] ?? '';
        $city = $_POST['city'] ?? '';
        $pickupLocation = $dataarray['pickLocation'];
        $returnLocation = $dataarray['dropLocation'] ?? $dataarray['pickLocation'];
        $pickupDateTime =  $dataarray['pickUpDateTime'];
        $returnDateTime = $dataarray['dropOffDateTime'];
        $voucher = "12345678";

        // Capture checkbox values
        $sign_up = isset($_POST['sign_up']) ? 'Yes' : 'No';
        $terms = isset($_POST['terms']) ? 'Accepted' : 'Not Accepted';

        $usersInfo = [
            'fName' => $first_name,
            'lName' => $last_name,
            'email' => $email,
            'countryCode' => $mobile_country_code,
            'mobileNo' => $mobile_number,
            'address' => $address,
            'city' => $city,
            'state' => $stateCode,
            'pickDate' => $pickupDateTime,
            'dropDate' => $returnDateTime,
            'pick' => $pickupLocation,
            'drop' => $returnLocation,
        ];
        $_SESSION['userInfo'] = $usersInfo;

        // Validation and processing logic here
        if (empty($first_name) && empty($last_name) && empty($email) && empty($age) && empty($terms)) {
            echo "Please fill all required fields.";
        } else {
            $xml = "
                    <OTA_VehResRQ xmlns=\"http://www.opentravel.org/OTA/2003/05\" Version=\"1.008\">
                        <POS>
                            <Source ISOCountry=\"IN\" AgentDutyCode=\"T17R16L5D11\">
                                <RequestorID Type=\"4\" ID=\"X975\">
                                    <CompanyName Code=\"CP\" CodeContext=\"4PH5\"/>
                                </RequestorID>
                            </Source>
                        </POS>
                        <VehResRQCore>
                            <VehRentalCore PickUpDateTime=\"$pickupDateTime\" ReturnDateTime=\"$returnDateTime\">
                                <PickUpLocation LocationCode=\"$pickupLocation\" CodeContext=\"IATA\"/>
                                <ReturnLocation LocationCode=\"$returnLocation\" CodeContext=\"IATA\"/>
                            </VehRentalCore>
                            <Customer>
                                <Primary>
                                    <PersonName>
                                        <GivenName>$first_name</GivenName>
                                        <Surname>$last_name</Surname>
                                    </PersonName>
                                    <Email>$email</Email>
                                    <Address>
                                        <AddressLine>$address</AddressLine>
                                        <CityName>$city</CityName>
                                        <StateProv StateCode=\"$stateCode\"/>
                                        <CountryName Code=\"$mobile_country_code\"/>
                                    </Address>
                                </Primary>
                            </Customer>
                            <VendorPref Code=\"$vdNo\"/>
                            <VehPref Code=\"$code\" CodeContext=\"SIPP\"/>
                            <RentalPaymentPref>
                                <Voucher Identifier=\"$voucher\" IdentifierContext=\"TestVoucher\"/>
                            </RentalPaymentPref>
                        </VehResRQCore>
                    </OTA_VehResRQ>";
            // Initialize cURL session
            $ch = curl_init();
            // var_dump($xml);

            // Set cURL options
            curl_setopt($ch, CURLOPT_URL, "https://vv.xqual.hertz.com/DirectLinkWEB/handlers/DirectLinkHandler?id=ota2007a");
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_HTTPHEADER, [
                'Content-Type: application/xml',
                'Content-Length: ' . strlen($xml)
            ]);
            curl_setopt($ch, CURLOPT_POSTFIELDS, $xml);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 0);
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, 0);


            $response = curl_exec($ch);
        }
    }
}
if(isset($response)){
    if($response === false){
        $error = curl_error($ch);    
        curl_close($ch);    
        die('cURL Error: ' . $error);   
    }else{
        if($vdNo == "Euro"){
            $xmlres = new SimpleXMLElement($response);
            $email = $Email;
            $final = $EuroPrice;
            $givenName = $xmlres->serviceResponse->driver['firstName'];
            $surname = $xmlres->serviceResponse->driver['lastName'];
            $confID = $xmlres->serviceResponse->reservation['resNumber'];
            $carName = $xmlres->serviceResponse->reservation['carCategory'];
            $confirmed = true;
        }else{
            $xmlres = new SimpleXMLElement($response);
            // If <Success> tag is present, print a success message
            echo "Success! The vehicle reservation was processed successfully.";
            // Retrieve and print the name
            $givenName = $xmlres->VehResRSCore->VehReservation->Customer->Primary->PersonName->GivenName;
            $surname = $xmlres->VehResRSCore->VehReservation->Customer->Primary->PersonName->Surname;
    
            // Retrieve and print the ConfID
            $confID = $xmlres->VehResRSCore->VehReservation->VehSegmentCore->ConfID['ID'];
    
            // Retrieve and print the car name
            $carName = $xmlres->VehResRSCore->VehReservation->VehSegmentCore->Vehicle->VehMakeModel['Name'];
            $confirmed = true;
        }
        if ($confirmed) {
            
            $sql = "INSERT INTO `bookings`(`Id`, `FirstName`, `LastName`, `ConfirmedId`, `CarName`, `AccessId`) VALUES ('','$givenName','$surname','$confID','$carName','$AccessId')";
    
            if ($conn->query($sql) === TRUE) {
                echo "<script>console.log(\"New record created successfully\")</script>";
            } else {
                echo "<script>console.log(\"Error: \" . $sql . \"<br>\" . $conn->error\")</script>";
            }
    
            $conn->close();
            // Create an instance of PHPMailer
            $mail = new PHPMailer(true);
    
            try {
                // Server settings
                $mail->isSMTP();                                 // Set mailer to use SMTP
                $mail->Host       = 'smtp.gmail.com';          // Specify main and backup SMTP servers
                $mail->SMTPAuth   = true;                        // Enable SMTP authentication
                $mail->Username   = 'dhokekiran98@gmail.com';    // SMTP username
                $mail->Password   = 'fzepmsgxliiticxs';       // SMTP password
                $mail->SMTPSecure = 'tls';                        // Enable TLS encryption, `ssl` also accepted
                $mail->Port       = 587;                         // TCP port to connect to
    
                // Recipients
                $mail->setFrom("dhokekiran98@gmail.com", "Hertz_Support");
                $mail->addAddress($email, $first_name . " " .  $last_name);
    
                // Content
                $mail->isHTML(true);                            // Set email format to HTML
                $mail->Subject = "Confirmation from hertz : $confID";
                $mail->Body    = "Passengers given name : $givenName <br> Passengers surname : $surname <br> Car booked : $carName <br> Check details : 
                    <a href='detail.php?confId=$confID&surname=$surname' 
                    style='background-color: #ffd207; color:#0d7fa6; padding: 5px; text-decoration: none; border-radius: 5px;'>Click Here</a>
                    ";
                $mail->AltBody = '';
    
                if ($mail->send()) {
                    echo "<script>window.location.href='sucess.php?cnfNo=$confID&lName=$surname&rate=$final&vdNo=$vdNo'</script>";
                }
                // echo 'Message has been sent';
            } catch (Exception $e) {
                echo "Message could not be sent. Mailer Error: {$mail->ErrorInfo}";
            }
        } else {
            //if not sucess
            echo "<script>
                        alert('Vehicle is not available, please try again!');
                        window.location.href = 'index.php';
                    </script>";
            unset($_SESSION['dataarray']);
            unset($_SESSION['responseZR']);
            unset($_SESSION['responseZT']);
            unset($_SESSION['responseZE']);
            unset($_SESSION['userInfo']);
        }
        // Close cURL session
        curl_close($ch);
    }
}

// if ($response === false) {
//     $error = curl_error($ch);
//     curl_close($ch);
//     die('cURL Error: ' . $error);
// } else {
    
//     if($vdNo == "Euro"){

//         $confirmed = true;
//     }else{
//         $xmlres = new SimpleXMLElement($response);
//         // If <Success> tag is present, print a success message
//         echo "Success! The vehicle reservation was processed successfully.";
//         // Retrieve and print the name
//         $givenName = $xmlres->VehResRSCore->VehReservation->Customer->Primary->PersonName->GivenName;
//         $surname = $xmlres->VehResRSCore->VehReservation->Customer->Primary->PersonName->Surname;

//         // Retrieve and print the ConfID
//         $confID = $xmlres->VehResRSCore->VehReservation->VehSegmentCore->ConfID['ID'];

//         // Retrieve and print the car name
//         $carName = $xmlres->VehResRSCore->VehReservation->VehSegmentCore->Vehicle->VehMakeModel['Name'];
//         $confirmed = true;
//     }

//     // Check if the <Success> tag exists
//     if ($confirmed) {
        
//         $sql = "INSERT INTO `bookings`(`Id`, `FirstName`, `LastName`, `ConfirmedId`, `CarName`, `AccessId`) VALUES ('','$givenName','$surname','$confID','$carName','$AccessId')";

//         if ($conn->query($sql) === TRUE) {
//             echo "<script>console.log(\"New record created successfully\")</script>";
//         } else {
//             echo "<script>console.log(\"Error: \" . $sql . \"<br>\" . $conn->error\")</script>";
//         }

//         $conn->close();
//         // Create an instance of PHPMailer
//         $mail = new PHPMailer(true);

//         try {
//             // Server settings
//             $mail->isSMTP();                                 // Set mailer to use SMTP
//             $mail->Host       = 'smtp.gmail.com';          // Specify main and backup SMTP servers
//             $mail->SMTPAuth   = true;                        // Enable SMTP authentication
//             $mail->Username   = 'dhokekiran98@gmail.com';    // SMTP username
//             $mail->Password   = 'fzepmsgxliiticxs';       // SMTP password
//             $mail->SMTPSecure = 'tls';                        // Enable TLS encryption, `ssl` also accepted
//             $mail->Port       = 587;                         // TCP port to connect to

//             // Recipients
//             $mail->setFrom("dhokekiran98@gmail.com", "Hertz_Support");
//             $mail->addAddress($email, $first_name . " " .  $last_name);

//             // Content
//             $mail->isHTML(true);                            // Set email format to HTML
//             $mail->Subject = "Confirmation from hertz : $confID";
//             $mail->Body    = "Passengers given name : $givenName <br> Passengers surname : $surname <br> Car booked : $carName <br> Check details : 
//                 <a href='detail.php?confId=$confID&surname=$surname' 
//                 style='background-color: #ffd207; color:#0d7fa6; padding: 5px; text-decoration: none; border-radius: 5px;'>Click Here</a>
//                 ";
//             $mail->AltBody = '';

//             if ($mail->send()) {
//                 echo "<script>window.location.href='sucess.php?cnfNo=$confID&lName=$surname&rate=$final'</script>";
//             }
//             // echo 'Message has been sent';
//         } catch (Exception $e) {
//             echo "Message could not be sent. Mailer Error: {$mail->ErrorInfo}";
//         }
//     } else {
//         //if not sucess
//         echo "<script>
//                     alert('Vehicle is not available, please try again!');
//                     window.location.href = 'index.php';
//                 </script>";
//         unset($_SESSION['dataarray']);
//         unset($_SESSION['responseZR']);
//         unset($_SESSION['responseZT']);
//         unset($_SESSION['responseZE']);
//         unset($_SESSION['userInfo']);
//     }
//     // Close cURL session
//     curl_close($ch);
// }

?>

<body>
    <?php include 'header.php' ?>
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
    <div id="book">
        <div class="container d-flex gap-md-5 my-5 flex-column flex-md-row">
            <div class="col-md-8 order-2 order-md-1 back_div">
                <p class="text-white py-1 px-3" style="background-color: #48ab53;">
                    BOOK TODAY BEFORE RATE CHANGE!
                </p>
                <div class="p-3">
                    <h1>Book Your Car Rental</h1>
                    <div class="d-flex justify-content-between my-2">
                        <h5>Driver</h5>
                        <p><i class="fa fa-asterisk fa-xs" style="color: red;"></i> Required Field</p>
                    </div>
                    <form action="" method="post">
                        <div>
                            <div class="d-md-flex my-2 input_div">
                                <label class="col-md-4">Driver's First Name</label>
                                <span class="col-md-8 ast"><input required type="text" name="first_name" style="width: 18rem;padding: 0.5rem;"></span>
                            </div>
                            <div class="d-md-flex my-2 input_div">
                                <label class="col-md-4">Driver's Last Name</label>
                                <span class="col-md-8 ast"><input required type="text" name="last_name" style="width: 18rem;padding: 0.5rem;"></span>
                            </div>
                            <div class="d-md-flex my-2 input_div">
                                <label class="col-md-4">Email Address</label>
                                <span class="col-md-8 ast"><input required type="email" name="email" style="width: 18rem;padding: 0.5rem;"></span>
                            </div>
                            <div class="d-md-flex my-2 input_div">
                                <label for="countryCode" class="col-md-4">Mobile Country Code:</label>
                                <select id="countryCode" class="col-md-8 ast" style="width: 18rem;padding: 0.5rem;margin-left:0.8rem;" name="mobile_country_code">
                                    <option value="IN" selected>India (+91)</option>
                                    <option value="US">United States (+1)</option>
                                    <option value="AU">Australia (+61)</option>
                                </select>
                            </div>
                            <div class="d-md-flex my-2 input_div">
                                <label class="col-md-4">Your Telephone Number</label>
                                <span class="col-md-8 ast"><input required type="text" name="phone" style="padding: 0.5rem;"></span>
                            </div>
                        </div>
                        <div>
                            <div class="my-3">
                                <h5>Billing Information</h5>
                            </div>
                            <div>
                                <div class="d-md-flex my-2 input_div">
                                    <label class="col-md-4">Country of Residence</label>
                                    <span class="col-md-8 ast"><input required type="text" name="country" style="width: 18rem;padding: 0.5rem;"></span>
                                </div>
                                <div class="d-md-flex my-2 input_div">
                                    <label class="col-md-4">Address</label>
                                    <span class="col-md-8 ast"><input required type="text" name="address" style="width: 18rem;padding: 0.5rem;"></span>
                                </div>
                                <div class="d-md-flex my-2 input_div">
                                    <label class="col-md-4">State</label>
                                    <span class="col-md- ast"><input required type="text" name="State" style="width: 18rem;padding: 0.5rem;"></span>
                                </div>
                                <div class="d-md-flex my-2 input_div">
                                    <label class="col-md-4">Postal Code</label>
                                    <span class="col-md-8 ast"><input required type="text" name="postal_code" style="padding: 0.5rem;"></span>
                                </div>
                                <div class="d-md-flex my-2 input_div">
                                    <label class="col-md-4">City</label>
                                    <span class="col-md-8 ast"><input required type="text" name="city" style="width: 18rem;padding: 0.5rem;"></span>
                                </div>
                            </div>
                        </div>
                        <div>
                            <div class="d-flex gap-3">
                                <input type="checkbox" name="sign_up" id="sign">
                                <label for="sign">Yes, I will sign up for exclusive discounts, sales, and a few surprises.</label>
                            </div>
                            <div class="d-flex gap-3">
                                <input type="checkbox" name="terms" id="terms" required>
                                <label for="terms">Click here to confirm you've read and agreed to the <span class="text-primary">Terms, Conditions & Local Fees</span></label>
                            </div>
                        </div>
                        <div class="my-3 d-flex justify-content-end">
                            <button type="submit" class="btn btn-primary">PROCEED TO PAYMENT <i class="fa-solid fa-angle-right fa-lg" style="color: #ffffff;"></i></button>
                        </div>
                    </form>
                </div>
            </div>
            <div class="col-md-4 order-1 order-md-2 back_div">
                <div class="d-flex justify-content-between p-3 align-content-center">
                    <h4>Reservation Information</h4>
                    <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target=".bd-example-modal-lg">MODIFY</button>
                </div>
                <div class="d-grid justify-content-center">
                    <img src="https://images.hertz.com/vehicles/220x128/<?php echo $image; ?>" alt="<?php echo $name; ?>">
                    <div class="d-flex justify-content-end">
                        <img src="<?php echo $vendorLogo; ?>" alt="">
                    </div>
                </div>
                <div class="mt-3 p-3">
                    <h3><strong><?php echo $name; ?></strong></h3>
                    <p>OR SIMILAR | <?php echo strtoupper($transmission); ?> CLASS</p>
                    <div class="d-flex gap-2 my-3">
                        <div class="car_spec">
                            <?php echo ucfirst($transmission); ?>
                        </div>
                        <div class="car_spec">
                            <img src="./images/door-icon.png" alt="">
                            <?php echo $passengers; ?>
                        </div>
                        <div class="car_spec">
                            <img src="./images/person-icon.png" alt="">
                            <?php echo $passengers; ?>
                        </div>
                        <div class="car_spec">
                            <img src="./images/S-luggage-icon.png" alt="">
                            <?php echo $luggage; ?>
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
                    <div class="text-primary">
                        <p>+ Terms and Conditions</p>
                    </div>
                </div>
                <div class="p-3">
                    <div class="d-flex justify-content-between ">
                        <p style="font-size:x-large; font-weight: 700;">Total Rental</p>
                        <?php if ($vdNo == "Euro"): ?>
                            <p style="font-size:x-large; font-weight: 700;"><?php echo number_format($final, 2); ?></p>
                        <?php else: ?>
                            <p style="font-size:x-large; font-weight: 700;"><?php echo $currency; ?><?php echo number_format($final, 2); ?></p>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <?php include 'footer.php' ?>
</body>

</html>