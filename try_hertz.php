<?php
session_start();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js" integrity="sha384-YvpcrYf0tY3lHB60NNkmXc5s9fDVZLESaAA55NDzOxhy9GkcIdslK1eN7N6jIeHz" crossorigin="anonymous"></script>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet" integrity="sha384-QWTKZyjpPEjISv5WaRU9OFeRpok6YctnYmDr5pNlyT2bRjXh0JMhjY6hW+ALEwIH" crossorigin="anonymous">
    <title>Document</title>
</head>
<body>
    <style>
        #showLocation{
            background-color: #d4d4d4;
            height: 27rem;
        }
        #dropoffLocationName_div,#pickupLocationName_div{
            cursor: pointer;
            overflow-y: scroll;
            height: 22rem;
        }
        #locationName{
            padding: 10px;
            border: 1px solid #d4d4d4;
        }
        .selected{
            background-color: #bfe6e6;
            color: white;
            padding: 1rem;
        }
    </style>
<?php
include 'dbconn.php';
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
$dataArray = $_SESSION['dataarray'];
$cityName = "MEL";
$sql = "SELECT * FROM `filter_locations_hertz` WHERE groupName Like 'MEL%'";
$result = $conn->query($sql);

// Fetch all rows into an array
$locations = $result->fetch_all(MYSQLI_ASSOC);
?>
<div class="row" id="showLocation">
    <div class="col-4">
        <div>
            <p class="text-center mt-3">Pick Up Location</p>
        </div>
        <div id="pickupLocationName_div">
            <?php foreach ($locations as $row): ?>
                <p id="locationName" dataHertz="<?php echo $row['citycode']; ?>"><?php echo $row['cityaddress']; ?></p>
            <?php endforeach; ?>
        </div>
    </div>
    <div class="col-4">
        <div>
            <p class="text-center mt-3">Drop Off Location</p>
        </div>
        <div id="dropoffLocationName_div">
            <?php foreach ($locations as $row1): ?>
                <p id="locationName" dataHertz="<?php echo $row1['citycode']; ?>"><?php echo $row1['cityaddress']; ?></p>
            <?php endforeach; ?>
        </div>
    </div>
    <div class="col-4">
        <div>
            <p class="text-center mt-3">Payment Information</p>
        </div>
        <div class="res_pay">
            <div>
                <p>Insurances Package</p>
                <p>Rates starting at ...</p>
            </div>
            <div>

            </div>
        </div>
        <div class="res_pay">
            <div class="d-flex">
                <a href="book.php?reference=' . $reference . '&vdNo=Euro" class="btn btn-primary">BOOK NOW</a>
            </div>
        </div>
    </div>
</div>

<script>
    let pickupSelected = false;
    let dropoffSelected = false;
    let pickupData = {};
    let dropoffData = {};
    let carCategory = "CDAR";
    let infoObject = {
        pickUpDateEuro: <?php echo json_encode(formatDateAndTime($dataArray['pickUpDateTime'])[0]); ?>,
        pickUpTimeEuro: <?php echo json_encode(formatDateAndTime($dataArray['pickUpDateTime'])[1]); ?>,
        dropOffDateEuro: <?php echo json_encode(formatDateAndTime($dataArray['dropOffDateTime'])[0]); ?>,
        dropOffTimeEuro: <?php echo json_encode(formatDateAndTime($dataArray['dropOffDateTime'])[1]); ?>
    };
    document.getElementById('pickupLocationName_div').addEventListener('click', function(event) {
        if (event.target && event.target.id === "locationName") {
            pickupData = {
                hertz: event.target.getAttribute('dataHertz'),
                euro: event.target.getAttribute('dataEuro')
            };
            console.log(`Pickup location: Hertz - ${pickupData.hertz}, Euro - ${pickupData.euro}`);
            if (pickupData.hertz || pickupData.euro) {
                let prevSelected = document.querySelector('#pickupLocationName_div .selected');
                if (prevSelected) {
                    prevSelected.classList.remove('selected');
                }
                event.target.classList.add('selected');
                pickupSelected = true;
            } else {
                console.log('Error: Missing data attributes');
            }
        }
        if (pickupSelected && dropoffSelected) {
            callGetQuote();
        }
    });

    document.getElementById('dropoffLocationName_div').addEventListener('click', function(event) {
        if (event.target && event.target.id === "locationName") {
            dropoffData = {
                hertz: event.target.getAttribute('dataHertz'),
                euro: event.target.getAttribute('dataEuro')
            };
            console.log(`Dropoff location: Hertz - ${dropoffData.hertz}, Euro - ${dropoffData.euro}`);
            if (dropoffData.hertz || dropoffData.euro) {
                let prevSelected = document.querySelector('#dropoffLocationName_div .selected');
                if (prevSelected) {
                    prevSelected.classList.remove('selected');
                }
                event.target.classList.add('selected');
                dropoffSelected = true;
            } else {
                console.log('Error: Missing data attributes');
            }
        }
        if (pickupSelected && dropoffSelected) {
            callGetQuote();
        }
    });

    function callGetQuote() {
        let data = {
            carCategory: carCategory,
            pickup: pickupData,
            dropoff: dropoffData,
            pickUpTime: infoObject.pickUpTimeEuro,
            dropOffTime: infoObject.dropOffTimeEuro,
            pickUpDate: infoObject.pickUpDateEuro,
            dropOffDate: infoObject.dropOffDateEuro
        };
        fetch('getQuote.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json'
            },
            body: JSON.stringify(data)
        })
        .then(response => response.json())
        .then(data => {
            console.log(data);

            if (data.quote && data.quote.rate && data.quote.currency) {
                const rate = data.quote.rate;
                const currency = data.quote.currency;

                const paymentInfoDiv = document.querySelector('.res_pay');
                if (paymentInfoDiv) {
                    paymentInfoDiv.innerHTML += `
                        <div>
                            <p>Rental Rate: ${rate} ${currency}</p>
                        </div>
                    `;
                }
            } else {
                console.error('Quote details are missing in the response');
            }
        })
        .catch(error => console.log('Error:', error));
    }

</script>
</body>
</html>

