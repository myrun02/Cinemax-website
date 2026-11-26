<?php
session_start();
require 'db.php';

$bookingScript = "";
$errorMsg = "";

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $customerEmail = trim($_POST['customer_email'] ?? '');
    if (empty($customerEmail) && isset($_SESSION['user']['email'])) {
        $customerEmail = $_SESSION['user']['email'];
    }
    if (empty($customerEmail)) {
        $customerEmail = 'Guest';
    }

    $movieTitle    = trim($_POST['movie_title'] ?? '');
    $showDate      = trim($_POST['show_date'] ?? '');
    $showTime      = trim($_POST['show_time'] ?? '');
    $seats         = trim($_POST['seats'] ?? '');
    $totalAmount   = floatval($_POST['total_amount'] ?? 0);
    $paymentMethod = trim($_POST['payment'] ?? '');

    if (!$movieTitle || !$seats || !$paymentMethod) {
        $errorMsg = "Please select seats and a payment method.";
    } else {
        $stmt = $conn->prepare("INSERT INTO bookings (customer_email, movie_title, show_date, show_time, seats, total_amount, payment_method) VALUES (?, ?, ?, ?, ?, ?, ?)");
        $stmt->bind_param("sssssds", $customerEmail, $movieTitle, $showDate, $showTime, $seats, $totalAmount, $paymentMethod);

        if ($stmt->execute()) {
            $safeMovie   = addslashes($movieTitle);
            $safeSeats   = addslashes($seats);
            $safeMethod  = addslashes($paymentMethod);
            $formattedRM = number_format($totalAmount, 2);

            $safeDateTime = addslashes(trim($showDate . ' ' . $showTime));
            $bookingScript = "<script>
                const history = JSON.parse(localStorage.getItem('cinemax_history') || '[]');
                history.unshift({
                    type: 'Movie Ticket ({$safeMethod})',
                    date: new Date().toLocaleString(),
                    details: '🎬 {$safeMovie} ({$safeDateTime}) | Seats: {$safeSeats}',
                    total: '{$formattedRM}'
                });
                localStorage.setItem('cinemax_history', JSON.stringify(history));
                alert('Payment Success via {$safeMethod}!\\nMovie: {$safeMovie}\\nSeats: {$safeSeats}\\nTotal: RM {$formattedRM}');
                localStorage.removeItem('selectedSeats');
                localStorage.removeItem('totalAmount');
                window.location.href = 'index.html';
            </script>";
        } else {
            $errorMsg = "Database error: " . $conn->error;
        }
        $stmt->close();
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Booking & Payment - CINEMAX</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;600;800&display=swap');
        * { margin: 0; padding: 0; box-sizing: border-box; font-family: 'Poppins', sans-serif; }
        body {
            background-color: #0a0b10; color: white;
            display: flex; flex-direction: column; align-items: center;
            min-height: 100vh; padding: 25px 20px;
        }
        .hidden { display: none !important; }

        /* --- STEP 1: HALL / SEATS --- */
        #stepSeats { width: 100%; display: flex; flex-direction: column; align-items: center; }
        .screen-container { perspective: 500px; margin-bottom: 40px; width: 100%; display: flex; flex-direction: column; align-items: center; }
        .screen {
            background-color: #fff; height: 12px; width: 85%; max-width: 600px; margin: 15px 0;
            transform: rotateX(-45deg); box-shadow: 0 8px 50px rgba(0, 132, 255, 1); border-radius: 10px;
        }
        .screen-text { font-size: 18px; color: #0084ff; text-transform: uppercase; letter-spacing: 12px; font-weight: 800; }

        .hall-layout { display: flex; align-items: flex-start; gap: 20px; padding: 15px; overflow-x: auto; max-width: 100%; }
        .row-labels { display: grid; grid-template-rows: repeat(10, 1fr); gap: 12px; }
        .row-label { height: 35px; display: flex; align-items: center; font-weight: 800; color: #0084ff; font-size: 18px; }
        .seating-area { display: grid; grid-template-columns: repeat(10, 1fr); gap: 12px; }
        .seat {
            width: 40px; height: 35px; background-color: #1e2329; border: 1px solid #2d333b;
            border-top-left-radius: 10px; border-top-right-radius: 10px; cursor: pointer;
            transition: 0.3s; display: flex; justify-content: center; align-items: center;
            font-size: 11px; color: rgba(255,255,255,0.5); font-weight: 600;
        }
        .seat.selected { background-color: #0084ff !important; color: white !important; box-shadow: 0 0 20px rgba(0, 132, 255, 1); border-color: white; }
        .seat.occupied { background-color: #2a1212; border-color: #ff4d4d; color: #ff4d4d; cursor: not-allowed; opacity: 0.8; }

        .legend { display: flex; gap: 30px; margin: 25px 0; padding: 12px 30px; background: #161b22; border-radius: 50px; border: 1px solid rgba(0, 132, 255, 0.2); }
        .item { display: flex; align-items: center; font-size: 14px; gap: 8px; }
        .leg-seat { width: 18px; height: 16px; border-radius: 4px; background-color: #1e2329; border: 1px solid #2d333b; }
        .leg-selected { background-color: #0084ff; box-shadow: 0 0 10px #0084ff; }
        .leg-occupied { background-color: #2a1212; border-color: #ff4d4d; color: #ff4d4d; display: flex; justify-content: center; align-items: center; font-size: 10px; }

        .summary-card {
            background: #161b22; padding: 25px; border-radius: 20px; width: 100%;
            max-width: 450px; border: 1px solid rgba(0, 132, 255, 0.3); text-align: center;
        }
        .info-row { display: flex; justify-content: space-between; margin-bottom: 10px; font-size: 15px; }
        #totalPrice { color: #00ff88; font-size: 28px; font-weight: 800; display: block; margin: 15px 0; }

        /* --- STEP 2: PAYMENT --- */
        .payment-container {
            background: #161b22; width: 100%; max-width: 480px; padding: 30px;
            border-radius: 20px; border: 1px solid rgba(0, 132, 255, 0.3);
            box-shadow: 0 15px 35px rgba(0, 0, 0, 0.7); margin-top: 40px;
        }
        .payment-container h2 {
            text-align: center; color: #0084ff; font-weight: 800;
            letter-spacing: 2px; margin-bottom: 25px; text-transform: uppercase;
        }
        .summary-box {
            background: rgba(255, 255, 255, 0.03); padding: 20px;
            border-radius: 15px; margin-bottom: 25px; border-left: 4px solid #0084ff;
        }
        .summary-box p { display: flex; justify-content: space-between; margin-bottom: 8px; font-size: 14px; color: #aaa; }
        .summary-box p span { color: white; font-weight: 600; text-align: right; }
        .total-price { font-size: 22px !important; color: #00ff88 !important; margin-top: 10px; border-top: 1px solid #333; padding-top: 10px; }

        .payment-options { display: grid; grid-template-columns: 1fr 1fr; gap: 12px; margin-bottom: 25px; }
        .method {
            position: relative; background: #1c2128; border: 2px solid #2d333b;
            padding: 15px; border-radius: 12px; cursor: pointer; text-align: center;
            transition: 0.3s; display: flex; flex-direction: column; align-items: center; gap: 10px;
        }
        .method i { font-size: 20px; color: #0084ff; }
        .method span { font-size: 12px; font-weight: 600; }
        .method input { position: absolute; opacity: 0; }
        .method:hover, .method.active { border-color: #0084ff; background: rgba(0, 132, 255, 0.1); box-shadow: 0 0 15px rgba(0, 132, 255, 0.3); }

        .confirm-btn {
            width: 100%; padding: 14px; background: #0084ff; color: white; border: none;
            border-radius: 12px; font-weight: 600; font-size: 15px; cursor: pointer;
            text-transform: uppercase; transition: 0.3s;
        }
        .confirm-btn:hover { background: #00a2ff; transform: scale(1.02); }
        .back-btn {
            width: 100%; padding: 12px; margin-top: 12px; background: transparent;
            color: #aaa; border: 1px solid #2d333b; border-radius: 12px;
            font-weight: 600; cursor: pointer; transition: 0.3s;
        }
        .back-btn:hover { color: white; border-color: #0084ff; }
        .error-banner { color: #ff4d4d; text-align: center; margin-bottom: 15px; font-size: 13px; }
    </style>
</head>
<body>

    <?php echo $bookingScript; ?>

    <!-- STEP 1: SEAT SELECTION -->
    <div id="stepSeats">
        <h2 id="movieTitle" style="margin-bottom: 20px; color: #0084ff;">Select Your Seats</h2>

        <div class="screen-container">
            <p class="screen-text">SCREEN</p>
            <div class="screen"></div>
        </div>

        <div class="hall-layout">
            <div class="row-labels" id="rowLabels"></div>
            <div class="seating-area" id="seatContainer"></div>
        </div>

        <div class="legend">
            <div class="item"><div class="leg-seat"></div> Available</div>
            <div class="item"><div class="leg-seat leg-selected"></div> Selected</div>
            <div class="item"><div class="leg-seat leg-occupied"><i class="fas fa-times"></i></div> Sold</div>
        </div>

        <div class="summary-card">
            <div class="info-row"><span>Seats:</span> <span id="selectedSeatsList">-</span></div>
            <div class="info-row"><span>Amount:</span> <span id="count">0</span></div>
            <span id="totalPrice">RM 0.00</span>
            <button type="button" class="confirm-btn" id="toPaymentBtn">Proceed to Payment</button>
            <button type="button" class="back-btn" id="addTicketToCartBtn" style="border-color: #0084ff; color: #0084ff;"> <i class="fas fa-cart-plus"></i> Add Tickets to Cart
</button>
        </div>
    </div>

    <!-- STEP 2: PAYMENT -->
    <form method="POST" action="booking.php" id="stepPayment" class="payment-container hidden">
        <h2>Secure Payment</h2>

        <?php if ($errorMsg): ?>
            <p class="error-banner"><?php echo htmlspecialchars($errorMsg); ?></p>
        <?php endif; ?>

        <!-- Hidden fields sent to MySQL -->
        <input type="hidden" name="customer_email" id="inputEmail">
        <input type="hidden" name="movie_title" id="inputMovie">
        <input type="hidden" name="show_date" id="inputDate">
        <input type="hidden" name="show_time" id="inputTime">
        <input type="hidden" name="seats" id="inputSeats">
        <input type="hidden" name="total_amount" id="inputTotal">

        <div class="summary-box">
            <p>Movie: <span id="payMovieTitle">No Movie Selected</span></p>
            <p>Date & Time: <span id="payDateTime">-</span></p>
            <p>Seats: <span id="paySelectedSeats">-</span></p>
            <p class="total-price">Total Price <span id="payTotalDisplay">RM 0.00</span></p>
        </div>

        <p style="font-size: 13px; margin-bottom: 12px; color: #888; text-transform: uppercase; letter-spacing: 1px;">Payment Method:</p>
        <div class="payment-options">
            <label class="method">
                <input type="radio" name="payment" value="Online Banking">
                <i class="fas fa-university"></i>
                <span>FPX Banking</span>
            </label>
            <label class="method">
                <input type="radio" name="payment" value="TnG eWallet">
                <i class="fas fa-wallet"></i>
                <span>Touch 'n Go</span>
            </label>
            <label class="method">
                <input type="radio" name="payment" value="Cards">
                <i class="fas fa-credit-card"></i>
                <span>Credit Card</span>
            </label>
            <label class="method">
                <input type="radio" name="payment" value="ShopeePay">
                <i class="fas fa-shopping-bag"></i>
                <span>ShopeePay</span>
            </label>
        </div>

        <button type="submit" class="confirm-btn" id="payBtn">Pay Now</button>
        <button type="button" class="back-btn" id="backToSeatsBtn"><i class="fas fa-arrow-left"></i> Back to Seat Selection</button>
    </form>

    <script>
        const container = document.getElementById('seatContainer');
        const rowLabels = document.getElementById('rowLabels');
        const countDisplay = document.getElementById('count');
        const priceDisplay = document.getElementById('totalPrice');
        const seatListText = document.getElementById('selectedSeatsList');

        const rows = ['A', 'B', 'C', 'D', 'E', 'F', 'G', 'H', 'I', 'J'];
        const seatPrice = 20.00;
        let currentSeats = [];
        let currentTotal = "0.00";

        const movie = localStorage.getItem("selectedMovie") || "Select Seats";
        const date = localStorage.getItem("selectedDate") || "";
        const time = localStorage.getItem("selectedTime") || "";
        document.getElementById('movieTitle').innerText = movie;

        // Render Row Labels
        rows.forEach(r => {
            const lbl = document.createElement('div');
            lbl.className = 'row-label';
            lbl.innerText = r;
            rowLabels.appendChild(lbl);
        });

        // Render 10x10 Seats
        rows.forEach(r => {
            for (let i = 1; i <= 10; i++) {
                const seatId = r + i;
                const seat = document.createElement('div');
                seat.className = 'seat';

                if (Math.random() < 0.2) {
                    seat.classList.add('occupied');
                    seat.innerHTML = '<i class="fas fa-times"></i>';
                } else {
                    seat.innerText = i;
                    seat.setAttribute('data-id', seatId);
                    seat.addEventListener('click', function() {
                        this.classList.toggle('selected');
                        calculateTotal();
                    });
                }
                container.appendChild(seat);
            }
        });

        function calculateTotal() {
            const selected = container.querySelectorAll('.seat.selected');
            currentSeats = Array.from(selected).map(s => s.getAttribute('data-id'));
            currentTotal = (currentSeats.length * seatPrice).toFixed(2);

            countDisplay.innerText = currentSeats.length;
            seatListText.innerText = currentSeats.length > 0 ? currentSeats.join(', ') : "-";
            priceDisplay.innerText = "RM " + currentTotal;
        }

        // Switch to Step 2: Payment & fill hidden form inputs
        document.getElementById('toPaymentBtn').addEventListener('click', () => {
            if (currentSeats.length === 0) {
                alert("Please select at least one seat!");
                return;
            }

            let userEmail = "Guest";
            const rawUser = localStorage.getItem("currentUser");
            if (rawUser) {
                try { userEmail = JSON.parse(rawUser).email || "Guest"; } catch {}
            }

            document.getElementById('inputEmail').value = userEmail;
            document.getElementById('inputMovie').value = movie;
            document.getElementById('inputDate').value = date;
            document.getElementById('inputTime').value = time;
            document.getElementById('inputSeats').value = currentSeats.join(", ");
            document.getElementById('inputTotal').value = currentTotal;

            document.getElementById('payMovieTitle').innerText = movie;
            document.getElementById('payDateTime').innerText = (date || time) ? `${date} ${time}`.trim() : "-";
            document.getElementById('paySelectedSeats').innerText = currentSeats.join(", ");
            document.getElementById('payTotalDisplay').innerText = "RM " + currentTotal;

            document.getElementById('stepSeats').classList.add('hidden');
            document.getElementById('stepPayment').classList.remove('hidden');
            window.scrollTo(0, 0);
        });

        // Add selected movie tickets to the Cart in localStorage
        document.getElementById('addTicketToCartBtn').addEventListener('click', () => {
            if (currentSeats.length === 0) {
                alert("Please select at least one seat!");
                return;
            }

            const cart = JSON.parse(localStorage.getItem('cinemax_cart') || '[]');
            cart.push({
                type: 'ticket',
                name: `🎬 ${movie}`,
                details: `${date} ${time} | Seats: ${currentSeats.join(', ')}`,
                price: parseFloat(currentTotal),
                qty: 1
            });

            localStorage.setItem('cinemax_cart', JSON.stringify(cart));
            alert(`Tickets for ${movie} (${currentSeats.join(', ')}) added to cart!`);
            window.location.href = 'index.html'; // Change to index_2.html if you haven't renamed it
        });

        // Switch back to Step 1: Seats
        document.getElementById('backToSeatsBtn').addEventListener('click', () => {
            document.getElementById('stepPayment').classList.add('hidden');
            document.getElementById('stepSeats').classList.remove('hidden');
        });

        // Payment Method Selection
        const methods = document.querySelectorAll('.method');
        methods.forEach(m => {
            m.addEventListener('click', function() {
                methods.forEach(opt => opt.classList.remove('active'));
                this.classList.add('active');
                this.querySelector('input').checked = true;
            });
        });

        // Validate payment method before form submits to PHP
        document.getElementById('stepPayment').addEventListener('submit', (e) => {
            const selected = document.querySelector('input[name="payment"]:checked');
            if (!selected) {
                e.preventDefault();
                alert("Please select a payment method!");
            }
        });
    </script>
</body>
</html>