<?php
/**
 * Test the updated delivery availability logic
 */

require_once '../includes/db_helper.php';

echo "=== TESTING DELIVERY AVAILABILITY ===\n\n";

// Test coordinates (example book location and user location in Kerala)
$lenderLat = 9.5279; // Kottayam area
$lenderLng = 76.8226;
$borrowerLat = 9.5177; // Nearby location
$borrowerLng = 76.5993;

echo "Testing delivery from:\n";
echo "  Lender (Pickup): ($lenderLat, $lenderLng)\n";
echo "  Borrower (Dropoff): ($borrowerLat, $borrowerLng)\n\n";

$available = checkDeliveryAvailability($lenderLat, $lenderLng, $borrowerLat, $borrowerLng);

if ($available) {
    echo "✅ DELIVERY AVAILABLE!\n";
    echo "The system found at least one agent who can service this route.\n";
} else {
    echo "❌ NO DELIVERY AVAILABLE\n";
    echo "No agents found within 20km service radius.\n";
}

echo "\n";
echo "If this still shows 'NO DELIVERY', check:\n";
echo "1. Are agents accepting deliveries? (is_accepting_deliveries = 1)\n";
echo "2. Do agents have service_start coordinates set?\n";
echo "3. Run check_agents.php to see agent details\n";
